# Multi-pago consistente + desglose delivery en panel + fix precio táctil - Especificación

## Estado: IMPLEMENTADO — 2026-07-24 (PR pendiente de merge)

> Spec creado 2026-07-24 a partir del bug reportado en la tienda (promo 5%
> efectivo + descuento 10% efectivo con pago dividido). Cubre 4 sub-features:
> RF-01/02 (validación de promos/listas por FP con multi-pago), RF-03 (base
> del ajuste FP bienes-primero con tope), RF-04 (desglose visible de envío en
> panel delivery), RF-05 (fix precio fallback en catálogo táctil).
> Fases 1-5 COMPLETAS en branch `feat/multi-pago-consistente`. Suite
> completa: 1410 passed / 1 skipped. Validación en vivo de la API contra el
> core local: los 4 escenarios del usuario dan los números esperados
> ($1855 single-FP sin regresión; $1900 con pago dividido, orden-independiente,
> tope en bienes). Pendiente: validación visual del usuario (panel delivery +
> táctil) y merge.

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

- [x] **1 FP efectivo (tienda y panel)**: total $1855 (sin regresión).
      Evidencia: cotizar en vivo (demo) + `test_promo_restringida_a_fp_no_aplica…` (rama single-FP).
- [x] **2 FP, efectivo $1000 + resto otra**: promo NO aplica; ajuste
      efectivo = −$100; total $1900. Evidencia: en vivo +
      `test_cotizar_dos_fp_con_envio_topa_el_descuento_en_los_bienes` (tienda)
      + `test_desglose_dos_fp_asigna_bienes_primero_con_tope` (panel).
- [x] **Orden invertido de los pagos**: mismo resultado. Evidencia: en vivo +
      `test_promo_restringida_a_fp_es_orden_independiente…` + unit del asignador.
- [x] **2 FP ambas habilitadas en la promo**: promo aplica; base $950 → −$95
      (efectivo $1000 topa en bienes). Evidencia: `test_promo_aplica_con_pago_dividido_si_acepta_ambas_fp`
      (variante $600: −$60 sobre su porción).
- [x] **Pago efectivo $1500**: base = bienes completos, nunca más. Evidencia:
      en vivo (−$100) + test tienda.
- [x] **Listas condicionadas por FP**: `test_lista_condicionada_por_fp_no_aplica_con_pago_dividido_mixto`.
- [x] **Cupones**: intactos (ya validaban todas las FP); cotizador ahora les
      pasa el set completo en vez de `pagos[0]`. Suites de cupones verdes.
- [x] **Panel alta/edición delivery**: filas nuevas en `_resumen-totales` +
      leyendas en `_modal-pago-mixto` (render cubierto por suites Livewire;
      validación visual del usuario pendiente).
- [x] **Detalle del pedido en panel**: fila `Envío` (+badge manual) en
      `pedidos-delivery.blade.php`.
- [x] **Táctil**: `CatalogoTactilPrecioTest` (3 tests: mostrador, delivery,
      refresh por cambio de tipo) — grilla == precio al agregar.
- [x] **Single-FP en venta/mostrador**: suite completa 1410 passed / 1 skipped.
- [x] Contrato `docs/api-v1-delivery.md` actualizado (semántica multi-FP,
      sin cambio de shape).
- [x] Pint verde en todos los archivos tocados; suites de pedidos/ventas/API
      verdes.

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

### Fase 3: UX panel delivery (RF-04) [COMPLETO — 2026-07-24]
1. Totales de alta/edición con subtotal artículos + envío.
2. Fila `Envío` en detalle (`pedidos-delivery.blade.php`).
3. Leyendas de base del ajuste en el modal de desglose.
4. Traducciones es/en/pt.

### Fase 4: Fix precio táctil (RF-05) [COMPLETO — 2026-07-24]
1. `cargarCatalogoTactil()` × 2 con precio efectivo (una pasada).
2. Refresh del snapshot al cambiar lista activa.
3. Test: precio de grilla == precio al agregar (sucursal con override +
   lista delivery).

### Fase 5: Contrato + docs + verificación [COMPLETO — 2026-07-24]
1. Nota de comportamiento en `docs/api-v1-delivery.md`.
2. Validación en vivo (tienda :8001 contra core :8000) del escenario del
   usuario.
3. `@docs-sync` al armar el PR; `/sdd-verify` con matriz de cumplimiento.

---

## RF-06 (agregado 2026-07-24, feedback post-validación): traslado del ajuste al pago "resto"

- El cálculo del ajuste no cambia (RF-03), cambia la ASIGNACIÓN: un pago con
  monto DECLARADO se cobra por su monto exacto (el billete de $1000 sigue
  siendo $1000) y el ajuste que genera se traslada al pago sin monto (el
  "resto"), que queda ya ajustado. Efectivo $1000 + otra FP → $1000 y $900.
- Si el pago con ajuste ES el resto, se lo aplica a sí mismo ("te queda por
  pagar $900 en efectivo"). Sin pago resto (todos declarados), comportamiento
  histórico (cada uno sobre sí). Edge: descuento trasladado > resto → el
  resto queda en $0 y el excedente vuelve al pago declarado (nunca negativo).
- Contrato: campo aditivo `ajuste_generado` por pago (para que la tienda
  explique "Efectivo genera −$100"); invariantes base+ajuste=final y
  Σ generado = Σ aplicado.
- **Decisión usuario (2026-07-24)**: los RECARGOS también aplican solo sobre
  artículos (simétrico al descuento y al single-FP) — una FP con recargo que
  cubre solo envío recarga $0.
- Alcance v1: tienda + core (`CotizadorCarritoTienda::desglosarPagos`) **y
  el desglose del panel DELIVERY** (feedback en vivo del usuario: el panel
  quedaba incoherente — el descuento pegado al efectivo, pendiente
  desfasado al caer la promo). Implementación panel:
  `NuevoPedidoDelivery::reasignarBasesAjustePagosDesglose` reescrito a
  traslado (lo INGRESADO por FP es lo que se COBRA; los ajustes generados
  reducen el PENDIENTE y el pago que CIERRA el desglose los absorbe, con
  `monto_ingresado` persistido por pago) + hook nuevo
  `alCambiarFormaPagoCandidataDesglose` (trait): al ELEGIR la FP candidata
  en el modal se revalidan promos con el set candidato y el pendiente ya
  incluye el ajuste hipotético de esa FP — "asignar pendiente" ofrece el
  monto final correcto. Venta/mostrador (sin envío ni traslado) siguen
  igual; la generalización a N pagos con la ventana rediseñada sigue
  pendiente.
- Lado tienda (bcn-tienda): los blades de carrito/checkout muestran
  `ajuste_generado` ("genera −$X") en la FP que origina el descuento y
  `monto_ajuste` en la que lo recibe.

---

## Ampliación 2026-07-24 (post-merge #178/#34): desglose universal + rediseño visual

### RF-07: Traslado del ajuste en TODOS los hosts del desglose (venta y mostrador)
- La semántica RF-06 (lo ingresado es lo que se cobra; los ajustes generados
  reducen el pendiente; el pago que cierra absorbe) sube de
  `NuevoPedidoDelivery` al trait `WithPagosDesglose` como DEFAULT:
  `NuevaVenta` y `NuevoPedidoMostrador` la heredan (sin envío, bienes =
  total_final). Hook nuevo `montoExcluidoDeAjustesDesglose()` (default 0;
  delivery → envío).
- La revalidación al elegir la FP candidata (hook
  `alCambiarFormaPagoCandidataDesglose`) también pasa a default del trait, y
  `formasPagoContexto()` (WithCalculoVenta) considera la candidata en todos
  los hosts.
- **Cuidados**: percepción fiscal (Fase 5b) distribuida en `monto_final` de
  pagos fiscales → el recálculo debe PRESERVAR la porción de percepción;
  cuotas (recargo por pago se mantiene sobre su propio pago); moneda
  extranjera (paths single-pago quedan idénticos por construcción);
  `montoFacturaFiscal` (suma finales de pagos fiscales): al mover plata
  entre FPs cambia qué monto queda bajo factura — consecuencia deseada de
  la semántica nueva.
- Cobro rápido: excluido en todos los hosts (saldo indivisible).

### RF-08: Rediseño visual del modal de desglose (`_modal-pago-mixto`)
- Modal más ancho; en desktop DOS columnas: izquierda total a cobrar +
  pendiente + selector de FPs SIEMPRE visible; derecha los pagos agregados
  (+percepción/totales). En móvil, apilado (selector primero).
- Al completarse el desglose (pendiente 0) el selector se apaga (dim +
  deshabilitado) con estado visual "desglose completo"; se reactiva al
  quitar un pago.
- Sin cambios de lógica: solo estructura/estética del blade compartido.

### RF-09: Auditoría de los flujos de cobro restantes (informe, no cambio ciego)
- `GestionarCobranzas` (cta cte): desglose PROPIO fuera del trait, sin
  promos (cobra deuda ya generada) y con semántica monto_pagado vs
  monto_para_deuda preexistente → auditar coherencia conceptual y reportar
  hallazgos al usuario antes de tocar.
- Botones de cobro separados (confirmar pagos planificados en
  PedidosDelivery/PedidosMostrador): cobran montos persistidos sin
  recalcular → verificar que no re-derivan ajustes.
- `CambioFormaPagoService` (cambio de FP post-venta) y compras
  (EditorCompra/pagos proveedores): verificar si les aplica la regla.

### Plan (continúa numeración)
- Fase 6: RF-07 en el trait + regresión ventas/mostrador/delivery [COMPLETO — 2026-07-24]
  - Traslado y revalidación-por-candidata movidos al trait (defaults);
    delivery solo conserva `montoExcluidoDeAjustesDesglose()` (envío).
  - Régimen de CIERRE exacto: la base del pago que cierra = total − Σ
    ingresados previos (evita el punto fijo "el descuento del que cierra
    reduce su propia cobertura"); régimen ABIERTO con pool en el pendiente.
  - Percepción fiscal y recargo de cuotas preservados ENCIMA del ingresado;
    paths single-pago de moneda extranjera usan base directa (total −
    excluido) y quedan idénticos.
- Fase 7: RF-08 rediseño del modal [COMPLETO — 2026-07-24]
  - `lg:max-w-5xl` + grilla 2 columnas (selector FP siempre visible a la
    izquierda con estado "Desglose completo"; pagos + vuelto + fiscal a la
    derecha; en móvil orden fuente actual). `npm run build` corrido.
- Fase 8: RF-09 auditoría [COMPLETO — 2026-07-24] — informe:
  - GestionarCobranzas: NO APLICA — modelo pronto-pago PROPIO sobre deuda
    (sin promos); diverge del mental-model del desglose de venta pero es
    legítimo. Decisión pendiente del usuario si se unifica a futuro.
  - Confirmación de pagos planificados (delivery/mostrador + services):
    COHERENTE — cobran monto_final persistido, sin re-derivar.
  - **CambioFormaPagoService + Ventas::agregarAlDesgloseCambio: LÓGICA
    VIEJA parcial** — ajuste per-pago auto-aplicado (total congelado,
    tolerable) y **NO revalida promos/listas condicionadas por FP** al
    cambiar la FP de una venta cobrada (ej.: venta con 10% "solo efectivo"
    cambiada a tarjeta conserva el descuento). Gap real; requiere decisión
    del usuario (revalidar vs alertar vs bloquear).
  - Compras y WithCobroIntegracion/CobroService: NO APLICA / COHERENTE.

### RF-10: Bloqueo del cambio de FP con promo condicionada [COMPLETO — 2026-07-24]

> Implementado en `CambioFormaPagoService::validarBeneficiosCondicionadosPorFP`
> (guard en cambiarFormaPago / agregarPagoAVenta / eliminarPagoDeVenta, ANTES
> de mutar nada): cubre promos comunes (condición por_forma_pago), especiales
> (formas_pago_ids), cupones restringidos y listas condicionadas; regla
> "todas las FP resultantes ∈ permitidas". 4 tests nuevos. Además fix del
> botón Confirmar del panel delivery (mostraba el total del desglose de IVA
> escalado — $950 en un pedido de $1900; ahora usa la misma fuente que la
> fila TOTAL del resumen).
- **Decisión del usuario**: en `CambioFormaPagoService` (y su espejo
  `Ventas::agregarAlDesgloseCambio`), si la venta tiene promociones/listas
  cuyo descuento estuvo condicionado a una FP y el cambio intenta REMOVER o
  reemplazar esa FP, se BLOQUEA el cambio con un mensaje claro: "no se puede
  cambiar esta forma de pago porque la promo X dependía de ella — para
  modificarla hay que cancelar la venta entera (y recargarla)".
- Detección: la venta persiste sus promos aplicadas
  (`venta_promociones`/detalle); cruzar sus `formas_pago_ids` (condiciones
  por_forma_pago / formas_pago_ids de especiales) contra el set de FPs
  resultante del cambio.
- Implementar en la PRÓXIMA sesión (no incluido en el PR actual).

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
