# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Proyecto: E-commerce "Reactive" (Base Template LatAm)

**Versión:** 1.1 (Superset - Formal/Informal Support)
**Stack Tecnológico:** Laravel 12 (API), React 18 + Vite (SPA), MySQL, FilamentPHP v3 (Admin).
**Infraestructura Target:** Hostinger Shared Hosting.

---

### 1. Visión del Producto y Arquitectura

Plataforma de comercio electrónico "White Label" diseñada para la realidad de Latinoamérica. El sistema funciona como un "Superset" que soporta flujos formales (pasarelas de pago, facturación).
Prioriza la **captura del lead** y la **inmediatez** mediante un Agente de IA (Gemini) conectado a la WhatsApp Cloud API.

**Restricciones de Arquitectura (CRÍTICAS):**

- Al estar en Shared Hosting, **NO** se pueden usar demonios de Node.js persistentes ni WebSockets.
- Todos los procesos asíncronos (comunicación con WhatsApp, Gemini, Google Sheets) **DEBEN** usar Laravel Queues con el driver `database` y ejecutarse vía Cron Jobs.
- Los Webhooks deben responder `200 OK` en menos de 3 segundos; el procesamiento real se delega a los Jobs.

---

### 2. Modelo de Datos (Schema Flexible)

El sistema debe soportar diferentes rubros (ej. Zapatos con tallas vs. Artesanías con materiales). Se requiere el uso intensivo de columnas JSON para evitar esquemas rígidos.

#### Entidades Core a crear/modificar:

1. **`leads` (El activo del negocio)**
    - `whatsapp_id` (string, unique) -> Ej: +591XXXXXXXX
    - `name` (string, nullable)
    - `interaction_count` (integer, default 0)
    - `interests` (json, nullable) -> Tracking de categorías vistas.

2. **`products` (Catálogo Flexible)**
    - `nombre` (string), `slug` (string, unique), `descripcion` (text), `precio` (decimal).
    - `attributes` (json, nullable) -> Datos dinámicos (ej: `{"material": "cuero", "marca": "Nike"}`).
    - `has_variants` (boolean, default false).

3. **`product_variants` (Gestión de Stock Compleja)**
    - Relación: `product_id` (Foreign Key).
    - `sku` (string, nullable), `price` (decimal, nullable).
    - `stock` (integer, default 0).
    - `variant_attributes` (json) -> Ej: `{"talla": "42", "color": "Rojo"}`.

4. **`orders` (Superset Formal/Informal)**
    - `user_id` (unsignedBigInteger, nullable) -> **Debe permitir Guest Checkout**.
    - `guest_data` (json, nullable) -> Guarda nombre, dirección, teléfono y datos fiscales (NIT/RUC).
    - `payment_method` (enum: `['manual_qr', 'gateway_stripe', 'gateway_mercadopago']`).
    - `type` (enum: `['formal', 'informal']`, default 'informal').
    - `status` (string) -> pending_payment, paid, shipped, cancelled.
    - `session_uuid` (string, index, nullable) -> Crucial para el tracking pre-compra.

5. **`order_items` (Detalle inmutable)**
    - Relaciones: `order_id`, `product_id`, `variant_id` (nullable).
    - `quantity` (integer), `unit_price` (decimal).
    - `snapshot_data` (json, nullable) -> Respaldo de cómo era el producto al momento de comprar.

---

### 3. Lógica de Negocio (Core Features)

#### A. Checkout "Guest First"

1. El frontend (React) maneja el carrito en `LocalStorage`.
2. Al finalizar la compra, no se exige registro/contraseña. Se envía un payload con `guest_data` y el `session_uuid`.
3. Laravel busca si el teléfono existe en la tabla `leads`; si no, lo crea de forma transparente.

#### B. Módulos de Pago
- **Modo Formal (`gateway`):**
  Redirige a la pasarela externa. Usa webhooks (`POST /api/webhooks/payment`) para cambiar el estado a `paid`.

#### C. Módulo "Sniper" (Tracking de Intención de Compra)

1. **Frontend:** React genera un `guest_uuid` al entrar y lo guarda en LocalStorage. Cada acción de agregar al carrito envía un request silencioso a Laravel (`POST /api/track/cart`) para persistir temporalmente el carrito.
2. **Inyección en WhatsApp:** El botón flotante de WhatsApp en la web inyecta este UUID en el mensaje inicial: `wa.me/...?text=Hola (ref:guest_uuid)`.
3. **Webhook & AI:** Cuando Laravel recibe este mensaje inicial, busca el carrito asociado al `guest_uuid` y le inyecta ese contexto a Google Gemini para que responda sabiendo exactamente qué estaba mirando el cliente.

---

### 4. Endpoints Base de la API REST

- `GET /api/products` (Debe soportar filtros por JSON attributes).
- `POST /api/track/cart` (Endpoint silencioso para el Sniper).
- `POST /api/orders` (Creación de orden, maneja lógica Guest).
- `GET /api/webhook/whatsapp` (Validación de Meta - Handshake).
- `POST /api/webhook/whatsapp` (Recepción de mensajes - Solo encola el Job, retorna 200 OK rápido).
- `POST /api/webhook/payment` (Recepción de estados de pasarela).

---

### 5. Panel Administrativo (FilamentPHP)

Se utilizará FilamentPHP v3 para construir el panel backend (ahorrando tiempo en desarrollo de interfaces).

- Debe incluir ABM (Alta, Baja, Modificación) para `Products`, usando componentes tipo `Repeater` o `KeyValue` para gestionar fácilmente las columnas JSON de `attributes` y `variants`.
- Vista detallada de `Orders` y gestión manual de estados para el flujo informal.
