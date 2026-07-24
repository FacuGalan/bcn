# Multi-pago consistente + desglose delivery en panel + fix precio táctil - Especificación

## Estado: EN REVISIÓN

> Spec creado 2026-07-24 a partir del bug reportado en la tienda (promo 5%
> efectivo + descuento 10% efectivo con pago dividido). Cubre 4 sub-features:
> RF-01/02 (validación de promos/listas por FP con multi-pago), RF-03 (base
> del ajuste FP bienes-primero con tope), RF-04 (desglose visible de envío en
> panel delivery), RF-05 (fix precio fallback en catálogo táctil).
> Esperando aprobación del usuario para `/sdd-apply`.

---

## Contexto y Motivación

Caso real reproducido en la tienda (aceite $1000, envío $1000, promo 5%
"solo efectivo" sobre el artículo, FP efectivo con descuento 10%):

- **Con 1 FP (efectivo)**: $1000 − $50 (promo) − $95 (10% s/$950) + $1000
  envío = **$1855** ✔ correcto.
- **Con 2 FP (efectivo $1000 + otra)**: la promo 5% aplica igual (no
  debería: la segunda FP no la habilita) y el descuento de efectivo da
  **−$48,72**, un número inexplicable para el consumidor.

Causas raíz identificadas en el código:

1. **`pagos[0]` como FP del carrito**: `CotizacionController:132` y
   `PedidoTiendaService:120-121` pasan la FP del PRIMER pago al motor como
   si fuera la FP de toda la operación → promos y listas condicionadas por
   FP aplican al 100% según el ORDEN en que la tienda mande los pagos.
2. **Prorrateo proporcional del envío** (`CotizadorCarritoTienda::
   desglosarPagos:307-331`, espejo de `NuevoPedidoDelivery::
   baseAjustePagoDesglose:1177`): la base del ajuste de cada pago es
   `monto × (total−envío)/total`. Matemáticamente consistente (D17) pero
   ininteligible: $1000 en efectivo sobre total $1950 → base $487,18 →
   −$48,72. Nadie piensa "mi billete paga 48,7% de aceite y 51,3% de envío".
3. Las **promos** no tienen el equivalente de la regla que los **cupones**
   ya tienen: `CuponService::validarFormasPagoCupon` exige que TODAS las FP
   del desglose estén permitidas (anti-abuso, comentario en
   `CuponService.php:323-324`), revalidado por el panel en
   `WithPagosDesglose:1990-1999`.

Además se aprovecha el spec para dos deudas de UX/bug del panel:

4. El panel delivery no muestra el envío como línea propia del desglose
   monetario (está mezclado dentro del subtotal vía renglón-concepto D17), y
   el modal de pago desglosado no explica sobre qué base calcula el
   descuento/recargo de cada FP.
5. El catálogo táctil (mostrador y delivery) muestra el precio crudo
   `articulos.precio_base` (fallback global) en la grilla, pero al agregar
   usa el precio real (`obtenerPrecioConLista`: override por sucursal +
   lista por forma de venta + promos) → divergencia visual sistemática en
   sucursales con precio propio o listas Delivery/Take-away.

---

## Principios de Diseño

1. **El core calcula, la tienda muestra** (regla de oro del contrato v1):
   todos los cambios de cálculo viven en `bcn_pymes`; `bcn-tienda` no debería
   requerir cambios de código (los totales que muestra salen de `cotizar`).
2. **Paridad panel ↔ tienda**: la MISMA operación (mismos ítems, mismas FP,
   mismos montos) debe dar el MISMO total en NuevoPedidoDelivery y en la
   tienda. Toda regla nueva se implementa en UNA fuente compartida.
3. **Precedente cupones**: la validación de promos por FP con pago dividido
   copia la semántica ya decidida para cupones (todas las FP deben estar
   permitidas; pago mixto con FP no incentivada = abuso).
4. **Orden-independencia**: el resultado no puede depender del orden en que
   se declaren los pagos.
5. **Sin cambios de contrato estructurales**: el shape de la respuesta de
   `cotizar` no cambia (cambia la semántica del cálculo). Se documenta en
   `docs/api-v1-delivery.md` como nota de comportamiento.
6. **Sin migraciones**: todo es cálculo + UI. Ningún cambio de esquema.

---

## Requisitos Funcionales

### RF-01: Promociones condicionadas por FP validadas contra TODAS las FP del pago
- El contexto del motor (`WithCalculoVenta`) pasa de `forma_pago_id` (int)
  a soportar además `formas_pago_ids` (array) — con 1 FP el comportamiento
  es idéntico al actual.
- Una promo con restricción de FP (`formas_pago_ids` de la promo) aplica
  **solo si TODAS las FP declaradas** están incluidas. Si alguna no está,
  la promo NO aplica (cae entera, no prorratea).
- Aplica a promos comunes (`WithCalculoVenta:803-808`) y especiales
  (`WithCalculoVenta:1452-1460`).
- Alcances: cotizador tienda (`CotizadorCarritoTienda`), alta de pedido
  tienda (`PedidoTiendaService`), y panel (los 3 hosts de
  `WithPagosDesglose` al modificar el desglose — ver RF-03 nota de
  recálculo).
- En el panel, al agregar/quitar pagos del desglose se **recalcula la
  venta** con el set de FPs vigente (igual que hoy se revalida el cupón),
  para que el operador vea caer/entrar promos en vivo.

### RF-02: Listas de precios condicionadas por FP — misma regla
- `ListaPrecioCondicion::evaluar` / `ListaPrecio::validarCondiciones`
  reciben el set completo: una condición de FP se cumple solo si TODAS las
  FP declaradas la satisfacen.
- Si la lista condicionada no aplica, el resolutor
  (`buscarListaAplicable`) cae a la siguiente lista aplicable (regla
  actual de especificidad, sin cambios).

### RF-03: Base del ajuste por FP = asignación bienes-primero con tope (reemplaza prorrateo proporcional)
- **Regla nueva** (reemplaza D17 "exclusión proporcional" SOLO para la base
  del ajuste; el envío sigue excluido y sigue siendo renglón-concepto):
  los BIENES (total_final post-promos/cupón/desc. general, SIN envío) se
  asignan a los pagos y el ajuste % de cada pago se calcula sobre la
  porción de bienes asignada.
- **Asignación greedy orden-independiente**: se ordenan los pagos por
  `ajuste_porcentaje` ASCENDENTE (mayor descuento primero; recargo al
  final) y se les asigna bienes hasta el tope de su monto:
  `base_i = min(monto_i, bienes_restantes)`. Garantiza
  `Σ bases = min(Σ montos, bienes)` y `base_i ≤ monto_i`.
  - Pro-cliente y determinística: maximiza el descuento y minimiza el
    recargo, y el resultado no depende del orden de declaración.
  - Caso usuario: bienes $950 (promo aplicada, ambas FP habilitadas),
    efectivo $1000 → base = min(1000, 950) = $950 → **−$95** ✔.
  - Sin promo (RF-01 la tira): bienes $1000, efectivo $1000 → base $1000
    → **−$100**; total = 2000 − 100 = **$1900**.
- **Fuente única**: la asignación se implementa en un helper puro
  compartido (`App\Services\Pedidos\AsignadorBasesAjustePagos`, sin estado,
  método estático `asignar(array $pagos, float $bienes): array`) usado por:
  - `CotizadorCarritoTienda::desglosarPagos` (tienda), y
  - `WithPagosDesglose` vía el hook de delivery (panel): al agregar/quitar
    un pago se re-asignan las bases de TODOS los pagos del desglose (hoy
    cada pago congela su base al agregarse).
- Alcance: SOLO donde hay envío como valor fijo (delivery/tienda). En
  NuevaVenta y NuevoPedidoMostrador no hay envío → base = monto del pago
  (comportamiento actual, sin cambios).
- Con 1 solo pago que cubre todo, la regla da `base = bienes` → mismo
  resultado que hoy (sin regresión del caso single-FP).

### RF-04: Desglose visible de envío en el panel delivery
- **Alta/edición** (`NuevoPedidoDelivery`): el bloque de totales muestra
  `Subtotal artículos` (bienes sin envío) + `Envío` (fijo, con indicador
  si fue pisado a mano) + `Total`.
- **Detalle del pedido** (`pedidos-delivery.blade.php:1694-1735`): agregar
  fila `Envío` al `<dl>` de totales, mostrando `costo_envio` de cabecera y
  presentando el subtotal de bienes como `subtotal − costo_envio`. Solo
  presentación: la persistencia (renglón-concepto D17) no se toca.
- **Modal de pago desglosado** (`WithPagosDesglose` blade): por cada pago
  con ajuste ≠ 0, mostrar la base: p.ej. "10% s/ $950 (artículos)" en vez
  del monto solo. Además una línea informativa "El envío ($X) no recibe
  descuentos ni recargos".
- La tienda ya muestra el envío separado; verificar que los textos del
  checkout de la tienda expliquen el ajuste igual que el panel (si hace
  falta texto, es cambio menor en `bcn-tienda`, sin cálculo).

### RF-05: Catálogo táctil muestra el precio real (fix bug)
- `cargarCatalogoTactil()` de `NuevoPedidoMostrador` (~410-458) y
  `NuevoPedidoDelivery` (~533-590) reemplazan `(float) $a->precio_base`
  por el precio efectivo resuelto con la MISMA cadena que usa el alta:
  override por sucursal (`obtenerPrecioBaseEfectivo`) + lista activa
  (`obtenerPrecioConLista`) — una pasada en `mount` sobre el snapshot
  (opción B de la exploración).
- Si cambia la lista activa durante la sesión (cambio de forma de
  venta/FP que activa lista condicionada), el snapshot se refresca (mismo
  trigger que ya recalcula la venta).
- Resultado: "lo que ves en la grilla es lo que se agrega".

---

## Modelo de Datos

Sin cambios. No hay tablas nuevas ni modificadas.

---

## Pantallas UI

### NuevoPedidoDelivery (`/pedidos/delivery/nuevo`) — existente
- Totales con `Subtotal artículos` / `Envío` / `Total` (RF-04).
- Recálculo de promos al modificar el desglose de pagos (RF-01).
- Grilla táctil con precio real (RF-05).

### PedidosDelivery (`/pedidos/delivery`) — existente
- Fila `Envío` en el desglose de totales del detalle (RF-04).

### NuevoPedidoMostrador (`/pedidos/mostrador/nuevo`) — existente
- Grilla táctil con precio real (RF-05). Sin cambios de ajuste FP (no hay
  envío).

### Modal pago desglosado (blade compartido de `WithPagosDesglose`) — existente
- Leyenda de base del ajuste por pago + línea "el envío no recibe
  ajustes" cuando hay envío (RF-04).

---

## Servicios

### `AsignadorBasesAjustePagos` — `app/Services/Pedidos/AsignadorBasesAjustePagos.php` (NUEVO)
- `asignar(array $pagos, float $bienes): array` — pagos = lista de
  `['monto' => float, 'ajuste_porcentaje' => float, ...]`; devuelve la misma
  lista con `base_ajuste` asignada (greedy por ajuste ascendente, tope en
  monto, Σ bases ≤ bienes). Puro, sin BD, testeable unitario.

### `CotizadorCarritoTienda` — modificado
- `cotizar(...)`: acepta `array $formasPagoIds` (además del singular, que
  queda como alias con 1 elemento) y lo pasa al contexto del motor.
- `desglosarPagos(...)`: usa `AsignadorBasesAjustePagos` en lugar del
  `factorBase` proporcional.

### `PedidoTiendaService` / `CotizacionController` — modificados
- Dejan de pasar `pagos[0].forma_pago_id`: pasan TODAS las FP del desglose
  al cotizador.

### `WithCalculoVenta` / `WithPagosDesglose` — modificados
- Contexto de promos/listas con `formas_pago_ids` (RF-01/02).
- Recálculo de venta + re-asignación de bases al mutar el desglose (panel).

---

## Migraciones Necesarias

Ninguna.

---

## Traducciones

Claves nuevas (es/en/pt, orden alfabético):
| Clave (es) | en | pt |
|------------|----|----|
| `Subtotal artículos` | Items subtotal | Subtotal de itens |
| `Envío` (si no existe) | Shipping | Entrega |
| `:pct% s/ :monto (artículos)` | :pct% on :monto (items) | :pct% s/ :monto (itens) |
| `El envío (:monto) no recibe descuentos ni recargos` | Shipping (:monto) gets no discounts or surcharges | A entrega (:monto) não recebe descontos nem acréscimos |
| (las definitivas se ajustan en implementación) | | |

---

## Criterios de Aceptación

Escenario base: artículo $1000, envío $1000, promo 5% restringida a
efectivo, efectivo con ajuste −10%, segunda FP sin ajuste y fuera de la promo.

- [ ] **1 FP efectivo (tienda y panel)**: total $1855 (sin regresión).
- [ ] **2 FP, efectivo $1000 + resto otra**: promo NO aplica; ajuste
      efectivo = −$100 (base $1000 = min(monto, bienes)); total $1900.
      Mismo resultado en tienda y en panel delivery.
- [ ] **Orden invertido de los pagos**: mismo resultado exacto (orden-
      independencia).
- [ ] **2 FP ambas habilitadas en la promo**: promo aplica (−$50); ajuste
      efectivo = −$95 (base $950); paridad tienda/panel.
- [ ] **Pago efectivo $1500 (cubre bienes y parte del envío)**: base del
      ajuste = bienes completos ($950 o $1000 según promo), nunca más.
- [ ] **Listas condicionadas por FP**: con 2 FP donde una no cumple la
      condición, la lista condicionada NO aplica y cae a la siguiente.
- [ ] **Cupones**: comportamiento actual intacto (ya validaban todas las FP).
- [ ] **Panel alta/edición delivery**: se ve `Subtotal artículos` + `Envío`
      + `Total`; el modal de desglose explica la base de cada ajuste.
- [ ] **Detalle del pedido en panel**: fila `Envío` visible; subtotal de
      bienes = subtotal − envío.
- [ ] **Táctil (mostrador y delivery)**: el precio de la grilla coincide
      SIEMPRE con el precio al agregar (sucursal con override + lista
      Delivery activa).
- [ ] **Single-FP en venta/mostrador**: cero cambios de totales (regresión).
- [ ] Contrato `docs/api-v1-delivery.md` actualizado con la nota de
      comportamiento multi-FP.
- [ ] Pint + suites de pedidos/tienda verdes; smoke tests de los
      componentes tocados.

---

## Plan de Implementación

### Fase 1: Motor — promos y listas multi-FP (RF-01, RF-02) [COMPLETO — 2026-07-24]
1. `WithCalculoVenta`: contexto `formas_pago_ids` (retro-compat con
   `forma_pago_id`); regla "todas ∈" en promos comunes y especiales.
2. `ListaPrecio(Condicion)`: evaluación de condición FP contra el set.
3. `CotizadorCarritoTienda::cotizar` + `CotizacionController` +
   `PedidoTiendaService`: pasar todas las FP (adiós `pagos[0]`).
4. Tests: promo cae con FP mixta, aplica con ambas habilitadas,
   orden-independencia, lista condicionada, single-FP sin regresión.

### Fase 2: Base del ajuste bienes-primero (RF-03) [COMPLETO — 2026-07-24]
1. `AsignadorBasesAjustePagos` (nuevo, unit tests puros).
2. `CotizadorCarritoTienda::desglosarPagos` lo consume.
3. Panel: `WithPagosDesglose` re-asigna bases del desglose completo al
   agregar/quitar pagos (hook delivery); recálculo de venta con el set de
   FPs (cierra RF-01 lado panel).
4. Tests de paridad tienda ↔ panel con el escenario de los criterios.

### Fase 3: UX panel delivery (RF-04) [PENDIENTE]
1. Totales de alta/edición con subtotal artículos + envío.
2. Fila `Envío` en detalle (`pedidos-delivery.blade.php`).
3. Leyendas de base del ajuste en el modal de desglose.
4. Traducciones es/en/pt.

### Fase 4: Fix precio táctil (RF-05) [PENDIENTE]
1. `cargarCatalogoTactil()` × 2 con precio efectivo (una pasada).
2. Refresh del snapshot al cambiar lista activa.
3. Test: precio de grilla == precio al agregar (sucursal con override +
   lista delivery).

### Fase 5: Contrato + docs + verificación [PENDIENTE]
1. Nota de comportamiento en `docs/api-v1-delivery.md`.
2. Validación en vivo (tienda :8001 contra core :8000) del escenario del
   usuario.
3. `@docs-sync` al armar el PR; `/sdd-verify` con matriz de cumplimiento.

---

## Notas y Decisiones

- 2026-07-24 — **D1**: la asignación de bienes a pagos es greedy por
  `ajuste_porcentaje` ascendente (pro-cliente, orden-independiente). Evita
  el edge de dos FP con descuento descontando dos veces sobre los mismos
  bienes (Σ bases ≤ bienes).
- 2026-07-24 — **D2** (usuario): con pago dividido, una promo restringida a
  FP aplica solo si TODAS las FP están habilitadas en la promo; si no, cae
  entera. Espejo de la regla de cupones (anti-abuso).
- 2026-07-24 — **D3**: misma regla para listas de precios condicionadas por
  FP (mismo hueco de `pagos[0]`).
- 2026-07-24 — **D4**: el fix del táctil usa el precio con lista (opción B
  de la exploración): es el único que hace coincidir grilla y alta en
  delivery/take-away, donde la divergencia es sistemática.
- 2026-07-24 — **D5**: se mantiene D17 (envío = renglón-concepto, excluido
  del ajuste); lo que cambia es CÓMO se reparte la exclusión entre pagos
  (bienes-primero en vez de proporcional). El caso single-FP no cambia.
- 2026-07-24 — **D6**: sin cambios de shape en el contrato v1 (solo
  semántica documentada). `bcn-tienda` no requiere cambios de cálculo;
  a lo sumo textos del checkout.
