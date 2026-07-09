# Compras → Costos → Precios - Especificación

## Estado: APROBADO — EN IMPLEMENTACIÓN (Fases 1-5 completas)

> Spec creado el 2026-07-01 tras sesión de diseño con el usuario (decisiones D1-D7).
> Es el SDD propio que la Fase 6 del spec `sistema-impositivo.md` dejó como
> prerequisito: "módulo compras depende del modelo de costos, merece su propio SDD".
> Ronda 2026-07-02: D11-D16 (estados desacoplados, cta cte de proveedores completa,
> origen de fondos del pago, toggle no fiscal) + saneamiento ampliado.
> Ronda 2026-07-02 bis (doble revisión: huecos + consistencia vs código real):
> D17-D20 (cancelación con pagos, NC de proveedor en v1, pago por sucursal,
> permiso confirmar) + replanteo total del código de compras (touchpoints como
> único contrato) + fixes de consistencia.
> Ronda 2026-07-09 (revisión impositiva profunda pre-apply, aprobada por el
> usuario): período del crédito = fecha_comprobante, compra_ivas como fuente
> canónica del ledger, factura A a monotributista (RG 5003/2021) como
> advertencia, D21 (fórmulas según condición IVA del comercio), D22 (cuentas
> de compra para reportes), menú padre "Compras" + ABM de proveedores nuevo,
> rename a costo_unitario_computable.
> Pendiente de aprobación del usuario antes de /sdd-apply.

---

## Contexto y Motivación

El sistema tiene el circuito de VENTA completo y maduro: precios finales con IVA
incluido (`articulos.precio_base` + `precio_iva_incluido=true`), listas de precios,
promociones, desglose de IVA en la venta por división (`VentaService.php:313`:
`neto = precio ÷ (1+alic)`), percepciones aplicadas y capa fiscal. Pero el eslabón
de entrada está incompleto:

- **No existe modelo de costos**: el único costo registrado vive en
  `movimientos_stock.costo` (por movimiento). No hay costo del artículo, ni
  historial de costos, ni relación costo→precio.
- **El módulo de compras existe a medias**: `Compra`/`CompraDetalle`/
  `CompraPercepcion` + `CompraService` calculan IVA por renglón y mueven stock,
  pero hay **schema drift** (el SQL base no coincide con lo que los modelos usan),
  no hay descuentos por renglón, ni carga por código de proveedor, ni UI de
  percepciones, y la capa fiscal de compras (`ImpuestoService::registrarDesdeCompra`,
  lista desde la Fase 4 fiscal) no está cableada.
- **No hay puente costo→precio**: la utilidad no existe como dato; los precios se
  ajustan a mano o con el wizard masivo, sin referencia al costo real.
- **No hay cuenta corriente de proveedores**: la deuda vive solo en
  `compras.saldo_pendiente` con un `registrarPago()` rudimentario
  (`CompraService.php:382`, forma de pago hardcodeada 'efectivo', sin ledger
  auditable), y `cancelarCompra()` devuelve saldo a caja mutando directo, sin
  contraasiento de `MovimientoCaja`.
- **Permisos aspiracionales**: `compras.ver/crear/cancelar/pagar` solo existen en
  un docblock — no están sembrados, la ruta no tiene middleware y el componente
  no autoriza. El componente tampoco usa `SucursalAware`/`CajaAware` (resuelve
  sucursal con un TODO, `Compras.php:449`).

Este feature define el circuito completo: compra (fiscal o no) → costo computable
neto → costos del artículo (último/promedio/reposición, por sucursal + consolidado)
→ historial → utilidad objetivo → precio de venta sugerido → revisión/repricing.
Incluye además el lado PAGO (D12): cuenta corriente de proveedores espejo de la
de clientes, con pago al alta o posterior contra el mismo ledger.

---

## Principios de Diseño

1. **El costo se almacena SIEMPRE como costo COMPUTABLE**: neto (sin IVA) cuando
   el IVA fue crédito fiscal recuperable; total pagado cuando no lo fue (por eso
   el campo se llama `computable` y no `neto`). La vista "costo final con IVA"
   se deriva con el `TipoIva` del artículo solo cuando aplica, espejo del
   circuito de venta (que deriva el neto dividiendo el precio final).
2. **Costo computable** (estándar contable/ERP): el IVA solo integra el costo
   cuando NO es recuperable. RI + comprobante que discrimina IVA ⇒ costo = neto
   (el IVA es crédito fiscal). Comprador que no computa crédito (monotributo) o
   comprobante que no discrimina (factura B, compra no fiscal) ⇒ TODO lo pagado
   es costo, sin descomponer IVA teórico (D4). Las percepciones sufridas NO son
   costo para un RI: van al ledger fiscal (ya existente).
3. **Capa de costos desacoplada de compras** (misma filosofía que la capa fiscal):
   `CostoService` es la única puerta de escritura de costos; compras solo lo invoca.
4. **Tres costos en paralelo, uno rector**: `costo_ultimo` (rector para pricing,
   D1), `costo_promedio` (PPP, valuación/CMV), `costo_reposicion` (manual). Se
   persisten y muestran los tres.
5. **Costos por sucursal + consolidado por comercio** (D5): las compras entran por
   sucursal; el consolidado (fila `sucursal_id NULL`) se actualiza con cada compra
   de cualquier sucursal. Prepara la fase futura de transferencias inter-sucursal
   (nota: `proveedores.es_sucursal_interna` + `sucursal_id` ya existen).
6. **Historial append-only de costos**: espejo del patrón `HistorialPrecio` —
   nunca se pierde un cambio de costo ni su origen.
7. **Utilidad = markup % sobre costo neto, UN solo modo en todo el sistema** (D2):
   cascada comercio → categoría → artículo. Equivalentes (coeficiente, margen
   s/venta) solo como columnas informativas derivadas.
8. **El costo nuevo nunca pisa el precio solo** (D3): revisión post-compra con
   preview (patrón `CambioMasivoPrecios`) + flag opt-in por artículo para
   repricing automático. El redondeo se aplica al precio FINAL; se muestra el
   margen real post-redondeo.
9. **Services first, multi-tenant**: todo en `pymes_tenant`, transacciones
   `DB::connection('pymes_tenant')->transaction()`, Livewire solo orquesta.
10. **La deuda con proveedores es un ledger, no un campo** (D12): espejo de la
    cta cte de clientes — append-only, contraasientos, saldo calculado on-the-fly
    sobre movimientos activos (nunca saldo por fila). `compras.saldo_pendiente`
    queda como caché por compra; la fuente de verdad es el ledger.
11. **El estado de la compra es ciclo de vida, no situación de pago** (D11):
    `borrador → completada → cancelada`. Lo impago se deriva de
    `saldo_pendiente > 0`, nunca de un valor de `estado`.

### Fórmulas canónicas

```
Cadena completa del costo (por renglón):
  precio_unitario de factura (neto si el comprobante discrimina IVA; final si no)
    × cascada de descuentos del renglón      (1−d1/100)(1−d2/100)…
    − prorrateo del descuento global del comprobante (por importe)
    + prorrateo de conceptos que computan costo (flete/imp. internos, por importe)
    = costo unitario facturado
    → regla computable: ¿discrimina IVA y el CUIT comprador es RI?
         SÍ ⇒ ya es neto (el IVA fue crédito fiscal, no integra el costo)
         NO ⇒ tal cual: todo lo pagado ES el costo
    ÷ factor de conversión (unidad de compra → unidad de stock, D8)
    = COSTO UNITARIO COMPUTABLE (por unidad de stock; persiste en el renglón)
    (NOTA naming: se llama "computable", NO "neto" — en compras B/C/no fiscales
     contiene el IVA no recuperable. Es correcto contablemente: ese IVA es
     costo real porque la venta genera débito pleno sin crédito que lo compense.)

Descuentos anidados por renglón (D6):
  factor = (1−d1/100) × (1−d2/100) × ... × (1−dn/100)
  precio_efectivo = precio_lista_proveedor × factor

Costo promedio ponderado (PPP), por sucursal y consolidado:
  nuevo_ppp = (stock_previo × ppp_previo + cantidad × costo_unitario_computable)
              / (stock_previo + cantidad)
  (si stock_previo ≤ 0 ⇒ nuevo_ppp = costo_unitario_computable)

Precio sugerido (D2 — el precio del artículo es FINAL con IVA):
  precio_final_sugerido = costo_rector × (1 + utilidad/100) × (1 + alic_efectiva/100)
  → redondeo sobre el precio FINAL

Margen real (inverso, misma división que hace la venta):
  neto_venta  = precio_final ÷ (1 + alic_efectiva/100)
  margen_real = (neto_venta − costo_rector) / costo_rector × 100

alic_efectiva (D21 — condición IVA del COMERCIO):
  CUIT default del comercio es RI (esResponsableInscripto)
      ⇒ alic_efectiva = alícuota del TipoIva del artículo
  comercio que NO computa IVA (monotributo/exento)
      ⇒ alic_efectiva = 0 en AMBAS fórmulas
  (Sin esto, a un monotributista el sugerido le agregaría un ×1,21 que no debe
   a nadie y el margen informado sería 40% cuando el real es ~69%: para un
   no-RI el costo es bruto y TODO el precio de venta es ingreso.)
  Además: si articulo.precio_iva_incluido = false (el precio del artículo se
  almacena NETO), el sugerido se materializa SIN el factor (1+alic) y el margen
  no divide — espejo exacto de VentaService.php:313-315.

Utilidad objetivo (cascada):
  articulo.utilidad_porcentaje ?? categoria.utilidad_porcentaje ?? config.utilidad_default
```

---

## Requisitos Funcionales

### RF-01: Costo computable por renglón de compra
- Al confirmar una compra, cada renglón produce un `costo_unitario_computable`
  según la regla de costo computable (condición IVA del CUIT comprador de la
  compra + tipo de comprobante) y los descuentos anidados aplicados.
- El tipo de comprobante define si discrimina IVA: `factura_a` y `factura_m`
  discriminan (la M se trata como la A para el crédito; las retenciones que la
  M implica quedan manuales en v1 — pregunta anotada para el contador);
  `factura_b`, `factura_c`, remito/no fiscal NO discriminan.
- El campo se llama `computable` (no "neto") a propósito: en compras que no
  discriminan contiene el IVA no recuperable (ver nota en fórmulas canónicas).

### RF-02: Costos del artículo (último / promedio / reposición)
- `costo_ultimo`: se actualiza con cada compra confirmada (por sucursal de la
  compra + fila consolidada). Guarda proveedor, compra y fecha de origen.
- `costo_promedio`: PPP recalculado en la misma confirmación (stock de la sucursal
  para la fila sucursal; stock total del comercio para la consolidada).
  Arranque: si el PPP previo es NULL (catálogo preexistente sin costo), la
  primera compra lo fija = `costo_unitario_computable` (el stock previo sin costo NO
  pondera). Backfill desde `movimientos_stock.costo` queda como opción futura.
- `costo_reposicion`: editable a mano; si es NULL, todo lector usa fallback a
  `costo_ultimo`.
- Costo rector para pricing: `costo_ultimo` (configurable a futuro vía
  `configuracion_costos.costo_rector`, v1 fijo en 'ultimo').

### RF-03: Historial de costos
- Todo cambio de `costo_ultimo` o `costo_reposicion` (por compra, manual o
  importación) registra fila en `historial_costos` con anterior/nuevo/%/origen/
  compra/proveedor/sucursal/usuario. El PPP no se historiza (reconstruible desde
  movimientos de stock; evita ruido de una fila por compra).

### RF-04: Códigos y costos por proveedor
- `articulo_proveedor`: N proveedores por artículo con `codigo_proveedor`,
  último costo neto de ESE proveedor, descuentos habituales (JSON) y fecha de
  última compra. Se actualiza al confirmar compra (upsert).
- En la carga de compra se puede buscar el artículo por código del proveedor
  seleccionado (además de por código/nombre propio). Los descuentos habituales
  del proveedor se precargan en el renglón (editables).
- Código duplicado: un mismo `codigo_proveedor` apuntando a 2+ artículos no se
  puede bloquear por UNIQUE — validación de aplicación al asignarlo (advertencia)
  y, si la búsqueda devuelve varios, selector en lugar de autocompletar.

### RF-05: Descuentos anidados por renglón + descuento global
- Cada renglón admite lista ordenada de descuentos % en cascada sobre el neto
  del renglón. Se persisten la lista, el monto total de descuento y el costo
  unitario efectivo resultante.
- El comprobante admite además un descuento global (al pie), que se PRORRATEA a
  los renglones por importe para que el costo unitario sea el real. Se persisten
  el % / monto global en el encabezado y el monto asignado en cada renglón.

### RF-06: Compra fiscal / no fiscal
- La compra selecciona proveedor + tipo de comprobante + CUIT del comercio
  (atribución fiscal, ya existe). El tipo define la regla de costo computable
  (RF-01) y si genera crédito fiscal para el ledger.
- **Toggle "compra no fiscal"** (D15): materializa `tipo_comprobante` no fiscal
  y desactiva TODO el cálculo de impuestos — sin desglose de IVA por renglón,
  sin `compra_ivas`/netos, sin percepciones, nada al ledger fiscal. El renglón
  carga el precio final pagado directo y ese total ES el costo (regla D4). La UI
  oculta las secciones fiscales cuando el toggle está activo.
- Validación comprobante×CUIT: **ADVERTENCIA no bloqueante** (corregido
  2026-07-09). Desde la RG 5003/2021 un monotributista SÍ recibe factura A
  (con leyenda "Receptor Responsable Monotributo") — bloquearla impediría
  cargar facturas reales; `CondicionIva` ya lo documenta (códigos 6/13/16
  válidos para factura A). La matriz dura es SOLO la regla de costo computable:
  factura A + comprador no-RI ⇒ discrimina pero SIN crédito fiscal, todo lo
  pagado es costo. La advertencia señala combinaciones atípicas (ej. factura B
  a un RI) sin impedir la carga.
- **Gate del crédito fiscal (explícito)**: se envía `$ivaCredito` al ledger
  SOLO si `fiscal AND el tipo discrimina AND el CUIT comprador es RI
  (esResponsableInscripto)`. OJO: `ImpuestoService::registrarDesdeCompra` NO
  gatea por condición IVA (a diferencia del débito, que sí gatea RI en
  `registrarDesdeComprobante`, ImpuestoService.php:441) — el gate es
  responsabilidad del `CompraService` (caller), documentado en su contrato.
- **Fuente canónica del crédito = `compra_ivas`** (el desglose de la factura
  física, RF-14), NUNCA la suma de renglones. El array `$ivaCredito` (con
  base_imponible/alicuota/monto por alícuota) se arma desde `compra_ivas`.
- **Período del crédito = `fecha_comprobante`**: `registrarDesdeCompra` hoy usa
  `$compra->fecha ?? now()` (ImpuestoService.php:540) — se ajusta en la Fase 4
  para preferir `fecha_comprobante` (y `compras.fecha` se sanea de su
  `ON UPDATE CURRENT_TIMESTAMP`, RF-12, que hoy la re-pisa en cada update).
  Sin esto, una factura de junio cargada en julio computa el crédito en el
  período equivocado y el Libro IVA Compras no cuadra con la posición.
- `fecha_comprobante` es OBLIGATORIA en compras fiscales (rige el período del
  crédito); solo puede ser NULL con el toggle no fiscal. Si cae en un período
  fiscal viejo/ya presentado: advertencia NO bloqueante (AFIP admite computar
  crédito tardío; decide el contador).
- Percepciones con comprador no-RI: se cargan solo informativas (suman al total
  y a la deuda), sin ledger fiscal (v1; anotar si el contador pide otro
  tratamiento).
- Percepciones sufridas: UI para cargar `compra_percepciones` (impuesto del
  catálogo, base, alícuota, monto); al confirmar se cablea
  `ImpuestoService::registrarDesdeCompra()` (crédito de IVA por alícuota +
  percepciones → ledger, RF-05 del spec fiscal). Al cancelar,
  `anularDesdeCompra()`. NOTA (revisión Fable 2026-07-01): la cancelación
  cross-período debe seguir el patrón NC (reversa negativa en su período) —
  ajustar `anularDesdeCompra` en este feature.

### RF-07: Cancelación de compra y costos
- Si la compra cancelada es la que fijó el `costo_ultimo` vigente, se restaura el
  costo anterior desde `historial_costos` (con fila nueva de historial, origen
  'cancelacion'). El PPP NO se recalcula hacia atrás (nota documentada; la
  próxima compra lo corrige). `articulo_proveedor` no se revierte.

### RF-08: Utilidad objetivo en cascada
- `configuracion_costos.utilidad_default` (comercio) → `categorias.utilidad_porcentaje`
  (override opcional) → `articulos.utilidad_porcentaje` (override opcional).
- Editable desde: configuración de comercio, ABM de categorías y ABM de artículos.

### RF-09: Margen real y precio sugerido
- Para cada artículo (y sucursal): margen real calculado con la fórmula inversa
  sobre el precio final efectivo (`obtenerPrecioBaseEfectivo`) y el costo rector.
- Sin fila de costos en la sucursal ⇒ fallback a la consolidada (`sucursal_id
  NULL`); sin costo alguno ⇒ margen NULL (la UI y el semáforo lo toleran).
- Columnas informativas derivadas: coeficiente (final/costo neto) y margen sobre
  venta. Vista de costos/margen en GestionarArticulos (columna + detalle).

### RF-10: Revisión de precios post-compra
- Al confirmar una compra que cambió costos, se listan los artículos cuyo margen
  real quedó por debajo de la utilidad objetivo: costo nuevo, precio actual,
  margen real, precio sugerido (con redondeo configurable, patrón
  `CambioMasivoPrecios`), y aplicación en lote → escribe precio + `HistorialPrecio`.
- Alcance del precio aplicado: si el artículo tiene override de precio en la
  sucursal de la compra se actualiza ese override; si no, el precio global.
- La revisión es RETOMABLE: se reabre desde el detalle de la compra y calcula
  SIEMPRE contra el costo y precio VIGENTES (no snapshot); si otra compra
  posterior ya cambió el costo, la revisión lo refleja.

### RF-11: Repricing automático opt-in
- `articulos.precio_administrado_por_utilidad` (default false): al confirmar la
  compra, esos artículos se repricean solos con la fórmula de precio sugerido +
  redondeo, registrando en `HistorialPrecio` (origen 'utilidad_automatica'), y se
  informan en el resumen de la compra.
- Alcance del precio escrito: misma regla que RF-10 (override sucursal si existe,
  si no el global — aceptado que una compra en la sucursal A puede repricear el
  precio global que también rige en B).

### RF-13: Identidad del comprobante del proveedor
- La compra persiste el número REAL del comprobante del proveedor
  (`numero_comprobante_proveedor`, formato libre ej. `0003-00012345`) además del
  número interno autogenerado. Anti-duplicado
  (`proveedor_id`,`tipo_comprobante`,`numero_comprobante_proveedor`): validación
  de aplicación que EXCLUYE canceladas (permite recargar una factura tras
  cancelarla, RF-17) — no se puede cargar dos veces la misma factura activa
  (NULL permitido para compras internas sin comprobante).
- Fechas: `fecha_comprobante` (la de la factura — rige el período fiscal del
  crédito en el ledger), `fecha_vencimiento` (aging de deuda en cta cte) y
  `created_at` (carga).

### RF-14: Desglose de IVA y totales del comprobante
- Tabla `compra_ivas` (espejo de `ComprobanteFiscalIva`): base imponible e importe
  por alícuota. Encabezado con `neto_gravado`, `neto_no_gravado`, `neto_exento`.
- **Es la fuente CANÓNICA del crédito fiscal** (alimenta `$ivaCredito` de
  `registrarDesdeCompra`) y del Libro IVA Compras — simetría exacta con el
  débito, que sale de `comprobante_fiscal_iva`. Así el Libro cuadra contra la
  factura física sin depender de derivar del detalle (redondeos, conceptos).
- Carga: se PRE-SUGIERE desde los renglones (tipo_iva_id × bases con descuentos
  aplicados) + conceptos gravados (RF-15), y es EDITABLE para calzar con la
  factura física. Validación de cuadre no bloqueante: si
  `Σ(renglones+conceptos)` difiere de `compra_ivas` por más que una tolerancia
  de redondeo (± $1 por alícuota), advertir antes de confirmar.
- El descuento global (RF-05) reduce las bases: la sugerencia calcula el IVA
  DESPUÉS de aplicar cascada de renglón + prorrateo del descuento global (la
  factura real ya lo trae así).

### RF-15: Conceptos de pie de factura (D9)
- Tabla `compra_conceptos`: renglones no-artículo de la factura (flete, impuestos
  internos, envases, otros) con monto y flag `computa_costo`.
- `monto` sigue la MISMA base que los renglones: NETO si el comprobante
  discrimina IVA, final si no (2026-07-09). El flete de una factura A viene
  neto y su IVA vive en el desglose del pie ⇒ en `compra_ivas` (RF-14).
- `tipo_iva_id` (NULL = no gravado/exento, ej. impuestos internos): permite que
  la SUGERENCIA automática de `compra_ivas` incluya los conceptos gravados y
  cierre contra la factura. No genera cálculo propio — solo alimenta la
  sugerencia editable.
- Los que computan costo se PRORRATEAN a los renglones por importe (landed cost).
  Caso argentino clave: impuestos internos de bebidas SON costo real (no son IVA
  ni percepción — no se recuperan).
- Los que no computan (según flag) solo completan el total del comprobante.

### RF-16: Unidades de compra vs unidades de stock (D8)
- `articulo_proveedor.factor_conversion` (default 1): se compra en "bultos" del
  proveedor y se stockea en unidades propias. El renglón persiste
  `cantidad_comprada` (bultos) y `cantidad` (stock = comprada × factor).
- Costo unitario computable = costo del bulto ÷ factor (siempre por unidad de STOCK).

### RF-17: Flujo borrador → completada → cancelada (D10 + D11)
- `estado` pasa a ser SOLO ciclo de vida: `enum('borrador','completada','cancelada')`.
  Lo impago NO es un estado: se deriva de `saldo_pendiente > 0` (D11).
- Migración de datos: las compras `pendiente` existentes (cta cte impagas, que YA
  movieron stock) pasan a `completada` conservando su `saldo_pendiente`; ninguna
  debe quedar interpretable como borrador. Se elimina la lógica
  `estado='pendiente' si forma_pago=cta_cte` (`CompraService.php:80`).
- `borrador`: editable, SIN efectos (no toca stock, costos, ledger ni caja). Una
  factura larga se carga en varias sesiones. Se elimina directamente sin reversas.
- Confirmar (→ `completada`): dispara en una transacción stock + CostoService +
  ImpuestoService + cta cte proveedor (RF-18) + pago inicial (RF-19) + repricing
  automático. Idempotente (todos los services ya lo son).
- Cancelar (→ `cancelada`): reversas de stock/costos/fiscal/cta cte
  (RF-06/RF-07/RF-18), todas por contraasiento.
- Una `completada` es INMUTABLE salvo campos sin efecto (observaciones,
  `fecha_vencimiento`); no vuelve a borrador — corregir = cancelar + recargar.
  Por eso el anti-duplicado (RF-13) pasa a validación de APLICACIÓN que excluye
  `estado='cancelada'` (un UNIQUE puro de BD impediría recargar la misma factura
  tras cancelarla); queda índice normal para la búsqueda.
- Cancelar una compra CON pagos aplicados (D17): el usuario elige — anular los
  pagos en cascada (contraasientos por origen; caso error de carga sin pago real)
  o dejarlos como saldo a favor del proveedor (caso plata que realmente salió).
  Si algún pago tiene origen caja con turno cerrado, la cascada se bloquea y
  solo queda la vía saldo a favor.

### RF-18: Cuenta corriente de proveedores (D12)
- Ledger append-only `movimientos_cuenta_corriente_proveedor`, espejo de
  `movimientos_cuenta_corriente` (clientes). Semántica contable de PASIVO:
  **HABER = aumenta la deuda con el proveedor** (compra), **DEBE = la reduce**
  (pago). Saldo = Σhaber − Σdebe sobre movimientos `activo`, on-the-fly (patrón
  exacto de `MovimientoCuentaCorriente`, incl. `calcularSaldo*`).
- Anulaciones por contraasiento (patrón `crearContraasiento`): fila inversa +
  `anulado_por_movimiento_id`; ambas quedan `activo` y se cancelan matemáticamente.
- `proveedores.tiene_cuenta_corriente` habilita el circuito. Proveedor sin CC:
  la compra solo admite contado y no genera filas de ledger (comportamiento actual).
- Al confirmar compra de proveedor CC (espejo invertido de
  `VentaService::procesarPagosCuentaCorriente`): HABER por el total + DEBE por lo
  pagado en el momento. Contado total ⇒ par haber/debe con saldo 0 (el extracto
  del proveedor queda completo); cta cte ⇒ solo HABER (+ DEBE parcial si hay pago
  inicial, RF-19).
- Anticipos y saldo a favor: mismos tipos que clientes (`anticipo`,
  `uso_saldo_favor`) — un pago sin compras genera saldo a favor NUESTRO con el
  proveedor, consumible en pagos posteriores.
- Caches (patrón Cliente, con `lockForUpdate`): `proveedores.saldo_cache`,
  `ultimo_movimiento_ccp_at`. Sin `limite_credito` (ese límite lo administra el
  proveedor); en su lugar `proveedores.dias_pago` (NULL = sin default) precarga
  `fecha_vencimiento` de la compra.
- Alcance sucursal (D19): la operatoria de pago es POR SUCURSAL ACTIVA (espejo
  estricto de clientes) — compras pendientes, FIFO y aging de la sucursal activa.
  `saldo_cache` es el consolidado informativo del comercio; el saldo por
  sucursal se calcula del ledger (índice proveedor+sucursal+estado).
- v1 sin moneda extranjera en este ledger (se omiten las columnas de snapshot ME
  de clientes; agregar si compras en ME llegan).

### RF-19: Pagos a proveedores (D12)
- TODO pago pasa por `PagoProveedorService` (espejo de `CobroService`): orden de
  pago `pagos_proveedores` (número `OP-{suc}-{8 díg}`) + aplicación a compras
  (`pago_proveedor_compras`: parcial, a varias compras, FIFO o manual) + desglose
  de formas de pago (`pago_proveedor_pagos`).
- **Origen de fondos** (D14): por default el pago sale de la **caja activa**
  (CajaAware), validando caja abierta y saldo suficiente (efectivo no puede
  quedar negativo, regla ya existente de `Caja`). Con el permiso especial
  `compras.pagar_avanzado` el usuario puede además elegir por renglón del
  desglose: **otra caja** de la sucursal, **efectivo de Tesorería**
  (`MovimientoTesoreria` egreso — requiere nuevo
  `TesoreriaService::registrarEgresoExterno()`, espejo del ingreso externo
  existente, referencia `pago_proveedor`) o **cuenta de empresa** vía
  transferencia (`CuentaEmpresaService::registrarMovimientoAutomatico`, egreso).
  En todos los casos se valida saldo del origen antes de confirmar.
- En el alta de la compra (RF-17): contado (FP inmediata) o cta cte; con cta cte
  puede registrarse opcionalmente un **pago inicial parcial**, que se materializa
  como PagoProveedor dentro de la misma confirmación (un solo camino de escritura).
  Validación: `0 < pago_inicial < total` (igual al total ES contado; mayor = error).
  El bloque de pago embebido en la pantalla de compras usa la **caja activa de la
  sesión** (el componente de compra NO es CajaAware — solo su bloque de pago lee
  la caja activa y valida apertura+saldo al confirmar) y `compras.pagar_avanzado`
  habilita ahí los mismos orígenes alternativos.
- Proveedor SIN cta cte: el contado genera igualmente PagoProveedor + egreso por
  origen (rastro auditable); solo se omite el ledger de cta cte (RF-18).
- Pantalla de pagos (espejo de `GestionarCobranzas`): compras pendientes del
  proveedor con aging por `fecha_vencimiento`, selección FIFO/manual, pago
  parcial, anticipo sin compras, estado de cuenta (extracto con saldo acumulado
  en memoria), anulación de orden de pago (contraasientos de ledger + caja +
  reversa de `saldo_pendiente`; bloqueada si el turno de caja está cerrado,
  patrón `CobroService::anularCobro`).
- Deprecación: reemplaza `CompraService::registrarPago()` actual (hardcodea
  forma de pago 'efectivo', `CompraService.php:413`, y no deja rastro auditable).

### RF-20: Permisos y menú (formalización)
- Crear formalmente `compras.ver/crear/cancelar/pagar` (hoy solo docblock):
  migración de permisos idempotente + `ProvisionComercioCommand::seedRolesYPermisos()`
  + middleware `permission:` en rutas + `hasPermissionTo()` en componentes.
- `compras.confirmar` (D20), separado de `compras.crear`: cargar/editar
  borradores requiere `crear`; confirmar (mueve stock/costos/ledger/plata)
  requiere `confirmar`. En comercios chicos ambos van al mismo rol.
- Mapa permiso→rol default: se define en Fase 1 con el usuario (propuesta base:
  todos a admin; `crear` también al rol operativo).
- Rutas nuevas (`/pagos-proveedores`) con middleware `permission:`; componentes
  full-page nuevos con `#[Lazy]` + skeleton (checklist de módulo).
- Permisos nuevos: `costos.ver` (costos y márgenes en artículos — dato sensible:
  sin él no se muestran columnas ni modales de costo), `costos.editar` (costo
  manual/reposición y utilidad), `compras.revisar_precios` (aplicar RF-10/RF-11),
  `compras.pagar_avanzado` (D14: elegir otra caja, efectivo de Tesorería o
  cuenta de empresa como origen del pago; sin él, solo la caja activa),
  `compras.proveedores` (ABM de proveedores) y `compras.reportes` (RF-22).
- **Menú: grupo PADRE nuevo "Compras"** (2026-07-09, pedido del usuario —
  verificado: hoy NO existe ningún ítem de compras en `menu_items`; la ruta
  `compras.index` está huérfana de menú). Estructura (patrón de los grupos
  existentes, ej. Ventas id=33; slug UNIQUE, migración idempotente):
  - **Compras** (padre, sin ruta, orden entre Stock y Clientes)
    - Compras → `compras.index` (orden 1)
    - Proveedores → `compras.proveedores` (orden 2) — componente NUEVO
      `GestionarProveedores` (hoy no existe ABM: los proveedores solo aparecen
      en un select dentro de Compras)
    - Pagos a proveedores → `compras.pagos-proveedores` (orden 3)
    - Reportes → `compras.reportes` (orden 4, RF-22)

### RF-21: Nota de crédito de proveedor — devolución parcial (D18)
- La NC es una fila más de `compras` con `tipo_comprobante` NC (fiscal A/B/C o
  no fiscal) + `compra_origen_id` (FK a la compra original; NULL si es NC suelta,
  ej. descuento financiero posterior). Mismo flujo borrador→completada→cancelada
  y mismo anti-duplicado (proveedor+tipo+número).
- Efectos al confirmar (inversos y PARCIALES, por renglón cargado):
  - **Stock**: egreso por las cantidades devueltas (en unidades de stock).
  - **Fiscal**: la NC física trae su PROPIO desglose de IVA ⇒ la NC carga sus
    propias filas de `compra_ivas` (del documento real) y ESE desglose alimenta
    la reversa del crédito (en negativo), registrada en el PERÍODO de la NC
    (patrón NC cross-período de la revisión Fable). La derivación proporcional
    desde la compra origen queda solo como PRECARGA sugerida, editable
    (2026-07-09 — antes decía "reversa proporcional", que era una aproximación).
  - **Costos**: NO recalcula `costo_ultimo` ni PPP (una devolución parcial no
    restaura el costo anterior; misma filosofía documentada de RF-07).
  - **Cta cte**: tipo `nota_credito`, DEBE que baja el `saldo_pendiente` de la
    compra origen hasta cubrirlo; el excedente (o una NC suelta) genera saldo a
    favor (`saldo_favor_haber`).
- Cancelar una NC confirmada revierte sus efectos por contraasiento.

### RF-22: Cuentas de compra — agrupación para reportes (D22)
- Catálogo `cuentas_compra` por comercio (ABM simple: nombre, orden, activo),
  seed inicial editable: Mercadería, Insumos, Servicios, Gastos generales.
  Es una agrupación de gestión (NO plan de cuentas contable formal): sirve
  para responder "¿cuánto gasté en qué?" por período.
- **Configuración: default por PROVEEDOR + override por COMPRA** (decisión
  2026-07-09): `proveedores.cuenta_compra_id` (NULL = sin default) precarga
  `compras.cuenta_compra_id` al elegir proveedor; editable en el encabezado de
  la compra. Por artículo NO (un artículo casi siempre es "mercadería" y no
  tipifica el gasto; el proveedor sí lo tipifica). NULL permitido = "sin
  clasificar" (los reportes lo muestran como categoría propia para ir saneando).
- Las NC heredan la cuenta de su compra origen (editable); restan en el reporte.
- **Reporte "Compras por cuenta"** (pantalla Reportes del grupo Compras):
  período + sucursal → total por cuenta (compras completadas − NC), con
  drill-down a las compras. Cortes secundarios: por proveedor y por mes.
  Patrón visual: `ReportesTesoreria`.
- Futuro anotado (no bloquear): split por RENGLÓN para facturas mixtas
  (mercadería + gastos en un mismo comprobante) — la columna en el encabezado
  no lo impide, se agregaría `cuenta_compra_id` NULL en el detalle que
  prevalece sobre el encabezado.

### RF-12: Replanteo del schema y módulo de compras (prerequisito)
- **El código actual de compras se REESCRIBE** (directiva 2026-07-02):
  `CompraService`, componente `Compras` y modelos se replantean sin obligación
  de compatibilidad con su lógica interna. Los contratos que SÍ se respetan son
  los touchpoints reales (verificados contra el código): stock
  (`MovimientoStock`), precios (`PrecioService`/`HistorialPrecio`/
  `obtenerPrecioBaseEfectivo`), cta cte de clientes (patrón a espejar),
  caja/tesorería/cuentas (`MovimientoCaja`/`TesoreriaService`/
  `CuentaEmpresaService`) y fiscal (`ImpuestoService`).
- Schema final limpio según este spec; migración idempotente que lleva las
  tablas reales al schema final PRESERVANDO los datos existentes (lección
  fiscal: el try/catch tenant convierte errores en no-ops — verificar el efecto
  real en BD por comercio) + regenerar `tenant_tables.sql`.
- Saneos concretos del schema actual: `numero`→`numero_comprobante`,
  `iva`→`total_iva`, agregar `tipo_comprobante`/`saldo_pendiente`/
  `observaciones`/`tipo_iva_id`/`precio_sin_iva`; **`compras.fecha` es
  `timestamp ON UPDATE CURRENT_TIMESTAMP`** (se pisa sola en cada update — y es
  la fecha que HOY usa el ledger fiscal, ImpuestoService.php:540) →
  pasar a `date` sin ON UPDATE; dropear `compras.caja_id` (huérfana con D14);
  `forma_pago` set final (o FK `formas_pago`,
  decidir en Fase 1 — el SQL base y el código hoy usan dos sets distintos);
  estados D11 con mapeo `pendiente`→`completada`.
- El componente reescrito es `SucursalAware` (NO CajaAware, D14) y aplica los
  permisos RF-20. Los costos salen de `articulo_costos` (la relación `precios()`
  que usaba el componente viejo no existe).

---

## Modelo de Datos

### Tablas nuevas (todas tenant, prefijo `{NNNNNN}_`)

#### `articulo_costos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `articulo_id` | bigint unsigned | — | FK articulos, ON DELETE CASCADE |
| `sucursal_id` | bigint unsigned NULL | NULL | FK sucursales; NULL = consolidado comercio |
| `costo_ultimo` | decimal(12,4) NULL | NULL | Neto computable de la última compra confirmada |
| `costo_promedio` | decimal(12,4) NULL | NULL | PPP |
| `costo_reposicion` | decimal(12,4) NULL | NULL | Manual; NULL ⇒ fallback a costo_ultimo |
| `proveedor_ultimo_id` | bigint unsigned NULL | NULL | FK proveedores (origen del último) |
| `compra_ultima_id` | bigint unsigned NULL | NULL | FK compras (origen del último) |
| `fecha_costo_ultimo` | timestamp NULL | NULL | |
| `created_at/updated_at` | timestamp NULL | | |

UNIQUE (`articulo_id`,`sucursal_id`); KEY (`sucursal_id`).
(4 decimales: el PPP y los descuentos en cascada generan fracciones; el precio se
redondea recién al final de la cadena.)

#### `historial_costos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `articulo_id` | bigint unsigned | — | FK articulos, CASCADE |
| `sucursal_id` | bigint unsigned NULL | NULL | NULL = consolidado |
| `tipo_costo` | enum('ultimo','reposicion') | — | El PPP no se historiza |
| `costo_anterior` | decimal(12,4) NULL | NULL | |
| `costo_nuevo` | decimal(12,4) | — | |
| `porcentaje_cambio` | decimal(8,2) NULL | NULL | |
| `origen` | enum('compra','manual','importacion','cancelacion') | — | |
| `compra_id` | bigint unsigned NULL | NULL | |
| `proveedor_id` | bigint unsigned NULL | NULL | |
| `usuario_id` | bigint unsigned NULL | NULL | Sin FK (users en config) |
| `detalle` | varchar(255) NULL | NULL | |
| `created_at` | timestamp | — | `UPDATED_AT = null` (patrón HistorialPrecio) |

KEY (`articulo_id`,`created_at`), KEY (`compra_id`).

#### `articulo_proveedor`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `articulo_id` | bigint unsigned | — | FK articulos, CASCADE |
| `proveedor_id` | bigint unsigned | — | FK proveedores, CASCADE |
| `codigo_proveedor` | varchar(50) NULL | NULL | Código del artículo en el catálogo del proveedor |
| `factor_conversion` | decimal(10,4) | 1.0000 | Unidades de stock por unidad de compra (D8: bulto x12 ⇒ 12) |
| `descuentos_habituales` | json NULL | NULL | Lista ordenada de % (se precargan en el renglón) |
| `costo_ultimo` | decimal(12,4) NULL | NULL | Último neto computable de ESTE proveedor |
| `fecha_ultima_compra` | timestamp NULL | NULL | |
| `activo` | tinyint(1) | 1 | |
| `created_at/updated_at` | timestamp NULL | | |

UNIQUE (`articulo_id`,`proveedor_id`); KEY (`proveedor_id`,`codigo_proveedor`) para
búsqueda por código en la carga.

#### `compra_ivas` (RF-14, espejo de `comprobante_fiscal_iva`)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `compra_id` | bigint unsigned | — | FK compras, CASCADE |
| `alicuota` | decimal(5,2) | — | 21.00 / 10.50 / 27.00 / 0.00 |
| `base_imponible` | decimal(12,2) | — | Neto gravado a esa alícuota |
| `importe` | decimal(12,2) | — | IVA de esa alícuota |
| `created_at/updated_at` | timestamp NULL | | |

KEY (`compra_id`).

#### `compra_conceptos` (RF-15, D9)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `compra_id` | bigint unsigned | — | FK compras, CASCADE |
| `tipo` | enum('flete','impuestos_internos','envases','otro') | 'otro' | |
| `descripcion` | varchar(150) NULL | NULL | |
| `monto` | decimal(12,2) | — | Misma base que los renglones: neto si el comprobante discrimina (RF-15) |
| `tipo_iva_id` | bigint unsigned NULL | NULL | FK tipos_iva; NULL = no gravado. Alimenta la sugerencia de compra_ivas |
| `computa_costo` | tinyint(1) | 0 | true ⇒ se prorratea a los renglones por importe |
| `created_at/updated_at` | timestamp NULL | | |

KEY (`compra_id`).

#### `cuentas_compra` (RF-22, D22)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `nombre` | varchar(100) | — | |
| `orden` | int | 0 | |
| `activo` | tinyint(1) | 1 | |
| `created_at/updated_at` | timestamp NULL | | |

Seed inicial (editable): Mercadería, Insumos, Servicios, Gastos generales
(+ ProvisionComercioCommand).

#### `configuracion_costos` (una fila por comercio)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `utilidad_default` | decimal(6,2) | 30.00 | Markup % por defecto del comercio |
| `costo_rector` | enum('ultimo','promedio','reposicion') | 'ultimo' | v1 fijo en 'ultimo' (UI de solo lectura) |
| `created_at/updated_at` | timestamp NULL | | |

#### `movimientos_cuenta_corriente_proveedor` (RF-18, espejo de `movimientos_cuenta_corriente`)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `proveedor_id` | bigint unsigned | — | FK proveedores |
| `sucursal_id` | bigint unsigned | — | FK sucursales |
| `fecha` | date | — | |
| `tipo` | enum('compra','pago','anticipo','uso_saldo_favor','nota_credito','devolucion_saldo','anulacion_compra','anulacion_pago','ajuste_debito','ajuste_credito') | — | |
| `debe` | decimal(12,2) | 0 | Reduce la deuda (pago, NC del proveedor) |
| `haber` | decimal(12,2) | 0 | Aumenta la deuda (compra) — semántica pasivo |
| `saldo_favor_debe` | decimal(12,2) | 0 | Consume saldo a favor nuestro |
| `saldo_favor_haber` | decimal(12,2) | 0 | Genera saldo a favor nuestro (anticipo) |
| `documento_tipo` | enum('compra','pago','pago_compra','ajuste') | — | |
| `documento_id` | bigint unsigned | — | Polimórfico |
| `compra_id` | bigint unsigned NULL | NULL | FK compras, SET NULL |
| `pago_proveedor_id` | bigint unsigned NULL | NULL | FK pagos_proveedores, SET NULL |
| `concepto` | varchar(255) | — | |
| `observaciones` | text NULL | NULL | Motivo de anulación |
| `estado` | enum('activo','anulado') | 'activo' | Contraasientos: AMBOS quedan activos |
| `anulado_por_movimiento_id` | bigint unsigned NULL | NULL | Self-FK al contraasiento, SET NULL |
| `usuario_id` | bigint unsigned | — | Sin FK (users en config) |
| `created_at/updated_at` | timestamp NULL | | |

Índices espejo: (`proveedor_id`,`sucursal_id`,`estado`), (`proveedor_id`,`fecha`),
(`documento_tipo`,`documento_id`), (`tipo`,`estado`), (`compra_id`),
(`pago_proveedor_id`). Sin columnas de snapshot ME (v1, ver RF-18).

#### `pagos_proveedores` (RF-19, análogo de `cobros`)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `numero` | varchar(20) | — | `OP-{suc}-{8 díg}` (patrón generarNumeroRecibo) |
| `proveedor_id` | bigint unsigned | — | FK proveedores |
| `sucursal_id` | bigint unsigned | — | FK sucursales |
| `caja_id` | bigint unsigned NULL | NULL | FK cajas |
| `fecha` | date | — | |
| `monto_total` | decimal(12,2) | — | |
| `saldo_favor_usado` | decimal(12,2) | 0 | |
| `monto_a_favor` | decimal(12,2) | 0 | Excedente → anticipo |
| `tipo` | enum('pago','anticipo') | 'pago' | |
| `observaciones` | text NULL | NULL | |
| `estado` | enum('activo','anulado') | 'activo' | |
| `motivo_anulacion` | varchar(255) NULL | NULL | |
| `anulado_por_usuario_id` | bigint unsigned NULL | NULL | Auditoría (patrón cobros) |
| `anulado_at` | timestamp NULL | NULL | |
| `cierre_turno_id` | bigint unsigned NULL | NULL | |
| `usuario_id` | bigint unsigned | — | |
| `created_at/updated_at` | timestamp NULL | | |

#### `pago_proveedor_compras` (análogo de `cobro_ventas`)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `pago_proveedor_id` | bigint unsigned | — | FK pagos_proveedores, CASCADE |
| `compra_id` | bigint unsigned | — | FK compras |
| `monto_aplicado` | decimal(12,2) | — | Baja `compras.saldo_pendiente` |
| `saldo_anterior` | decimal(12,2) | — | Snapshot de auditoría (patrón cobro_ventas) |
| `saldo_posterior` | decimal(12,2) | — | |
| `created_at/updated_at` | timestamp NULL | | |

#### `pago_proveedor_pagos` (desglose de formas de pago, análogo de `cobro_pagos`)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `pago_proveedor_id` | bigint unsigned | — | FK pagos_proveedores, CASCADE |
| `forma_pago_id` | bigint unsigned | — | FK formas_pago |
| `monto` | decimal(12,2) | — | |
| `origen` | enum('caja','tesoreria','cuenta_empresa') | 'caja' | D14: de dónde salen los fondos |
| `caja_id` | bigint unsigned NULL | NULL | FK cajas (origen 'caja'; default la activa) |
| `cuenta_empresa_id` | bigint unsigned NULL | NULL | FK cuentas_empresa (origen 'cuenta_empresa') |
| `movimiento_caja_id` | bigint unsigned NULL | NULL | FK al egreso generado (para contraasentar exacto al anular) |
| `movimiento_cuenta_empresa_id` | bigint unsigned NULL | NULL | Ídem, origen cuenta |
| `movimiento_tesoreria_id` | bigint unsigned NULL | NULL | Ídem, origen tesorería |
| `cierre_turno_id` | bigint unsigned NULL | NULL | POR RENGLÓN (D16: turno cerrado bloquea solo renglones caja) |
| `estado` | enum('activo','anulado') | 'activo' | |
| `created_at/updated_at` | timestamp NULL | | |

(Espejo fiel de `cobro_pagos`, que guarda movimiento/estado/cierre_turno por
renglón — sin esas FKs la anulación por origen y D16 no son implementables.
Origen 'tesoreria' usa la Tesorería de la sucursal del pago — no necesita FK
de cuenta. Orígenes distintos de 'caja' requieren `compras.pagar_avanzado`.)

### Tablas modificadas

#### `compras_detalle` — Cambios
- Agregar: `descuentos` (json NULL) — lista ordenada de % en cascada
- Agregar: `descuento_monto` (decimal(12,2), 0) — total descontado del renglón (cascada propia)
- Agregar: `descuento_global_monto` (decimal(12,4), 0) — porción prorrateada del descuento global (RF-05)
- Agregar: `conceptos_costo_monto` (decimal(12,4), 0) — porción prorrateada de conceptos que computan costo (RF-15)
- Agregar: `cantidad_comprada` (decimal(12,3) NULL) + `factor_conversion` (decimal(10,4), 1) —
  RF-16; `cantidad` (existente) queda SIEMPRE en unidades de stock (= comprada × factor),
  así el código de stock no cambia
- Agregar: `codigo_proveedor_usado` (varchar(50) NULL) — trazabilidad de carga por código
- Agregar: `costo_unitario_computable` (decimal(12,4) NULL) — resultado final de la
  cadena (por unidad de stock). Renombrado de "neto" el 2026-07-09: en compras
  B/C/no fiscales contiene el IVA no recuperable (ver fórmulas canónicas)
- Reconciliar drift (RF-12): `tipo_iva_id` (FK tipos_iva NULL), `precio_sin_iva`
  (decimal(12,2) NULL) — el modelo ya los usa.

#### `compras` — Reconciliación drift (RF-12) + encabezado completo (RF-13/14)
- Alinear con el modelo: `numero` → `numero_comprobante`, `iva` → `total_iva`,
  agregar `tipo_comprobante` (varchar), `saldo_pendiente` (decimal(12,2), caché —
  fuente de verdad: ledger RF-18), `observaciones` (text NULL), y reconciliar
  `forma_pago` completo (set final o FK `formas_pago`, ver RF-12).
  (Migración idempotente columna-por-columna; verificar estado real por comercio.)
- Estados D11: `estado` → `enum('borrador','completada','cancelada')` + mapeo de
  datos `pendiente` → `completada` (conservan `saldo_pendiente`).
- Agregar (RF-13): `numero_comprobante_proveedor` (varchar(20) NULL);
  anti-duplicado (`proveedor_id`,`tipo_comprobante`,`numero_comprobante_proveedor`)
  como validación de APLICACIÓN que excluye `estado='cancelada'` (RF-17: un
  UNIQUE de BD impediría recargar una factura cancelada) + índice normal para
  búsqueda; `fecha_comprobante` (date NULL — rige el período fiscal, obligatoria
  si fiscal), `fecha_vencimiento` (date NULL).
- Agregar (RF-21): `compra_origen_id` (bigint unsigned NULL, FK compras — la NC
  apunta a su compra original; NULL en compras normales y NC sueltas).
- Agregar (RF-14): `neto_gravado`, `neto_no_gravado`, `neto_exento` (decimal(12,2), 0).
- Agregar (RF-05): `descuento_global_porcentaje` (decimal(6,2) NULL),
  `descuento_global_monto` (decimal(12,2), 0).
- Agregar (RF-22): `cuenta_compra_id` (bigint unsigned NULL, FK cuentas_compra
  SET NULL) — precargada desde el proveedor, editable por compra.
- Deprecar `caja_id` (2026-07-09): con D14 la caja pertenece al PAGO
  (`pago_proveedor_pagos.caja_id`), no a la compra — dropear en el saneamiento
  (verificar antes que ningún dato existente lo necesite migrar a un pago).
- Semántica de `estado` (RF-17/D11): `borrador` = sin efectos; `completada` =
  confirmada (stock+costos+ledger+cta cte); `cancelada` = revertida. Situación de
  pago SIEMPRE derivada de `saldo_pendiente`.

#### `proveedores` — Cambios (RF-18/RF-22)
- Agregar: `tiene_cuenta_corriente` (tinyint(1), 0) — habilita cta cte y pagos
- Agregar: `dias_pago` (int NULL) — precarga `fecha_vencimiento` de la compra
- Agregar: `saldo_cache` (decimal(12,2), 0) + `ultimo_movimiento_ccp_at`
  (timestamp NULL) — patrón cache de Cliente (actualización con lockForUpdate)
- Agregar (RF-22): `cuenta_compra_id` (bigint unsigned NULL, FK cuentas_compra
  SET NULL) — cuenta default que precarga la compra

#### `articulos` — Cambios
- Agregar: `utilidad_porcentaje` (decimal(6,2) NULL) AFTER `precio_iva_incluido` — override
- Agregar: `precio_administrado_por_utilidad` (tinyint(1), 0) — repricing automático

#### `categorias` — Cambios
- Agregar: `utilidad_porcentaje` (decimal(6,2) NULL) — override de nivel categoría

---

## Pantallas UI

### 1. Compras (`/compras`) — completar componente existente
**Componente**: `App\Livewire\Compras\Compras` (existe; HOY sin traits — el
saneamiento RF-12 le suma `SucursalAware` y los permisos RF-20. NO es CajaAware:
la caja pertenece al pago, D14)
- Toggle "compra no fiscal" (D15): oculta secciones fiscales, sin cálculo de
  impuestos; el total pagado es el costo.
- Selección de tipo de comprobante (define fiscal/no fiscal) + CUIT comprador (existe).
- Búsqueda de artículo también por `codigo_proveedor` del proveedor seleccionado.
- Descuentos anidados por renglón (chips de % en cascada, muestra neto efectivo).
- Sección percepciones sufridas (combobox catálogo impuestos + base/alícuota/monto).
- Sección pago (RF-19): contado o cta cte (si el proveedor la tiene) + pago
  inicial parcial opcional + `fecha_vencimiento` precargada con `dias_pago`.
- Cuenta de compra (RF-22): selector en el encabezado, precargado con el
  default del proveedor, editable.
- Al confirmar: resumen con costos actualizados y acceso a la revisión de precios.
- **Los detalles finos de UX de esta pantalla se discuten en su fase (D7)** — la
  estructura de datos de este spec ya los soporta.

### 2. Revisión de precios post-compra (modal/pantalla desde la confirmación)
**Componente nuevo**: `App\Livewire\Compras\RevisionPreciosCompra`
- Tabla: artículo, costo anterior→nuevo, precio actual, margen real, utilidad
  objetivo, precio sugerido (redondeo configurable), checkbox por fila.
- Aplicar en lote → precio (global u override sucursal según RF-10) + HistorialPrecio.
- Patrón visual y de aplicación: `CambioMasivoPrecios`.

### 3. Costos en artículos (extender GestionarArticulos)
- Columna margen real (con semáforo vs objetivo) en el listado.
- En el modal del artículo: los 3 costos (por sucursal activa + consolidado),
  utilidad override, flag "precio administrado por utilidad", historial de costos
  (modal, patrón historial de precios), proveedores del artículo (códigos +
  costos por proveedor).
- El costo vigente muestra su ORIGEN (proveedor + tipo de comprobante):
  el mismo artículo comprado con factura A ($100 neto) y luego con B ($121
  total) salta ~21% de base sin cambio real de precio — es correcto
  contablemente (el costo B ES mayor: IVA no recuperable), pero el operador
  tiene que poder verlo para no repricear a ciegas (nota 2026-07-09).
- **Claridad conceptual OBLIGATORIA en toda UI de costos** (pedido del usuario
  2026-07-09, aplica acá, a la revisión de precios RF-10 y a cualquier display
  de costo/utilidad/precio): (a) el término de cara al operador es "Costo" a
  secas — "computable" es jerga de spec/BD; el detalle de cómo nació (neto de
  factura A / total pagado de B) se ve en el historial y en el origen;
  (b) los tres costos se etiquetan por su pregunta: "Último (¿cuánto me sale
  hoy?)", "Promedio (¿cuánto me costó lo que tengo?)", "Reposición (manual)";
  (c) donde se calcule precio sugerido o margen, mostrar la CUENTA visible y
  desglosada (costo → +utilidad % → +IVA si aplica → redondeo → precio final),
  no solo el resultado — el operador tiene que poder seguir el cálculo;
  (d) dejar explícito en la UI que el costo se actualiza con las COMPRAS
  (nunca con las ventas).

### 4. Configuración
- `utilidad_default` en configuración del comercio (junto a config existente).
- `utilidad_porcentaje` en el ABM de categorías.

### 4b. Proveedores (`/compras/proveedores`) — componente NUEVO
**Componente nuevo**: `App\Livewire\Compras\GestionarProveedores`
(hoy NO existe ABM de proveedores — solo un select dentro de Compras;
patrón: `GestionarClientes`, Lazy + skeleton, permiso `compras.proveedores`)
- ABM completo: datos del proveedor + `tiene_cuenta_corriente` + `dias_pago`
  + cuenta de compra default (RF-22) + saldo de cta cte (informativo, con
  acceso al estado de cuenta).
- ABM del catálogo `cuentas_compra` (modal simple desde esta pantalla o desde
  configuración — decidir en la fase de UI).

### 4c. Reportes de compras (`/compras/reportes`) — RF-22
**Componente nuevo**: `App\Livewire\Compras\ReportesCompras`
(patrón `ReportesTesoreria`, permiso `compras.reportes`)
- Compras por cuenta (período + sucursal, completadas − NC, drill-down),
  cortes por proveedor y por mes.

### 5. Pagos a proveedores (`/pagos-proveedores`) — RF-19
**Componente nuevo**: `App\Livewire\Compras\GestionarPagosProveedores`
(espejo de `GestionarCobranzas`: SucursalAware + CajaAware + Lazy)
- Listado de proveedores con deuda (saldo_cache) + aging por `fecha_vencimiento`.
- Modal de pago: compras pendientes (FIFO o selección manual con monto parcial),
  desglose de formas de pago, uso de saldo a favor, excedente → anticipo.
- Origen de fondos (D14): default caja activa; con `compras.pagar_avanzado` se
  habilita el selector de origen por renglón del desglose (otra caja / efectivo
  de Tesorería / cuenta de empresa), mostrando el saldo disponible de cada origen.
- Anticipo sin compras; estado de cuenta del proveedor (extracto con saldo
  acumulado, patrón modal de `verCuentaCorriente`); anular orden de pago.

---

## Servicios

### `CostoService` — `app/Services/CostoService.php` (nuevo, única puerta de escritura)
- `costoComputableRenglon(array $renglon, Compra $compra): float` — cadena completa
  RF-01: cascada de descuentos del renglón − prorrateo descuento global + prorrateo
  conceptos al costo → regla computable (condición IVA del CUIT + tipo comprobante)
  → ÷ factor de conversión = costo unitario computable por unidad de STOCK.
- `registrarDesdeCompra(Compra $compra, ?int $usuarioId): void` — por renglón:
  actualiza `articulo_costos` (fila sucursal + fila consolidada NULL: último + PPP),
  upsert `articulo_proveedor`, registra `historial_costos`. Idempotente por compra.
- `revertirCostoUltimoSiCorresponde(Compra $compra, ?int $usuarioId): void` — RF-07.
- `actualizarManual(Articulo $a, ?int $sucursalId, string $tipo, float $valor, int $usuarioId): void`.
- `utilidadObjetivo(Articulo $a): float` — cascada artículo → categoría → config.
- `margenReal(Articulo $a, ?int $sucursalId): ?array` — {costo_rector, neto_venta,
  margen_real, coeficiente, margen_sobre_venta} (NULL si no hay costo).
- `precioSugerido(Articulo $a, ?int $sucursalId, ?float $utilidad = null): float`
  — fórmula canónica + redondeo. NOTA: `aplicarRedondeo()` hoy es `protected` en
  el Livewire `CambioMasivoPrecios.php:291` — extraerlo a `PrecioService` para
  poder reutilizarlo desde acá.
- `alicuotaEfectiva(Articulo $a): float` (D21) — alícuota del TipoIva del
  artículo SI el CUIT default del comercio es RI (`esResponsableInscripto()`)
  Y `articulo.precio_iva_incluido`; 0 en caso contrario. La usan `margenReal`
  y `precioSugerido` (única puerta — nunca leer la alícuota directo en fórmulas
  de pricing).

### `CompraService` — REESCRIBIR (RF-12: sin compatibilidad con la lógica actual)
- NC de proveedor (RF-21): mismo pipeline de confirmación con efectos inversos
  parciales (stock egreso, fiscal en período de la NC, ledger `nota_credito`).
- `crearCompra()` / `actualizarBorrador()`: persisten encabezado completo (RF-13/14),
  renglones con descuentos y cantidades (RF-05/16), `compra_ivas`, `compra_conceptos`,
  percepciones. En estado borrador NO hay efectos (RF-17).
- `confirmarCompra()`: transacción única — prorrateos (descuento global + conceptos
  al costo, por importe) → `costo_unitario_computable` por renglón → stock →
  `CostoService::registrarDesdeCompra()` → `ImpuestoService::registrarDesdeCompra()`
  → `CuentaCorrienteProveedorService::registrarMovimientosCompra()` (RF-18) →
  pago inicial vía `PagoProveedorService` (RF-19) → repricing automático (RF-11).
  Idempotente.
  **Contrato fiscal del caller (2026-07-09)**: `$ivaCredito` se arma desde
  `compra_ivas` (fuente canónica, RF-14) y se envía SOLO si `fiscal AND el tipo
  discrimina AND el CUIT comprador es RI` — el service NO gatea por condición
  IVA (su docblock ya lo delega al caller); vacío en todo otro caso.
- **Ajuste a `ImpuestoService::registrarDesdeCompra/anularDesdeCompra`**: el
  período fiscal pasa de `$compra->fecha ?? now()` (línea 540) a
  `fecha_comprobante` (RF-06/RF-13); `anularDesdeCompra` al patrón NC
  cross-período (reversa negativa en el período de la cancelación).
- `cancelarCompra()`: → `ImpuestoService::anularDesdeCompra()` (ajustado a patrón
  NC cross-período) + `CostoService::revertirCostoUltimoSiCorresponde()` +
  `CuentaCorrienteProveedorService::anularMovimientosCompra()` + reversa de stock
  + contraasiento de caja. Con pagos aplicados: parámetro de elección D17
  (cascada de anulación de pagos o conversión a saldo a favor). Un borrador se
  elimina sin reversas.
- `registrarPago()` actual desaparece → `PagoProveedorService` (RF-19).
- Fix del stock: `MovimientoStock::crearMovimientoCompra` pasa a recibir el
  `costo_unitario_computable` (hoy usa `precio_sin_iva`, que es incorrecto para
  compras que no discriminan).

### `CuentaCorrienteProveedorService` — nuevo (espejo de `CuentaCorrienteService`)
- `registrarMovimientosCompra(Compra $c, int $usuarioId): array` — HABER por el
  total + DEBE por lo pagado en el momento (espejo invertido de
  `procesarPagosCuentaCorriente`). Solo proveedores con CC.
- `registrarMovimientosPago(PagoProveedor $p, array $aplicaciones, int $usuarioId): array`
  — uso de saldo a favor + DEBE por compra aplicada + anticipo/excedente.
- `anularMovimientosCompra(...)` / `anularMovimientosPago(...)` — contraasientos
  (incl. ajuste débito si un anticipo ya consumido se anula, patrón clientes).
- `obtenerExtracto()` / `obtenerExtractoResumido()` / `obtenerSaldos()` /
  `obtenerComprasPendientes()` (FIFO por `fecha_vencimiento`, luego fecha).
- `actualizarCacheProveedor(int $proveedorId): void` — transacción + lockForUpdate.

### `PagoProveedorService` — nuevo (espejo de `CobroService`)
- `registrarPago(array $data, array $comprasAAplicar, array $pagos): PagoProveedor`
  — transacción única: OP + aplicaciones + desglose FP con egreso según origen
  (D14: `MovimientoCaja` egreso / `TesoreriaService::registrarEgresoExterno()`
  NUEVO, espejo de registrarIngresoExterno / `CuentaEmpresaService::
  registrarMovimientoAutomatico`) + ledger + baja de `compras.saldo_pendiente`.
  Valida saldo del origen y permiso `compras.pagar_avanzado` si origen ≠ caja
  activa. NOTA: `MovimientoCaja` no tiene factory de egreso (los egresos hoy se
  arman a mano) — crear `MovimientoCaja::crearEgresoPagoProveedor()`, espejo de
  `crearIngresoCobro`. Cada renglón del desglose guarda la FK del movimiento
  generado (contraasiento exacto al anular).
- `registrarAnticipo(...)`, `anularPago(int $pagoId, string $motivo): array` —
  contraasientos de ledger + reversa por origen (caja / tesorería / cuenta
  empresa), restaura `saldo_pendiente`. El bloqueo por `cierre_turno_id` aplica
  SOLO si algún renglón del desglose tiene origen 'caja' con turno cerrado (D16);
  pagos 100% tesorería/cuenta empresa se anulan siempre — el saldo vuelve a su
  origen.
- `distribuirMontoFIFO(...)`, `generarNumeroOrdenPago(int $sucursalId): string`.

### `PrecioService` — sin cambios de contrato
- La revisión/repricing escribe por los caminos existentes (precio_base global u
  override `articulos_sucursales`) + `HistorialPrecio::registrar()`.

---

## Migraciones Necesarias (orden)

1. `reconciliar_schema_compras` — RF-12: columnas faltantes/renombres en `compras`
   y `compras_detalle`, enum `forma_pago` completo, estados D11 + mapeo
   `pendiente`→`completada` (idempotente, verificar estado real por comercio).
2. `add_encabezado_completo_a_compras` — RF-13/14/05: nro comprobante proveedor +
   UNIQUE, fechas, netos, descuento global.
3. `create_compra_ivas`
4. `create_compra_conceptos` (incluye tipo_iva_id, RF-15)
5. `create_articulo_costos`
6. `create_historial_costos`
7. `create_articulo_proveedor` (incluye factor_conversion)
8. `create_configuracion_costos` (+ seed de la fila con defaults, incl. ProvisionComercioCommand)
8b. `create_cuentas_compra` — RF-22: catálogo + seed inicial + `cuenta_compra_id`
    en `proveedores` y `compras` (+ ProvisionComercioCommand).
9. `add_costos_y_descuentos_a_compras_detalle` — descuentos, prorrateos, cantidades,
   codigo_proveedor_usado, costo_unitario_computable.
10. `add_utilidad_a_articulos` (utilidad_porcentaje + precio_administrado_por_utilidad)
11. `add_utilidad_a_categorias`
12. `add_cta_cte_a_proveedores` — RF-18: tiene_cuenta_corriente, dias_pago, caches.
13. `create_movimientos_cuenta_corriente_proveedor` — RF-18.
14. `create_pagos_proveedores` — RF-19: + `pago_proveedor_compras` +
    `pago_proveedor_pagos` (una migración, 3 tablas).
15. `menu_y_permisos_compras` — RF-20: ítems de menú + permisos (idempotente) +
    actualizar `ProvisionComercioCommand::seedRolesYPermisos()`.
16. Regenerar `database/sql/tenant_tables.sql` (todas).

---

## Traducciones

Claves nuevas (es/en/pt, orden alfabético, skill /traducir). Principales:
"Costo último", "Costo promedio", "Costo de reposición", "Costo neto",
"Costo con IVA", "Utilidad (%)", "Utilidad objetivo", "Margen real",
"Precio sugerido", "Revisión de precios", "Precio administrado por utilidad",
"Código del proveedor", "Descuentos en cascada", "Historial de costos",
"Proveedores del artículo", "Percepciones de la compra", "Pagos a proveedores",
"Orden de pago", "Estado de cuenta del proveedor", "Deuda con proveedores",
"Anticipo a proveedor", "Días de pago", "Pago inicial", + mensajes de la
revisión post-compra. (Lista final en /sdd-apply.)

---

## Criterios de Aceptación

- [ ] Compra factura A de comprador RI: costo = neto con descuentos; el IVA va al
      ledger como crédito fiscal; percepciones al ledger, NO al costo.
- [ ] Compra no fiscal / factura B de comprador RI (y toda compra de monotributo):
      costo = total pagado con descuentos; nada al ledger de IVA.
- [ ] Descuentos 10+5+3 sobre renglón de $1000 ⇒ neto efectivo $829,35
      (1000×0,90×0,95×0,97 — cascada, no suma) persistido con la lista y el monto.
- [ ] Confirmar compra actualiza costo_ultimo + PPP en fila sucursal Y consolidada,
      upsert de articulo_proveedor y filas de historial_costos.
- [ ] PPP: stock 10 @ $100 + compra 5 @ $130 ⇒ $110.
- [ ] Cancelar la compra que fijó el último costo restaura el anterior (historial
      origen 'cancelacion'); cancelación fiscal cross-período con reversa negativa.
- [ ] Cascada de utilidad: artículo 50 % pisa categoría 40 % pisa comercio 30 %.
- [ ] Precio sugerido: costo neto $100, utilidad 40 %, IVA 21 % ⇒ final $169,40
      (+ redondeo configurado); margen real inverso reproduce la utilidad (pre-redondeo).
- [ ] Revisión post-compra lista solo artículos con margen real < objetivo y aplica
      en lote registrando HistorialPrecio.
- [ ] Artículo con flag automático se repricea al confirmar la compra sin pasar
      por la revisión.
- [ ] Carga de renglón por código de proveedor encuentra el artículo y precarga
      descuentos habituales y factor de conversión.
- [ ] No se puede cargar dos veces el mismo comprobante del proveedor
      (proveedor + tipo + número → UNIQUE con mensaje claro).
- [ ] Descuento global −5 % sobre comprobante de 2 renglones se prorratea por
      importe y reduce el costo unitario de ambos.
- [ ] Concepto "impuestos internos $500, computa costo" se prorratea a los
      renglones; concepto "flete $300, no computa" completa el total sin tocar costos.
- [ ] Compra de 2 bultos x12 @ $1200/bulto ⇒ stock +24 unidades, costo unitario
      neto $100 (con factor 12).
- [ ] Borrador: se guarda y edita sin tocar stock/costos/ledger; al confirmar se
      dispara todo una sola vez; borrador se elimina sin reversas.
- [ ] Migración de estados: las compras 'pendiente' existentes quedan 'completada'
      con su `saldo_pendiente` intacto; ninguna es interpretable como borrador.
- [ ] Compra cta cte de proveedor CC genera HABER por el total; contado total
      genera par HABER/DEBE con saldo 0 (extracto completo).
- [ ] Pago inicial al confirmar una compra cta cte se materializa como
      PagoProveedor (un solo camino de escritura) y baja el saldo.
- [ ] Pago parcial aplicado a 2 compras (FIFO) genera los DEBE, baja el
      `saldo_pendiente` de ambas y registra egreso de caja según el desglose de FP.
- [ ] Anticipo a proveedor genera saldo a favor nuestro; el pago siguiente lo
      consume (`uso_saldo_favor`).
- [ ] Anular una orden de pago contraasienta ledger + el origen de fondos (caja,
      tesorería o cuenta empresa) y restaura los `saldo_pendiente`; bloqueada
      solo si algún renglón de origen caja tiene el turno cerrado — una OP 100%
      tesorería/cuenta empresa se anula siempre (el saldo vuelve al origen).
- [ ] Sin `compras.pagar_avanzado` el pago solo puede salir de la caja activa
      (con validación de saldo); con el permiso se puede pagar desde otra caja,
      efectivo de Tesorería o cuenta de empresa, y cada origen valida su saldo.
- [ ] Compra con toggle NO FISCAL: sin desglose de IVA, sin compra_ivas, sin
      percepciones, nada al ledger fiscal; el total pagado es el costo.
- [ ] NC de proveedor por 3 de 10 unidades: stock −3, reversa proporcional del
      crédito fiscal en el PERÍODO de la NC, DEBE en ledger que baja el saldo de
      la compra origen; `costo_ultimo` y PPP intactos.
- [ ] Cancelar compra con pagos aplicados ofrece cascada o saldo a favor; con
      turno de caja cerrado en un renglón caja, solo saldo a favor.
- [ ] Cancelar y recargar la misma factura del proveedor es posible (el
      anti-duplicado excluye canceladas); cargarla dos veces activa, NO.
- [ ] Primera compra de un artículo con stock previo y PPP NULL fija
      PPP = costo unitario computable (el stock sin costo no pondera).
- [ ] Factura A bajo CUIT comprador monotributista SE PUEDE cargar (RG
      5003/2021): advertencia no bloqueante, SIN crédito al ledger, todo lo
      pagado (IVA incluido) es costo.
- [ ] El crédito fiscal del ledger sale de `compra_ivas` (no de la suma de
      renglones) y su período es `fecha_comprobante`: factura de junio cargada
      en julio computa el crédito en JUNIO.
- [ ] Desglose sugerido vs cargado: si Σ(renglones+conceptos gravados) difiere
      de `compra_ivas` por más de la tolerancia, advertencia antes de confirmar.
- [ ] Comercio cuyo CUIT default NO es RI: precio sugerido = costo × (1+utilidad)
      SIN factor de IVA y margen real sin división (alic_efectiva = 0, D21).
- [ ] Concepto flete $300 con tipo_iva 21% en factura A: entra neto al prorrateo
      de costo y su IVA aparece en la sugerencia de compra_ivas.
- [ ] Compra hereda la cuenta de compra default del proveedor, editable; reporte
      "Compras por cuenta" suma completadas − NC por período y muestra "sin
      clasificar" como categoría propia (RF-22).
- [ ] Menú: grupo padre "Compras" con hijos Compras / Proveedores / Pagos a
      proveedores / Reportes, con sus permisos respectivos.
- [ ] Cancelar una compra confirmada contraasienta el ledger de proveedor y la
      caja (nunca mutación directa de saldos).
- [ ] Permisos compras.* sembrados y ENFORCED (middleware + hasPermissionTo);
      usuario sin `costos.ver` no ve costos ni márgenes en ninguna pantalla.
- [ ] `compra_ivas` + netos del encabezado cuadran contra el total del comprobante
      y alimentan el Libro IVA Compras.
- [ ] Smoke tests Livewire de componentes nuevos/modificados; tests de CostoService
      (matriz condición IVA × tipo comprobante, PPP, cascada, historial, reversión)
      y de los services de cta cte proveedor/pagos (es dinero: suite completa).

---

## Plan de Implementación

### Fase 1: Replanteo schema compras + permisos (RF-12, RF-20) [COMPLETO]
Migración al schema final (estados D11, forma_pago, fecha sin ON UPDATE, drop
caja_id, preservando datos) + menú (grupo padre "Compras" + hijos) / permisos
(con mapa permiso→rol a definir con el usuario) + verificación real por
comercio + tenant_tables.sql. El código de compras se reescribe en las Fases
4-6; esta fase deja la base de datos y los permisos listos. Sin cambios
funcionales visibles.

#### Ajustes de implementación (Fase 1, 2026-07-09)
- Migraciones: `2026_07_09_120000_reconciliar_schema_compras` +
  `2026_07_09_120100_add_compras_menu_y_permisos`. Ejecutadas en dev Y test;
  efecto verificado contra BD real (no solo exit code del migrate).
- **`compras.caja_id` NO se dropeó**: el `CompraService` actual la usa en todo
  el flujo; el drop se difiere a la Fase 4/5 (cuando el service reescrito y
  `pago_proveedor_pagos` tomen su lugar).
- **El menú nació INACTIVO** (padre "Compras" orden 5 + 4 hijos, permisos ya
  creados y asignados): el componente actual es el que se reescribe en Fase 6 —
  no se expone. Activación: Fase 5 → proveedores + pagos-proveedores; Fase 6 →
  padre + listado-compras; Fase 8 → reportes-compras. La navegación filtra
  `activo=true` (MenuItem:90).
- Hijo del listado: slug `listado-compras` (el slug `compras` lo lleva el padre,
  UNIQUE) — nombre "Listado de Compras", patrón Ventas/Clientes.
- **Permisos según la convención REAL del proyecto** (no la del docblock viejo):
  pantallas = `menu.{slug}` (listado-compras/proveedores/pagos-proveedores/
  reportes-compras), acciones = `func.compras.*` (crear/confirmar/cancelar/
  pagar/pagar_avanzado/revisar_precios) y `func.costos.*` (ver/editar) vía
  `permisos_funcionales`. NO existen func.compras.ver/proveedores/reportes (los
  cubre el permiso de menú). Sin middleware `permission:` en rutas — el
  proyecto no lo usa en ninguna (verificado); autorización en componentes.
- Asignación default: Administrador + Super Administrador en todos los tenants
  (patrón delivery); el resto de los roles se asigna por comercio desde la
  pantalla Roles y Permisos.
- Parche compat D11 en el service viejo (`CompraService:80`): `estado` siempre
  `completada` (antes cta_cte ⇒ 'pendiente', valor que ya no existe en el enum).
  La vista vieja conserva referencias muertas a 'pendiente' (filtro/badge) —
  inofensivas, muere todo en la reescritura de Fase 6.
- Hallazgo: los comercios 5-13 son registros residuales SIN tablas tenant
  (ni `sucursales`) — la migración los saltea con guard; el único tenant real
  (comercio 1) quedó reconciliado y `compra_percepciones` se crea donde falte.
- `WithTenant::$testTables` ganó compras/compras_detalle/compra_percepciones
  (DELETE selectivo para los tests de las fases siguientes).
- Verificación: pint OK, suite Smoke completa 193 verdes, tenant_tables.sql
  regenerado (bloques compras + compras_detalle al schema final).

### Fase 2: BD de costos [COMPLETO]
Migraciones 2-11 (incl. detalle de compras, utilidad en artículos/categorías,
cuentas_compra RF-22) + modelos (`ArticuloCosto`, `HistorialCosto`,
`ArticuloProveedor`, `ConfiguracionCostos`, `CuentaCompra`) + relaciones en
`Articulo`/`Proveedor`/`Categoria` + ProvisionComercioCommand.

#### Ajustes de implementación (Fase 2, 2026-07-09)
- Migraciones 2-11 en UN bundle (`2026_07_09_130000_create_bd_costos_fase2`,
  patrón validado en integraciones de pago), idempotente columna a columna.
  Ejecutada en dev y test, efecto verificado contra BD real (7 tablas nuevas,
  las columnas de compras/compras_detalle/articulos/categorias/proveedores y
  los seeds de configuracion_costos + las 4 cuentas de compra).
- Modelos nuevos (7): ArticuloCosto, HistorialCosto (UPDATED_AT null, patrón
  HistorialPrecio), ArticuloProveedor, ConfiguracionCostos (singleton
  `obtener()` con firstOrCreate — cubre tenants de test creados del SQL pelado
  sin seeds), CuentaCompra, CompraIva, CompraConcepto. Actualizados (5):
  Compra (encabezado completo + relaciones ivas/conceptos/cuentaCompra/
  compraOrigen/notasCredito), CompraDetalle, Articulo (utilidad + costos()/
  historialCostos()/proveedores()), Categoria, Proveedor (cuentaCompra()/
  articulos()).
- `ProvisionComercioCommand::seedCostos()` nuevo (config + cuentas de compra
  para comercios nuevos; menú y permisos ya los toma genérico de Fase 1).
- **Gotcha MySQL documentado en el schema**: el UNIQUE (articulo_id,
  sucursal_id) NO impide duplicar la fila consolidada (NULL admite N filas) —
  la unicidad del consolidado la garantiza CostoService como única puerta de
  escritura (firstOrCreate + lockForUpdate en transacción, Fase 3).
- tenant_tables.sql regenerado por DUMP REAL del comercio 1 (12 bloques:
  5 reemplazados + 7 nuevos, prefijo → {{PREFIX}}, sin AUTO_INCREMENT) y
  VALIDADO end-to-end con TEST_FORCE_RECREATE=1 (recreación completa desde el
  SQL + smoke verde).
- WithTenant::$testTables ganó las 7 tablas nuevas.
- Verificación: pint OK, suite Smoke completa 193 verdes.

### Fase 3: CostoService núcleo [COMPLETO]
Costo computable + registrarDesdeCompra (último/PPP/proveedor/historial/consolidado)
+ actualizarManual + reversión + cascada utilidad + margen/precio sugerido.
Suite de tests completa (es el corazón del feature).

#### Ajustes de implementación (Fase 3, 2026-07-09)
- `CostoService` (app/Services/CostoService.php) con TODOS los métodos del
  spec + `prorratearPorImporte()` público (el prorrateo de descuento global y
  conceptos es del pipeline de confirmación — Fase 4 — pero la herramienta con
  residuo-al-último vive acá) y `costoRector()` como lector formal con la
  config (ultimo/promedio/reposición con fallback).
- **Contrato de `registrarDesdeCompra` fijado**: asume que el stock YA incluye
  la compra (orden del pipeline: stock → costos) y resta la cantidad propia
  para ponderar el PPP contra el stock previo. Agrupa renglones por artículo
  (el mismo artículo en N renglones pondera dentro del comprobante).
- Desvío documentado de RF-03: el historial se registra por CADA compra aunque
  el costo no cambie — es el marcador de idempotencia y la trazabilidad de
  "qué costo trajo cada compra" (evita re-aplicar el PPP en un retry).
- RF-07: restaurar a "sin costo" (cancelar la primera compra) registra
  `costo_nuevo = 0` con detalle explícito (la columna es NOT NULL); el origen
  (proveedor/compra previos) se reapunta desde el historial previo.
- `alicuotaEfectiva` (D21): CUIT resuelto por sucursal vía pivot
  `cuit_sucursal.es_principal` (fallback: primer CUIT de la sucursal;
  consolidado: primer CUIT activo del comercio; sin CUIT ⇒ NO computa IVA).
- Gotcha: la relación artículo→categoría se llama `categoriaModel` (no
  `categoria`) — la cascada de utilidad la usa.
- `PrecioService::aplicarRedondeo(float, string)` público extraído de
  CambioMasivoPrecios (que ahora delega) — tipos: ninguno/entero/decena/centena.
- Tests: `CostoServiceTest` 28 verdes cubriendo TODOS los criterios de
  aceptación de la fase (cascada 829,35 / PPP 110 / arranque PPP NULL /
  sugerido 169,40 / monotributo 140 sin IVA / margen inverso 40% / cascada
  utilidad 50-40-30 / consolidado multi-sucursal / reversión con restauración
  / idempotencia / redondeos). Suite completa de services: 287 verdes.
- Nota de entorno de test: cuits/cuit_sucursal no están en el DELETE selectivo
  de WithTenant — la suite limpia SOLO sus cuits (un DELETE global choca con
  FKs de residuos de otras suites, ej. cuentas_empresa→cuits).

### Fase 4: CompraService nuevo [COMPLETO]
Reescritura completa (RF-12): borrador→completada→cancelada, descuentos
anidados, costo_unitario_computable, gate del crédito + `$ivaCredito` desde
compra_ivas, ajuste de ImpuestoService (período = fecha_comprobante; anular
con patrón NC cross-período), CostoService, costo correcto en MovimientoStock,
NC de proveedor (RF-21: efectos inversos parciales, desglose IVA propio).
Tests de integración compra→costo→ledger→NC (incl. matriz condición IVA ×
tipo comprobante contra el ledger y el período del crédito).

#### Ajustes de implementación (Fase 4, 2026-07-09)
- **ImpuestoService ajustado**: `registrarDesdeCompra` usa
  `fecha_comprobante ?? fecha` para el período + parámetro `esNotaCredito`
  (RF-21: reversa NEGATIVA con el desglose PROPIO de la NC en su período);
  `anularDesdeCompra` REESCRITO al patrón cross-período — reversas negativas
  fechadas HOY, originales quedan ACTIVOS y netean a cero (antes flipaba
  estado en el período original, que puede estar declarado). Idempotente por
  suma-cero. El test viejo `anular_desde_compra_contraasienta` se adaptó a la
  semántica nueva + 2 tests nuevos (período por fecha_comprobante, NC negativa).
- **Caso RG 5003 completado en la CADENA DE COSTO** (hallazgo de esta fase —
  la regla del spec era ambigua acá): factura A/M con comprador NO-RI viene
  NETA impresa, pero el IVA no recuperable ES costo ⇒
  `costoComputableRenglon` ganó `alicuota_no_recuperable` (la alícuota del
  tipo_iva del renglón cuando `discrimina AND !compradorRI`; 0 en el resto).
  Test: neto 100 + IVA 21 ⇒ computable 121 para monotributo comprador.
- Compra model: constantes de estado (D11) y de tipos de comprobante
  (factura_a/b/c/m, no_fiscal, nota_credito_a/b/c/no_fiscal) + helpers
  esNotaCredito()/esFiscal()/discriminaIva()/esBorrador() + scopes
  borradores/completadas/canceladas/activas (el scope 'pendientes' murió).
- CompraService API: crearBorrador / actualizarBorrador (hereda sucursal y
  usuario al validar) / eliminarBorrador / confirmarCompra / cancelarCompra /
  esComprobanteDuplicado / advertenciaComprobanteCuit (los textos de la
  advertencia RG 5003 y "B a un RI" listos para la UI de Fase 6).
- Validaciones de coherencia D15 en la confirmación: comprobante que no
  discrimina NO lleva compra_ivas; compra no fiscal NO lleva percepciones;
  NC con origen exige misma proveedor + completada; fecha_comprobante
  obligatoria si fiscal.
- Números internos: COM-{suc}-{8dig} para compras, NCP- para NC (el real del
  proveedor viaja en numero_comprobante_proveedor).
- **Cancelar con pagos aplicados bloqueado hasta Fase 5** (guard
  saldo_pendiente == total): el hook D17 (cascada o saldo a favor) se
  implementa con la cta cte de proveedores.
- El componente viejo `Compras` no rompe en mount (usa boot() con DI y el
  contenedor resuelve el constructor nuevo); sus acciones viejas quedan
  muertas hasta la reescritura de Fase 6 (menú inactivo, sin exposición).
- Verificación: pint OK, CompraServiceTest 17 verdes (integración
  compra→costo→ledger→NC completa, matriz A-RI/B-RI/A-mono/no-fiscal,
  prorrateos con flete y descuento global, factor bulto→stock, anti-duplicado
  con recarga post-cancelación, cross-período), suite services 306 verdes,
  Smoke 193 verdes.

### Fase 5: Cuenta corriente de proveedores (RF-18/19) [COMPLETO]
Migraciones 12-14 + modelos + `CuentaCorrienteProveedorService` +
`PagoProveedorService` (incl. `MovimientoCaja::crearEgresoPagoProveedor` y
`TesoreriaService::registrarEgresoExterno` nuevos) + cableado en
confirmar/cancelar compra (D17) + pantalla `GestionarPagosProveedores` (ruta +
Lazy + skeleton) + estado de cuenta + **componente `GestionarProveedores`
NUEVO** (ABM completo: CC/días de pago/cuenta de compra default — hoy no
existe ABM de proveedores) + ABM `cuentas_compra`.
Suite de tests completa (es dinero: ledger + contraasientos + caja/tesorería).

#### Ajustes de implementación (Fase 5, 2026-07-09 — commits 5a núcleo + 5b UI)
- Migración bundle `2026_07_09_150000` (12-14): orden CREATE pagos ANTES que
  el ledger (su FK apunta a pagos_proveedores); activa el menú padre Compras
  + Proveedores + Pagos a Proveedores (listado-compras sigue inactivo hasta
  Fase 6).
- **Desvío semántico documentado vs el espejo de clientes**: el movimiento
  `uso_saldo_favor` SOLO consume el saldo (saldo_favor_debe, sin debe) — la
  reducción de deuda viaja en el DEBE de las aplicaciones por compra, que
  incluyen los fondos del saldo a favor (con debe además, la deuda bajaba dos
  veces; posible bug latente del lado CLIENTES a revisar aparte).
- El saldo de caja lo actualiza el CALLER (convención del proyecto:
  aumentarSaldo/disminuirSaldo); el contraasiento de MovimientoCaja lo
  restaura solo. PagoProveedorService egresa con disminuirSaldo en efectivo.
- Origen 'caja': movimiento de caja SOLO para FP efectivo (espejo del
  criterio afecta_caja de cobros); FP con cuenta_empresa_id vinculada egresa
  además en la cuenta (espejo exacto del ingreso automático de cobros).
- El permiso `func.compras.pagar_avanzado` se autoriza en el COMPONENTE
  (service API-first sin sesión); el service valida los SALDOS de cada origen.
- Contado SIN datos de pago ⇒ completada con saldo pendiente (D11: lo impago
  se deriva del saldo; se paga después por la pantalla). Con datos, los
  fondos cubren el total exacto. Default de forma_pago del borrador: cta_cte
  si el proveedor tiene CC, efectivo si no.
- D17 'saldo_favor': contraasiento del DEBE de cada pago aplicado + movimiento
  `devolucion_saldo` con saldo_favor_haber (la OP queda ACTIVA — la plata
  salió de verdad); 'anular_pagos' anula cada OP ENTERA (si tocaba otras
  compras, también les restaura el saldo — documentado).
- Cancelar una NC restaura el saldo de la compra origen (tope: su total).
- UI: `GestionarProveedores` (SucursalAware; ABM + cuentas de compra + estado
  de cuenta) y `GestionarPagosProveedores` (CajaAware + session sucursal,
  patrón GestionarCobranzas; pago FIFO/manual, saldo a favor, desglose por
  origen gated por pagar_avanzado, anticipo, extracto + OPs con anulación).
  Rutas compras/proveedores y compras/pagos-proveedores.
- MovimientoTesoreria ganó REFERENCIA_EGRESO_EXTERNO y REFERENCIA_PAGO_PROVEEDOR
  (columna varchar, sin migración).
- Verificación: CuentaCorrienteProveedorTest 17 verdes (ledger pasivo, contado
  par haber/debe, pago inicial, FIFO 2 compras, anticipo+consumo, tesorería
  con validación de saldo, caja insuficiente, anulación OP + D16, D17 ambas
  ramas, NC contra saldo + excedente, extracto), services 323 verdes, smoke
  Compras 4 nuevos (197 smokes totales), 112 traducciones ×3 (4122 parejas),
  pint OK.

### Fase 6: UI de compras [PENDIENTE]
Componente reescrito (SucursalAware): carga por código de proveedor, descuentos
por renglón, percepciones, tipo de comprobante, toggle no fiscal, NC, desglose
compra_ivas sugerido/editable con validación de cuadre, cuenta de compra
(RF-22), sección pago (contado/cta cte/pago inicial con caja activa).
**Sesión de diseño UX (D7) HECHA — decisiones abajo.**

#### Sesión UX D7 (2026-07-09, decidida con el usuario)
1. **Estructura**: `/compras` = listado; la carga/edición abre un MODAL A
   PANTALLA COMPLETA (patrón del editor de ventas — pedido explícito).
2. **Renglones = grilla tipo planilla** con navegación por teclado (Enter/Tab
   avanza de celda; al final agrega fila). Buscador de artículo en la primera
   celda que matchea código propio, código del proveedor seleccionado y
   nombre. Columnas: Artículo | Cant. comprada (bultos) | Factor (precargado
   de articulo_proveedor, editable) | Cant. stock (auto = comprada × factor) |
   Precio unit. | Desc. | Unit. efectivo | Subtotal.
3. **Alta rápida de artículo INLINE** desde el buscador de la grilla (patrón
   /combobox-alta-rapida: modal mínimo nombre/categoría/IVA/código) — además
   persiste automáticamente el código del proveedor en articulo_proveedor.
4. **Descuentos en cascada como TEXTO "10+5+3"** en la celda (como los imprime
   la factura del proveedor); al lado se muestra el unitario efectivo.
5. **Sección fiscal SIEMPRE VISIBLE** junto a los totales: desglose de IVA
   autocalculado de los renglones y EDITABLE para calzar con la factura
   física, con advertencia de cuadre (no bloqueante); Conceptos y
   Percepciones como sub-secciones colapsables; el pie muestra la cuenta
   completa (neto − desc global + conceptos + IVA + percepciones = total)
   para verificar contra la factura en vivo. Toggle NO FISCAL oculta todo el
   bloque (D15). Advertencia comprobante×CUIT no bloqueante (textos ya en
   CompraService::advertenciaComprobanteCuit).
6. **Pago = MODAL AL CONFIRMAR** (patrón cobro de ventas): cta cte (sin pago o
   pago inicial parcial) / contado (desglose FP por el total); vencimiento
   precargado con dias_pago del proveedor.
7. **Borrador con botón explícito** [Guardar borrador]; el listado los muestra
   retomables/eliminables.
8. **Post-confirmación**: resumen con aviso "N artículos bajo el margen
   objetivo → [Revisar precios]" — NO bloquea, la revisión es retomable
   (pantalla en Fase 8).
9. **NC**: camino principal desde el DETALLE de la compra ([Cargar NC]
   precarga renglones con cantidades a devolver, tope lo comprado); camino
   secundario "Nueva NC" suelta desde el listado.
10. **Listado = patrón lista de pedidos** (el formato de delivery/mostrador):
    datos con botones inline (badge de pago = botón pagar), badges de estado,
    acciones acotadas (Ver / Editar / Cargar NC / Cancelar).
11. **Detalle con reconstrucción PERFECTA** (pedido explícito): renglones con
    descuentos y computables, desglose fiscal, conceptos, percepciones, pagos
    aplicados, NCs vinculadas y costos que generó — se debe poder recrear
    exactamente los montos de la factura.
12. **Edición "como si fuese el alta"** (pedido explícito): borrador = edición
    directa; una COMPLETADA se reabre en el MISMO editor precargado en modo
    corrección — por detrás se materializa como cancelar+recrear atómico
    (preserva la inmutabilidad del ledger por contraasientos, RF-17). Los
    CONFLICTOS (pagos aplicados, turno cerrado, stock insuficiente para
    revertir, NCs vinculadas) se resuelven con decisiones puntuales DURANTE
    la implementación con el usuario — anotado como pendiente de decisión.
13. **Mobile**: listado en cards; el editor es desktop-first (la grilla
    scrollea horizontal en móvil — la carga de facturas es tarea de escritorio).

### Fase 7: Utilidad y margen en artículos/config [PENDIENTE]
Config comercio + categorías + artículos (override, flag, columnas margen,
historial de costos, proveedores del artículo). Permisos `costos.ver/editar`.

### Fase 8: Revisión de precios + repricing + reportes [PENDIENTE]
`RevisionPreciosCompra` + flag automático en confirmación + `ReportesCompras`
(compras por cuenta, RF-22).

### Fase 9: Verificación + docs [PENDIENTE]
/sdd-verify + @docs-sync + manual de usuario.

### Fases futuras (fuera de alcance)
- **Transferencias inter-sucursal** (D5, ampliada 2026-07-02): módulo PROPIO
  (para no complejizar ni ventas ni compras en sí mismas) donde cargar una
  compra en una sucursal significa una venta en otra y viceversa, pudiendo usar
  un precio especial/lista interna. El proveedor interno ya está modelado
  (`proveedores.es_sucursal_interna` + `sucursal_id`); el precio de venta
  interno se vuelve costo de la sucursal destino.
- **Compensación de saldos cliente↔proveedor** (anotada 2026-07-02): cuando una
  misma entidad está configurada como cliente Y proveedor, poder balancear
  saldos entre ambas cuentas corrientes (cancelar deuda de una con saldo de la
  otra). Ambos ledgers ya soportan `ajuste_debito`/`ajuste_credito`; al
  implementarse se agrega un tipo `compensacion` (o se referencia el ajuste
  espejo) + un vínculo cliente↔proveedor (probablemente por CUIT). Nada del
  diseño actual lo bloquea.
- **Costo de artículos elaborados** (D13): costo de receta = Σ(costo rector del
  ingrediente × cantidad) desde `articulo_costos` + `receta_ingredientes` (hoy
  sin costo). Definirá si se persiste o deriva y si se recalcula en cascada al
  cambiar el costo de un ingrediente.
- **Moneda extranjera en compras/pagos a proveedores**: snapshot ME en el ledger
  de proveedores (columnas espejo de clientes), si aparecen compras en ME.

---

## Notas y Decisiones

- 2026-07-01 (sesión de diseño, con el usuario):
  - **D1 — Costo rector = último** (de cualquier proveedor). Los tres costos se
    persisten; `costo_rector` queda en config para flexibilizar a futuro.
  - **D2 — Utilidad = markup % sobre costo neto**, cascada comercio → categoría →
    artículo (pedido explícito del usuario). Un solo modo de expresión en todo el
    sistema; equivalentes solo informativos. El precio se materializa sobre el
    FINAL con IVA (fuente de verdad del sistema; la venta desglosa dividiendo,
    verificado en `VentaService.php:313` y `TipoIva::obtenerPrecioSinIva`).
  - **D3 — Repricing**: revisión post-compra + flag automático opt-in por artículo.
  - **D4 — Compra no fiscal de RI**: todo lo pagado es costo (costo computable
    estándar; no se descompone IVA teórico).
  - **D5 — Costos por sucursal + consolidado**: el usuario anticipa transferencias
    inter-sucursal (central provee a otras) como fase futura explícita.
  - **D6 — Descuentos por renglón**: anidados en cascada a partir del neto del
    renglón; cada renglón puede tener varios.
  - **D7 — UI de compras**: los detalles finos del componente de carga se discuten
    antes de la fase de UI de compras (hoy Fase 6); este spec garantiza que los
    datos los soporten.
- 2026-07-01 (segunda ronda, revisión de persistencia con el usuario):
  - **D8 — Factor de conversión por proveedor**: se compra en bultos, se stockea
    en unidades. `cantidad` del detalle queda SIEMPRE en unidades de stock para
    no tocar el código de stock existente.
  - **D9 — Conceptos de pie de factura**: tabla `compra_conceptos` con flag
    `computa_costo` y prorrateo por importe (landed cost). Impuestos internos =
    costo real (no recuperable).
  - **D10 — Flujo borrador → confirmar**: el efecto (stock+costos+ledger) ocurre
    SOLO al confirmar, en una transacción. Reusa el enum `estado` existente
    (`pendiente` = borrador).
  - Adiciones directas de la revisión: número real del comprobante del proveedor
    + UNIQUE anti-duplicado; `fecha_comprobante` ≠ carga + `fecha_vencimiento`;
    `compra_ivas` + netos en encabezado (Libro IVA Compras cuadra contra la
    factura física); descuento global del comprobante prorrateado a renglones.
  - Prorrateos SIEMPRE por importe del renglón (no por cantidad): es el criterio
    estándar y evita distorsiones cuando conviven artículos caros y baratos.
- 2026-07-01: percepciones sufridas NO integran el costo para RI — ya están
  resueltas por la capa fiscal (`movimientos_fiscales`, pago a cuenta). Para
  no-RI tampoco se integran en v1 (simplificación; anotar si el contador pide).
- 2026-07-01: el PPP no se historiza (una fila por compra sería ruido);
  reconstruible desde `movimientos_stock.costo`.
- 2026-07-01: `anularDesdeCompra` (fiscal) debe ajustarse al patrón NC de la
  revisión Fable (reversa negativa en el período de la cancelación) al cablearlo.
- 2026-07-02 (tercera ronda, lado pago + saneamiento profundo, con el usuario):
  - **D11 — Estados desacoplados del pago**: `estado` queda solo como ciclo de
    vida (`borrador`/`completada`/`cancelada`); lo impago se deriva de
    `saldo_pendiente > 0`. Resuelve el conflicto con el código actual donde
    `pendiente` = cta cte impaga CON stock movido (incompatible con el borrador
    de D10). Migración: `pendiente` → `completada`.
  - **D12 — Cuenta corriente de proveedores COMPLETA** (elección explícita del
    usuario sobre la v1 simple): ledger espejo del de clientes, con pago al alta
    de la compra o posterior por el mismo modelo de cobranzas. Sub-decisiones:
    - Semántica de pasivo: HABER aumenta la deuda (compra), DEBE la reduce (pago).
    - TODO pago pasa por `PagoProveedorService`; el pago inicial al confirmar se
      materializa como PagoProveedor (un solo camino de escritura).
    - Sin `limite_credito` del lado proveedor; `dias_pago` para vencimientos.
    - Sin moneda extranjera en v1 (columnas ME se agregan si hace falta).
    - Reemplaza `CompraService::registrarPago()` (hardcodeaba FP 'efectivo').
  - **D13 — Costo de recetas/elaborados**: fase futura explícita (no infla este
    spec, que ya tiene 9 fases). `RecetaIngrediente` hoy no tiene costo.
  - **D14 — La compra es solo SucursalAware; la caja pertenece al PAGO**: la
    compra no impacta una caja específica. El pago sale por default de la caja
    activa (validando saldo); el permiso `compras.pagar_avanzado` habilita otra
    caja, efectivo de Tesorería (requiere nuevo
    `TesoreriaService::registrarEgresoExterno()` — hoy solo existe el ingreso
    externo) o cuenta de empresa por transferencia. Origen por renglón del
    desglose (`pago_proveedor_pagos.origen` + caja_id/cuenta_empresa_id).
  - **D15 — Toggle compra NO FISCAL**: desactiva todo cálculo de impuestos
    (sin IVA por renglón, sin compra_ivas, sin percepciones, nada al ledger);
    el total pagado es el costo (consistente con D4).
  - **D16 — Anulación de OP por origen**: el bloqueo por turno de caja cerrado
    aplica solo a renglones con origen 'caja'; una OP pagada desde tesorería o
    cuenta de empresa se puede anular siempre (el saldo vuelve a su origen).
  - Visiones futuras anotadas (NO desarrollar en este spec, solo no bloquearlas):
    compensación de saldos cliente↔proveedor cuando la entidad es ambas cosas;
    módulo propio de operaciones inter-sucursal (compra en una = venta en otra,
    con precio especial interno).
- 2026-07-02 (cuarta ronda, doble revisión — huecos funcionales + auditoría de
  consistencia contra el código real):
  - **Replanteo total del código de compras** (directiva del usuario): el código
    actual de compras es descartable, se reescribe sin compatibilidad. Los
    contratos firmes son los TOUCHPOINTS, verificados contra el código real:
    todos existen como el spec asume (ImpuestoService::registrarDesdeCompra/
    anularDesdeCompra en Fiscal/ImpuestoService.php:524/601 con firma compatible,
    HistorialPrecio::registrar, obtenerPrecioBaseEfectivo, TipoIva, formas_pago
    con codigo CTA_CTE + cuenta_empresa_id, contraasientos de MovimientoCaja y
    MovimientoCuentaCorriente). Faltantes a CREAR: `TesoreriaService::
    registrarEgresoExterno` (solo existe el ingreso), `MovimientoCaja::
    crearEgresoPagoProveedor` (no hay factory de egreso) y extraer
    `aplicarRedondeo()` (protected en CambioMasivoPrecios.php:291) a PrecioService.
  - **D17 — Cancelar compra con pagos aplicados**: el usuario ELIGE al cancelar:
    anular pagos en cascada (error de carga) o dejarlos como saldo a favor del
    proveedor (plata que realmente salió). Turno cerrado en renglón caja ⇒ solo
    saldo a favor.
  - **D18 — NC de proveedor EN V1** (RF-21, elección explícita del usuario sobre
    fase futura): fila de `compras` con tipo NC + `compra_origen_id`, efectos
    inversos PARCIALES (stock, fiscal en período de la NC, ledger); costos NO se
    recalculan.
  - **D19 — Pago por sucursal activa** (espejo estricto de clientes): compras
    pendientes/FIFO/aging de la sucursal activa; saldo global solo informativo.
  - **D20 — `compras.confirmar` separado de `compras.crear`**: cargar borradores
    ≠ confirmar (que mueve stock/costos/ledger/plata).
  - Completada = INMUTABLE (corregir = cancelar + recargar); el anti-duplicado
    pasa a validación de aplicación que excluye canceladas (el UNIQUE de BD
    impedía recargar).
  - Fixes de consistencia de la auditoría: criterio cascada corregido a $829,35
    (era $829,58, no cuadraba con la fórmula); `pago_proveedor_pagos` ganó
    estado/cierre_turno/FKs a movimientos POR RENGLÓN (espejo fiel de
    cobro_pagos — sin eso D16 no era implementable); auditoría espejo en
    pagos (saldo_anterior/posterior, anulado_por/at); migraciones 9-11
    asignadas a Fase 2; `compras.fecha` timestamp ON UPDATE saneada a date;
    citas VentaService 429→313; enum ledger ganó nota_credito/devolucion_saldo.
  - Hallazgos de exploración incorporados al saneamiento (RF-12/RF-20): el
    componente `Compras` NO era SucursalAware/CajaAware (el spec lo afirmaba mal);
    permisos de compras solo en docblock (sin seed/middleware/authorize); drift
    de `forma_pago` mayor al anotado (dos sets distintos SQL vs código);
    `cancelarCompra()` sin contraasiento de caja.
- 2026-07-09 (quinta ronda: revisión impositiva profunda pre-apply, verificada
  contra código real y APROBADA por el usuario):
  - **Fixes críticos del circuito IVA** (el corazón del balance compra/venta):
    (1) período del crédito = `fecha_comprobante` — `registrarDesdeCompra` usaba
    `compra->fecha` (ImpuestoService.php:540), que además hoy es timestamp
    ON UPDATE y se auto-pisa; (2) fuente canónica del crédito = `compra_ivas`
    (nunca la suma de renglones) + validación de cuadre con tolerancia +
    sugerencia calculada DESPUÉS de descuentos; (3) gate explícito del caller:
    crédito solo si fiscal AND discrimina AND CUIT comprador RI (el service no
    gatea, a diferencia del débito que gatea RI en línea 441); (4) la validación
    comprobante×CUIT pasa a ADVERTENCIA — post RG 5003/2021 un monotributista
    SÍ recibe factura A (CondicionIva ya documenta códigos 6/13/16 válidos);
    la regla de costo (discrimina + no-RI ⇒ todo al costo) ya lo manejaba bien.
  - **D21 — alícuota efectiva por condición del comercio**: las fórmulas de
    precio sugerido y margen anulan la alícuota si el CUIT default del comercio
    no es RI (para un monotributista el costo es bruto y todo el precio es
    ingreso; la fórmula única le inflaba el sugerido y le mentía el margen).
    También respetan `articulo.precio_iva_incluido=false`. Única puerta:
    `CostoService::alicuotaEfectiva()`.
  - **D22 — Cuentas de compra** (pedido del usuario): agrupación de gestión
    para reportes de gastos. Configuración elegida: catálogo `cuentas_compra`
    + default por PROVEEDOR + override por COMPRA (por artículo no tipifica el
    gasto; solo por compra obliga a elegir siempre). Reporte "Compras por
    cuenta". Split por renglón anotado como futuro (facturas mixtas).
  - **Menú: grupo padre "Compras"** (pedido del usuario): Compras / Proveedores
    / Pagos a proveedores / Reportes. Verificado: hoy NO hay ítem de compras en
    menu_items ni ABM de proveedores (solo un select en Compras) → componente
    `GestionarProveedores` NUEVO en Fase 5 + permisos `compras.proveedores` y
    `compras.reportes`.
  - Menores: rename `costo_unitario_neto`→`costo_unitario_computable` (en B/C/
    no fiscal contiene IVA no recuperable — nombre honesto); conceptos con
    `tipo_iva_id` y monto en la misma base que renglones (flete de factura A =
    neto, su IVA en compra_ivas); NC carga su PROPIO desglose de IVA (el
    proporcional solo precarga); factura M = A para el crédito (retenciones
    manuales v1, pregunta al contador); UI muestra origen/tipo de comprobante
    del costo vigente (salto de base A↔B ~21% es real pero hay que verlo);
    drop de `compras.caja_id` (huérfana con D14).
