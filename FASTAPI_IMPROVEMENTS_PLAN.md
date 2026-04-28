# 📋 Plan de Mejoras: Transfiriendo Buenas Prácticas de Laravel a FastAPI

**Versión:** 1.0  
**Fecha:** 10 de Abril de 2026  
**Objetivo:** Elevar la calidad operativa del bot FastAPI desde "vibecoding" a software industrialmente sólido mediante la transferencia de patrones probados del bot Laravel.

---

## 🎯 Introducción

El bot Laravel ha sido construido con cuidado ingenieril: manejo de errores robusto, retries, tolerancia a fallos, validaciones exhaustivas y logging persistente. El bot FastAPI tiene una arquitectura moderna excelente (microservicio, async, RAG vectorial), pero le faltan capas de resiliencia operativa.

Este documento especifica 7 módulos de mejora con justificación, procedimiento y referencias directas al código Laravel que debe replicarse.

---

## 📦 Módulo 1: Retry/Backoff para Sincronización de Productos

### ¿Qué es?
Mecanismo que reintentar automáticamente si la sincronización de embeddings falla, con delays exponenciales entre intentos.

### ¿Por qué es importante?
- Laravel hace `tries = 3` y `backoff = 10` en `SyncProductWithRAGJob`.
- Si FastAPI cae mientras Laravel intenta sincronizar un producto, el embedding se queda obsoleto indefinidamente.
- Un retry automático garantiza "eventual consistency" en la RAG.

### Cómo lo hace Laravel
```php
// SyncProductWithRAGJob.php
public $tries = 3;        // Reintentar 3 veces
public $backoff = 10;     // Esperar 10s entre intentos

// Si falla la línea Http::timeout(10)->post(...)->throw()
// Laravel lo reencolará automáticamente
```

### Cómo aplicarlo a FastAPI

#### Opción A: Cola de reintentos en Redis (Recomendada)
- Crear tabla `product_sync_queue` en `bot_rag_db`:
  - `product_id`, `attempts`, `next_retry_at`, `error_log`, `created_at`
- Cuando `/internal/sync-products` reciba un producto:
  - Intenta vectorizar.
  - Si falla, inserta en `product_sync_queue` con `attempts=1` y `next_retry_at=now+10s`.
- Worker nuevo `sync_retry_worker.py`:
  - Cada 10s, busca filas con `attempts < 3` y `next_retry_at <= now`.
  - Reintenta la vectorización.
  - Si sigue fallando, incrementa `attempts` y ajusta `next_retry_at`.

#### Opción B: Job en Redis Queue (Más simple)
- Usar `arq` o `rq` (Python job queue en Redis).
- Cuando sync falle, el job fallido se reencolará automáticamente.

### Procedimiento aproximado
1. Crear tabla `product_sync_queue` con migraciones.
2. Crear servicio `ProductSyncRetryService` en `services/product_sync_retry.py`.
3. Modificar `/internal/sync-products` para capturar excepciones e insertar en retry queue.
4. Crear worker `sync_retry_worker.py` que busque y reintente.
5. Documentar en `README.md` cómo monitorear la queue.

### Archivos a crear
- `services/product_sync_retry.py`
- `scripts/sync_retry_worker.py`
- `migrations/add_product_sync_queue_table.py` (script de migración)

### Archivos a modificar
- `main.py`: Capturar excepciones en `/internal/sync-products`

### Referencia en Laravel
- [SyncProductWithRAGJob.php](../app/Jobs/SyncProductWithRAGJob.php)

---

## 📦 Módulo 2: CLI para Sincronización Masiva de Embeddings

### ¿Qué es?
Comando de consola que recorre todos los productos y sincroniza embeddings en bulk, con progreso visual.

### ¿Por qué es importante?
- Si necesitas reconstruir embeddings (cambio de modelo NLP, bug corregido), la CLI permite hacerlo sin tocar interfaz.
- Laravel tiene `php artisan rag:sync-products` con progress bar.
- FastAPI necesita equivalente.

### Cómo lo hace Laravel
```php
// SyncAllProductsToRAG.php
protected $signature = 'rag:sync-products';

foreach ($products as $product) {
    SyncProductWithRAGJob::dispatchSync($product);  // Procesar síncronamente en CLI
    $bar->advance();
}
```

### Cómo aplicarlo a FastAPI

#### Opción única: Script CLI con click o typer
- Crear `cli/sync_products_cli.py` usando framework como `click` o `typer`.
- Script solicita:
  - Filtro opcional (ej: `--category=celular`).
  - Batch size (defecto 10).
  - Modo dry-run (mostrar qué haría sin ejecutar).
- Conecta a `ecommerce_db`, obtiene productos y llama a motor de embeddings.
- Muestra progreso con `tqdm` o `rich.progress`.

### Procedimiento aproximado
1. Instalar `click` o `typer` en `requirements.txt`.
2. Crear `fastapi_bot/cli/sync_products_cli.py`.
3. CLI carga modelo de embeddings, itera productos, llama `EmbeddingsEngine.generate_embedding_async()`.
4. Inserta o actualiza en `product_embeddings` con timestamp.
5. Loguea resultados y errores.
6. Ejecutable como: `python -m fastapi_bot.cli.sync_products_cli --batch-size=20`

### Archivos a crear
- `cli/__init__.py`
- `cli/sync_products_cli.py`

### Archivos a modificar
- `requirements.txt`: Añadir `click>=8.0` o `typer>=0.9`

### Referencia en Laravel
- [SyncAllProductsToRAG.php](../app/Console/Commands/SyncAllProductsToRAG.php)

---

## 📦 Módulo 3: Persistencia de Mensajes en DB (No solo Redis)

### ¿Qué es?
Guardar historial inbound/outbound de WhatsApp en PostgreSQL, no solo en Redis.

### ¿Por qué es importante?
- Redis es efímero: si falla o se limpia, se pierden conversaciones.
- Laravel persiste en `whatsapp_messages` tabla: dirección (inbound/outbound), tokens, timestamp.
- Auditoría, debugging y analytics requieren histórico.

### Cómo lo hace Laravel
```php
// ProcessWhatsAppMessage.php
$lead->whatsappMessages()->create([
    'message_id' => $wamid,
    'body' => $text,
    'direction' => 'inbound',
    'source' => 'user',
]);

// ... y luego:
$lead->whatsappMessages()->create([
    'body' => $finalMessage,
    'direction' => 'outbound',
    'source' => 'ai',
    'tokens_used' => $aiResult['tokens'],
]);
```

### Cómo aplicarlo a FastAPI

#### Opción única: Tabla `chat_history` en PostgreSQL
- Tabla `chat_history` ya existe (Módulo 4 anterior), pero está subutilizada.
- Modificar `ChatProcessor.log_whatsapp_message()` para hacer inserciones reales.
- Cada mensaje inbound/outbound → insert directo a PostgreSQL.

### Procedimiento aproximado
1. Tabla `chat_history` debe tener columnas:
   - `id`, `lead_id`, `message_id`, `body`, `direction` (inbound/outbound), `source` (user/ai), `tokens_used`, `created_at`.
2. Índices: `(lead_id, created_at)` y `(message_id)` para queries rápidas.
3. Modificar `ChatProcessor.log_whatsapp_message()`:
   - Insert async en `chat_history`.
   - Manejo de excepciones (no bloquear flujo si logging falla).
4. En `/chat/test`, loguear con `test_mode=False` para persistir.

### Archivos a crear
- (Ninguno, la tabla ya existe)

### Archivos a modificar
- `services/chat_processor.py`: Mejorar `log_whatsapp_message()`.
- `main.py`: Asegurar que logging no bloquea respuesta a Meta.

### Referencia en Laravel
- [ProcessWhatsAppMessage.php L60-65, L237-243](../app/Jobs/ProcessWhatsAppMessage.php)

---

## 📦 Módulo 4: Validación Rigurosa de Items y Carrito

### ¿Qué es?
Verificación exhaustiva que cada item en el carrito existe, tiene stock, y cantidad es válida.

### ¿Por qué es importante?
- Laravel hace validación en L195-205: `Product::whereIn('slug', $pureSlugs)->count()` y compara con items.
- Sin validación, FastAPI podría crear órdenes con productos inexistentes.
- Evita bugs de cantidad negativa o slug malformado.

### Cómo lo hace Laravel
```php
// ProcessWhatsAppMessage.php L204-211
$pureSlugs = array_values(array_unique(array_column($items, 'slug')));
$validCount = \App\Models\Product::whereIn('slug', $pureSlugs)
    ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
    ->count();

if ($validCount !== count($pureSlugs)) {
    $finalMessage = 'Estoy revisando el inventario exacto...';
    $items = [];
}
```

### Cómo aplicarlo a FastAPI

#### Opción única: Validador en ChatProcessor
- Crear método `validate_order_items(items: list) -> (bool, str)`.
- Verificar:
  - Cada item tiene `slug`.
  - Cantidad es entero positivo.
  - Producto existe en ecommerce_db y tiene stock > 0.
  - No hay duplicados en el carrito.
- Si alguno falla, retornar (False, "Mensaje de error").

### Procedimiento aproximado
1. En `ChatProcessor`, añadir método:
   ```python
   async def validate_order_items(cls, items: list) -> tuple[bool, str]:
       # Obtener slugs únicos
       # Query: SELECT slug FROM products WHERE slug IN (...) AND stock > 0
       # Comparar cantidad de resultados con items
       # Validar cantidad > 0 y tipo correcto
   ```
2. Antes de crear orden en `process_message_async()`:
   ```python
   if intent == 'buy':
       is_valid, error_msg = await ChatProcessor.validate_order_items(items)
       if not is_valid:
           final_message = error_msg
           items = []
   ```

### Archivos a crear
- (Ninguno)

### Archivos a modificar
- `services/chat_processor.py`: Añadir `validate_order_items()`.

### Referencia en Laravel
- [ProcessWhatsAppMessage.php L195-211](../app/Jobs/ProcessWhatsAppMessage.php)

---

## 📦 Módulo 5: Fallback Amigable (No Error 500 Crudo)

### ¿Qué es?
Si hay excepción no capturada, enviar mensaje amigable al usuario, no fallar silenciosamente.

### ¿Por qué es importante?
- Laravel en `ProcessWhatsAppMessage.php` L276-279 captura toda excepción y envía mensaje.
- Sin esto, usuario nunca sabe qué pasó.
- Mejora UX y reduce confusión.

### Cómo lo hace Laravel
```php
// ProcessWhatsAppMessage.php L274-279
catch (\Exception $e) {
    Log::error("ProcessWhatsAppMessage Error Crítico: " . $e->getMessage());
    if ($phone) {
        $whatsappService->sendMessage($phone, "¡Hola casero! Estamos reabasteciendo la tienda un momento. ¿En qué te ayudo?");
    }
    return;
}
```

### Cómo aplicarlo a FastAPI

#### Opción única: Try-catch en worker + fallback message
- En `main_worker.py`, cuando `process_message_async()` falla:
  - Log del error completo.
  - Enviar mensaje fallback a través de WhatsApp.
  - Markear mensaje en Redis como "failed" para no procesar de nuevo.

### Procedimiento aproximado
1. Crear función `send_fallback_message(phone: str)` en `ChatProcessor`.
2. En `main_worker.py`:
   ```python
   try:
       await ChatProcessor.process_message_async(msg_data)
   except Exception as e:
       logger.error(f"Worker error: {e}")
       phone = msg_data.get('from')
       if phone:
           await ChatProcessor.send_fallback_message(phone)
   ```
3. Mensaje fallback: "¡Hola! Estamos reabasteciendo la tienda un momento. ¿En qué te ayudo?"

### Archivos a crear
- (Ninguno)

### Archivos a modificar
- `services/chat_processor.py`: Añadir `send_fallback_message()`.
- `main_worker.py`: Envolver procesamiento en try-except.

### Referencia en Laravel
- [ProcessWhatsAppMessage.php L273-280](../app/Jobs/ProcessWhatsAppMessage.php)

---

## 📦 Módulo 6: Servicios Separados y Encapsulación

### ¿Qué es?
Dividir lógica en clases/servicios específicas, no todo en `ChatProcessor` monolítico.

### ¿Por qué es importante?
- Laravel tiene `DeepSeekService`, `WhatsAppService`, `GeminiService`.
- Cada servicio tiene responsabilidad clara.
- FastAPI todo en `ChatProcessor` es frágil y difícil de testear.

### Cómo lo hace Laravel
```php
// Services/DeepSeekService.php
public function generateResponse(...): array { ... }

// Services/WhatsAppService.php
public function sendMessage(string $to, string $text): bool { ... }
```

### Cómo aplicarlo a FastAPI

#### Opción única: Servicios específicos
- Crear `services/deepseek_service.py`: Lógica de llamada a API DeepSeek.
- Crear `services/whatsapp_service.py`: Lógica de envío a Meta.
- Crear `services/order_service.py`: Lógica de creación de orden.
- Crear `services/lead_service.py`: Lógica de leads.
- `ChatProcessor` orquesta, no implementa.

### Procedimiento aproximado
1. Crear clases tipo:
   ```python
   # services/deepseek_service.py
   class DeepSeekService:
       async def generate_response(self, messages, temperature=0.7) -> dict:
           # Llamada a API con retry

   # services/whatsapp_service.py
   class WhatsAppService:
       async def send_message(self, phone: str, text: str) -> bool:
           # POST a Meta webhook
   
   # services/order_service.py
   class OrderService:
       async def create_order(self, lead_id, items, cart_key) -> tuple:
           # Crear order y retornar URL
   ```
2. `ChatProcessor` usa estas clases.

### Archivos a crear
- `services/deepseek_service.py`
- `services/whatsapp_service.py`
- `services/order_service.py`
- `services/lead_service.py`

### Archivos a modificar
- `services/chat_processor.py`: Refactor para usar servicios.

### Referencia en Laravel
- [DeepSeekService.php](../app/Services/DeepSeekService.php)
- [WhatsAppService.php](../app/Services/WhatsAppService.php)

---

## 📦 Módulo 7: Repositorio/Wrapper para Consultas de Dominio

### ¿Qué es?
Encapsular queries SQL complejas en métodos descriptivos, no queries raw en `ChatProcessor`.

### ¿Por qué es importante?
- Laravel usa Eloquent + relaciones: `Product::whereIn(...)->whereHas(...)`.
- FastAPI tiene queries SQL raw esparcidas.
- Difícil de testear, mantener y reutilizar.

### Cómo lo hace Laravel
```php
// Process via Eloquent:
$products = Product::with(['variants' => fn($q) => $q->where('stock', '>', 0)])
    ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
    ->limit(3)
    ->get();

// O con llamadas de servicio:
$dbProducts = Product::whereIn('slug', $pureSlugs)->get()->keyBy('slug');
```

### Cómo aplicarlo a FastAPI

#### Opción única: Repositorio de dominio
- Crear `repositories/product_repository.py`:
  ```python
  class ProductRepository:
      async def get_with_stock(self, limit=3) -> list:
          # Query products con stock > 0
      
      async def get_by_slugs_with_stock(self, slugs: list) -> list:
          # Query específicos productos
  ```
- Crear `repositories/lead_repository.py`:
  ```python
  class LeadRepository:
      async def get_or_create(self, phone: str) -> dict:
          # Emula Lead::firstOrCreate
  ```

### Procedimiento aproximado
1. Crear `repositories/__init__.py`.
2. Crear `repositories/product_repository.py` con métodos comunes.
3. Crear `repositories/lead_repository.py`.
4. Crear `repositories/order_repository.py`.
5. Inyectar en `ChatProcessor` en lugar de queries directas.

### Archivos a crear
- `repositories/__init__.py`
- `repositories/product_repository.py`
- `repositories/lead_repository.py`
- `repositories/order_repository.py`

### Archivos a modificar
- `services/chat_processor.py`: Usar repositorios.

### Referencia en Laravel
- [Lead.php Model](../app/Models/Lead.php)
- [Product.php Model](../app/Models/Product.php)

---

## 🗓️ Plan de Ejecución Recomendado

### Fase 1: Urgente (Semana 1)
- **Módulo 5**: Fallback amigable (1-2 horas) - Impacto alto, esfuerzo bajo.
- **Módulo 4**: Validación de items (2-3 horas) - Evita bugs de negocio.

### Fase 2: Importante (Semana 2)
- **Módulo 3**: Persistencia de mensajes (2-3 horas) - Auditoría y debugging.
- **Módulo 1**: Retry/backoff de sync (4-5 horas) - Resiliencia operativa.

### Fase 3: Arquitectura (Semana 3)
- **Módulo 6**: Servicios separados (6-8 horas) - Refactor sistemático.
- **Módulo 7**: Repositorios (6-8 horas) - Mantenibilidad a largo plazo.

### Fase 4: DevOps (Semana 4)
- **Módulo 2**: CLI de sincronización (3-4 horas) - Operacionalización.

---

## ✅ Checklist de Validación

Cuando completes cada módulo, valida:

- [ ] **Módulo 1**: Tabla `product_sync_queue` existe. Worker intenta 3 veces.
- [ ] **Módulo 2**: `python -m fastapi_bot.cli.sync_products_cli --help` funciona.
- [ ] **Módulo 3**: Verificar `chat_history` tiene registros después de `/chat/test`.
- [ ] **Módulo 4**: Intenta crear orden con slug inválido, recibe error amigable.
- [ ] **Módulo 5**: Worker falla y envía fallback message a WhatsApp.
- [ ] **Módulo 6**: `ChatProcessor` usa `DeepSeekService`, `WhatsAppService`, etc.
- [ ] **Módulo 7**: `ProductRepository.get_by_slugs_with_stock()` es usado en lugar de query raw.

---

## 📚 Referencias Cruzadas

| Buena Práctica | Laravel | FastAPI | Módulo |
|---|---|---|---|
| Retry/Backoff | `SyncProductWithRAGJob` tries/backoff | Tabla + Worker retry | 1 |
| CLI masiva | `SyncAllProductsToRAG` command | `sync_products_cli.py` | 2 |
| Persistencia mensajes | `whatsapp_messages` tabla | `chat_history` tabla | 3 |
| Validación items | L195-211 `ProcessWhatsAppMessage` | `validate_order_items()` | 4 |
| Fallback error | L273-280 `catch Exception` | Try-catch en worker | 5 |
| Servicios | `DeepSeekService`, `WhatsAppService` | Refactor en servicios | 6 |
| Repositorios | Eloquent + Models | `ProductRepository`, etc. | 7 |

---

## 🎓 Notas Finales

- Estos 7 módulos transformarán FastAPI de "prototipo rápido" a "software production-ready".
- Comienza por Fase 1 (Módulos 4-5) para impacto inmediato en estabilidad.
- Usa las pruebas en `test_e2e.py` para validar cada cambio.
- Documenta los cambios en `README.md` de FastAPI conforme avances.

---

**Documento generado:** 10 Abril 2026  
**Última actualización:** N/A  
**Siguiente revisión:** Post-implementación Fase 1
