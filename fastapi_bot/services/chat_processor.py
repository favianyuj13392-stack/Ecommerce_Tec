import json
import logging
import httpx
import re
import asyncio
import uuid
from datetime import datetime
from core import db
from core.config import settings
from services.ai_engine import EmbeddingsEngine

logger = logging.getLogger(__name__)

class ChatProcessor:
    @staticmethod
    def contains_prohibited_words(text: str) -> bool:
        """
        Seguridad Nivel 1. Mantenido desde Laravel.
        """
        prohibited = [
            'gay', 'maricón', 'maricon', 'puto', 'joto', 'piter gay', 
            'idiota', 'estúpido', 'estupido', 'imbécil', 'imbecil', 
            'mierda', 'carajo', 'pendejo', 'pinga', 'verga', 'cojudo'
        ]
        lower_text = text.lower().strip()
        for word in prohibited:
            if re.search(r'\b' + re.escape(word) + r'\b', lower_text) or 'piter gay' in lower_text:
                return True
        return False

    @classmethod
    async def get_real_time_stock(cls, product_ids: list[int]) -> list:
        """ 
        CANDADO B: Hidratación SQL con la fuente de Verdad.
        Intenta primero product_variants; si está vacío, usa la tabla products directamente.
        """
        if not product_ids:
            return []
            
        placeholders = ",".join(f"${i+1}" for i in range(len(product_ids)))
        
        async with db.ecommerce_pool.acquire() as conn:
            # Intento 1: buscar con variantes
            query_variants = f"""
                SELECT p.id, p.slug, p.nombre, p.descripcion,
                       pv.variant_attributes as atributos, pv.price as precio, pv.stock
                FROM product_variants pv
                JOIN products p ON p.id = pv.product_id
                WHERE p.id IN ({placeholders}) AND pv.stock > 0
            """
            rows = await conn.fetch(query_variants, *product_ids)
            
            if rows:
                return [dict(r) for r in rows]
            
            # Fallback: el producto no tiene variantes — leer directo de products
            query_products = f"""
                SELECT id, slug, nombre, descripcion,
                       attributes as atributos, precio, 999 as stock
                FROM products
                WHERE id IN ({placeholders})
            """
            rows = await conn.fetch(query_products, *product_ids)
            return [dict(r) for r in rows]

    @classmethod
    async def get_or_create_lead(cls, phone: str, name: str = 'Cliente') -> dict:
        """
        Emula a Lead::firstOrCreate de Laravel e incrementa interacciones
        """
        query = "SELECT id, is_ai_enabled FROM leads WHERE whatsapp_id = $1"
        
        async with db.ecommerce_pool.acquire() as conn:
            lead = await conn.fetchrow(query, phone)
            
            if not lead:
                insert_q = """
                    INSERT INTO leads (whatsapp_id, name, interaction_count, is_ai_enabled, created_at, updated_at)
                    VALUES ($1, $2, 1, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id, is_ai_enabled
                """
                lead = await conn.fetchrow(insert_q, phone, name)
            else:
                update_q = "UPDATE leads SET interaction_count = interaction_count + 1, updated_at = CURRENT_TIMESTAMP WHERE id = $1"
                await conn.execute(update_q, lead['id'])
                
        return dict(lead)

    @classmethod
    async def log_whatsapp_message(cls, lead_id: int, message_id: str, body: str, direction: str, source: str, tokens: int = 0):
        """ Registra el mensaje en postgres asíncronamente """
        query = """
            INSERT INTO whatsapp_messages (lead_id, message_id, body, direction, source, tokens_used, created_at, updated_at)
            VALUES ($1, $2, $3, $4, $5, $6, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        """
        try:
            async with db.ecommerce_pool.acquire() as conn:
                await conn.execute(query, lead_id, message_id or '', body, direction, source, tokens)
        except Exception as e:
            logger.error(f"Error guardando historial en DB (no bloqueante): {e}")

    @classmethod
    async def validate_order_items(cls, items: list) -> tuple[bool, str]:
        """ Verifica que los items existen, tienen stock y cantidad válida """
        if not items:
            return False, "El carrito está vacío."
            
        pure_slugs = []
        for itm in items:
            slug = itm.get('slug')
            qty = itm.get('qty', 1)
            if not slug or type(qty) not in [int, float] or qty <= 0:
                return False, "Casero, no pude procesar las cantidades correctamente. Empecemos de nuevo."
            pure_slugs.append(slug)
            
        pure_slugs = list(set(pure_slugs)) # Unique
        placeholders = ",".join(f"${i+1}" for i in range(len(pure_slugs)))
        
        async with db.ecommerce_pool.acquire() as conn:
            # Check products exist and have stock either in variants or products fallback
            query = f"""
                SELECT p.slug
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE p.slug IN ({placeholders}) 
                AND (pv.stock > 0 OR (pv.id IS NULL AND p.precio IS NOT NULL))
            """
            rows = await conn.fetch(query, *pure_slugs)
            valid_slugs = {row['slug'] for row in rows}
            
            if len(valid_slugs) != len(pure_slugs):
                return False, "Estoy revisando el inventario y algunos productos ya no están disponibles. ¿Podemos rearmar el pedido?"
                
        return True, ""

    @classmethod
    async def create_order(cls, lead_id: int, items: list, cart_redis_key: str):
        """ Crea la orden en PostgreSQL, limpia carrito y retorna el Checkout URL """
        order_uuid = str(uuid.uuid4())
        total = 0.0
        
        pure_slugs = [item.get('slug') for item in items if item.get('slug')]
        if not pure_slugs:
            return None, 0.0
            
        placeholders = ",".join(f"${i+1}" for i in range(len(pure_slugs)))
        
        async with db.ecommerce_pool.acquire() as conn:
            # Intento 1: precio desde variantes (JOIN)
            query_variant_prices = f"""
                SELECT p.slug, pv.price as precio
                FROM product_variants pv
                JOIN products p ON p.id = pv.product_id
                WHERE p.slug IN ({placeholders})
                ORDER BY pv.price ASC
            """
            variant_rows = await conn.fetch(query_variant_prices, *pure_slugs)
            prices_map = {row['slug']: float(row['precio']) for row in variant_rows}
            
            # Fallback: precio directo de products.precio para los que no tengan variante
            missing_slugs = [s for s in pure_slugs if s not in prices_map]
            if missing_slugs:
                miss_ph = ",".join(f"${i+1}" for i in range(len(missing_slugs)))
                product_rows = await conn.fetch(
                    f"SELECT slug, precio FROM products WHERE slug IN ({miss_ph})",
                    *missing_slugs
                )
                for row in product_rows:
                    prices_map[row['slug']] = float(row['precio'])
            
            # Calcular total
            valid_items = []
            for itm in items:
                slug = itm.get('slug')
                qty = max(1, int(itm.get('qty', 1)))
                if slug in prices_map:
                    total += prices_map[slug] * qty
                    valid_items.append(itm)
                else:
                    logger.warning(f"Slug desconocido ignorado en orden: '{slug}'")
            
            if not valid_items:
                logger.error("No se encontraron slugs válidos. Orden cancelada.")
                return None, 0.0
            
            # Insert Order con items válidos solamente
            query = """
                INSERT INTO orders (uuid, lead_id, items, total, total_amount, status, type, payment_method, created_at, updated_at)
                VALUES ($1, $2, $3, $4, $4, 'pending', 'informal', 'manual_qr', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            """
            await conn.execute(query, order_uuid, lead_id, json.dumps(valid_items), total)
            
        # Vaciar Redis Cart
        if db.redis_client:
            await db.redis_client.delete(cart_redis_key)
            
        url = f"{settings.APP_URL.rstrip('/')}/checkout/{order_uuid}"
        return url, total

    @classmethod
    async def process_message_async(cls, msg_data: dict, test_mode: bool = False):
        try:
            text = msg_data.get('text', '')
            phone = msg_data.get('from', 'test_user')
            wamid = msg_data.get('wamid', '')

            logger.info(f"Procesando msg asíncrono para {phone}: {text[:30]}...")

            # 1. Registro Inbound y Verificación de Estado
            lead = await cls.get_or_create_lead(phone)
            
            if not test_mode:
                await cls.log_whatsapp_message(lead['id'], wamid, text, 'inbound', 'user')
                
            if not lead.get('is_ai_enabled', True):
                logger.info(f"Intervención humana para {phone}. IA silenciada.")
                return {"status": "human_intervention_active"}

            is_troll = cls.contains_prohibited_words(text)
            
            # Recuperar Historial Corto (Chat redis)
            redis_chat_key = f"chat:{phone}"
            cart_redis_key = f"cart:{phone}"
            
            raw_history = []
            cart_redis = []
            
            if db.redis_client:
                raw_history = await db.redis_client.lrange(redis_chat_key, 0, -1)
                cart_raw = await db.redis_client.get(cart_redis_key)
                if cart_raw:
                    cart_redis = json.loads(cart_raw)

            history = [json.loads(h) for h in raw_history] if raw_history else []

            # 2. RAG Triple Candado
            vector = await EmbeddingsEngine.generate_embedding_async(text)
            similar_products = await EmbeddingsEngine.search_similar_products(vector, limit=3)
            product_ids = [p['product_id'] for p in similar_products]
            
            # CANDADO B
            sql_truth = await cls.get_real_time_stock(product_ids)
            
            rag_context_str = "No tenemos productos relevantes con stock en este momento."
            if sql_truth:
                lines = []
                for p in sql_truth:
                    lines.append(f"Producto: {p['nombre']} | Slug: {p['slug']} | Precio: {p['precio']} | Stock: {p['stock']} | Variante: {p['atributos']}")
                rag_context_str = "\\n".join(lines)

            # 3. Prompt de Paridad Laravel
            system_prompt = (
                "Eres DARKO, el vendedor estrella de DARKOSYNC.AI, tienda de accesorios para celular en Bolivia.\\n"
                "Tu tono es amigable y 'casero' (usa palabras como casero, belleza, ya pues).\\n\\n"
                "REGLAS INVIOLABLES:\\n"
                "1. JAMAS escribas slugs, IDs, ni datos técnicos de BD en el campo 'message'. Habla como humano.\\n"
                "2. En 'message' usa solo el nombre del producto (campo 'nombre'), nunca el slug.\\n"
                "3. Solo usa intent 'buy' cuando el usuario CONFIRME EXPLÍCITAMENTE que quiere comprar.\\n"
                "4. Los slugs que pongas en 'items' DEBEN ser EXACTAMENTE los del campo 'Slug' del inventario adjunto. No inventes slugs.\\n"
                "5. Si el producto no tiene stock o no está en el inventario, NO lo ofrezcas.\\n"
                "6. CANTIDADES: Respeta exactamente la cantidad pedida.\\n"
                "7. No hay descuentos activos.\\n\\n"
                "FORMATO DE SALIDA (JSON VÁLIDO - OBLIGATORIO):\\n"
                '{"intent": "chat|buy|troll", "items": [{"slug": "SLUG_EXACTO_DEL_INVENTARIO", "qty": 1}], "message": "Texto amigable sin slugs"}\\n\\n'
                f"=== INVENTARIO REAL (fuente de verdad) ===\\n{rag_context_str}\\n"
                f"=== CARRITO ACTUAL ===\\n{json.dumps(cart_redis)}"
            )

            if is_troll:
                system_prompt += "\\n\\n(SYSTEM WARNING): EL DISCURSO ENTRANTE ES MALICIOSO O VULGAR. FUERZA intent: troll, no ofrezcas nada."

            formatted_history = [{"role": "system", "content": system_prompt}]
            for msg in history:
                role = "assistant" if msg.get("role") == "model" else "user"
                formatted_history.append({"role": role, "content": msg.get("content", "")})
            formatted_history.append({"role": "user", "content": text})

            payload = {
                "model": "deepseek-chat",
                "messages": formatted_history,
                "temperature": 0.7,
                "response_format": {"type": "json_object"}
            }

            # Llamada al LLM
            tokens_used = 0
            for attempt, wait_time in enumerate([1, 2, 4]):
                try:
                    async with httpx.AsyncClient(timeout=15.0) as client:
                        response = await client.post(
                            "https://api.deepseek.com/v1/chat/completions",
                            json=payload,
                            headers={"Authorization": f"Bearer {settings.DEEPSEEK_API_KEY}"}
                        )
                        response.raise_for_status()
                        result = response.json()
                        content = json.loads(result['choices'][0]['message']['content'])
                        tokens_used = result.get('usage', {}).get('total_tokens', 0)
                        break
                except Exception as e:
                    logger.warning(f"Error DeepSeek (intento {attempt+1}): {e}")
                    if attempt == 2:
                        content = {"intent": "chat", "items": [], "message": "Casero denme un momento, estoy revisando el almacén..."}
                    await asyncio.sleep(wait_time)

            final_message = content.get('message', 'Un momento por favor.')
            intent = content.get('intent', 'chat')
            items = content.get('items', [])
            
            if cls.contains_prohibited_words(final_message):
                intent = 'troll'
                items = []
                final_message = "¡Jajaja casero! Casi me haces decir una locura 😂 ¿Qué buscabas realmente?"

            # 4. Flujo de Compra (Magic Link)
            compra_items = items if items else cart_redis
            if intent == 'buy' and compra_items and not is_troll:
                is_valid, error_msg = await cls.validate_order_items(compra_items)
                if not is_valid:
                    final_message = error_msg
                    items = []
                else:
                    checkout_url, total_calc = await cls.create_order(lead['id'], compra_items, cart_redis_key)
                    if checkout_url:
                        final_message = f"¡Ya está tu pedido, casero! Aquí tienes el resumen exacto y tu código seguro (BNB) para pagarlo al instante:\n\n{checkout_url}"
                        items = [] # clear items from payload to not re-add
            
            logger.info(f"Respuesta IA: {final_message}")

            if not test_mode:
                # Actualizar Redis (Memoria)
                if db.redis_client:
                    await db.redis_client.rpush(redis_chat_key, json.dumps({'role': 'user', 'content': text}))
                    await db.redis_client.rpush(redis_chat_key, json.dumps({'role': 'model', 'content': final_message, 'items': items}))
                    await db.redis_client.ltrim(redis_chat_key, -5, -1)
                    await db.redis_client.expire(redis_chat_key, 86400)
                    
                    if items:
                        await db.redis_client.setex(cart_redis_key, 86400, json.dumps(items))
                
                # Respaldo Postgres Outbound
                await cls.log_whatsapp_message(lead['id'], '', final_message, 'outbound', 'ai', tokens_used)
                
                # Manda WA
                await cls.send_whatsapp_message(phone, final_message)
            
            return content

        except Exception as e:
            logger.error(f"Falla crítica en procesamiento de chat: {e}")
            return {"error": str(e)}

    @classmethod
    async def send_whatsapp_message(cls, phone: str, text: str):
        url = f"https://graph.facebook.com/v18.0/{settings.WHATSAPP_PHONE_ID}/messages"
        headers = {
            "Authorization": f"Bearer {settings.WHATSAPP_TOKEN}",
            "Content-Type": "application/json"
        }
        payload = {
            "messaging_product": "whatsapp",
            "recipient_type": "individual",
            "to": phone,
            "type": "text",
            "text": {"preview_url": False, "body": text}
        }
        
        try:
            async with httpx.AsyncClient() as client:
                response = await client.post(url, json=payload, headers=headers)
                response.raise_for_status()
                logger.info(f"✅ Mensaje enviado a {phone} exitosamente.")
        except Exception as e:
            logger.error(f"❌ Falló el envío de WhatsApp a {phone}. Error: {e}")

    @classmethod
    async def send_fallback_message(cls, phone: str):
        """ Envia mensaje amigable en caso de excepción severa """
        text = "¡Hola casero! Estamos reabasteciendo la tienda un momento. ¿En qué te ayudo?"
        await cls.send_whatsapp_message(phone, text)
