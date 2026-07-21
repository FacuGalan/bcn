# Tienda Online — Especificación del Proyecto

## Estado: APROBADO (2026-07-16) — Fase 0 COMPLETA (2026-07-16)

> Proyecto APARTE (repo nuevo `bcn-tienda`): frontend multi-tenant de la tienda
> online, un solo deploy para todos los comercios, que consume la API v1 de BCN
> Pymes (contrato `docs/api-v1-delivery.md`, ya con FP en el precio — E13).
> Este spec define la arquitectura del proyecto nuevo Y los endpoints que el
> CORE (este repo) debe sumar (Fase 0). Decisiones del usuario (2026-07-14):
> stack Laravel + Livewire; v1 con checkout invitado + login de consumidores;
> pago online MP en fase posterior.

---

## Contexto y Motivación

El core ya tiene todo lo que la tienda necesita para vender: catálogo público
con el motor real de precios/promos, cotización server-side del carrito
(promos comunes y especiales, cupones, FP con ajuste — paridad exacta con el
panel), alta de pedidos con aceptación manual/automática, seguimiento público
en tiempo real (Reverb) y la identidad global de consumidores (BD config:
`consumidores`, `consumidor_direcciones`, `consumidor_comercio`, guard +
Sanctum) con mapping a clientes tenant según la política de cada comercio.

Falta el producto que el consumidor final usa: la TIENDA. Es un proyecto
aparte porque su ciclo de vida, deploy, seguridad y audiencia son otros
(público anónimo de internet vs. operadores autenticados del comercio).

---

## Principios de Diseño

1. **API-first ESTRICTO**: la tienda consume EXCLUSIVAMENTE la API v1 del
   core. Nunca abre las BD tenant ni config. Si la tienda necesita un dato que
   la API no expone, se agrega el endpoint en el core (Fase 0 o la fase que
   corresponda) — nunca un acceso directo.
2. **La tienda nunca calcula precios** (D12 del spec delivery): todo total
   mostrado sale de `carrito/cotizar`. Cambió el carrito/cupón/FP → re-cotiza.
3. **Una tienda = una sucursal = un slug** (D15). URL:
   `bcn.bcnsoft.com.ar/tienda/{slug}`; el dominio propio (futuro) mapea al
   mismo slug.
4. **Un deploy, N tiendas**: el proyecto es multi-tenant por slug. Branding
   (nombre, logo, colores si se suma) sale de `GET /tiendas/{slug}`.
5. **Mobile-first + PWA**: la tienda es una PWA instalable POR TIENDA
   (manifest dinámico por slug — reusar los aprendizajes del framework
   multi-PWA del core). Diseño mobile-first real: el 90% del tráfico de
   pedidos gastronómicos es móvil.
6. **Invitado primero**: todo el funnel (catálogo → carrito → checkout →
   seguimiento) funciona SIN cuenta. El login suma comodidad (direcciones,
   historial, precios por cliente), nunca es requisito.
7. **El token de seguimiento es la credencial del pedido**: el consumidor
   invitado sigue su pedido por URL con token ULID (ya provisto por la API).
8. **Session-side tokens**: el Bearer de consumidor (Sanctum) vive en la
   SESIÓN server-side de la tienda, nunca en localStorage (XSS-safe). La
   tienda es el único cliente del token.
9. **Degradación honesta**: sin Reverb el seguimiento hace polling; sin
   coordenadas la tienda no inventa alcance (mismo principio D5 del core).
10. **Temable desde el día 1, editor después**: la visión es que cada
    comercio/sucursal le imprima su personalidad a la tienda (fuentes,
    tamaños, colores, layout/orientación de componentes, comportamiento).
    El editor es Fase 6, PERO desde la Fase 1 NINGUNA vista hardcodea
    estilos: toda la UI se construye sobre design tokens (CSS custom
    properties: paleta, tipografía, radios, espaciado/densidad) con un tema
    default moderno, y `GET /tiendas/{slug}` prevé un objeto `tema` en la
    respuesta (v1: defaults + logo). Así la personalización futura es un
    problema de DATOS (un JSON por tienda), no un refactor de vistas.
    Tres reglas concretas para Fase 1 (2026-07-16):
    - **Tokens**: ningún blade usa colores/fuentes/tamaños directos; solo
      clases que resuelven a CSS custom properties del tema.
    - **Layout como datos**: la home se renderiza desde un array de
      secciones (hero, buscador, categorías, destacados, grilla...) con
      orden/variante por sección. En v1 el array es el default fijo, pero
      reordenar o cambiar variantes en el futuro es cambiar datos, no vistas.
    - **Comportamiento como datos**: la respuesta de `GET /tiendas/{slug}`
      prevé también un objeto `comportamiento` (seteos de conducta de la
      tienda; v1: defaults). Tema + comportamiento = un solo JSON por tienda.
11. **Medible desde el día 1 (GA4 + Meta Pixel por tienda)** (2026-07-16):
    cada comercio/sucursal saca métricas de SU tienda con sus propios IDs
    (`ga4_measurement_id`, `meta_pixel_id`), que viajan en
    `GET /tiendas/{slug}` y se inyectan solo si están cargados (RF-T7).
    Reglas de arquitectura:
    - **Capa de tracking única**: helper JS `track(evento, payload)` que
      despacha a `gtag` Y `fbq`. Los componentes emiten eventos semánticos;
      NADIE llama a gtag/fbq directo desde una vista.
    - **Taxonomía estándar, no custom**: GA4 ecommerce (`view_item_list`,
      `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`,
      `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`
      con `items[]`/`value`/`currency`) y eventos estándar de Meta
      (`ViewContent`, `AddToCart`, `InitiateCheckout`, `AddPaymentInfo`,
      `Purchase`) — los nombres estándar habilitan optimización/audiencias.
    - **Los modales se miden con eventos, no con pageviews falsas**: abrir
      el detalle en modal dispara `view_item`/`ViewContent` igual que una
      página. Además, el detalle de artículo hace `pushState` a
      `/articulo/{id}` (URL real: compartible, pageview, y el back del
      móvil cierra el modal).
    - **`purchase` con dedup**: `transaction_id` = pedido, `event_id`
      compartido Pixel/CAPI (el Conversions API server-side es futuro, pero
      el event_id se genera desde v1 para no duplicar cuando llegue).
    - Nombres de rutas/componentes/vistas se eligen alineados a esta
      taxonomía (ej: la vista del funnel de checkout separa datos → envío →
      pago para que los pasos mapeen 1:1 a los eventos).

---

## Arquitectura

### Proyecto nuevo `bcn-tienda` (repo aparte)

- Laravel 11 + Livewire 3 + Alpine + Tailwind (mismo stack del core).
- **Sin BD de negocio propia**: solo session/cache (Redis o file en v1) y la
  cola si hiciera falta. Cero tablas de dominio: el estado vive en el core.
- Cliente HTTP tipado hacia la API v1: `CoreApi` (service) con métodos por
  endpoint, manejo uniforme de errores (`error.code`), timeouts cortos,
  retry solo en GET idempotentes.
- El **carrito vive en la sesión** de la tienda (server-side): ítems +
  cantidades + opcionales + cupón + FP elegida. Cada cambio re-cotiza contra
  `carrito/cotizar` y la UI SOLO muestra lo que respondió el core.
- Echo/Reverb del CORE para el seguimiento (canal público
  `pedidos-delivery.seguimiento.{token}` ya existente). La tienda no tiene
  websockets propios.
- Rate limiting local además del throttle del core (no quemar el cupo del
  core con un bot).

### Páginas (rutas de la tienda)

| Ruta | Página | Fase |
|---|---|---|
| `/tienda/{slug}` | Home de la tienda: branding, abierto/cerrado, catálogo por categorías, buscador | 1 |
| `/tienda/{slug}/articulo/{id}` | Detalle: imagen, descripción, opcionales (min/max/obligatorio), agregar | 1 |
| `/tienda/{slug}/carrito` | Carrito + cupón + tipo (delivery/take-away) | 1 |
| `/tienda/{slug}/checkout` | Datos + dirección (con cotización de envío) + promesa (franja/ASAP) + FP (con ajuste visible) + confirmar | 1 |
| `/tienda/{slug}/pedido/{token}` | Seguimiento en tiempo real (máquina de estados del contrato) | 1 |
| `/registro`, `/login`, `/verificar`, `/recuperar` | Auth del consumidor (global, no por tienda) | 2 |
| `/mi-cuenta` | Perfil, direcciones guardadas, historial de pedidos cross-comercio, re-pedir | 2 |
| `/` (landing global) | Marketplace: "qué tiendas llegan a mi ubicación", filtro por rubro | 5 |

### Flujo del checkout (v1)

1. Carrito en sesión → `POST carrito/cotizar` (tipo + items + cupón + FP) →
   totales/promos/ajuste FP para pintar.
2. Dirección (delivery): autocomplete + `POST envios/cotizar` → costo/alcance.
   Fuera de alcance = mensaje honesto, sin permitir pedir.
3. Promesa: `GET /tiendas/{slug}` (modo) + `GET /franjas` si corresponde.
4. FP: las de `GET /tiendas/{slug}` con su `ajuste_porcentaje` visible
   ("Efectivo −10%"); al cambiarla → re-cotizar (total_a_pagar).
5. `POST pedidos` → 201 con `token_seguimiento` → redirect al seguimiento.
   Respuesta `por_aceptar: true` → pantalla "esperando confirmación" (el
   canal de tiempo real avisa el cambio).

---

## Fase 0 — Endpoints nuevos en el CORE (este repo) — ✅ COMPLETA

Implementada el 2026-07-16 (rama feat/tienda-fase-0). Notas de implementación:
- Tokens de verificación/reset STATELESS (HMAC con APP_KEY, sin tablas
  nuevas): `ConsumidorTokenService`. El de reset embebe un fragmento del hash
  de password → single-use sin storage.
- Emails markdown con branding neutro (`app/Mail/Consumidores/*`); los links
  apuntan a `TIENDA_URL` (`config/tienda.php`) → `/verificar?token=` y
  `/recuperar?token=`.
- Middleware `api.consumidor` (el Bearer debe ser Consumidor, no Comercio).
- Historial: fan-out acotado a tenants con tienda + merge por fecha;
  requiere email verificado (403).
- Marketplace: snapshot por tienda cacheado 5 min (coords, zonas, calendario,
  logo); alcance con la misma semántica de `envios/cotizar`.
- CORS: `config/cors.php` + `CORS_ALLOWED_ORIGINS` (default `*` hasta
  configurar el dominio de la tienda).
- GOTCHA descubierto: los throttle inline de Laravel comparten bucket por
  `sha1(user|ip)` — throttles apilados necesitan el 3er parámetro (prefijo).

La tienda v1 (fases 1-2) necesita que la API crezca en:

### RF-T1: Auth de consumidores (Sanctum sobre guard `consumidores`)
- `POST /v1/consumidores/registro` — nombre, email, password, teléfono →
  crea consumidor + envía verificación de email. Throttle agresivo.
- `POST /v1/consumidores/login` — email+password → Bearer token (Sanctum,
  tokenable Consumidor — la tabla PAT de config ya lo soporta).
- `POST /v1/consumidores/logout` — revoca el token.
- `POST /v1/consumidores/verificar` / `reenviar-verificacion` — flujo de
  email de verificación. DECIDIDO (2026-07-16): se permite pedir SIN
  verificar; la verificación desbloquea el historial/cuenta.
- `POST /v1/consumidores/recuperar` + `restablecer` — password reset por email.
- `GET /v1/consumidores/me` — perfil + banderas (verificado).
- Mailing: los emails salen del CORE (mailer ya configurado) con branding
  neutro BCN (el consumidor es global, no de un comercio).

### RF-T2: Direcciones del consumidor
- `GET/POST/PATCH/DELETE /v1/consumidores/direcciones` — CRUD sobre
  `consumidor_direcciones` (alias, dirección, referencia, localidad_id,
  lat/lng, es_default). El checkout las precarga; el pedido sigue copiando
  snapshot (nada cambia en el alta).

### RF-T3: Historial de pedidos del consumidor
- `GET /v1/consumidores/pedidos` — pedidos del consumidor CROSS-comercio
  (por `consumidor_id`, resolviendo por comercio de la tienda de origen),
  paginado: fecha, tienda (slug/nombre), total, estado, token_seguimiento.
  Alcance v1: solo pedidos delivery/take-away (los únicos de tienda).
- "Re-pedir": la tienda arma el carrito con los renglones del pedido elegido
  y RE-COTIZA (los precios son los de hoy, no los históricos).

### RF-T4: Marketplace (para Fase 5, estructura desde ya)
- `GET /v1/tiendas?lat=&lng=&rubro_id=` — tiendas habilitadas que llegan a
  esa ubicación (radio/zonas de su config) con nombre, slug, rubro, logo,
  abierta ahora, distancia. Cross-comercio (BD config + fan-out controlado a
  config_delivery por sucursal — cachear agresivo).
- `GET /v1/rubros` — catálogo global.

### RF-T5: Hardening público — EN CURSO (2026-07-17)
- CORS para el dominio de la tienda. ✅ (`config/cors.php` +
  `CORS_ALLOWED_ORIGINS`). Pendiente de esta tanda: `exposed_headers: [ETag]`
  para que la revalidación funcione cross-origin desde el subdominio.
- Throttle por endpoint ya existente: revisar cupos con tráfico real de
  tienda. Estado: grupo público 60/min, alta 15/min, cancelar 10/min — se
  mantienen hasta tener tráfico real (decisión: no tunear a ciegas).
- `GET /tiendas/{slug}/catalogo`: cache HTTP ✅ (ETag + `Cache-Control:
  public, max-age=60` + `If-None-Match`→304, ya en `TiendaController`).
  Pendiente de esta tanda: (a) cache SERVER-SIDE del payload
  (`Cache::remember` 60s por sucursal+tipo — hoy cada request re-consulta la
  BD tenant aunque responda 304), (b) tests de ETag/304/Cache-Control (no
  existían).

### RF-T7: IDs de analytics por tienda — EN CURSO (2026-07-17)
- Columnas `ga4_measurement_id` + `meta_pixel_id` (nullable) en la tabla
  `tiendas` (BD config — ahí vive la identidad de la tienda, D15).
- Editables en el panel dentro del apartado Tienda Online del nuevo
  componente de Configuración (RF-T10).
- `GET /tiendas/{slug}` los expone en un objeto `analytics`; la tienda
  inyecta los scripts solo si están cargados (Principio 11):
  `"analytics": { "ga4_measurement_id": "G-XXXX"|null, "meta_pixel_id": "999"|null }`

### RF-T6: persistencia del tema — ADELANTADO PARCIAL (2026-07-17)
- Columna `tema` (JSON nullable) en `config.tiendas`. `GET /tiendas/{slug}`
  pasa a exponer `tema` y `comportamiento` = defaults del core con merge del
  JSON persistido (la tienda NO cambia código — Principio 10).
- Schema v1 del `tema` (design tokens, claves estables):
  `colores` (primario, acento, fondo, superficie, texto), `tipografia`
  (fuente: catálogo cerrado de fuentes self-hosted de la tienda), `radios`
  (none|sm|md|lg|full), `densidad` (compacta|normal|amplia).
  `comportamiento`: v1 sirve defaults (objeto reservado, sin seteos aún).
- La UI de edición v1 es el apartado Tienda Online de RF-T10 (form de
  campos, sin preview en vivo). El EDITOR visual con preview y presets sigue
  siendo Fase 6.

### RF-T10: Configuración → Delivery/Take Away en el panel — EN CURSO (2026-07-17)
Reestructuración de la configuración en el panel del core (pedido del
usuario 2026-07-17):
- **Nuevo ítem de menú** hijo de Configuración: "Delivery / Take Away"
  (menu_items BD pymes, permiso `menu.*` vía observer). La pantalla de
  configuración de delivery DEJA de ser solo un link interno de la pantalla
  de pedidos (el link se mantiene apuntando a la nueva ruta).
- **Mismo componente `ConfiguracionDelivery`** re-homeado bajo
  `configuracion/delivery` con TODO lo que ya tiene (operatoria, envío/zonas,
  calendario de atención/horarios, promesa, numeración display, etc.). La
  ruta vieja `pedidos/delivery/configuracion` redirige (301) a la nueva.
- **Apartado nuevo "Tienda Online"** (sub-componente
  `Configuracion\ConfiguracionTienda`, patrón lazy de
  `ConfiguracionDeliveryEnvio`): si el comercio no tiene tienda para la
  sucursal, CTA "Crear mi tienda online" (genera slug sugerido desde
  comercio+sucursal, editable, unique global); creada la tienda: toggle
  `habilitada`, slug (editable con warning de que cambia la URL pública),
  IDs de analytics (RF-T7) y TODAS las opciones estéticas del tema (RF-T6
  adelantado): colores, tipografía, radios, densidad.
- Permiso funcional nuevo `func.tienda.config` para el apartado tienda
  (crear/editar); el resto del componente sigue con
  `func.pedidos_delivery.config`.
- Deuda declarada (decisión usuario): qué seteos son "de la tienda" vs "del
  panel de delivery" se re-evaluará después de usar el componente unificado;
  por ahora TODO convive en esta pantalla. **→ Resuelta por RF-T11.**

### RF-T11: Rediseño del panel + identidad visual + preview en vivo — IMPLEMENTADO (2026-07-17, rama feat/tienda-config-redesign-rf-t11)

Rediseño del componente unificado (pedido del usuario 2026-07-17, salda la
deuda declarada de RF-T10):

- **Full-width**: la pantalla usa todo el ancho (grid 2 col en xl para la
  zona delivery: General | Promesa; Envío/zonas a lo ancho).
- **Orden**: primero lo estrictamente del panel/delivery; al final el
  apartado "Tienda Online" que agrupa TODO lo de la tienda.
- **Switch maestro** del apartado = `tiendas.habilitada` (decisión usuario):
  prendido despliega la config y publica al guardar; apagado colapsa y
  despublica al guardar. Prenderlo sin tienda la CREA al instante
  (`TiendaService::crearParaSucursal`, siempre despublicada). El PADRE
  (`ConfiguracionDelivery`) es el ÚNICO escritor de `habilitada`; el hijo
  `ConfiguracionTienda` pierde el CTA de creación y el checkbox publicada.
- **Calendario de atención y Pedidos externos** (data del padre,
  `config_delivery`): viven DENTRO del apartado tienda desplegado; fallback
  a la zona delivery cuando no hay tienda o está apagada (partials del
  padre, un solo lugar visible a la vez — la data aplica siempre).
- **Logo y portada** (`tiendas.logo_path`/`portada_path`, migración config):
  upload en el panel con `ImagenTiendaService` (patrón ImagenArticulo: MIME
  real finfo, whitelist jpg/png/webp, re-encode WebP 85, UUID, path
  `tiendas/{comercio_id}/`; logo ≤800px, portada ≤1600×900). Se procesan AL
  GUARDAR; preview con temporaryUrl().
- **Preview en vivo**: drawer lateral derecho con mock del storefront
  pintado con CSS vars derivadas de los design tokens del form (Alpine
  x-data estático + entangle .live — reflejo instantáneo).
- **API (aditivo 2026-07-17)**: `GET /v1/tiendas/{slug}` suma
  `logo_url`/`portada_url` absolutas; el marketplace `GET /v1/tiendas`
  respalda el `logo_url` documentado (prima logo de tienda, fallback
  pantalla-cliente/empresa). Contrato actualizado en api-v1-delivery.md.
- bcn-tienda consume logo/portada en su fase correspondiente (pendiente del
  lado tienda). **→ Hecho en RF-T12 (portada; logo ya estaba).**

### RF-T12: Visor en vivo de la tienda REAL en el panel — IMPLEMENTADO (2026-07-18, cross-repo)

Reemplaza el mock del drawer de RF-T11 por la TIENDA REAL embebida
(decisiones usuario: mock solo como fallback de despublicada; visor dentro
del apartado Tienda Online, 2 columnas en xl).

- **Core** (rama feat/tienda-visor-preview-rf-t12, apilada sobre RF-T11):
  apartado en 2 columnas (config | visor sticky). Publicada persistida →
  iframe `{tienda.url}/tienda/{slug}?preview=1`; despublicada → mock
  compartido (partial tienda-preview-mock; drawer queda para <xl).
  `Alpine.data('tiendaPreview')` (resources/js/tienda-preview.js): entangle
  de los 8 tokens + $watch con debounce 150ms → postMessage al iframe con
  targetOrigin = origen de config('tienda.url') (nunca '*'). Logo/portada
  por eventos Livewire `tienda-preview-imagenes` (URLs server-rendered,
  temporaryUrl del upload pendiente); `tienda-guardada` recarga el iframe.
- **bcn-tienda** (rama feat/preview-portada-rf-t12): (1) portada_url en el
  hero (banner + overlay); (2) modo preview con `?preview=1` (query, NUNCA
  sesión: cookies SameSite no viajan en iframe cross-site): whitelist
  `core.panel_origins` (env PANEL_ORIGINS), snapshot FRESCO con Cache::put,
  analytics anulados (no ensuciar métricas), `preview.js` con el port
  DIFF-vs-default de TiendaActual::temaCssVars (comentarios espejo — volver
  al default remueve las vars y la tienda recupera su paleta oklch + dark
  auto); (3) CSP `frame-ancestors 'self' {panel_origins}` GLOBAL
  (hardening anti-clickjacking).
- **Protocolo** (canal frontend-only, la API v1 NO cambia): tienda→panel
  `{tipo:'tienda-preview-ready', slug, path}` al cargar y en cada
  livewire:navigated; panel→tienda `{tipo:'tienda-preview-estado',
  tema:{...shape del contrato}, logoUrl, portadaUrl}`. Validación de origen
  en ambos sentidos + hex re-validados antes de setProperty.
- Limitación documentada: en prod con dominios de distinto site, el
  carrito dentro del iframe no tiene sesión (SameSite) — el visor valida
  estética; la operatoria completa se prueba en la pestaña real.

### RF-T13: Personalización estética avanzada de la tienda — IMPLEMENTADO (2026-07-18, cross-repo: core feat/tienda-estetica-avanzada-rf-t13 + tienda feat/estetica-avanzada-rf-t13; validado en vivo)

Cross-repo (core + bcn-tienda). Decisiones del usuario 2026-07-18. Toda
opción nueva tiene su valor "nada/ninguno". Persistencia: TODO dentro del
JSON `config.tiendas.tema` (aditivo, SIN migración de columnas); defaults
en `Tienda::TEMA_DEFAULTS` y expuesto por `temaCompleto()` en
`GET /v1/tiendas/{slug}` (contrato api-v1-delivery.md actualizado,
aditivo). Triple espejo obligatorio para lo que refleja en vivo:
`Tienda.php` (core) ↔ `TiendaActual.php` (tienda) ↔ `preview.js`.

**Shape nuevo del `tema` (sub-objetos aditivos):**

```json
"tema": { "...existente...": "colores/tipografia/radios/densidad",
  "portada":    { "overlay": true, "posicion": "center" },
  "textos":     { "slogan": "", "descripcion": "" },
  "redes":      { "facebook": null, "instagram": null },
  "catalogo":   { "layout": "grilla" },
  "destacados": { "modo": "banner", "adorno": "ninguno" },
  "promos":     { "mostrar_home": false } }
```

- `portada.overlay` (bool): fade con color primario sobre la portada
  (comportamiento actual) o imagen cruda. `portada.posicion`:
  `top|center|bottom` → `object-position` vertical (el ancho SIEMPRE es
  pantalla completa — decisión usuario: no hace falta 3×3). El panel
  muestra el recorte sobre la portada real y refleja en vivo en el visor.
- `textos.slogan` (≤120 chars): en el hero bajo el nombre del comercio.
  `textos.descripcion` (≤1000): SECCIÓN nueva de la home debajo del hero
  (vacío = la sección no se renderiza).
- `redes.facebook`/`instagram`: URLs validadas (host facebook.com /
  instagram.com, https) → botones con icono en el hero. `null` = sin botón.
- `catalogo.layout`: `grilla` (actual, foto protagonista) | `lista`
  (renglón-tarjeta: imagen izq., nombre/descripción/precio der. —
  bcn-tienda ya tiene la variante lista en catalogo.blade.php, RF-T13 la
  alimenta desde el tema).
- `destacados.modo`: `banner` (carrusel horizontal arriba, actual) |
  `tarjeta_grande` (intercalado entre los artículos, full-width y doble
  alto) | `ninguno`. `destacados.adorno` (solo tarjeta_grande): `glow`
  (brillo alrededor) | `badge` (redondo sobresaliente del borde con icono
  de destacado) | `ambos` | `ninguno`.
- `promos.mostrar_home` (bool): activa el aviso de promociones en la home.

**Promociones (híbrido, decisión explícita del usuario):**

1. **Genéricas** (no atadas a un artículo puntual: `PromocionEspecial` —
   combos/NxM/grupos — y `Promocion` de alcance no-artículo): el catálogo
   suma el campo ADITIVO `promociones_genericas: [{nombre, descripcion}]`
   (vigentes HOY para el canal tienda, calculadas por
   `CatalogoTiendaService`). La home las muestra como badge/desplegable
   "Promociones de hoy" (si `promos.mostrar_home`).
2. **Por artículo**: el catálogo suma `precio_lista` (ADITIVO, solo cuando
   difiere del `precio` final) → la card muestra el precio de lista TACHADO
   + precio oferta.
3. **Carrito**: cotizar debe permitir mostrar qué promo aplica a qué
   renglón con precio tachado (verificar qué expone ya la respuesta de
   `carrito/cotizar` tras bcn-tienda PR #8; extender ADITIVAMENTE con
   `precio_lista`/detalle por renglón solo si falta).

**Panel (`ConfiguracionTienda`)**: nuevos controles en "Apariencia" —
toggle overlay + selector de posición (con recorte visible sobre la
miniatura de la portada), inputs slogan/descripcion, inputs FB/IG,
selector layout artículos, selector modo+adorno destacados, toggle promos
en home. Validaciones server-side (regex URL por red, largos, `in:` por
enum). Reflejo EN VIVO por postMessage: overlay, posicion, slogan,
descripcion, redes (extender shape del mensaje `tienda-preview-estado` y
espejo en preview.js). Layout/destacados/promos_home: recarga al guardar
(iframe reload — son server-rendered; limitación documentada).

**bcn-tienda**: hero (slogan, botones redes con SVG inline, overlay
condicional, object-position), sección nueva `texto`, sección/badge
`promos` (genéricas), variante de catálogo desde
`tema.catalogo.layout` (via `seccionesHome()`/variante), partial nuevo de
destacado `tarjeta_grande` con adornos (glow = box-shadow con
color-mix del primario; badge = absoluto sobresaliente con icono),
precios tachados en cards y carrito. Tokens CSS nuevos solo si hacen
falta (`--tienda-*`).

**Fases:** F1 contrato+modelo core (TEMA_DEFAULTS + show() + doc contrato)
· F2 panel core (UI+validaciones+eventos preview) · F3 catálogo/promos
core (precio_lista + promociones_genericas + cotizar si falta) · F4
tienda hero/texto/portada · F5 tienda layout+destacados+tachados · F6
tienda promos home+carrito · F7 espejo preview.js + traducciones (es/en/pt
ambos repos) + docs (@docs-sync, design-tokens.md, preview-panel.md,
api-v1-delivery.md) + tests (core: ConfiguracionTienda*, ApiV1Delivery;
tienda: TemaYAnalytics/Portada/Preview/CoreApiContract/TiendaActual).

**Criterios de aceptación:** cada opción nueva default = comportamiento
actual (tienda existente NO cambia sin tocar nada); snapshot viejo sin las
claves → la tienda usa defaults (tolerancia a clave ausente); overlay
off = imagen cruda; posicion refleja en visor sin guardar; slogan/redes/
descripcion visibles solo si cargados; layout lista muestra renglones;
destacado tarjeta_grande ocupa full-width doble alto con el adorno
elegido; promos genéricas listadas solo con mostrar_home on y vigentes
hoy; precio_lista tachado solo cuando difiere; contract tests de la
tienda en verde contra fixtures actualizados.

### RF-T14: Configuración de tienda POR ARTÍCULO (vidriera) — IMPLEMENTADO (2026-07-20, cross-repo: core feat/tienda-config-articulos-rf-t14 + tienda feat/config-articulos-rf-t14; F1-F5 completas, tests verdes en ambos)

Cross-repo (core + bcn-tienda). Decisiones del usuario 2026-07-20: badges
PREDEFINIDOS + texto libre; galería MÚLTIPLE de fotos por artículo;
reordenamiento por DRAG & DROP (artículos y categorías). Primer bloque de
config de tienda a nivel ARTÍCULO: a diferencia del `tema` (JSON en
config.tiendas), estos datos viven en la BD TENANT. Todo es ADITIVO: un
artículo sin nada configurado se ve EXACTAMENTE igual que hoy.

**UI (panel `ConfiguracionTienda`)**: sección nueva "Artículos de la
tienda" DENTRO del contenedor "Identidad y apariencia", debajo de
"Presentación del catálogo", en la COLUMNA DE CONFIGURACIÓN (izquierda) —
el visor sticky de la derecha sigue siempre visible acompañando el scroll
(pedido explícito del usuario). Sub-componente Livewire embebido
`ConfiguracionTiendaArticulos` (NO lazy full-page; patrón embebido) para
no engordar más `ConfiguracionTienda`. Lista los artículos VISIBLES en la
tienda de la sucursal activa (mismo criterio que `CatalogoTiendaService`:
activo + vendible + visible_tienda), agrupados por categoría colapsable,
en el MISMO orden que muestra la tienda. Por fila: thumbnail (1ª foto de
tienda o imagen operativa), nombre, toggle destacado, chips de badges,
botón "Fotos (n)" que expande la galería inline. GUARDADO INMEDIATO por
acción (toggle, drop, upload, borrar, badges al confirmar) — estos datos
no forman parte del form del tema ni del botón "Guardar tienda"; el visor
recarga (debounced) tras cada guardado porque el catálogo es
server-rendered (misma limitación documentada de RF-T13). Permiso:
`func.tienda.config`.

**Modelo de datos (tenant, migración + regenerar tenant_tables.sql):**

1. Tabla nueva `{prefix}articulo_imagenes_tienda`: `id`, `articulo_id`
   (FK a `{prefix}articulos`, cascade), `path` (varchar 255), `orden`
   (int, default 0), timestamps. Índice por `articulo_id`. Máx 5 fotos
   por artículo (validación en service). Galería GLOBAL del artículo
   (no por sucursal), igual que la imagen operativa.
2. `{prefix}articulos.badges_tienda` JSON NULL: array
   `[{"tipo":"sin_tacc"}, {"tipo":"custom","texto":"Receta de la nona"}]`.
   Máx 4 badges por artículo. Catálogo predefinido (constante
   `Articulo::BADGES_TIENDA`): `sin_tacc`, `vegetariano`, `vegano`,
   `picante`, `nuevo`, `mas_vendido`, `artesanal`, `sin_azucar` + `custom`
   (texto libre ≤30 chars). Icono+color por tipo se resuelven en la
   TIENDA (espejo en bcn-tienda con tokens `--tienda-*`); el core solo
   persiste/valida tipos.
3. Destacado y orden REUSAN lo existente: `articulos.destacado`,
   `articulos.orden`, `categorias.orden` (drag & drop renumera 10,20,30…
   dentro de la categoría / entre categorías). Sin columnas nuevas.
   ORDEN 100% MANUAL (decisión 2026-07-20): destacado NO fuerza posición
   en el listado — es decoración (banner/tarjeta grande).
4. Ampliación 2026-07-20 (2da tanda, mismo branch): badges nuevos
   `sin_lactosa|kosher|con_frutos_secos`; `articulos.alergenos_tienda`
   JSON (texto libre por comas, máx 15×40 chars, botonera de atajos:
   soja/huevos/pescado/mariscos/maní/mostaza/sésamo/cereales con
   gluten/lupino/apio) → catálogo expone `alergenos[]` aditivo → aviso
   "Contiene: …" en el DETALLE de la tienda; y
   `articulos.descripcion_tienda` TEXT (vacía ⇒ el catálogo sirve la
   operativa por la MISMA clave `descripcion`). Badges en cards de la
   tienda: SOLO icono (label en title/aria-label); chip completo con
   palabra en el detalle. Visor: refresco por morph (postMessage
   `tienda-preview-refrescar-catalogo` → `$wire.$refresh()`), preview
   saltea el cache local del catálogo.

**Services:** `ImagenArticuloTiendaService` nuevo (clon del pipeline
`ImagenArticuloService`: finfo MIME real, whitelist jpg/png/webp,
re-encode WebP q85, UUID, resize 800px, path
`articulos/{comercio_id}/tienda/{uuid}.webp`, borra archivo al eliminar).
Reordenamiento y badges: métodos en el componente vía transacción
`pymes_tenant` (o service chico si crece). Drag & drop en el front:
SortableJS bundleado en `resources/js` (patrón Alpine.data, NADA inline).

**API v1 (aditivo, contrato api-v1-delivery.md):** cada artículo del
catálogo suma `imagenes: [url…]` (galería ordenada; `[]` si no tiene —
`imagen_url` se MANTIENE como fallback/compat) y
`badges: [{tipo, texto|null}]` (`[]` si no tiene). CLAVE: invalidar el
cache server-side del catálogo (`Cache::forget` de las keys
`comercio:sucursal:tipo`, ambos tipos) al guardar CUALQUIER config por
artículo — sin esto el panel guarda pero la tienda/visor muestran viejo
hasta 60s (y el ETag acompaña solo).

**bcn-tienda:** `detalle-articulo` con CARRUSEL cuando `imagenes.length >
1` (scroll-snap horizontal + dots; sin librería); cards
(grilla/lista/destacado) muestran `imagenes[0] ?? imagen_url`. Badges:
chips con icono SVG inline + color por tipo (espejo del catálogo de
badges) en cards y detalle; `custom` renderiza el texto con estilo
neutro. Fixtures de contrato (`catalogo.json`) + contract tests
actualizados (claves nuevas opcionales, tolerancia a ausencia).

**Fases:** F1 core datos (migraciones tenant + tenant_tables.sql + modelo
`ArticuloImagenTienda` + relación/constantes en `Articulo` +
`ImagenArticuloTiendaService` + tests service) · F2 core API
(`CatalogoTiendaService` imagenes[]/badges[] + invalidación cache +
contrato .md + ApiV1DeliveryTest) · F3 core panel lista (sub-componente
embebido: lista agrupada, destacado, badges con modal, smoke test) · F4
core panel galería + drag & drop (upload múltiple, orden fotos,
SortableJS artículos/categorías) · F5 tienda (carrusel + badges +
fixtures/contract tests) · F6 cierre (traducciones es/en/pt ambos repos,
@docs-sync, validación en vivo).

**Criterios de aceptación:** artículo sin config nueva → catálogo y
tienda IDÉNTICOS a hoy (aditivo puro); snapshot viejo sin `imagenes`/
`badges` → la tienda no rompe (tolerancia a clave ausente); galería con
fallback a imagen operativa; máx 5 fotos / 4 badges validados
server-side; drag & drop persiste y la tienda refleja el orden tras
recarga; guardar config por artículo invalida el cache del catálogo (se
ve en el visor sin esperar 60s); badges predefinidos con icono/color
consistentes y custom neutro; contract tests de la tienda en verde;
smoke test del sub-componente nuevo.

### RF-T15: Auto-guardado del panel Delivery/Tienda — IMPLEMENTADO (2026-07-20)

Decisión del usuario 2026-07-20: en Configuración → Delivery/Tienda ya NO
hay botón Guardar — todo cambio persiste AL INSTANTE. La ÚNICA excepción
(a propósito) es la APARIENCIA de la tienda (tema/logo/portada/contenido/
catálogo, RF-T6/T11/T13): conserva su botón "Guardar apariencia" para que
el público nunca vea la tienda a medio cambiar mientras el comerciante
elige en el visor.

- `ConfiguracionDelivery`: hook `updated()` con whitelist
  `PROPS_AUTOGUARDADO` → `persistirConfig()` (núcleo silencioso;
  `guardarConfig()` queda como wrapper con toast para tests/acciones
  explícitas). Repeaters (horarios, franjas, feriados) persisten al mutar.
  Vista: inputs a `wire:model.live` (texto con debounce 500-800ms),
  indicador "Guardando…/Los cambios se guardan automáticamente" en el
  header, sin botones Guardar.
- Switch Tienda Online: publica/despublica AL INSTANTE (antes difería al
  guardado); prenderlo sin tienda la crea Y publica en el mismo acto.
  `persistirConfig()` ya no toca `tiendas.habilitada` (único escritor: el
  toggle). Se eliminó el badge "(se publica al guardar)".
- `ConfiguracionTienda`: slug (normalizado + unique check, recarga el
  visor vía `tienda-guardada`) y analytics (GA4/Pixel) auto-guardan con
  debounce 1000ms; validación fallida = error visible y NO persiste.
  El botón pasó de "Guardar tienda" a "Guardar apariencia".
- La config por artículo (RF-T14) ya nacía con guardado inmediato.

### RF-T16: Pedidos por ENCARGUE (día futuro) — IMPLEMENTADO (2026-07-20, cross-repo: core feat/tienda-encargos-rf-t16 + tienda feat/encargos-rf-t16; F1-F5 completas, tests verdes. Ajuste vs diseño: el reporte de producción NO tiene ítem de menú propio — se llega desde la solapa Encargos del panel, mismo público)

Cross-repo (core + bcn-tienda) + PANEL DE ATENCIÓN (pedido explícito del
usuario: los pedidos de la tienda terminan ahí y hay que distinguir "de
ahora" vs programados, con gestión propia y reporte de producción).
APROVECHA la estructura que la Fase 8 de pedidos-delivery dejó creada:
`pedidos_delivery.programado_para` (columna existente, hoy write-only),
keys `acepta_programados` + `programados_aparecen_min_antes` en
config_delivery, y `articulos.permite_programado` (hoy sin lectura de
negocio). CERO migraciones nuevas. Los CUPOS por franja siguen FUERA
(sub-fase futura, como define ese spec).

**Config (auto-guardado RF-T15, sección "Encargos" junto al calendario):**

- `acepta_programados` (key existente): toggle maestro "Tomar pedidos por
  encargue". OFF = nada de encargos en tienda/API/panel (default actual).
- CALENDARIO PROPIO de encargos (decisión usuario 2026-07-20): keys nuevas
  ADITIVAS del JSON `config_delivery.encargos` = `{dias_laborales,
  horarios, feriados, anticipacion_horas (default 24), max_dias_adelante
  (default 30)}` — mismo shape que el calendario de atención y PRECARGADO
  desde él al activar el toggle por primera vez. Permite encargar para
  días en que el local no atiende al público.
- `programados_aparecen_min_antes` (key existente, default 60): cuántos
  minutos antes de la hora pactada el encargo APARECE en el kanban.

**Artículos:** REUSA `articulos.permite_programado` ("disponible para
encargos"). Se expone TAMBIÉN en la vidriera (ConfiguracionTiendaArticulos,
checkbox por artículo con guardado inmediato — hoy solo está en el ABM).
El catálogo público suma `permite_encargo` (bool, aditivo) por artículo.

**Services/API (aditivo, contrato api-v1-delivery.md):**

- `DeliveryEnvioService::validarProgramado(Sucursal, Carbon, array
  $articuloIds)` (firma ya prevista en el spec de delivery): fecha/hora
  dentro del calendario de encargos (día válido + rango horario + no
  feriado + ventana anticipación→max_dias) y TODOS los artículos con
  `permite_programado`.
- Endpoint nuevo `GET /v1/tiendas/{slug}/encargos?tipo=[&fecha=Y-m-d]`:
  sin fecha → fechas disponibles de la ventana; con fecha → slots de 30
  min generados de los rangos del día. 404/vacío si no acepta encargos.
- `POST carrito/cotizar` y alta: campo nuevo `entrega.programado_para`
  (ISO datetime). Cotizar valida y devuelve error claro si un artículo no
  permite encargo. El alta persiste `programado_para` + `hora_pactada_at`
  (= programado_para) y respeta aceptación manual/automática como hoy.
- Tienda CERRADA con encargos activos: `GET /tiendas/{slug}` suma
  `encargos: {activo, anticipacion_horas, max_dias_adelante}`; la tienda
  puede seguir vendiendo SOLO en modo encargo (el pedido inmediato sigue
  rechazándose fuera de horario, como hoy).

**bcn-tienda (checkout):** si `encargos.activo`, el paso de entrega suma
la opción "Encargar para otro día" → selector de fecha (del endpoint) +
selector de slot. Si el carrito tiene artículos sin `permite_encargo`, se
avisa antes de cotizar (y el core igual valida server-side). Con la
tienda cerrada y encargos activos, el hero muestra "Cerrado ahora — podés
encargar para otro día" y el flujo solo ofrece encargo.

**Panel de atención (PedidosDelivery):**

1. El kanban/lista EXCLUYE los programados cuya hora esté a más de
   `programados_aparecen_min_antes` minutos (query, sin scheduler); al
   entrar en ventana aparecen como cualquier pedido, con badge.
2. Badge "Encargo · {d/m H:i}" (color propio, ej. violeta) en tarjeta,
   lista y detalle para TODO pedido con `programado_para`.
3. SOLAPA nueva "Encargos" en el mismo panel: lista de programados
   futuros agrupados por día (fecha, hora, cliente, tipo, total, items),
   con acciones ver detalle / cancelar / "pasar a preparación ahora"
   (lo adelanta al kanban). Filtro por rango de fechas.
4. Filtro "Programados" en el selector de estado existente.

**Reporte de producción (nuevo, patrón ReportesCompras):** página
`pedidos/encargos/produccion` (menú + permiso nuevos): rango de fechas →
agrupado por DÍA → ARTÍCULO con cantidades totales (suma de
pedidos_delivery_detalle de programados activos), drill-down a los
pedidos. Para saber qué producir en los próximos días. Export simple
(imprimible) v1.

**Fases:** F1 core config+calendario encargos (keys, precarga, UI panel
config auto-save) · F2 core services+API (validarProgramado, endpoint
encargos, cotizar/alta con programado_para, permite_encargo en catálogo,
contrato) · F3 panel atención (ocultamiento por ventana, badges, solapa
Encargos, filtro) · F4 reporte de producción (componente+ruta+menú+
permiso) · F5 vidriera (checkbox encargos por artículo) + tienda
(checkout fecha+slot, hero cerrado-con-encargos, fixtures/contract
tests) · F6 cierre (traducciones, docs, validación en vivo).

**Criterios de aceptación:** toggle OFF ⇒ comportamiento idéntico a hoy
(aditivo puro); encargo fuera de calendario/ventana o con artículo no
apto ⇒ 422 con motivo; el programado NO aparece en el kanban hasta su
ventana y SÍ en la solapa Encargos; badge visible en kanban/lista/
detalle; reporte suma cantidades correctas por día/artículo excluyendo
cancelados; tienda cerrada + encargos ⇒ solo flujo encargo; contract
tests de la tienda verdes; smoke de los componentes nuevos.

### RF-T17: Carrito reordenado + dirección con Google Maps — APROBADO (2026-07-21)

Cross-repo (mayormente bcn-tienda). El carrito pasa a concentrar TODA la
decisión de compra; el checkout queda para identidad del cliente y
confirmación (ver RF-T19/T20).

**Orden nuevo de secciones del carrito** (`Carrito.php` + blade):

1. Artículos pedidos (como hoy).
2. Selector delivery / retiro en el local (se mueve arriba de lo demás).
3. Dirección de entrega — SOLO visible con delivery (hoy vive en el
   checkout; SE MUEVE al carrito).
4. Cupón.
5. Formas de pago (RF-T18: desplegable + 2 FP).

La aclaración de precios del carrito pasa a decir: "los precios pueden
variar según el tipo de entrega y la forma de pago seleccionadas".

**Dirección con Google Maps (paridad con alta de pedidos delivery del
panel — `ManejaDomicilio` + `domicilio-mapa.js` + spec
`domicilio-google-maps.md`):**

- Botón "Usar mi ubicación actual" PRIMERO: geolocation → reverse
  geocoding → coordenadas + texto legible de la dirección (hoy solo
  guarda lat/lng sin texto).
- Input de dirección con `PlaceAutocompleteElement` de Google: al elegir
  una sugerencia se obtienen coordenadas + texto legible. El texto es
  SIEMPRE editable a mano (el valor editado no pisa las coordenadas).
- Botón "Abrir mapa": modal con el punto en el mapa y
  `AdvancedMarkerElement` arrastrable; mover el pin actualiza
  coordenadas y re-resuelve el texto (igual que el modal del panel).
- Direcciones guardadas del consumidor logueado: siguen como chips (hoy),
  arriba del input.
- bcn-tienda porta su propio `Alpine.data` (espejo de
  `domicilio-mapa.js`, en `resources/js/`, patrón bundle) y usa
  `GOOGLE_MAPS_API_KEY` propio (`.env` de bcn-tienda,
  `config/services.php`). SIN key ⇒ degradación al comportamiento
  actual (input texto + "usar mi ubicación" sin reverse geocoding).
- El payload de `POST /pedidos` NO cambia (ya acepta
  `direccion.{texto,referencia,lat,lng}`); el core no necesita cambios
  para este RF.

**Criterios:** carrito en el orden 1-5; dirección solo con delivery;
autocomplete setea lat/lng + texto; pin arrastrable actualiza ambos;
texto editable siempre; sin key la tienda funciona como hoy; contract
tests sin cambios (no cambia el contrato); smoke Livewire de Carrito.

### RF-T18: Formas de pago en tienda — orden, disponibilidad por sucursal y pago con 2 FP — APROBADO (2026-07-21)

Cross-repo. La FP se elige en el carrito (desplegable) y el pedido puede
declararse con HASTA 2 formas de pago, con los mismos ajustes
(descuentos/recargos por FP) y promociones que calcula el sistema.

**Core — disponibilidad y orden (decisión usuario: POR SUCURSAL):**

- Migración tenant ADITIVA: columna `disponible_en_tienda` tinyint(1)
  default 1 en el pivot `formas_pago_sucursales` (+ regenerar
  `tenant_tables.sql`). Fila ausente en el pivot = disponible (default
  aditivo: nada cambia para comercios existentes).
- `FormaPago::esDeclarableEnTienda(sucursalId)` suma el filtro del flag;
  `TiendaController::formasPagoPublicas()` pasa de `orderBy('nombre')` a
  `orderBy('orden')` (columna existente, ya gestionada por el modal de
  orden del ABM).
- UI panel: en `GestionarFormasPago`, sección por sucursal existente del
  pivot (activo / ajuste override) suma el toggle "Disponible en tienda
  online". (Es el lugar donde ya se administra la relación FP×sucursal;
  ConfiguracionTienda solo la referencia con un link.)

**Core — cotización y alta con 2 FP (contrato ADITIVO, máx 2 en v1):**

- `POST carrito/cotizar`: campo nuevo `pagos: [{forma_pago_id, monto}]`
  (si viene, `forma_pago_id` singular se ignora; singular sigue vigente
  para compat). El core valida: FPs declarables en tienda, montos > 0,
  suma = total a pagar (tolerancia de redondeo); calcula el ajuste de
  CADA FP sobre su monto con la MISMA lógica de `WithAjusteFormaPago` /
  `WithPagosDesglose` del panel (paridad exacta) y devuelve desglose:
  `pagos: [{forma_pago_id, nombre, monto_base, ajuste_porcentaje,
  monto_ajuste, monto_final}]` + `total_a_pagar` recalculado. Promos y
  cupones siguen calculándose como hoy (motor único
  `CotizadorCarritoTienda`).
- `POST /pedidos`: campo nuevo `pagos: [{forma_pago_id, monto,
  paga_con?}]` (compat: `pago.{forma_pago_id, paga_con}` sigue
  funcionando = 1 FP). `PedidoTiendaService::registrarPagoDeclarado()`
  guarda N pagos PLANIFICADOS vía `PedidoDeliveryService::agregarPago()`
  — MISMO formato que el alta manual (`forma_pago_id, monto_base,
  ajuste_porcentaje, monto_ajuste, monto_final, monto_recibido, vuelto,
  planificado`). El panel los ve idénticos a un pedido cargado a mano.
- Contrato `docs/api-v1-delivery.md` actualizado (aditivo, sin romper
  v1). Fixtures/contract tests de bcn-tienda actualizados.

**bcn-tienda — selector:**

- Reemplazar radios por DESPLEGABLE (respeta el orden que manda la API).
- Botón "Usar 2 formas de pago": habilita segundo desplegable + un input
  de monto por cada FP. La suma debe dar el total; al editar un monto el
  otro se autocompleta con el resto. Cada cambio re-cotiza
  (`carrito/cotizar` con `pagos[]`) y muestra el desglose por FP
  (ajustes, descuentos/recargos) que respondió el core — la tienda NUNCA
  calcula.

**Criterios:** FP con flag OFF en la sucursal no aparece en la tienda;
orden respetado; cotización con 2 FP devuelve el mismo resultado que el
panel para el mismo carrito (test de paridad en el core); suma ≠ total ⇒
422 claro; pedido con 2 FP queda con 2 pagos planificados idénticos al
alta manual (test de service); singular `pago`/`forma_pago_id` sigue
funcionando (compat, contract test); smoke del ABM FP con el toggle.

### RF-T19: Datos del cliente configurables (email / cumpleaños) — APROBADO (2026-07-21)

Cross-repo. Con RF-T17/T18 el checkout queda en: paso "tus datos" (este
RF) → promesa → confirmación (RF-T20).

**Core — config por sucursal (patrón claves aditivas en
`config_delivery`, como encargos RF-T16; editable en
`ConfiguracionDelivery` con auto-guardado RF-T15):**

- `checkout.pedir_email`: `'no' | 'opcional' | 'obligatorio'` (default =
  comportamiento actual de la API: opcional).
- `checkout.pedir_cumpleanios`: bool (default false).
- `GET /tiendas/{slug}` expone `checkout: {pedir_email,
  pedir_cumpleanios}` (aditivo).

**Core — fecha de nacimiento:**

- Migración tenant ADITIVA: `clientes.fecha_nacimiento` DATE NULL (+
  regenerar tenant_tables.sql).
- Migración config ADITIVA: `consumidores.fecha_nacimiento` DATE NULL.
- `POST /pedidos`: `cliente.fecha_nacimiento` opcional (date, pasado).
  Se persiste en el cliente tenant; si el pedido es de consumidor
  logueado, se copia también a su cuenta global (para pre-llenar en
  otras tiendas). `GET /consumidor` (me) expone `fecha_nacimiento`.
- Validación server-side: si `pedir_email = 'obligatorio'`, el alta
  exige email (422). El cumpleaños NUNCA es obligatorio.

**bcn-tienda — paso "tus datos":**

- Email visible según config (oculto / opcional / requerido).
- Cumpleaños visible solo si `pedir_cumpleanios`, siempre opcional, con
  leyenda: "Se solicita para participar de promociones y descuentos".
- Consumidor logueado: campos pre-llenados desde su cuenta.

**Criterios:** cada combinación de config muestra/exige lo correcto
(tienda + validación core en paridad); cumpleaños se guarda en cliente
tenant y en consumidor global si hay Bearer; defaults ⇒ comportamiento
idéntico a hoy; contract tests con el bloque `checkout` nuevo; smoke de
ConfiguracionDelivery con las keys nuevas.

### RF-T20: Checkout estético — promesa, pago y resumen final — APROBADO (2026-07-21)

Solo bcn-tienda (la cotización ya devuelve todo lo necesario).

- **Promesa ("cuándo")**: reemplazar radios planos por cards/segmented
  moderno (design system de la tienda, tema-aware), mostrando la promesa
  de tiempo de forma destacada.
- **Pago**: sección rediseñada. Con 2 FP muestra claramente el valor a
  abonar con CADA una (nombre, monto final, ajuste si hubo). Input "¿Con
  cuánto pagás?" por cada FP de efectivo (`permite_vuelto`) para
  calcular el vuelto (el vuelto se muestra por FP). Estética moderna.
- **Confirmación final**: al final de todo, resumen COMPLETO del
  carrito: ítems, subtotal, descuentos aplicados, promociones que
  participan (nombre y monto), cupón, puntos, costo de envío, desglose
  por forma de pago y total a pagar — todos los montos tal como los
  respondió la cotización.

**Criterios:** vuelto correcto por FP efectivo; resumen muestra CADA
promo/descuento con su monto (lo que devuelve `carrito/cotizar`); dark
mode + móvil verificados; smoke Livewire Checkout.

### RF-T21: Banner "Promociones de hoy" — popover con precio fijo y condiciones — APROBADO (2026-07-21)

Cross-repo. Hoy el banner (RF-T13) es un acordeón que EMPUJA el
contenido y la API solo manda `nombre` + `descripcion`.

**Core (`CatalogoTiendaService::promocionesGenericas()`, aditivo):**

- Cada promo suma: `precio_fijo` (number|null — PromocionEspecial de
  precio fijo), `condiciones` (list<string> legibles generadas de las
  condiciones estructuradas: NxM, mínimo de unidades/monto, días,
  horario, forma de pago, categoría, etc.). Contrato actualizado.

**bcn-tienda (`secciones/promos.blade.php`):**

- Reemplazar el acordeón por un BOTÓN chico pero llamativo (pill con el
  count, color acento, micro-animación sutil) que NO empuja el layout:
  abre un POPOVER/overlay moderno (Alpine, `position: absolute`/fixed +
  transición; en móvil bottom-sheet) con la lista de promos.
- Cada promo: nombre, descripción, PRECIO FIJO destacado si lo tiene, y
  sus condiciones como chips/lista secundaria.

**Criterios:** el contenido de la página NO se desplaza al abrir; promo
de precio fijo muestra el precio; condiciones legibles correctas (test
del service de catálogo); tema claro/oscuro + móvil; contract tests con
los campos nuevos.

### RF-T22: Colores personalizados legibles en dark mode — APROBADO (2026-07-21)

Cross-repo. Problema: un color personalizado del tema se emite como
paleta FIJA en ambos modos (`TiendaActual::temaCss()`), pero los tokens
NO personalizados siguen siendo adaptativos. Caso típico: el comercio
define fondo de tarjetas CLARO, no toca el texto → en la preview (modo
claro) se ve negro sobre claro, pero el dark mode real pone la tinta
blanca → ilegible. Solución en DOS frentes (decisión 2026-07-21):

**bcn-tienda — auto-contraste (garantía de legibilidad):**

- En `temaCssVars()`: si el comercio personalizó un token de FONDO
  (`fondo`/superficie, `tarjetas`/superficie-alta) y NO personalizó su
  par de TEXTO, derivar `tinta`/`tinta-suave` (y `borde`) por
  luminancia/contraste del fondo elegido (umbral por luminancia
  relativa, salida oscura o clara) y emitirlos FIJOS para ambos modos
  — el fondo custom arrastra su par de texto. Si el comercio
  personalizó también el texto, se respeta lo suyo (no se pisa).
- Unit tests del cálculo (fondo claro ⇒ tinta oscura; fondo oscuro ⇒
  tinta clara; texto custom ⇒ intacto).

**Core — toggle de modo en el visor del panel (visibilidad):**

- El visor en vivo (RF-T12) suma toggle sol/luna "Ver en modo oscuro":
  fuerza el modo del iframe de preview. Lado tienda: en modo preview se
  acepta el parámetro (query `?modo=claro|oscuro` del visor) que setea
  `data-modo` en `:root` — la infraestructura `data-modo` YA existe
  (`app.css`, `comportamiento.modo_color`).
- Nice-to-have (si es barato en F4): aviso de contraste insuficiente en
  el panel cuando texto y fondo personalizados no contrastan.

**Criterios:** fondo claro custom + dark mode del cliente ⇒ texto
legible (fijo); toggle del visor muestra ambos modos sin recargar la
config; tienda sin personalización ⇒ CERO cambios (adaptativa como
hoy).

### RF-T23: Autocompletado del checkout por cookie (invitados) — APROBADO (2026-07-21)

Solo bcn-tienda (sin cambios de contrato). Al CONFIRMARSE un pedido, la
tienda guarda en una cookie first-party (cifrada por Laravel, larga
duración ~6 meses) los datos del comprador: `nombre, telefono, email,
fecha_nacimiento?, direccion {texto, referencia, lat, lng}`.

- Objetivo doble: no volver a tipear nada en el próximo pedido
  (principalmente INVITADOS sin cuenta) y evitar llamadas de gusto a la
  API de Maps (texto + coordenadas ya resueltos viajan en la cookie).
- La cookie es del dominio de la tienda (un deploy, N tiendas) ⇒ vale
  ENTRE tiendas de distintos comercios: los datos personales se
  pre-llenan siempre.
- La DIRECCIÓN pre-llenada se valida SIEMPRE contra el rango de entrega
  de la tienda actual (cotización de envío existente, que ya rechaza
  fuera de zona): si no está en rango, se muestra aviso "esta dirección
  está fuera de la zona de entrega de esta tienda, ingresá otra" y se
  deja el selector de dirección editable (RF-T17).
- Consumidor LOGUEADO: sus direcciones guardadas y datos de cuenta
  tienen prioridad; la cookie es el fallback para invitados.
- El pedido confirmado ACTUALIZA la cookie (última dirección usada
  gana).

**Criterios:** segundo pedido invitado ⇒ todo pre-llenado sin tocar
Maps; dirección fuera de zona en otra tienda ⇒ aviso y no bloquea
elegir otra; logueado ⇒ prioridad de la cuenta; cookie cifrada
(EncryptCookies) y solo lectura server-side.

### Fases RF-T17..T23 (cada una = un PR mergeable; core primero)

- **F1 (core)**: RF-T18 lado core — migración pivot + filtro + orden +
  toggle en ABM + contrato. — ✅ COMPLETA (2026-07-21, rama
  `feat/tienda-checkout-operacion`; tests FormaPagoTest +
  ApiV1DeliveryTest + SmokeConfiguracion verdes).
- **F2 (core)**: RF-T18 multi-pago — cotizar/alta con `pagos[]`,
  `registrarPagoDeclarado` N pagos, tests de paridad, contrato. —
  ✅ COMPLETA (2026-07-21; desglosarPagos en CotizadorCarritoTienda como
  fuente única, FP principal = pagos[0], envío excluido proporcional
  D17, limitación v1: sin usar_puntos con 2 FP).
- **F3 (core)**: RF-T19 lado core — keys checkout + migraciones
  fecha_nacimiento (tenant + config) + exposición + validaciones +
  contrato.
- **F4 (core)**: RF-T21 lado core (promos enriquecidas + contrato) +
  RF-T22 toggle sol/luna en el visor (+ aviso de contraste si es
  barato).
- **F5 (tienda)**: RF-T17 + RF-T18 lado tienda — carrito reordenado,
  dirección Maps, desplegable FP + 2 FP, fixtures/contract tests.
- **F6 (tienda)**: RF-T19/T20/T21 lado tienda (paso datos, promesa,
  pago, resumen final, popover promos) + RF-T22 auto-contraste y
  `?modo=` en preview + RF-T23 cookie de autocompletado.
- **F7**: cierre — traducciones (es/en/pt), docs (@docs-sync),
  validación en vivo del usuario.

### RF-T8: Saldo de puntos del consumidor (Fase 3)

`GET /v1/tiendas/{slug}/puntos` *(Bearer consumidor)* — el saldo y las reglas
del programa de puntos DE ESE comercio para el consumidor logueado:

```json
{ "data": {
    "activo": true,              // programa activo (comercio + sucursal) Y consumidor con cliente
    "saldo": 120,                // puntos disponibles (ledger movimientos_puntos)
    "saldo_en_pesos": 6000,      // saldo × valor_punto_canje (para mostrar)
    "valor_punto_canje": 50,
    "minimo_canje": 10,
    "puede_canjear": true        // saldo >= minimo_canje
} }
```

- Resolución: consumidor → `consumidor_comercio.cliente_id` del comercio de la
  tienda. **Sin cliente materializado, programa inactivo (comercio o
  sucursal) o cliente con `programa_puntos_activo=false` → `activo: false`
  con saldo 0** (degradación honesta, sin error).
- Saldo por sucursal solo si `configuracion_puntos.modo_acumulacion =
  por_sucursal` (misma semántica del panel).

### RF-T9: Canje de puntos en cotización y alta (Fase 3)

Extensión ADITIVA de `carrito/cotizar` y `POST /pedidos`: campo booleano
`usar_puntos` (solo tiene efecto con Bearer de consumidor con cliente).

- **El canje es un PAGO, no un descuento de precio** (paridad con el panel:
  VentaPago `es_pago_puntos` bajo la FP "Canje Puntos"): `total_final` no
  cambia; la cotización devuelve el bloque `puntos` y el
  `total_a_pagar` neto:

```json
"puntos": { "usados": 40, "monto": 2000, "saldo_restante": 80,
            "a_ganar": 5 },
"total_a_pagar": 4551.43       // total_a_pagar previo − puntos.monto
```

- **Monto del canje = MÁXIMO posible** (decisión UX 2026-07-17: toggle, sin
  input de monto): `min(saldo × valor_punto_canje, total_a_pagar)`,
  respetando `minimo_canje`. `puntos.usados = ceil(monto /
  valor_punto_canje)` (misma fórmula del panel).
- **Alcance v1 de la fase: SOLO canje como descuento sobre el total.** El
  canje de artículo gratis por puntos (modo del panel) queda para una
  iteración posterior.
- `puntos.a_ganar`: estimado de acumulación del pedido (fórmula del panel:
  monto efectivo × multiplicador de la FP ÷ monto_por_punto, con el redondeo
  de la config; excluye el monto pagado con puntos). Se muestra SIEMPRE que
  el programa esté activo (aunque no canjee), como incentivo.
- **Alta del pedido** con `usar_puntos: true`: el core revalida el saldo
  FRESCO, registra el pago-puntos del pedido (los campos que
  `procesarCanjesPuntos()` ya honra al convertir) y responde el pedido con
  el bloque `puntos`. **El MovimientoPunto se crea recién en la conversión a
  venta** (mismo diseño del panel: si el saldo se gastó en el medio, la
  conversión falla la parte de puntos y el comercio lo resuelve — ventana
  asumida y documentada).
- Cupones/promos/FP se aplican ANTES (sobre el precio); los puntos pagan al
  final. Cambió el carrito/cupón/FP → re-cotizar (regla general).

---

## Fases del proyecto TIENDA (repo nuevo)

### Fase 1: Funnel invitado completo [v1]
Scaffolding + CoreApi + home por slug + catálogo + detalle con opcionales +
carrito en sesión + checkout invitado (envío, promesa, FP con ajuste, cupón)
+ alta + seguimiento en tiempo real + PWA por tienda. **Vendible por sí sola.**

### Fase 2: Consumidores [v1]
Registro/login/verificación/recuperación (contra RF-T1), direcciones guardadas
(RF-T2), historial + re-pedir (RF-T3), checkout precargado, cotización con
Bearer (precios por cliente donde haya mapping — ya soportado por la API).

### Fase 3: Puntos — ✅ COMPLETA (2026-07-17)
Saldo y canje desde la tienda contra RF-T8/RF-T9 (canje = pago por el máximo
vía toggle; solo descuento sobre el total en v1; "puntos a ganar" visible en
el checkout). Requiere consumidor con cliente materializado (alta automática
del comercio recomendada). La ACUMULACIÓN ya ocurre hoy al convertir el
pedido; el canje del pedido lo liquida la conversión (`procesarCanjesPuntos`).

### Fase 4: Pago online [post-v1, depende del core]
Checkout MP (crear preferencia/QR dinámico + retorno + webhook). Bloqueado por
el diferido del spec de integraciones (checkout online + refund). El contrato
del pedido ya lo prevé (pagos online no tocan caja, estado "a devolver").

### Fase 5: Marketplace / landing global — ✅ COMPLETA (2026-07-17, bcn-tienda#9)
Buscador por ubicación + rubro (RF-T4), SEO de tiendas (SSR ya nativo).
Lado core: cero cambios (RF-T4 ya existía desde Fase 0).

### Fase 6: Editor de personalización [post-v1]
Editor visual en el panel del CORE para que cada comercio/sucursal configure
su tienda: paleta de colores, tipografía (fuentes self-hosted por el criterio
Lighthouse), tamaños, radios, densidad, orden/variante de las secciones de la
home y seteos de comportamiento. Con preview en vivo y presets de temas.
Requiere del CORE (RF-T6, se especificará en su momento): persistencia del
JSON tema+comportamiento por tienda, endpoint de edición y UI en el panel.
`GET /tiendas/{slug}` pasa de servir defaults a servir el JSON persistido —
la tienda NO cambia código (garantizado por el Principio 10).

---

## Modelo de Datos

- **Core**: sin tablas nuevas para fases 0-2 (consumidores/direcciones/
  mapping/tokens ya existen). Puntos/pago online definirán lo suyo en sus
  specs.
- **Tienda**: sin BD de dominio (solo sesiones/cache).

## Criterios de Aceptación (v1 = Fases 0+1+2)

- [ ] Un consumidor invitado completa el funnel entero desde el móvil:
  catálogo → carrito con promo aplicada → cupón → efectivo con descuento
  (total_a_pagar correcto) → dirección con envío cotizado → franja/ASAP →
  pedido creado → seguimiento en vivo hasta entregado.
- [ ] El total mostrado en el checkout == total del pedido creado == total que
  ve el panel (paridad E13, verificada con FP con ajuste y promo por FP).
- [ ] Pedido "por aceptar" muestra la espera y reacciona en tiempo real a la
  aceptación/rechazo (incluido timeout → "demorado").
- [ ] Registro + verificación + login + logout + recuperación funcionan; el
  checkout precarga dirección default; el historial lista y "re-pedir"
  rearma el carrito re-cotizado.
- [ ] Consumidor con cliente materializado ve SUS precios (lista por cliente)
  en catálogo y checkout con el mismo total que el pedido.
- [ ] Tienda cerrada / artículo agotado / fuera de alcance: mensajes honestos,
  sin permitir pedir (la API ya bloquea; la tienda no debe llegar al 422).
- [ ] PWA instalable por tienda con su nombre/ícono; Lighthouse mobile ≥ 90
  en performance/accesibilidad en el catálogo.
- [ ] Seguridad: token de consumidor solo en sesión server-side; CORS
  restringido; rate limit local; ningún acceso directo a BD del core.
- [ ] Temabilidad (Principio 10): cambiar el JSON de tema (colores/fuente/
  radios/densidad) u orden de secciones de la home repinta la tienda SIN
  tocar código; ningún blade contiene colores/fuentes hardcodeados.
- [ ] Analytics (Principio 11): con IDs cargados, el funnel completo emite
  la taxonomía GA4/Meta estándar (view_item_list → view_item [incluso en
  modal] → add_to_cart → begin_checkout → add_shipping_info →
  add_payment_info → purchase con transaction_id y event_id); sin IDs
  cargados, no se inyecta ningún script de terceros.

## Notas y Decisiones

- 2026-07-14: decisiones del usuario — stack Laravel+Livewire (proyecto
  aparte), v1 = invitado + login, pago online en fase posterior.
- 2026-07-16: spec APROBADO por el usuario. Decisión RF-T1: se puede pedir
  sin verificar el email (la verificación desbloquea historial/cuenta).
- 2026-07-16: decisión de personalización — v1 arma el esqueleto con la
  ARQUITECTURA temable (tokens + layout/comportamiento como datos, reglas
  del Principio 10); el editor visual y la persistencia del tema quedan
  para Fase 6 (+ RF-T6 en el core). Fase 0 ya deployada en producción.
- 2026-07-16: decisión analytics — GA4 + Meta Pixel por tienda como
  requisito de arquitectura (Principio 11): capa track() única, taxonomía
  estándar, modales medidos por eventos + pushState en detalle de artículo,
  event_id para dedup CAPI futuro. IDs por sucursal = RF-T7 en el core
  (chico, entra durante Fase 1).
- 2026-07-17: decisión de arquitectura PWA — NO hay una sola PWA con
  dirección inicial condicional: son DOS apps instalables que conviven en el
  mismo dominio porque el manifest se declara POR PÁGINA. (a) PWA por tienda
  (Principio 5): las páginas `/tienda/{slug}/...` declaran un manifest
  dinámico `/tienda/{slug}/manifest.webmanifest` generado desde el snapshot
  de `GET /tiendas/{slug}` (nombre, logo, theme_color del tema), con
  `start_url`/`scope` = `/tienda/{slug}` — quien instala desde el link de un
  comercio instala LA APP DE ESE COMERCIO. (b) PWA marketplace (Fase 5): la
  landing `/` declara el manifest global BCN (`start_url`/`scope` = `/`),
  abre en el buscador de tiendas por ubicación (RF-T4). Los scopes anidados
  no se pisan. Reglas derivadas: disciplina de scope (nada de una tienda
  fuera de `/tienda/{slug}/...`, nada global adentro); auth global fuera del
  scope de tienda (la barra de navegación temporal al salir del scope es el
  trade-off aceptado, patrón OAuth); `start_url` con `?utm_source=pwa`
  (analytics); íconos v1 = logo de la API, set maskable por tienda = futuro
  RF del core; con dominio propio por tienda el mismo manifest dinámico
  sirve `scope: /` en ese dominio sin cambios. Nota Instagram: los links
  tocados dentro de IG abren en su navegador embebido, donde NO se puede
  instalar PWA — el visitante usa la tienda como web normal (el funnel
  invitado ya lo cubre); un aviso "abrí en tu navegador para instalar" es
  mejora opcional futura.
- 2026-07-17: cambio ADITIVO al contrato v1 para habilitar "re-pedir"
  (RF-T3): `GET /tiendas/{slug}/pedidos/{token}` suma `items[]`
  (`articulo_id`, `nombre`, `cantidad`, `opcionales[{opcional_id, nombre,
  cantidad}]`), excluyendo el renglón-concepto del costo de envío y los
  conceptos sin artículo. Sin esto la tienda no podía rearmar el carrito
  (ni el historial ni el seguimiento exponían los renglones). Aditivo =
  no rompe consumidores existentes; documentado en api-v1-delivery.md.
- 2026-07-17: decisión del usuario — imágenes DE TIENDA como feature futura:
  la imagen que el artículo tiene cargada en el sistema es solo el fallback
  v1; la visión es un sector aparte de configuración visual de la tienda
  donde cada comercio define VARIAS fotos por artículo específicas para la
  tienda (galería), independientes de la imagen operativa del panel.
  Requiere RF del core (persistencia + endpoint; natural junto al editor de
  Fase 6 / RF-T6, `tema` ya es el JSON por tienda). La tienda ya renderiza
  desde `imagen_url` del catálogo, así que el cambio futuro es de DATOS del
  lado core, no un refactor de vistas. Mientras tanto, fix 2026-07-17:
  `imagen_url` del catálogo pasa a URL absoluta (relativa se rompía por
  cross-origin).
- 2026-07-17: decisión de deploy — la tienda se sirve en el SUBDOMINIO
  `tienda.bcnsoft.com.ar` (vhost Apache propio en el mismo server del core),
  no montada por path en el dominio del core: las rutas globales del
  consumidor (`/login`, `/registro`, `/mi-cuenta`, ...) y el `/build` de
  Vite chocarían con el core. `bcn.bcnsoft.com.ar/tienda/*` redirige (301)
  al subdominio para que los links compartidos sigan vivos. En el `.env`
  del core: `TIENDA_URL=https://tienda.bcnsoft.com.ar` (links de los
  emails) y `CORS_ALLOWED_ORIGINS` restringido a ese origen. Ajusta el
  Principio 3 (la URL canónica pasa al subdominio; el dominio propio por
  tienda sigue siendo futuro). Playbook completo:
  `bcn-tienda/.claude/docs/deploy-playbook.md`.
- 2026-07-17: Fase 3 (puntos) — decisiones del usuario: (a) UX de canje =
  TOGGLE "usar mis puntos" que aplica el máximo canjeable (sin input de
  monto); (b) alcance v1 = solo canje como descuento sobre el total (el
  artículo-gratis del panel queda para después); (c) el checkout muestra los
  puntos que el pedido va a ganar (estimado del core en la cotización).
  Diseño: RF-T8 (saldo) + RF-T9 (canje como PAGO con paridad de conversión;
  el MovimientoPunto se crea al convertir, ventana de saldo asumida).
- 2026-07-17: Fase 5 (marketplace) — decisiones del usuario: (a) la
  geolocalización se pide con BOTÓN EXPLÍCITO en la landing (al entrar se
  listan todas las tiendas; el permiso del navegador recién se dispara al
  tocar "Ver tiendas cerca mío"); (b) branding de la landing = "Tiendas BCN"
  con la paleta/logo del core (#FFAF22/#222036, icono_bcn), aplicado VÍA
  TOKENS en el layout marketplace (Principio 10 intacto). Sin analytics en
  la landing (los IDs son por tienda — Principio 11). Cache local en la
  tienda de las consultas sin ubicación (60s; rubros 1h, espejo de los TTLs
  del core) + throttle de la ruta `/`. Implementación: bcn-tienda#9.
- 2026-07-14: pasada 1 de revisión del armado: bug de cupones corregido y FP
  en el precio con paridad panel (PR #158, E13). La API quedó lista para el
  funnel invitado completo.
- El repo nuevo tendrá su propio CLAUDE.md (convenciones del proyecto tienda)
  y su propia suite de tests, incluidos contract tests contra la API v1
  (fixtures del contrato congelado — si el core rompe el contrato, lo detecta
  la suite de la tienda).
- Pendientes conocidos del core que NO bloquean v1: convertir-en-cliente
  (panel), cupos por franja + programados (Fase 8 delivery), webhooks
  salientes, puntos por API, checkout MP.
- 2026-07-17: pedido del usuario — la configuración de la tienda es un
  componente del panel con ítem de menú propio Configuración → Delivery/
  Take Away (RF-T10): concentra la config de delivery existente + horarios
  de apertura + apartado de creación/config de la tienda online (estética
  incluida). La separación fina tienda-vs-delivery se evaluará con el
  componente en uso. Esto adelanta la persistencia del tema (RF-T6) como
  columna JSON en `config.tiendas` con form simple; el editor visual con
  preview sigue en Fase 6.
