import asyncio
import os
import sys
from dotenv import load_dotenv
import asyncpg

# Agregar el directorio padre (fastapi_bot) al PYTHONPATH
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from core.config import settings

async def migrate():
    load_dotenv()
    
    print(f"🔄 Conectando a bot_rag_db en: {settings.BOT_RAG_DB_URL}")
    try:
        conn = await asyncpg.connect(settings.BOT_RAG_DB_URL)
    except Exception as e:
        print(f"❌ Error conectando a DB: {e}")
        return

    print("🔄 Creando tabla product_sync_queue y sus índices...")
    
    try:
        # Iniciar transacción
        async with conn.transaction():
            await conn.execute("""
                CREATE TABLE IF NOT EXISTS product_sync_queue (
                    id SERIAL PRIMARY KEY,
                    product_id INTEGER NOT NULL,
                    nombre VARCHAR(255),
                    categoria VARCHAR(255),
                    vector_text TEXT NOT NULL,
                    attempts INTEGER DEFAULT 0,
                    error_log TEXT,
                    next_retry_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            """)
            
            await conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_product_sync_retry_at ON product_sync_queue (next_retry_at);
            """)
            
            await conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_product_sync_attempts ON product_sync_queue (attempts);
            """)
            
            # Constraint para evitar que el mismo producto se encole multiples veces
            await conn.execute("""
                ALTER TABLE product_sync_queue 
                DROP CONSTRAINT IF EXISTS unique_product_sync;
            """)
            
            await conn.execute("""
                ALTER TABLE product_sync_queue 
                ADD CONSTRAINT unique_product_sync UNIQUE (product_id);
            """)
            
        print("✅ Migración completada exitosamente.")
    except Exception as e:
        print(f"❌ Error en migración, haciendo ROLLBACK: {e}")
        # La transacción hace rollback automático en caso de excepción
        # Pero podemos dropear la tabla explícitamente como contingencia extra si fue requerida
        try:
            await conn.execute("DROP TABLE IF EXISTS product_sync_queue CASCADE;")
            print("⚠️ Tabla product_sync_queue eliminada para mantener limpieza tras fallo.")
        except Exception as drop_error:
            print(f"⚠️ Error intentando eliminar la tabla fallida: {drop_error}")
        raise e
    finally:
        await conn.close()

if __name__ == "__main__":
    asyncio.run(migrate())
