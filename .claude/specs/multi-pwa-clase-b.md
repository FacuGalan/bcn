# Multi-PWA Clase B — Pantallas auxiliares remotas - Especificación

## Estado: EN PROGRESO (Fase 1 ✅ — Fases 2-5 pendientes)

> Spec creado el 2026-06-26 vía flujo SDD. Define el framework **Clase B** (pantallas en dispositivos remotos sin sesión) + dos casos de uso: **monitor llamador de pedidos** y **consultor de precios**. Pendiente: aprobación del usuario sobre los PUNTOS DE DECISIÓN antes de `/sdd-apply`.

---

## Contexto y Motivación

El refactor `pwa-scope-multiapp` (PR #125) dejó montado el framework multi-PWA y planteó dos **clases** de pantallas auxiliares:

- **Clase A — co-ubicada**: corre en el MISMO navegador/perfil que el POS, comparte sesión, se comunica por `BroadcastChannel` (same-origin). Ej: `pantalla-cliente` (ya implementada).
- **Clase B — remota** (este spec): corre en un **dispositivo distinto, en otra ubicación física, sin sesión** (no hay login, no comparte navegador con el POS). Necesita: (a) identificar el comercio/sucursal sin sesión, (b) tiempo real por red (Reverb, no BroadcastChannel).

Casos de uso de Clase B que pide el negocio:
1. **Monitor llamador de pedidos**: una TV/monitor en el salón que muestra los pedidos **listos para retirar** ("Pedido 45 — LISTO"), tipo patio de comidas. Reacciona en vivo cuando un pedido pasa a `LISTO`.
2. **Consultor de precios**: una pantalla/tablet pública donde el cliente busca o escanea un artículo y ve su **precio de venta** (como las góndolas de supermercado).

Ambas son **públicas y de solo lectura**, instalables como PWA independientes, y deben funcionar en un dispositivo que nunca se loguea.

---

## Principios de Diseño

1. **Sin sesión, identificación por token vía índice global**: cada sucursal expone un **token público fijo** en la URL (`/llamador/{token}`, `/precios/{token}`). Como `sucursales` es una tabla **tenant** (prefijo `{NNNNNN}_`), NO se puede resolver el token sin saber el comercio primero (problema huevo-gallina). Por eso el token se resuelve contra un **índice global** en la conexión `config` (`pantalla_publica_tokens`: `token` → `comercio_id` + `sucursal_id`), **mismo patrón que `MercadoPagoCollectorIndex`** para webhooks. El middleware busca el comercio en el índice, configura el tenant con `TenantService::usarComercioParaProceso()` y recién ahí carga la `Sucursal`. NO hay `Auth::user()`.
2. **Mínima exposición**: las pantallas Clase B son **solo lectura** y exponen el **mínimo dato necesario**. El llamador solo ve número de pedido + estado; el consultor solo ve nombre + precio de venta (nunca costos, márgenes, stock ni datos internos).
3. **Tiempo real por canal acotado al token**: Clase B NO puede suscribirse al canal privado del comercio (requiere sesión). Usa un **canal de broadcast propio, acotado por token**, con payload reducido.
4. **Reutiliza el framework PWA existente**: cada pantalla = `manifest-{nombre}.json` con scope propio + íconos dedicados + SW global (igual que `pantalla-cliente`). Fuera del scope `/app`.
5. **Personalización por sucursal**: reutiliza el patrón `sucursales.config_*` (json) para título, logo y colores de cada pantalla.
6. **Multi-tenant correcto**: token y configuración viven en la conexión correcta; el broadcast respeta el aislamiento por comercio.
7. **Token por sucursal, multi-dispositivo**: el token identifica la SUCURSAL, no el dispositivo. La misma URL puede abrirse en **N dispositivos a la vez** (ej. 4 consultores de precios en distintos puntos del super, o varios monitores llamadores en el mostrador). El canal público de Reverb soporta múltiples suscriptores nativamente; los endpoints son idempotentes/solo-lectura.

---

## Requisitos Funcionales

### RF-01: Token público por sucursal + índice global
- Cada sucursal tiene un `token_publico` **único global, aleatorio y no adivinable** (~32 chars URL-safe, `Str::random(40)` o similar).
- Hay **dos credenciales** con roles distintos:
  - **Token largo** (`token_publico`, ~40 chars, no adivinable): credencial de máquina. Nombra el **canal de Reverb** y autoriza los endpoints de datos. **Nunca se tipea**: se guarda en `localStorage` del dispositivo tras vincular.
  - **Código corto** (`codigo_corto`, 5-6 chars, alfabeto sin ambigüedades — Crockford base32, sin `0/O/1/I/L`): credencial humana **solo para vincular** una TV que no puede escanear QR. Se tipea **una sola vez** en una URL corta.
- El token se materializa en DOS lugares, siempre en sincronía:
  - **Índice global** `pantalla_publica_tokens` (conexión `config`, sin prefijo): `token` (unique) + `codigo_corto` (unique) → `comercio_id` + `sucursal_id`. Es la **fuente de verdad para resolver sin sesión** (patrón `MercadoPagoCollectorIndex`).
  - Columna `sucursales.token_publico` (tenant): copia para la UI/config y para mostrar URLs/QR.
- Se genera automáticamente al provisionar un comercio (escribe token + código corto en ambos lugares) y al correr la migración (backfill de sucursales existentes → puebla columna tenant + índice global).
- Se puede **regenerar manualmente** desde Configuración (rotación si se filtra). Regenerar **actualiza token + código corto en el índice global y la columna tenant** e invalida las URLs/dispositivos viejos (el token guardado en localStorage deja de resolver → 404 → vuelve a vincular).

### RF-02: Resolución de tenant por token (middleware)
- Middleware `ResolvePublicTokenMiddleware`: toma `{token}` de la URL, lo busca en el **índice global `pantalla_publica_tokens`** (conexión `config`, sin tenant configurado aún), obtiene `comercio_id` + `sucursal_id`, configura el comercio con `usarComercioParaProceso()` y recién entonces carga la `Sucursal` (ya en conexión tenant) dejándola disponible en el request. Si el token no existe en el índice → **404 genérico** (sin distinguir "no existe" de "inválido", para no habilitar enumeración).
- Las rutas Clase B viven FUERA de `/app` (sin `auth`, sin `tenant` de sesión).

### RF-02b: Vinculación del dispositivo (PWA pairing) — URL corta tipeable
- **Restricción de hardware**: una TV no tiene cámara para escanear QR y se navega con control remoto → **la URL a tipear debe ser lo más corta posible**. Por eso el código corto, no el token largo.
- El token **no se genera por dispositivo**: es fijo por sucursal (server-side). El dispositivo lo **adquiere una vez y lo persiste** en `localStorage`.
- **Dos caminos de vinculación** según el dispositivo:
  - **Tablet/celular (puede escanear)**: escanea el **QR** de Configuración (apunta a `/{pantalla}/{token}` con el token largo) → guarda el token en `localStorage` → vinculado.
  - **TV (tipear)**: tipea una **URL corta** del estilo `dominio/ll/{codigo}` (llamador) o `dominio/pr/{codigo}` (consultor) con el **código corto** de la sucursal. El backend **canjea el código corto por el token largo**, lo guarda en `localStorage` → vinculado. El prefijo (`ll`/`pr`) selecciona la pantalla; un mismo código corto sirve para ambas con prefijo distinto.
- **`start_url` genérico**: el manifest apunta a `/llamador` / `/precios` (sin token ni código) → un solo manifest estático sirve a todos (igual que `manifest-pantalla-cliente.json`).
- Al **relanzar la PWA** desde el ícono abre la ruta genérica; el JS **lee el token de `localStorage`** y entra directo a la sucursal vinculada. Sin re-tipear ni re-escanear.
- Si el dispositivo nunca se vinculó (localStorage vacío) → muestra **pantalla de "vincular dispositivo"** con la instrucción y el campo para tipear el código / o escanear QR.
- Si el token fue **regenerado** o es inválido → el endpoint responde 404 → la pantalla **descarta el token guardado** y vuelve a la pantalla de vinculación.
- **Seguridad del código corto**: el canje código→token está **rate-limited** (anti fuerza bruta) y el código es **rotable**. El código corto solo habilita ver una pantalla pública de solo-lectura y baja sensibilidad (números + nombre / precios públicos); el **canal de Reverb y los endpoints de datos siguen usando el token largo inadivinable**, nunca el código corto.
- La ruta genérica sirve un **shell neutro** (sin datos sensibles); la personalización (título/logo/colores) y los datos se cargan client-side vía los **endpoints acotados al token** una vez resuelto desde `localStorage`.

### RF-03: Monitor llamador de pedidos (`/llamador/{token}`) — DOS COLUMNAS
- Pantalla pública full-screen, dividida en dos columnas:
  - **Izquierda — "En preparación"**: pedidos en estado **`EN_PREPARACION`** de la sucursal. El cliente ve que su pedido se está preparando (anticipación). Muestra número (+ alias corto si existe).
  - **Derecha — "Listo / Retirar"** (el "llamador"): pedidos en estado **`ESTADO_LISTO`** (`'listo'`), destacados (más grande, color distinto). Muestra **número + nombre** + dispara **sonido/chime** al aparecer un pedido nuevo en esta columna para llamar la atención del cliente.
- Estados reales (constantes de `PedidoMostrador`): `ESTADO_EN_PREPARACION = 'en_preparacion'`, `ESTADO_LISTO = 'listo'`. Usar las constantes, no strings sueltos.
- Reacciona en vivo: un pedido se mueve de la columna izquierda a la derecha cuando pasa a `LISTO`; desaparece de ambas al entregarse/cancelarse.
- NO muestra items, montos ni datos sensibles. Solo número + nombre.
- Al cargar (cold start) trae el snapshot actual de pedidos `EN_PREPARACION` + `LISTO` vía endpoint público.
- Funciona en **varios monitores simultáneos** (misma URL/token).
- Instalable como PWA (`manifest-llamador.json`, scope `/llamador`).
- **Nombre (alias)**: se muestra **solo el nombre** del cliente — el `nombre_cliente_temporal` si es cliente temporal, o el **primer nombre** del cliente asociado si está registrado. **Nunca apellido** ni datos de contacto. Se acepta exponer el nombre en la pantalla pública porque es justamente para llamar al cliente.

### RF-04: Broadcast del estado de pedido al canal público
- Cuando un pedido **entra o sale** de `EN_PREPARACION` o `LISTO`, se emite un evento a un **canal público acotado al token de la sucursal** con payload mínimo `{numero, alias, estado, at}`.
- El canal privado del comercio (`comercios.{id}.pedidos-mostrador`) se mantiene intacto para el POS; el público es adicional.
- Cualquier cantidad de monitores suscriptos reciben el mismo evento.

### RF-05: Consultor de precios (`/precios/{token}`)
- Pantalla pública donde el cliente busca un artículo (por nombre o código/código de barras) y ve su **precio de venta de lista base**.
- Resuelve la **lista de precios base de la sucursal** (sin cliente) vía `PrecioService`. Solo artículos **activos en la sucursal** (`articulos_sucursales`).
- **Promos como info, sin calcular**: además del precio base, muestra el **listado de promociones vigentes en las que participa** el artículo (nombre/descripción de cada promo, ej. "2x1 los martes", "10% OFF"), **SIN** calcular ni mostrar el precio promocional. Es información para el cliente, no un cálculo.
- NO expone costo, margen, stock ni listas internas. Resultado: nombre + precio base (+ unidad si aplica) + nombres de promos vigentes.
- Endpoint público `GET /api/precios/{token}/buscar?q=` con **rate limiting**. Soporta múltiples consultores simultáneos (misma URL/token).
- Instalable como PWA (`manifest-consultor-precios.json`, scope `/precios`).

### RF-06: Personalización por sucursal
- Cada pantalla Clase B toma título, logo y colores de una config por sucursal (json), con defaults sensatos. Reutiliza el patrón de `config_pantalla_cliente`.

### RF-07: Configuración y descubrimiento de URLs
- Desde Configuración (sucursal/integraciones), el operador ve, por cada pantalla Clase B de la sucursal:
  - **URL larga + QR** (para tablets/celulares que escanean).
  - **URL corta + código corto** (para tipear en una TV, ej. `dominio/ll/4F2K`), bien destacado.
  - Botón **copiar** (cada URL) y botón **regenerar token** (con confirmación: aclara que desvincula los dispositivos actuales).

### RF-08: Seguridad y solo-lectura
- Todo Clase B es de **solo lectura**. Ni el token ni el código corto autorizan ninguna escritura.
- Payload de broadcast mínimo; endpoints (precios + canje de código) con **rate limit**; token largo no adivinable; **404 genérico** sin enumeración.
- Garantías del canal público de Reverb: ver subsección **"Seguridad del canal público"** en Servicios (solo-suscripción, whisper deshabilitado, app secret interno, sin auth endpoint nuevo, Reverb expuesto solo por WS/TLS).

---

## Modelo de Datos

### Tabla nueva (config)

#### `pantalla_publica_tokens` (conexión `config`, sin prefijo) — Índice global
Fuente de verdad para resolver el tenant sin sesión (patrón `MercadoPagoCollectorIndex`). Es **global** porque el middleware la consulta ANTES de saber el comercio (no puede leer una tabla tenant con prefijo todavía).
- `id`
- `token` (string(40), **unique**) — token largo, nombra el canal Reverb y autoriza endpoints.
- `codigo_corto` (string(8), **unique**) — código corto humano para vincular TVs (canje → token).
- `comercio_id` (unsigned, índice) — FK lógica a `config.comercios`.
- `sucursal_id` (unsigned, índice) — id de la sucursal en su tenant.
- timestamps.
- Único registro por sucursal (unique compuesto `comercio_id` + `sucursal_id`).

> El canje del `codigo_corto` y la resolución del `token` se hacen contra esta tabla en conexión `config`. Rate-limit en el endpoint de canje.

### Tablas modificadas

#### `sucursales` (tenant) — Cambios
- Agregar `token_publico` (string(40), nullable→unique, default null) AFTER `config_pantalla_cliente`. Índice único (copia para UI/config; la resolución sin sesión usa el índice global). Backfill aleatorio en migración.
- Agregar `config_llamador` (json, nullable) — personalización del monitor llamador (título, logo, colores, sonido on/off).
- Agregar `config_consultor_precios` (json, nullable) — personalización del consultor.
  - **PUNTO DE DECISIÓN A** ✅ RESUELTO: columnas separadas `config_llamador`/`config_consultor_precios` por claridad y defaults independientes (modelo `Sucursal::CONFIG_*_DEFAULTS`).

> El token y las configs de personalización viven en `sucursales` (patrón `config_pantalla_cliente`); el **mapeo global token/código → comercio+sucursal** vive en `pantalla_publica_tokens` (config). Regenerar `database/sql/tenant_tables.sql` tras la migración tenant.

---

## Pantallas UI

### Pantalla 1: Monitor llamador (`/llamador` genérico → resuelve token de localStorage; `/llamador/{token}` para vincular por QR)
**Tipo**: vista Blade pública (NO Livewire full-page con sesión) + JS que escucha Reverb por el canal del token.
**Trait**: ninguno (sin sesión).
- **Dos columnas**: izquierda "En preparación" (pedidos `EN_PREPARACION`), derecha "Listo / Retirar" (pedidos `LISTO`, destacados). Alto contraste, legible de lejos.
- Suscripción Reverb al canal público del token; el pedido migra de izquierda a derecha en vivo; **sonido/chime** al entrar a la columna "Listo".
- **Audio unlock (simple)**: por la política de autoplay, el chime no suena hasta que hubo una interacción. Solución mínima: una capa inicial "Tocá para activar" que desbloquea el `AudioContext` y desaparece. Si resulta molesto en la práctica, se revisa después — no sobre-ingenierizar.
- Snapshot inicial (ambas columnas) vía endpoint público.
- Pantalla de **vinculación** si no hay token en localStorage (campo para tipear el código corto / instrucción de QR).
- PWA: `manifest-llamador.json` (`start_url` `/llamador`), íconos `pwa-icons/llamador-*.png`.

### Pantalla 2: Consultor de precios (`/precios` genérico; `/precios/{token}` para vincular por QR)
**Tipo**: vista Blade pública + JS (búsqueda contra el endpoint público).
**Trait**: ninguno.
- Input de búsqueda (nombre/código), soporte lector de código de barras (input + Enter).
- Muestra nombre + precio de venta grande + nombres de promos vigentes. Estados: vacío, buscando, sin resultados.
- Pantalla de **vinculación** si no hay token en localStorage.
- PWA: `manifest-consultor-precios.json` (`start_url` `/precios`), íconos `pwa-icons/consultor-precios-*.png`.

### Pantalla 3 (config): tarjeta de pantallas Clase B en Configuración
**Componente**: extensión del editor de sucursal / integraciones (Livewire existente, con sesión).
- Por cada pantalla muestra: **URL larga + QR** (escanear) y **URL corta + código** (tipear en TV), botón copiar, botón **Regenerar token** (con confirmación que avisa que desvincula los dispositivos actuales), y edición de la personalización por pantalla.

---

## Servicios

### `PantallaPublicaService` (nuevo) — `app/Services/PantallaPublicaService.php`
- `resolverPorToken(string $token): ?array` — busca en `pantalla_publica_tokens` (config), configura tenant con `usarComercioParaProceso()`, devuelve la `Sucursal` + comercio (usado por el middleware).
- `canjearCodigoCorto(string $codigo): ?string` — busca el `codigo_corto` en el índice global y devuelve el `token` largo para guardar en localStorage (usado por el endpoint de vinculación de TV; rate-limited).
- `regenerarToken(Sucursal $sucursal): array` — genera token + código corto nuevos, los persiste en el índice global Y en `sucursales.token_publico` (escritura; se llama desde config con sesión). Devuelve ambos.
- `pedidosParaLlamador(Sucursal $sucursal): array` — snapshot de pedidos `ESTADO_EN_PREPARACION` + `ESTADO_LISTO` (payload mínimo `{numero, nombre, estado}`) para el cold start del llamador. Solo nombre (temporal o primer nombre del cliente).
- `buscarPreciosPublico(Sucursal $sucursal, string $q): array` — busca artículos activos en la sucursal + resuelve **precio de lista base** vía `PrecioService` + adjunta **nombres de promociones vigentes**. Payload mínimo (nombre, precio, unidad, promos[]). Rate limit aplicado en la ruta.

### `PrecioService` + modelos existentes — reutilizados (no reimplementar)
- **Precio base**: `PrecioService::obtenerPrecioBase($articuloId, $sucursalId)` (`PrecioService.php:88`) ya resuelve la lista aplicable internamente → **no** hace falta llamar `obtenerListaAplicable()` por separado. Devuelve `['precio', 'precio_base', 'origen', ...]`.
- **Promos vigentes del artículo (sin calcular)**: usar el método existente `Articulo::obtenerPromocionesActivas($sucursalId)` (`Articulo.php:400`), que ya devuelve las promos activas/vigentes de la sucursal que incluyen el artículo. Mapear a `->pluck('nombre')` para exponer solo nombres.
- **Artículo activo en sucursal**: filtrar por la relación pivote `articulos_sucursales.activo = true` (`Articulo::estaDisponibleEnSucursal()` / `wherePivot('activo', true)`).
- **Búsqueda por código**: `scopePorCodigo()` / `scopePorCodigoBarras()` (`Articulo.php:165-173`) + búsqueda por nombre.

### Broadcast (RF-04) — RESUELTO: canal público por token (B1)
- Nuevo evento **`PedidoLlamadorPublicoBroadcast`** (`ShouldBroadcastNow`) en **canal público** `llamador.{token}` con payload mínimo `{numero, nombre, estado, at}`. Se emite cuando un pedido entra/sale de `ESTADO_EN_PREPARACION` o `ESTADO_LISTO`, desde `PedidoMostradorService::cambiarEstado` (junto al broadcast privado existente `PedidoMostradorBroadcast`, que se mantiene intacto para el POS).
- El cliente (JS público) se suscribe con Echo en **canal público** (`Echo.channel('llamador.'+token)`, sin `private-`, sin pegar a `/broadcasting/auth`). El **token largo** de localStorage es el secreto del canal.
- **Multi-dispositivo**: el canal público admite N suscriptores → varios monitores reciben el mismo evento. Sin estado por dispositivo.

#### Seguridad del canal público (RF-08) — garantías explícitas
- **Solo-suscripción**: los canales públicos de Reverb solo permiten *recibir*; el cliente no puede publicar → imposible inyectar un "pedido listo" falso. **Client events / whisper DESHABILITADOS** (default; no habilitarlos).
- **El secreto del server nunca sale**: el navegador usa solo la **app key pública** de Reverb para suscribirse (protocolo Pusher). El **app secret** y la API HTTP de publicación (server→Reverb) quedan internos, nunca expuestos.
- **Sin auth endpoint nuevo**: al ser canal público no se toca `/broadcasting/auth` → el canal privado del comercio (POS) sigue igual de protegido. No se afloja nada existente.
- **Payload mínimo**: `{numero, nombre, estado, at}`, sin costos, IDs internos ni datos que habiliten enumeración.
- **Exposición de Reverb**: si los dispositivos no están en la LAN, exponer solo el puerto WS con TLS (`wss`), nunca la API HTTP de publicación de Reverb.
- **Peor caso de un atacante con el token**: ver número + nombre de pedidos listos (datos públicos por diseño). No hay vector de escritura ni de ejecución. Se mitiga rotando el token.

---

## Migraciones Necesarias

1. `create_pantalla_publica_tokens_table` (**config**, tabla compartida sin prefijo) — índice global `token` (unique) + `codigo_corto` (unique) + `comercio_id` + `sucursal_id`. NO itera comercios para crear la tabla (es config).
2. `add_token_publico_y_configs_clase_b_to_sucursales` (**tenant**) — agregar `token_publico` (unique) + `config_llamador` + `config_consultor_precios`. **Backfill**: itera todos los comercios; por cada sucursal existente genera token + código corto, los escribe en la columna tenant Y en `pantalla_publica_tokens` (config). Try/catch por comercio. Regenerar `tenant_tables.sql`.

Actualizar `ProvisionComercioCommand` → al crear cada sucursal, generar token + código corto y escribir en ambos lugares (columna tenant + índice global config).

---

## Traducciones

Claves nuevas (es/en/pt), entre otras:
| Clave (es) | en | pt |
|------------|----|----|
| Pedido listo | Order ready | Pedido pronto |
| Consultor de precios | Price checker | Consultor de preços |
| Buscar artículo o escanear código | Search item or scan code | Buscar item ou escanear código |
| Sin resultados | No results | Sem resultados |
| Regenerar token | Regenerate token | Regenerar token |
| URL de pantalla pública | Public screen URL | URL da tela pública |
| Vincular dispositivo | Pair device | Vincular dispositivo |
| Ingresá el código | Enter the code | Digite o código |
| Tocá para activar el sonido | Tap to enable sound | Toque para ativar o som |
| Código de vinculación | Pairing code | Código de vinculação |

---

## Criterios de Aceptación

- [ ] Cada sucursal tiene un `token_publico` único + `codigo_corto` único en el índice global `pantalla_publica_tokens` (config); provisión y migración los generan; backfill OK (token en columna tenant + índice global).
- [ ] El middleware resuelve la sucursal correcta **desde el índice global, sin sesión**; token inexistente → 404 genérico.
- [ ] **Vinculación corta**: tipear `dominio/ll/{codigo}` (o `pr`) en un dispositivo nuevo lo vincula (canjea código → token, persiste en localStorage); relanzar la PWA entra directo sin re-tipear; canje rate-limited.
- [ ] El llamador muestra las dos columnas (`EN_PREPARACION` izquierda, `LISTO` derecha) con el snapshot actual al cargar y reacciona en vivo (migra de columna al pasar a LISTO con sonido, desaparece al entregarse) vía Reverb.
- [ ] El llamador NO expone items ni montos; solo número + **nombre** (temporal o primer nombre, nunca apellido).
- [ ] **Multi-dispositivo**: abrir la misma sucursal en 2+ dispositivos funciona en simultáneo (todos reciben los eventos / pueden consultar).
- [ ] El consultor devuelve nombre + precio de lista base correcto solo de artículos activos en la sucursal, + nombres de promos vigentes (sin calcular precio promocional); NO expone costo/stock; tiene rate limit.
- [ ] Regenerar el token + código invalida las URLs viejas y desvincula los dispositivos (vuelven a pantalla de vinculación).
- [ ] **Seguridad del canal público**: whisper deshabilitado, app secret no expuesto, no se agrega ni se debilita ningún endpoint de auth; un atacante con token solo ve datos públicos de solo-lectura.
- [ ] Ambas pantallas son instalables como PWA con `start_url` genérico, scope/manifest/íconos propios.
- [ ] El canal privado del comercio (POS) sigue funcionando sin cambios.
- [ ] Tests: middleware (resolución por índice global) + canje de código, PantallaPublicaService (`pedidosParaLlamador` + `buscarPreciosPublico`), broadcast del evento público, smoke de las vistas. Suite en verde + Pint.

---

## Plan de Implementación

### Fase 1: Token + índice global + middleware + infraestructura [COMPLETO]
1. Migración config `create_pantalla_publica_tokens_table` (índice global token + código corto).
2. Migración tenant `token_publico` + configs en `sucursales` (backfill a columna tenant + índice global). Regenerar `tenant_tables.sql`.
3. `ProvisionComercioCommand` genera token + código corto en ambos lugares.
4. `PantallaPublicaService::resolverPorToken` + `canjearCodigoCorto` + `regenerarToken`.
5. Middleware `ResolvePublicTokenMiddleware` (resuelve por índice global → `usarComercioParaProceso`). Grupo de rutas públicas Clase B fuera de `/app` + endpoint de canje de código corto (rate-limited).
6. **Spike temprano**: probar un canal público de Reverb end-to-end (Echo en página sin sesión) antes de construir vistas — es infra nueva (hoy `pantalla-cliente` usa BroadcastChannel, no Reverb).

### Fase 2: Monitor llamador [PENDIENTE]
1. Evento público `PedidoLlamadorPublicoBroadcast` (`ShouldBroadcastNow`) + emitirlo al entrar/salir de `EN_PREPARACION`/`LISTO` (en `PedidoMostradorService::cambiarEstado`, junto al broadcast privado existente).
2. Canal público `llamador.{token}` (sin auth).
3. Endpoint snapshot `pedidosParaLlamador` + vista `/llamador` (genérico + `/llamador/{token}` para QR) + JS Reverb + pantalla de vinculación + audio unlock simple.
4. PWA: `manifest-llamador.json` (`start_url` genérico), íconos, registro en SW.

### Fase 3: Consultor de precios [PENDIENTE]
1. `PantallaPublicaService::buscarPreciosPublico` (reusa `obtenerPrecioBase` + `obtenerPromocionesActivas`) + endpoint `/api/precios/{token}/buscar` con rate limit.
2. Vista `/precios` (genérico + `/precios/{token}` para QR) + JS de búsqueda (incl. lector de código) + pantalla de vinculación.
3. PWA: `manifest-consultor-precios.json` (`start_url` genérico), íconos, SW.

### Fase 4: Configuración + personalización [PENDIENTE]
1. Tarjeta en Configuración: URLs + QR + copiar + regenerar token + edición de personalización por pantalla.
2. Defaults de personalización en el modelo `Sucursal`.

### Fase 5: Tests + docs [PENDIENTE]
1. Tests (middleware, service, broadcast, smoke vistas). 
2. Docs (`manual-usuario.md`, `ai-knowledge-base.md`) al crear el PR.

---

## Notas y Decisiones

- 2026-06-26: Spec creado vía SDD. Decisiones del usuario: **ambos casos de uso en un spec** + **token fijo por sucursal**.
- 2026-06-26: PUNTOS DE DECISIÓN **RESUELTOS** por el usuario:
  - **A** ✅ Columnas separadas `config_llamador` / `config_consultor_precios`.
  - **B** ✅ Canal **público por token** (B1). El token identifica la sucursal; **multi-dispositivo** (varios monitores/consultores con la misma URL). Datos no críticos.
  - **C** ✅ Consultor: muestra **precio de lista base** + **listado de promos vigentes** en las que participa (solo nombres, **sin calcular** el precio promocional).
  - **D** ✅ Llamador de **dos columnas**: "En preparación" (`EN_PREPARACION`) a la izquierda + "Listo/Retirar" (`LISTO`) a la derecha con **número + nombre + sonido**.
- 2026-06-26 (revisión): ajustes tras contrastar con el código real:
  - **Índice global `pantalla_publica_tokens` (config)**: resuelve el problema huevo-gallina (no se puede leer `sucursales` tenant sin saber el comercio). Patrón `MercadoPagoCollectorIndex`.
  - **Código corto + URL corta tipeable** para vincular TVs sin cámara/QR; el token largo (inadivinable) queda solo para el canal Reverb y los endpoints. Vinculación persistida en localStorage; `start_url` genérico.
  - **Alias = solo nombre** (temporal o primer nombre; nunca apellido).
  - **Seguridad del canal público** documentada (solo-suscripción, whisper off, app secret interno, sin nuevo auth endpoint, Reverb por WS/TLS).
  - **Reuso de código existente**: `obtenerPrecioBase`, `Articulo::obtenerPromocionesActivas`, scopes de código, constantes `ESTADO_*`.
  - **Audio unlock** simple (capa "tocá para activar"), sin sobre-ingeniería.
  - Spike temprano de Reverb público (infra nueva).
- Sin decisiones abiertas. Spec listo para aprobación final → `/sdd-apply`.
