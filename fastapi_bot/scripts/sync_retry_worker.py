import asyncio
import logging
import sys
import os

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from core import db
from services.ai_engine import EmbeddingsEngine

logging.basicConfig(level=logging.INFO, format="%(asctime)s - [SYNC_RETRY] - %(message)s")
logger = logging.getLogger(__name__)

async def process_retries():
    # Asegurarnos de que estamos conectados
    if not db.rag_pool:
        logger.error("No database connection available.")
        return

    MAX_ATTEMPTS = 3

    # Buscar items pendientes para reintento
    query = """
        SELECT id, product_id, nombre, categoria, vector_text, attempts 
        FROM product_sync_queue 
        WHERE attempts < $1 AND next_retry_at <= CURRENT_TIMESTAMP
        ORDER BY next_retry_at ASC
        LIMIT 10
    """
    
    async with db.rag_pool.acquire() as conn:
        items = await conn.fetch(query, MAX_ATTEMPTS)
        
        if not items:
            return

        logger.info(f"Encontrados {len(items)} productos pendientes de reintento.")

        for item in items:
            product_id = item['product_id']
            logger.info(f"Reintentando sincronización para producto_id {product_id} (Intento {item['attempts'] + 1})")
            
            try:
                vector = await EmbeddingsEngine.generate_embedding_async(item['vector_text'])
                vector_str = "[" + ",".join(map(str, vector)) + "]"
                
                # Insertar / Actualizar embedding principal
                insert_emb = """
                    INSERT INTO product_embeddings (product_id, nombre, categoria, embedding)
                    VALUES ($1, $2, $3, $4::vector)
                    ON CONFLICT (product_id) DO UPDATE 
                    SET embedding = EXCLUDED.embedding, nombre = EXCLUDED.nombre, 
                        categoria = EXCLUDED.categoria, updated_at = CURRENT_TIMESTAMP
                """
                
                async with conn.transaction():
                    await conn.execute(insert_emb, product_id, item['nombre'], item['categoria'], vector_str)
                    # Borrar de la cola de reintentos
                    await conn.execute("DELETE FROM product_sync_queue WHERE id = $1", item['id'])
                    
                logger.info(f"✅ Reintento exitoso para producto_id {product_id}. Eliminado de la cola.")
                
            except Exception as e:
                logger.error(f"❌ Falló el reintento para producto_id {product_id}: {e}")
                # Incrementar intentos y programar el próximo reintento (backoff)
                new_attempts = item['attempts'] + 1
                backoff_seconds = 10 * new_attempts # 10s, 20s, 30s
                
                update_queue = f"""
                    UPDATE product_sync_queue 
                    SET attempts = $1, error_log = $2, next_retry_at = CURRENT_TIMESTAMP + interval '{backoff_seconds} seconds'
                    WHERE id = $3
                """
                await conn.execute(update_queue, new_attempts, str(e), item['id'])

async def run_worker():
    logger.info("Iniciando Sync Retry Worker...")
    await db.connect_dbs()
    EmbeddingsEngine.load_model()
    
    try:
        while True:
            await process_retries()
            await asyncio.sleep(5) # Revisar la cola cada 5 segundos
    except KeyboardInterrupt:
        logger.info("Worker detenido por usuario.")
    finally:
        await db.disconnect_dbs()

if __name__ == "__main__":
    asyncio.run(run_worker())
