# Pedidos por Mostrador - Especificación

## Estado: EN PROGRESO — PR1 (Refactor)

> Spec inicial creado el 2026-05-04. Aprobado por el usuario el 2026-05-04.
> En progreso: PR1 (refactor de NuevaVenta a sub-traits) en branch `refactor/nueva-venta-traits`.
> Diseñado para ser la **primera implementación** de un patrón reutilizable que a futuro alimentará Pedidos por Delivery, Comandas de Salón (con mesas) y Ventas Mayoristas. Cada uno tendrá tablas propias, pero todos compartirán los **traits Livewire** y **services agnósticos** que se extraen de Nueva Venta en la Fase 0 (PR1).
>
> **🔴 Garantía explícita del PR1**: la operativa actual de Nueva Venta debe seguir funcionando **exactamente igual** después del refactor. Cero cambios visibles para el usuario, cero cambios de comportamiento, cero cambios en BD. Ver detalles y checklist en "Plan de Implementación → PR1 → REGLA DE ORO".

---

## Contexto y Motivación

Hoy la única operativa de venta es `App\Livewire\Ventas\NuevaVenta` (componente monolítico de ~7954 líneas) que materializa una venta de inmediato (crea `Venta` + `VentaDetalle` + `VentaPago`, descuenta stock, emite comprobante fiscal y cierra caja en un solo flujo).

Esto no cubre el caso operativo de gastronomía/mostrador donde:

1. El cliente pide en mostrador, el cajero toma el pedido y manda a producción.
2. Cocina prepara mientras tanto, pasando estados (en preparación → listo → entregado).
3. El cobro puede ocurrir al alta del pedido o al entregar (o quedar pendiente).
4. La facturación fiscal puede ocurrir al cobrar al alta, al entregar, o cuando el cajero decida.
5. La numeración del pedido se usa para llamar al cliente ("Pedido 47, listo!"), no para auditar contabilidad.

Además, este módulo es el **prototipo** del patrón que después aplicaremos a **Delivery**, **Salón con mesas** y **Mayoristas**, cada uno con su propia tabla pero compartiendo toda la lógica de carrito, cálculo, descuentos, pagos y cupones.

---

## Principios de Diseño

0. **🔴 PRIORIDAD ABSOLUTA: NO ROMPER NUEVA VENTA** — El PR1 (refactor) es un cambio mecánico de organización del código del componente `NuevaVenta`, sin modificar comportamiento alguno. Después del merge, Nueva Venta debe funcionar **exactamente** como antes: mismos métodos, mismos eventos, mismos cálculos, misma vista, misma performance, mismos resultados en pantalla. La estrategia de validación está detallada en el "Plan de Implementación → PR1 → REGLA DE ORO" y tiene checklist obligatorio antes de mergear. Si algún paso del refactor introduce una diferencia de comportamiento, el commit que la introdujo se revierte y se replanifica. Las "mejoras tentadoras" detectadas durante el refactor van a un TODO y se hacen en otro PR.

1. **Reutilización por composición, no herencia** — Toda la lógica compartida con Nueva Venta vive en sub-traits Livewire (`WithCarritoItems`, `WithBusquedaArticulos`, `WithDescuentos`, `WithCupones`, `WithPuntos`, `WithPagosDesglose`, `WithCalculoVenta`, `WithOpcionales`, `WithBusquedaClientes`). Cada componente compone los que necesita. Esto permite que `NuevaVenta`, `NuevoPedidoMostrador`, y futuros `PedidoDelivery`/`ComandaSalon`/`VentaMayorista` reutilicen el mismo código sin acoplamiento.

2. **Tablas propias por tipo de documento** — `pedidos_mostrador*` son tablas dedicadas (no polimórficas). A futuro habrá `pedidos_delivery*`, `salon_comandas*`, etc., cada uno con su esquema. Esto evita la complejidad de columnas nullable o JSON metadata, y permite reportes y consultas tipadas.

3. **Pedido como pre-venta materializada** — El pedido NO es una venta. Es un documento operativo con su propio ciclo de vida (estados de cocina + estados de pago) que **eventualmente** se convierte en `Venta` invocando `VentaService::crearVenta()`. La conversión es el punto único donde se emite comprobante fiscal y se cierra contablemente.

4. **Stock consumido al confirmar** — Para gastronomía la materia prima se consume cuando cocina empieza a preparar. El descuento de stock (incluyendo recetas) se hace en el `confirmar pedido`. Cancelar el pedido genera contraasiento. La conversión a venta NO toca stock (ya está descontado).

5. **Caja siempre cuadra** — Cualquier cobro al alta del pedido genera `MovimientoCaja` con `referencia_tipo = 'pedido_mostrador'`. Al convertir el pedido en venta, esos movimientos se **re-asocian** a la venta resultante (`referencia_tipo = 'venta'`, `referencia_id = $venta->id`) en la misma transacción.

6. **Multi-tenant estricto** — Todas las tablas nuevas viven en `pymes_tenant`, todos los modelos declaran `protected $connection = 'pymes_tenant'`, todas las transacciones usan `DB::connection('pymes_tenant')->transaction()`. Iteración por comercio en migraciones tenant.

7. **Sucursal-aware obligatorio** — Pedidos pertenecen a una sucursal. Componentes usan trait `SucursalAware`. Cambiar de sucursal recarga la lista de pedidos.

8. **Append-only para pagos y movimientos de caja** — Anular un pago genera contraasiento (movimiento inverso), nunca DELETE. Mismo patrón de `MovimientoCuentaCorriente`/`MovimientoStock`.

9. **Preparado para tiempo real, no acoplado a infra** — Disparamos eventos Laravel estándar (`PedidoCreado`, `PedidoEstadoCambiado`, etc.). UI interna usa `wire:poll` cada 5-10s. Cuando agreguemos Reverb/Pusher, los listeners se suman sin cambiar el código que dispara.

10. **API REST disponible desde día uno** — Endpoints REST con Sanctum auth para que el futuro tótem/app externa/módulo de cocina puedan crear y consultar pedidos sin tocar Livewire. La UI interna y la API consumen el mismo `PedidoMostradorService`.

---

## Requisitos Funcionales

### RF-01: Lista de pedidos activos
- Pantalla principal del módulo: lista/grilla de pedidos de la sucursal activa con filtro por estado (todos / borrador / confirmado / en_preparacion / listo / entregado / cancelado / facturado), por fecha (hoy por defecto) y por identificador/cliente.
- Cada card de pedido muestra: número, identificador (si tiene), cliente (si tiene), total, estado del pedido (badge color), estado de pago (badge color), tiempo desde creación.
- Botones por card: Editar, Cobrar (si pago < total), Cambiar estado (siguiente lógico), Convertir en venta, Cancelar, Imprimir comanda, Imprimir precuenta.
- Polling automático cada 8s para reflejar cambios desde otras terminales.

### RF-02: Crear nuevo pedido (modal pantalla completa)
- Botón "Nuevo Pedido" en la lista abre modal de pantalla completa con la misma funcionalidad que Nueva Venta (búsqueda artículos, opcionales, cliente, descuentos, cupones, puntos, lista de precios, ajustes manuales).
- Layout: 3 columnas → izquierda (búsqueda + carrito), centro (panel táctil de categorías → artículos), derecha (resumen + cliente + identificador + acciones).
- Campo `identificador` libre (ej: "Juan", "Mesa 5", "Pedido 12") para llamar al cliente o ubicar el pedido. Opcional.
- Botón "Confirmar pedido" (sin cobrar) — guarda como `confirmado`, descuenta stock, dispara evento, imprime comanda si configurado.
- Botón "Confirmar y cobrar" — guarda + abre modal de pago (mismo `WithPagosDesglose` de Nueva Venta) + registra pagos + estado pago = `pagado`.
- Botón "Cancelar" — descarta sin guardar.
- Botón "Guardar como borrador" — guarda con estado `borrador`, no descuenta stock, no imprime comanda.

### RF-03: Editar pedido existente
- Click en pedido de la lista abre el mismo modal full-screen poblado con sus datos.
- Solo se pueden editar items y descuentos si el pedido está en estado `borrador` o `confirmado`.
- Si el pedido está `en_preparacion`, `listo` o `entregado`: solo lectura. El botón Editar dice "Ver detalle" y los items son no editables. Sí se permiten cambios de pago (cobrar pendiente, agregar pagos).
- Cambios en items que afecten stock generan ajustes incrementales en `MovimientoStock` (delta positivo o negativo).

### RF-04: Estados del pedido
- Transiciones permitidas:
  - `borrador` → `confirmado` (al guardar definitivo)
  - `confirmado` → `en_preparacion` → `listo` → `entregado`
  - cualquiera → `cancelado` (con motivo)
  - `entregado` → `facturado` (al convertir en venta)
- Cada transición registra `usuario_id`, timestamp y opcional `observacion`.
- El siguiente módulo de Cocina podrá manipular estados `en_preparacion` ↔ `listo` ↔ `entregado` (vía API y/o componente Livewire propio que se agregará después).
- Configuración por sucursal: `conversion_automatica_al_entregar` (boolean). Si está activa, al pasar a `entregado` se ejecuta automáticamente la conversión a venta.

### RF-05: Estados de pago (independientes del estado del pedido)
- `pendiente` — sin pagos asociados.
- `parcial` — suma de pagos < total.
- `pagado` — suma de pagos = total.
- Campo cache `estado_pago` se recalcula al agregar/anular pagos.
- Puede combinarse libre con cualquier estado de pedido (ej: pedido `en_preparacion` con pago `pagado`, o `entregado` con pago `pendiente`).

### RF-06: Pagos al alta o posteriores
- Modal de pago reutiliza `WithPagosDesglose` (el mismo de Nueva Venta): múltiples formas de pago, cuotas, ajustes por forma de pago, vuelto, moneda extranjera.
- Cada pago crea un `PedidoMostradorPago` y un `MovimientoCaja` con `referencia_tipo = 'pedido_mostrador'` y `referencia_id = $pedido->id`.
- Anular un pago genera contraasiento en MovimientoCaja (movimiento inverso) y marca `PedidoMostradorPago.estado = anulado`.
- NO se emiten comprobantes fiscales en esta etapa. Esos viven en la conversión a venta.

### RF-07: Conversión a venta
- Disparada manualmente desde el panel ("Facturar y cerrar"), o automáticamente al pasar a `entregado` si la sucursal lo tiene configurado.
- Llama a `PedidoMostradorService::convertirEnVenta($pedido)`:
  1. Abre transacción tenant.
  2. Construye `$datosVenta` y `$detalles` desde el pedido.
  3. Invoca `VentaService::crearVenta($datosVenta, $detalles)` con flag `stock_ya_descontado = true` (nuevo flag, ver RF-09).
  4. Migra registros de `PedidoMostradorPago.activo` a `VentaPago` correspondientes (incluyendo `monto_base`, `ajuste_porcentaje`, `cuotas`, etc.).
  5. Re-asocia los `MovimientoCaja` correspondientes: `referencia_tipo = 'venta'`, `referencia_id = $venta->id`.
  6. Si la sucursal tiene `facturacion_fiscal_automatica` o el usuario marcó factura: invoca `ComprobanteFiscalService` para emitir comprobante.
  7. Marca pedido como `facturado`, guarda `venta_id`.
  8. Si quedan pagos pendientes en CC, registra `MovimientoCuentaCorriente`.
  9. Acumula puntos del cliente.
  10. Commit.
- El pedido sigue existiendo (no se borra). Se mantiene como histórico con link a la venta.

### RF-08: Cancelación del pedido
- Botón "Cancelar pedido" en cualquier estado anterior a `facturado`.
- Modal pide motivo (obligatorio).
- Si el pedido tenía pagos: se anulan generando contraasiento en MovimientoCaja.
- Si el pedido había descontado stock (estado >= `confirmado`): se generan movimientos de stock inversos para revertir.
- Estado pasa a `cancelado`. Se guarda `cancelado_at`, `cancelado_por_usuario_id`, `motivo_cancelacion`.

### RF-09: Stock con descuento al confirmar
- Cuando un pedido pasa a `confirmado` (o nace directo confirmado), se ejecuta el mismo flujo de descuento de stock que `VentaService` (incluye descuento por receta de ingredientes si el artículo tiene receta en esa sucursal).
- Cada movimiento se crea con `referencia_tipo = 'pedido_mostrador'`, `referencia_id = $pedido->id`.
- Cuando el pedido se convierte en venta, **NO** se descuenta de nuevo (flag `stock_ya_descontado` en `VentaService::crearVenta`). En cambio, los registros de stock se re-asocian a la venta (igual que MovimientoCaja).
- Si se cancela el pedido, contraasiento (movimiento inverso por cada movimiento original).

### RF-10: Numeración por sucursal con reset manual
- Numeración correlativa por sucursal: `1, 2, 3...` (campo `numero` en `pedidos_mostrador`).
- El número se asigna en transacción al confirmar el pedido (no en borrador).
- Botón en configuración de sucursal: "Resetear numeración de pedidos" (con confirmación). Útil cuando los números crecen mucho. Permiso `pedidos_mostrador.resetear_numeracion`.
- Para evitar colisiones con números viejos: el reset NO toca pedidos existentes; simplemente vuelve el contador a 0 y los siguientes pedidos arrancan en 1. Si hay duplicados con pedidos antiguos, se aclara en la UI con la fecha.

### RF-11: Panel táctil de categorías y artículos (split persistente)
Layout dentro del modal full-screen, columna central, dos zonas siempre visibles en simultáneo:

**Zona superior — Barra de categorías** (sticky, altura fija ~80px en desktop / ~64px en tablet):
- Fila horizontal con **todas las categorías activas** de la sucursal.
- Scroll horizontal con scrollbar fino + soporte touch (swipe), wheel del mouse mapeado a scroll horizontal, y dos flechas laterales (◀ ▶) que aparecen solo cuando hay overflow.
- Cada chip de categoría: ancho mínimo ~110px, alto ~64px, fondo con el `color` de la categoría (con suficiente contraste para texto blanco), icono opcional (`heroicon`) y nombre. Si la categoría tiene imagen (RF-16), va de fondo con overlay oscuro 30%.
- Categoría seleccionada se destaca con `ring-2 ring-white` + sombra + ligera elevación.
- Por defecto al abrir el modal queda seleccionada la **primera categoría activa por orden** (campo `orden` o `nombre` ASC).
- Click/tap en categoría → cambia el contenido de la zona inferior sin recargar (Alpine local, sin viaje a Livewire).

**Zona inferior — Grilla de artículos** (ocupa el resto vertical, scroll interno propio):
- Grilla responsive: 5 columnas en desktop (≥1280px), 4 en lg (1024px), 3 en md (768px), 2 en mobile.
- Cada card de artículo: alto ~140px, ancho según grilla, mostrar imagen (RF-16) si existe ocupando 60% del alto con overlay del nombre + precio abajo. Si no hay imagen, fondo del color de la categoría + nombre + precio centrados.
- Si el artículo tiene grupos opcionales obligatorios, mostrar badge ⚙ en esquina superior derecha del card.
- Si el artículo está sin stock o inactivo en la sucursal, se opaca al 40% y queda no clickeable.
- Scroll vertical interno cuando los artículos no entran.
- Click/tap en artículo: agrega 1 unidad al carrito (`agregarArticulo()`). Si tiene grupos opcionales, abre wizard de opcionales (mismo de Nueva Venta).
- Tap largo (>500ms) o doble click: abre popover para ingresar cantidad antes de agregar.

**Comportamiento general**:
- El buscador (columna izquierda) y el panel táctil son complementarios: el cajero puede usar el que prefiera, indistintamente, y el carrito recibe los items igual.
- Si la sucursal no tiene categorías o no tiene artículos asignados, mostrar empty state con CTA: "Configurá tus categorías" / "Asigná artículos a categorías" (link a la pantalla correspondiente).
- Toda la interacción de la zona barra+grilla vive en Alpine.js (sin `wire:click` para no rebotar al server). Solo el `agregarArticulo` final invoca a Livewire.

**Justificación del layout vs alternativas (drill-in)**:
El layout split persistente reduce clicks (no hay "volver a categorías"), mantiene el contexto visual del menú entero y es lo que usan POS gastronómicos comerciales (TouchBistro, Square, Toast). Un drill-in obliga al cajero a hacer 2 taps por cada cambio de categoría, lo que es lento en pico de operación.

### RF-12: Comanda automática + reimpresión manual
- Al confirmar un pedido, si la sucursal tiene `imprime_comanda_automatico = true` y existe una asignación en `impresora_tipo_documento` para `tipo_documento = 'comanda'`, se dispara la impresión via QZ Tray (servicio `ImpresionService`) sin intervención del cajero.
- Botón "Imprimir comanda" en el detalle del pedido para reimprimir manualmente en cualquier momento.
- Botón "Imprimir precuenta" para imprimir resumen sin valor fiscal (similar a comanda pero con totales).
- Plantilla de comanda nueva: `app/Services/Impresion/PlantillasComanda.php` (ESC/POS y HTML).
- Si la sucursal `usa_beepers`, el número de beeper se imprime en grande en el encabezado de la comanda y de la precuenta, junto al número de pedido.

### RF-13: Eventos Laravel
Eventos en `app/Events/PedidoMostrador/`:
- `PedidoCreado` — payload: pedido_id, sucursal_id, usuario_id.
- `PedidoEstadoCambiado` — payload: pedido_id, estado_anterior, estado_nuevo, usuario_id.
- `PedidoEstadoPagoCambiado` — payload: pedido_id, estado_pago_anterior, estado_pago_nuevo.
- `PedidoConvertidoEnVenta` — payload: pedido_id, venta_id, usuario_id.
- `PedidoCancelado` — payload: pedido_id, motivo, usuario_id.

Listeners no se implementan en esta versión. Quedan como puntos de extensión documentados.

### RF-14: API REST scaffolding (Sanctum)
- Auth: `auth:sanctum` middleware.
- Rutas en `routes/api.php` bajo prefijo `/api/v1/pedidos-mostrador`.
- Endpoints v1:
  - `POST /` — crear pedido (mismo payload que el componente Livewire)
  - `GET /` — listar (filtros: estado, sucursal_id, fecha_desde, fecha_hasta)
  - `GET /{id}` — detalle
  - `PATCH /{id}/estado` — cambiar estado (validación de transiciones permitidas)
  - `PATCH /{id}/cancelar` — cancelar (motivo obligatorio)
  - `POST /{id}/pagos` — registrar pago
  - `POST /{id}/convertir-venta` — disparar conversión
- Resource Controllers: `app/Http/Controllers/Api/V1/PedidoMostradorController.php`
- Form Requests con validación.
- API Resources para serialización (`PedidoMostradorResource`, etc.).
- Permisos Spatie aplicados (la API usa los mismos permisos que la UI).
- Rate limiting estándar de Laravel (60/min por usuario).

### RF-15: Beepers llamadores (configurable por sucursal)
- Configuración por sucursal: flag `usa_beepers` (boolean, default false). Editable desde la pantalla de configuración de sucursal junto con las otras opciones de pedidos.
- Si `usa_beepers = true`:
  - El form de Nuevo/Editar Pedido muestra un input "Número de beeper" en la columna derecha, al lado del identificador.
  - El input es **obligatorio para confirmar** el pedido (no es obligatorio para guardar como borrador).
  - Validación: alfanumérico libre 1-20 caracteres (algunos beepers usan prefijos como "B12" o "VIP-3").
  - Si el cajero intenta confirmar sin completar el beeper, se bloquea con toast de error.
- Si `usa_beepers = false`: el input no se muestra, el campo queda NULL en BD.
- Visibilidad del beeper:
  - Lista de pedidos: badge prominente con el número de beeper en cada card/fila.
  - Comanda impresa: número de beeper grande en el encabezado (junto al número de pedido).
  - Precuenta: número de beeper visible.
  - API REST: campo `numero_beeper` siempre presente en los resources (NULL si no aplica).
- A futuro, el módulo Monitor de clientes podrá iluminar el número del beeper cuando el pedido pase a `listo`.
- **No aplica al PR1** (refactor). Se implementa en PR2.

### RF-16: Imagen de artículo (sub-feature opcional, dentro del PR2)
- Agregar campo `imagen` (varchar 255 nullable) a `articulos`.
- Storage en `storage/app/public/articulos/{comercio_id}/{articulo_id}.{ext}`.
- UI de carga en el form de edición de artículo (drag & drop + redimensionado a 400x400).
- Panel táctil muestra la imagen si existe; fallback a color+nombre si no.
- Si esta sub-feature complica el PR2, se puede diferir a un PR3. El panel táctil queda preparado para mostrar imagen condicionalmente desde el inicio.

### RF-17: Cliente del pedido — oficial, temporal, o alta inline
El pedido **siempre necesita identificar a quién atiende** (para llamar al cliente cuando esté listo). El cajero tiene 3 caminos para resolverlo:

**A) Cliente oficial existente** — si ya está dado de alta en el sistema:
- Combo de búsqueda de cliente (mismo del trait `WithBusquedaClientes` que se extrae en PR1) busca por nombre, CUIT, email o teléfono.
- Al seleccionarlo, se asocia `cliente_id` al pedido. Los campos `nombre_cliente_temporal` / `telefono_cliente_temporal` quedan NULL.
- El nombre y teléfono se toman de `clientes.nombre` y `clientes.telefono` para mostrar/imprimir/llamar.

**B) Cliente temporal sin alta** — caso típico de mostrador rápido:
- Inputs de **nombre** y **teléfono** en la columna derecha del pedido (visibles siempre, independientes del combo de cliente oficial).
- El cajero puede ingresar **solo el teléfono** y el sistema busca automáticamente (debounce 500ms, normalizando: quita espacios/guiones/paréntesis del input y de `clientes.telefono` antes de comparar) si existe un cliente con ese teléfono.
  - **Match exacto único**: pre-asocia el cliente automáticamente, autocompleta el nombre, muestra toast "Cliente reconocido: {nombre}". El cajero puede desvincular si no era ese.
  - **Múltiples matches**: dropdown con los matches para que el cajero elija o ignore.
  - **Sin match**: el cajero completa el nombre. Aparece botón **"Dar de alta como cliente"** (opcional).
- Si no se asocia cliente oficial: ambos campos `nombre_cliente_temporal` y `telefono_cliente_temporal` se guardan en el pedido. Son **obligatorios para confirmar** (no para guardar borrador).
- Validaciones:
  - `nombre_cliente_temporal`: 2-150 chars.
  - `telefono_cliente_temporal`: 6-30 chars, acepta números, espacios, guiones, paréntesis y `+`. Se normaliza a solo dígitos al guardar para consultas posteriores (campo guardado tal cual lo ingresó el cajero, normalización solo para búsqueda).

**C) Alta de cliente desde el pedido** — si el cajero quiere registrarlo como cliente oficial al toque:
- Botón "Dar de alta como cliente" abre el modal de alta rápida (mismo del trait `WithBusquedaClientes`) pre-poblado con nombre y teléfono.
- Al guardar, el cliente recién creado queda asociado al pedido (`cliente_id` se setea, `nombre_cliente_temporal` y `telefono_cliente_temporal` se limpian).
- Permiso requerido: `clientes.crear`.

**Visibilidad del cliente en el resto del flujo**:
- Lista de pedidos: muestra nombre del cliente (oficial o temporal) y teléfono como texto secundario.
- Comanda y precuenta impresas: nombre + teléfono visibles en encabezado.
- API REST: cada pedido devuelve `cliente: { id, nombre, telefono, es_temporal: bool }` resumiendo de uno u otro lado.
- Conversión a venta: si el pedido tenía cliente temporal sin alta, la `Venta` resultante queda con `cliente_id = NULL` (consumidor final). Los datos temporales se preservan en el pedido como histórico.

**No aplica al PR1** (refactor). Se implementa en PR2.

---

## Modelo de Datos

### Tablas nuevas (todas tenant)

#### `pedidos_mostrador`

Espejo de `ventas` con los siguientes ajustes: agregamos campos de estado de pedido, identificador, timestamps de cada estado, y `venta_id` para link tras conversión.

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK auto | — | |
| `numero` | int unsigned | NULL | Numeración por sucursal. NULL en borrador. |
| `identificador` | varchar(100) | NULL | Texto libre: "Juan", "Mesa 5", etc. |
| `numero_beeper` | varchar(20) | NULL | Número de beeper si la sucursal usa beepers (RF-15). |
| `sucursal_id` | bigint unsigned | — | FK `sucursales.id` |
| `cliente_id` | bigint unsigned | NULL | FK `clientes.id`. NULL si el pedido se carga con datos temporales sin alta (ver `nombre_cliente_temporal` / `telefono_cliente_temporal`). |
| `nombre_cliente_temporal` | varchar(150) | NULL | Nombre del cliente cuando NO se asocia un cliente oficial. Obligatorio al confirmar si `cliente_id IS NULL`. |
| `telefono_cliente_temporal` | varchar(30) | NULL | Teléfono del cliente cuando NO se asocia uno oficial. Obligatorio al confirmar si `cliente_id IS NULL`. |
| `caja_id` | bigint unsigned | NULL | FK `cajas.id` (NULL en borrador) |
| `canal_venta_id` | bigint unsigned | NULL | FK `canales_venta.id` (default mostrador) |
| `forma_venta_id` | bigint unsigned | NULL | FK `formas_venta.id` |
| `lista_precio_id` | bigint unsigned | NULL | FK `listas_precios.id` |
| `usuario_id` | bigint unsigned | — | FK `config.users.id` (alta) |
| `fecha` | timestamp | now | |
| `estado_pedido` | enum | 'borrador' | borrador / confirmado / en_preparacion / listo / entregado / facturado / cancelado |
| `estado_pago` | enum | 'pendiente' | pendiente / parcial / pagado (cache) |
| `subtotal` | decimal(12,2) | 0.00 | |
| `iva` | decimal(12,2) | 0.00 | |
| `descuento` | decimal(12,2) | 0.00 | |
| `total` | decimal(12,2) | 0.00 | |
| `ajuste_forma_pago` | decimal(12,2) | 0.00 | |
| `total_final` | decimal(12,2) | 0.00 | |
| `descuento_general_tipo` | enum | NULL | porcentaje / monto_fijo |
| `descuento_general_valor` | decimal(12,2) | NULL | |
| `descuento_general_monto` | decimal(12,2) | 0.00 | |
| `cupon_id` | bigint unsigned | NULL | FK `cupones.id` |
| `monto_cupon` | decimal(12,2) | 0.00 | |
| `puntos_ganados` | int unsigned | 0 | |
| `puntos_usados` | int unsigned | 0 | |
| `observaciones` | text | NULL | |
| `motivo_cancelacion` | varchar(500) | NULL | |
| `confirmado_at` | timestamp | NULL | |
| `en_preparacion_at` | timestamp | NULL | |
| `listo_at` | timestamp | NULL | |
| `entregado_at` | timestamp | NULL | |
| `cancelado_at` | timestamp | NULL | |
| `cancelado_por_usuario_id` | bigint unsigned | NULL | |
| `venta_id` | bigint unsigned | NULL | FK `ventas.id` (al convertir) |
| `convertido_at` | timestamp | NULL | |
| `created_at` / `updated_at` / `deleted_at` | timestamp | | soft deletes |

Índices: `numero`, `sucursal_id`, `estado_pedido`, `estado_pago`, `fecha`, `cliente_id`, `caja_id`, `venta_id`.

FK: `sucursal_id` → `sucursales`, `cliente_id` → `clientes`, `caja_id` → `cajas`, `venta_id` → `ventas` (ON DELETE SET NULL).

#### `pedidos_mostrador_detalle`

Espejo de `ventas_detalle`. Mismos campos cambiando `venta_id` por `pedido_mostrador_id`. Soporta concepto libre, opcionales, ajustes manuales, descuentos por promoción/cupón/lista, pago con puntos.

| Campo | Tipo | Default | Notas |
|-------|------|---------|-------|
| `id` | bigint PK | | |
| `pedido_mostrador_id` | bigint unsigned | | FK con ON DELETE CASCADE |
| (resto idéntico a `ventas_detalle` de columna `articulo_id` en adelante) | | | |

#### `pedido_mostrador_detalle_opcionales`

Espejo exacto de `venta_detalle_opcionales` cambiando FK por `pedido_mostrador_detalle_id`.

#### `pedido_mostrador_detalle_promociones`

Espejo exacto de `venta_detalle_promociones` cambiando FK por `pedido_mostrador_detalle_id`.

#### `pedido_mostrador_promociones`

Espejo de `venta_promociones` cambiando FK por `pedido_mostrador_id`. Registra promociones aplicadas a nivel pedido (no a item específico).

#### `pedidos_mostrador_pagos`

Espejo de `venta_pagos` con campos fiscales **removidos** (esos viven solo en el ciclo de venta).

| Campo | Tipo | Default | Notas |
|-------|------|---------|-------|
| `id` | bigint PK | | |
| `pedido_mostrador_id` | bigint unsigned | | FK CASCADE |
| `forma_pago_id` | bigint unsigned | | |
| `concepto_pago_id` | bigint unsigned | NULL | |
| `monto_base` | decimal(12,2) | | |
| `ajuste_porcentaje` | decimal(6,2) | 0.00 | |
| `monto_ajuste` | decimal(12,2) | 0.00 | |
| `monto_final` | decimal(12,2) | | |
| `monto_recibido` | decimal(12,2) | NULL | |
| `vuelto` | decimal(12,2) | NULL | |
| `cuotas` | tinyint unsigned | NULL | |
| `recargo_cuotas_porcentaje` | decimal(6,2) | NULL | |
| `recargo_cuotas_monto` | decimal(12,2) | NULL | |
| `monto_cuota` | decimal(12,2) | NULL | |
| `referencia` | varchar(100) | NULL | |
| `observaciones` | text | NULL | |
| `es_cuenta_corriente` | tinyint(1) | 0 | |
| `es_pago_puntos` | tinyint(1) | 0 | |
| `puntos_usados` | int unsigned | 0 | |
| `afecta_caja` | tinyint(1) | 1 | |
| `estado` | enum | 'activo' | activo / anulado |
| `movimiento_caja_id` | bigint unsigned | NULL | FK `movimientos_caja.id` SET NULL |
| `anulado_por_usuario_id` | bigint unsigned | NULL | |
| `anulado_at` | timestamp | NULL | |
| `motivo_anulacion` | varchar(500) | NULL | |
| `creado_por_usuario_id` | bigint unsigned | | |
| `cierre_turno_id` | bigint unsigned | NULL | |
| `moneda_id` | bigint unsigned | NULL | |
| `monto_moneda_original` | decimal(14,2) | NULL | |
| `tipo_cambio_tasa` | decimal(14,6) | NULL | |
| `venta_pago_id` | bigint unsigned | NULL | FK `venta_pagos.id` (poblado al convertir) |
| `created_at` / `updated_at` | timestamp | | |

NO incluidos: `comprobante_fiscal_id`, `monto_facturado`, `nota_credito_generada_id`, `comprobante_fiscal_nuevo_id`, `estado_facturacion`, `venta_pago_reemplazado_id`, `operacion_origen`, `datos_snapshot_json`. Esos campos son del ciclo fiscal de venta y se establecen en `VentaPago` al convertir.

### Tablas modificadas

#### `sucursales` — Cambios
- Agregar `pedido_mostrador_ultimo_numero` (int unsigned, default 0) AFTER `facturacion_fiscal_automatica`. Contador para numeración propia.
- Agregar `imprime_comanda_automatico` (tinyint(1), default 1) AFTER `pedido_mostrador_ultimo_numero`.
- Agregar `pedido_conversion_automatica_al_entregar` (tinyint(1), default 0) AFTER `imprime_comanda_automatico`.
- Agregar `usa_beepers` (tinyint(1), default 0) AFTER `pedido_conversion_automatica_al_entregar`. Si true, los pedidos requieren `numero_beeper`.

#### `articulos` — Cambios (sub-feature opcional RF-16)
- Agregar `imagen` (varchar(255), NULL) AFTER `descripcion`. Storage path relativo. Si se difiere, mover a un PR aparte.

#### `movimientos_caja` — Sin cambios de schema
- `referencia_tipo` ya es `varchar(191)`, acepta el valor `'pedido_mostrador'` sin migración. Solo agregar constante `MovimientoCaja::REF_PEDIDO_MOSTRADOR = 'pedido_mostrador'`.

#### `movimientos_stock` — Verificar
- Confirmar durante PR2 que `referencia_tipo` (o equivalente) acepta valor `'pedido_mostrador'`. Si es enum, agregarlo. Si es varchar, sin cambios.

---

## Pantallas UI

### Pantalla 1: Lista de pedidos (`/pedidos/mostrador`)
**Componente**: `App\Livewire\Pedidos\PedidosMostrador`
**Traits**: `SucursalAware`, `#[Lazy]` con skeleton `<x-skeleton.page-table />`
- Header: título, filtros (estado pedido, estado pago, fecha, identificador/cliente), botón "Nuevo Pedido" (color verde, abre modal full-screen).
- Móvil: cards (`sm:hidden`). Desktop: tabla (`hidden sm:block`) con columnas: Nº, Beeper (si sucursal `usa_beepers`), Identificador/Cliente, Total, Estado pedido, Estado pago, Tiempo, Acciones. El número de beeper se muestra como badge prominente para identificación rápida.
- Acciones por fila: Editar (azul), Cobrar (verde, oculto si pagado), Cambiar estado (siguiente lógico), Convertir en venta (si entregado y no facturado), Cancelar (rojo), Imprimir comanda, Imprimir precuenta.
- `wire:poll.8s="refresh"` para auto-actualización.
- Modales: cancelar (motivo), confirmar conversión a venta.

### Pantalla 2: Modal full-screen Nuevo/Editar Pedido (`/pedidos/mostrador/nuevo` y `?editar={id}`)
**Componente**: `App\Livewire\Pedidos\NuevoPedidoMostrador`
**Traits**: `SucursalAware`, `CajaAware`, `#[Lazy]`, **+ todos los sub-traits compartidos** (`WithCarritoItems`, `WithBusquedaArticulos`, `WithBusquedaClientes`, `WithDescuentos`, `WithCupones`, `WithPuntos`, `WithOpcionales`, `WithPagosDesglose`, `WithCalculoVenta`).
- Layout 3 cols (`grid-cols-1 lg:grid-cols-12`):
  - **Izquierda (4 cols)**: input búsqueda artículos + carrito de items (mismo render que NuevaVenta).
  - **Centro (5 cols)**: panel táctil (categorías → artículos al click).
  - **Derecha (3 cols)**:
    - **Bloque "Cliente"** (RF-17): combo búsqueda de cliente oficial + inputs Nombre y Teléfono temporales (con búsqueda automática por teléfono y botón "Dar de alta") + indicador del cliente seleccionado/temporal actual.
    - Identificador, número de beeper (si la sucursal `usa_beepers`), lista de precios, descuentos, cupón, puntos, resumen totales, botones acción.
- Botones acción derecha:
  - "Guardar borrador" (gris)
  - "Confirmar pedido" (verde) → guarda + descuenta stock + comanda + cierra modal
  - "Confirmar y cobrar" (verde oscuro) → guarda + abre modal de pagos
  - "Cobrar pendiente" (visible solo si editando con saldo > 0)
  - "Convertir en venta" (azul, visible solo en estados entregado/listo)
  - "Cancelar pedido" (rojo, con confirmación)
- Modal se abre con `wire:click` → emite evento → componente padre lo escucha y abre. Cierre con `Esc` o botón X.
- Atajos teclado: F2 cobrar, F3 cancelar, F4 descuentos, Esc cerrar (mismo patrón que NuevaVenta).

### Pantalla 3: Configuración de sucursal (modificación menor)
**Componente existente**: `App\Livewire\Configuracion\Sucursales` (o donde se editen sucursales)
- Sección nueva "Pedidos por mostrador":
  - Toggle "Imprimir comanda automáticamente al confirmar"
  - Toggle "Convertir a venta automáticamente al marcar como entregado"
  - Toggle "Usa beepers llamadores" (si activo, el alta de pedido pide número de beeper obligatorio)
  - Botón "Resetear numeración de pedidos" (con confirmación + permiso)
  - Indicador del próximo número que se asignará

---

## Servicios

### `App\Services\Pedidos\PedidoMostradorService` (nuevo)

```php
crearPedido(array $data, array $detalles, bool $esBorrador = false): PedidoMostrador
  // Si !$esBorrador: asigna número, descuenta stock, dispara PedidoCreado, dispara comanda
  // Si $esBorrador: solo guarda registros sin numero ni stock

actualizarPedido(PedidoMostrador $pedido, array $data, array $detalles): PedidoMostrador
  // Recalcula deltas de stock (positivos/negativos por cambios en items)
  // Solo permitido en estados borrador/confirmado

cambiarEstado(PedidoMostrador $pedido, string $nuevoEstado, ?string $observacion = null): void
  // Valida transición permitida
  // Setea timestamp del estado correspondiente
  // Si configurado: convierte automáticamente al pasar a entregado
  // Dispara PedidoEstadoCambiado

agregarPago(PedidoMostrador $pedido, array $datosPago): PedidoMostradorPago
  // Crea PedidoMostradorPago + MovimientoCaja (referencia_tipo = pedido_mostrador)
  // Recalcula estado_pago del pedido
  // Dispara PedidoEstadoPagoCambiado si cambió

anularPago(PedidoMostradorPago $pago, ?string $motivo): void
  // Genera contraasiento en MovimientoCaja
  // Marca pago como anulado
  // Recalcula estado_pago

cancelarPedido(PedidoMostrador $pedido, string $motivo): void
  // Anula todos los pagos (contraasientos)
  // Revierte stock si estaba descontado (movimientos inversos)
  // Marca cancelado

convertirEnVenta(PedidoMostrador $pedido, ?array $opcionesFiscales = null): Venta
  // Construye datosVenta + detalles desde el pedido
  // Llama a VentaService::crearVenta($datosVenta, $detalles, ['stock_ya_descontado' => true])
  // Migra PedidoMostradorPago a VentaPago
  // Re-asocia MovimientoCaja a la venta resultante
  // Re-asocia MovimientoStock a la venta resultante
  // Si corresponde, emite comprobante fiscal vía ComprobanteFiscalService
  // Marca pedido como facturado, guarda venta_id
  // Si quedan saldos pendientes en CC: registra MovimientoCuentaCorriente
  // Acumula puntos
  // Dispara PedidoConvertidoEnVenta

siguienteNumero(int $sucursalId): int
  // SELECT FOR UPDATE sucursales.pedido_mostrador_ultimo_numero
  // Incrementa atómicamente y retorna

resetearNumeracion(int $sucursalId, int $usuarioId): void
  // Set sucursales.pedido_mostrador_ultimo_numero = 0
  // Audita en log

imprimirComanda(PedidoMostrador $pedido): void
  // Resuelve impresora vía ImpresoraTipoDocumento (tipo = 'comanda')
  // Genera contenido vía PlantillasComanda
  // Despacha al ImpresionService

imprimirPrecuenta(PedidoMostrador $pedido): void
  // Idem pero con tipo_documento = 'precuenta'
```

### `App\Services\VentaService` — Modificación
- `crearVenta()` recibe nuevo parámetro opcional `array $opciones = []` con flag `'stock_ya_descontado' => bool`. Si true, salta el bloque de descuento de stock pero sí registra metadatos (lista de movimientos a re-asociar).
- Sin breaking change: si no se pasa, comportamiento idéntico.

### `App\Services\Impresion\PlantillasComanda` (nuevo)
- `generarComandaESCPOS(PedidoMostrador $pedido): string`
- `generarComandaHTML(PedidoMostrador $pedido): string`
- `generarPrecuentaESCPOS(PedidoMostrador $pedido): string`
- `generarPrecuentaHTML(PedidoMostrador $pedido): string`

### Services agnósticos a reutilizar (sin cambios)
- `PrecioService` — cálculo, lista aplicable, promociones.
- `OpcionalService` — recetas de opcionales por sucursal.
- `CuponService` — validación y aplicación de cupones.
- `PuntosService` — acumulación y canje.
- `StockService` — descuento y reverso de stock con recetas.
- `ImpresionService` — despacho a QZ Tray.

---

## Sub-traits Livewire (Fase 0 / PR1)

Extracción del componente `App\Livewire\Ventas\NuevaVenta` (~7954 líneas) a sub-traits componibles. **Ningún cambio funcional.** Suite completa de tests verifica que el comportamiento de Nueva Venta es idéntico antes y después.

Ubicación: `app/Livewire/Concerns/Carrito/`

| Trait | Líneas aproximadas a mover | Métodos principales |
|-------|---------------------------|---------------------|
| `WithCarritoItems` | ~700 | `agregarArticulo`, `eliminarItem`, `actualizarCantidad`, `agregarPorCodigo`, `agregarPrimerArticulo` |
| `WithBusquedaArticulos` | ~400 | `updatedBusquedaArticulo`, `seleccionarArticulo`, `articulosResultados` |
| `WithBusquedaClientes` | ~250 | `updatedBusquedaCliente`, alta rápida cliente |
| `WithOpcionales` | ~300 | wizard de opcionales, agregar/quitar opcional a item |
| `WithDescuentos` | ~400 | descuento general (modal, aplicar, quitar), tope por rol |
| `WithCupones` | ~350 | `validarCupon`, `aplicarCupon`, `quitarCupon` |
| `WithPuntos` | ~400 | `aplicarCanjePuntos`, `canjearArticuloConPuntos`, `puntosLibres` |
| `WithCalculoVenta` | ~1500 | `calcularVenta`, `crearPoolUnidades`, `obtenerPromocionesEspeciales`, `obtenerPromocionesComunes`, `aplicarPromocionesComunes` |
| `WithPagosDesglose` | ~1800 | modal pago, `agregarAlDesglose`, `eliminarDelDesglose`, `confirmarPago`, manejo moneda extranjera |
| `WithArticuloRapido` | ~200 | modal alta rápida de artículo |
| `WithConsultaPrecios` | ~150 | modo consulta de precios |

**Restante en `NuevaVenta` tras refactor**: solo orquestación y los métodos específicos de venta (`procesarVenta`, `procesarVentaConDesglose`, integración con `ComprobanteFiscalService`, etc.). Estimado: <2000 líneas.

`NuevoPedidoMostrador` compone los traits que necesita (todos menos los específicamente fiscales) y agrega su propia lógica de "guardar como pedido" (no procesar venta).

---

## Migraciones Necesarias

### PR1 (refactor — sin migraciones de schema)
Solo refactor de código. No hay migraciones.

### PR2 (módulo)

1. `add_pedidos_mostrador_config_to_sucursales` — agrega `pedido_mostrador_ultimo_numero`, `imprime_comanda_automatico`, `pedido_conversion_automatica_al_entregar`, `usa_beepers` a `sucursales`.
2. `create_pedidos_mostrador_table` — crea `pedidos_mostrador` (iteración por comercio, prefijo, try/catch).
3. `create_pedidos_mostrador_detalle_table` — crea `pedidos_mostrador_detalle`.
4. `create_pedido_mostrador_detalle_opcionales_table`.
5. `create_pedido_mostrador_detalle_promociones_table`.
6. `create_pedido_mostrador_promociones_table`.
7. `create_pedidos_mostrador_pagos_table`.
8. `add_imagen_to_articulos` — sub-feature opcional RF-16. Si se difiere a PR3, omitir.
9. **Regenerar `database/sql/tenant_tables.sql`** (obligatorio post-migraciones tenant).
10. **Actualizar `ProvisionComercioCommand::seedRolesYPermisos()`** con los nuevos permisos.
11. **Migración de menú**: agregar item `pedidos.mostrador` bajo el padre `ventas`.

---

## Permisos

Permisos Spatie nuevos (en `pymes.permissions` compartida):
- `pedidos_mostrador.crear`
- `pedidos_mostrador.ver`
- `pedidos_mostrador.editar`
- `pedidos_mostrador.cambiar_estado`
- `pedidos_mostrador.cobrar`
- `pedidos_mostrador.convertir_venta`
- `pedidos_mostrador.cancelar`
- `pedidos_mostrador.resetear_numeracion`
- `pedidos_mostrador.api_acceso` (para tokens Sanctum de tótem/app externa)

Asignación por defecto:
- **Admin**: todos.
- **Cajero**: crear, ver, editar, cambiar_estado, cobrar, convertir_venta.
- **Cocina** (rol nuevo, opcional): ver, cambiar_estado (transiciones limitadas).
- **Solo lectura**: ver.

---

## Menú

Agregar item bajo "Ventas":
- `nombre`: "Pedidos por Mostrador"
- `slug`: `pedidos.mostrador`
- `ruta`: `/pedidos/mostrador`
- `icono`: `heroicon-o-clipboard-document-list` (o similar)
- `permiso`: `pedidos_mostrador.ver`
- `parent`: id del item "Ventas"

---

## Traducciones

Resumen de claves nuevas (orden alfabético en los 3 archivos `lang/{es,en,pt}.json`):

| es | en | pt |
|----|----|----|
| Cancelar pedido | Cancel order | Cancelar pedido |
| Cambiar estado | Change status | Mudar estado |
| Cobrar pendiente | Charge pending | Cobrar pendente |
| Comanda | Kitchen ticket | Comanda |
| Confirmar pedido | Confirm order | Confirmar pedido |
| Confirmar y cobrar | Confirm and charge | Confirmar e cobrar |
| Convertir en venta | Convert to sale | Converter em venda |
| En preparación | In preparation | Em preparação |
| Entregado | Delivered | Entregue |
| Estado del pago | Payment status | Status do pagamento |
| Estado del pedido | Order status | Status do pedido |
| Facturado | Invoiced | Faturado |
| Guardar borrador | Save draft | Salvar rascunho |
| Identificador | Identifier | Identificador |
| Imprimir comanda | Print kitchen ticket | Imprimir comanda |
| Imprimir precuenta | Print pre-bill | Imprimir pré-conta |
| Listo | Ready | Pronto |
| Motivo de cancelación | Cancellation reason | Motivo de cancelamento |
| Nuevo Pedido | New Order | Novo Pedido |
| Número de beeper | Beeper number | Número do bipe |
| Usa beepers llamadores | Uses caller beepers | Usa bipes chamadores |
| Ingresá el número de beeper | Enter the beeper number | Insira o número do bipe |
| El número de beeper es obligatorio | Beeper number is required | O número do bipe é obrigatório |
| Nombre del cliente | Customer name | Nome do cliente |
| Teléfono del cliente | Customer phone | Telefone do cliente |
| Cliente reconocido | Customer recognized | Cliente reconhecido |
| Dar de alta como cliente | Register as customer | Cadastrar como cliente |
| Nombre y teléfono son obligatorios | Name and phone are required | Nome e telefone são obrigatórios |
| Buscar por teléfono | Search by phone | Buscar por telefone |
| Pagado | Paid | Pago |
| Parcial | Partial | Parcial |
| Pedidos por Mostrador | Counter Orders | Pedidos no Balcão |
| Pendiente de pago | Payment pending | Pagamento pendente |
| Precuenta | Pre-bill | Pré-conta |
| Resetear numeración | Reset numbering | Resetar numeração |
| Tiempo desde alta | Time since creation | Tempo desde criação |
| Volver a categorías | Back to categories | Voltar às categorias |

(La lista final puede crecer durante la implementación; se completa con `/traducir` por convención.)

---

## API REST

### Estructura
```
routes/api.php → Route::prefix('v1')->middleware(['auth:sanctum','tenant.context'])->group(...)
app/Http/Controllers/Api/V1/PedidoMostradorController.php
app/Http/Requests/Api/V1/PedidoMostrador/{StorePedidoRequest,UpdateEstadoRequest,...}.php
app/Http/Resources/Api/V1/{PedidoMostradorResource,PedidoMostradorDetalleResource,...}.php
```

### Endpoints v1
| Método | Ruta | Permiso | Descripción |
|--------|------|---------|-------------|
| POST | `/api/v1/pedidos-mostrador` | `crear` | Crear pedido (mismo payload que el componente) |
| GET | `/api/v1/pedidos-mostrador` | `ver` | Listar paginado con filtros |
| GET | `/api/v1/pedidos-mostrador/{id}` | `ver` | Detalle |
| PATCH | `/api/v1/pedidos-mostrador/{id}/estado` | `cambiar_estado` | Cambiar estado |
| PATCH | `/api/v1/pedidos-mostrador/{id}/cancelar` | `cancelar` | Cancelar (motivo) |
| POST | `/api/v1/pedidos-mostrador/{id}/pagos` | `cobrar` | Agregar pago |
| POST | `/api/v1/pedidos-mostrador/{id}/convertir-venta` | `convertir_venta` | Disparar conversión |

### Auth
- Sanctum personal access tokens.
- Token se emite via UI nueva (Configuración → Tokens API), con scope (selección de permisos del rol).
- Tenant context: se resuelve desde el usuario del token (su comercio).

### Versionado
- Prefijo `/v1`. Si el contrato cambia incompatiblemente, `/v2` y se mantiene `/v1` durante el período de transición.

### Tests API
- Feature tests con `actingAs($user, 'sanctum')` y assertions JSON.

---

## Eventos Laravel

Ubicación: `app/Events/PedidoMostrador/`

```php
PedidoCreado(int $pedidoId, int $sucursalId, int $usuarioId)
PedidoEstadoCambiado(int $pedidoId, string $estadoAnterior, string $estadoNuevo, int $usuarioId)
PedidoEstadoPagoCambiado(int $pedidoId, string $estadoAnterior, string $estadoNuevo)
PedidoConvertidoEnVenta(int $pedidoId, int $ventaId, int $usuarioId)
PedidoCancelado(int $pedidoId, string $motivo, int $usuarioId)
```

Sin listeners en este PR. Cuando agreguemos Reverb/broadcasting, los eventos quedan listos. Cuando agreguemos módulo Cocina/Monitor, sus listeners se enganchan acá.

---

## Criterios de Aceptación

### Generales
- [ ] Lint pasa: `php vendor/bin/pint --test`
- [ ] Tests pasan: `php artisan test`
- [ ] `database/sql/tenant_tables.sql` regenerado
- [ ] Traducciones en los 3 archivos
- [ ] Documentación actualizada vía `@docs-sync` antes del PR
- [ ] CI verde en GitHub Actions

### PR1 (Refactor) — bloqueantes para mergear

**Regla de oro**: Nueva Venta debe funcionar **exactamente igual que antes**. Si un solo punto de los siguientes falla, el PR no se mergea.

- [ ] Todos los sub-traits creados en `app/Livewire/Concerns/Carrito/`
- [ ] `NuevaVenta` reducido a < 2500 líneas
- [ ] **Suite completa de tests verde con el MISMO número de tests pasando que en master pre-refactor** (no se acepta "rompió 2 tests pero arreglé 3 nuevos"). Diferencia tolerada: 0.
- [ ] **Tests existentes NO modificados** (`git diff master tests/` solo agrega tests nuevos de smoke por trait, no edita tests existentes)
- [ ] Snapshot del contrato Livewire (`tests/snapshots/nueva-venta-contract.txt`) sin diff vs baseline pre-refactor
- [ ] Vista Blade `nueva-venta.blade.php` y sus parciales **sin tocar** (`git diff master resources/views/livewire/ventas/` vacío)
- [ ] Services (`PrecioService`, `VentaService`, `OpcionalService`, `CuponService`, `PuntosService`, `StockService`, `ComprobanteFiscalService`) **sin tocar** (`git diff master app/Services/` vacío)
- [ ] Migraciones, configuración, rutas, traducciones, permisos, menú **sin tocar**
- [ ] Tablas, columnas, índices, foreign keys de BD **sin cambios**
- [ ] `database/sql/tenant_tables.sql` **idéntico** al de master
- [ ] Test nuevo de smoke por trait (mínimo 1 por trait usando un componente fixture)
- [ ] Lazy loading + skeleton de Nueva Venta funcionan idéntico
- [ ] Atajos de teclado (Ctrl+1..9, F2, F3, F4) responden igual
- [ ] **Verificación manual completa del checklist (sección "Estrategia de seguridad / Checklist obligatorio") con resultado idéntico al baseline**
- [ ] Capturas/video comparativos antes/después adjuntos en el PR description
- [ ] CI verde (Pint + PHPUnit)
- [ ] Tag `pre-refactor-traits-{fecha}` creado y comunicado para rollback express si surge bug en producción post-merge

### PR2 (Módulo)
- [ ] Migraciones aplicadas en todos los comercios
- [ ] Item de menú visible bajo Ventas con permiso correcto
- [ ] Crear pedido descuenta stock correctamente (verificable con `MovimientoStock`)
- [ ] Cancelar pedido revierte stock y pagos (contraasientos)
- [ ] Convertir pedido en venta:
  - Crea `Venta` y `VentaPago` correctos
  - **No vuelve a descontar stock**
  - Re-asocia `MovimientoCaja` a la venta
  - Emite comprobante fiscal si configurado
  - Marca pedido como `facturado` con `venta_id`
- [ ] Numeración por sucursal: pedidos consecutivos sin saltos, reset funciona, indexado
- [ ] Comanda se imprime automáticamente al confirmar (si configurado y hay impresora asignada)
- [ ] Botón reimprimir comanda funciona
- [ ] Panel táctil: barra horizontal de categorías scrolleable + grilla de artículos siempre visible. Cambio de categoría sin viaje al server (Alpine local). Click en artículo agrega al carrito.
- [ ] Empty state si la sucursal no tiene categorías o artículos asignados.
- [ ] **Beepers**: si la sucursal tiene `usa_beepers = true`, el form de pedido pide número de beeper obligatorio para confirmar; si está en false, el input no se muestra. El beeper aparece en lista de pedidos, comanda y precuenta impresas. Si está en false el input no aparece.
- [ ] **Cliente del pedido (RF-17)**:
  - Confirmar pedido sin cliente oficial Y sin nombre+teléfono temporal → bloqueado con error.
  - Ingresar solo teléfono que coincide con un cliente existente → autocompleta nombre y pre-asocia el cliente.
  - Ingresar teléfono sin coincidencia + nombre nuevo → guarda como temporal.
  - Botón "Dar de alta como cliente" crea el cliente y lo asocia al pedido (limpia campos temporales).
  - Comanda y precuenta impresas muestran nombre + teléfono (oficial o temporal).
  - Conversión a venta con cliente temporal → `Venta.cliente_id = NULL`, datos temporales se preservan en el pedido.
  - Búsqueda de teléfono normaliza (ignora espacios, guiones, paréntesis, +).
- [ ] Multi-pedido: se pueden tener varios pedidos abiertos en simultáneo en distintas terminales sin conflicto
- [ ] Polling de la lista actualiza al cambiar estado desde otra terminal
- [ ] Permisos respetados: usuario sin `cobrar` no ve botón Cobrar; sin `convertir_venta` no ve Convertir
- [ ] API: tests feature pasan para los 7 endpoints
- [ ] Eventos disparados verificables con `Event::fake()` en tests

---

## Plan de Implementación

### PR1 — Refactor de Nueva Venta a sub-traits [PENDIENTE]

**Branch**: `refactor/nueva-venta-traits`

#### 🔴 REGLA DE ORO

**Después de este PR, Nueva Venta DEBE funcionar exactamente igual que antes. Cero cambios visibles para el usuario, cero cambios en BD, cero cambios en el contrato Livewire (eventos, propiedades públicas, métodos públicos), cero cambios en el comportamiento de cálculo, pago, stock, fiscal o caja.**

Si alguna verificación detecta una diferencia de comportamiento, el commit que la introdujo se revierte y se vuelve a planificar. Los traits son **mecánica**: mover código sin tocar lógica. Cualquier oportunidad de "mejora" detectada durante el refactor se anota en un TODO y se hace en un PR aparte, NUNCA dentro de este.

#### Restricciones explícitas durante PR1

Lo que **SÍ** se hace:
- Mover bloques de código de `NuevaVenta.php` a archivos de trait en `app/Livewire/Concerns/Carrito/`.
- Reemplazar el bloque movido por un `use NombreDelTrait;` en `NuevaVenta`.
- Mantener nombres exactos de propiedades públicas, métodos públicos, eventos, computed properties y atributos `#[On(...)]`/`#[Computed]`/`#[Lazy]`.
- Mantener firmas exactas (parámetros, tipos, retornos).
- Conservar visibilidad (public/protected/private) idéntica.
- Conservar comentarios y docblocks.

Lo que **NO** se hace:
- ❌ Renombrar propiedades, métodos, eventos, vistas, claves de traducción.
- ❌ Cambiar firmas de métodos (ni siquiera "limpiar" parámetros).
- ❌ Refactorizar lógica interna de un método (aunque parezca obvio mejorarlo).
- ❌ Combinar/dividir métodos.
- ❌ Cambiar order de inicialización en `mount()` o `boot()`.
- ❌ Tocar la vista Blade de Nueva Venta (`nueva-venta.blade.php`) ni sus parciales.
- ❌ Tocar Services (`PrecioService`, `VentaService`, etc.) — se refactorea solo el componente Livewire.
- ❌ Agregar nuevas dependencias, nuevos helpers, nuevos eventos.
- ❌ Cambiar el orden o nombre de archivos de migración existentes.
- ❌ Tocar configuración (`config/`), rutas, traducciones, permisos, menú.
- ❌ Modificar sucursales, articulos ni ninguna tabla.
- ❌ Mover el archivo `NuevaVenta.php` de carpeta.
- ❌ Cambiar visibilidad (un `protected` no se vuelve `private`).

#### Estrategia de seguridad antes/durante/después

**Antes (Fase 0)**:
1. **Baseline de tests automatizados**:
   - Correr `php artisan test` completo en master. Anotar nº de tests verdes, nombres y duración total.
   - Correr `php artisan test --filter=NuevaVenta` y suite de Ventas. Anotar baseline.
   - Correr `php artisan test --filter=Precio` y `--filter=Promocion`. Anotar baseline.
   - Si algún test está fallando en master antes del PR1, registrarlo como pre-existente y no contarlo como regresión.
2. **Baseline de comportamiento manual** — guardar capturas o video de:
   - Layout de NuevaVenta en desktop y móvil.
   - Modal de pago abierto en pago único.
   - Modal de pago con desglose múltiple (efectivo + débito + CC).
   - Modal de descuentos.
   - Modal de cupón.
   - Modal de canje de puntos.
   - Wizard de opcionales.
3. **Snapshot del contrato del componente** — generar y commitear un archivo `tests/snapshots/nueva-venta-contract.txt` listando: propiedades públicas con tipo, métodos públicos con firma, computed properties, eventos `#[On]`, eventos despachados (`$this->dispatch(...)`). Después de cada extracción de trait, regenerar y diferenciar — el diff debe ser vacío.
4. **Tag de release antes del refactor**: `git tag pre-refactor-traits-2026-05-XX`. Permite volver al estado exacto si hace falta.

**Durante (un trait por commit)**:
1. Extraer un solo trait por commit (mínimo 11 commits, máximo 1 trait por commit).
2. Tras cada commit:
   - Correr `php vendor/bin/pint` sobre archivos modificados.
   - Correr suite completa de tests. **Si baja el nº de tests verdes vs baseline → revertir el commit y reanalizar.**
   - Regenerar snapshot del contrato. **Si difiere → revertir.**
   - Probar manualmente al menos el flujo cubierto por ese trait (ver checklist abajo).
3. Mensaje de commit estándar: `refactor(nueva-venta): extract WithCarritoItems trait` (sin cambios funcionales).
4. Si algo falla y la causa no es obvia en 30 minutos: revertir y abrir issue para análisis. No "arreglar" durante el refactor.

**Después (antes de mergear)**:
1. Suite completa verde con el mismo nº de tests que el baseline.
2. Snapshot del contrato sin diff.
3. Verificación manual exhaustiva (ver checklist abajo).
4. Diff completo del PR debe consistir en: archivos nuevos en `app/Livewire/Concerns/Carrito/`, deleciones de bloques en `NuevaVenta.php`, agregados de `use NombreTrait;` en `NuevaVenta`. Cualquier otro cambio merece justificación en el PR description.

#### Checklist obligatorio de validación manual (post-cada-trait y final)

En entorno con datos reales o de fixtures, ejecutar y verificar resultado **idéntico al baseline**:

**Búsqueda y carrito**:
- [ ] Buscar artículo por texto, agregar, ver en carrito.
- [ ] Agregar por código de barras (scanner emulado: tipeo rápido).
- [ ] Agregar por código manual.
- [ ] Cambiar cantidad de un item.
- [ ] Eliminar item.
- [ ] Limpiar carrito completo.
- [ ] Agregar artículo con grupo de opcionales obligatorios → wizard se abre, completar, ver opcionales en el item con precio extra.
- [ ] Agregar artículo desde búsqueda y desde "Alta artículo rápido".
- [ ] Concepto libre.

**Cliente**:
- [ ] Buscar cliente existente, asociarlo.
- [ ] Quitar cliente.
- [ ] Alta rápida de cliente desde el modal.

**Precios y promociones**:
- [ ] Cambiar lista de precios manualmente, ver recálculo.
- [ ] Aplicar artículo con promoción común vigente.
- [ ] Aplicar artículo con promoción especial NxM.
- [ ] Verificar que la "mejor combinación" se sigue eligiendo (test reproductor existente debe pasar).
- [ ] Aplicar ajuste manual a un item (% y monto fijo).

**Descuentos**:
- [ ] Aplicar descuento general por porcentaje.
- [ ] Aplicar descuento general por monto fijo.
- [ ] Quitar descuento general.
- [ ] Verificar tope por rol.

**Cupones**:
- [ ] Aplicar cupón válido.
- [ ] Cupón con restricción de forma de pago.
- [ ] Cupón con restricción de artículos.
- [ ] Quitar cupón.

**Puntos**:
- [ ] Canjear artículo con puntos.
- [ ] Aplicar canje de puntos como pago.
- [ ] Verificar acumulación post-venta.

**Pagos**:
- [ ] Pago único en efectivo con vuelto.
- [ ] Pago único con tarjeta + cuotas + recargo.
- [ ] Pago múltiple (efectivo + débito).
- [ ] Pago mixto con CC del cliente.
- [ ] Pago en moneda extranjera con tipo de cambio.
- [ ] Eliminar un pago del desglose y volver a agregar.
- [ ] Vuelto calculado correctamente.

**Cierre fiscal y caja**:
- [ ] Venta sin factura fiscal → MovimientoCaja generado correctamente.
- [ ] Venta con factura fiscal automática (Mono o RI) → ComprobanteFiscal creado, CAE recibido.
- [ ] Venta con CC → MovimientoCuentaCorriente registrado, saldo cliente actualizado.
- [ ] Venta con stock con receta → MovimientoStock por ingredientes.
- [ ] Venta con artículo sin stock controlado → no bloquea.
- [ ] Tests de cancelación de venta y anulación de pagos siguen pasando.

**UI/UX**:
- [ ] Layout idéntico al baseline en desktop (1920x1080) y móvil (375x812).
- [ ] Atajos de teclado (Ctrl+1..9, F2, F3, F4) funcionan igual.
- [ ] Lazy loading + skeleton aparecen igual.
- [ ] Toasts de éxito/error con los mismos textos.
- [ ] Modales abren y cierran igual.

#### Pasos del refactor

1. Snapshot de baselines (tests + contrato + capturas) — `chore(refactor): baseline antes de extraer traits`.
2. **Crear directorio** `app/Livewire/Concerns/Carrito/` y archivos vacíos para cada trait.
3. **Mover trait por trait** (un commit por trait). Después de cada uno: correr suite completa de tests.
   - Orden sugerido (de menor a mayor riesgo):
     1. `WithBusquedaClientes` (250 líneas)
     2. `WithBusquedaArticulos` (400 líneas)
     3. `WithCarritoItems` (700 líneas)
     4. `WithOpcionales` (300 líneas)
     5. `WithDescuentos` (400 líneas)
     6. `WithCupones` (350 líneas)
     7. `WithPuntos` (400 líneas)
     8. `WithArticuloRapido` (200 líneas)
     9. `WithConsultaPrecios` (150 líneas)
     10. `WithCalculoVenta` (1500 líneas) — el más crítico
     11. `WithPagosDesglose` (1800 líneas) — el más grande
4. **Tests de smoke por trait**: crear componente fixture mínimo que use cada trait y verifique que se monta + métodos públicos clave responden.
5. **Verificación manual de Nueva Venta**: ejecutar manualmente flujo completo (búsqueda, agregar items, opcionales, descuento, cupón, puntos, pago múltiple con CC + débito + efectivo, cierre con factura).
6. **Lint + tests + docs-sync + crear PR**.

### PR2 — Módulo Pedidos por Mostrador completo [PENDIENTE]

**Branch**: `feat/pedidos-mostrador`
**Depende de PR1 mergeado.**

#### Fase 2.1: Base de datos
1. Migración: agregar columnas a `sucursales`.
2. Migración: `create_pedidos_mostrador_table` (iteración por comercios, prefijo, try/catch — usar `/migration` skill).
3. Migración: `create_pedidos_mostrador_detalle_table`.
4. Migración: `create_pedido_mostrador_detalle_opcionales_table`.
5. Migración: `create_pedido_mostrador_detalle_promociones_table`.
6. Migración: `create_pedido_mostrador_promociones_table`.
7. Migración: `create_pedidos_mostrador_pagos_table`.
8. Regenerar `database/sql/tenant_tables.sql`.
9. Actualizar `ProvisionComercioCommand::seedRolesYPermisos()` con permisos nuevos.
10. Migración menú: insertar item bajo Ventas.

#### Fase 2.2: Modelos
11. `App\Models\PedidoMostrador` (con scopes: porSucursal, porEstado, porEstadoPago, hoy).
12. `App\Models\PedidoMostradorDetalle`.
13. `App\Models\PedidoMostradorDetalleOpcional`.
14. `App\Models\PedidoMostradorDetallePromocion`.
15. `App\Models\PedidoMostradorPromocion`.
16. `App\Models\PedidoMostradorPago`.
17. Tests unitarios de modelos (relaciones, scopes, casts).
18. Agregar constante `MovimientoCaja::REF_PEDIDO_MOSTRADOR`.

#### Fase 2.3: Service
19. `App\Services\Pedidos\PedidoMostradorService` con todos los métodos definidos.
20. Modificar `App\Services\VentaService::crearVenta()` para aceptar `$opciones['stock_ya_descontado']`.
21. `App\Services\Impresion\PlantillasComanda` (ESC/POS + HTML).
22. Tests unitarios del service: crear, agregar pago, cambiar estado, cancelar, convertir en venta, numeración.

#### Fase 2.4: Eventos
23. Clases de eventos en `app/Events/PedidoMostrador/`.

#### Fase 2.5: Componente Lista
24. `App\Livewire\Pedidos\PedidosMostrador` con SucursalAware + Lazy + skeleton.
25. Vista `resources/views/livewire/pedidos/pedidos-mostrador.blade.php` (cards móvil + tabla desktop, modales cancelar/convertir).
26. Test `Livewire::test()->assertOk()` con `withoutLazyLoading`.

#### Fase 2.6: Componente Modal Pedido
27. `App\Livewire\Pedidos\NuevoPedidoMostrador` componiendo todos los sub-traits.
28. Vista `nuevo-pedido-mostrador.blade.php` con layout 3 cols + panel táctil + atajos teclado.
29. Vista parcial `_panel-tactil.blade.php` (categorías + artículos).
30. Tests Livewire de carga, agregar item, calcular, confirmar, cobrar.

#### Fase 2.7: Configuración de sucursal
31. Modificar componente de edición de sucursal para mostrar nuevas opciones.
32. Botón resetear numeración con confirmación.

#### Fase 2.8: API REST
33. `routes/api.php` con endpoints + Sanctum.
34. Controller, Form Requests, Resources.
35. UI mínima de generación de tokens en Configuración.
36. Feature tests para los 7 endpoints.

#### Fase 2.9: Sub-feature opcional RF-16 (imagen artículo)
*Si se complica, mover a PR3 separado.*
37. Migración `add_imagen_to_articulos`.
38. UI de carga en form de Artículo.
39. Servicio de redimensionado (Intervention Image).
40. Renderizar en panel táctil.

#### Fase 2.10: Cierre
41. Lint + suite de tests completa.
42. Verificación manual: 5+ pedidos en distintos estados desde 2 terminales en simultáneo.
43. `@docs-sync` para actualizar manual y knowledge base.
44. Crear PR.

---

## Notas y Decisiones

- **2026-05-04**: Decisión inicial — el módulo se diseña como **prototipo del patrón** que luego cubrirá Delivery / Salón con mesas / Mayoristas. Cada uno tendrá tabla propia (no polimórfica). Lo compartido son los sub-traits Livewire y los services agnósticos. Esto evita acoplamientos prematuros.
- **2026-05-04**: Stock se descuenta al confirmar pedido (no al convertir a venta). Justificación: en gastronomía la materia prima se consume cuando cocina empieza a preparar. Al convertir, se evita doble descuento con flag `stock_ya_descontado` en `VentaService::crearVenta`.
- **2026-05-04**: Cobros previos a la conversión usan `MovimientoCaja.referencia_tipo = 'pedido_mostrador'`. Al convertir, los movimientos se re-asocian a la venta resultante. Este patrón mantiene la caja siempre cuadrada en cualquier momento del ciclo del pedido.
- **2026-05-04**: Numeración propia por sucursal (no compartida con `Caja.ultimoNumero` de ventas). Permite reset manual desde configuración (con permiso). Justificación: el número de pedido se usa para llamar al cliente, no para auditar contabilidad.
- **2026-05-04**: Sin concepto de mesa en este módulo. Mesas vendrán con el módulo Salón a futuro. Para identificar pedidos se usa el campo libre `identificador`.
- **2026-05-04**: API REST con Sanctum desde el inicio (mismo PR2). Permite que el futuro módulo de Cocina, Monitor y tótem externo consuman datos sin tocar Livewire.
- **2026-05-04**: Eventos Laravel se disparan ya, sin listeners. Reverb / broadcasting no se configura ahora; el día que se sume, los eventos están listos.
- **2026-05-04**: Refactor de `NuevaVenta` a sub-traits es un PR aparte (PR1) sin cambios funcionales. Una vez mergeado y validado, PR2 los reutiliza limpio.
- **2026-05-04**: La conversión a venta es **configurable por sucursal** (`pedido_conversion_automatica_al_entregar`). Cada negocio elige su flujo.
- **2026-05-04**: La carga de imagen de artículo (RF-16) es **opcional dentro de PR2**. Si complica el alcance, se puede diferir a PR3 sin bloquear el módulo.
- **2026-05-04**: Panel táctil con **layout split persistente** (RF-11): barra horizontal sticky de categorías + grilla de artículos siempre visible. Descartado el drill-in (categorías → atrás → otra categoría) porque obliga a 2 taps por cambio. El cambio de categoría vive en Alpine local para evitar latencia de Livewire. Inspirado en POS gastronómicos comerciales (Square, TouchBistro, Toast).
- **2026-05-04**: Beepers llamadores (RF-15) se modelan como configuración por sucursal (`usa_beepers`) + campo `numero_beeper` (varchar 20, alfanumérico) en el pedido. Si la sucursal los usa, el input es obligatorio para confirmar. Visible en lista, comanda impresa y precuenta. Preparado para que el futuro módulo Monitor ilumine el número cuando el pedido pase a `listo`.
- **2026-05-04**: Cliente del pedido (RF-17) admite 3 caminos: (a) cliente oficial existente vía combo de búsqueda; (b) cliente temporal con nombre+teléfono guardados en `nombre_cliente_temporal`/`telefono_cliente_temporal` del pedido (sin alta); (c) alta inline reutilizando el modal de alta rápida del trait `WithBusquedaClientes`. La búsqueda por teléfono autocompleta cliente si matchea (normalización: ignora espacios/guiones/paréntesis/+). Justificación: en mostrador rápido no siempre se quiere dar de alta a cada cliente, pero sí se necesita identificarlo para llamarlo cuando esté listo. Al convertir a venta, si el cliente era temporal, la venta queda como consumidor final y los datos temporales se preservan solo en el pedido.
- **2026-05-04**: PRIORIDAD ABSOLUTA del PR1 — **NO ROMPER NUEVA VENTA**. El refactor a sub-traits es mecánico: mover bloques de código sin cambiar lógica, firmas, nombres, eventos ni vistas. Se establecen restricciones explícitas (lista de prohibiciones), estrategia de baselines (snapshot de tests + capturas + contrato Livewire), commits atómicos por trait con verificación en cada uno, y checklist exhaustivo de validación manual antes de mergear. Tag de pre-refactor para rollback express. Cero tolerancia a "mejoras incidentales": cualquier mejora detectada va a TODO y se hace en PR aparte. Si un commit introduce diferencia funcional, se revierte; no se "arregla" sobre la marcha.
