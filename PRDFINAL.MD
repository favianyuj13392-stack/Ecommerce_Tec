# PRD Definitivo: Integración de Bot de WhatsApp con IA para E-Commerce Reactivo

**Versión: Definitiva (Incorpora PRD Inicial de Producto Base, Gaps Cubiertos, Alineación con Guía Original, Sin Limitantes de Hosting y Correcciones de Observaciones)**  
**Autor: Asesor IA (basado en plan de Favian, guía de Silversky Technology, PRD inicial de producto base, y conversaciones previas)**  
**Fecha: 7 de marzo de 2026**  
**Propósito:** Este Product Requirements Document (PRD) define de manera completa y detallada la arquitectura, funcionalidades, requisitos y roadmap para desarrollar e integrar un bot de WhatsApp con IA (Google Gemini) en tu producto base de e-commerce a medida ("Reactive" Base Template LatAm). Se fusiona el PRD inicial (visión, modelo de datos, lógica de negocio, endpoints, panel admin) con versiones previas, eliminando limitantes de shared hosting (asumiendo entorno flexible como VPS o cloud con acceso completo, e.g., para procesos persistentes, extensions DB y escalabilidad). Incorpora la guía "How to Make WhatsApp Bots" de Silversky Technology (adaptando su arquitectura webhook, memorización chat, código Laravel, características avanzadas). Incluye correcciones de observaciones: confirmación por email en Flujo 1 (evita costos WA), RAG simplificado a SQL para MVP (evita overingeniería), y seguridad en session_uuid (no exponer datos personales sin auth). Este PRD es exhaustivo para que tu IA lo use como base en el desarrollo, supervisión y iteraciones, permitiendo un MVP escalable.

**Alcance y Exclusiones:**

- **Alcance:** Plataforma white-label para LatAm, con bot reactivo para ventas conversacionales, recuperación de carritos, integración con web (pagos BNB formales). Enfoque en Bolivia (WA como "carrito real", QR pagos, regateo cultural). MVP: Flujos formales, IA híbrida con retrieval simple, anti-abuso.
- **Exclusiones:** Multi-idioma (solo español inicial), soporte audio/video en WA, integración CRM externa (e.g., HubSpot), app móvil. No incluye marketing pagado en WA (solo mensajes gratuitos en ventana 24h).

**Stakeholders:**

- **Dueño/Desarrollador Principal:** Favian.
- **IA Supervisora:** Tu IA para guiar código, revisar PRs y sugerir optimizaciones.
- **Usuarios Finales:** Clientes bolivianos (e-commerce, e.g., zapatos/productos con upsell).

**Requisitos No Funcionales:**

- **Performance:** Respuestas WA <2s (async queues); manejar 1,000 chats/día inicial, escalable a 10,000+.
- **Disponibilidad:** 99.9% uptime (VPS/cloud con monitoreo).
- **Compatibilidad:** Browsers modernos (Chrome/Firefox); WA mobile/desktop.
- **Escalabilidad:** Diseño para alta concurrencia (e.g., Redis para queues, vector DB para RAG futuro).
- **Seguridad:** HTTPS obligatorio; validación signatures; encriptación datos sensibles (e.g., phones hashed); no exponer PII en links mágicos sin auth.
- **Cumplimiento:** Ley boliviana de Protección de Datos (consentimiento opt-in WA); políticas Meta contra spam.

## 🎯 Concepto Central y Beneficios

El sistema es un E-Commerce Reactivo white-label para LatAm, priorizando captura de leads y inmediatez via IA en WA.

- **Web + BNB:** Caja registradora (pagos QR seguros, formales).
- **Backend Laravel:** Trackea comportamiento (vistas, carritos abandonados).
- **Gemini IA:** Analiza y persuade (personalización dinámica con retrieval simple).
- **WhatsApp:** Vendedor conversacional (saluda, upsell, redirige a web).

Esta estrategia es potente en Bolivia (WA como "carrito real"). Beneficios (de guía, adaptados):

- Soporte 24/7 con 80% automatización.
- +20% conversiones (IA persuasiva).
- Reducción abandono carritos (recuperación timed).
- Margen protegido (anti-abuso).
- Costos optimizados (WA gratuita en 24h).
- Multi-formato (texto, botones, links).

**Métricas de Éxito (KPIs):**

- Tasa conversión: >15%.
- % Recuperación carritos: >20%.
- % Abuso detectado: <5% (abandonos repetidos).
- Costo/mensaje WA: <0.05 Bs (solo gratuitos).
- Satisfacción usuario: NPS >70 (encuestas post-chat).

## 🛠️ Stack Técnico y Configuración

- **Backend:** Laravel 12 (API).
- **Frontend:** React 18 + Vite (SPA).
- **DB:** MySQL con soporte full-text para retrieval simple (escalable a vector extensions).
- **Panel Admin:** FilamentPHP v3.
- **Procesamiento Async:** Laravel Queues (driver Redis para escalabilidad); Supervisor para workers persistentes.
- **Integraciones:**
    - WhatsApp Cloud API (gratuita; regístrate en developers.facebook.com).
    - Google Gemini REST API (tier free; HTTP nativo con `Illuminate\Support\Facades\Http`).
    - BNB (webhook para confirmaciones pago).
- **Dependencias Composer:** `illuminate/support`, `spatie/data-transfer-object` (para DTOs), `guzzlehttp/guzzle` (HTTP), `filament/filament` (admin).
- **.env Vars Clave:**
    ```
    QUEUE_CONNECTION=redis
    QUEUE_TIMEOUT=60
    WHATSAPP_TOKEN=your_meta_token
    WHATSAPP_PHONE_ID=your_phone_id
    GEMINI_API_KEY=your_gemini_key
    BNB_WEBHOOK_SECRET=your_bnb_secret
    MIN_CART_VALUE=300  # Umbral AOV en Bs
    ABANDONMENT_MAX=3   # Máx abandonos para bloqueo
    ```
- **Setup Inicial (Paso a Paso):**
    1. Instala Laravel via Composer en entorno VPS/cloud.
    2. Configura DB con full-text indexes.
    3. Ejecuta `php artisan queue:table` y `migrate`.
    4. Registra WA en Meta (configura webhook URL).
    5. Instala Supervisor para queues persistentes.

## 🔄 Flujos de Compra

Dos flujos, siempre terminando en pago web formal. (Diagrama Textual: Usuario → WA/Web → Backend → IA/DB → Respuesta/Link → Pago BNB.)

### 🟢 Flujo 1: Pago Directo en la Web (con Retargeting)

1. Cliente agrega productos al carrito (React: localStorage + API Laravel sync; sessions UUID para tracking sin login).
2. Checkout: Formulario (email, teléfono WA con opt-in para mensajes). Genera QR BNB.
3. Pago y Confirmación: El Webhook del BNB confirma el pago y Laravel dispara la factura/recibo por Email (Cero costos de WA).

### 🟢 Flujo 2: Ordenar por WhatsApp (Conversacional)

1. Botón "Ordenar por WhatsApp" (React: genera `wa.me` con prefilled message).
2. Webhook recibe; bot activa (saluda, parsea intención con Gemini).
3. Conversación: Aclara dudas, upsell (sugiere complementarios basados DB), ajusta carrito (mensajes estructurados: botones/listas WA).
4. Confirmación: Reconstruye carrito final ("Comprarás X con Y% off?"), envía link mágico.
5. Redirección: Web carga carrito, ingresa datos, pago QR BNB.
6. Post-pago: Webhook BNB trigger mensaje confirmación (gratis en 24h ventana).

**Timing Recuperación (Estrategia Boliviana):**

- Tiempo X: 2h post-interacción ("Momento de Oro").
- Ajuste horario (lógica en Job: chequea timestamp DB).

| Hora Contacto Inicial | Timing Recordatorio | Razón                    |
| --------------------- | ------------------- | ------------------------ |
| Mañana (08:00-11:00)  | 2h después          | Cerrar antes almuerzo.   |
| Tarde (14:00-18:00)   | 2h después          | Impulso pre-fin laboral. |
| Noche (20:00-23:00)   | Siguiente 09:30     | Evitar invasivo.         |

- Si no responde: 2do recordatorio a 20h ("Último chance", con oferta si aplica).

## 🛒 Recuperación de Carrito

- Trigger: Job Scheduler si no pago post-WA.
- Estrategia: Recordatorio 1 (2h: "¿Problema con pago?"); Recordatorio 2 (20h: "Pedido reservado 4h más").
- Integra anti-abuso antes de oferta.

## ⚠ Problema Crítico: Explotación del Sistema

Riesgo: Abuso para forzar descuentos (inflar carritos, esperar ofertas), común en Bolivia por regateo.

## 🛡 Soluciones Contra Abuso (Todas Integradas, Híbrida)

Implementa en Job antes de IA (query DB).

1. **Límites por Cliente:** Tabla `clients` (phone unique, abandonment_history JSON, discount_history JSON, purchase_frequency int). Máx 1 oferta/mes; solo carritos >100 Bs; si >3 abandonos/30d: Mensaje alternativo sin oferta.
2. **Ofertas Variables/No Garantizadas:** 5-15% random (PHP rand/IA); alternativas (envío gratis, condicional). Advierte: "Podrías recibir oferta... pero no siempre."
3. **Detección Patrones:** Chequea reciente descuento/abandonos/carrito alto (>media\*2). Si sospechoso: Recordatorio sin oferta.
4. **Ventana 24h:** Solo trigger en gratuita.
5. **Una Sola Vez:** 1 cupón/30-60d; si ya recibido: Incentivo no-monetario (regalo sorpresa).
6. **Gamificación/Descuento Dinámico:** Variables (envío gratis/15 Bs fijo/"último stock"); no predecible.
7. **Umbral Carrito (AOV):** Solo >300 Bs para descuento; menores: Servicio puro.
8. **Valor Añadido > Descuento:** Prioriza asesoría ("Medidas talla L?") o prioridad despacho.

## 🤖 Rol de la IA (Gemini, Híbrida con Retrieval Simple para MVP)

- **Ventajas:** Personalización (analiza contexto/historial), mensajes persuasivos/naturales, adaptación conversacional, mejora continua.
- **Uso:** Solo para ofertas/recuperación inicial; reglas código como guardián.
- **Retrieval Simple para Anti-Alucinaciones (MVP):** Antes de prompt, recupera productos/stock reales via SQL/Eloquent (e.g., LIKE/full-text search). Augmenta prompt con JSON pre-filtrado (e.g., 3 productos relacionados con stock >0). Escala a RAG full (vector DB) post-MVP.
- **Prompt Estructurado (Ejemplo en Job):** "Actúa como vendedor e-commerce boliviano. Analiza [historial JSON, tipo producto, precio, tiempo abandono]. Sugiere oferta max 15% basada en estos productos reales con stock: [JSON retrieval], no inventes. Formato JSON: {response: string, discount: int, items: array}."
- **Validación:** Parse JSON; si >15% o alucinación: Fallback plantilla.

## 🚨 Control de Errores y Alucinaciones

- **Prompts Estructurados:** Siempre JSON output.
- **Fallback:** Si API falla/inválido: Plantilla estándar (e.g., "10% off por 2h").
- **Testing/Monitoreo:** Log todas respuestas (Laravel Log); umbrales (no descuentos >500 Bs); probar maliciosos.
- **Híbrido Inicial:** `USE_AI_FULL=false` en .env para escalar.

## 🎛 Control de Descuentos

- Lee de DB `discount_rules` (type string, max_percent int, active bool).
- Panel admin (Filament): Define % max, activa/desactiva.
- Validación código: Aplica solo límites.

## 🏗️ Arquitectura y Flujo Técnico

- **Diagrama Textual:** Usuario → WA → Webhook Laravel (rápido 200 OK) → Queue Job → Parseo/Memorización DB → Anti-Abuso Check → Retrieval Simple (SQL) → Gemini HTTP → Validación → Envío WA → Almacena DB.
- **Algoritmo (Adaptado Guía):** Recibe webhook, valida, memoriza básico, dispatch job; job procesa retrieval/IA/envío.
- **Modelo de Datos (de PRD Inicial, Flexible con JSON):**
    - `leads`: whatsapp_id (string unique), name (string nullable), interaction_count (int default 0), interests (JSON nullable).
    - `products`: nombre (string), slug (string unique), descripcion (text), precio (decimal), attributes (JSON nullable), has_variants (bool default false).
    - `product_variants`: product_id (FK), sku (string nullable), price (decimal nullable), stock (int default 0), variant_attributes (JSON).
    - `orders`: user_id (unsignedBigInteger nullable - permite Guest), guest_data (JSON nullable - nombre, dir, teléfono, fiscales), payment_method (enum: ['gateway_bnb']), status (string: pending_payment, paid, shipped, cancelled), session_uuid (string index nullable).
    - `order_items`: order_id (FK), product_id (FK), variant_id (nullable), quantity (int), unit_price (decimal), snapshot_data (JSON nullable).
    - `chats`: id, phone (string unique), history_json (JSON), timestamp (datetime).
    - `clients`: id, phone (string unique), abandonment_history (JSON), discount_history (JSON), purchase_frequency (int).
    - `discount_rules`: id, type (string), max_percent (int), active (bool).
- **Lógica de Negocio (Fusionada):**
    - **Checkout Guest First:** React maneja carrito LocalStorage; envía payload guest_data/session_uuid. Laravel crea lead si teléfono nuevo.
    - **Módulos Pago:** Solo formal (gateway BNB); webhook `/api/webhook/bnb` actualiza status.
    - **Sniper Tracking:** React genera guest_uuid, envía `/api/track/cart`. Botón WA inyecta UUID en mensaje; webhook busca carrito para contexto IA.
- **Endpoints API (de PRD Inicial, Expandido):**
    - `GET /api/products` (filtros JSON attributes).
    - `POST /api/track/cart` (tracking silencioso).
    - `POST /api/orders` (creación orden, maneja Guest).
    - `GET /api/webhook/whatsapp` (validación Meta).
    - `POST /api/webhook/whatsapp` (recepción - encola job, 200 OK rápido).
    - `POST /api/webhook/bnb` (estados pago).
- **Código (Expandido de v3.0):**
    - Routes/Webhook/Job como en plan definitivo.

## 📊 Riesgos y Mitigaciones

- **Riesgo: Timeouts/Bloqueos Meta:** Mitigación: Async queues.
- **Riesgo: Alucinaciones IA:** Mitigación: Retrieval simple, prompts estructurados, validación.
- **Riesgo: Abuso/Spam Bans:** Mitigación: Anti-abuso, templates aprobados Meta.
- **Riesgo: Costos Gemini/WA:** Mitigación: Tier free; solo mensajes gratuitos.
- **Riesgo: Datos Sensibles:** Mitigación: Hash phones, opt-in; no exponer guest_data en links sin auth.

## 🚀 Roadmap de Desarrollo por Fases (Detallado)

| Fase                        | Tasks                                                                                               | Dependencias |
| --------------------------- | --------------------------------------------------------------------------------------------------- | ------------ |
| 1: Setup Básico             | Instala Laravel/DB (con full-text indexes), APIs (WA/Gemini), queues/Supervisor.                    | Ninguna.     |
| 2: Flujos y Tracking        | Implementa Flujo 1/2 (botones React, webhook, links mágicos con auth). Sessions UUID.               | Fase 1.      |
| 3: IA y Anti-Abuso          | Integra Gemini HTTP con retrieval simple (SQL), anti-abuso checks, prompts.                         | Fase 2.      |
| 4: Recuperación y Controles | Scheduler timing, panel Filament para descuentos.                                                   | Fase 3.      |
| 5: Testing y Launch         | Unit/integration tests (PHPUnit), escenarios abuso/alucinaciones. Deploy VPS/cloud. Monitoreo logs. | Todas.       |

**Plan de Testing:**

- **Unit:** Webhook response <1s, IA parse JSON.
- **Integración:** Flujos end-to-end (WA simulado).
- **Escenarios:** Abuso (inflar carrito), errores (API down), timing (noche).
