from sentence_transformers import SentenceTransformer
from cachetools import LRUCache, cached
import threading
from core.config import settings

# Bloqueo para evitar colisiones multihilo si por accidente concurrent workers piden al mismo tiempo el modelo en ram.
_cache_lock = threading.Lock()

class EmbeddingsEngine:
    """
    Motor en memoria para IA. Cumple el mandato de 'Estrategia de Embeddings LOCAL'.
    """
    _model = None

    @classmethod
    def load_model(cls):
        print(f"⌛ Cargando modelo NLP en RAM: {settings.LOCAL_EMBEDDING_MODEL}...")
        cls._model = SentenceTransformer(settings.LOCAL_EMBEDDING_MODEL)
        print("✅ Modelo cargado y listo para inferencia instántanea.")

    @classmethod
    @cached(cache=LRUCache(maxsize=1000), lock=_cache_lock)
    def _generate_embedding_sync(cls, text: str) -> list[float]:
        if not cls._model:
            raise RuntimeError("El modelo no está cargado. Llama a load_model() primero.")
        return cls._model.encode(text).tolist()

    @classmethod
    async def generate_embedding_async(cls, text: str) -> list[float]:
        """ Wrapper asíncrono para liberar el Event Loop mientras calcula el Tensor """
        import asyncio
        loop = asyncio.get_running_loop()
        return await loop.run_in_executor(None, cls._generate_embedding_sync, text)

    @classmethod
    async def search_similar_products(cls, vector: list[float], limit: int = 5) -> list[dict]:
        """ CANDADO A: Búsqueda Semántica Vectorial en DB Independiente """
        from core.db import rag_pool
        import json
        
        # pgvector expects a string like '[0.1, 0.2, ...]'
        vector_str = "[" + ",".join(map(str, vector)) + "]"
        
        query = """
            SELECT product_id, nombre, categoria, embedding <=> $1::vector AS distance
            FROM product_embeddings
            ORDER BY distance ASC
            LIMIT $2
        """
        async with rag_pool.acquire() as conn:
            rows = await conn.fetch(query, vector_str, limit)
            
        return [dict(row) for row in rows]
