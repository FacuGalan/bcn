# Cambio de Forma de Pago en Ventas Registradas - Especificación

## Estado: EN PROGRESO — Fases 1-7 COMPLETAS + pivot mixto + pivot FC real pendiente de implementar

> **Pivot importante aplicado (2026-04-15)**: el flujo "1 pago → 1 pago con monto libre" se reemplazó por
> "1 pago → N pagos mixtos con suma obligatoria === monto viejo". El total de la venta es
> ahora INMUTABLE. La fiscalidad se decide por delta del monto facturado (flag `facturar`
> por cada pago del desglose). Ver handoff en `.claude/handoffs/cambio-forma-pago-venta-contexto.md`.
>
> **Pivot FC real (2026-04-16) — EN IMPLEMENTACIÓN**: se reemplaza el enfoque "FC nueva queda como flag"
> por emisión real automática. Regla binaria: si `monto_facturado_viejo != monto_facturado_nuevo`,
> se emite **NC por el pago viejo + FC nueva por los pagos nuevos facturables**, independientemente
> del signo del delta. La transacción se divide en 2 fases para tolerar fallos ARCA. Nuevo estado
> `estado_facturacion` en venta_pagos + reporte independiente de pagos pendientes de facturar.
> Ver sección "Extensión 2026-04-16: FC nueva real + estado_facturacion" más abajo.

> Feature que permite modificar, agregar o eliminar formas de pago de una venta ya registrada, sin afectar el total de artículos de la venta. Incluye gestión fiscal automática (NC/FC según config) y soporte para turnos cerrados con reporte de ajustes post-cierre.

---

## Contexto y Motivación

Hoy, una vez registrada una venta, los pagos asociados son inmutables. Si el cajero se equivoca (tildó débito en lugar de transferencia, cargó el monto mal, olvidó un pago), las únicas opciones son:

1. **Anular la venta completa** con `VentaService::cancelarVentaCompleta()` — revierte stock, pagos, CC, fiscal. Demasiado destructivo para un error simple de forma de pago.
2. **Pasar a cuenta corriente** con `anularPagosYPasarACtaCte()` — útil solo si el cliente no pagó.
3. **Anular solo parte fiscal** — útil solo para corregir fiscalidad.

Falta una operación intermedia: **corregir/cambiar los pagos manteniendo la venta**. Ejemplo del usuario: una venta de $500 con $200 efectivo + $300 débito donde el cajero quiere cambiar los $300 de débito por $300 de transferencia.

El feature también debe resolver un gap de visualización: el modal de detalle de venta actualmente **no muestra los cobros de cuenta corriente imputados** a los pagos CC de esa venta, lo que obliga al usuario a ir a otra pantalla para verlos.

---

## Principios de Diseño

1. **Append-only ledger**: no se modifican registros existentes. Los `venta_pagos` se anulan (estado='anulado') y se crean nuevos (estado='activo'). Consistente con el resto del sistema (CC, caja, cuenta empresa).
2. **Multi-tenant estricto**: todas las operaciones en conexión `pymes_tenant` con transacciones (`DB::connection('pymes_tenant')->transaction()`).
3. **Totales de venta inmutables**: el total base (artículos + descuentos + promociones) **nunca** cambia con esta operación. Solo puede variar `total_final` si cambia el ajuste por forma de pago, y eso se refleja via NC fiscal.
4. **Reuso de infraestructura existente**: aprovechar `ComprobanteFiscalService::crearNotaCredito()`, `CuentaEmpresaService::revertirMovimiento()`, `MovimientoCaja` contraasientos, patrón de UI de `GestionarCobranzas`.
5. **Fiscalmente correcto**: las decisiones de emisión de NC / FC nueva se derivan automáticamente de la matriz (ΔTotal, ΔCondiciónFiscal, config auto de sucursal).
6. **Permiso granular**: feature detrás de permiso dedicado, con permiso extra para operar sobre turnos cerrados y otro para saltear la NC cuando fiscal lo permitiría.
7. **Trazabilidad completa**: todo cambio queda auditado con usuario, timestamp, motivo y referencia al venta_pago anulado.

---

## Requisitos Funcionales

### RF-01: Cambio de forma de pago existente (sin afectar total)
- Permitir cambiar la `forma_pago_id` de un `venta_pago` activo por otra forma de pago disponible en la sucursal.
- Si ambas formas tienen el mismo `ajuste_porcentaje` efectivo y misma condición fiscal, es cambio interno puro sin toque fiscal.
- Se anula el `venta_pago` viejo y se crea uno nuevo con mismo `monto_base`.

### RF-02: Cambio de forma de pago con recálculo de ajuste
- Si la nueva FP tiene `ajuste_porcentaje` distinto, mostrar checkbox "Aplicar ajuste/recargo/descuento" (patrón `GestionarCobranzas`).
- Default del checkbox: encendido si la FP tiene ajuste configurado.
- Si usuario lo desmarca: `monto_ajuste = 0`, `monto_final = monto_base`.
- Si lo marca: se recalcula con `VentaPago::calcularMontoConAjuste($monto_base, $fp->ajuste_porcentaje)`.
- Afecta `total_final` de la venta (se recalcula como suma de `monto_final` de pagos activos).

### RF-03: Agregar nuevo pago a una venta
- Permitir agregar un nuevo `venta_pago` a una venta existente (útil para corregir un pago faltante).
- Incrementa `total_final` de la venta.
- Entra por la matriz fiscal como ΔTotal=Sí.

### RF-04: Eliminar un pago de una venta
- Permitir anular un `venta_pago` sin reemplazarlo (ej: se cargó de más).
- Decrementa `total_final` de la venta.
- Entra por la matriz fiscal como ΔTotal=Sí.

### RF-05: Cambio de monto de un pago
- Permitir cambiar el `monto_base` de un `venta_pago` (manteniendo misma FP).
- Se anula el viejo y se crea nuevo con monto distinto.
- Afecta `total_final` → entra por matriz fiscal.

### RF-06: Matriz de decisión fiscal automática

Variables:
- **ΔTotal**: ¿cambia el `total_final` de la venta?
- **ΔFiscal**: ¿cambia la condición fiscal? (nueva FP `factura_fiscal` ≠ vieja FP `factura_fiscal`, resuelto con override por sucursal)
- **Auto**: ¿sucursal tiene `facturacion_fiscal_automatica = true`?

| ΔTotal | ΔFiscal | Auto | NC | Factura nueva |
|--------|---------|------|-----|---------------|
| No | No | — | ❌ No | ❌ No |
| No | Sí (fiscal→no fiscal) | Sí | ✅ Automática | ❌ No |
| No | Sí (no fiscal→fiscal) | Sí | ❌ N/A | ✅ Automática |
| No | Sí | No | ❓ Preguntar | ❓ Preguntar (si nueva FP fiscal) |
| Sí | — | Sí | ✅ Obligatoria automática | ✅ Automática si nueva FP fiscal |
| Sí | — | No | ✅ Obligatoria | ❓ Preguntar |

Reglas específicas:
- Emitir NC implica llamar `ComprobanteFiscalService::crearNotaCredito()` sobre cada `ComprobanteFiscal` original vinculado a los `venta_pagos` anulados.
- Emitir nueva FC implica llamar al flujo estándar de facturación sobre los `venta_pagos` nuevos con FP fiscal.
- Saltear la NC cuando la matriz dice "preguntar" requiere permiso `func.modificar_pagos_sin_nc`.

### RF-07: Visualización de cobros CC en detalle de venta
- El modal de detalle de venta (`Ventas.blade.php`) debe mostrar, por cada `venta_pago` tipo CC, los cobros imputados (registros `cobro_ventas` relacionados).
- Columnas sugeridas: fecha del cobro, número de recibo, monto aplicado, interés aplicado, saldo resultante.
- Mejora independiente pero que se entrega junto al feature porque es necesaria para que el usuario vea el bloqueo de RF-14.

### RF-08: Bloqueos antes del cambio

No permitir cambiar un `venta_pago` si:
- La venta está `estado='cancelada'` → error "Venta ya cancelada"
- El `venta_pago` ya está `estado='anulado'` → error "Pago ya anulado"
- El `venta_pago` tiene cobros imputados (`cobrosAplicados()->exists()`) → error "Este pago tiene cobranzas aplicadas. Anule los cobros primero desde Cobranzas del cliente"
- La venta tiene puntos canjeados sobre ella (igual que cancelación completa) → error "Puntos ya canjeados"

### RF-09: Operación sobre turno cerrado
- Si el `venta_pago` original tiene `cierre_turno_id` NOT NULL, el cambio requiere permiso `func.cambiar_forma_pago_turno_cerrado`.
- Los movimientos de reversión (MovimientoCaja contraasiento, MovimientoCuentaEmpresa contraasiento) y los nuevos se crean con `cierre_turno_id = NULL` (van al turno actual abierto, o quedan "flotando" si no hay turno abierto).
- El cierre original **no se modifica** — su total histórico es inmutable.
- En la UI del modal debe mostrarse advertencia visual amarilla/ámbar: "Esta venta pertenece a un turno cerrado. El ajuste se registrará como movimiento post-cierre y quedará en el reporte de ajustes."

### RF-10: Reporte de ajustes post-cierre
- Nuevo componente Livewire: `App\Livewire\Cajas\AjustesPostCierre` accesible desde el menú de Cajas.
- Lista todos los `venta_pagos` anulados (`estado='anulado'`) y creados posteriormente cuyo `venta_pago` original pertenecía a un `cierre_turno_id` no NULL.
- Filtros: rango de fechas, sucursal, usuario que hizo el cambio, turno afectado.
- Columnas: fecha del ajuste, usuario, venta afectada, turno original, forma de pago vieja → nueva, ΔMonto, motivo.
- Se accede con permiso `func.ver_ajustes_post_cierre` (o `menu.ajustes-post-cierre`).

### RF-11: Motivo obligatorio
- Todo cambio requiere que el usuario ingrese un motivo (mismo patrón que `cancelarVentaCompleta`).
- Se guarda en `venta_pago.motivo_anulacion` del pago anulado.

### RF-12: Reversión de movimientos vinculados

Al anular un `venta_pago`, revertir en cascada:
- Si `afecta_caja=true` y `movimiento_caja_id` NOT NULL: crear `MovimientoCaja` de `tipo=egreso` con `referencia_tipo='anulacion_venta'` (ya existe patrón) y `caja->disminuirSaldo()`.
- Si tiene movimiento de vuelto (MovimientoCaja con `REF_VUELTO_VENTA`): crear contra-ingreso.
- Si `movimiento_cuenta_empresa_id` NOT NULL: `CuentaEmpresaService::revertirMovimiento()`.
- Si `es_cuenta_corriente=true`: `CuentaCorrienteService::anularMovimientosVentaPago()` (método nuevo, ver RF-15) — append-only con `tipo='anulacion_venta'`.

Al crear el nuevo `venta_pago`:
- Si `afecta_caja=true`: crear `MovimientoCaja` de `tipo=ingreso` con `referencia_tipo='venta'`, linkear a `venta_pago.movimiento_caja_id`.
- Si nueva FP tiene cuenta empresa: `CuentaEmpresaService::registrarMovimientoAutomatico()`.
- Si nueva FP es CC: crear `MovimientoCuentaCorriente` con `tipo='venta'`, ajustar saldo cliente.

### RF-13: Recálculo de totales de la venta
Después de todos los cambios en un cambio de pago, recalcular:
- `ventas.ajuste_forma_pago` = SUM(`monto_ajuste`) de pagos activos
- `ventas.total_final` = SUM(`monto_final`) de pagos activos
- `ventas.es_cuenta_corriente` = true si algún pago activo es CC
- `ventas.saldo_pendiente_cache` = SUM(`saldo_pendiente`) de pagos activos CC

### RF-14: Prohibido cambiar un VentaPago CC con cobros aplicados
Ya cubierto en RF-08, se detalla aquí por su importancia. La relación `VentaPago::cobrosAplicados()` (HasMany a `CobroVenta`) se chequea antes de cualquier operación sobre ese pago específico. Si hay registros, se rechaza el cambio con mensaje claro: "Este pago tiene $X aplicados en N cobros. Anule los cobros desde Cobranzas del cliente antes de modificar este pago."

### RF-15: Método nuevo en `CuentaCorrienteService`
Crear `anularMovimientosVentaPago(VentaPago $ventaPago, int $usuarioId, string $motivo)` que:
- Busca `MovimientoCuentaCorriente` donde `venta_pago_id = $ventaPago->id` y `estado='activo'`.
- Por cada uno, crea contraasiento con `tipo='anulacion_venta'`, `debe=haber_original`, `haber=debe_original`, `anulado_por_movimiento_id` bidireccional.
- Ajusta saldo cliente (`Cliente::ajustarSaldoEnSucursal()`).
- Similar al método existente `anularMovimientosVenta()` pero granular por venta_pago.

### RF-16: Permisos
Agregar a `PermisosFuncionalesSeeder.php` en grupo "Ventas":
- `func.cambiar_forma_pago_venta` — "Cambiar forma de pago en ventas registradas"
- `func.cambiar_forma_pago_turno_cerrado` — "Cambiar forma de pago sobre turnos cerrados"
- `func.modificar_pagos_sin_nc` — "Modificar pagos fiscales sin emitir NC (solo cuando la config lo permite)"
- `func.ver_ajustes_post_cierre` — "Ver reporte de ajustes post-cierre"

### RF-17: Trazabilidad completa

Cada cambio en los pagos de una venta debe quedar registrado de forma exhaustiva y auditable:

**En `venta_pagos` (ampliación de columnas)**:
- `venta_pago_reemplazado_id` — apunta al pago anulado cuando este pago lo reemplaza
- `operacion_origen` — enum: `'venta_original'`, `'cambio_pago'`, `'pago_agregado'`, `'anulacion_sin_reemplazo'`
- `creado_por_usuario_id` — usuario que creó este registro (distinto del de la venta si fue agregado/cambiado posteriormente)
- `nota_credito_generada_id` — si la anulación disparó una NC, apunta al `ComprobanteFiscal` de NC
- `comprobante_fiscal_nuevo_id` — si fue reemplazado y se emitió nueva FC al reemplazo, linkea ahí
- `datos_snapshot_json` — JSON con los datos del pago al momento de anularse (forma_pago, monto, ajuste, caja, usuario) para análisis forense

**Tabla nueva `venta_pago_ajustes` (audit log de operación completa)**:

Registra cada operación atómica (cambio/agregado/eliminación) como un único evento, agrupando los venta_pagos involucrados, los comprobantes fiscales generados y el estado fiscal/de turno. Esto simplifica enormemente el reporte post-cierre y la auditoría.

Estructura en sección "Modelo de Datos".

### RF-18: Claridad UI — copy explícito y preview detallado

La UI del feature debe ser **quirúrgicamente clara** sobre qué está pasando y qué efectos tendrá cada acción. Objetivo: un usuario no técnico debe poder leer el modal y entender exactamente qué operaciones van a ejecutarse antes de confirmar.

Requisitos específicos:

1. **Modal "Modificar pago" en 4 secciones visuales separadas**:
   - **A. Pago actual** (bloque gris, read-only): muestra forma de pago, monto base, ajuste, monto final, facturación, turno, usuario que lo creó. Sin posibilidad de editar.
   - **B. Nuevo pago** (bloque blanco con inputs): forma de pago nueva, monto nuevo, checkbox de ajuste, cuotas si aplica.
   - **C. Preview de cambios tipo diff**: muestra cada campo modificado con formato `CAMPO: valor_viejo → valor_nuevo`. Si un campo no cambia, se muestra en gris con "(sin cambio)".
   - **D. Acciones que se ejecutarán**: lista ordenada con checkboxes visuales de lo que va a pasar:
     - ☑ Anular pago actual (id: #123)
     - ☑ Crear nuevo pago: $300 en Transferencia Galicia
     - ☑ Generar movimiento de cuenta empresa (Banco Galicia): +$300
     - ☑ Emitir Nota de Crédito B-0001-00012345 por $300 (asociada a Factura B-0001-00012344)
     - ⚠ Esta operación afecta un turno cerrado el 2026-04-10 → se registra como ajuste post-cierre
     - Cada ítem con tooltip explicativo al hover.

2. **Motivo obligatorio** con:
   - Label explícito: "Motivo del cambio (se guarda en el historial y el reporte de ajustes)"
   - Placeholder con ejemplo: "Ej: Cajero tildó débito por error, era transferencia"
   - Mínimo 10 caracteres.

3. **Botones de acción con texto descriptivo**:
   - "Cancelar" (gris)
   - "Confirmar cambio y procesar operaciones" (primario, color según impacto: azul si neutro, ámbar si turno cerrado, rojo si involucra NC obligatoria)

4. **Tooltips en botones deshabilitados** (cada bloqueo tiene mensaje específico):
   - "No se puede modificar: este pago tiene $200 aplicados en 2 cobros de cuenta corriente. Anule los cobros desde Cobranzas del cliente primero."
   - "No se puede modificar: la venta está cancelada."
   - "No se puede modificar: este pago ya fue anulado el {fecha} por {usuario}."
   - "No tenés permiso para modificar pagos de turnos cerrados. Solicitá `func.cambiar_forma_pago_turno_cerrado` a tu administrador."

5. **Banner de turno cerrado** (ámbar llamativo) cuando aplica:
   > ⚠ **Atención**: Este pago pertenece al turno cerrado el **{fecha}** por **{usuario}**. Los cambios se registrarán como **ajuste post-cierre**. El total histórico del cierre NO se modificará, pero los movimientos contables (caja, cuenta empresa, CC) se ajustarán en el turno actual.

6. **Preview fiscal explícito** cuando aplica:
   - Verde: "✓ No se modifica la fiscalidad de la venta (mismo total, misma condición fiscal)"
   - Azul: "ℹ Se emitirá Nota de Crédito automática por $500 (cambio de condición fiscal de Fiscal → No Fiscal, sucursal tiene facturación automática)"
   - Amarillo: "⚠ Elegí qué hacer con la fiscalidad:" + checkboxes editables
   - Rojo: "🚨 El total de la venta cambia de $500 a $700. Se emitirá NC obligatoria por $500 y nueva FC por $700"

7. **Historial de cambios en detalle de venta**:
   - Nueva sección colapsable en el modal de detalle: "Historial de cambios en pagos".
   - Timeline vertical con cada evento (`venta_pago_ajustes`):
     - Icono del tipo de operación
     - Fecha/hora legible ("Hace 2 horas" + tooltip con fecha exacta)
     - Usuario
     - Descripción narrativa auto-generada: "Cambió forma de pago de **Débito Visa $300** a **Transferencia Galicia $300**"
     - Motivo (en cita)
     - Links clickeables a NC/FC generadas (abren detalle fiscal)
     - Badge si afectó turno cerrado.

8. **Confirmación final** antes de ejecutar:
   - Modal de confirmación con título "Confirmar operación" y resumen textual:
     > Se va a: anular el pago de **Débito Visa $300**, crear un pago nuevo de **Transferencia Galicia $300**, emitir una **Nota de Crédito por $300** y registrar esta operación en el historial de la venta. ¿Continuás?
   - Botón: "Sí, ejecutar ahora" / "Volver"

---

## Modelo de Datos

### Tablas nuevas

#### `venta_pago_ajustes` (tenant, con prefijo `{NNNNNN}_`)

Audit log de cada operación de ajuste sobre pagos de una venta. **Un registro por operación atómica** (un cambio de pago = 1 registro, aunque internamente genere 2 venta_pagos + N movimientos).

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint unsigned PK auto | — | |
| `venta_id` | bigint unsigned | — | FK a `{prefix}ventas.id` |
| `sucursal_id` | bigint unsigned | — | FK a sucursales (para filtros rápidos) |
| `tipo_operacion` | enum | — | `'cambio_pago'`, `'agregar_pago'`, `'eliminar_pago'` |
| `venta_pago_anulado_id` | bigint unsigned NULL | NULL | FK a `{prefix}venta_pagos.id` del pago anulado (NULL si es "agregar") |
| `venta_pago_nuevo_id` | bigint unsigned NULL | NULL | FK a `{prefix}venta_pagos.id` del pago nuevo (NULL si es "eliminar") |
| `forma_pago_anterior_id` | bigint unsigned NULL | NULL | FK a `formas_pago.id` (snapshot por si se borra) |
| `forma_pago_nueva_id` | bigint unsigned NULL | NULL | FK a `formas_pago.id` |
| `monto_anterior` | decimal(12,2) NULL | NULL | `monto_final` del pago anulado |
| `monto_nuevo` | decimal(12,2) NULL | NULL | `monto_final` del pago nuevo |
| `delta_total` | decimal(12,2) | 0.00 | Diferencia en `total_final` de la venta (+/-) |
| `delta_fiscal` | boolean | false | true si cambió la condición fiscal |
| `turno_original_id` | bigint unsigned NULL | NULL | `cierre_turno_id` del pago anulado (NULL si era turno abierto) |
| `es_post_cierre` | boolean | false | true si `turno_original_id` no es NULL → aparece en reporte |
| `nc_emitida_id` | bigint unsigned NULL | NULL | FK a `{prefix}comprobantes_fiscales.id` de NC generada |
| `fc_nueva_id` | bigint unsigned NULL | NULL | FK a `{prefix}comprobantes_fiscales.id` de FC nueva emitida |
| `nc_emitida_flag` | boolean | false | true si la matriz exigía NC y se emitió |
| `fc_nueva_flag` | boolean | false | true si se emitió FC nueva |
| `salteo_nc_autorizado` | boolean | false | true si usuario saltó la NC preguntada (requiere `func.modificar_pagos_sin_nc`) |
| `config_auto_al_operar` | boolean | — | snapshot de `sucursales.facturacion_fiscal_automatica` al momento |
| `motivo` | text | — | Motivo obligatorio ingresado por el usuario |
| `descripcion_auto` | text | — | Descripción generada automáticamente: "Cambió Débito Visa $300 por Transferencia Galicia $300" |
| `usuario_id` | bigint unsigned | — | FK a usuarios (config) — quién hizo el cambio |
| `ip_origen` | varchar(45) NULL | NULL | IP del request (auditoría) |
| `user_agent` | varchar(500) NULL | NULL | Navegador |
| `created_at` | timestamp | NOW | |
| `updated_at` | timestamp | — | |

Índices:
- `idx_vpa_venta` (`venta_id`)
- `idx_vpa_sucursal_fecha` (`sucursal_id`, `created_at`)
- `idx_vpa_post_cierre` (`es_post_cierre`, `created_at`) — para el reporte
- `idx_vpa_usuario` (`usuario_id`)

### Tablas modificadas

#### `venta_pagos` — Columnas nuevas

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `venta_pago_reemplazado_id` | bigint unsigned NULL | NULL | FK a `{prefix}venta_pagos.id` — el pago que este reemplaza |
| `operacion_origen` | enum | `'venta_original'` | `'venta_original'`, `'cambio_pago'`, `'pago_agregado'`, `'anulacion_sin_reemplazo'` |
| `creado_por_usuario_id` | bigint unsigned NULL | NULL | FK a usuarios — quién creó este registro (si distinto del de la venta) |
| `nota_credito_generada_id` | bigint unsigned NULL | NULL | FK a `{prefix}comprobantes_fiscales.id` — NC disparada por la anulación de este pago |
| `comprobante_fiscal_nuevo_id` | bigint unsigned NULL | NULL | FK a `{prefix}comprobantes_fiscales.id` — FC nueva cuando este pago fue emitido como reemplazo |
| `datos_snapshot_json` | json NULL | NULL | Snapshot JSON del pago al momento de anularse (forense) |

Posición: `AFTER motivo_anulacion`.

Campos ya existentes y reutilizados:
- `estado` (enum 'activo','anulado','pendiente')
- `anulado_por_usuario_id`, `anulado_at`, `motivo_anulacion`
- `cierre_turno_id`

#### Permisos (tabla `permisos` compartida en `pymes`)
4 nuevos registros insertados via `PermisosFuncionalesSeeder`. No es migración de schema sino de datos seed.

---

## Pantallas UI

### Pantalla 1: Modal de cambio de pago (dentro de detalle de venta)

**Componente**: `App\Livewire\Ventas\Ventas` (existente, se extiende)
**Trait**: `SucursalAware` (ya tiene)

Funcionalidad agregada al modal `showDetalleModal`:

1. **Sección nueva "Cobros aplicados"** (RF-07):
   - Se muestra solo si hay pagos CC con `cobrosAplicados()->exists()`.
   - Tabla: Fecha / Recibo / Monto aplicado / Interés / Saldo pendiente.
   - Badge "Anulado" en gris si el cobro fue anulado.

2. **Nuevo botón por cada pago activo**: "Modificar" (ícono pencil).
   - Deshabilitado si el pago tiene cobros aplicados (tooltip explica).
   - Deshabilitado si la venta está cancelada.
   - Deshabilitado si el usuario no tiene `func.cambiar_forma_pago_venta`.

3. **Botón "Agregar pago"** al final de la tabla de pagos (si venta no está cancelada).

4. **Botón "Eliminar" (ícono trash)** por cada pago activo (mismos bloqueos).

5. **Sub-modal de edición** (`showCambiarPagoModal`):
   - Select de forma de pago (nuevo): igual que `NuevaVenta`, muestra `ajuste_porcentaje` al lado del nombre.
   - Input de monto (si está editando, pre-llenado; si agrega, vacío).
   - Checkbox "Aplicar ajuste" (igual patrón que `GestionarCobranzas.blade.php:719-735`).
   - Si la nueva FP tiene cuotas: selector de cuotas (como en `NuevaVenta`).
   - Si la FP es CC: selector de cliente (si no era CC antes).
   - Textarea "Motivo del cambio" (obligatorio).
   - **Bloque de preview fiscal**: muestra qué va a pasar (ej: "Se emitirá NC por $500 y nueva FC B por $500").
   - Si matriz dice "preguntar": checkboxes "Emitir NC", "Emitir FC nueva" editables.
   - Si venta pertenece a turno cerrado: banner ámbar con warning.
   - Botones: "Cancelar" / "Confirmar cambio".

6. **Post-confirmación**:
   - Toast verde con resumen.
   - Refrescar modal de detalle.
   - Refrescar listado de ventas.

### Pantalla 2: Reporte de ajustes post-cierre

**Componente**: `App\Livewire\Cajas\AjustesPostCierre` (nuevo)
**Trait**: `SucursalAware`
**Ruta**: `/cajas/ajustes-post-cierre`

Funcionalidad:
- Header + filtros (rango fechas, sucursal si user tiene multi-sucursal, usuario, turno afectado).
- Cards móvil (`sm:hidden`) + tabla desktop (`hidden sm:block`) según design system.
- Columnas: Fecha / Usuario / Venta # / Turno original / FP vieja → FP nueva / ΔMonto / Motivo / Acciones (ver detalle venta).
- Paginación 15/página.
- Permiso: `func.ver_ajustes_post_cierre`.

### Mejoras en `NuevaVenta` y sistema
Ninguna. El feature es aditivo.

---

## Servicios

### `VentaService` — `app/Services/VentaService.php` (existente, se extiende)

Métodos nuevos:

```php
/**
 * Cambia la forma de pago de un venta_pago existente.
 * Anula el pago viejo y crea uno nuevo (append-only).
 * Aplica matriz fiscal automática según config.
 *
 * @param int $ventaPagoId ID del pago a reemplazar
 * @param array $datosNuevoPago ['forma_pago_id', 'monto_base', 'aplicar_ajuste', 'cuotas', 'cuota_id', ...]
 * @param string $motivo Motivo obligatorio del cambio
 * @param int $usuarioId Usuario que realiza la operación
 * @param array $opcionesFiscales ['emitir_nc' => bool|null, 'emitir_fc_nueva' => bool|null]
 * @return array ['venta_pago_anulado' => VentaPago, 'venta_pago_nuevo' => VentaPago, 'nc_emitidas' => [], 'fc_nuevas' => []]
 * @throws Exception si no cumple validaciones
 */
public function cambiarFormaPago(
    int $ventaPagoId,
    array $datosNuevoPago,
    string $motivo,
    int $usuarioId,
    array $opcionesFiscales = []
): array;

/**
 * Agrega un nuevo venta_pago a una venta existente.
 */
public function agregarPagoAVenta(
    int $ventaId,
    array $datosNuevoPago,
    string $motivo,
    int $usuarioId,
    array $opcionesFiscales = []
): array;

/**
 * Elimina (anula) un venta_pago sin reemplazo.
 */
public function eliminarPagoDeVenta(
    int $ventaPagoId,
    string $motivo,
    int $usuarioId,
    array $opcionesFiscales = []
): array;

/**
 * Valida si un venta_pago puede ser modificado.
 * @return array ['puede' => bool, 'razon' => string|null]
 */
public function puedeModificarVentaPago(int $ventaPagoId, int $usuarioId): array;

/**
 * Calcula la matriz fiscal para un cambio hipotético.
 * Permite a la UI previsualizar qué va a pasar antes de confirmar.
 * @return array ['delta_total' => bool, 'delta_fiscal' => bool, 'auto' => bool,
 *                'emitir_nc' => bool|'preguntar', 'emitir_fc_nueva' => bool|'preguntar',
 *                'preview_texto' => string]
 */
public function calcularMatrizFiscalCambio(VentaPago $pagoViejo, array $datosNuevoPago): array;

/**
 * Recalcula ajuste_forma_pago, total_final, es_cuenta_corriente, saldo_pendiente_cache
 * de una venta a partir de sus venta_pagos activos.
 */
private function recalcularTotalesVenta(Venta $venta): void;
```

Método privado auxiliar:
```php
/**
 * Revierte todos los movimientos vinculados a un venta_pago (caja, cuenta empresa, CC, vuelto).
 */
private function revertirMovimientosVentaPago(VentaPago $vp, int $usuarioId, string $motivo): void;
```

### `CuentaCorrienteService` — `app/Services/CuentaCorrienteService.php` (existente)

Método nuevo (RF-15):
```php
/**
 * Anula los movimientos de CC vinculados a un venta_pago específico (no a toda la venta).
 * Genera contraasientos append-only y ajusta saldo del cliente.
 */
public function anularMovimientosVentaPago(
    VentaPago $ventaPago,
    int $usuarioId,
    string $motivo
): void;
```

### Reuso sin cambios
- `ComprobanteFiscalService::crearNotaCredito()` — tal como está.
- `CuentaEmpresaService::revertirMovimiento()` y `registrarMovimientoAutomatico()` — tal como están.
- `VentaPago::calcularMontoConAjuste()` — helper estático.
- `Caja::aumentarSaldo()` / `disminuirSaldo()` — tal como están.

---

## Migraciones Necesarias

1. **`{timestamp}_add_trazabilidad_a_venta_pagos`** — agrega 6 columnas de trazabilidad a `{prefix}venta_pagos` (tenant).
   - Iterar TODOS los comercios con prefijo.
   - FKs:
     - `venta_pago_reemplazado_id` → `{prefix}venta_pagos(id) ON DELETE SET NULL`
     - `creado_por_usuario_id` → `config.users(id) ON DELETE SET NULL`
     - `nota_credito_generada_id` → `{prefix}comprobantes_fiscales(id) ON DELETE SET NULL`
     - `comprobante_fiscal_nuevo_id` → `{prefix}comprobantes_fiscales(id) ON DELETE SET NULL`
   - **Regenerar `database/sql/tenant_tables.sql`**.

2. **`{timestamp}_create_venta_pago_ajustes`** — tabla nueva tenant.
   - Iterar todos los comercios con prefijo `{NNNNNN}_venta_pago_ajustes`.
   - FKs a venta_pagos, ventas, comprobantes_fiscales, formas_pago (todas tenant), users (config).
   - Índices listados en sección Modelo de Datos.
   - **Regenerar `database/sql/tenant_tables.sql`**.

3. **`{timestamp}_add_permisos_cambio_forma_pago`** — migración compartida en `pymes`.
   - Inserta 4 permisos funcionales en tabla `permisos`.
   - Actualiza `PermisosFuncionalesSeeder` para que `ProvisionComercioCommand::seedRolesYPermisos()` los asigne al rol correspondiente (por defecto, solo admin/supervisor).

4. **`{timestamp}_add_menu_ajustes_post_cierre`** — entrada en `menu_items` (compartida).
   - Agrega item bajo el menú "Cajas" con slug `ajustes-post-cierre`, ruta `/cajas/ajustes-post-cierre`.

---

## Traducciones

Claves nuevas en `lang/{es,en,pt}.json` (orden alfabético):

| Clave (es) | en | pt |
|------------|----|----|
| Agregar pago | Add payment | Adicionar pagamento |
| Ajustes post-cierre | Post-closing adjustments | Ajustes pós-fechamento |
| Anule los cobros primero desde Cobranzas del cliente | Void collections first from client Collections | Anule cobranças primeiro de Cobranças do cliente |
| Cambiar forma de pago | Change payment method | Alterar forma de pagamento |
| Confirmar cambio | Confirm change | Confirmar alteração |
| El cambio generará una Nota de Crédito automática | Change will generate automatic Credit Note | A alteração gerará uma Nota de Crédito automática |
| El cambio generará una Nota de Crédito y una nueva Factura | Change will generate a Credit Note and a new Invoice | A alteração gerará uma Nota de Crédito e uma nova Fatura |
| Emitir Factura nueva | Issue new Invoice | Emitir nova Fatura |
| Emitir Nota de Crédito | Issue Credit Note | Emitir Nota de Crédito |
| Este pago tiene cobranzas aplicadas | This payment has collections applied | Este pagamento tem cobranças aplicadas |
| Este pago ya fue anulado | This payment has already been voided | Este pagamento já foi anulado |
| Esta venta pertenece a un turno cerrado | This sale belongs to a closed shift | Esta venda pertence a um turno fechado |
| Eliminar pago | Remove payment | Remover pagamento |
| Forma de pago vieja | Old payment method | Forma de pagamento anterior |
| Forma de pago nueva | New payment method | Nova forma de pagamento |
| Motivo del cambio | Change reason | Motivo da alteração |
| No se modificará la fiscalidad de la venta | Sale tax status will not change | O status fiscal da venda não mudará |
| Reporte de ajustes post-cierre | Post-closing adjustments report | Relatório de ajustes pós-fechamento |
| Se registrará como movimiento post-cierre | Will be recorded as post-closing movement | Será registrado como movimento pós-fechamento |
| Turno original | Original shift | Turno original |
| Ver cobros aplicados | View applied collections | Ver cobranças aplicadas |

---

## Criterios de Aceptación

### Cambio básico de forma de pago
- [ ] Con una venta de $500 ($200 efectivo + $300 débito), puedo cambiar los $300 débito por $300 transferencia, y el `total_final` queda en $500.
- [ ] El `venta_pago` original de débito queda `estado='anulado'` con motivo, usuario y timestamp.
- [ ] Se crea nuevo `venta_pago` de transferencia con `estado='activo'`.
- [ ] Saldo de caja: el ingreso de débito no afectaba caja (`afecta_caja=false`), por lo que no hay contraasiento de caja; la cuenta empresa vinculada al débito tiene contraasiento y la vinculada a transferencia tiene nuevo movimiento.

### Matriz fiscal — ΔTotal=No + ΔFiscal=No
- [ ] Cambiar débito fiscal por crédito fiscal con mismo monto y mismo ajuste → **no se emite NC ni FC**.

### Matriz fiscal — ΔTotal=No + ΔFiscal=Sí + Auto=Sí
- [ ] Cambiar transferencia (fiscal) $500 por efectivo (no fiscal) $500 con sucursal `facturacion_fiscal_automatica=true` → **se emite NC automáticamente, no se emite FC nueva**.
- [ ] La NC está vinculada a la FC original vía `comprobante_asociado_id`.

### Matriz fiscal — ΔTotal=Sí + Auto=Sí
- [ ] Cambiar débito fiscal $300 por débito fiscal $500 con `facturacion_fiscal_automatica=true` → **se emite NC obligatoria por $300 + FC nueva por $500**.

### Matriz fiscal — ΔTotal=No + ΔFiscal=Sí + Auto=No (preguntar)
- [ ] La UI muestra checkboxes "Emitir NC" y "Emitir FC nueva" editables.
- [ ] Si usuario desmarca NC sin tener `func.modificar_pagos_sin_nc`, el sistema rechaza con error.

### Bloqueos
- [ ] No se puede modificar un `venta_pago` con cobros imputados (RF-14).
- [ ] No se puede modificar un `venta_pago` ya anulado.
- [ ] No se puede modificar una venta cancelada.
- [ ] No se puede modificar una venta con puntos canjeados.

### Turnos cerrados
- [ ] Si el `venta_pago` pertenece a turno cerrado, el cambio se bloquea sin `func.cambiar_forma_pago_turno_cerrado`.
- [ ] Con el permiso: el cambio procede, los movimientos nuevos/contraasientos quedan con `cierre_turno_id=NULL`.
- [ ] El cierre original no se toca (`total_ingresos`, `total_egresos` no cambian).
- [ ] UI muestra banner ámbar con advertencia.
- [ ] El ajuste aparece en el reporte "Ajustes post-cierre".

### Visualización cobros CC
- [ ] En modal de detalle de venta, los pagos CC muestran sección expandible con lista de cobros imputados.
- [ ] Si no hay cobros, se muestra "Sin cobros aplicados".

### Reporte post-cierre
- [ ] Componente accesible desde menú con permiso `func.ver_ajustes_post_cierre`.
- [ ] Lista paginada con filtros operativos.
- [ ] Datos: fecha, usuario, venta, turno afectado, FP vieja→nueva, delta, motivo.

### Operaciones auxiliares
- [ ] Agregar pago nuevo a venta existente incrementa `total_final` y dispara matriz fiscal con ΔTotal=Sí.
- [ ] Eliminar pago sin reemplazo decrementa `total_final` y dispara matriz fiscal con ΔTotal=Sí.
- [ ] Si la nueva FP tiene cuotas, el cálculo de recargo por cuotas se aplica igual que en `NuevaVenta`.

### Integridad
- [ ] Toda la operación está envuelta en `DB::connection('pymes_tenant')->transaction()`.
- [ ] Si falla cualquier paso (ej: NC de AFIP rechazada), rollback completo.
- [ ] Ledger de CC y cuenta empresa queda consistente (suma de debe-haber y suma de movimientos cuadran).

### Trazabilidad
- [ ] Cada operación de cambio/agregado/eliminación crea exactamente un registro en `venta_pago_ajustes`.
- [ ] El `datos_snapshot_json` del pago anulado contiene los datos completos previos.
- [ ] `venta_pago_reemplazado_id` del pago nuevo apunta al pago anulado (en operaciones de cambio).
- [ ] `operacion_origen` de cada `venta_pago` es consistente con su historia.
- [ ] Si se emitió NC, `venta_pagos.nota_credito_generada_id` y `venta_pago_ajustes.nc_emitida_id` apuntan al mismo `ComprobanteFiscal`.
- [ ] `descripcion_auto` del ajuste es legible y describe correctamente la operación.
- [ ] El historial en el modal de detalle muestra todos los ajustes en orden cronológico con links funcionales a NC/FC.

### UI clara
- [ ] El modal "Modificar pago" tiene 4 secciones visualmente separadas (A/B/C/D).
- [ ] El preview tipo diff muestra cada campo con formato `valor_viejo → valor_nuevo` o "(sin cambio)".
- [ ] La lista de "Acciones que se ejecutarán" es exhaustiva y coherente con lo que realmente pasa en backend.
- [ ] Tooltips de botones deshabilitados indican causa específica (no "Deshabilitado" genérico).
- [ ] Banner de turno cerrado se muestra en ámbar con fecha y usuario del cierre.
- [ ] Preview fiscal cambia de color/mensaje según el caso de la matriz.
- [ ] Modal de confirmación final describe la operación en lenguaje natural completo.
- [ ] Timeline de historial en detalle de venta muestra eventos con iconos, fecha relativa y descripción narrativa.

### Tests
- [ ] Test unitario de `VentaService::cambiarFormaPago()` con cada caso de la matriz (6 casos).
- [ ] Test de bloqueos (cobros aplicados, venta cancelada, pago anulado, sin permisos).
- [ ] Test de turno cerrado (movimientos con `cierre_turno_id=NULL`, aparición en reporte).
- [ ] Test de recálculo de totales (venta con múltiples pagos, algunos anulados).
- [ ] Test de que `VentaPagoAjuste` se crea con todos los campos esperados.
- [ ] Test de `datos_snapshot_json` correctamente populado.
- [ ] Test de `agregarPagoAVenta` y `eliminarPagoDeVenta` generando ajustes correctos.

---

## Plan de Implementación

### Fase 1: Migraciones, permisos y modelos [COMPLETA]
1. Crear migración tenant `add_trazabilidad_a_venta_pagos` con iteración por comercios (skill `/migration`).
2. Crear migración tenant `create_venta_pago_ajustes` para la tabla de audit log.
3. Regenerar `database/sql/tenant_tables.sql`.
4. Crear migración compartida `add_permisos_cambio_forma_pago` con los 4 permisos.
5. Actualizar `PermisosFuncionalesSeeder.php` y `ProvisionComercioCommand::seedRolesYPermisos()`.
6. Crear migración de menú `add_menu_ajustes_post_cierre`.
7. Actualizar modelo `VentaPago.php`:
   - Agregar a `$fillable`: `venta_pago_reemplazado_id`, `operacion_origen`, `creado_por_usuario_id`, `nota_credito_generada_id`, `comprobante_fiscal_nuevo_id`, `datos_snapshot_json`.
   - Agregar cast: `datos_snapshot_json => 'array'`.
   - Agregar relaciones: `pagoReemplazado()` (BelongsTo self), `pagoQueMeReemplazo()` (HasOne self), `notaCreditoGenerada()` (BelongsTo ComprobanteFiscal), `comprobanteFiscalNuevo()` (BelongsTo ComprobanteFiscal), `creadoPor()` (BelongsTo User).
8. Crear modelo nuevo `VentaPagoAjuste.php` (skill `/modelo`):
   - Conexión `pymes_tenant`, fillables completos, casts.
   - Relaciones: `venta()`, `ventaPagoAnulado()`, `ventaPagoNuevo()`, `formaPagoAnterior()`, `formaPagoNueva()`, `ncEmitida()`, `fcNueva()`, `usuario()`.
   - Scopes: `postCierre()`, `porSucursal($id)`, `porRangoFechas($desde, $hasta)`, `porUsuario($id)`, `porTipoOperacion($tipo)`.

### Fase 2: Backend — Servicios [COMPLETA]
1. Extender `VentaService`:
   - `puedeModificarVentaPago()`
   - `calcularMatrizFiscalCambio()`
   - `revertirMovimientosVentaPago()` (privado)
   - `recalcularTotalesVenta()` (privado)
   - `registrarAjuste()` (privado, crea el `VentaPagoAjuste` con descripción auto-generada)
   - `cambiarFormaPago()`
   - `agregarPagoAVenta()`
   - `eliminarPagoDeVenta()`
2. Extender `CuentaCorrienteService` con `anularMovimientosVentaPago()`.
3. Crear helper `VentaPagoSnapshot` (value object o array builder) que serializa un `VentaPago` + contexto a JSON antes de anularlo.
4. Tests unitarios de cada método (skill `/test`).

### Fase 3: UI — Modal de detalle extendido [COMPLETA]
1. Extender componente `Ventas.php`:
   - Propiedades: `showCambiarPagoModal`, `pagoEditandoId`, `datosNuevoPago`, `matrizFiscalPreview`, `opcionesFiscales`.
   - Métodos: `abrirCambiarPago()`, `actualizarMatrizFiscal()` (en tiempo real al cambiar FP/monto), `confirmarCambioPago()`, `agregarPago()`, `eliminarPago()`.
   - Cargar cobros CC en `abrirDetalle()` para mostrar en el modal.
2. Extender vista `ventas.blade.php`:
   - Sección "Cobros aplicados" por cada pago CC.
   - Botones "Modificar", "Eliminar", "Agregar pago".
   - Sub-modal `<x-bcn-modal wire:model="showCambiarPagoModal">`.
   - Preview fiscal reactivo.
   - Banner ámbar para turno cerrado.
3. Seguir design system exacto (modal con color del botón que lo abre, responsive, dark mode).

### Fase 4: UI — Reporte ajustes post-cierre [COMPLETA]
1. Crear componente `App\Livewire\Cajas\AjustesPostCierre` (skill `/nuevo-componente`).
2. Crear vista Blade (skill `/vista`).
3. Agregar ruta en `routes/web.php`.
4. Agregar entrada al menú.

### Fase 5: Traducciones [COMPLETA]
1. Agregar las 21 claves a `lang/{es,en,pt}.json` (skill `/traducir`).

### Fase 6: Testing [EN PROGRESO]
1. Tests unitarios completos del service (skill `/test`).
2. Tests feature del componente (cambio, agregar, eliminar, bloqueos).
3. Test integración con `VentaService::cancelarVentaCompleta()` — que una venta con pagos cambiados pueda cancelarse correctamente.
4. Test de turno cerrado + ajuste post-cierre.

### Fase 7: Documentación y PR [PENDIENTE]
1. Invocar `@docs-sync` para actualizar `docs/manual-usuario.md` y `docs/ai-knowledge-base.md`.
2. `php vendor/bin/pint` + `php artisan test`.
3. Push branch + `gh pr create`.
4. `/sdd-verify` para generar Spec Compliance Matrix.

---

## Notas y Decisiones

- **2026-04-15**: Decidido usar append-only en `venta_pagos` (anular + crear nuevo) en lugar de UPDATE in-place. Razón: coherencia con el resto del sistema (CC, caja, cuenta empresa). Facilita auditoría y rollback.
- **2026-04-15**: Total base de venta (artículos + promos) es inmutable. Solo `total_final` puede variar por recálculo de `ajuste_forma_pago` cuando la nueva FP tiene recargo/descuento distinto. Usuario puede desmarcar aplicación de ajuste (patrón GestionarCobranzas).
- **2026-04-15**: Matriz fiscal automática decidida en 6 casos según ΔTotal + ΔFiscal + config auto de sucursal. Cuando no aplica auto, se pregunta al usuario. Criterio fiscal: una factura electrónica no discrimina FP al cliente, entonces cambio interno de FP fiscal→fiscal con mismo total no necesita NC.
- **2026-04-15**: Turnos cerrados permitidos con permiso dedicado. Cierre histórico no se modifica, contraasientos van al turno actual con `cierre_turno_id=NULL` y aparecen en reporte de ajustes post-cierre.
- **2026-04-15**: Bloqueo estricto si hay cobros imputados al VentaPago CC. El usuario debe anular los cobros primero desde Cobranzas del cliente (flujo ya existente). Alternativa de reversión en cascada descartada por complejidad.
- **2026-04-15**: Permiso `func.modificar_pagos_sin_nc` permite saltar la NC solo cuando la matriz dice "preguntar" (config auto = false). Si la matriz dice "obligatoria" (ΔTotal=Sí), la NC se emite sí o sí.
- **2026-04-15**: Cobros CC en detalle de venta se entrega dentro del mismo PR porque es necesario para que el usuario entienda el bloqueo RF-14 visualmente.
- **2026-04-15**: Trazabilidad extendida aprobada. Se agregan 6 columnas a `venta_pagos` y una tabla nueva `venta_pago_ajustes` como audit log de operación completa. Razón: el usuario pidió que todo lo que dé más trazabilidad e información se guarde. La tabla de ajustes simplifica enormemente el reporte post-cierre (consulta directa a una tabla) y facilita auditoría fiscal y operativa. El `datos_snapshot_json` preserva el estado del pago al momento de anularse, resistente a cambios posteriores en catálogos (formas de pago, cuentas empresa).
- **2026-04-15**: UI explícita priorizada. Se agregó RF-18 detallando los 4 bloques del modal de cambio, preview diff, banner de turno cerrado, tooltips específicos por bloqueo, preview fiscal por colores según matriz, historial tipo timeline, confirmación final narrativa. Razón: un cajero no técnico debe poder entender exactamente qué acciones se van a ejecutar antes de confirmar.

---

## Extensión 2026-04-16: FC nueva real + estado_facturacion

### Motivación del pivot

El enfoque original dejaba la emisión de FC nueva como un simple `fc_nueva_flag=true` sin disparar la facturación real, pensando que era complejo mapear un delta positivo sobre los artículos de la venta. Sin embargo, el proyecto factura sobre **montos de formas de pago** (no sobre ítems), como se ve en `ComprobanteFiscalService::crearComprobanteFiscal` con `$opciones['pagos_facturar']`. Por lo tanto, emitir una FC nueva sobre los `venta_pagos` facturables es tan viable como emitirla en una venta normal con pago mixto.

Además, el enfoque "delta < 0 → NC, delta > 0 → FC nueva" era difícil de leer y auditar. Un humano entiende mejor: "cancelé todo lo que facturé antes y emití lo nuevo desde cero".

### Reglas del nuevo flujo fiscal (reemplaza RF-06)

**Regla binaria** (suplanta la matriz 6-casos original, que era verdadera pero compleja):

| Monto facturado viejo vs nuevo | NC | FC nueva |
|-------------------------------|----|----------|
| Iguales (mismo monto, aunque cambie la FP) | ❌ No | ❌ No |
| Distintos (aunque sea por $1) | ✅ Sí, por `monto_facturado` del pago viejo | ✅ Sí, por suma de `monto_facturado` de pagos nuevos con `facturar=true` |

Definiciones:
- **Monto facturado viejo**: `VentaPago::monto_facturado` del pago anulado (NULL → 0 si el pago no era fiscal).
- **Monto facturado nuevo**: suma de `monto_final` de los pagos nuevos cuyo flag `facturar=true` y cuya FP tiene `factura_fiscal=true`.

Caso especial: si la sucursal tiene `facturacion_fiscal_automatica=false`, los dos comprobantes pasan por confirmación del usuario (igual que un alta normal). Esto se propaga vía `opciones['emitir_nc']` y `opciones['emitir_fc_nueva']`.

### Alcance de la NC (Opción A)

Cuando la FC original fue compartida entre varios pagos (ej: FC de $800 con débito $500 + transferencia $300), y se cambia **solo el débito** por otra FP:

- La NC se emite por **$500** (el `monto_facturado` del pago viejo que se anula), NO por los $800 completos.
- La FC original sigue viva por los $300 de transferencia que no cambian.
- La FC nueva se emite por `monto_facturado_nuevo`, que puede ser 0, 500, u otro valor según los pagos nuevos facturables.

Esto mantiene la integridad fiscal sin re-facturar pagos que no se tocaron.

### División de la transacción en 2 fases (reemplaza enfoque atómico anterior)

**Fase A — Atómica (DB::transaction en pymes_tenant)**:
1. Validar bloqueos (cobros CC, pago anulado, etc.).
2. Anular `venta_pago` viejo + snapshot JSON.
3. Revertir movimientos contables (caja, cuenta empresa, CC).
4. Crear `venta_pagos` nuevos + movimientos contables nuevos.
5. Si corresponde NC: emitir `ComprobanteFiscalService::crearNotaCredito` sobre el pago viejo. Si falla ARCA → **rollback total** y abortar la operación completa.
6. Commit.

**Fase B — Post-commit (fuera de la transacción)**:
7. Si corresponde FC nueva: emitir `ComprobanteFiscalService::crearComprobanteFiscal` con `$opciones['pagos_facturar']` construido desde los pagos nuevos con `facturar=true`.
   - **Éxito**: actualizar cada pago nuevo facturable → `comprobante_fiscal_id`, `monto_facturado`, `estado_facturacion='facturado'`. Update en `venta_pago_ajustes` con `fc_nueva_id`.
   - **Fallo**: marcar cada pago nuevo facturable con `estado_facturacion='pendiente_de_facturar'`. La NC permanece válida. Toast de advertencia: "Cambio registrado. La nueva factura quedó pendiente de emisión y se puede reintentar desde Cajas > Pagos pendientes de facturar."

**Por qué dos fases**: AFIP/ARCA es un side-effect externo no transaccional. Si se emite NC y después falla FC dentro de una sola transacción con rollback, la NC queda con CAE asignado pero en estado local 'rolled back' — inconsistencia fiscal grave. Separando, la NC se commitea solo después de emitirse OK, y la FC nueva (que es lo nuevo y puede reintentarse) queda como pendiente si falla.

### RF-19 (nuevo): Estado de facturación por pago

Nueva columna en `venta_pagos`:

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `estado_facturacion` | enum | `'no_facturado'` | `'no_facturado'`, `'facturado'`, `'pendiente_de_facturar'`, `'error_arca'` |

Semántica:
- `no_facturado`: pago no requiere FC (FP no fiscal o `facturar=false`).
- `facturado`: pago tiene `comprobante_fiscal_id` con CF autorizado.
- `pendiente_de_facturar`: pago debería tener FC pero la emisión falló; aparece en el reporte para reintento.
- `error_arca`: el reintento falló y el usuario decidió dejarlo marcado para revisión manual.

**Backfill en la migración**:
- Pagos con `comprobante_fiscal_id NOT NULL` → `'facturado'`.
- Resto → `'no_facturado'`.

**Scopes nuevos en `VentaPago`**:
- `scopePendientesDeFacturar()` → `where('estado_facturacion', 'pendiente_de_facturar')`.
- `scopeConErrorFacturacion()` → `where('estado_facturacion', 'error_arca')`.

### RF-20 (nuevo): Método `reintentarFacturacionPago` reutilizable

En `CambioFormaPagoService`:

```php
/**
 * Reintenta la emisión de FC sobre un venta_pago en estado 'pendiente_de_facturar'.
 * Diseñado para reuso en el futuro módulo general de "pendientes de facturar".
 *
 * @throws Exception si el pago no está en estado válido o falla la emisión.
 */
public function reintentarFacturacionPago(
    VentaPago $pago,
    int $usuarioId
): ComprobanteFiscal;
```

Comportamiento:
- Valida que `estado_facturacion === 'pendiente_de_facturar'`.
- Valida que `forma_pago->factura_fiscal === true`.
- Llama a `ComprobanteFiscalService::crearComprobanteFiscal` con `$opciones['pagos_facturar' => [['id' => $pago->id, 'monto_facturado' => $pago->monto_final]]]`.
- Si OK: actualiza `comprobante_fiscal_id`, `monto_facturado`, `estado_facturacion='facturado'`.
- Si falla: actualiza `estado_facturacion='error_arca'` (no queda en loop pendiente), log del error, re-throw para que la UI notifique.

### RF-21 (nuevo): Reporte independiente `PagosPendientesFacturacion`

Nuevo componente Livewire `App\Livewire\Cajas\PagosPendientesFacturacion`:

- **Ruta**: `/cajas/pagos-pendientes-facturacion`.
- **Permiso**: `func.ver_pagos_pendientes_facturacion` (nuevo).
- **Lista**: `VentaPago::with('venta', 'formaPago')->pendientesDeFacturar()->paginate(15)`.
- **Filtros**: sucursal, rango de fechas, forma de pago.
- **Columnas**: Fecha pago / Venta # / Cliente / FP / Monto a facturar / Estado / Acciones.
- **Acciones por fila**:
  - "Reintentar facturación" (botón azul, requiere permiso `func.reintentar_facturacion`).
  - "Marcar error" (botón rojo, mueve a `error_arca` sin reintentar).
  - "Ver venta" (abre detalle).
- **Base mínima deliberada**: este componente es la primera iteración del módulo futuro "búsqueda de pendientes de facturar" (que incluirá ventas y artículos). Por eso la lógica de reintento vive en el service (`reintentarFacturacionPago`) y no en el componente — para que el módulo futuro la consuma sin duplicar.

### RF-22 (nuevo): Badge + botón reintentar en detalle de venta

En el modal de detalle de venta (`ventas.blade.php`):
- Por cada pago con `estado_facturacion='pendiente_de_facturar'`: badge amarillo "Pendiente de facturar" al lado del monto.
- Por cada pago con `estado_facturacion='error_arca'`: badge rojo "Error de facturación" con tooltip del motivo.
- Botón "Reintentar facturación" (ícono retry) por fila pendiente, con permiso `func.reintentar_facturacion`.

### Permisos adicionales (suman a los 4 originales de RF-16)

- `func.reintentar_facturacion` — "Reintentar emisión de factura pendiente"
- `func.ver_pagos_pendientes_facturacion` — "Ver reporte de pagos pendientes de facturar"

### Entrada nueva de menú

- Bajo menú "Cajas": "Pagos pendientes de facturar" → `/cajas/pagos-pendientes-facturacion`.

### Archivos afectados por esta extensión

**Nuevos**:
- `database/migrations/2026_04_16_*_add_estado_facturacion_a_venta_pagos.php` (tenant, con backfill)
- `database/migrations/2026_04_16_*_add_permisos_y_menu_pagos_pendientes_facturacion.php` (shared)
- `database/migrations/2026_04_16_*_assign_pagos_pendientes_permissions.php` (shared)
- `app/Livewire/Cajas/PagosPendientesFacturacion.php`
- `resources/views/livewire/cajas/pagos-pendientes-facturacion.blade.php`
- `tests/Feature/Livewire/Cajas/PagosPendientesFacturacionTest.php`
- Tests adicionales en `CambioFormaPagoServiceTest.php`

**Modificados**:
- `app/Models/VentaPago.php` (fillable, constantes, scopes)
- `app/Services/Ventas/CambioFormaPagoService.php` (refactor 2 fases + regla binaria + `reintentarFacturacionPago`)
- `app/Livewire/Ventas/Ventas.php` (método `reintentarFacturacion`)
- `resources/views/livewire/ventas/ventas.blade.php` (badges + botón reintentar)
- `database/sql/tenant_tables.sql` (regenerado)
- `database/seeders/PermisosFuncionalesSeeder.php`
- `routes/web.php`
- `lang/{es,en,pt}.json`
- `tests/Traits/WithTenant.php` (sin cambios estructurales)

### Criterios de aceptación adicionales (extensión)

- [ ] Cambio con `monto_facturado_viejo == monto_facturado_nuevo`: NO se emite NC ni FC nueva (aunque cambie la FP).
- [ ] Cambio con montos facturados distintos + sucursal auto: se emite NC (por `monto_facturado` del pago viejo) + FC nueva (por `monto_facturado_nuevo`).
- [ ] FC nueva compartida en un solo comprobante por todos los pagos nuevos facturables (no una FC por pago).
- [ ] Si falla ARCA en Fase A (NC): rollback total, ningún cambio persistido.
- [ ] Si falla ARCA en Fase B (FC nueva): cambio + NC persisten, pagos nuevos facturables quedan `estado_facturacion='pendiente_de_facturar'`, se muestra toast de advertencia.
- [ ] Backfill post-migración: todos los pagos con `comprobante_fiscal_id` tienen `estado_facturacion='facturado'`; resto `'no_facturado'`.
- [ ] Componente `PagosPendientesFacturacion` lista solo pagos `pendientes_de_facturar` de sucursal activa, respeta filtros.
- [ ] Botón "Reintentar facturación" emite FC y muta estado a `'facturado'`; si falla, muta a `'error_arca'`.
- [ ] Detalle de venta muestra badge correspondiente según `estado_facturacion` de cada pago.
