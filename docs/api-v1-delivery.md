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
3. **Consumidores** (futuro, proyecto tienda): guard `consumidores` ya
   provisto; el endpoint público de pedidos acepta opcionalmente su Bearer.

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
horarios/calendario y config de entrega.

### `GET /v1/tiendas/{slug}/catalogo?tipo=delivery|take_away`
Catálogo visible según RF-17 (activo + vendible + visible en tienda +
disponible para el tipo). Los **agotados vienen marcados** `"agotado": true,
"pedible": false` — se muestran pero la API bloquea pedirlos. Los precios son
FINALES (motor de precios del sistema: listas + promociones vigentes); los
grupos de opcionales vienen con min/max/obligatorio.

### `POST /v1/tiendas/{slug}/envios/cotizar`
```json
{ "latitud": -34.6037, "longitud": -58.3816 }
```
→ `{ alcance: "ok"|"fuera_de_alcance"|"desconocido", pedible, costo_envio,
distancia_km, zona, demora_estimada_min }`. Fuera de alcance **no es
pedible** por la API (el forzado es solo del panel).

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
  "cupon_codigo": "PROMO10"
}
```
→ items con promociones atribuidas, `subtotal`, `iva`, `descuento`,
`total_final`, `cupon`, `desglose_iva`. El costo de envío va aparte (endpoint
anterior) y lo suma el alta del pedido.

### `POST /v1/tiendas/{slug}/pedidos`
Alta de pedido (throttle 15/min). Mismo payload del carrito **+**:
```json
{
  "cliente": { "nombre": "Juan", "telefono": "11...", "email": "j@x.com" },
  "direccion": { "direccion": "Av. Siempreviva 742", "referencia": "3B",
                 "latitud": -34.60, "longitud": -58.38, "localidad_id": null },
  "observaciones": "sin cebolla",
  "datos_fiscales": { "cuit": "20-...-3" }
}
```
Reglas:
- Tienda cerrada (calendario/horarios) → 422.
- Con georreferenciación activa: coordenadas obligatorias, fuera de alcance → 422.
- Artículo agotado / no disponible para el tipo → 422 con el nombre.
- Según la config de la sucursal el pedido entra **"por aceptar"**
  (`por_aceptar: true`, sin número — el comercio lo confirma o rechaza) o
  **confirmado** directo (aceptación automática).
- Respuesta 201 con el pedido, incluido `token_seguimiento`.
- Consumidor logueado (Bearer del guard consumidores): el pedido guarda su
  identidad; el alta de cliente en el comercio depende de la política del
  comercio.

### `GET /v1/tiendas/{slug}/pedidos/{token_seguimiento}`
Seguimiento público (el token ULID es la credencial): estado + label, hora
pactada, repartidor en camino, timestamps y el canal de tiempo real.

### `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/cancelar`
Cancelación por el consumidor: permitida hasta `confirmado` (antes de que
entre en preparación). Después, solo el comercio.

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
(mismo circuito que el panel). La edición completa del carrito es del panel.

### `GET /v1/delivery/config` *(config:read)*
Config operativa de la sucursal (horarios, radio, costos, aceptación, etc.).

### `GET /v1/repartidores` *(config:read)*

## Tiempo real (Reverb)

- **Seguimiento público** (canal público, sin auth):
  `pedidos-delivery.seguimiento.{token_seguimiento}` — evento
  `SeguimientoActualizado` `{ estado, estado_label, repartidor,
  hora_pactada_at, at }` en cada cambio de estado de un pedido externo.
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
