# Sistema Impositivo Argentino - Especificación

## Estado: APROBADO — EN IMPLEMENTACIÓN

> Spec creado y aprobado el 2026-06-12 (decisiones D1-D5 con el usuario).
> Implementación por fases vía /sdd-apply en rama feat/sistema-impositivo.

---

## Contexto y Motivación

El sistema soporta múltiples CUITs por comercio (modelo `Cuit`: condición de IVA, número de IIBB, certificados ARCA, puntos de venta, pivot `cuit_sucursal`) y emite comprobantes fiscales con CAE y desglose de IVA (`ComprobanteFiscal` + `ComprobanteFiscalIva`). Sin embargo:

- **Nada calcula los impuestos "extra"**: el campo `tributos` del comprobante está hardcodeado en 0 (`ComprobanteFiscalService`), las percepciones/retenciones que sufren las cuentas del comercio (Mercado Pago, bancos, proveedores) no se desglosan, y no existe posición fiscal alguna.
- La conciliación MP (PR #132) empezó a registrar lo que el proveedor descuenta como un egreso genérico `impuesto_integracion` — registra el monto pero no QUÉ impuesto es ni de qué jurisdicción, y no valida si corresponde según la condición fiscal del CUIT.
- El contador del comercio hoy no puede sacar del sistema un subdiario de IVA ni una posición de IIBB.

Este feature define la **estructura fiscal integral**: catálogo de impuestos, configuración por CUIT, registro estructurado de cada movimiento fiscal (sufrido o aplicado), cálculo en la emisión de comprobantes, desglose/validación de lo que descuenta MP, y los reportes de posición fiscal.

### Decisiones de alcance (2026-06-12, con el usuario)

- **D1 — Ambos sentidos**: impuestos SUFRIDOS (retenciones/percepciones que aplican MP, bancos y proveedores) E impuestos APLICADOS (tributos que el comercio calcula al emitir comprobantes según condición de IVA del emisor y receptor).
- **D2 — Entregable principal**: posición fiscal + libros (subdiario IVA ventas/compras, posición mensual de IVA e IIBB por jurisdicción, exportable). Los movimientos fiscales se registran como datos estructurados.
- **D3 — Alícuotas híbrido**: tabla configurable por CUIT/jurisdicción/impuesto cargada manualmente (con el contador), con estructura preparada para enchufar padrones provinciales (ARBA/AGIP) después → campo `origen_alicuota`.
- **D4 — Vínculo MP con desglose + validación**: pedir columnas `TAXES_AMOUNT`/`TAX_DETAIL`/`TAXES_DISAGGREGATED` al reporte, desglosar cada impuesto por tipo/jurisdicción, y ALERTAR cuando lo descontado difiere de lo esperado según la config del CUIT.

---

## Principios de Diseño

1. **Append-only ledger fiscal**: `movimientos_fiscales` nunca se edita ni borra — anulaciones por contraasiento (mismo patrón que MovimientoCuentaCorriente/MovimientoStock/MovimientoCuentaEmpresa).
2. **Todo keyed por CUIT**: cada movimiento fiscal pertenece a un `cuit_id`. La posición fiscal es por CUIT + período. La config de alícuotas es por CUIT.
3. **El catálogo describe, la config decide**: `impuestos` (catálogo de tipos+jurisdicciones, seed del sistema) es descriptivo; `cuit_impuesto_configs` dice si ESTE CUIT está alcanzado, con qué alícuota y desde cuándo.
4. **Origen polimórfico**: cada movimiento fiscal apunta a su origen (`ComprobanteFiscal`, `Compra`, `ConciliacionFila`, alta manual) — trazabilidad completa, sin morphMap (string plano, como ConciliacionFila).
5. **Período fiscal = string `YYYY-MM`** calculado al registrar (indexable, inmutable, no depende de timezone en consultas).
6. **Provider-agnostic en la conciliación**: el desglose de impuestos de MP pasa por el contrato del gateway (otra integración futura puede mapear sus propios códigos).
7. **No bloquear lo existente**: si un CUIT no tiene config impositiva, todo sigue funcionando como hoy (tributos 0, residuo genérico). El feature suma información, no agrega fricción obligatoria.
8. **Services first**: `ImpuestoService` es el único que escribe `movimientos_fiscales`; Livewire/reportes solo leen.

---

## Requisitos Funcionales

### RF-01: Catálogo de impuestos
- Tabla `impuestos` seeded por el sistema: IVA débito/crédito, percepción IVA, retención IVA, percepción IIBB, retención IIBB (una por jurisdicción argentina, código ISO 3166-2), retención ganancias, créditos y débitos bancarios (ley 25.413), SIRCREB, otros.
- Cada impuesto: `codigo` único, `nombre`, `tipo` (iva|iibb|ganancias|credito_debito|otro), `naturaleza_default` (percepcion|retencion|debito_fiscal|credito_fiscal), `jurisdiccion` (nullable: `AR` nacional o ISO 3166-2 provincial, ej. `AR-C` CABA, `AR-B` PBA), `activo`.
- Extensible: el comercio puede crear impuestos custom (`es_sistema=false`).

### RF-02: Configuración impositiva por CUIT
- Por cada CUIT del comercio: en qué impuestos está inscripto/alcanzado, número de inscripción (ej. IIBB ya existe en `cuits.numero_iibb` — se mantiene como dato del CUIT; la inscripción por jurisdicción vive acá), alícuota aplicable, `origen_alicuota` (manual|padron — padron queda para fase futura), si es **agente de percepción/retención** de ese impuesto, vigencia (`vigente_desde`/`vigente_hasta` nullable).
- La condición de IVA del CUIT ya existe (`cuits.condicion_iva_id`) y es la llave del cálculo: Responsable Inscripto discrimina IVA y puede ser agente; Monotributo no genera débito fiscal ni percibe.
- UI dentro de la gestión de CUITs existente (tab/sección nueva), NO sucursal-aware.

### RF-03: Ledger fiscal (`movimientos_fiscales`)
- Registro estructurado de cada impuesto: cuit, sucursal (nullable), impuesto, sentido (`sufrido`|`aplicado`), naturaleza (percepcion|retencion|debito_fiscal|credito_fiscal|tributo), fecha, `periodo_fiscal` (YYYY-MM), base imponible, alícuota, monto, número de certificado/constancia (nullable, para retenciones), origen polimórfico, estado (activo|anulado), usuario.
- Anulación SOLO por contraasiento (`anularMovimientoFiscal()` genera el inverso y linkea).
- Los movimientos de IVA débito/crédito se generan automáticamente desde comprobantes emitidos y compras registradas (no se cargan a mano).

### RF-04: Impuestos aplicados al emitir comprobantes
- Al emitir un comprobante fiscal, `ImpuestoService::calcularTributos()` evalúa la config del CUIT emisor + condición de IVA del receptor y devuelve los tributos aplicables (ej. percepción IIBB si el CUIT es agente de percepción y el receptor es RI de la jurisdicción).
- El desglose se persiste en `comprobante_fiscal_tributos` (paralelo a `ComprobanteFiscalIva`), el total va al campo `tributos` existente y viaja a ARCA en el array de tributos del comprobante.
- Cada tributo aplicado genera su `movimiento_fiscal` (sentido `aplicado`) al confirmar el CAE. La nota de crédito genera los contraasientos proporcionales.
- El IVA débito del comprobante (ya calculado hoy en `ComprobanteFiscalIva`) se registra también como `movimiento_fiscal` naturaleza `debito_fiscal` — alimenta la posición sin recalcular nada.
- Si el CUIT no es agente de nada (caso pyme típico): tributos = 0, comportamiento idéntico al actual.

### RF-05: Impuestos sufridos vía compras
- `CompraService` ya calcula crédito fiscal de IVA por detalle → al confirmar la compra se registra el `movimiento_fiscal` naturaleza `credito_fiscal` (por alícuota).
- La carga de compra permite registrar percepciones sufridas en la factura del proveedor (IVA percepción, IIBB percepción por jurisdicción): nueva sección en el form de compra → `movimientos_fiscales` sentido `sufrido` con origen `Compra`.

### RF-06: Impuestos sufridos vía conciliación MP (vínculo con integraciones)
- El gateway pide las columnas `TAXES_AMOUNT`, `TAX_DETAIL` y `TAXES_DISAGGREGATED` en la config del reporte (actualizar config existente vía PUT si ya fue creada — validar keys contra la API real al implementar: MP rechaza inválidas con 400 mudo).
- La fila cruda del CSV se guarda en `conciliaciones_filas.datos_extra` (JSON) — trazabilidad y a prueba de columnas futuras.
- Las filas tipo `impuesto` (tanto las tax_* como el residuo de cobros) se desglosan: `TAX_DETAIL` (ej. `tax_payment_iibb` → percepción IIBB; jurisdicción por sufijo cuando existe, ej. `tax_iibb_misiones`) mapea a un `impuesto_id` del catálogo vía tabla de mapeo del gateway.
- Al APLICAR la conciliación, cada fila de impuesto genera además del movimiento de ledger su `movimiento_fiscal` (sentido `sufrido`, origen `ConciliacionFila`) con el CUIT resuelto desde la config de la integración (la cuenta MP pertenece a un CUIT — ver RF-07).
- **Validación (D4)**: si el CUIT tiene config para ese impuesto, se compara la alícuota efectiva (monto/base) contra la configurada; si difiere > tolerancia se marca la fila con `alerta_validacion` y la pantalla de revisión lo muestra (badge ámbar + detalle esperado vs real).

### RF-07: Vínculo cuenta MP ↔ CUIT
- `cuentas_empresa` (las vinculadas a integraciones) y/o `integracion_pago_sucursal` necesitan saber a qué `cuit_id` pertenece la cuenta de MP (una cuenta MP está registrada bajo un CUIT). Campo `cuit_id` nullable en `cuentas_empresa` + autocompletado: al vincular la integración, si el comercio tiene un solo CUIT activo se asigna; si hay varios, selector en la config de la cuenta.
- Sin CUIT asignado: los movimientos fiscales de la conciliación quedan sin generar y la corrida muestra aviso (no bloquea el ledger).

### RF-08: Alta manual de movimientos fiscales
- Pantalla para registrar retenciones/percepciones sufridas que no entran por ningún flujo automático (ej. retención bancaria SIRCREB del resumen, retención de un cliente agente al cobrar): CUIT, impuesto, fecha, base, alícuota o monto, certificado, observaciones.
- Permiso propio (`func.fiscal.movimientos_crear`). Anulación por contraasiento con permiso (`func.fiscal.movimientos_anular`).

### RF-09: Posición fiscal y libros
- **Subdiario IVA Ventas**: comprobantes emitidos del CUIT+período: neto gravado por alícuota, IVA, exento/no gravado, tributos, total. Export CSV compatible "Libro IVA Digital" (formato simplificado fase 1, formato ARCA exacto fase futura).
- **Subdiario IVA Compras**: compras del período con crédito fiscal por alícuota + percepciones sufridas.
- **Posición IVA del período**: débito fiscal − crédito fiscal − retenciones/percepciones de IVA sufridas = saldo técnico + saldo de libre disponibilidad.
- **Posición IIBB por jurisdicción**: base imponible (ventas del período por jurisdicción de la sucursal del comprobante), percepciones y retenciones sufridas a cuenta.
- Filtros: CUIT (obligatorio), período (YYYY-MM), export CSV/Excel de todo.

### RF-10: Permisos y menú
- Módulo nuevo "Fiscal" (menú + permisos + traducciones + ProvisionComercioCommand): `fiscal.posicion`, `fiscal.libros`, `fiscal.movimientos`, `fiscal.configuracion` con sus `func.*`.

---

## Modelo de Datos

### Tablas nuevas (todas tenant, prefijo `{NNNNNN}_`)

#### `impuestos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `codigo` | varchar(50) UNIQUE | — | ej. `iva_debito`, `perc_iibb_ar_c`, `ret_sircreb` |
| `nombre` | varchar(150) | — | |
| `tipo` | enum(iva,iibb,ganancias,credito_debito,otro) | — | |
| `naturaleza_default` | enum(percepcion,retencion,debito_fiscal,credito_fiscal,tributo) | — | |
| `jurisdiccion` | varchar(6) NULL | NULL | `AR` o ISO 3166-2 (`AR-C`, `AR-B`...) |
| `es_sistema` | boolean | true | seed vs custom del comercio |
| `activo` | boolean | true | |
| timestamps | | | |

#### `cuit_impuesto_configs`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cuit_id` | bigint FK cuits | — | |
| `impuesto_id` | bigint FK impuestos | — | |
| `inscripto` | boolean | true | alcanzado por este impuesto |
| `numero_inscripcion` | varchar(30) NULL | NULL | |
| `es_agente_percepcion` | boolean | false | |
| `es_agente_retencion` | boolean | false | |
| `alicuota` | decimal(6,4) NULL | NULL | % aplicable (percepción que aplica o sufre) |
| `alicuota_minimo_base` | decimal(12,2) NULL | NULL | base mínima para aplicar |
| `origen_alicuota` | enum(manual,padron) | manual | D3: padrón = fase futura |
| `vigente_desde` | date NULL | NULL | |
| `vigente_hasta` | date NULL | NULL | |
| timestamps + UNIQUE(cuit_id, impuesto_id, vigente_desde) | | | |

#### `movimientos_fiscales`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cuit_id` | bigint FK cuits | — | |
| `sucursal_id` | bigint NULL FK | NULL | jurisdicción de la operación |
| `impuesto_id` | bigint FK impuestos | — | |
| `sentido` | enum(sufrido,aplicado) | — | |
| `naturaleza` | enum(percepcion,retencion,debito_fiscal,credito_fiscal,tributo) | — | |
| `fecha` | date | — | |
| `periodo_fiscal` | char(7) INDEX | — | `YYYY-MM` |
| `base_imponible` | decimal(14,2) NULL | NULL | |
| `alicuota` | decimal(6,4) NULL | NULL | |
| `monto` | decimal(14,2) | — | siempre positivo; el signo lo da naturaleza+sentido |
| `certificado_numero` | varchar(50) NULL | NULL | constancia de retención |
| `origen_tipo` | varchar(50) NULL | NULL | ComprobanteFiscal/Compra/ConciliacionFila/NULL=manual |
| `origen_id` | bigint NULL | NULL | |
| `movimiento_anulado_id` | bigint NULL FK self | NULL | contraasiento → original |
| `estado` | enum(activo,anulado) | activo | |
| `observaciones` | text NULL | NULL | |
| `usuario_id` | bigint NULL | NULL | |
| timestamps + INDEX(cuit_id, periodo_fiscal), INDEX(origen_tipo, origen_id) | | | |

#### `comprobante_fiscal_tributos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `comprobante_fiscal_id` | bigint FK CASCADE | — | |
| `impuesto_id` | bigint FK impuestos | — | |
| `base_imponible` | decimal(14,2) | — | |
| `alicuota` | decimal(6,4) | — | |
| `monto` | decimal(14,2) | — | |
| `codigo_arca` | smallint NULL | NULL | código de tributo del WS de ARCA |
| timestamps | | | |

### Tablas modificadas

#### `conciliaciones_filas` — Cambios
- Agregar: `datos_extra` (JSON, NULL) AFTER `descripcion` — fila cruda del CSV del proveedor.
- Agregar: `impuesto_id` (bigint, NULL, FK impuestos) AFTER `concepto_codigo` — desglose del impuesto identificado.
- Agregar: `alerta_validacion` (varchar(255), NULL) — texto de la alerta esperado-vs-real (RF-06).

#### `cuentas_empresa` — Cambios
- Agregar: `cuit_id` (bigint, NULL, FK cuits) AFTER `identificador_externo` (RF-07).

#### `compras` — Cambios
- Sin columnas nuevas (las percepciones sufridas van directo a `movimientos_fiscales` origen Compra).

### Seeds
- Catálogo `impuestos`: IVA débito/crédito, percepción/retención IVA, percepción/retención IIBB × 24 jurisdicciones, retención ganancias, ley 25.413, SIRCREB, otro. (~55 filas, generadas desde array de jurisdicciones).
- `ProvisionComercioCommand` → seed para comercios nuevos.

---

## Pantallas UI

### Pantalla 1: Configuración impositiva del CUIT (dentro de la gestión de CUITs existente)
**Componente**: extender el componente existente de CUITs con tab "Impuestos" (o sub-componente `App\Livewire\Configuracion\CuitImpuestos`)
**Traits**: ninguno (global)
- Lista de impuestos del catálogo con toggle inscripto, alícuota, agente percepción/retención, vigencia
- Alta de impuesto custom
- Hint del origen de alícuota (manual; padrón deshabilitado "próximamente")

### Pantalla 2: Movimientos fiscales (`/fiscal/movimientos`)
**Componente**: `App\Livewire\Fiscal\MovimientosFiscales`
**Traits**: ninguno (global, filtro por CUIT) — `#[Lazy]` + skeleton
- Listado paginado con filtros: CUIT, período, impuesto, sentido, origen
- Modal alta manual (RF-08), anulación por contraasiento con confirmación
- Cards móvil + tabla desktop, dark mode

### Pantalla 3: Posición fiscal (`/fiscal/posicion`)
**Componente**: `App\Livewire\Fiscal\PosicionFiscal`
**Traits**: ninguno — `#[Lazy]` + skeleton
- Selector CUIT + período → cards: IVA (débito, crédito, retenciones/percepciones a cuenta, saldo), IIBB por jurisdicción (base, a cuenta), otros
- Export CSV/Excel

### Pantalla 4: Libros IVA (`/fiscal/libros`)
**Componente**: `App\Livewire\Fiscal\LibrosIva`
**Traits**: ninguno — `#[Lazy]` + skeleton
- Tabs Ventas/Compras, CUIT + período, detalle por comprobante con desglose por alícuota
- Export CSV

### Cambios en pantallas existentes
- **Conciliaciones**: fila de impuesto muestra desglose (impuesto + jurisdicción) y badge de `alerta_validacion`; aviso si la cuenta no tiene CUIT asignado.
- **Cuentas de empresa**: selector de CUIT en cuentas vinculadas a integración.
- **Compras**: sección "Percepciones de la factura" en el form.

---

## Servicios

### `ImpuestoService` — `app/Services/Fiscal/ImpuestoService.php` (nuevo)
- `calcularTributos(Cuit $emisor, ?CondicionIva $receptor, float $netoGravado, ?Sucursal $sucursal): array` — tributos aplicables al emitir (RF-04)
- `registrarMovimientoFiscal(array $datos): MovimientoFiscal` — única puerta de escritura; calcula `periodo_fiscal`
- `anularMovimientoFiscal(MovimientoFiscal $mov, int $usuarioId): MovimientoFiscal` — contraasiento
- `registrarDesdeComprobante(ComprobanteFiscal $c): void` — débito fiscal + tributos al confirmar CAE; inverso para NC
- `registrarDesdeCompra(Compra $compra, array $percepciones): void` — crédito fiscal + percepciones sufridas
- `registrarDesdeConciliacion(ConciliacionFila $fila, Cuit $cuit): void` — sufridos MP (RF-06)
- `validarImpuestoSufrido(ConciliacionFila $fila, Cuit $cuit): ?string` — alerta esperado vs real (D4)
- `configVigente(Cuit $cuit, int $impuestoId, ?Carbon $fecha): ?CuitImpuestoConfig`

### `PosicionFiscalService` — `app/Services/Fiscal/PosicionFiscalService.php` (nuevo)
- `posicionIva(Cuit $cuit, string $periodo): array`
- `posicionIibb(Cuit $cuit, string $periodo): array` — por jurisdicción
- `libroIvaVentas(Cuit $cuit, string $periodo): Collection`
- `libroIvaCompras(Cuit $cuit, string $periodo): Collection`
- `exportarCsv(...)`

### Servicios modificados
- `ARCA/ComprobanteFiscalService`: reemplazar `'tributos' => 0` por cálculo vía ImpuestoService + persistir `comprobante_fiscal_tributos` + enviar array de tributos a ARCA + hook post-CAE → `registrarDesdeComprobante()`
- `CompraService`: hook al confirmar → `registrarDesdeCompra()`
- `IntegracionesPago/MercadoPagoGateway`: columnas nuevas en `asegurarConfigReporteCuenta` (+ PUT si la config existe sin ellas), `datos_extra` en filas normalizadas, mapa TAX_DETAIL → código de impuesto
- `IntegracionesPago/ConciliacionCuentaService`: desglose por impuesto en filas tipo `impuesto`, `alerta_validacion`, generación de movimientos fiscales al aplicar (si la cuenta tiene CUIT)

---

## Migraciones Necesarias

1. `create_impuestos_table` + seed catálogo
2. `create_cuit_impuesto_configs_table`
3. `create_movimientos_fiscales_table`
4. `create_comprobante_fiscal_tributos_table`
5. `add_datos_fiscales_to_conciliaciones_filas` (datos_extra, impuesto_id, alerta_validacion)
6. `add_cuit_id_to_cuentas_empresa`
7. `add_fiscal_menu_y_permisos` (menú + permisos, conexión pymes)
8. Regenerar `database/sql/tenant_tables.sql` + actualizar `ProvisionComercioCommand`

---

## Traducciones

Módulo completo nuevo (~40 claves es/en/pt vía /traducir en cada fase): nombres de pantallas, impuestos del catálogo, naturalezas, sentidos, labels de posición fiscal, alertas de validación, textos de ayuda de config.

---

## Criterios de Aceptación

- [ ] Un CUIT RI con config de percepción IIBB como agente genera tributos en facturas A a receptores RI y los informa a ARCA; el mismo comprobante para consumidor final no percibe
- [ ] Un CUIT sin config impositiva factura exactamente igual que hoy (tributos 0, sin movimientos fiscales de tributos, sin errores)
- [ ] Emitir comprobante con CAE genera movimientos fiscales de débito fiscal por alícuota; la NC genera contraasientos
- [ ] Confirmar compra genera crédito fiscal; percepciones cargadas en la compra quedan como sufridas
- [ ] La conciliación MP desglosa filas tax_* por impuesto/jurisdicción, guarda la fila cruda en datos_extra, y al aplicar genera movimientos fiscales sufridos con el CUIT de la cuenta
- [ ] Si lo que MP descontó difiere de la alícuota configurada → alerta visible en la revisión
- [ ] Alta manual de retención con certificado + anulación por contraasiento (permisos respetados)
- [ ] Posición IVA del período cierra: débito − crédito − a cuenta = saldo, verificable contra los libros del mismo período
- [ ] Posición IIBB muestra base y a-cuenta por jurisdicción
- [ ] Exports CSV de libros y posición
- [ ] Tests: ImpuestoService (cálculo por condición IVA × agente × receptor), PosicionFiscalService, integración conciliación→fiscal, smoke de los 4 componentes
- [ ] Docs actualizados (@docs-sync) al crear el PR

---

## Plan de Implementación

### Fase 1: BD + Modelos + Catálogo [COMPLETO]
Migraciones 1-6, modelos `Impuesto`, `CuitImpuestoConfig`, `MovimientoFiscal`, `ComprobanteFiscalTributo`, relaciones en Cuit/CuentaEmpresa/ConciliacionFila, seed catálogo, tenant_tables.sql, ProvisionComercioCommand.

### Fase 2: ImpuestoService núcleo [PENDIENTE]
registrar/anular/configVigente/calcularTributos + tests unitarios exhaustivos (matriz condición IVA × agente × receptor).

### Fase 3: Config UI por CUIT [PENDIENTE]
Tab impuestos en gestión de CUITs + traducciones + smoke test.

### Fase 4: Sufridos vía conciliación MP [PENDIENTE]
Gateway (columnas nuevas + PUT config + validación de keys contra API real), datos_extra, mapa TAX_DETAIL, desglose + alerta en revisión, cuit_id en cuentas, movimientos fiscales al aplicar. Validación EN VIVO con la cuenta real del usuario.

### Fase 5: Aplicados en comprobantes [PENDIENTE]
calcularTributos en emisión, comprobante_fiscal_tributos, envío a ARCA, débito fiscal post-CAE, NC con contraasientos. Validación en testing de ARCA.

### Fase 6: Sufridos vía compras + alta manual [PENDIENTE]
Hook CompraService, sección percepciones en form de compra, pantalla movimientos fiscales con alta manual y anulación.

### Fase 7: Posición fiscal + libros [PENDIENTE]
PosicionFiscalService + 2 pantallas + exports + menú/permisos del módulo.

### Fase 8: Verificación + docs [PENDIENTE]
/sdd-verify, @docs-sync, manual de usuario.

---

## Notas y Decisiones

- 2026-06-12 (Fase 1): descubierto y reparado de paso un bug del PR #132 ya mergeado — la migración `140001_add_tipo_impuesto` alteraba `conciliaciones_filas` pero la tabla real es `conciliacion_filas` (singular): el try/catch tenant se tragó el error y el enum quedó sin `impuesto` en comercios existentes. Fix: migración `150006` re-aplica el ALTER con el nombre correcto. Lección: tras una migración tenant, verificar el efecto en la BD real (el patrón try/catch convierte errores en no-ops silenciosos).
- 2026-06-12 (Fase 1): los COMMENT de columnas en tenant_tables.sql NO pueden contener `;` — el provisioning de tests splitea el SQL por punto y coma.
- 2026-06-12 (Fase 1): el catálogo canónico de impuestos vive en `create_impuestos_table::catalogo()` (estático); ProvisionComercioCommand lo consume vía `require` para no duplicar las 56 filas.

- 2026-06-12: D1-D4 decididas con el usuario (ver Contexto).
- 2026-06-12: La jurisdicción de la operación se infiere de la PROVINCIA de la sucursal del comprobante. **Dependencia**: los campos provincia/localidad de sucursal están pendientes de propagar a ConfiguracionEmpresa (memoria del proyecto) — Fase 1 debe incluirlos si no llegaron antes.
- 2026-06-12: El formato exacto del Libro IVA Digital de ARCA queda para fase futura; fase 1 exporta CSV simplificado para el contador.
- 2026-06-12: Padrones provinciales (ARBA/AGIP): estructura preparada (`origen_alicuota`), implementación fuera de alcance.
- 2026-06-12 (D5, usuario): retenciones que aplican CLIENTES agentes al pagar → **alta manual (RF-08) por ahora**; si a futuro resulta frecuente se integra al flujo de cobranza como fase nueva.
