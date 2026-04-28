import asyncio
import json
import logging
from core import db
from services.chat_processor import ChatProcessor
from services.ai_engine import EmbeddingsEngine

logging.basicConfig(level=logging.INFO, format="%(asctime)s - [WORKER] - %(message)s")
logger = logging.getLogger(__name__)

async def main():
    logger.info("Arrancando Worker Desacoplado...")
    
    # Init Conexiones INDEPENDIENTES para este proceso
    await db.connect_dbs()
    EmbeddingsEngine.load_model()
    
    logger.info("Escuchando cola 'whatsapp_queue' en Redis...")

    # MÓDULO 4: Límite global concurrencia (Evitar quemar RAM/APIs simultáneas)
    MAX_CONCURRENT_TASKS = 20
    semaphore = asyncio.Semaphore(MAX_CONCURRENT_TASKS)

    async def _safe_process(msg):
        try:
            await asyncio.wait_for(semaphore.acquire(), timeout=30.0)
            try:
                await ChatProcessor.process_message_async(msg)
            except Exception as e:
                logger.error(f"Worker Error Crítico en process_message_async: {e}", exc_info=True)
                phone = msg.get('from')
                if phone:
                    try:
                        await ChatProcessor.send_fallback_message(phone)
                    except Exception as fallback_error:
                        logger.error(f"No se pudo enviar fallback a {phone}: {fallback_error}")
            finally:
                semaphore.release()
        except asyncio.TimeoutError:
            logger.error("⚠️ SEMAFORO TIMEOUT: Servidor saturado. Mensaje de %s no procesado.", msg.get('from', 'unknown'))

    while True:
        try:
            if not db.redis_client:
                logger.error("No hay Redis, reintentando...")
                await asyncio.sleep(5)
                continue
                
            item = await db.redis_client.blpop("whatsapp_queue", 0)
            
            if item:
                queue_name, raw_data = item
                msg_data = json.loads(raw_data)
                
                # Despacha la tarea envolviéndola en el Semáforo.
                # Nota Arquitectónica: asyncio.create_task() avanza si creamos la Task.
                # Como el wrapper restringe el acquire, las Tasks nacerán y esperarán silenciosas
                # sin rebasar el límite de las 20 en ejecución llameante.
                asyncio.create_task(_safe_process(msg_data))
                
        except Exception as e:
            logger.error(f"Error procesando cola: {e}")
            await asyncio.sleep(1)

if __name__ == "__main__":
    asyncio.run(main())
