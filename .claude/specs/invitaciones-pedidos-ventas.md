# Invitaciones (Cortesías) en Pedidos y Ventas - Especificación

## Estado: EN PROGRESO (Fase 1 — 2026-05-19)

> Feature de "invitar" (regalar) items individuales o pedidos/ventas completos como cortesía. El total cobrable pasa a $0 pero el consumo se registra (stock + trazabilidad) para reportes. Diseñado para ser reutilizable en futuros canales (delivery).
>
> **Próximo paso**: `/sdd-apply invitaciones-pedidos-ventas` desde una rama nueva (a crear en próxima sesión).

---

## Contexto y Motivación

El operario necesita registrar **cortesías** (items o pedidos regalados al cliente) preservando trazabilidad para reportes y auditoría. Hoy esto se simula con un descuento del 100% pero pierde la intención del registro: queda confundido con descuentos comerciales.

Necesidad:

- Marcar **renglones individuales** del carrito como cortesía.
- Marcar el **pedido/venta completo** como cortesía (todos los items).
- Registrar **quién** invitó, **cuándo** y el **motivo** (obligatorio).
- Que el total cobrable de un item/pedido invitado sea $0 sin requerir formas de pago.
- Que la marca se preserve al **convertir un pedido en venta**.
- Que el stock se descuente normalmente (el item se consume).
- Que el feature sea reutilizable: hoy aplica a **Pedidos por Mostrador** y **Ventas**, mañana a **Pedidos Delivery** y otros canales — la lógica vive en un trait/concern del carrito.

---

## Principios de Diseño

1. **Lógica en trait reutilizable**: la mecánica de invitar/desinvitar (props, métodos, validaciones de permiso) vive en `WithInvitaciones` y se compone en cualquier componente que use carrito (`NuevoPedidoMostrador`, `NuevaVenta`, futuros canales).
2. **Modelo de datos espejado**: columnas equivalentes en `pedidos_mostrador` / `ventas` (cabecera) y `pedido_mostrador_detalle` / `ventas_detalle` (líneas). Esto evita una tabla polimórfica para reportes simples y respeta el patrón actual de "tablas paralelas" de Pedidos vs Ventas.
3. **Item invitado = item con `precio_unitario = 0` + flag**: NO inventamos un tipo nuevo de item. La invitación se materializa poniendo precio en 0 (lo que hace que `total` también sea 0 sin tocar `calcularVenta()`) y agregando metadatos (`es_invitacion`, `motivo`, `usuario`, `fecha`, `monto_invitado`). Sin alterar el flujo de cálculo existente.
4. **`monto_invitado` cacheado por línea**: para reportes, cada línea guarda el monto monetario regalado (= cantidad × precio_unitario_original). Sumar este campo da el total invitado de un período/sucursal sin recomputar precios.
5. **`total_invitado` cacheado en cabecera**: igual lógica, suma de los `monto_invitado` de las líneas. Útil en listados sin joinear detalles.
6. **Reversibilidad solo mientras el documento es editable**: el operario puede des-invitar mientras el pedido esté en BORRADOR o CONFIRMADO con `estado_pago=pendiente`. Una vez cobrado/convertido, queda fijo.
7. **Conversión Pedido → Venta preserva todo**: las columnas de invitación se copian 1:1, incluyendo `invitado_por_usuario_id` y `invitado_at` originales (el quién/cuándo de la invitación no cambia al convertir).
8. **Permisos granulares por canal y alcance**: dos permisos por canal (pedido completo / renglón). El trait usa un `permisoInvitacionPrefix` configurable por el componente host.
9. **Cortesía es aparte del motor de beneficios comerciales**: un item invitado se EXCLUYE totalmente del motor de promociones (comunes y especiales NxM/combo), cupones, descuento general y descuento por lista. No contribuye a thresholds ni a monto mínimo, ni recibe descuentos propios. La intención: cortesía y promo son canales de beneficio diferentes y no deben acumularse — el reporte separa "invitado" de "descontado por promo" sin mezclas.

---

## Requisitos Funcionales

### RF-01: Invitar un renglón individual desde el carrito (inline)

- En la vista de items del carrito, cada línea tiene un botón "🎁 Invitar" (icono de regalo).
- Click sin invitar: abre un mini-modal con **textarea de motivo (obligatorio, texto libre, máx 500 chars)** y botón "Invitar". Al confirmar:
  - Se setea `es_invitacion=true` en el item.
  - Se guarda `precio_unitario_original` (snapshot para revertir).
  - Se setea `precio_unitario=0`.
  - Se guarda `invitacion_motivo`, `invitado_por_usuario_id=auth()->id()`, `invitado_at=now()`, `monto_invitado=cantidad*precio_unitario_original`.
  - Se recalcula la venta.
- Click cuando ya está invitado: abre un mini-modal de confirmación con "Quitar invitación". Al confirmar:
  - Restaura `precio_unitario` desde `precio_unitario_original`.
  - Limpia los campos de invitación.
  - Recalcula.
- Si el usuario NO tiene permiso `{prefix}.invitar_renglon`: el botón aparece deshabilitado (no oculto) con tooltip "Sin permiso para invitar renglones".

### RF-02: Invitar todos los items desde el modal de cobro (selección masiva)

- En el modal de desglose (`_modal-pago-mixto.blade.php`), arriba del listado de pagos, agregar un **switch "Invitar todo el pedido"** (visualmente destacado, verde).
- Al activar: aparece **textarea de motivo obligatorio**. Botón "Confirmar invitación".
- Al confirmar:
  - Itera todos los items del carrito y marca cada uno como invitado (mismas columnas que RF-01), con el mismo motivo y usuario.
  - Setea cabecera: `es_invitacion_total=true`, `invitacion_motivo` (mismo motivo), `invitado_por_usuario_id`, `invitado_at`, `total_invitado` (suma).
  - Cierra el modal. El pedido se "cobra" sin agregar pagos (porque `total_final=0`).
- Si el usuario tiene `{prefix}.invitar_renglon` pero NO `{prefix}.invitar_pedido`: el switch NO aparece.

### RF-03: Indicadores visuales de invitación

- **Item invitado en el carrito**: badge verde "Invitado" + precio original en strike-through + "$0" al lado. Tooltip con motivo + "Invitado por {usuario_nombre} el {fecha}".
- **Resumen del pedido**: si hay items invitados, aparece línea adicional "Total invitado: $X" debajo del subtotal.
- **Cabecera pedido completamente invitado**: badge prominente "PEDIDO INVITADO" en el header del modal.
- **Listado de pedidos**: una columna o icono indicador que muestra si el pedido tiene invitación (total o parcial).

### RF-04: Persistencia de invitación al guardar pedido/venta

- `construirDetallesPedido()` y `construirDetallesVenta()` incluyen las nuevas claves para cada item: `es_invitacion`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `monto_invitado`, `precio_unitario_original`.
- `construirDataPedido()` y `construirDataVenta()` incluyen: `es_invitacion_total`, `total_invitado`, y los campos cabecera (`invitacion_motivo` si total, `invitado_por_usuario_id`, `invitado_at`).
- El service persiste las columnas tal cual al insertar/actualizar.

### RF-05: Stock se descuenta normalmente

- Un item invitado genera `MovimientoStock` igual que cualquier otro item (el bien fue consumido aunque no se cobró).
- Si futuro se quiere identificar "movimientos de stock por invitación" en reportes, se hace con join a `pedido_mostrador_detalle.es_invitacion`. No agregamos campo nuevo en `movimientos_stock`.

### RF-06: Conversión Pedido → Venta preserva invitación

- `PedidoMostradorService::convertirEnVenta()` copia las columnas de invitación tanto en cabecera como en cada detalle.
- El `invitado_por_usuario_id` original se preserva (NO se reemplaza por el usuario que convierte).
- La venta resultante tiene los mismos `total_invitado`, `es_invitacion_total`.

### RF-07: Reversibilidad

- Mientras el pedido esté **editable** (`estado_pedido` en `[BORRADOR]` o `[CONFIRMADO]` con `estado_pago=pendiente`), el operario puede des-invitar items o quitar la invitación total.
- Una vez cobrado (estado_pago `parcial`/`pagado`) o convertido en venta, la invitación queda fija y los botones quedan deshabilitados con tooltip "Pedido ya cobrado/convertido".
- En NuevaVenta: la invitación se decide antes de procesar la venta. Una vez procesada no se puede deshacer (igual que cualquier item).

### RF-08: Pedido invitado se procesa sin formas de pago

- Si `es_invitacion_total=true` y `total_final=0`, el flujo de cobro NO requiere pagos.
- En `procesarVentaConDesglose()` / `procesarCobroRapido()`: si total=0, omite la validación de "desglose completo" y de "caja abierta". El pedido pasa directo a `estado_pago=pagado` (porque no hay nada que cobrar).
- Si `es_invitacion_total=false` pero la suma de items NO invitados sigue siendo > 0, se requieren formas de pago para esos items normalmente.

### RF-09: Permisos verificados en backend

- Cada método de invitar/desinvitar en el trait empieza con `auth()->user()?->hasPermissionTo(...)`. Si falla, dispara `toast-error` y retorna sin modificar estado.
- La UI deshabilita botones, pero el backend es la verdad.

### RF-10: Reportes (out of scope para este PR)

- Este PR NO implementa reportes nuevos. Solo deja la data persistida para que reportes futuros puedan consultar:
  - Total invitado por sucursal/período: `SUM(total_invitado) FROM ventas WHERE fecha BETWEEN ?`.
  - Top usuarios que invitan: `GROUP BY invitado_por_usuario_id`.
  - Items más invitados: `SUM(monto_invitado) FROM ventas_detalle WHERE es_invitacion GROUP BY articulo_id`.
- Validar que estas queries sean factibles sin schemas adicionales.

### RF-11: Items invitados están EXCLUIDOS del motor de promociones, cupones y descuentos

- Al marcar un item como invitado (vía RF-01 o RF-02), todos sus campos de descuento se ponen y mantienen en 0:
  - `descuento_porcentaje = 0`, `descuento_monto = 0`
  - `descuento_promocion = 0`, `descuento_promocion_especial = 0`
  - `descuento_cupon = 0`, `descuento_lista = 0`
  - `ajuste_manual_*` se limpia (no aplica ajuste manual sobre un item de cortesía).
  - `tiene_promocion = false`, `_promociones_item` vacío.
- El motor de promociones (`WithCalculoVenta::calcularVenta()` y los métodos de aplicación de promos comunes / especiales / cupones / descuento general) debe **filtrar los items con `es_invitacion=true` antes de procesarlos**:
  - Un item invitado NO cuenta para el threshold de promos NxM (ej: "lleva 3, paga 2" sobre 5 items, 2 invitados → la promo se calcula solo sobre 3).
  - Un item invitado NO suma al subtotal que valida el "monto mínimo" del cupón.
  - Un item invitado NO se incluye en los combos.
  - El descuento general (%) se aplica solo al subtotal de items NO invitados.
- Si un item YA tenía promo aplicada y luego se marca como invitado, las promos previas se quitan (reset de `descuento_promocion*` y `_promociones_item`).
- Si un item invitado se DES-invita (RF-07), entra de nuevo al motor: `calcularVenta()` re-evalúa promos sobre él.
- Edge case: una promoción NxM que requiere 3 items para activarse y solo hay 5 items, 3 invitados → la promo NO se activa (porque solo quedan 2 no invitados).
- Edge case: cupón con monto mínimo $1000, pedido con 4 items por $400 c/u donde 2 están invitados → subtotal cobrable $800 → cupón NO aplica.
- En `convertirEnVenta()`: los items invitados (y sus invariantes de descuentos=0) se preservan tal cual; el motor de promociones de la venta no se re-ejecuta sobre ellos.

---

## Modelo de Datos

### Tablas modificadas

#### `pedidos_mostrador` — Cambios
- Agregar: `es_invitacion_total` (boolean, default false) AFTER `total_final`
- Agregar: `invitacion_motivo` (varchar(500), null) AFTER `es_invitacion_total`
- Agregar: `invitado_por_usuario_id` (bigint unsigned, null, FK a `config.users.id`) AFTER `invitacion_motivo`
- Agregar: `invitado_at` (timestamp, null) AFTER `invitado_por_usuario_id`
- Agregar: `total_invitado` (decimal(15,2), default 0) AFTER `invitado_at`
- Índice nuevo: `idx_es_invitacion_total` en `(es_invitacion_total, fecha)` para reportes.

#### `pedido_mostrador_detalle` — Cambios
- Agregar: `es_invitacion` (boolean, default false) AFTER `total`
- Agregar: `invitacion_motivo` (varchar(500), null) AFTER `es_invitacion`
- Agregar: `invitado_por_usuario_id` (bigint unsigned, null) AFTER `invitacion_motivo`
- Agregar: `invitado_at` (timestamp, null) AFTER `invitado_por_usuario_id`
- Agregar: `monto_invitado` (decimal(15,2), default 0) AFTER `invitado_at`
- Agregar: `precio_unitario_original` (decimal(15,2), null) AFTER `monto_invitado` — snapshot para revertir invitación.
- Índice nuevo: `idx_es_invitacion` en `(es_invitacion)` para reportes.

#### `ventas` — Cambios
- Idéntico a `pedidos_mostrador`: `es_invitacion_total`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `total_invitado`.

#### `ventas_detalle` — Cambios
- Idéntico a `pedido_mostrador_detalle`: `es_invitacion`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `monto_invitado`, `precio_unitario_original`.

### Tablas nuevas

Ninguna. Aprovechamos las tablas existentes.

---

## Pantallas UI

### Componentes Livewire afectados

- **`App\Livewire\Pedidos\NuevoPedidoMostrador`**: incorpora el trait `WithInvitaciones` con `$permisoInvitacionPrefix = 'func.pedidos_mostrador'`.
- **`App\Livewire\Ventas\NuevaVenta`**: incorpora el trait con `$permisoInvitacionPrefix = 'func.ventas'`.
- **`App\Livewire\Pedidos\PedidosMostrador`**: agrega columna/badge en listado para indicar invitación. (Solo lectura, sin lógica de toggle).

### Vistas modificadas

- `resources/views/livewire/carrito/_detalle-items.blade.php`: botón "🎁 Invitar" inline en cada item + indicador visual del estado.
- `resources/views/livewire/carrito/_modal-pago-mixto.blade.php`: switch "Invitar pedido completo" + textarea de motivo + cambio del botón de confirmación.
- `resources/views/livewire/carrito/_resumen-totales.blade.php` (si existe): mostrar "Total invitado: $X" cuando aplica.
- `resources/views/livewire/pedidos/pedidos-mostrador.blade.php`: badge "Invitado" en filas/cards.

### Mini-modales nuevos (componentes parciales Blade)

- `resources/views/livewire/carrito/_modal-invitar-item.blade.php`: motivo + confirmar/cancelar.
- `resources/views/livewire/carrito/_modal-desinvitar-item.blade.php`: confirmación.

---

## Servicios

### Trait nuevo: `App\Livewire\Concerns\Carrito\WithInvitaciones`

Archivo: `app/Livewire/Concerns/Carrito/WithInvitaciones.php`.

**Props públicas**:

- `public bool $invitarTodo = false;` — estado del switch en el modal de cobro.
- `public string $motivoInvitacionTotal = '';` — motivo cuando se invita todo.
- `public bool $mostrarModalInvitarItem = false;` — visibility del mini-modal.
- `public ?int $invitarItemIndex = null;` — índice del item a invitar.
- `public string $invitarItemMotivo = '';` — motivo del item a invitar.
- `public bool $mostrarModalDesinvitarItem = false;`
- `public ?int $desinvitarItemIndex = null;`

**Métodos públicos** (todos chequean permiso):

- `abrirInvitarItem(int $index): void` — abre mini-modal para motivo.
- `confirmarInvitarItem(): void` — aplica la invitación al item.
- `abrirDesinvitarItem(int $index): void` — abre confirmación.
- `confirmarDesinvitarItem(): void` — quita la invitación, restaura precio.
- `toggleInvitarTodo(): void` — activa/desactiva el switch.
- `confirmarInvitarTodo(): void` — aplica invitación a todos los items + cabecera.

**Métodos protegidos / helpers**:

- `protected function getPermisoInvitacionPrefix(): string` — el componente host lo override (ej: `'func.pedidos_mostrador'` o `'func.ventas'`).
- `protected function puedeInvitarPedido(): bool`
- `protected function puedeInvitarRenglon(): bool`
- `protected function marcarItemComoInvitado(int $index, string $motivo): void` — núcleo: setea `es_invitacion=true`, guarda `precio_unitario_original`, pone `precio_unitario=0`, **resetea todos los descuentos** (`descuento_*`, `ajuste_manual_*`, `tiene_promocion=false`, `_promociones_item=[]`), guarda metadatos.
- `protected function desmarcarItem(int $index): void` — restaura `precio_unitario` desde el snapshot y limpia metadatos. Llama a `calcularVenta()` para que el motor re-evalúe promos.
- `protected function recalcularTotalInvitado(): void` — suma `monto_invitado` de los items.

**Cómo se integra**:

- El componente host hace `use WithInvitaciones;` y opcionalmente override `getPermisoInvitacionPrefix()`.
- En `construirDetallesPedido()`/`construirDetallesVenta()` se llaman las claves del item.
- En `construirDataPedido()`/`construirDataVenta()` se incluye la cabecera.

### Cambios en services existentes

#### `PedidoMostradorService` (`app/Services/Pedidos/PedidoMostradorService.php`)

- `crearPedido()`: el array `$data` ahora puede contener las columnas de invitación cabecera. El array `$detalles` puede contener las columnas por línea. Persistir tal cual.
- `actualizarPedido()`: lo mismo.
- `convertirEnVenta()` (línea 614-713): copiar las columnas de invitación al crear la venta. En el array `$dataVenta`: `es_invitacion_total`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `total_invitado`. En `mapearDetalleAArrayVenta()` (línea 1263): copiar `es_invitacion`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `monto_invitado`, `precio_unitario_original`.

#### `VentaService` (`app/Services/VentaService.php`)

- `crearVenta()`: el array `$data` puede contener las columnas cabecera. El array `$detalles` lo mismo. Persistir.
- `crearDetalleVenta()`: persistir las columnas extra.

#### `procesarCobroRapido()` / `procesarVentaConDesglose()` / `confirmarSinCobrar()`

- Antes de validar caja y desglose: si `total_final == 0` (porque todo está invitado), saltar las validaciones de caja/desglose y persistir el pedido como `estado_pago=pagado` directamente (sin crear `PedidoMostradorPago`).
- Edge case: pedido parcial donde algunos items están invitados pero el resto sí requiere cobro → flujo normal con desglose por el `total_final` reducido.

#### Motor de cálculo y promociones (`WithCalculoVenta`, `WithCupones`, `WithDescuentos`)

- En `WithCalculoVenta::calcularVenta()` y todos los métodos auxiliares que iteran sobre `$this->items` para aplicar beneficios comerciales:
  - **Filtrar items invitados antes de procesar promos/cupones/descuentos**: usar `collect($this->items)->reject(fn($i) => $i['es_invitacion'] ?? false)` (o equivalente) cuando se calculen thresholds NxM, combos, sumas para monto mínimo de cupón, base del descuento general.
  - Los items invitados pasan por el cálculo solo para totalizar su `monto_invitado` (que se calcula como `cantidad * precio_unitario_original`); no afectan a otros items.
- En `WithDescuentos::aplicarDescuentoGeneral()` (o equivalente): el descuento % se aplica sobre el subtotal de items NO invitados. Los items invitados quedan intactos con `precio_unitario=0` y todos sus descuentos en 0.
- En `WithCupones::validarMontoMinimo()` (o equivalente): el subtotal contra el cual valida el cupón excluye los items invitados.
- En el motor de promociones especiales (NxM, combos): los items invitados se omiten al armar las "agrupaciones de promo".
- Si la implementación actual del motor está dispersa, agrupar en un único helper `getItemsParaMotorBeneficios(): array` que retorne solo los items NO invitados. Refactorizar los puntos de uso para llamar a este helper en lugar de `$this->items` directo.

---

## Migraciones Necesarias

Multi-tenant (iteran TODOS los comercios, SQL raw con prefijo, try/catch por comercio según `.claude/docs/workflows-migraciones.md`). Regenerar `database/sql/tenant_tables.sql` después.

1. `add_invitacion_columns_to_pedidos_mostrador_tables` — agrega las columnas a `pedidos_mostrador` y `pedido_mostrador_detalle`.
2. `add_invitacion_columns_to_ventas_tables` — agrega las columnas a `ventas` y `ventas_detalle`.
3. Una sola migración compartida que las haga las 4 tablas en bloque también es válida — definir en `/sdd-apply`.

---

## Permisos

Se crean vía migración (siguiendo el patrón de `pedidos_mostrador.cobrar/cancelar` que también viven en migración, no en `PermisosFuncionalesSeeder.php`). La migración:

1. Inserta en `pymes.permisos_funcionales` los 4 permisos.
2. Llama `PermisoFuncional::syncAllToSpatie()` para crearlos en `pymes.permissions`.
3. Itera los tenants existentes y asigna los 4 permisos a los roles `Administrador` y `Super Administrador` (idempotente).

Permisos:

- `func.pedidos_mostrador.invitar_pedido` — "Invitar pedido completo (Mostrador)"
- `func.pedidos_mostrador.invitar_renglon` — "Invitar renglón de pedido (Mostrador)"
- `func.ventas.invitar_venta` — "Invitar venta completa"
- `func.ventas.invitar_renglon` — "Invitar renglón de venta"

`ProvisionComercioCommand::seedRolesYPermisos()` NO requiere cambios: ya itera automáticamente todos los `func.*` y se los asigna a Administrador / Super Administrador al provisionar comercios nuevos.

---

## Traducciones

Claves nuevas en `lang/{es,en,pt}.json`:

| Clave (es) | en | pt |
|---|---|---|
| `Invitar` | `Comp` | `Cortesia` |
| `Invitar renglón` | `Comp item` | `Cortesia item` |
| `Invitar pedido completo` | `Comp full order` | `Cortesia pedido completo` |
| `Invitar venta completa` | `Comp full sale` | `Cortesia venda completa` |
| `Motivo de la invitación` | `Comp reason` | `Motivo da cortesia` |
| `El motivo es obligatorio` | `Reason is required` | `O motivo e obrigatorio` |
| `Renglón invitado` | `Item comped` | `Item de cortesia` |
| `Pedido invitado` | `Order comped` | `Pedido de cortesia` |
| `Venta invitada` | `Sale comped` | `Venda de cortesia` |
| `Pedido invitado por :usuario el :fecha` | `Comped by :usuario on :fecha` | `Cortesia por :usuario em :fecha` |
| `Quitar invitación` | `Remove comp` | `Remover cortesia` |
| `Total invitado` | `Comp total` | `Total cortesia` |
| `No tenés permiso para invitar renglones` | `Not allowed to comp items` | `Sem permissao para itens de cortesia` |
| `No tenés permiso para invitar el pedido` | `Not allowed to comp full order` | `Sem permissao para pedido de cortesia` |

(Las claves se ordenan alfabéticamente en cada archivo usando `/traducir`).

---

## Criterios de Aceptación

- [ ] **CA-01**: En `NuevoPedidoMostrador`, click en el botón "🎁" de un item abre el mini-modal de motivo. Tras ingresar motivo y confirmar, el item queda con `precio_unitario=0`, badge "Invitado", y los totales del pedido se recalculan correctamente.
- [ ] **CA-02**: El motivo es obligatorio: el botón "Invitar" del mini-modal queda deshabilitado mientras el textarea esté vacío o solo whitespace.
- [ ] **CA-03**: En el modal de desglose (cobro), aparece el switch "Invitar pedido completo". Al activarlo + ingresar motivo + confirmar, todos los items quedan invitados, la cabecera tiene `es_invitacion_total=true`, y el pedido se persiste con `estado_pago=pagado` sin agregar pagos.
- [ ] **CA-04**: Sin el permiso `func.pedidos_mostrador.invitar_renglon`, el botón "🎁" del item aparece deshabilitado y al click no hace nada. Backend rechaza la operación si se llama via `wire:click`.
- [ ] **CA-05**: Sin `func.pedidos_mostrador.invitar_pedido`, el switch "Invitar pedido completo" no aparece en el modal.
- [ ] **CA-06**: Pedido con 2 items invitados + 1 item cobrado normal → `total_invitado` cabecera = suma de los 2 invitados, `total_final` = solo el item cobrado. El cobro pide formas de pago solo por el `total_final`.
- [ ] **CA-07**: Click en "🎁" sobre un item ya invitado abre el mini-modal de "Quitar invitación". Al confirmar, el item recupera el `precio_unitario_original` y se limpian los campos de invitación.
- [ ] **CA-08**: Un pedido con invitación se convierte en venta. La venta resultante tiene los mismos `es_invitacion_total`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `total_invitado` que el pedido. Cada `ventas_detalle` preserva su `es_invitacion` y `monto_invitado` del `pedido_mostrador_detalle` original.
- [ ] **CA-09**: Smoke + integration tests existentes de Pedidos y Ventas siguen pasando (`php artisan test --filter=Pedido` y `--filter=SmokeVentas`).
- [ ] **CA-10**: Tests nuevos cubren: invitar item (con permiso/sin permiso), invitar todo, des-invitar, persistir, conversión preserva invitación, pedido total invitado se procesa sin pagos.
- [ ] **CA-11**: Pint pasa en todos los archivos modificados.
- [ ] **CA-12**: `database/sql/tenant_tables.sql` regenerado con las nuevas columnas.
- [ ] **CA-13** (Promos x invitación): pedido con 5 items donde 2 están invitados y hay una promo "lleva 3 paga 2" activa para ese artículo → la promo evalúa los 3 items NO invitados, aplica al patrón. Los 2 invitados quedan con `descuento_promocion=0`, `tiene_promocion=false`. Las 3 unidades no invitadas reciben el beneficio normal.
- [ ] **CA-14** (Cupón con monto mínimo + invitación): pedido con 4 items por $400 c/u (subtotal $1600), 2 marcados como invitados (subtotal cobrable $800), cupón con monto mínimo $1000 → cupón NO aplica (el motor calcula contra el subtotal sin invitados).
- [ ] **CA-15** (Descuento general + invitación): pedido con 3 items, descuento general 10%, uno marcado como invitado → el 10% se aplica solo sobre los 2 items NO invitados; el item invitado queda con todos sus campos de descuento en 0.
- [ ] **CA-16** (Reset al invitar): item que ya tenía promo aplicada (con `descuento_promocion > 0` y `tiene_promocion=true`) se marca como invitado → todos sus campos de descuento se resetean a 0, `tiene_promocion=false`, `_promociones_item=[]`. Al des-invitarlo, las promos se re-evalúan (`calcularVenta()` corre de nuevo).

---

## Plan de Implementación

### Fase 1: Modelo de datos + Permisos + Traducciones [COMPLETO]

1. Crear migración `add_invitacion_columns_to_pedidos_mostrador_tables` (tenant, itera comercios, regenera SQL).
2. Crear migración `add_invitacion_columns_to_ventas_tables` (tenant).
3. Actualizar `$fillable` y `$casts` en `PedidoMostrador`, `PedidoMostradorDetalle`, `Venta`, `VentaDetalle`.
4. Regenerar `database/sql/tenant_tables.sql`.
5. Agregar 4 permisos a `PermisosFuncionalesSeeder.php`.
6. Actualizar `ProvisionComercioCommand::seedRolesYPermisos()` para asignarlos a Administrador / Super Administrador.
7. Agregar traducciones (es/en/pt) usando `/traducir`.
8. Correr migración local. Tests existentes deben seguir pasando.

### Fase 2: Trait `WithInvitaciones` [COMPLETO]

1. Crear `app/Livewire/Concerns/Carrito/WithInvitaciones.php` con props y métodos.
2. En `marcarItemComoInvitado()` y `desmarcarItem()`: aplicar el reset de descuentos y la limpieza de `_promociones_item` / `tiene_promocion` (RF-11).
3. Tests unitarios del trait: invitar item, des-invitar item, invitar todo, verificación de permiso (usar `prepararComponente()` pattern de `NuevaVentaCambioFPTest.php`).
4. Test de reset: invitar un item que ya tenía promo aplicada deja todos sus campos de descuento en 0.
5. Smoke test: trait no rompe los componentes que lo componen aún sin usarlo.

### Fase 3: Motor de promociones/cupones/descuentos excluye invitados [COMPLETO]

1. Explorar exhaustivamente `WithCalculoVenta`, `WithCupones`, `WithDescuentos` y los métodos auxiliares en `NuevaVenta` que aplican promos comunes/especiales. Identificar TODOS los puntos donde se itera `$this->items` para beneficios.
2. Crear helper `getItemsParaMotorBeneficios()` (en `WithCalculoVenta` o trait dedicado) que retorna solo items con `es_invitacion=false`.
3. Refactorizar los puntos de uso identificados para llamar al helper en lugar de `$this->items` directo.
4. Tests focales (siguiendo el pattern de `NuevaVentaCambioFPTest`):
   - Promo NxM no se activa si los items no invitados no alcanzan el threshold (CA-13).
   - Cupón monto mínimo: validar contra subtotal sin invitados (CA-14).
   - Descuento general %: aplica solo sobre items no invitados (CA-15).

### Fase 4: Integración en `NuevoPedidoMostrador` [COMPLETO]

1. Componer el trait `WithInvitaciones` en el componente.
2. Override `getPermisoInvitacionPrefix()` → `'func.pedidos_mostrador'`.
3. Modificar `construirDetallesPedido()` y `construirDataPedido()` para incluir las nuevas columnas.
4. Modificar `procesarCobroRapido()`, `procesarVentaConDesglose()` y `confirmarSinCobrar()` para el camino "total=0 saltar validaciones".
5. Test de integración: crear pedido con item invitado, verificar persistencia.
6. Test: pedido completamente invitado se procesa sin formas de pago.

### Fase 5: UI inline (botón en cada item) [COMPLETO]

1. Modificar `_detalle-items.blade.php`: agregar botón "🎁" en la línea de controles.
2. Crear `_modal-invitar-item.blade.php` (mini-modal de motivo).
3. Crear `_modal-desinvitar-item.blade.php` (confirmación).
4. Modificar la vista para mostrar el indicador visual del item invitado (badge + strike-through).
5. Smoke visual: abrir editor, ver botones, ejecutar flujo en navegador.

### Fase 6: UI switch en modal de cobro [PENDIENTE]

1. Modificar `_modal-pago-mixto.blade.php`: agregar switch "Invitar pedido completo" arriba del listado de pagos.
2. Cuando switch activo: mostrar textarea de motivo, ocultar selector de FPs, cambiar texto del botón principal a "Confirmar invitación".
3. Recálculo en vivo: al activar el switch, el "Total a cobrar" debe mostrar $0.
4. Test de integración: ciclo completo desde activar switch hasta verificar BD.

### Fase 7: Integración en `NuevaVenta` [PENDIENTE]

1. Componer el trait en `NuevaVenta`.
2. Override `getPermisoInvitacionPrefix()` → `'func.ventas'`.
3. Modificar `construirDetallesVenta()` y `construirDataVenta()`.
4. Modificar `procesarVentaConDesglose()` (versión NuevaVenta) para "total=0 saltar pagos".
5. Tests de integración.

### Fase 8: Conversión Pedido → Venta preserva invitación [PENDIENTE]

1. Modificar `PedidoMostradorService::convertirEnVenta()` y `mapearDetalleAArrayVenta()` para copiar las nuevas columnas.
2. Test específico: pedido con invitación parcial + total. Convertir. Verificar venta resultante.

### Fase 9: Indicadores en listados [PENDIENTE]

1. Modificar `pedidos-mostrador.blade.php`: badge "Invitado" en filas/cards si `es_invitacion_total` o `total_invitado > 0`.
2. Modificar el listado de ventas si existe vista similar.

### Fase 10: Verificación final + Docs [PENDIENTE]

1. Correr `php vendor/bin/pint --test` en todos los archivos modificados.
2. Correr suite completa de tests: `php artisan test --filter=Pedido` y `--filter=Venta`.
3. Smoke manual end-to-end en navegador.
4. Invocar `@docs-sync` para actualizar `docs/manual-usuario.md` y `docs/ai-knowledge-base.md`.
5. Crear PR.

---

## Notas y Decisiones

- **2026-05-18**: Decisión scope = Pedidos + Ventas con trait reutilizable, pensando en delivery a futuro. Confirmado por usuario.
- **2026-05-18**: Granularidad = línea completa (sin desdoblar cantidad). Si el operario quiere invitar 1 de 3 unidades, divide la línea manualmente. Esto evita complejidad del modelo dual cantidad_cobrada / cantidad_invitada.
- **2026-05-18**: UI = botón inline en cada item + switch masivo en el modal de cobro (ambos). Confirmado por usuario.
- **2026-05-18**: Permisos = 4 separados (2 por canal × 2 alcances). Más granular, alinea con la política de permisos del proyecto.
- **2026-05-18**: Motivo obligatorio, texto libre. Si en futuro queremos catálogo de motivos (cliente, evento, error de cocina, cortesía gerencia), agregamos una tabla `motivos_invitacion` después sin romper compatibilidad — el campo `invitacion_motivo` queda con el texto resuelto.
- **2026-05-18**: Conversión Pedido → Venta hereda invitación. El `invitado_por_usuario_id` original se preserva (no se reemplaza por quien convierte).
- **2026-05-18**: Reversibilidad solo mientras editable. Después fija para trazabilidad.
- **2026-05-18**: Reportes NO se implementan en este PR — solo persistimos la data. Reportes futuros consultan las columnas directamente o vía sub-query agregada.
- **2026-05-18**: `precio_unitario_original` en el detalle es snapshot para revertir. Si el operario invita un item, des-invita y vuelve a invitar, vuelve al precio original (no al "precio cuando invitó la última vez" — que sería igual de todos modos porque entre toggles no cambia el catálogo).
- **2026-05-18**: Stock se descuenta normalmente. No agregamos campo nuevo en `movimientos_stock` — los reportes joinen con `pedido_mostrador_detalle.es_invitacion`.
- **2026-05-18**: Items invitados se EXCLUYEN del motor de promociones, cupones, descuento general y descuento por lista (RF-11). Cortesía y promo son canales de beneficio diferentes y no se acumulan. Confirmado por usuario. Implica modificar `WithCalculoVenta`, `WithCupones`, `WithDescuentos` para filtrar items invitados; refactorizar via helper `getItemsParaMotorBeneficios()`. Test foco: CA-13, CA-14, CA-15, CA-16.
- **OPEN**: ¿En el listado de pedidos/ventas conviene una columna "es_invitacion" filtrable, o solo un badge? → Decidir en Fase 8.
- **OPEN**: ¿El badge "PEDIDO INVITADO" en el header del editor debe ser solo info o también clickeable para ver detalles? → Decidir en Fase 5.
