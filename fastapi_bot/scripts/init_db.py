import asyncio
import asyncpg
import sys
import os

# Ajustar path temporalmente para importar settings
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from core.config import settings

async def init_db():
    print(f"⌛ Conectando a {settings.BOT_RAG_DB_URL}...")
    conn = await asyncpg.connect(settings.BOT_RAG_DB_URL)
    
    try:
        # Asegurar extensión pgvector
        await conn.execute('CREATE EXTENSION IF NOT EXISTS vector;')
        
        # MÓDULO 4: TABLA PRODUCT_EMBEDDINGS
        await conn.execute('''
            CREATE TABLE IF NOT EXISTS product_embeddings (
                id SERIAL PRIMARY KEY,
                product_id INTEGER UNIQUE NOT NULL,
                nombre VARCHAR(255) NOT NULL,
                categoria VARCHAR(100),
                embedding vector(384), -- all-MiniLM-L6-v2 usa 384 dimensiones
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ''')
        
        # MÓDULO 4: TABLA CHAT_HISTORY (Para RAG si superamos Redis)
        await conn.execute('''
            CREATE TABLE IF NOT EXISTS chat_history (
                id SERIAL PRIMARY KEY,
                phone VARCHAR(50) NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ''')
        
        # MÓDULO 4: TABLA ORDERS_SYNC (Para analíticas disjuntas de Conversión AI)
        await conn.execute('''
            CREATE TABLE IF NOT EXISTS orders_sync (
                id SERIAL PRIMARY KEY,
                order_uuid VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(50) NOT NULL,
                total DECIMAL(10, 2) NOT NULL,
                is_paid BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ''')
        
        print("✅ Migraciones de estructura FastApi completadas exitosamente en bot_rag_db.")
        
    except Exception as e:
        print(f"❌ Error creando tablas: {e}")
    finally:
        await conn.close()

if __name__ == "__main__":
    asyncio.run(init_db())
