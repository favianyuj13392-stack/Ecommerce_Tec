import asyncpg
import redis.asyncio as redis
from contextlib import asynccontextmanager
from .config import settings

# Global connection pools represent the 'async/await' standard layer
ecommerce_pool = None
rag_pool = None
redis_client = None

async def connect_dbs():
    global ecommerce_pool, rag_pool, redis_client
    
    # Pool para datos estáticos/RAG
    rag_pool = await asyncpg.create_pool(dsn=settings.BOT_RAG_DB_URL, min_size=1, max_size=10)
    
    # Pool para datos vivos (precios, stock)
    ecommerce_pool = await asyncpg.create_pool(dsn=settings.ECOMMERCE_DB_URL, min_size=1, max_size=10)
    
    # Redis para Session & Cart info
    redis_client = redis.from_url(settings.REDIS_URL, decode_responses=True)
    print("✅ Todas las conexiones a DB (RAO y Transaccional) + Redis iniciadas asíncronamente.")

async def disconnect_dbs():
    global ecommerce_pool, rag_pool, redis_client
    if rag_pool:
        await rag_pool.close()
    if ecommerce_pool:
        await ecommerce_pool.close()
    if redis_client:
        await redis_client.close()
