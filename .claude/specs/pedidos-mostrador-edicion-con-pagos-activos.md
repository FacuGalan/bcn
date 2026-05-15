# Pedidos Mostrador — Edición de pedidos con pagos activos parciales

## Estado: PROPUESTA — NO IMPLEMENTAR HASTA QUE APAREZCA CASO DE USO REAL

> Documento creado el 2026-05-15 a pedido del usuario para no perder el análisis. Es un spec exploratorio: queda como referencia para una próxima sesión si aparece un caso de uso recurrente en producción.

---

## Contexto

Hoy `NuevoPedidoMostrador::cargarPedidoExistente()` (línea 800-810) bloquea editar un pedido si tiene cobros materializados (`estado_pago != pendiente`). Los pagos `planificados` no cuentan como cobros — esos sí dejan editar. La regla es conservadora: si ya tocaste caja, no toques el pedido.

El usuario preguntó si convendría permitir editar pedidos que tengan pagos **activos parciales** (cobros reales pero que no cubren el total todavía). El caso típico: la mesa pagó $50 de $100 con efectivo y ahora pide agregar un postre.

## Por qué hoy está bloqueado (justificación)

Permitir editar libremente cuando hay pagos activos abre cuatro frentes de complejidad que el flujo "Venta" no tiene porque las ventas no se editan:

### 1. Quitar ítems o bajar cantidad → exceso cobrado

Pagó $50, total cae a $40. Hay $10 ya en caja sin contraprestación. Resolverlo requiere decidir entre:
- Vuelto inmediato del cajero (¿automático? ¿requiere caja abierta? ¿afecta arqueo?).
- Crédito al cliente en cuenta corriente.
- Devolución a caja con contraasiento.
- Bloquear el cambio.

Hoy esa decisión no existe en pedidos. Implementarla obliga a elegir reglas que después no se pueden revertir sin pisar trazabilidad.

### 2. Ajuste FP del pago viejo deja de ser coherente

Pagó $50 con FP que tiene 10% off (monto_base $55,55, ajuste -$5,55, monto_final $50). Si el total sube a $100, ese pago de $50:
- ¿Sigue valiendo $50 como pagado y el 10% off se "perdió" sobre la base nueva?
- ¿Se reparte proporcional al nuevo total y queda como $50 cubriendo $55,55 nominales?
- ¿El pago viejo se anula y se rehace con la base nueva?

Ninguna respuesta es obviamente correcta. Cada una rompe alguna invariante (idempotencia del pago, integridad del ledger, o el monto cobrado real).

### 3. Promos especiales (NxM, combos) dependen de la canasta exacta

Si pagó por un combo "Coca + Alfajor por $1500" y después se cambia el alfajor por otro ítem distinto, la promo ya no aplica a la canasta nueva. El descuento ya cobrado queda "huérfano" — el cliente ya pagó $1500 pero ahora el subtotal lleno son $1800.

Resolverlo manualmente requiere lógica de "promo persistida ≠ promo aplicable", y abre la puerta a fraude (cobrás con combo, después cambiás items para invalidar el combo y quedarte con el descuento).

### 4. MovimientoStock + MovimientoCaja ya impactaron

El pedido confirmado ya descontó stock y registró el ingreso de caja por el pago. Editar items implica:
- Contraasientos parciales de stock (revertir solo lo cambiado, sin tocar lo que sigue igual).
- Si baja el total, ¿se hace contraasiento de caja proporcional?
- Si sube, ¿se diferencia "lo cobrado antes" de "lo a cobrar ahora" en el ledger?

El servicio actual hace `revertirStockPorPedido()` + `descontarStockPorPedido()` (re-aplicar todo) en `actualizarPedido()`. Eso funciona porque hoy ningún cobro depende del stock previo. Si abrimos edición con cobros, hay que cambiar a contraasientos selectivos.

---

## Punto medio propuesto: "agregar-only" cuando hay pagos activos parciales

Permitir **únicamente agregar ítems nuevos** cuando `estado_pago = parcial`. NO permitir: quitar ítems, bajar cantidad, cambiar precio unitario, cambiar promociones aplicadas a ítems existentes, cambiar cupón ni descuento general.

Las invariantes que se mantienen:

- Pagos activos quedan intactos. No se anulan ni recalculan. El stock previo no se toca.
- `montoPendienteDesglose` sube en `precio(nuevos_items)` ya descontado por las promociones que apliquen.
- `estado_pago` queda `parcial` por definición — hay que cobrar la diferencia para llegar a `pagado`.

### Cómo se sostienen pagos activos + monto pendiente

- `recalcularEstadoPago()` ya soporta este caso: `Σ pagos activos < total_final` → `parcial`. No cambia lógica.
- `recalcularTotales()` necesita una variante: si hay pagos activos, NO recalcular `total_final` a partir de los pagos viejos sumando ajuste FP (no aplica a los items nuevos). En su lugar:
  - `total_final = total + ajuste_de_pagos_activos + ajuste_que_aplicaría_a_la_porción_pendiente`
  - O más simple: `total_final_pedido = monto_cobrado_real + monto_pendiente_nuevo`. La parte cobrada ya quedó cerrada con su ajuste FP propio.
- UX: en el form de edición mostrar dos bloques visibles:
  - "Ya cobrado": tabla read-only con los pagos activos (FP, monto, fecha).
  - "Pendiente": editable, con los nuevos items + selector de FP para la parte que falta cobrar.

### Promociones aplicables a items nuevos relacionados con items anteriores

Tres categorías a tratar distinto:

**a) Promociones comunes (% off general, monto fijo)**

Aplican a todo el carrito por definición. Decisión propuesta: se aplican solo a la **porción nueva** (items recién agregados). Lo ya cobrado no se "actualiza" ni se devuelve. Si la promo era 10% sobre el total y antes de agregar había $50 cobrados con esa promo aplicada, los nuevos items reciben 10% también, pero el ajuste no se redistribuye sobre los $50 viejos.

Razón: lo cobrado ya tiene su propia historia (ajuste FP propio, comprobante propio si después se facturara). Tratar la parte nueva como un "carrito hermano" simplifica el modelo y evita refacturaciones complejas.

**b) Promociones especiales (NxM, combos)**

Caso crítico. Si el combo es "Coca + Alfajor por $1500" y solo había Coca en el pedido cobrado, ahora agregás Alfajor:
- Opción conservadora: la promo NO aplica retroactivamente. El alfajor nuevo se cobra a precio lleno. Razón: la Coca ya se cobró con su precio (sin combo), si ahora "completara" el combo habría que descontar plata de la Coca ya cobrada, que es lo mismo que el problema #3 de la sección anterior.
- Opción permisiva: la promo aplica al alfajor nuevo y la diferencia que faltaba descontar a la Coca queda como "ajuste pendiente" a aplicar al próximo cobro. Más complejo, requiere tabla nueva `ajuste_promo_diferido`.

Recomendación inicial: **conservadora**. Las promos especiales solo aplican si todos los ítems del combo se agregan en la misma sesión de edición (todos en "pendiente", ninguno en "cobrado"). Si parte del combo ya está cobrada, no se permite cerrar el combo retroactivamente.

**c) Cupón**

Hoy es uno por venta/pedido (`cupon_id` en `pedidos_mostrador`). Si ya está aplicado y cobrado, los items nuevos NO reciben el cupón. El cupón se considera "consumido" en la porción cobrada.

Si todavía no se aplicó cupón al pedido y se agrega uno al editar, aplica solo a items nuevos.

### Cómo sabríamos si un ítem se pagó

**Hoy no se sabe**. `pedido_mostrador_pagos.pedido_mostrador_id` apunta al pedido, no a items. Es un modelo "header-level" no "line-level".

Tres caminos para resolverlo:

**Camino A — Sin asociación, marcar items cobrados por orden de agregado (sugerido)**

Agregar columna `pedido_mostrador_detalle.cobrado_at` (timestamp nullable). Cuando un pago activo se aplica, marcar como `cobrado_at = now()` los items existentes ordenados por id ASC hasta que `Σ subtotal_cobrado >= monto_pago_acumulado`. Los items que entren después con la edición quedan con `cobrado_at = null` y son los editables/eliminables.

Pros: simple, no requiere relación many-to-many, da una respuesta unívoca a "¿qué items están cobrados?".
Contras: el orden es heurístico. Si el cliente "pagó la mitad" no necesariamente quería pagar los primeros 3 de 6 items — la asignación es arbitraria pero defendible.

**Camino B — Pivote pago_item**

Nueva tabla `pedido_mostrador_pago_items` (pago_id, detalle_id, monto_imputado). Cada vez que se aplica un pago, se distribuye explícitamente entre items. Permite "pagar items específicos".

Pros: trazabilidad total.
Contras: complejidad N×M, requiere UI para imputar, complica los reportes existentes.

**Camino C — Sub-pedidos / "cuentas"**

Modelo restaurant-style: un pedido tiene varias "cuentas" (sub-grupos de items que se cobran como una unidad). Cada cuenta tiene sus pagos. Cobrar "items específicos" = abrir una cuenta nueva con esos items.

Pros: muy claro modelo mental. Mapeo directo con "dividir la cuenta".
Contras: cambio estructural grande, agrega nivel de jerarquía nuevo, afecta vistas, reportes, conversión a venta.

### ¿Se puede cobrar por items con descuentos/promociones globales?

Es la pregunta más espinosa. Hay descuentos que son **por línea** (precio del ítem ya viene con descuento aplicado) y descuentos que son **por carrito** (10% off general, cupón sobre total, ajuste FP).

**Por línea — viable hoy**: el subtotal del ítem ya refleja descuento_promocion + descuento_promocion_especial + descuento_cupon + descuento_lista. Cobrar la suma de subtotales de items específicos es directo.

**Por carrito — requiere prorrateo**: si el descuento del 10% es $100 sobre $1000 totales, y el cliente quiere pagar 3 de 5 items ($600 nominal), el 10% prorrateado al ítem se calcula como `descuento_item = 100 × (subtotal_item / 1000)`. Cobrás $540 ($600 - $60 prorrateado). El resto del descuento ($40) queda asociado a los items pendientes.

Reglas necesarias para que esto cierre:
1. Bloquear cambios en el descuento global / cupón mientras haya items "pagados" — el % o monto debe quedar congelado para que el prorrateo sea estable.
2. Las promos especiales (NxM/combos) **no se pueden prorratear** porque dependen de la canasta completa. Si un combo abarca items A y B, cobrar solo A "rompe" el combo. Regla: items que forman parte de una promo especial se cobran juntos o no se cobran.
3. El ajuste FP del cobro parcial se calcula sobre la base del prorrateo, no sobre la base nominal completa.

### Cómo se rompe el ledger en cada caso

Para cada opción del cobro por items, el riesgo de quedar inconsistente con caja y stock:

| Decisión | Caja | Stock | Promos persistidas |
|----------|------|-------|--------------------|
| Sin asociación + agregar-only | OK | OK | OK |
| Sub-cuentas (Camino C) | OK (un MovimientoCaja por sub-cuenta) | OK (descuento al confirmar cuenta) | Complejidad media |
| Cobro por items con prorrateo | OK con auditoría extra | OK | Riesgo alto si cambian promos retroactivo |

---

## Conclusión y orden sugerido para una futura sesión

Si en producción aparece el caso "necesito agregar items a un pedido ya cobrado parcialmente", el camino más barato y limpio es:

1. **Fase 1** — Agregar-only en items, sin tocar promociones especiales que ya estén persistidas, descuento global congelado, cupón congelado. UI con bloques "ya cobrado" (read-only) y "pendiente" (editable). Esto cierra el 80% de los casos reales de retail.

2. **Fase 2** (si el caso lo pide) — Camino A (`cobrado_at` por item) para responder "qué ítem se pagó", útil para reportes y para deshabilitar la edición de items cobrados específicamente.

3. **Fase 3** (si surge necesidad de "dividir la cuenta") — Evaluar Camino C (sub-pedidos/cuentas) en lugar de cobro-por-item con prorrateo. Modelo más limpio aunque cambio estructural mayor.

La opción **cobro por items con prorrateo de descuento global** queda como último recurso. Resuelve el caso "dividir la cuenta con un cupón aplicado" sin sub-pedidos, pero abre la complejidad de prorrateos retroactivos que es propensa a bugs.

---

## Referencias

- Bloqueo actual: `app/Livewire/Pedidos/NuevoPedidoMostrador.php:800-810` en `cargarPedidoExistente()`.
- Estado de pago: `app/Services/Pedidos/PedidoMostradorService.php::recalcularEstadoPago()` (ya soporta `parcial`).
- Recálculo de totales autoritativo: `recalcularTotales()` en el mismo service (paridad cerrada con Venta en PR #90).
- Spec primario de Pedidos Mostrador: `.claude/specs/pedidos-mostrador.md`.
- Spec de paridad cerrada: `.claude/specs/pedidos-mostrador-paridad-venta.md`.
