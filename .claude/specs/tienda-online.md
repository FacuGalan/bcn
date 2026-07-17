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

### RF-T5: Hardening público
- CORS para el dominio de la tienda.
- Throttle por endpoint ya existente: revisar cupos con tráfico real de tienda.
- `GET /tiendas/{slug}/catalogo`: soporte de cache HTTP (ETag/Last-Modified o
  TTL corto) — es el endpoint más golpeado.

### RFs del CORE posteriores a Fase 0 (pendientes, chicos)
- **RF-T7: IDs de analytics por tienda** (conviene DURANTE Fase 1, es chico):
  campos `ga4_measurement_id` + `meta_pixel_id` en la config de tienda por
  sucursal, editables en el panel (config delivery/tienda), expuestos en
  `GET /tiendas/{slug}`. La tienda inyecta los scripts solo si están cargados.
- **RF-T6: persistencia del tema** (para Fase 6): JSON tema+comportamiento
  por tienda + endpoint de edición + UI/editor en el panel. Hasta entonces
  `GET /tiendas/{slug}` sirve defaults.

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
