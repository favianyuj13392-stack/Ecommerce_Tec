# PRD v2.0: E-Commerce Reactivo y Cerebro de Ventas AI (Arquitectura Desacoplada)

**Versión: 2.0 (Evolución a Arquitectura de Microservicios: Laravel + FastAPI + PostgreSQL)**  
**Autor: Arquitecto AI**  
**Propósito:** Este Documento de Requisitos (PRD) preserva íntegramente la visión de negocio, flujos conversacionales, y medidas de seguridad (Anti-Abuso/Trolleo) del proyecto DARKOSYNC.AI original, pero sustituye completamente la infraestructura monolítica por una arquitectura moderna y desacoplada Orientada a Servicios (SOA). Esta nueva arquitectura habilita escalabilidad SaaS, Búsqueda Semántica Vectorial (RAG Real), y optimización radical de recursos CPU/RAM para alta concurrencia.

---

## 🎯 Concepto Central y Beneficios Mantenidos
El sistema interactúa como Vendedor Estrella "Casero", priorizando persuasión conversacional, inmediatez via IA y cierre de ventas automatizado.

- **Vendedor Conversacional (WA):** Saluda, recupera carritos, hace upsell, detecta intención pura (bypass de trolleos).
- **Checkout Dinámico:** Redirección a web con reconstrucción de carrito y Pasarela Inteligente (QR BNB).
- **Anti-Abuso Integrado:** Seguridad contra jailbreaks, límites de carritos inflados, y control de cupones para el contexto boliviano de "regateo".
- **Memoria Híbrida Real:** Corto plazo (sesión) y largo plazo (intereses guardados).

**Métricas de Éxito (KPIs):**
- Tasa conversión: >15%.
- Manejo asíncrono ágil: Capacidad para +1,000 usuarios concurrentes sin degradación.
- Alucinaciones < 1%: Precisión de RAG vectorial con comprensión semántica.
- Tiempo de respuesta IA: < 5s promedio.

---

## 🛠️ Nuevo Stack Técnico y Arquitectura de Microservicios

La clave de la v2.0 es la especialización. Dividimos responsabilidades entre el lenguaje óptimo para la web (PHP) y el lenguaje nativo para la IA (Python).

### 1. Base de Datos Central (SSoT - Single Source of Truth)
- **Tecnología:** **PostgreSQL + Extensión `pgvector`**.
- **Propósito:** Aloja tanto la metadata del e-commerce (Orders, Users) como los *Embeddings* vectoriales de los productos para la IA. Laravel gestiona la estructura (migraciones), FastAPI hace consultas tensoriales.
- **Capa Híbrida Temporal:** **Redis** (Persistencia efímera para la memoria de corto plazo de los chats `chat:{phone}` y carritos).

### 2. Back-Office & Plataforma E-Commerce (Laravel 12)
- **Roles:** Gestión, Facturación, Panel Administrativo (Filament v3), y UI de Checkout web (Blade/Tailwind).
- **Procesamiento:** Generación y validación de cobros con pasarelas (API BNB Sandbox), envío de correos, Integración con impuestos (SIAT en un futuro).
- **Cambio crítico:** Ya no procesará la cola de IA. Delegará la inteligencia al microservicio.

### 3. Cerebro Bot & NLP (FastAPI - Python)
- **Roles:** Interceptar webhooks de WhatsApp, limpieza de texto, Vectorización (Embeddings), Búsqueda Semántica (RAG verdadero), orquestación de prompts con DeepSeek, interceptor de seguridad anti-troll, y envío de mensajes vía Graph API.
- **Performance:** Programación Asíncrona nativa (`async/await`) reduciendo la huella de memoria (RAM) al 10% del consumo de Workers de PHP en picos de concurrencia.
- **Micro-Librerías:** `psycopg2` / `asyncpg` (DB), `LangChain` (opcional) / HTTP async (`httpx`) hacia DeepSeek.

---

## 🔄 Flujos de Compra (Integración Transparente)

El usuario final no nota la separación tecnológica.

### Flujo Bot a Web (Handoff Seguro)
1. **[FastAPI]** Recibe mensaje del webhook de Meta instantáneamente. 
2. **[FastAPI]** Busca en PostgreSQL (`pgvector`) los productos semánticamente similares a la voluntad del usuario cruzando inventario real (atributos y stocks).
3. **[FastAPI]** Consume DeepSeek aplicando el estricto `System Prompt`. Detecta `intent: "buy"`.
4. **[FastAPI]** Crea el registro de la `Order` con estado `pending`, limpia el carrito de Redis, y escribe el UUID mágico de pago `APP_URL/checkout/{uuid}` en el WhatsApp del usuario.
5. **[Laravel]** El cliente clickea, entra al servidor web. Laravel extrae la Orden, cruza precios unitarios * cantidades, e invoca al `BnbPaymentService` generando QR.
6. **[Laravel]** Recibe webhook de confirmación del BNB y finaliza la venta.

---

## 🛡 Escudo de Seguridad Inquebrantable (Mantenido)

1. **Protocolo Anti-Jailbreak en FastAPI:** Función rápida `contains_prohibited_words()` antes de llamar a LLM y después de recibir el texto. Si detecta intención maliciosa, fuerza `intent: troll` y vacía arrays.
2. **Blindaje RAG:** FastAPI inyecta *Variantes y Atributos* explícitos (Color, Talla, Precios de variante) directamente en el Prompt desde PostgreSQL, erradicando alucinaciones probabilísticas.
3. **Limits de AOV (Average Order Value):** Control de compras falsas.

---

## 🚀 Hoja de Ruta de Migración (Estrategia "Estrangulador")

1. **Migración de Capa de Datos:** Exportar MySQL actual a PostgreSQL. Activar extensión `pgvector`. Actualizar variables de entorno de Laravel y testear Filament.
2. **Añadir Embeddings:** Migración Laravel para añadir campo `embedding vector(1536)` (o dependiente del modelo) a los productos.
3. **Construcción Cerebro FastAPI:** Configurar entorno Docker/venv con Python. Trasladar la inyección RAG, el *System Prompt* y la comunicación de Meta al script asíncrono.
4. **Enrutamiento Webhook:** Apuntar `ngrok/Meta` directamente al endpoint expuesto por FastAPI (FastAPI manejará la validación y el payload).
5. **Apagado de Motor Viejo:** Eliminar el Job `ProcessWhatsAppMessage` y el `DeepSeekService` de la base de código de Laravel, dejando el sistema permanentemente desacoplado.
