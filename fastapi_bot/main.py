import json
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import PlainTextResponse
from fastapi.middleware.cors import CORSMiddleware

from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded

from core import db
from core.config import settings
from services.ai_engine import EmbeddingsEngine

# Configuración básica de Logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger(__name__)

# Configuración de Rate Limiting
limiter = Limiter(key_func=get_remote_address)

@asynccontextmanager
async def lifespan(app: FastAPI):
    # ON_STARTUP
    await db.connect_dbs()
    EmbeddingsEngine.load_model()
    yield
    # ON_SHUTDOWN
    await db.disconnect_dbs()

app = FastAPI(
    title="DarkoSync AI Brain", 
    description="Microservicio de Ventas Asíncrono con RAG Triple Candado",
    lifespan=lifespan
)

# Registramos Middleware de Rate Limiting y manejador de Errores
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# Configuración CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/health")
async def check_health():
    """
    Verifica de manera Asíncrona la conectividad Cero Error con Redis y PostgreSQL (Ambas DBs Lógicas)
    """
    health_status = {"status": "healthy"}
    services = {}

    try:
        # Verificar Redis
        if db.redis_client:
            await db.redis_client.ping()
            services['redis'] = "ok"
        else:
            services['redis'] = "not_loaded"

        # Verificar ecommerce_db
        if db.ecommerce_pool:
            async with db.ecommerce_pool.acquire() as conn:
                await conn.fetchval('SELECT 1')
            services['ecommerce_db'] = "ok"
        else:
            services['ecommerce_db'] = "not_loaded"

        # Verificar bot_rag_db y Mitigación Riesgo 2
        if db.rag_pool:
            async with db.rag_pool.acquire() as conn:
                await conn.fetchval('SELECT 1')
                # Risk 2: Validar la antiguedad del último embedding
                last_updated = await conn.fetchval('SELECT MAX(updated_at) FROM product_embeddings')
                
                # Check pendings in queue
                pending_retries = await conn.fetchval('SELECT COUNT(*) FROM product_sync_queue')
                
            services['bot_rag_db'] = {"status": "ok", "last_sync": str(last_updated) if last_updated else "Nunca", "pending_retries": pending_retries}
        else:
            services['bot_rag_db'] = "not_loaded"

    except Exception as e:
        health_status["status"] = "unhealthy"
        health_status["error"] = str(e)
        logger.error(f"Healthcheck failed: {e}")

    health_status["services"] = services
    return health_status


@app.get("/webhook/whatsapp")
async def verify_whatsapp_webhook(request: Request):
    """
    Endpoint para verificación de Meta Developers
    """
    params = request.query_params
    mode = params.get('hub.mode')
    token = params.get('hub.verify_token')
    challenge = params.get('hub.challenge')

    if mode == 'subscribe' and token == settings.WHATSAPP_VERIFY_TOKEN:
        logger.info("Webhook Verified by Meta Successfully!")
        return PlainTextResponse(content=challenge, status_code=200)
    
    raise HTTPException(status_code=403, detail="Error, wrong validation token")


@app.post("/webhook/whatsapp")
@limiter.limit("100/minute")
async def receive_whatsapp_message(request: Request):
    """
    El canal Asíncrono de Ingesta. Parseará el JSON entrante y empujará a una Cola Redis (Trabajo no Bloqueante).
    """
    try:
        body = await request.json()
    except Exception:
        raise HTTPException(status_code=400, detail="Invalid JSON Payload")

    entries = body.get('entry', [])
    for entry in entries:
        changes = entry.get('changes', [])
        for change in changes:
            value = change.get('value', {})
            
            # Defensa 1: Ignorar actualizaciones de estatus (tickets azules o entregados)
            if 'statuses' in value:
                continue

            messages = value.get('messages', [])
            
            for msg in messages:
                # Defensa 2: Por ahora solo procesamos mensajes de texto.
                if msg.get('type') != 'text':
                    continue

                msg_data = {
                    'id': msg.get('id'),
                    'from': msg.get('from'),
                    'timestamp': msg.get('timestamp'),
                    'type': msg.get('type'),
                    'text': msg.get('text', {}).get('body', ''),
                    'wamid': msg.get('id')
                }

                # RPUSH es FIFO (Primero en Entrar, Primero en Salir). 
                # Meta los envía en orden, debemos desencolarlos en orden.
                if db.redis_client:
                    await db.redis_client.rpush("whatsapp_queue", json.dumps(msg_data))
                    logger.info(f"Mensaje encolado en Redis desde {msg_data['from']}")

    # Importante retornar "received" rápidamente o Meta nos castigará bajando el límite de la API
    return {"status": "received"}


@app.post("/chat/test")
async def test_rag_deepseek(request: Request):
    """
    Endpoint de depuración: Llama directamente al procesador asíncrono evadiendo la Cola Redis.
    """
    body = await request.json()
    from services.chat_processor import ChatProcessor
    
    # Creamos estructura mínima simulada de WA
    msg_data = {
        'from': body.get('phone', 'test_dev'),
        'text': body.get('message', 'Hola')
    }
    
    # Ejecutamos el procesador en modo test_mode=True para que no guarde basura en DB/Redis
    response = await ChatProcessor.process_message_async(msg_data, test_mode=True)
    return response


# ==========================================
# MÓDULO 4: ENDPOINTS INTERNOS DE SINCRONIZACIÓN B2B
# ==========================================

@app.post("/internal/sync-products")
async def internal_sync_products(request: Request):
    """
    Recibe un POST silencioso desde Laravel Observer (Cuando el Admin edita producto).
    Actualiza el Vector RAG inmediatamente en bot_rag_db.
    """
    payload = await request.json()
    product_id = payload.get("id")
    text_to_vectorize = payload.get("vector_text")
    nombre = payload.get("nombre", "Sin Nombre")
    categoria = payload.get("categoria", "General")
    
    if not text_to_vectorize or not product_id:
        raise HTTPException(status_code=400, detail="Faltan datos para vectorizar")
        
    try:
        vector = await EmbeddingsEngine.generate_embedding_async(text_to_vectorize)
        vector_str = "[" + ",".join(map(str, vector)) + "]"
        
        query = """
            INSERT INTO product_embeddings (product_id, nombre, categoria, embedding)
            VALUES ($1, $2, $3, $4::vector)
            ON CONFLICT (product_id) DO UPDATE 
            SET embedding = EXCLUDED.embedding, nombre = EXCLUDED.nombre, 
                categoria = EXCLUDED.categoria, updated_at = CURRENT_TIMESTAMP
        """
        async with db.rag_pool.acquire() as conn:
            await conn.execute(query, product_id, nombre, categoria, vector_str)
            
            # Borrar de la cola de reintentos si existía
            await conn.execute("DELETE FROM product_sync_queue WHERE product_id = $1", product_id)
            
        logger.info(f"RAG Sincronizado para producto_id {product_id}")
        return {"status": "synced", "product_id": product_id}
    except Exception as e:
        logger.error(f"Error vectorizando producto {product_id}, encolando para reintento: {e}")
        query_queue = """
            INSERT INTO product_sync_queue (product_id, nombre, categoria, vector_text, error_log)
            VALUES ($1, $2, $3, $4, $5)
            ON CONFLICT (product_id) DO UPDATE
            SET error_log = EXCLUDED.error_log, next_retry_at = CURRENT_TIMESTAMP + interval '10 seconds'
        """
        async with db.rag_pool.acquire() as conn:
            await conn.execute(query_queue, product_id, nombre, categoria, text_to_vectorize, str(e))
        return {"status": "queued_for_retry", "product_id": product_id}


@app.post("/internal/create-order")
async def internal_sync_order(request: Request):
    """
    Endpoint para separar la analítica: Registra una orden creada.
    """
    payload = await request.json()
    uuid = payload.get("uuid")
    phone = payload.get("phone")
    total = payload.get("total")
    
    if not uuid:
        raise HTTPException(status_code=400, detail="Falta UUID")
        
    query = """
        INSERT INTO orders_sync (order_uuid, phone, total)
        VALUES ($1, $2, $3)
        ON CONFLICT (order_uuid) DO NOTHING
    """
    async with db.rag_pool.acquire() as conn:
        await conn.execute(query, uuid, phone, total)
        
    logger.info(f"Orden B2B Sincronizada en bot_rag_db: {uuid}")
    return {"status": "order_synced"}


