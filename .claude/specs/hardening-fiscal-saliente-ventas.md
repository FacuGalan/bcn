# Hardening Fiscal Saliente / Ventas (Tanda 2) - Especificación

## Estado: APROBADO (2026-07-14) — implementación en curso

> Tanda 2 del hardening post-auditoría integral (2026-07-14). Cubre los 8 hallazgos
> de fiscal saliente/ventas listados como "Fuera de alcance" en
> `hardening-circuito-precios.md`, re-verificados contra master (HEAD 85f51d0,
> post-PR #156): los 8 siguen presentes. Los 3 hallazgos de compras (gross-up
> RG5003, corregirCompra/PPP, retenciones sufridas en UI) quedan FUERA para specs
> propios.

---

## Contexto y Motivación

La auditoría integral del 2026-07-14 encontró, además de los problemas de
compras/costos ya corregidos en la tanda 1 (PR #156), una serie de bugs en el
circuito fiscal SALIENTE (ventas → comprobante → ledger): percepciones con base
mal calculada, re-emisiones que pierden tributos, pedidos convertidos que no
perciben, clasificación de IVA 0% inconsistente, caches fiscales pisados y un
"post-commit" del ledger que en realidad corre dentro de la transacción del cobro.

Restricción del usuario (2026-07-14): **NO cambiar la interacción/UX existente**.
Esta tanda solo corrige montos y registros. Hay 2 cambios de comportamiento
VISIBLES esperados (documentados en RF-V1 y RF-V3): son correcciones de montos que
hoy se calculan mal, no cambios de flujo.

---

## Principios de Diseño

1. **Base gravada única**: la base imponible de toda percepción aplicada es el
   neto GRAVADO (alícuotas > 0%). Exento/0% jamás integra la base. Una sola
   fuente de verdad para la separación gravado/exento en todo el sistema.
2. **La re-emisión replica a la original**: una FC re-emitida (reintento, cambio de
   forma de pago) lleva exactamente los mismos tributos y desglose que llevó (o
   habría llevado) la original. Nada se pierde en el camino.
3. **Uniformidad del agente de percepción**: si el comercio es agente, percibe
   igual por todos los canales (venta directa, pedido mostrador, pedido delivery).
   El canal no cambia la obligación fiscal.
4. **AFIP cierra siempre**: todo comprobante emitido cumple
   `ImpNeto + ImpOpEx + ImpIVA + ImpTrib = ImpTotal` por construcción, absorbiendo
   residuos de redondeo (patrón ya existente de `calcularDesgloseIvaProporcional`).
5. **Ledger tras commit real**: el registro best-effort del ledger fiscal corre
   después del commit REAL de la transacción de negocio, nunca dentro de un
   savepoint (patrón two-phase de `CambioFormaPagoService`).
6. **Sin cambios de UX**: ninguna pantalla cambia su flujo, botones ni campos.
   Solo cambian montos/registros que hoy son incorrectos.

---

## Requisitos Funcionales

### RF-V1 (ALTA): Base de percepción aplicada = neto GRAVADO

**Hallazgo**: `WithPagosDesglose::aplicarPercepcionFiscal()` (~línea 472) usa
`desgloseIvaFiscal['total_neto']`, que `formatearDesgloseParaAFIP()` (566-595) y
`recalcularDesgloseIvaFiscal()` (650-681) arman sumando TODAS las alícuotas del
`por_alicuota`, incluida 0%. Ídem `NuevaVenta.php:1310`. La percepción se cobra
también sobre el neto de ítems exentos/0%.

- El desglose fiscal del carrito separa explícitamente `neto_gravado` (alícuotas
  > 0) de `neto_exento` (alícuota 0) — ver RF-V4, es el mismo cambio estructural.
- `aplicarPercepcionFiscal` / `calcularTributosFiscales` usan SOLO `neto_gravado`
  como base imponible.
- **Cambio visible esperado**: en ventas con ítems exentos/0% a clientes
  percibidos, la percepción cobrada BAJA (hoy se cobra de más). Cobrado==facturado
  se mantiene (la percepción sigue integrando el total del cobro).

### RF-V2 (ALTA): Reintento / cambio de FP conservan los tributos

**Hallazgo**: `CambioFormaPagoService::emitirFcNuevaPostCommit()` (820-826) y
`reintentarFacturacionPago()` (902-911) llaman `crearComprobanteFiscal()` sin la
clave `tributos` ⇒ `ImpTrib = 0` y `calcularDesgloseIvaProporcional()` reparte el
monto completo como neto+IVA de bienes. La FC re-emitida pierde la percepción que
la venta cobró.

- Ambos caminos recuperan los tributos del comprobante ORIGINAL de la venta
  (relación `tributosDetalle`, mismo patrón snapshot que ya usa
  `crearNotaCredito`, líneas 811-823) y los pasan en `opciones['tributos']`.
  Si no hay comprobante original (reintento de una emisión que nunca salió),
  recalcular con `ImpuestoService::calcularPercepcionesComprobante()` sobre la
  venta/cliente.
- `calcularDesgloseIvaProporcional()` (727-782) descuenta `impTrib` de la base a
  prorratear: el neto+IVA se calcula sobre `montoAFacturar − impTrib`, manteniendo
  la absorción de residuo en la última alícuota (cierre AFIP).
- Si el monto a facturar es PARCIAL, los tributos se prorratean en la misma
  proporción que el resto del comprobante (criterio idéntico al del desglose IVA
  proporcional).

### RF-V3 (ALTA): Pedidos convertidos calculan percepciones aplicadas

**Hallazgo**: `PedidoMostradorService::convertirEnVenta()` (677) y su gemelo en
`PedidoDeliveryService` crean la venta sin calcular tributos: un cliente percibido
que compra por pedido no paga percepción; el mismo cliente en venta directa sí.

- La conversión calcula percepciones con la MISMA puerta que `NuevaVenta`
  (`ImpuestoService::calcularPercepcionesComprobante()` — base = neto gravado de
  RF-V1) y las incluye en la venta resultante y en su comprobante si factura.
- El total del pedido mostrado al cliente ANTES de convertir debe contemplar la
  percepción o el ajuste debe quedar explícito en la conversión — decidir en
  implementación mirando dónde se fija el total cobrable del pedido (los pagos
  planificados se materializan al confirmar; el cálculo debe ocurrir antes de
  materializar el cobro).
- **Cambio visible esperado**: pedidos de clientes alcanzados empiezan a incluir
  percepción (hoy se omite y se factura mal). Documentar en manual de usuario.

### RF-V4 (ALTA): Clasificación 0% única (gravado vs exento)

**Hallazgo**: `ComprobanteFiscalService::calcularDetallesIva()` (541-543) manda
alícuota 0% a `neto_exento`, pero la rama que consume el `desglose_iva` armado por
el frontend (193-210) acumula todo en `neto_gravado`. El mismo ítem 0% queda
clasificado distinto según el camino de emisión.

- Una sola regla en todo el sistema: alícuota 0% ⇒ EXENTO (`ImpOpEx`), alícuota
  > 0 ⇒ gravado. Es la semántica que ya aplica `calcularDetallesIva`.
- El desglose que arman `formatearDesgloseParaAFIP` / `recalcularDesgloseIvaFiscal`
  incorpora la separación (`neto_gravado` / `neto_exento` como claves explícitas)
  y `ComprobanteFiscalService` la respeta en la rama frontend igual que en la
  rama service.
- Este cambio estructural es el que habilita RF-V1 (la base gravada sale de acá).

### RF-V5 (MEDIA): Conversión con descuento de cabecera cierra en AFIP

**Hallazgo**: `convertirEnVenta()` (715-725) pasa `_usar_totales_proporcionados` +
`descuento_general_monto`; al facturar la venta convertida el desglose IVA puede
no cerrar `ImpNeto + ImpIVA = ImpTotal` ⇒ rechazo AFIP 10048 (mismo error ya
resuelto en venta directa con el ajuste de descuento por FP, fix AFIP 10051 de la
Fase 10a).

- El camino de facturación de ventas convertidas recalcula el desglose sobre los
  totales FINALES (con descuento de cabecera aplicado), absorbiendo el residuo en
  la última alícuota (reusar el patrón de `calcularDesgloseIvaProporcional`).
- Test con descuento de cabecera que genere residuo de redondeo verificando el
  invariante de cierre.

### RF-V6 (MEDIA): Facturación parcial no pisa `monto_fiscal_cache`

**Hallazgo**: `crearComprobanteFiscal()` (341-345) hace
`$venta->update(['monto_fiscal_cache' => $venta->total_final, 'monto_no_fiscal_cache' => 0])`
incondicionalmente, aunque `!$esTotalVenta` (facturación parcial por
`pagos_facturar`/`total_a_facturar`).

- El cache se actualiza con lo REALMENTE facturado: sumar los comprobantes fiscales
  vigentes de la venta (o incrementar por `total_a_facturar` del comprobante
  recién emitido); `monto_no_fiscal_cache = total_final − monto_fiscal_cache`.
- Las NC/anulaciones que ya ajustan este cache deben seguir cerrando (revisar los
  caminos de reversa existentes contra la nueva semántica de suma).

### RF-V7 (MEDIA): Ledger fiscal tras el commit REAL del cobro

**Hallazgo**: en `NuevaVenta.php` la transacción del cobro abre en 1318 y
`crearComprobanteFiscal()` se invoca en 1467, DENTRO. El `beginTransaction/commit`
interno del service (238/385) es un savepoint anidado, y `registrarFiscal()` (396,
comentado "POST-COMMIT best-effort") corre con la transacción externa aún abierta:
si el cobro luego rollbackea, el ledger igual se intentó registrar; y el
"best-effort" no protege nada porque comparte transacción.

- Aplicar el patrón two-phase de `CambioFormaPagoService` (Fase A atómica / Fase B
  post-commit): la emisión + persistencia del comprobante quedan en la transacción
  del cobro; `registrarFiscal()` se difiere a after-commit real
  (`DB::connection('pymes_tenant')->afterCommit(...)` o invocación explícita tras
  el commit del outer en `NuevaVenta`), manteniendo el reintento best-effort.
- Verificar que los demás llamadores de `crearComprobanteFiscal` (cambio FP,
  reintento, conversión) queden coherentes con el mismo criterio.

### RF-V8 (BAJA, condicionado a reproducción): Cortesía total con concepto libre

**Hallazgo (PLAUSIBLE, no confirmado)**: path legacy de invitación por línea con
concepto libre (VentaService 87, 164-165, 393, 448). El path moderno cortocircuita
la facturación con total=0 (`NuevaVenta.php:1302`), pero la ruta legacy señalada
en la auditoría podría explotar.

- PRIMERO reproducir con un test (venta 100% cortesía conteniendo concepto libre,
  por el path legacy por línea). Si explota: fix mínimo. Si no reproduce: registrar
  el resultado en Notas y cerrar el RF sin cambios.

---

## Modelo de Datos

### Tablas nuevas
Ninguna.

### Tablas modificadas
Ninguna. No hay migraciones: todos los fixes son de lógica sobre columnas
existentes (`comprobante_fiscal_tributos`, `monto_fiscal_cache`, etc.).

---

## Pantallas UI

**Ninguna pantalla cambia su interacción.** Componentes tocados solo en su lógica:

### Carrito de ventas (`NuevaVenta` + `WithPagosDesglose`) — lógica
- RF-V1 (base gravada), RF-V4 (separación gravado/exento), RF-V7 (orden
  transacción/fiscal). El desglose mostrado en pantalla puede cambiar montos de
  percepción (corrección), no campos ni flujo.

### Conversión de pedidos (sin UI propia) — lógica
- RF-V3, RF-V5 en los services de conversión.

---

## Servicios

### `ComprobanteFiscalService` — `app/Services/ARCA/ComprobanteFiscalService.php`
- `calcularDetallesIva` / rama `desglose_iva` frontend: clasificación 0% única (RF-V4).
- `crearComprobanteFiscal`: `monto_fiscal_cache` por suma de comprobantes (RF-V6);
  `registrarFiscal` diferido a after-commit real (RF-V7).

### `CambioFormaPagoService` — `app/Services/Ventas/CambioFormaPagoService.php`
- `emitirFcNuevaPostCommit` / `reintentarFacturacionPago`: recuperar/recalcular
  tributos y pasarlos en `opciones['tributos']` (RF-V2).
- `calcularDesgloseIvaProporcional`: base = monto − impTrib (RF-V2).

### `ImpuestoService` — `app/Services/Fiscal/ImpuestoService.php`
- `calcularPercepcionesComprobante`: recibe/usa neto GRAVADO como base (RF-V1);
  sin cambio de firma si la base ya llega como parámetro (ajustan los llamadores).

### `PedidoMostradorService` / `PedidoDeliveryService`
- `convertirEnVenta`: cálculo de percepciones (RF-V3) + desglose que cierra con
  descuento de cabecera (RF-V5).

### `VentaService`
- Cortesía legacy (RF-V8, condicionado) y coherencia del orden fiscal (RF-V7).

### `WithPagosDesglose` (concern Livewire)
- `formatearDesgloseParaAFIP` / `recalcularDesgloseIvaFiscal`: separación
  gravado/exento (RF-V4); `aplicarPercepcionFiscal` / `calcularTributosFiscales`:
  base gravada (RF-V1).

---

## Migraciones Necesarias

Ninguna.

---

## Traducciones

Ninguna prevista (no hay UI nueva ni mensajes nuevos). Si la implementación
agrega algún aviso, alta en los 3 idiomas vía `/traducir`.

---

## Criterios de Aceptación

- [ ] V1: venta con ítem gravado $1000 (21%) + ítem exento $500 a cliente
  percibido 3% ⇒ base de percepción = neto gravado de $1000 (≈826,45), NO
  incluye los $500 exentos. Cobrado == facturado. Percepción en `ImpTrib` y
  `comprobante_fiscal_tributos` con esa base.
- [ ] V2: venta con percepción → cambio de FP / reintento de facturación ⇒ la FC
  nueva lleva los MISMOS tributos que la original (ImpTrib > 0, detalle en
  `tributosDetalle`) y su desglose cierra `ImpNeto+ImpOpEx+ImpIVA+ImpTrib=ImpTotal`.
- [ ] V3: pedido (mostrador y delivery) de cliente percibido convertido en venta ⇒
  la venta incluye percepción idéntica a la de una venta directa equivalente; el
  comprobante la lleva en ImpTrib.
- [ ] V4: mismo carrito con ítem 0% facturado por camino frontend y por camino
  service ⇒ misma clasificación (0% en `ImpOpEx`/`neto_exento` en ambos).
- [ ] V5: conversión con descuento de cabecera que genere residuo de redondeo ⇒
  comprobante cierra exacto (sin 10048).
- [ ] V6: venta $1000 facturada parcialmente por $400 ⇒
  `monto_fiscal_cache = 400`, `monto_no_fiscal_cache = 600`; segunda FC por $600 ⇒
  1000/0. Reversas (NC) siguen cerrando.
- [ ] V7: si la transacción del cobro rollbackea después de la emisión, el ledger
  fiscal NO queda registrado; en flujo exitoso el ledger se registra tras el
  commit real (test con rollback forzado).
- [ ] V8: test de reproducción cortesía total + concepto libre por path legacy;
  resultado documentado (fix aplicado o descartado).
- [ ] Sin cambios de UX: smokes existentes de NuevaVenta / Pedidos / Fiscal verdes
  sin modificar interacciones.
- [ ] Suites Venta|Pedido|Fiscal|Impuesto|Comprobante verdes; Pint verde; suite
  completa verde.

---

## Plan de Implementación

### Fase 1: Desglose gravado/exento único (RF-V4 + RF-V1) [COMPLETO]
1. Separación `neto_gravado`/`neto_exento` en `formatearDesgloseParaAFIP` /
   `recalcularDesgloseIvaFiscal` + regla 0%=exento en la rama frontend de
   `ComprobanteFiscalService`.
2. Base de percepción = neto gravado en `aplicarPercepcionFiscal` /
   `calcularTributosFiscales` / `NuevaVenta`.
3. Tests: clasificación 0% por ambos caminos + base de percepción con exentos
   (ampliar `PercepcionFiscalVentaTest`, `DesgloseFiscalRedondeoTest`).

### Fase 2: Re-emisión con tributos (RF-V2) + cache fiscal (RF-V6) [COMPLETO]
1. Snapshot/recalculo de tributos en `emitirFcNuevaPostCommit` y
   `reintentarFacturacionPago`; base −impTrib en `calcularDesgloseIvaProporcional`.
2. `monto_fiscal_cache` por suma de comprobantes vigentes + revisión de reversas.
3. Tests: reintento/cambio FP con percepción; facturación parcial en dos tramos.

### Fase 3: Conversión de pedidos (RF-V3 + RF-V5) [COMPLETO]
1. Percepciones en `convertirEnVenta` (mostrador + delivery) reusando la puerta
   de `NuevaVenta`; definir el punto de cálculo vs pagos planificados.
2. Desglose que cierra con descuento de cabecera.
3. Tests: conversión con cliente percibido (ambos canales) + descuento con residuo.

### Fase 4: Transaccionalidad del ledger (RF-V7) + cortesía legacy (RF-V8) [COMPLETO]
1. `registrarFiscal` a after-commit real (patrón two-phase); revisar llamadores.
2. Test de rollback forzado (ledger no registrado) + flujo exitoso.
3. Reproducción RF-V8; fix o descarte documentado.

### Fase 5: Verificación y cierre [PENDIENTE]
1. `/sdd-verify` (matriz de criterios + suite completa).
2. `@docs-sync` (manual: percepción en pedidos convertidos y base gravada;
   KB: reglas de clasificación 0%, semántica de `monto_fiscal_cache`).
3. PR → master.

---

## Fuera de alcance

- Gross-up RG5003 sobre conceptos con IVA propio (COMPRAS — spec propio).
- `corregirCompra` y contaminación del PPP (COMPRAS — necesita diseño propio).
- Retenciones sufridas desde la UI de compras (mejora con UI nueva — spec propio;
  además violaría la restricción "sin cambios de UX" de esta tanda).
- Padrones por jurisdicción en ventas (sigue esperando definiciones del contador).

---

## Notas y Decisiones

- 2026-07-14 (Fase 4, RF-V7): fix vía `DB::connection('pymes_tenant')
  ->afterCommit(...)` en `registrarFiscal` — sin transacción externa corre
  inmediato (comportamiento previo intacto); con ella, corre tras el commit real
  y se descarta en rollback. No hizo falta reordenar los callers.
- 2026-07-14 (Fase 4, RF-V8): **REPRODUCIDO y corregido.** La cortesía total con
  concepto libre explotaba con "Concepto libre requiere
  _usar_totales_proporcionados=true" (NuevaVenta::procesarVenta, único caller
  legacy, no pasa el flag). Fix mínimo: un concepto libre siempre usa la rama de
  datos proporcionados de crearDetalleVenta (sus datos vienen completos de la
  UI). Hallazgo colateral documentado: la matemática de cabecera del modo legacy
  (calcularTotales suma subtotal con IVA + IVA de nuevo) queda fuera de alcance —
  en la práctica ese path solo se ejercita con cortesías (montos 0).
- 2026-07-14 (Fase 3, hallazgo de implementación): **el pedido MOSTRADOR no
  emite comprobante fiscal en ningún punto de su ciclo** (la emisión de
  mostrador es el pendiente PR2.C/D del spec de pedidos, fuera de esta tanda).
  Sin emisión no hay obligación de percepción ⇒ RF-V3/RF-V5 se implementaron en
  DELIVERY (único canal de conversión que emite FC, rev9). Cuando mostrador
  incorpore su emisión fiscal, debe reusar `percepcionParaConversion` /
  `desgloseIvaProporcional` del service de delivery (extraer a un lugar común).
- 2026-07-14 (Fase 3): la percepción de la conversión se COBRA sumándola al
  pago PLANIFICADO fiscal de mayor monto antes de materializarlo (el total del
  pedido pasa a incluirla, espejo de NuevaVenta). Si los pagos fiscales ya están
  activos (p.ej. cobro en la vuelta del repartidor), la percepción NO se aplica
  (warning en log): nunca se factura un tributo que el cliente no pagó.
- 2026-07-14 (Fase 2/3, guard "no autopercibir"): el recálculo de tributos en
  re-emisiones sin comprobante previo exige EVIDENCIA de cobro (excedente
  `monto_final − base − ajuste − recargo` en los pagos activos) y escala el
  desglose recalculado a ese monto (cobrado manda si la config cambió).
- 2026-07-14: restricción del usuario — esta tanda NO cambia interacción/UX;
  los 2 cambios de monto visibles (RF-V1 percepción menor con exentos, RF-V3
  pedidos que empiezan a percibir) son correcciones esperadas y documentadas.
- 2026-07-14: exploración re-verificó los 11 hallazgos contra master post-#156:
  los 8 de ventas siguen vigentes (ninguno se atenuó con el Bloque A de la tanda 1
  más allá de simplificar RF-V1/V4 al garantizar precio final con IVA); los 3 de
  compras se difieren.
- Origen: auditoría 4 agentes 2026-07-14 (detalle file:line en la sesión; resumen
  en memoria `auditoria-circuito-precios-impuestos`).
