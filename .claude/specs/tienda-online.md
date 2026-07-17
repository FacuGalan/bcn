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

### Fase 3: Puntos [post-v1]
Saldo y canje desde la tienda (requiere endpoints core nuevos de puntos +
cliente materializado). La ACUMULACIÓN ya ocurre hoy al convertir el pedido.

### Fase 4: Pago online [post-v1, depende del core]
Checkout MP (crear preferencia/QR dinámico + retorno + webhook). Bloqueado por
el diferido del spec de integraciones (checkout online + refund). El contrato
del pedido ya lo prevé (pagos online no tocan caja, estado "a devolver").

### Fase 5: Marketplace / landing global [post-v1]
Buscador por ubicación + rubro (RF-T4), SEO de tiendas (SSR ya nativo).

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

## Notas y Decisiones

- 2026-07-14: decisiones del usuario — stack Laravel+Livewire (proyecto
  aparte), v1 = invitado + login, pago online en fase posterior.
- 2026-07-16: spec APROBADO por el usuario. Decisión RF-T1: se puede pedir
  sin verificar el email (la verificación desbloquea historial/cuenta).
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
