# Ronda: Cuenta del consumidor — perfil, favoritos y beneficios de registrado

> Spec de la ronda 2026-07-30. Continúa la numeración de RFs de
> `tienda-ronda-crossmap-2.md` (último: RF-T38). Workflow cross-repo de
> siempre: los RF de CORE van primero (endpoint + contrato + tests), la
> tienda consume después.

---

## Estado de partida (auditoría 2026-07-30, ambos repos)

**Ya existe y FUNCIONA (esta ronda solo lo valida con tests donde falte):**
- Registro global con **auto-login** (el token Sanctum sirve YA, sin verificar
  — `AuthController::registro` responde `{token, consumidor}`) y **email de
  verificación** (TTL 48 h del link, reenvío con throttle). La cuenta es una
  sola para todas las tiendas BCN (BD config: `consumidores`).
- `/mi-cuenta` con direcciones (crear/default/borrar, tope 10) e historial
  cross-comercio (requiere verificado; fan-out sobre tenants) + repedir.
- Precarga en carrito/checkout: sesión del carrito > perfil de la cuenta >
  cookie `bcn_comprador`; direcciones guardadas como chips en el carrito con
  autoselección de la default (con coords).
- Puntos en el carrito (bloque de la cotización + toggle canje, RF-T8/T9).
- **Mapping consumidor↔cliente**: `config.consumidor_comercio(consumidor_id,
  comercio_id, cliente_id)` + `pedidos_delivery.consumidor_id`. Con la flag
  D11 (`comercios.tienda_alta_cliente_automatica`) el cliente tenant se crea
  solo en el primer pedido; el consumidor es rastreable a sus clientes por
  comercio vía `Consumidor::clienteIdEn()`.

**Huecos que esta ronda cierra:**
- Sin edición de perfil (no existe `PATCH /consumidores/me`).
- Direcciones de mi-cuenta SIN coordenadas ni edición (el carrito sí usa
  coords de las guardadas → una dirección creada en mi-cuenta queda coja).
- Puntos invisibles fuera del carrito (ni mi-cuenta ni endpoint agregado).
- Favoritos: inexistentes en todo el stack.
- Sin plazo de verificación (solo bloquea el historial).
- Trazabilidad inversa cliente→consumidor sin índice ni relación ni panel.
- Auth sin marca (layout pelado, sin logo bcnsoft ni botón volver).
- Home sin acceso a pedidos anteriores (solo banner "¿Lo de siempre?" del
  último pedido).

---

## RFs de CORE

### RF-T39: Editar perfil del consumidor
- `PATCH /v1/consumidores/me` (Bearer): `nombre` (2-150), `telefono`
  (nullable ≤30), `fecha_nacimiento` (nullable date, no futura). Devuelve el
  perfil actualizado (mismo shape de `GET /me`).
- **Email NO se cambia en v1**: la sal del token de verificación ES el email
  (cambiarlo invalida los links en vuelo) y exige flujo propio con
  re-verificación → post-v1. Password se cambia por el flujo `recuperar`.
- Contrato: documentar el PATCH y de paso alinear `GET /me` (ya devuelve
  `fecha_nacimiento` y el doc lo omite).

### RF-T40: Plazo de verificación — cuenta RESTRINGIDA a los 7 días
- Decisión (2026-07-30): a los **7 días** de creada sin verificar, la cuenta
  pasa a **restringida** — NO se borra ni se cierra sesión:
  - `POST /tiendas/{slug}/pedidos` con Bearer restringido → 403
    `verificacion_requerida` (mensaje accionable). Comprar como invitado
    sigue abierto siempre.
  - Acumular/canjear puntos requiere pedido logueado ⇒ queda cubierto solo.
  - Login, `/me`, direcciones y reenvío siguen andando: verificar
    des-restringe al instante (no hay estado persistido: se computa de
    `created_at` + `email_verified_at`).
- `GET /me` y la respuesta del registro exponen `verificacion_vence_el`
  (ISO-8601, null si verificado) para que la tienda muestre la cuenta
  regresiva sin calcular nada.
- Alternativa DESCARTADA: borrar cuentas no verificadas a los 7 días (pierde
  clientes y datos por un email tardío). Limpieza de cuentas nunca
  verificadas y sin pedidos >90 días: mantenimiento futuro, fuera de ronda.

### RF-T41: Favoritos de comercios
- Tabla config `consumidor_favoritos`: `consumidor_id` FK cascade,
  `tienda_id` FK cascade, timestamps, unique(consumidor, tienda).
- `GET /v1/consumidores/favoritos` → `[{slug, nombre, logo_url, habilitada,
  localidad}]` (shape del marketplace).
- `PUT /v1/consumidores/favoritos/{slug}` (idempotente) y
  `DELETE /v1/consumidores/favoritos/{slug}`.
- No toca `GET /tiendas/{slug}` (snapshot público sigue cacheable): la tienda
  sabe si es favorita consultando la lista con Bearer.

### RF-T42: Puntos del consumidor cross-comercio
- `GET /v1/consumidores/puntos` (Bearer): por cada comercio con mapping en
  `consumidor_comercio`, el estado del programa: `[{tienda: {slug, nombre},
  activo, saldo, saldo_en_pesos}]`. Reusa `PuntosTiendaService::info()`;
  tenant caído se saltea con log (patrón del historial). Sin gate de
  verificación (es SU saldo; el gate de verificado queda para historial).

### RF-T43: Trazabilidad inversa consumidor↔cliente
- Índice nuevo sobre `consumidor_comercio (comercio_id, cliente_id)`.
- Relación `PedidoDelivery::consumidor()` + helper estático
  `ConsumidorComercio::consumidorDeCliente(int $comercioId, int $clienteId)`.
- Panel (mínimo de la ronda): en el detalle/edición del cliente, badge
  "Cuenta BCN: {email}" cuando el cliente está vinculado a un consumidor.
- Tests de ida y vuelta: alta de pedido con D11 ON crea mapping y es
  consultable en ambos sentidos; con D11 OFF el pedido guarda consumidor_id
  igual (rastreable sin cliente).

### RF-T47: Canje de artículos por puntos en la tienda (core)
- La maquinaria YA existe para el POS y se REUSA entera: `articulos.
  puntos_canje` (costo en puntos, >0 = canjeable), renglones
  `pagado_con_puntos`, `PuntosService::canjearArticuloConPuntos()` (ledger
  `MovimientoPunto` tipo canje-artículo en la conversión a venta) y las
  cabeceras `puntos_canjeados_articulos`/`articulos_canjeados_monto` que
  `PedidoDeliveryService` ya persiste. Esta ronda solo agrega la PUNTA de la
  tienda:
- **Config del comercio**: columna nueva `articulos_sucursales.canje_tienda`
  (bool default false, migración tenant + tenant_tables.sql). Toggle ⭐ en la
  grilla de `ConfiguracionTiendaArticulos`, habilitado solo si el artículo
  tiene `puntos_canje > 0` (si no, tooltip "Cargale puntos de canje en el
  artículo"). Guardado inmediato + invalidación del cache de catálogo.
- **Catálogo** (`GET /tiendas/{slug}/catalogo`, aditivo): `articulos[].
  puntos_canje` (int, solo si canje_tienda y puntos_canje > 0; ausente si
  no) — la tienda no muestra nada si falta la clave (tolerancia).
- **Cotización y alta**: `items[].canjear_con_puntos` (bool). El core valida
  con Bearer: saldo suficiente para TODOS los canjes (además del canje-pago
  RF-T9 si viene `usar_puntos`), artículo efectivamente canjeable, y marca el
  renglón `pagado_con_puntos` (importe $0 para el total, puntos descontados
  del saldo). La cotización devuelve por ítem `canjeado_con_puntos: true` y
  el bloque `puntos` suma `usados_en_articulos`. Sin Bearer o saldo corto →
  422 con código claro (la UI no debería llegar).
- Restricción v1 (espejo del POS): el canje de artículo convive con
  `usar_puntos` (canje-pago) — el saldo se valida contra la SUMA; sigue
  incompatible con 2 FP (limitación RF-T9 existente).

### RF-T49: Sign in with Google (core)
- **Decisión (2026-07-30, aprobada por Facu)**: login/registro con Google
  para bajar la fricción del alta. La tienda obtiene el **ID token** de
  Google Identity Services (GIS) en el navegador y el CORE lo verifica y
  resuelve la cuenta (la tienda nunca decide identidad).
- `POST /v1/consumidores/auth/google` (público, throttle): body
  `{credential}` (el JWT de GIS). El core verifica firma contra las JWKS de
  Google (cacheadas), `iss` (`accounts.google.com` con o sin https), `aud`
  (= `GOOGLE_CLIENT_ID` de config/services) y expiración. Inválido → 422.
- Resolución de cuenta (en orden):
  1. `google_id` ya existe → login.
  2. email ya existe → **linkea** (setea `google_id`); si Google es
     autoritativo y no estaba verificada → `email_verified_at = now()`
     (el login con Google prueba la posesión de la casilla).
  3. no existe → crea consumidor con `nombre` (claim `name`, fallback parte
     local del email), `email`, `google_id`, `password = null`.
- **Verificación**: Google es AUTORITATIVO si el email termina en
  `@gmail.com`, o si `email_verified=true` y viene `hd` (Workspace). En ese
  caso la cuenta nace/queda VERIFICADA (sin mail de verificación ni plazo
  RF-T40). Caso raro no autoritativo → flujo de verificación normal.
- Migración config `consumidores`: `google_id` varchar nullable UNIQUE +
  `password` NULLABLE (cuentas Google no tienen). Login con password de una
  cuenta sin password → mismo error genérico (no revela el método).
- Respuesta: shape de login (`{token, consumidor}`) + `creado` (bool).
- Dependencia nueva: `firebase/php-jwt` (verificación local del JWT, sin el
  SDK gigante de Google). Service `GoogleIdTokenService` mockeable en tests.
- Config: `GOOGLE_CLIENT_ID` en `.env` de core Y tienda (mismo client). Alta
  en Google Cloud Console con orígenes `tienda.bcnsoft.com.ar` +
  `http://localhost:8001` (la hace Facu; el código cae con gracia si falta
  la env: 503 `google_no_configurado`).
- Contrato: documentar en `api-v1-delivery.md`.

## RFs de TIENDA

### RF-T44: Estética de auth con marca bcnsoft
- Layout `components/layouts/consumidor.blade.php`: logo bcnsoft arriba
  (mismo asset del sello powered-by), tarjeta centrada, y botón VOLVER
  (flecha en esquina superior izquierda, patrón visual de la tienda) que va a
  `?volver` validado o `history.back()` con fallback al marketplace.
- Registro: la bienvenida menciona el email de verificación y el plazo de 7
  días (RF-T40) sin tono amenazante.

### RF-T45: Mi cuenta 2.0
- **Editar datos personales**: nombre/teléfono/cumpleaños → `PATCH /me`
  (RF-T39). Email solo lectura.
- **Direcciones con la misma funcionalidad del carrito**: autocomplete +
  mapa + "mi ubicación" (reutilizar `tienda-domicilio.js` fuera del contexto
  de una tienda), alta y EDICIÓN con coordenadas (el PATCH del core ya
  acepta lat/lng), borrar y default como hoy.
- **Import de la cookie**: si está logueado, no tiene NINGUNA dirección y la
  cookie `bcn_comprador` trae dirección válida (texto + coords) → se da de
  alta automáticamente como default con un aviso ("Guardamos la dirección
  que venías usando"). La cookie no se toca (sigue de fallback invitado).
- **Puntos por comercio** (RF-T42): sección con saldo por tienda.
- **Favoritos** (RF-T41): lista con link a cada tienda y quitar.
- Aviso de verificación con cuenta regresiva (`verificacion_vence_el`).

### RF-T46: Beneficios de registrado dentro de la tienda
- **Corazón de favorito** en la tarjeta del hero de la tienda (toggle
  PUT/DELETE; invitado → login con `volver` a la tienda).
- **"Pedidos anteriores" en la home**: span sutil (logueado + verificado +
  tiene pedidos en ESA tienda) que abre un sheet con los últimos pedidos de
  esta tienda (fecha, total, estado) y botón "Repetir" por pedido
  (`RepedirService`, precios de hoy, avisos de omitidos como siempre). El
  banner "¿Lo de siempre?" (último pedido) se mantiene.
- **Checkout con cuenta restringida** (RF-T40): si `verificacion_vence_el`
  pasó, la tienda intercepta ANTES del 403 con CTA "Reenviar email" y la
  alternativa "Salir y comprar como invitado".
- Menú ⋮ del buscador suma "Mi cuenta" / "Salir" (hoy el acceso a la cuenta
  desaparece con la barra compacta).
- Ya existente que solo se VALIDA: precarga de datos desde el perfil,
  chips de direcciones guardadas en carrito, puntos con Bearer, catálogo
  con precios por cliente.

### RF-T48: Canje de artículos por puntos en la tienda (frontend)
- Card/detalle del artículo canjeable (logueado, saldo alcanza): chip
  "⭐ Canjealo por N puntos" en el detalle; al activarlo, el artículo entra
  al carrito como CANJE (cantidad 1, sin opcionales pagos). Invitado o saldo
  corto: chip informativo "⭐ N puntos" sin acción (con CTA de login si
  invitado).
- Carrito: el renglón canjeado se muestra $0 con badge ⭐ y "−N puntos";
  el bloque de puntos muestra el total de puntos usados (artículos + canje-
  pago RF-T9). Quitar el renglón devuelve el saldo (re-cotiza).
- Todo número sale de la cotización del core (regla de oro: la tienda no
  calcula nada).

### RF-T50: Botón Google + escape del webview (tienda)
- **Botón "Continuar con Google"** en login y registro (antes del form de
  email): botón oficial de GIS; el credential se POSTea al backend de la
  tienda, que llama a `POST /v1/consumidores/auth/google` del core y guarda
  el Bearer en sesión como siempre. `creado=true` → bienvenida.
- **Webview embebido (IG/FB)**: Google bloquea OAuth ahí
  (`disallowed_useragent`, política de Google desde 2023, sin workaround).
  Detección de webview: la existente de la ronda mobile. Comportamiento:
  - **Android**: el botón dispara un escape a Chrome vía `intent://` con
    URL de continuidad (abajo). Un toque y sigue en Chrome.
  - **iOS**: intento de escape best-effort; si no se puede, guía de un paso
    "⋯ → Abrir en el navegador" + botón copiar link.
  - El registro por email sigue SIEMPRE disponible como alternativa.
- **Token de continuidad de carrito**: el escape abre un navegador con
  cookies nuevas (sesión virgen → carrito vacío). La URL de escape lleva un
  token firmado de corta duración (~15 min, cache de la tienda) que al
  aterrizar re-hidrata el carrito y vuelve a la pantalla donde estaba. Sin
  token válido → home de la tienda normal (degradación silenciosa).
- GIS requiere origen en la allowlist del client: prod
  `tienda.bcnsoft.com.ar`, dev `http://localhost:8001`.

---

## Orden de implementación

1. **Core** (PR bcn): RF-T39 → T43 + RF-T47 + contrato (`api-v1-delivery.md`)
   + tests (`ApiV1ConsumidoresTest`, `ApiV1DeliveryTest` + nuevos).
   — **HECHO** (PR #189, 2026-07-30).
2. **Core** (PR bcn): RF-T49 (Sign in with Google) + contrato.
3. **Tienda** (PR bcn-tienda): RF-T44 → T46 + RF-T48 + RF-T50 +
   fixtures/contract tests actualizados.
4. Deploy: core primero, tienda después (como siempre).
