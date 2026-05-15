# Pedidos Mostrador — Paridad de persistencia con Venta — Especificación

## Estado: COMPLETO — 2026-05-15

> Aprobado por el usuario el 2026-05-14. Branches:
> - `feat/pedidos-mostrador-paridad-venta` (PR #89, mergeado) — Fases 1+2: schema + modelos + hot-fix.
> - `feat/pedidos-mostrador-paridad-logica` — Fases 3-7: service core, livewire, conversión, tests.
>
> **Hot-fix del bug aplicado**: condición `$desgloseEstaCompleto && $esMixto` cambiada a `$desgloseEstaCompleto` en `NuevoPedidoMostrador::construirDataPedido()` (líneas 1281-1291). Eso cierra el caso reportado (FP simple con descuento → `total_final` correcto → `estado_pago` correcto).
>
> **Schema agregado** (Fases 1+2): 4 columnas en `pedidos_mostrador` + 2 en `pedidos_mostrador_pagos` + modelos actualizados.
>
> **Lógica autoritativa agregada** (Fases 3-7):
> - `PedidoMostradorService::recalcularTotales()` se invoca tras cada cambio de pagos (agregar, confirmar planificado, anular, eliminar planificado) y al editar pedido. Pisa `total_final` y `ajuste_forma_pago` desde la suma de `monto_ajuste` + `recargo_cuotas_monto` de pagos activos+planificados.
> - `PedidoMostradorService::guardarPromocionesPedido()` espejo de `VentaService::guardarPromocionesVenta()`. Insertan promociones desde `_promociones_comunes`/`_promociones_especiales` del payload.
> - `NuevoPedidoMostrador::construirDataPedido()` agrega los 4 campos de puntos + `_promociones_*`.
> - `NuevoPedidoMostrador::construirDetallesPedido()` deriva `descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon`, `tiene_promocion` desde `$this->resultado['items']`.
> - `convertirEnVenta()` mapea los 4 campos de puntos + clona `pedido_mostrador_promociones` → `venta_promociones` + mapea `saldo_pendiente` y `operacion_origen` en venta_pagos.
> - 7 tests focales nuevos en `PedidoMostradorParidadVentaTest` (CA-01 a CA-05), todos verdes.

---

## Contexto y Motivación

**Bug observado en producción (2026-05-14):**
Pedido total nominal $100. Cliente paga con efectivo (FP con 10% de descuento). El pago se guarda con `monto_final = $90`. El sistema marca el pedido como `estado_pago = parcial` cuando debería estar `pagado`.

**Causa raíz** (verificada en exploración, ver reporte adjunto en chat):
- `PedidoMostradorService::recalcularEstadoPago()` (línea 924-953) compara `sum(pagos.monto_final)` contra `pedido.total_final`.
- `total_final` del pedido no refleja el ajuste por FP correctamente porque el service **no recalcula totales server-side** — confía ciegamente en lo que envía el Livewire.
- Además, otros campos de la composición del cálculo no se persisten: descuentos por promoción por línea, promociones a nivel pedido, montos en pesos de canje de puntos.

**5 gaps identificados** (gap #6 fiscal queda fuera del alcance):
1. `PedidoMostradorService` nunca recalcula totales (vs `VentaService::actualizarTotales()`).
2. No persiste promociones a nivel pedido en `pedido_mostrador_promociones` (tabla existe vacía).
3. No persiste descuentos por promo en líneas (`descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon` en `pedido_mostrador_detalle`).
4. Faltan 4 columnas de puntos en `pedidos_mostrador` (`puntos_usados_monto`, `articulos_canjeados_monto`, `puntos_canjeados_pago`, `puntos_canjeados_articulos`).
5. Faltan 3 columnas de auditoría en `pedidos_mostrador_pagos` (`saldo_pendiente`, `operacion_origen`, `creado_por_usuario_id`).

**Por qué ahora:** Pedidos Mostrador se desplegó a producción en sesión 13 (PRs #83-#86). El bug bloquea el flujo de cobro real con FP con ajuste. No se puede avanzar con features dependientes (conversión auto al entregar, API REST, etc.) sin esta paridad porque toda la lógica posterior asume que `total_final` y `estado_pago` son confiables.

---

## Principios de Diseño

1. **API-first**: `PedidoMostradorService` es el contrato autoritativo. Recalcula totales server-side desde detalles + descuentos + ajustes. Si el Livewire mandó algo distinto, gana el service. Filosofía alineada con `feedback_api_first_services` — Livewire / API REST / CLI deben poder consumir el mismo service y obtener resultados consistentes.
2. **Paridad estructural con Venta**: las nuevas columnas usan el mismo nombre, tipo y default que sus equivalentes en `ventas` / `venta_pagos`. Esto hace que `convertirEnVenta()` sea un mapeo trivial sin renombres.
3. **Sin backfill**: módulo recién desplegado, sin datos productivos a migrar. Sólo migración de schema.
4. **Append-only ledger preservado**: no se modifica el comportamiento de pagos (estados `activo`/`anulado`/`planificado`, contraasientos al anular).
5. **Tests focales antes de cerrar**: el test reproductor del bug original es criterio de aceptación bloqueante.
6. **Sin cambios visibles de UI**: cambios internos de persistencia. La vista lista lee los mismos campos (`total_final`, `estado_pago`) que ahora estarán correctos.

---

## Requisitos Funcionales

### RF-01: Recálculo autoritativo server-side
- `PedidoMostradorService::crearPedido()` y `actualizarPedido()` invocan internamente `recalcularTotales($pedido)` después de persistir detalles, antes de guardar el header definitivo.
- `recalcularTotales()` deriva los totales desde:
  - Subtotal: `Σ pedido_mostrador_detalle.subtotal` (que ya descuenta promo / promo especial / cupón / lista).
  - Descuento general: `pedido.descuento_general_monto`.
  - Ajuste FP + recargo cuotas: leídos del pago activo o planificado primario (o sumados si hay varios). El cálculo replica `Venta::actualizarTotales()` 1:1.
  - `total_final = subtotal - descuento_general + iva + ajuste_forma_pago + recargo_cuotas`.
- Si el Livewire mandó valores distintos, se sobrescriben silenciosamente. No se lanza excepción (el service es la verdad).

### RF-02: Persistencia de promociones a nivel pedido
- Nuevo método `PedidoMostradorService::guardarPromocionesPedido(PedidoMostrador $pedido, array $promociones)` (espejo de `VentaService::guardarPromocionesVenta()`).
- Se invoca desde `crearPedido()` y `actualizarPedido()` cuando viene el array.
- Inserta en `pedido_mostrador_promociones` con la misma estructura de columnas que `venta_promociones`.
- Tipos de promoción soportados (idénticos a venta): `promocion`, `promocion_especial`, `forma_pago`, `cupon`.

### RF-03: Persistencia de descuentos por promo por línea
- `crearDetalle()` y `actualizarDetalle()` en `PedidoMostradorService` reciben y persisten:
  - `descuento_promocion`
  - `descuento_promocion_especial`
  - `descuento_cupon`
  - `descuento_lista`
  - `tiene_promocion`
- Estos campos ya existen en la tabla `pedido_mostrador_detalle` desde el módulo original, sólo no se estaban poblando.

### RF-04: Columnas faltantes en `pedidos_mostrador`
Agregar 4 columnas con naming, tipo y orden idénticos a `ventas`:
- `puntos_canjeados_pago` INT UNSIGNED DEFAULT 0 AFTER `puntos_usados`
- `puntos_canjeados_articulos` INT UNSIGNED DEFAULT 0 AFTER `puntos_canjeados_pago`
- `puntos_usados_monto` DECIMAL(12,2) DEFAULT 0 AFTER `puntos_canjeados_articulos`
- `articulos_canjeados_monto` DECIMAL(12,2) DEFAULT 0 AFTER `puntos_usados_monto`

### RF-05: Columnas faltantes en `pedidos_mostrador_pagos`
Agregar 2 columnas (corrección del reporte: `creado_por_usuario_id` ya existe en la tabla original):
- `saldo_pendiente` DECIMAL(12,2) DEFAULT 0 AFTER `monto_final`
- `operacion_origen` ENUM('venta_original','cambio_pago','pago_agregado','anulacion_sin_reemplazo') DEFAULT 'venta_original' AFTER `saldo_pendiente`

> Nota: usamos los mismos valores del ENUM que `venta_pagos.operacion_origen` aunque "venta_original" suene raro para pedido, para que el mapeo `PedidoMostradorPago` → `VentaPago` durante `convertirEnVenta()` sea trivial.

### RF-06: Estado de pago correcto post-cambio
- `recalcularEstadoPago()` no cambia su lógica. Funciona correctamente porque `total_final` ahora refleja todos los ajustes.
- Test reproductor del bug debe pasar.

### RF-07: Conversión a Venta sin discrepancias
- `convertirEnVenta()` migra al `VentaService` todos los campos nuevos:
  - Los 4 de puntos al header de Venta.
  - Las promociones de `pedido_mostrador_promociones` → `venta_promociones` (insertadas después de `crearVenta()`).
  - Llama `VentaService::crearVenta()` con `usarTotalesProporcionados = true` y ya no hay recálculo divergente.
- Los 3 campos nuevos de `pedidos_mostrador_pagos` se mapean a `venta_pagos` durante la migración de pagos (`venta_pagos` ya tiene esos campos).

### RF-08: Compatibilidad con pagos planificados
- Al recalcular totales se consideran tanto pagos `activo` como `planificado` para deducir ajuste FP + recargo cuotas (lo que se "tiene previsto" cobrar).
- `estado_pago` sigue calculándose **sólo** sobre pagos `activo` (lo cobrado real).
- Si al editar un pedido el `total_final` cambia y los pagos planificados existentes ya no cubren el nuevo total, NO se invalidan automáticamente — el usuario los puede ajustar manualmente. (Decisión conservadora; revisable.)

---

## Modelo de Datos

### Tablas modificadas

#### `pedidos_mostrador` — Agregar columnas (orden idéntico a `ventas`)
| Campo | Tipo | Default | Posición | Descripción |
|-------|------|---------|----------|-------------|
| `puntos_canjeados_pago` | INT UNSIGNED | 0 | AFTER `puntos_usados` | Cantidad de puntos canjeados como medio de pago |
| `puntos_canjeados_articulos` | INT UNSIGNED | 0 | AFTER `puntos_canjeados_pago` | Cantidad de puntos canjeados por artículos |
| `puntos_usados_monto` | DECIMAL(12,2) | 0 | AFTER `puntos_canjeados_articulos` | Equivalente en pesos de puntos canjeados como medio de pago |
| `articulos_canjeados_monto` | DECIMAL(12,2) | 0 | AFTER `puntos_usados_monto` | Equivalente en pesos de artículos canjeados con puntos |

#### `pedidos_mostrador_pagos` — Agregar columnas (2, no 3)
| Campo | Tipo | Default | Posición | Descripción |
|-------|------|---------|----------|-------------|
| `saldo_pendiente` | DECIMAL(12,2) | 0 | AFTER `monto_final` | Saldo del pago que queda pendiente (usado en pagos parciales) |
| `operacion_origen` | ENUM | 'venta_original' | AFTER `saldo_pendiente` | Mismo ENUM que `venta_pagos`: `venta_original`, `cambio_pago`, `pago_agregado`, `anulacion_sin_reemplazo` |

> `creado_por_usuario_id` ya existe en `pedidos_mostrador_pagos` desde la migración original (correción a la exploración inicial).

### Tablas existentes a usar (sin cambios de schema)
- `pedido_mostrador_promociones`: ya existe con estructura idéntica a `venta_promociones`. Se va a empezar a poblar.
- `pedido_mostrador_detalle_promociones`: ya existe con estructura idéntica a `venta_detalle_promociones`. Sin cambios.
- `pedido_mostrador_detalle`: ya tiene las columnas `descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon`, `descuento_lista`, `tiene_promocion`. Se van a empezar a poblar.

---

## Pantallas UI

### `NuevoPedidoMostrador` (`app/Livewire/Pedidos/NuevoPedidoMostrador.php`)
**Sin cambios visuales.** Cambios internos en los métodos privados que arman el payload al service:

- `construirDataPedido()`: agregar al array de datos:
  - `puntos_usados_monto` (del trait `WithPuntos`).
  - `articulos_canjeados_monto`, `puntos_canjeados_pago`, `puntos_canjeados_articulos` (idem).
- `construirDetallesPedido()`: por cada item, agregar:
  - `descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon`, `descuento_lista`, `tiene_promocion`.
  - Estos datos ya se calculan en `WithCalculoVenta` / `WithCarritoItems`, sólo no se estaban pasando al service.
- Nuevo método `construirPromocionesPedido()`: espejo de cómo `NuevaVenta` arma su array de promociones. Pasa al service como tercer parámetro de `crearPedido()` / `actualizarPedido()`.

### `PedidosMostrador` (`app/Livewire/Pedidos/PedidosMostrador.php`)
**Sin cambios.** Ya lee `total_final` y `estado_pago` directos del modelo, que ahora estarán correctos.

---

## Servicios

### `PedidoMostradorService` — `app/Services/Pedidos/PedidoMostradorService.php`

**Métodos modificados:**
- `crearPedido(array $datos, array $detalles, array $promociones = [])`:
  - Nuevo parámetro `$promociones` (default vacío para compatibilidad).
  - Después de crear el header y los detalles, invoca `recalcularTotales($pedido)`.
  - Si `$promociones` no está vacío, invoca `guardarPromocionesPedido($pedido, $promociones)`.
  - Al final, invoca `recalcularEstadoPago($pedido)` (ya existe).
- `actualizarPedido(...)`: idéntico tratamiento.
- `crearDetalle(...)`: recibe y persiste los 5 campos nuevos de promo/lista por línea.
- `convertirEnVenta(...)`:
  - Pasa los 4 campos nuevos de puntos al `datosVenta` enviado a `VentaService::crearVenta()`.
  - Después de crear la venta, migra `pedido_mostrador_promociones` → `venta_promociones` (INSERT directo conservando campos).
  - Los 3 campos nuevos de `pedidos_mostrador_pagos` se mapean al crear cada `VentaPago` durante la migración.

**Métodos nuevos:**
- `recalcularTotales(PedidoMostrador $pedido): void`
  - Espejo de `Venta::actualizarTotales()`.
  - Lee detalles, descuento_general, pagos (activos + planificados) para deducir ajuste FP y recargo cuotas.
  - Recalcula subtotal, iva, total, ajuste_forma_pago, total_final.
  - Persiste con `$pedido->save()`.
- `guardarPromocionesPedido(PedidoMostrador $pedido, array $promociones): void`
  - Espejo de `VentaService::guardarPromocionesVenta()`.
  - DELETE `pedido_mostrador_promociones` WHERE pedido_id (idempotente).
  - INSERT batch con el array recibido.

### `VentaService` — sin cambios
Ya tiene `usarTotalesProporcionados` (verificado en exploración). El llamado desde `convertirEnVenta()` continúa usándolo en `true`.

---

## Migraciones Necesarias

1. **`add_paridad_venta_columns_to_pedidos_mostrador`** — Tenant. Agrega 4 columnas a `pedidos_mostrador`.
2. **`add_paridad_venta_columns_to_pedidos_mostrador_pagos`** — Tenant. Agrega 3 columnas a `pedidos_mostrador_pagos` + FK a `config.users`.
3. **Regenerar `database/sql/tenant_tables.sql`** post-migración (verificación de prefijos FK incluida).

Patrón de migración: iterar todos los comercios, SQL raw con prefijo, try/catch por comercio. Usar skill `/migration`.

---

## Traducciones

Sin traducciones nuevas. Cambios internos de persistencia, no se agregan mensajes UI nuevos.

---

## Criterios de Aceptación

- [x] **CA-01** — Test reproductor del bug pasa: pedido total $100, FP efectivo 10% descuento, pago $90 → `estado_pago = pagado`. (`test_pago_con_fp_descuento_marca_pedido_pagado_y_recalcula_total_final`).
- [x] **CA-02** — `pedido_mostrador_promociones` se puebla al crear/editar pedido con promo nivel pedido (`test_guarda_promociones_a_nivel_pedido` + `test_actualizar_pedido_reemplaza_promociones_previas`).
- [x] **CA-03** — `pedido_mostrador_detalle.descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon` se pueblan por línea (`test_persiste_descuentos_de_promocion_y_cupon_por_linea`).
- [x] **CA-04** — `recalcularTotales()` ignora `total_final` erróneo enviado por el Livewire y persiste el cálculo correcto (`test_recalcular_totales_sobrescribe_total_final_erroneo_desde_pagos` + `test_anular_pago_revierte_ajuste_fp_en_total_final`).
- [x] **CA-05** — Conversión Pedido → Venta migra promociones, puntos y todos los nuevos campos sin recálculo divergente (`test_conversion_a_venta_preserva_totales_y_migra_promociones`).
- [x] **CA-06** — Smoke tests existentes siguen verdes: 63/63 tests de Pedido en verde.
- [x] **CA-07** — Lint Pint OK en archivos modificados.
- [x] **CA-08** — `database/sql/tenant_tables.sql` ya regenerado en PR #89 (Fases 1+2). Esta fase no toca schema.

---

## Plan de Implementación

### Fase 1: Migraciones [COMPLETO]
1. ✅ `2026_05_14_175728_add_paridad_venta_columns_to_pedidos_mostrador.php` (4 columnas, orden idéntico a `ventas`).
2. ✅ `2026_05_14_175744_add_paridad_venta_columns_to_pedidos_mostrador_pagos.php` (2 columnas: `saldo_pendiente` + `operacion_origen`).
3. ✅ Ejecutadas en local. Verificadas en comercio fixture 000001.
4. ✅ `tenant_tables.sql` regenerado (líneas 2310-2313 y 2471-2472).

### Fase 2: Modelos [COMPLETO]
1. ✅ `PedidoMostrador.php`: agregadas 4 columnas a `$fillable` y `$casts` (integer para los 2 de cantidad, decimal:2 para los 2 de monto).
2. ✅ `PedidoMostradorPago.php`: agregadas `saldo_pendiente` y `operacion_origen` a `$fillable`. Cast `decimal:2` para `saldo_pendiente`. 4 constantes `OPERACION_*` para los valores del ENUM (paridad con venta).
3. ✅ Smoke test SmokePedidosTest 15/15 verde.

### Fase 3: Service core [PENDIENTE — próxima sesión]

> **Hallazgo de exploración importante** (anotado para no repetir el análisis):
> `Venta::actualizarTotales()` (`app/Models/Venta.php:360`) es muy simple: sólo recalcula subtotal/iva/total desde detalles, **NO recalcula `total_final` ni `ajuste_forma_pago`**. En el flujo normal, `VentaService::crearVenta()` recibe `$usarTotalesProporcionados = true` y persiste `total_final` y `ajuste_forma_pago` tal como vienen del Livewire (línea 134-135).
>
> Conclusión: el patrón "service autoritativo" en Venta no aplica a `total_final` — éste depende del pago/desglose y se calcula en el frontend. Para Pedido, el approach correcto en próxima sesión es:
> 1. **`recalcularTotales()` se llama después de cada cambio en pagos** (`agregarPago`, `confirmarPagoPlanificado`, `anularPago`, `eliminarPagoPlanificado`), no sólo al crear el pedido.
> 2. La lógica: `total_final = total + sum(pagos.monto_ajuste activos+planificados) + sum(pagos.recargo_cuotas_monto activos+planificados)`. El signo de `monto_ajuste` ya viene aplicado (negativo en descuento, positivo en recargo).
> 3. Al crear el pedido inicial (antes de tener pagos), confiar en lo que envía el Livewire (idem a Venta). Pero apenas hay pagos, el service toma el control.
> 4. Si los pagos planificados cambian (edición), recalcular y actualizar `total_final` en cascada.

Pasos concretos:
1. Implementar `PedidoMostradorService::recalcularTotales(PedidoMostrador $pedido): void`:
   - Si hay pagos activos/planificados: recalcular `ajuste_forma_pago` desde ellos.
   - Si no: respetar lo que está persistido (no pisar con 0).
   - Persistir `subtotal`, `iva`, `total`, `ajuste_forma_pago`, `total_final`.
2. Llamar `recalcularTotales()` al final de `agregarPago()`, `confirmarPagoPlanificado()`, `anularPago()`, `eliminarPagoPlanificado()`.
3. Implementar `PedidoMostradorService::guardarPromocionesPedido(PedidoMostrador $pedido, array $promociones): void` (espejo de `VentaService::guardarPromocionesVenta()` línea 436).
4. Ajustar `crearPedido()` y `actualizarPedido()`: aceptar `$promociones`, invocar `guardarPromocionesPedido()`.
5. Ajustar `crearDetalle()` para persistir los 5 campos de promo/lista por línea (`descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon`, `descuento_lista`, `tiene_promocion`).

### Fase 4: Livewire alta/edición [PENDIENTE — próxima sesión]
1. Ajustar `construirDataPedido()`: incluir 4 campos de puntos (`puntos_canjeados_pago`, `puntos_canjeados_articulos`, `puntos_usados_monto`, `articulos_canjeados_monto`). Hoy quedan en 0 al persistir. Buscar en el trait `WithPuntos` los nombres de las propiedades equivalentes a las que usa `NuevaVenta`.
2. Ajustar `construirDetallesPedido()`: incluir 5 campos de promo/lista por línea. Calcularlos desde el carrito (los traits `WithCalculoVenta` / `WithCupones` ya los tienen).
3. Implementar `construirPromocionesPedido()` espejo del de `NuevaVenta`. Buscar `NuevaVenta::construirPromociones*` o equivalente para ver la estructura.
4. Llamar a `crearPedido()` / `actualizarPedido()` con el tercer parámetro `$promociones`.

### Fase 5: Conversión a venta [PENDIENTE — próxima sesión]
1. Ajustar `convertirEnVenta()` en `PedidoMostradorService`: pasar 4 campos de puntos a `VentaService::crearVenta()`.
2. Tras crear la venta, INSERT batch en `venta_promociones` desde `pedido_mostrador_promociones` (clonar campo a campo, sólo cambiar `pedido_mostrador_id` por `venta_id`).
3. Al crear cada `VentaPago`, mapear `saldo_pendiente` (probablemente igual a `monto_final` para pagos sin parcial) y `operacion_origen` (probablemente `'venta_original'` por defecto al venir de pedido).

### Fase 6: Tests focales [PENDIENTE — próxima sesión]
1. **Test reproductor del bug original** (CA-01): pedido $100 + FP efectivo con 10% descuento + cobro 1-paso → `estado_pago = pagado`. Setup: usar `$reflection->invoke($livewire->instance(), 'construirDataPedido')` con estado interno preconfigurado, o un test E2E completo del flujo de Livewire. **Validar también el camino post-hot-fix**: re-aplicar el `&& $esMixto` y verificar que el test rompe (regression guard).
2. Test persistencia promo nivel pedido (CA-02): aplicar promo de FP → `pedido_mostrador_promociones` queda con 1 row.
3. Test persistencia descuento promo en línea (CA-03): aplicar promo a un artículo → `pedido_mostrador_detalle.descuento_promocion` > 0.
4. Test recálculo (CA-04): agregar pago → llamar `recalcularTotales()` → ver que `total_final` queda con ajuste FP correcto independientemente de lo que envió el Livewire.
5. Test conversión (CA-05): convertir pedido a venta → `pedido.total_final === venta.total_final`, `venta_promociones` poblada igual que `pedido_mostrador_promociones`.

### Fase 7: Verificación [PENDIENTE — próxima sesión]
1. `php artisan test --filter=SmokePedidos` (CA-06).
2. `php vendor/bin/pint --test` (CA-07).
3. `tenant_tables.sql` ya regenerado en Fase 1 — verificar con `git diff` que no quedó nada.
4. Verificar manualmente en browser el flujo completo (no sólo el reproductor del bug).
5. Commit final + push + PR review CI.

## Para retomar (próxima sesión)

1. `git checkout feat/pedidos-mostrador-paridad-venta` (si sigue sin mergear) o crear branch nuevo desde master si el hot-fix ya está mergeado.
2. Leer estas Fases 3-7 en orden.
3. Antes de Fase 3, releer `app/Services/VentaService.php` líneas 119-180 (cómo persiste totales y promociones) y `app/Services/Pedidos/PedidoMostradorService.php::crearPedido/actualizarPedido` para entender el diff.
4. Para Fase 6 test 1, mirar `tests/Integration/Livewire/Ventas/NuevaVentaCambioFPTest.php` como referencia de patrones de testing del flujo de cobro.

---

## Notas y Decisiones

- **2026-05-14**: Decisión recálculo server-side autoritativo (sin validar, sin lanzar excepción si discrepa). Razón: alineación con `feedback_api_first_services` — el service es el contrato y no debe confiar en el frontend. Beneficio adicional: el bug observado se vuelve imposible porque el service siempre persiste lo correcto independientemente de lo que llegue.
- **2026-05-14**: Decisión naming idéntico a Venta para las nuevas columnas. Razón: trivialidad del mapeo en `convertirEnVenta()`. Costo: ninguno (no hay legacy data).
- **2026-05-14**: Sin backfill. Razón: módulo Pedidos Mostrador desplegado en sesión 13 (2026-05-14), sin volumen de pedidos productivos. Si en algún momento aparece data inconsistente puntual, recalcular caso por caso desde tinker.
- **2026-05-14**: Skip campos fiscales en pagos (`comprobante_fiscal_id`, gap #6 del reporte). Razón: la facturación aplica sólo a Venta, no a Pedido. Tras la conversión, los `venta_pagos` se hidratan correctamente.
- **2026-05-14**: Edición de pedido con pagos planificados que dejan de cubrir el nuevo total: NO invalidar automáticamente, dejar al usuario ajustar. Razón: conservadora; si en uso real molesta, se revisita.

---

## Referencias

- Reporte de exploración: ver chat (sesión 14, 2026-05-14, post `/sdd-explore`).
- Spec original del módulo: `.claude/specs/pedidos-mostrador.md`.
- Memoria pagos planificados: `project_pedidos_pagos_planificados.md`.
- Memoria de repasos: `project_repasos_logica_venta_plan.md` (Repaso 2 cerrado para Venta; éste lo replica para Pedido).
- Feedback API-first: `feedback_api_first_services.md`.
