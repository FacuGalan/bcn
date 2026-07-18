# API v1 — Pedidos Delivery

API REST del módulo de pedidos delivery/take-away (spec `pedidos-delivery`, RF-11).
Base: `https://{host}/api/v1`. Todas las respuestas son JSON; los errores tienen
forma uniforme:

```json
{ "error": { "code": "operacion_invalida", "message": "...", "details": null } }
```

| Código HTTP | `error.code` | Cuándo |
|---|---|---|
| 401 | `no_autenticado` / `no_autorizado` | Token faltante/inválido |
| 403 | `sin_permiso` | Token sin la ability requerida |
| 404 | `no_encontrado` / `tienda_no_encontrada` | Recurso o slug inexistente |
| 422 | `validacion` | Payload inválido (`details` trae los campos) |
| 422 | `operacion_invalida` | Regla de negocio (mensaje legible) |
| 429 | — | Throttle superado |
| 500 | `error_interno` | Error del servidor (sin detalle) |

## Audiencias

1. **Público por tienda** (sin auth, throttle 60/min): rutas bajo
   `/v1/tiendas/{slug}/...`. El `slug` identifica comercio+sucursal (la tienda
   es POR SUCURSAL).
2. **Integración** (Bearer token, throttle 120/min): token emitido por el
   comercio en *Configuración → Tokens de API* con **abilities**. La sucursal
   se indica con el header `X-Sucursal-Id` (default: la principal).
3. **Consumidores** (proyecto tienda, RF-T1..T3): cuenta GLOBAL
   cross-comercio con Bearer Sanctum propio (ver *Endpoints de consumidores*).
   El endpoint público de pedidos y `carrito/cotizar` aceptan opcionalmente
   ese Bearer (precios por cliente donde exista mapping). El token vive en la
   SESIÓN server-side de la tienda, nunca en el navegador del consumidor.
4. **Marketplace** (público, throttle 30/min): landing global de tiendas
   (`GET /v1/tiendas`, `GET /v1/rubros`), sin tenant.

### Abilities de los tokens de integración

| Ability | Da acceso a |
|---|---|
| `pedidos:read` | `GET /pedidos-delivery`, `GET /pedidos-delivery/{id}` |
| `pedidos:write` | `POST /pedidos-delivery`, `PATCH /pedidos-delivery/{id}` |
| `config:read` | `GET /delivery/config`, `GET /repartidores` |
| `catalogo:read` | (reservada para catálogo autenticado) |

## Endpoints públicos (por slug)

### `GET /v1/tiendas/{slug}`
Datos públicos de la tienda: nombre, ubicación, si está abierta ahora,
horarios/calendario, config de entrega, **contrato de promesa** y **formas de
pago declarables**:

```json
{
  "entrega": {
    "modo_promesa": "franjas|automatica|manual",
    "acepta_lo_antes_posible": true,
    "demora_base_min": 20,        // solo modo automatica (estimación "~X min")
    "demora_min_por_km": 5,       // solo modo automatica
    "usa_franjas": false          // true ⇒ consultar GET /franjas
  },
  "formas_pago": [
    { "id": 1, "nombre": "Efectivo", "codigo": "efectivo", "permite_vuelto": true,
      "ajuste_porcentaje": -10 }
  ]
}
```

`formas_pago` son las declarables **contra entrega/retiro** (el pago online
integrado es otro circuito, pendiente en el spec de integraciones);
`permite_vuelto: true` habilita el campo `paga_con` del alta de pedido.
`ajuste_porcentaje` es el descuento (negativo) o recargo (positivo) de esa FP
— mostrarlo junto a la opción ("Efectivo −10%"); el monto exacto lo calcula
`carrito/cotizar` con `forma_pago_id`.

**Analytics, tema y comportamiento** (aditivo 2026-07-17, RF-T7 + RF-T6):

```json
{
  "analytics": {
    "ga4_measurement_id": "G-XXXXXXXXXX",   // null ⇒ NO inyectar gtag
    "meta_pixel_id": "123456789012345"      // null ⇒ NO inyectar fbq
  },
  "tema": {
    "colores": { "primario": "#4f46e5", "acento": "#f59e0b",
                 "fondo": "#f9fafb", "superficie": "#ffffff",
                 "texto": "#111827" },
    "tipografia": { "fuente": "system" },   // system|inter|poppins|roboto|montserrat|lora (self-hosted en la tienda)
    "radios": "md",                          // none|sm|md|lg|full
    "densidad": "normal",                    // compacta|normal|amplia
    "portada": {                             // (aditivo 2026-07-18, RF-T13)
      "overlay": true,                       // false ⇒ portada cruda, sin fade del color primario
      "posicion": "center"                   // encuadre vertical: top|center|bottom (object-position)
    },
    "textos": {
      "slogan": "",                          // hero, bajo el nombre ('' ⇒ no se muestra)
      "descripcion": ""                      // sección propia de la home ('' ⇒ sin sección)
    },
    "redes": {
      "facebook": "",                        // URL del perfil ('' ⇒ sin botón en el hero)
      "instagram": ""
    },
    "catalogo": { "layout": "grilla" },      // grilla|lista (renglón-tarjeta)
    "destacados": {
      "modo": "banner",                      // banner|tarjeta_grande|ninguno
      "adorno": "ninguno"                    // glow|badge|ambos|ninguno (solo aplica a tarjeta_grande)
    },
    "promos": { "mostrar_home": false }      // true ⇒ mostrar aviso "Promociones de hoy" en la home
  },
  "comportamiento": {}                       // reservado (Principio 10); v1 sin seteos
}
```

Sub-objetos de RF-T13: los defaults replican el comportamiento previo al RF
(snapshot viejo sin las claves ⇒ la tienda usa estos defaults y se ve igual
que siempre — tolerancia a clave ausente en ambos lados).

`tema` es el resultado EFECTIVO (defaults del core + JSON configurado en el
panel): la tienda lo vuelca a sus design tokens sin defaults propios. Las
claves son contrato: agregar claves es aditivo; renombrar/quitar exige v2.

**Identidad visual** (aditivo 2026-07-17, RF-T11):

```json
{
  "logo_url": "https://core.example/storage/tiendas/1/uuid.webp",   // null ⇒ sin logo
  "portada_url": "https://core.example/storage/tiendas/1/uuid.webp" // null ⇒ sin portada (banner del header)
}
```

URLs ABSOLUTAS (host del core) porque la tienda corre en otro origen.
Imágenes re-encodeadas a WebP por el panel (logo ≤800px, portada ≤1600×900).

### `GET /v1/tiendas/{slug}/franjas?tipo=delivery|take_away`
Horarios de entrega/retiro de la JORNADA con lugar (modo `franjas`):
```json
{ "modo_promesa": "franjas", "acepta_lo_antes_posible": true,
  "franjas": [ { "hora": "2026-07-08T20:30:00-03:00", "label": "20:30" } ] }
```
Vacío si la sucursal no trabaja por franjas. El valor `hora` es el que se
manda en `entrega.franja` del alta (la API rechaza horarios inventados o
vencidos). Los cupos por franja llegan en Fase 8.

### `GET /v1/tiendas/{slug}/puntos` *(Bearer consumidor — RF-T8, Fase 3)*

Saldo y reglas del programa de puntos DEL comercio de la tienda para el
consumidor logueado:

```json
{ "data": { "activo": true, "saldo": 120, "saldo_en_pesos": 6000,
            "valor_punto_canje": 50, "minimo_canje": 10,
            "puede_canjear": true } }
```

Sin cliente materializado (mapping D11), programa inactivo (comercio o
sucursal) o cliente excluido → `activo: false` con saldo 0 (nunca un error).
La consulta NO crea el cliente. Saldo por sucursal solo si el programa está
en modo `por_sucursal`.

### `GET /v1/tiendas/{slug}/catalogo?tipo=delivery|take_away`
Catálogo visible según RF-17 (activo + vendible + visible en tienda +
disponible para el tipo). Los **agotados vienen marcados** `"agotado": true,
"pedible": false` — se muestran pero la API bloquea pedirlos. Los precios son
FINALES (motor de precios del sistema: listas + promociones vigentes).

`imagen_url` (de artículos y categorías) es SIEMPRE una URL absoluta con el
host de la API (fix 2026-07-17): la tienda corre en otro origen y una ruta
relativa se rompería contra su propio host. `null` si no hay imagen.

Los grupos de opcionales son los ASIGNADOS al artículo en la sucursal de la
tienda (paridad con el panel), con el precio de la asignación (override por
artículo, no el del catálogo global). Grupos sin opciones vivas no se
publican. `disponible: false` = mostrar deshabilitada (agotada):

```json
"opcionales": [
  { "grupo_id": 1, "nombre": "Extras", "tipo": "seleccionable|cuantitativo",
    "obligatorio": false, "min": 0, "max": 3,
    "opciones": [
      { "opcional_id": 4, "nombre": "Extra cheddar", "precio_extra": 250,
        "disponible": true }
    ] }
]
```

El `opcional_id` es el que se manda en `items.*.opcionales` de
`carrito/cotizar` y del alta. La cotización/alta **rechaza (422) opcionales
no asignados al artículo en esa sucursal o no disponibles**, y suma al total
el `precio_extra` de la asignación — el mismo cálculo del panel (el precio
del ítem que ve el motor incluye los opcionales; las promos aplican sobre
ese precio, igual que en el mostrador).

**Cache HTTP (RF-T5)**: la respuesta trae `ETag` y `Cache-Control:
public, max-age=60`. Revalidar con `If-None-Match` → `304` sin payload si el
catálogo no cambió. El armado además se cachea SERVER-SIDE 60s (los cambios
de catálogo/precios pueden demorar hasta un minuto en verse en la tienda).
`ETag` está en `exposed_headers` de CORS para consumo browser-side.

### `POST /v1/tiendas/{slug}/envios/cotizar`
```json
{ "latitud": -34.6037, "longitud": -58.3816, "hora_pactada": "2026-07-10 22:30:00" }
```
→ `{ alcance: "ok"|"fuera_de_alcance"|"desconocido", pedible, costo_envio,
distancia_km, zona, demora_estimada_min }`. Fuera de alcance **no es
pedible** por la API (el forzado es solo del panel).

`hora_pactada` es opcional: evalúa las franjas de costo de la zona para ese
momento (p. ej. envío más caro de noche); sin ella se cotiza para ahora.
Las zonas son polígonos dibujados en la config: si la sucursal tiene zonas
activas, ellas definen el alcance (fuera de todas ⇒ `fuera_de_alcance`);
sin zonas rige el radio general con costo por km.

### `POST /v1/tiendas/{slug}/carrito/cotizar`
Cotización server-side del carrito completo — el contrato que la tienda
muestra en el checkout. **Nunca calcular precios localmente.**
```json
{
  "tipo": "delivery",
  "items": [
    { "articulo_id": 12, "cantidad": 2,
      "opcionales": [{ "opcional_id": 5, "cantidad": 1 }] }
  ],
  "cupon_codigo": "PROMO10",
  "forma_pago_id": 1
}
```
→ items con promociones atribuidas, `subtotal`, `iva`, `descuento`,
`total_final`, `cupon`, `forma_pago`, `total_a_pagar`, `desglose_iva`. El
costo de envío va aparte (endpoint anterior) y lo suma el alta del pedido.

`forma_pago_id` (opcional): la FP que el consumidor piensa declarar. Participa
del precio con los **mismos cálculos del panel**: promociones y listas de
precios condicionadas por forma de pago, cupones restringidos a FP, y el
descuento/recargo de la FP (`forma_pago.ajuste_monto`). `total_final` sigue
siendo el total de bienes; **`total_a_pagar` = total_final + ajuste** es lo que
el consumidor paga (sin envío). Recomendado: re-cotizar al cambiar la FP en el
checkout. Un cupón restringido a formas de pago exige `forma_pago_id` (422 si
falta o no coincide).

`usar_puntos` (opcional bool — RF-T9, Fase 3, requiere Bearer de consumidor
con cliente): canjea el **MÁXIMO** de puntos posible como PAGO (no toca
precios ni `total_final`): `monto = min(saldo × valor_punto_canje,
total_a_pagar)`, `usados = ceil(monto / valor_punto_canje)`. Con programa
activo (canjee o no) la respuesta suma el bloque `puntos` y el
`total_a_pagar` queda NETO del canje:

```json
"puntos": { "usados": 40, "monto": 2000, "saldo": 120, "saldo_restante": 80,
            "puede_canjear": true, "a_ganar": 5 },
"total_a_pagar": 4551.43
```

`a_ganar` es el ESTIMADO de acumulación del pedido (fórmula real del panel:
monto pagado sin puntos × multiplicador de la FP ÷ monto_por_punto, con el
redondeo de la config; sin envío). El crédito verdadero lo hace la conversión
a venta.

### `POST /v1/tiendas/{slug}/pedidos`
Alta de pedido (throttle 15/min). Mismo payload del carrito **+**:
```json
{
  "cliente": { "nombre": "Juan", "telefono": "11...", "email": "j@x.com" },
  "direccion": { "direccion": "Av. Siempreviva 742", "referencia": "3B",
                 "latitud": -34.60, "longitud": -58.38, "localidad_id": null },
  "entrega": { "lo_antes_posible": true },
  "pago": { "forma_pago_id": 1, "paga_con": 20000 },
  "observaciones": "sin cebolla",
  "datos_fiscales": { "cuit": "20-...-3" }
}
```
`entrega` (opcional — "¿cuándo lo querés?"):
- `franja` (solo modo `franjas`): un `hora` de `GET /franjas`; inventada o
  vencida → 422. Sin franja: default "lo antes posible" si la config lo
  ofrece; si no, 422 pidiendo elegir.
- `lo_antes_posible: true`: solo si `acepta_lo_antes_posible`; si no → 422.
- Modo `automatica`: la hora la calcula el sistema por distancia. Modo
  `manual` + aceptación manual: la pacta el comercio al aceptar.

`pago` (opcional — "¿cómo pagás?"): declara el pago **contra entrega/retiro**
como planificado (no cobra nada): `forma_pago_id` de `GET /tiendas/{slug}` y,
si `permite_vuelto`, `paga_con` (efectivo con el que paga → el repartidor sale
con el vuelto). `paga_con` menor al total → 422.

La FP declarada **impacta el precio del pedido** con los mismos cálculos que
`carrito/cotizar` (promos/listas por FP + ajuste por FP): el `total_final` del
pedido queda con el ajuste aplicado y el pago planificado se descompone como
en el panel (`monto_base + monto_ajuste = monto_final`). El **envío queda
fuera** de la base del ajuste (es un valor fijo): efectivo −10% sobre $1000 de
productos + $500 de envío = $1400. Checkout con la misma FP y pedido muestran
el MISMO total.

Reglas:
- Tienda cerrada (calendario/horarios) → 422.
- Con georreferenciación activa: coordenadas obligatorias, fuera de alcance → 422.
- Artículo agotado / no disponible para el tipo → 422 con el nombre.
- Según la config de la sucursal el pedido entra **"por aceptar"**
  (`por_aceptar: true`, sin número — el comercio lo confirma o rechaza) o
  **confirmado** directo (aceptación automática).
- Respuesta 201 con el pedido, incluido `token_seguimiento`.
- `usar_puntos: true` (RF-T9, con Bearer de consumidor con cliente): el core
  recalcula el canje MÁXIMO con saldo FRESCO y registra el pago con puntos
  como planificado (FP interna "Canje Puntos") + la FP declarada por el
  RESTO. El descuento de saldo real (MovimientoPunto) ocurre al convertir a
  venta — si el saldo se gastó en el medio, esa parte del canje falla en la
  conversión y lo resuelve el comercio (ventana asumida).
- Consumidor logueado (Bearer del guard consumidores): el pedido guarda su
  identidad; el alta de cliente en el comercio depende de la política del
  comercio. El `carrito/cotizar` con ese mismo Bearer cotiza con su cliente
  (precios especiales) — checkout y pedido muestran el MISMO total.

### `GET /v1/tiendas/{slug}/pedidos/{token_seguimiento}`
Seguimiento público (el token ULID es la credencial): estado + label, hora
pactada / `lo_antes_posible`, `demorado` (por aceptar con el timeout del
comercio vencido), repartidor en camino, timestamps y el canal de tiempo real.

**Máquina de estados del seguimiento** (render por `estado`; el `estado_label`
ya viene resuelto por tipo):

| `estado` | delivery | take_away |
|---|---|---|
| `borrador` + `por_aceptar` | esperando confirmación del comercio | ídem |
| `confirmado` | confirmado | confirmado |
| `en_preparacion` | en preparación | en preparación |
| `listo` | listo para enviar (**salteable** si la sucursal no usa este paso) | listo (salteable) |
| `en_camino` | en camino 🛵 (`repartidor_en_camino`) | **"Para retirar"** — el cliente pasa a buscarlo (`repartidor_en_camino` siempre null) |
| `entregado` | entregado | retirado/entregado |
| `cancelado` | con `cancelado_motivo` | ídem |

El estado interno `facturado` (convertido en venta) **nunca se expone**: el
GET lo devuelve como `entregado` y el canal de tiempo real no lo emite.
Cualquier estado puede saltearse (p. ej. aceptación automática con comanda
directa pasa confirmado→en_preparacion al toque): renderizar por progreso
acumulado, no por secuencia estricta.

La respuesta incluye `items[]` (agregado ADITIVO 2026-07-17, RF-T3
"re-pedir"): los renglones pedibles del pedido, EXCLUYENDO el
renglón-concepto del costo de envío y los conceptos sin artículo:

```json
"items": [
  { "articulo_id": 12, "nombre": "Hamburguesa clásica", "cantidad": 2,
    "opcionales": [
      { "opcional_id": 5, "nombre": "Cheddar extra", "cantidad": 1 }
    ] }
]
```

Sirve para mostrar qué se pidió en el seguimiento y para que la tienda arme
"re-pedir": rearma el carrito con `articulo_id`/`opcional_id`/`cantidad` y
**re-cotiza** (precios de hoy, nunca históricos).

### `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/cancelar`
Cancelación por el consumidor: permitida hasta `confirmado` (antes de que
entre en preparación). Después, solo el comercio.

## Endpoints de consumidores (RF-T1..T3, cuenta global de la tienda)

Base `/v1/consumidores`. Sin tenant (la cuenta es cross-comercio). Decisión
RF-T1: **se puede pedir sin verificar el email**; la verificación desbloquea
el historial. Throttle agresivo por endpoint (registro 5/min, login 10/min,
emails 3/min).

### Auth

- `POST /registro` — `{nombre, email, password (min 8), telefono?}` → `201`
  `{data: {token, consumidor}}` + email de verificación. El token sirve YA.
- `POST /login` — `{email, password}` → `{data: {token, consumidor}}`.
  Credenciales malas → `422 validacion`.
- `POST /logout` *(Bearer)* — revoca el token actual.
- `GET /me` *(Bearer)* — perfil: `{id, nombre, email, telefono,
  email_verificado}`.
- `POST /verificar` — `{token}` (del link del email, la tienda lo reenvía
  desde su página `/verificar`) → marca verificado (idempotente). Token
  inválido/vencido → `422 operacion_invalida`.
- `POST /reenviar-verificacion` *(Bearer)* — reenvía si falta verificar.
- `POST /recuperar` — `{email}` → siempre `200` (no revela existencia);
  si existe manda el link de reset (vence en 60 min, single-use).
- `POST /restablecer` — `{token, password}` → cambia el password y **revoca
  todos los tokens** (la tienda debe re-loguear).

Un Bearer de INTEGRACIÓN (comercio) sobre estos endpoints → `403 sin_permiso`.

### `GET|POST|PATCH|DELETE /v1/consumidores/direcciones[/{id}]` *(Bearer)*

CRUD de direcciones guardadas (máx. 10): `{alias?, direccion, referencia?,
localidad_id?, latitud?, longitud?, es_default?}`. La primera queda default;
marcar otra la desplaza; borrar la default promueve a la más nueva. El
checkout las precarga — el pedido sigue copiando snapshot.

### `GET /v1/consumidores/pedidos?page=&per_page=` *(Bearer, email verificado)*

Historial CROSS-comercio (fan-out a los tenants con tienda, merge por fecha
desc): `{data: [{fecha, numero, tipo, estado, por_aceptar, total_final,
token_seguimiento, tienda: {slug, habilitada, nombre}}], meta: {page,
per_page, total, has_more}}`. `estado` usa la misma verdad pública del
seguimiento (`facturado` = `entregado`). Sin verificar → `403 sin_permiso`.
"Re-pedir": la tienda arma el carrito desde `GET /pedidos/{token}` y
**re-cotiza** (precios de hoy, no históricos).

## Endpoints de marketplace (RF-T4, público)

### `GET /v1/tiendas?lat=&lng=&rubro_id=`

Tiendas habilitadas para la landing global. Con `lat/lng` excluye las que no
llegan (zonas dibujadas o radio; misma semántica de `envios/cotizar`) y
ordena por distancia; una tienda sin georreferenciar devuelve
`alcance: "desconocido"` (no se inventa alcance, D5). Sin coordenadas lista
todas en orden alfabético. Card: `{slug, nombre, comercio, rubro: {id,
nombre}, logo_url, direccion, localidad, latitud, longitud, abierta_ahora,
takeaway_habilitado, alcance, distancia_km}`. Los datos por tienda se
cachean ~5 min. `logo_url` (RF-T11): prima el logo propio de la tienda
(config del panel); fallback al logo de pantalla-cliente/empresa de la
sucursal. Por el cache, un cambio de logo puede demorar ~5 min en verse.

### `GET /v1/rubros`

Catálogo global de rubros activos: `[{id, nombre, slug}]` (cache 1 h).

## Endpoints de integración (Bearer + `X-Sucursal-Id`)

### `GET /v1/pedidos-delivery` *(pedidos:read)*
Listado paginado. Filtros query: `estado`, `tipo`, `origen`, `desde`, `hasta`,
`per_page` (max 100). Respuesta `{ data: [...], meta: {...} }`.

### `GET /v1/pedidos-delivery/{id}` *(pedidos:read)*

### `POST /v1/pedidos-delivery` *(pedidos:write)*
Alta con el mismo payload del endpoint público (origen `api` +
`origen_referencia` del integrador). Respeta la aceptación configurada.

### `PATCH /v1/pedidos-delivery/{id}` *(pedidos:write)*
Modificaciones operativas puntuales:
```json
{ "estado": "en_preparacion|listo|en_camino|entregado",
  "repartidor_id": 3, "observaciones": "...", "observacion_estado": "..." }
```
`en_camino` con repartidor asignado crea la salida de reparto implícita
(mismo circuito que el panel); para **take-away** significa "listo para
retirar" (sin salida). `entregado` sobre un pedido que está EN una salida de
reparto → 422: la entrega de un pedido en la calle se registra con la VUELTA
del repartidor desde el panel (ahí se cargan los cobros contra entrega).
La edición completa del carrito es del panel.

### `GET /v1/delivery/config` *(config:read)*
Config operativa de la sucursal (horarios, radio, costos, aceptación, etc.).

### `GET /v1/repartidores` *(config:read)*

## Tiempo real (Reverb)

- **Seguimiento público** (canal público, sin auth):
  `pedidos-delivery.seguimiento.{token_seguimiento}` — evento
  `SeguimientoActualizado` `{ estado, estado_label, repartidor,
  hora_pactada_at, lo_antes_posible, at }` en cada cambio de estado de un
  pedido externo (también cuando el comercio edita la hora pactada).
- **Panel/integraciones** (canal privado del comercio):
  `comercios.{comercioId}.pedidos-delivery` — evento `PedidoDeliveryBroadcast`
  `{ pedidoId, sucursalId, tipo, at }` con tipos `creado`, `estado_cambiado`,
  `pago_cambiado`, `cancelado`, `convertido_venta`.

## Alta de una tienda (registro global)

La tabla `config.tiendas` mapea `slug → comercio+sucursal` y habilita las
rutas públicas. v1 se administra por consola/soporte:

```php
Tienda::create(['comercio_id' => 1, 'sucursal_id' => 2,
                'slug' => 'mi-hamburgueseria', 'habilitada' => true]);
```
