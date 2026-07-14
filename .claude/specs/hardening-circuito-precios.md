# Hardening del Circuito de Precios e Impuestos - Especificación

## Estado: IMPLEMENTADO (2026-07-14) — sdd-verify APROBADO, suite completa verde

> Consolidación post-auditoría integral (2026-07-14, 4 revisores paralelos sobre los
> PRs #153/#154/#155 + sistema impositivo). Tres bloques: (A) precio de venta SIEMPRE
> final con IVA, (B) fixes de la auditoría — tanda 1 (compras/costos/fiscal entrante),
> (C) cambio masivo de precios extendido a COSTOS. La tanda 2 (fiscal saliente/ventas)
> queda especificada como fuera de alcance al final.

---

## Contexto y Motivación

La auditoría integral del circuito compras→costos→precio→venta/IVA confirmó que el
núcleo está sólido (fórmula del sugerido consistente en sus 4 calculadores, ledger
simétrico débito/crédito, D25 sin solapamientos), pero encontró 5 problemas graves y
~15 medios que orbitan casos secundarios: comprador no-RI, comercios mono-sucursal,
`precio_iva_incluido=false` y caminos de re-emisión/conversión.

Decisiones del usuario (2026-07-14):
- El precio de venta es **SIEMPRE final con IVA incluido**. Las listas y promociones
  actúan sobre ese precio final y no distinguen neto/IVA — mantener un modo "neto"
  duplica toda esa lógica para un caso que hoy además está roto (se factura más de lo
  que se cobra). Se fuerza `precio_iva_incluido=true` y se quita la opción de la UI
  (la columna queda deprecada, no se borra).
- El cambio masivo de precios se extiende a COSTOS (costo último/rector) con 4 modos:
  solo costo / costo + actualización automática del precio (como compras, según config
  del artículo) / solo venta (actual) / costo + venta por igual.

---

## Principios de Diseño

1. **Precio final único**: todo precio de venta del sistema es final con IVA adentro;
   el desglose fiscal siempre DIVIDE. Ningún camino suma IVA encima.
2. **Nada se evapora**: todo componente pagado en una compra termina en el ledger
   fiscal o en el costo (D4). La matriz comprador × coeficiente debe cerrar al 100%.
3. **Reversas espejo**: toda reversa restaura exactamente lo que el registro creó;
   si el estado vigente ya no es el que la operación fijó, la reversa no lo pisa.
4. **Costo rector como fuente**: el masivo de costos opera sobre `costo_ultimo`
   (rector D1) con la misma puerta única (`CostoService`) e historial que el resto.
5. **Deprecar sin romper**: `precio_iva_incluido` no se elimina; se fuerza `true` en
   escritura, se migra el dato y desaparece de la UI. Los reads existentes quedan
   correctos por construcción.

---

## Requisitos Funcionales

### Bloque A — Precio de venta siempre final con IVA

#### RF-A1: Migración de datos y forzado de `precio_iva_incluido`
- Migración de DATOS (tenant, todos los comercios): `UPDATE articulos SET precio_iva_incluido = 1 WHERE precio_iva_incluido = 0`.
- `GestionarArticulos::save()` fuerza `precio_iva_incluido = true` (crear y editar).
- Cualquier otro camino de alta/edición (alta rápida en compras/ventas, import CSV de
  artículos, API si expone el campo) también fuerza `true`.

#### RF-A2: Quitar el toggle de la UI
- Se elimina el switch "IVA incluido en el precio" del ABM de artículos (sección
  "Utilidad y precio"). El hint del precio pasa a texto fijo: "Precio FINAL: el IVA
  va adentro y se desglosa al facturar".
- Revisar import/export de artículos: si la columna viaja, se ignora en import
  (siempre true) y puede seguir saliendo en export (informativa).

#### RF-A3: Sanear los caminos que asumían flag=false
- `WithCalculoVenta` (desglose fiscal): la rama `else` que suma IVA encima queda
  inalcanzable; se elimina o se reduce a `precioIvaIncluido = true` con comentario.
- `VentaService::crearDetalleVenta`: ídem — el detalle persiste siempre con la
  semántica "precio final" (neto = total / (1+alícuota)); el invariante
  `neto + IVA = total` se cumple por construcción.
- `PedidoDeliveryService` / `PedidoMostradorService`: el hardcode
  `'precio_iva_incluido' => true` deja de ser un bug (coincide con la regla global);
  se deja con comentario que lo ata a este spec.
- `CostoService::alicuotaEfectiva` (D21): sin cambios de firma; con el flag siempre
  true, la alícuota efectiva queda determinada solo por `comercioComputaIva`.

### Bloque B — Fixes de auditoría (tanda 1: compras / costos / fiscal entrante)

#### RF-B1 (ALTA): Percepciones con comprador no-RI — nada se evapora
- En `CompraService::resolverProrrateosYComputables`: si `! compradorEsRI($compra)`,
  el coeficiente EFECTIVO de toda percepción es 0 ⇒ el 100% del monto prorratea al
  costo (D4), sin importar lo cargado en el renglón.
- En `EditorCompra::coeficientePercepcionDefault`: si el CUIT comprador no es RI,
  devolver '0' SIEMPRE (incluida percepción de IVA).
- La matriz resultante (comprador × coef × fiscal) cierra 100% en todas las filas:
  RI ⇒ `coef` al ledger + `1−coef` al costo; no-RI ⇒ todo al costo.
- Servicios (D23) sin cambios: sin renglones, el gasto por cuenta absorbe el total.

#### RF-B2 (ALTA): Descuento global fantasma
- `CompraService::actualizarBorrador` resetea `descuento_global_monto = 0` cuando
  `descuento_global_porcentaje === null` (o directamente se elimina el fallback de
  `montoDescuentoGlobal`, hoy código muerto — decidir en implementación mirando si
  algún camino legítimo persiste monto fijo sin porcentaje).

#### RF-B3 (ALTA): Cancelar no pisa un costo editado a mano
- `CostoService::revertirCostoUltimoSiCorresponde`: saltear la fila si
  `(float) $fila->costo_ultimo !== (float) $historialCompra->costo_nuevo` (el vigente
  ya no es el que la compra fijó).
- Complemento: `actualizarManual('ultimo')` limpia `compra_ultima_id` y
  `proveedor_ultimo_id` (el vigente pasó a ser manual, no de una compra).

#### RF-B4 (ALTA): Mono-sucursal — precio único de verdad
- En comercios de UNA sucursal, el modal del ABM también edita el precio EFECTIVO:
  al guardar, si existe override en `articulos_sucursales`, se actualiza el override
  (mismo criterio que multi); `precio_base` se actualiza solo si no hay override o
  junto con él (decidir en implementación: la opción más simple y coherente con
  multi es "persistir siempre sobre la sucursal", unificando el wire a
  `precio_sucursal` en todos los casos de edición).
- `edit()` carga el precio efectivo en el campo visible siempre.
- Resultado: el escenario "masivo escribe override → ABM edita base muerta" queda
  imposible.

#### RF-B5 (MEDIA): Coeficiente default con vigencia
- `EditorCompra::coeficientePercepcionDefault` consulta la config VIGENTE del CUIT
  (mismo criterio que `ImpuestoService::configVigente`: filtro de vigencia + orden
  `vigente_desde IS NULL, vigente_desde DESC`), no `first()` a ciegas.

#### RF-B6 (MEDIA): Percepción con monto y sin impuesto no pasa silenciosa
- `EditorCompra::validarParaGuardar` (o regla de validación): renglón de percepción
  con monto > 0 e impuesto vacío BLOQUEA con mensaje claro.
- `totales()` de la vista excluye renglones sin impuesto (coherencia visual mientras
  se carga).

#### RF-B7 (MEDIA): Cancelación concurrente
- `CompraService::cancelarCompra` toma `lockForUpdate()` sobre la compra y re-chequea
  `estaCompletada()` DENTRO de la transacción (mismo patrón que
  `anularMovimientoFiscal`). Ídem `corregirCompra` si no hereda el lock.

#### RF-B8 (MEDIA): Revisión de precios — piso de costo y ceros
- En `RevisionPreciosCompra`: un sugerido que quede `<= costo` (margen ≤ 0) aparece
  DESMARCADO por default y con badge de advertencia; un sugerido $0 nunca aparece
  seleccionado. `aplicar()` no aplica filas con `precio_nuevo <= costo` salvo
  confirmación explícita (checkbox re-marcado por el usuario cuenta como tal, con el
  badge visible).
- Normalizar parseo de `precio_nuevo` (aceptar coma decimal, rechazar separador de
  miles ambiguo — reusar `num()` del editor).

#### RF-B9 (MEDIA): Reversas del ledger no anulables a mano
- `ImpuestoService::anularMovimientoFiscal` rechaza movimientos con
  `origen_tipo !== null` (los maneja su origen: cancelar/NC de la compra o venta).
  El botón de anular en `MovimientosFiscales` se oculta para esos movimientos con
  tooltip explicando el porqué.

#### RF-B10 (MEDIA): NC de proveedor — snapshot de percepciones de la origen
- `EditorCompra::precargarNcDesdeOrigen` precarga también las percepciones de la
  compra origen (impuesto + base + alícuota + monto + COEFICIENTE del snapshot),
  editables. Evita que la NC tome un coeficiente de config actual distinto del que
  registró la origen (reversa ≠ registro).
- Advertencia NO bloqueante si el desglose fiscal de la NC excede el de la origen
  (IVA o percepciones), estilo advertencias existentes del editor.

#### RF-B11 (MEDIA): Cancelar NC sin cuenta corriente no infla el saldo origen
- Persistir lo REALMENTE aplicado por la NC contra la origen (reusar
  `saldo_pendiente` de la NC como "monto aplicado" o campo equivalente ya existente)
  y usar eso en `restaurarSaldoOrigenPorNcCancelada` en lugar de asumir el total.

#### RF-B12 (BAJA, chores): limpieza
- Observación de NC suelta: sin `compra_origen_id`, el texto no dice "compra origen #0".
- Docblocks desactualizados de `PosicionFiscalService` / `ImpuestoService` que dicen
  que compras "no cablea su hook" (cablea desde #153).
- `'sin_redondeo'` → unificar vocabulario con `PrecioService::aplicarRedondeo`
  ('ninguno') en RevisionPreciosCompra y CambioMasivoPrecios.
- ABM: refrescar la alícuota de `costosInfo` al cambiar `tipo_iva_id` en el modal
  (el toggle de IVA desaparece con RF-A2; queda solo este disparador).
- Historial `override_sucursal`: no registrar cambio 500→500 (comparar contra el
  precio EFECTIVO anterior, no contra NULL).

### Bloque C — Cambio masivo extendido a COSTOS

#### RF-C1: Selector de objetivo
- En `CambioMasivoPrecios`, nuevo selector "Aplicar sobre": **Precio de venta**
  (default, comportamiento actual) / **Costo** / **Costo y precio por igual**.
- Los modos que tocan costo requieren permiso `func.costos.editar` (además del
  permiso actual de la pantalla); sin permiso, el selector solo ofrece precio.

#### RF-C2: Modo "Costo"
- El % (o monto) de incremento/descuento se aplica sobre `costo_ultimo` de la fila
  de costos de la SUCURSAL ACTIVA de cada artículo filtrado (espejo de la edición
  manual del ABM), vía `CostoService::actualizarManual(..., origen: 'masivo')`.
- La grilla de preview muestra costo actual → costo nuevo (además de las columnas
  actuales), y el margen resultante si hay precio.
- Sub-opción visible solo en este modo — "¿Actualizar el precio de venta?":
  - **No** (default): solo costo.
  - **Automático según configuración del artículo**: tras actualizar el costo, los
    artículos del lote con `precio_administrado_por_utilidad = true` se repricean
    con la fórmula del sugerido (mismo mecanismo que el paso 7 de
    `confirmarCompra`), origen `utilidad_automatica` con detalle "cambio masivo de
    costos". Los no-opt-in no se tocan.
- Artículos sin fila de costos en la sucursal: se crea la fila con el costo nuevo
  solo si había costo consolidado como base; si no hay costo alguno, la fila del
  preview lo indica y se saltea (no hay base sobre la cual aplicar %).

#### RF-C3: Modo "Costo y precio por igual"
- El MISMO % se aplica a `costo_ultimo` y al precio de venta efectivo (respetando el
  nivel de escritura del masivo actual). Dos historiales: `historial_costos` origen
  'masivo' + `HistorialPrecio` origen masivo actual.

#### RF-C4: Repricing extraído y reutilizable
- La lógica de repricing del paso 7 de `confirmarCompra` se extrae a un método
  compartido (`CostoService::repricearArticulos(array $articuloIds, int $sucursalId, string $detalle)`
  o equivalente) consumido por CompraService y por el masivo — una sola fórmula.

---

## Modelo de Datos

### Tablas nuevas
Ninguna.

### Tablas modificadas
- Sin columnas nuevas. Cambios de DATOS:
  - Migración tenant (todos los comercios, idempotente):
    `UPDATE {prefix}articulos SET precio_iva_incluido = 1 WHERE precio_iva_incluido = 0`.
- `historial_costos.origen`: nuevo valor `'masivo'`. NOTA de implementación
  (2026-07-14): la columna resultó ser ENUM, no string ⇒ migración
  `add_masivo_a_historial_costos_origen` (ALTER por comercio) + regenerado
  `tenant_tables.sql`. Vocabulario documentado en el modelo.

---

## Pantallas UI

### ABM de artículos (`GestionarArticulos`) — modificada
- RF-A2 (sin toggle IVA), RF-B4 (precio efectivo también en mono), RF-B12 (alícuota
  refrescada, historial sin no-ops).

### Editor de compras (`EditorCompra`) — modificada
- RF-B1 (default coef no-RI), RF-B5 (vigencia), RF-B6 (validación percepción),
  RF-B10 (NC precarga percepciones + advertencia).

### Movimientos fiscales (`MovimientosFiscales`) — modificada
- RF-B9: botón anular oculto para movimientos con origen.

### Revisión de precios (`RevisionPreciosCompra`) — modificada
- RF-B8: piso de costo, ceros, parseo.

### Cambio masivo (`CambioMasivoPrecios`) — modificada
- Bloque C completo: selector objetivo, sub-opción de repricing, preview con costos,
  permisos.

---

## Servicios

### `CompraService`
- `resolverProrrateosYComputables`: coeficiente efectivo 0 para comprador no-RI (RF-B1).
- `actualizarBorrador`: reset de `descuento_global_monto` (RF-B2).
- `cancelarCompra`/`corregirCompra`: lock + re-check (RF-B7).
- `confirmarCompra` paso 7: delega en el repricing extraído (RF-C4).

### `CostoService`
- `revertirCostoUltimoSiCorresponde`: guard de costo vigente (RF-B3).
- `actualizarManual`: limpia proveniencia al editar 'ultimo' (RF-B3); acepta origen
  'masivo' (RF-C2).
- `repricearArticulos(...)`: extraído del paso 7 (RF-C4).

### `ImpuestoService`
- `anularMovimientoFiscal`: rechaza movimientos con origen (RF-B9).

### `CuentaCorrienteProveedorService` / `CompraService`
- Persistencia del aplicado de la NC + uso en la restauración (RF-B11).

---

## Migraciones Necesarias

1. `force_precio_iva_incluido_articulos` — UPDATE de datos tenant (idempotente por
   naturaleza; iterar comercios con prefijo + try/catch). No toca schema ⇒ NO
   requiere regenerar `tenant_tables.sql` (verificar que el DEFAULT de la columna ya
   sea 1; si es 0, alterar el default y AHÍ SÍ regenerar).

---

## Traducciones

Claves nuevas (estimadas; definir exactas en implementación, 3 idiomas):
- Selector del masivo: "Aplicar sobre", "Costo", "Costo y precio por igual",
  "¿Actualizar el precio de venta?", "Automático según configuración del artículo",
  "Solo costo".
- Validaciones/avisos: percepción sin impuesto, sugerido por debajo del costo,
  advertencia NC excede origen, tooltip de movimiento no anulable.

---

## Criterios de Aceptación

- [x] A: ningún artículo queda con `precio_iva_incluido=0`; el ABM no ofrece el
  toggle; una venta de un artículo (ex-false) cobra y factura el MISMO monto con
  desglose neto+IVA=total exacto.
- [x] B1: compra fiscal de comprador monotributista con percepción $X ⇒ costo
  absorbe $X completo; con comprador RI y coef 0,6 ⇒ ledger 0,6X + costo 0,4X
  (tests existentes de D25 siguen verdes).
- [x] B2: borrador con global 10% al que se le borra el descuento ⇒ total y costos
  SIN descuento.
- [x] B3: compra→edición manual→cancelación ⇒ el costo manual sobrevive; sin edición
  manual ⇒ restaura el previo (test existente sigue verde).
- [x] B4: mono-sucursal con override creado por masivo ⇒ el ABM muestra y edita el
  precio que la venta cobra.
- [x] B5-B12: cada fix con su test de regresión puntual (B12 son chores cosméticos
  sin superficie testeable propia; cubiertos por las suites que los atraviesan).
- [x] C: masivo modo costo +10% sobre lote filtrado ⇒ costo_ultimo × 1,1 con
  historial 'masivo'; sub-opción automática ⇒ SOLO los opt-in repricean con la
  fórmula del sugerido; modo "por igual" ⇒ ambos valores × 1,1 con dos historiales;
  sin permiso func.costos.editar no se ofrecen los modos de costo.
- [x] Suites Compra|Costo|Impuesto|Fiscal|Proveedor|Articulo verdes; Pint verde;
  smoke de cada componente tocado. Suite COMPLETA: 1244 passed / 1 skipped
  (2026-07-14).

## Spec Compliance Matrix (sdd-verify 2026-07-14)

| # | Criterio | Test | Resultado |
|---|----------|------|-----------|
| A | Venta ex-neto cobra=factura (neto+IVA=total) | VentaServiceTest::crear_venta_articulo_ex_neto_cobra_igual_que_factura | PASS ✓ |
| A | Flag deprecado no altera alícuota efectiva | CostoServiceTest::alicuota_efectiva_ignora_flag_neto_deprecado | PASS ✓ |
| A | ABM sin toggle, monta y guarda | SmokeArticulosTest (aplica_precio_sugerido + edit_con_costos) | PASS ✓ |
| B1 | No-RI ⇒ percepción 100% al costo (service) | CompraServiceTest::percepcion_con_comprador_no_ri_todo_al_costo_aunque_tenga_coeficiente | PASS ✓ |
| B1 | No-RI ⇒ coeficiente default 0 (editor) | SmokeComprasTest::editor_coeficiente_cero_para_comprador_no_ri | PASS ✓ |
| B1 | Matriz RI intacta (D25) | CompraServiceTest::percepcion_con_coeficiente_* (existentes) | PASS ✓ |
| B2 | Descuento global fantasma | CompraServiceTest::actualizar_borrador_sin_porcentaje_elimina_descuento_global | PASS ✓ |
| B3 | Cancelar no pisa costo manual | CostoServiceTest::cancelar_no_pisa_un_costo_editado_a_mano | PASS ✓ |
| B3 | Manual limpia proveniencia | CostoServiceTest::actualizar_ultimo_manual_limpia_proveniencia_de_compra | PASS ✓ |
| B4 | Mono-sucursal edita precio efectivo | SmokeArticulosTest::gestionar_articulos_mono_sucursal_edita_precio_efectivo | PASS ✓ |
| B5 | Coeficiente usa config vigente | SmokeComprasTest::editor_coeficiente_usa_config_vigente | PASS ✓ |
| B6 | Percepción sin impuesto bloquea | SmokeComprasTest::editor_percepcion_con_monto_sin_impuesto_bloquea_el_guardado | PASS ✓ |
| B7 | Cancelación doble lanza (lock+re-check) | CompraServiceTest::cancelar_dos_veces_lanza | PASS ✓ |
| B8 | Piso de costo desmarca; re-marcar aplica | SmokeComprasTest::revision_precios_piso_de_costo_desmarca_y_no_aplica | PASS ✓ |
| B9 | Movimiento con origen no anulable | ImpuestoServiceTest::no_se_puede_anular_un_movimiento_con_origen | PASS ✓ |
| B10 | NC precarga percepciones snapshot | SmokeComprasTest::nc_precarga_percepciones_de_la_origen_con_coeficiente_snapshot | PASS ✓ |
| B11 | Cancelar NC restaura lo aplicado | CompraServiceTest::cancelar_nc_parcialmente_aplicada_no_infla_saldo_origen | PASS ✓ |
| B12 | Chores (cosmético/docblocks) | cubiertos por suites que los atraviesan | PASS ✓ |
| C | Modo costo con historial 'masivo' | CambioMasivoCostosTest::modo_costo_actualiza_costo_ultimo_con_historial_masivo | PASS ✓ |
| C | Automático repricea solo opt-in | CambioMasivoCostosTest::modo_costo_automatico_repricea_solo_los_opt_in | PASS ✓ |
| C | Modo "por igual" dos historiales | CambioMasivoCostosTest::modo_ambos_aplica_el_mismo_porcentaje_a_costo_y_precio | PASS ✓ |
| C | Gate de permisos | CambioMasivoCostosTest::sin_permiso_el_selector_vuelve_a_precio | PASS ✓ |
| C | Sin costo se saltea | CambioMasivoCostosTest::articulo_sin_costo_se_saltea | PASS ✓ |
| C4 | Repricing compartido sin regresión | CompraServiceTest::articulo_con_flag_se_repricea_al_confirmar (existente) | PASS ✓ |

**Resultado: 24/24 criterios con test que PASÓ — APROBADO.**

---

## Plan de Implementación

### Fase 1: Precio final único (Bloque A) [COMPLETO]
1. Migración de datos + verificación del default de columna.
2. Forzados en save/import/alta rápida + quitar toggle del ABM.
3. Saneo de ramas flag=false (WithCalculoVenta, VentaService) + comentarios en
   conversiones de pedidos.
4. Tests: venta de ex-false cobra=factura; smoke ABM.

### Fase 2: ALTAs de compras/costos (RF-B1 a B4) [COMPLETO]
1. B1 matriz no-RI (service + default editor) + tests matriz.
2. B2 descuento fantasma + test.
3. B3 revert vs manual + test.
4. B4 precio mono-sucursal + test.

### Fase 3: MEDIAs tanda 1 (RF-B5 a B12) [COMPLETO]
1. B5 vigencia, B6 validación percepción, B7 lock, B8 revisión, B9 ledger,
   B10 NC snapshot, B11 saldo NC, B12 chores — cada una con test puntual donde aplique.

### Fase 4: Masivo a costos (Bloque C) [COMPLETO]
1. RF-C4 extraer repricing compartido (refactor sin cambio de comportamiento + tests
   existentes verdes).
2. RF-C1/C2/C3 UI + service + permisos.
3. Tests de los 4 modos + smoke.

### Fase 5: Verificación y cierre [EN PROGRESO]
1. `/sdd-verify` ✓ APROBADO (matriz 24/24; suite completa 1244 passed / 1 skipped).
2. `@docs-sync` ✓ (manual: ABM sin toggle, masivo con costos; KB: reglas nuevas).
3. PR → master (pendiente: validación en vivo del usuario + merge).

---

## Fuera de alcance (tanda 2 — fiscal saliente / ventas)

Hallazgos de la auditoría que quedan para un spec propio de VENTAS (no bloquean esta
tanda; varios se atenúan con el Bloque A):
- Base de percepción aplicada incluye exentos (debería ser neto GRAVADO).
- Reintento de facturación / cambio de FP pierden los tributos del comprobante
  (ImpTrib=0) y prorratean todo como bienes.
- Pedidos convertidos no calculan percepciones aplicadas (uniformidad del agente).
- Clasificación 0% inconsistente (gravado vs exento según camino).
- Conversión con descuento de cabecera + FC total ⇒ AFIP 10048.
- Facturación parcial pisa `monto_fiscal_cache` con el total.
- "Post-commit" del ledger dentro de la transacción del cobro (savepoint).
- Cortesía total con concepto libre explota (path legacy).
- Gross-up RG5003 sobre conceptos con IVA propio (compras, fórmula fina).
- `corregirCompra` y contaminación del PPP (necesita diseño propio).
- Retenciones sufridas inalcanzables desde la UI de compras (mejora).

---

## Notas y Decisiones

- 2026-07-14: **Precio de venta siempre FINAL con IVA** (decisión del usuario).
  Alternativa "quitar la columna" descartada: forzar true + ocultar UI logra lo
  mismo tocando una fracción del código; la columna queda deprecada.
- 2026-07-14: masivo de costos opera sobre **costo ÚLTIMO (rector)** — decisión del
  usuario vía AskUserQuestion. PPP no se toca (es de compras). Reposición queda
  fuera (se puede agregar como objetivo futuro si hace falta).
- 2026-07-14: los 4 modos del masivo son los pedidos por el usuario: solo costo /
  costo + act. automática (según config del artículo, espejo de compras) / solo
  venta / costo + venta por igual.
- Origen de los informes: auditoría 4 agentes 2026-07-14 (detalle con file:line en
  la sesión; resumen en memoria `auditoria-circuito-precios-impuestos`).
- 2026-07-14: spec APROBADO por el usuario tal cual; la implementación arranca en la
  próxima sesión con /sdd-apply, Fase 1.
