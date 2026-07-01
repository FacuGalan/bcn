# Sistema Impositivo Argentino - Especificación

## Estado: APROBADO — PUNTO DE DESCANSO LIMPIO (2026-06-23)

> Spec creado y aprobado el 2026-06-12 (decisiones D1-D5 con el usuario).
> Implementación por fases vía /sdd-apply en rama feat/sistema-impositivo.
>
> **Consolidación 2026-06-23 / actualizado 2026-06-24**: todo lo codeable sin
> bloqueo externo está COMPLETO, mergeado y verde. Fases 1, 2, 3, 5a, 5b, 6
> (capa fiscal), 7, 9, 10a + 10b + RF-08 mergeadas. **10b mergeada (PR #139,
> 2026-06-24)**: importador de padrón ARBA/AGIP validado en vivo con archivo
> real, subida solo comprimida (.zip/.gz, detección por bytes mágicos +
> descompresión por streaming), comando artisan `fiscal:importar-padron` de
> operación, fix `parseFecha` (cero inicial de ARBA como espacio). Lo que resta
> está **bloqueado por dato externo, no por falta de trabajo**:
> - **4b** (base imponible de impuestos sufridos vía MP): necesita UNA fila
>   real con impuestos. **Evaluado y descartado el camino MCP/testing**: el
>   sandbox de MP NO genera retenciones (dependen del padrón fiscal real, no
>   del flujo de pago); la cuenta del comercio no sufre impuestos por su
>   condición de IVA; y MP NO documenta el esquema de `TAXES_DISAGGREGATED`.
>   Diseño ya preparado: la fila cruda se conserva en `datos_extra` → la
>   primera fila real de CUALQUIER comercio destraba el parseo como drop-in.
>   NO escribir parser especulativo (sería deuda no testeable).
> - **Fase 6 (módulo compras funcional)**: NO avanzar — depende del modelo de
>   costos/precios de proveedor aún sin definir (merece su propio SDD).
> - **Auditoría fiscal "REVISAR (Fable)"**: REALIZADA el 2026-07-01 (rama
>   feat/fiscal-revision-fable): 2 bugs corregidos (NC cross-período,
>   precedencia manual/padrón) + 3 profundizaciones (exclusión IVA por
>   certificado, monto mínimo de percepción, desglose ingresos IIBB). Quedan
>   solo preguntas normativas para el contador — ver checklist más abajo.

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

### RF-11: Domicilios fiscales por CUIT y jurisdicción de la operación (agregado 2026-06-17)

> Surge de revisar la fase de homologación: la jurisdicción que hoy se infiere de
> la sucursal física es incorrecta cuando una caja factura con un PV cuyo CUIT
> está domiciliado en otra provincia. AFIP modela esto con domicilios declarados
> por CUIT, y cada punto de venta asociado a uno. **Es prerequisito de la Fase 5b**
> (las percepciones aplicadas viajan a AFIP con la jurisdicción del PV).

- **Domicilios por CUIT (espejo AFIP)**: cada CUIT declara N domicilios. Tabla nueva `cuit_domicilios`: tipo (fiscal/comercial/otro), provincia (ISO 3166-2), localidad (ref a `localidades` config), dirección, geo opcional (lat/lng), `es_principal`, activo. (CP diferido — ver decisión 2026-06-17.)
- **PV → domicilio**: `puntos_venta.cuit_domicilio_id` (uno de los domicilios de SU CUIT). Refleja el alta de AFIP donde cada PV se da con un domicilio.
- **Jurisdicción de la operación**: se deriva de `comprobante.puntoVenta.cuitDomicilio.provincia` (NO de `sucursal.provincia`). El comprobante ya guarda `punto_venta_id` (NOT NULL) → la cadena es confiable. Esto alimenta tanto el cálculo de percepciones IIBB aplicadas (Fase 5b) como la base imponible de IIBB de la posición (Fase 7).
- **Sucursal con domicilio estructurado (STANDALONE)**: migrar `sucursales.localidad` de texto libre a `localidad_id` (ref soft a `localidades`), manteniendo `provincia` (ISO, ya existe) y lat/lng. La edición vive en la **config de sucursales** y es **independiente de tener CUIT o integración de pago**: un comercio puede dejar bien configurada provincia/localidad/geolocalización de una sucursal sin CUIT ni MP (casos de uso: tienda digital a futuro, logística, o simplemente correctitud de datos). MP conserva su modal pero lee/escribe los mismos campos; al activar CUIT/integraciones, los datos ya están y son compatibles.
- **Formato de domicilio unificado y reutilizable**: un componente Blade/trait compartido (provincia → localidad dependiente + geo opcional) es la **capa de compatibilidad** — mismo formato para el domicilio físico de la sucursal, los domicilios fiscales del CUIT y desarrollos futuros. **Importante (decoupling)**: la dirección física de la sucursal NO es la fuente de la jurisdicción fiscal (esa sale del domicilio del PV); son dos direcciones con el MISMO formato pero distinta fuente. "Deberían coincidir", pero no se acoplan. Objetivo del usuario: compatibilidad bilateral y replicabilidad.
- **Padrón de localidades**: reemplazar el dataset actual (CPs con errores) por una fuente oficial y actual (GeoRef Argentina / datos.gob.ar) para provincia→localidad confiables. **El CP NO se muestra** (dato no confiable); se difiere como campo editable por domicilio si a futuro se necesita imprimir/declarar.
- **Migración de datos existentes**: el domicilio único actual de cada CUIT (`cuits.direccion` + `cuits.localidad_id`) → su `cuit_domicilios` `es_principal`; cada PV existente → el principal de su CUIT; sucursales → backfill de `localidad_id` best-effort desde el texto libre. Sin romper lo que ya factura.

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

#### `cuit_domicilios` — Tabla nueva (RF-11, tenant)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cuit_id` | bigint FK cuits | — | |
| `tipo` | enum(fiscal,comercial,otro) | fiscal | espejo de AFIP |
| `provincia` | varchar(6) | — | ISO 3166-2 (`AR-B`...) — jurisdicción |
| `localidad_id` | bigint NULL | NULL | ref soft a `localidades` (config, sin FK cross-DB, igual que `cuits.localidad_id`) |
| `direccion` | varchar(255) | — | calle y número |
| `codigo_postal` | varchar(10) NULL | NULL | columna creada pero NO usada en UI (diferido) |
| `latitud` | decimal(10,7) NULL | NULL | geo opcional |
| `longitud` | decimal(10,7) NULL | NULL | |
| `es_principal` | boolean | false | domicilio fiscal principal del CUIT |
| `activo` | boolean | true | |
| timestamps + INDEX(cuit_id) | | | |

#### `puntos_venta` — Cambios (RF-11)
- Agregar: `cuit_domicilio_id` (bigint, NULL, FK `cuit_domicilios`) — el domicilio declarado del PV (uno de los de su CUIT). Backfill al principal del CUIT en la migración.

#### `sucursales` — Cambios (RF-11)
- Agregar: `localidad_id` (bigint, NULL, ref soft a `localidades`) — reemplaza la edición de `localidad` (texto libre, que queda como columna de transición / se backfillea). `provincia` (ISO) y lat/lng ya existen (agregadas para MP).

#### `localidades` (config, compartida) — Reemplazo de datos (RF-11)
- Reseed desde fuente oficial actual (GeoRef Argentina). **Riesgo**: `cuits.localidad_id` y futuros `*.localidad_id` referencian estos IDs → la migración debe **remapear por (provincia, nombre)** o preservar IDs, no truncar y recrear a ciegas. `codigo_postal` queda nullable y sin uso en UI.

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

### Fase 2: ImpuestoService núcleo [COMPLETO]
registrar/anular/configVigente/calcularTributos + tests unitarios exhaustivos (matriz condición IVA × agente × receptor). `app/Services/Fiscal/ImpuestoService.php` + `tests/Unit/Services/Fiscal/ImpuestoServiceTest.php` (23 tests verdes). Los hooks `registrarDesde*`/`validarImpuestoSufrido` NO se incluyeron acá — se cablean en fases 4/5/6 cuando existan los orígenes. **Pendiente Fase 9**: `calcularTributos` hoy recibe `Sucursal` para la jurisdicción IIBB; cambiará a recibir la jurisdicción del domicilio del PV (firma + tests). Aún no está wired a emisión (5b), así que el cambio es de bajo riesgo.

### Fase 3: Config UI por CUIT [COMPLETO]
Componente embebido `App\Livewire\Configuracion\CuitImpuestos` (NO full-page, NO sucursal-aware), abierto vía evento `abrir-impuestos-cuit` desde un botón "Impuestos" en cada fila de CUIT (tab-cuits). Modal con: combobox de alta rápida sobre el catálogo, lista editable (alícuota, base mínima, N° inscripción, vigencia opcional, flags inscripto/agente perc/ret), alta de impuesto custom (es_sistema=false). v1: una config actual por impuesto (sin historial de vigencias). **El IVA débito/crédito NO se gestiona en esta pantalla** (decisión 2026-06-16): lo determina la condición de IVA del CUIT (form del CUIT) y la posición se arma desde comprobantes/compras en Fases 5/6 (que distinguen RI vs monotributo — el monotributo no lleva IVA). El combobox excluye naturalezas `debito_fiscal`/`credito_fiscal` y el alta custom solo permite percepción/retención/tributo. Traducciones es/en/pt. Smoke + tests funcionales (abrir/agregar, quitar persiste, no-resiembra, catálogo sin IVA) — 5 verdes. Permisos: reusa el gate de ConfiguracionEmpresa (los `fiscal.*` finos son RF-10/Fase 7).

### Fase 4: Sufridos vía conciliación MP [4a COMPLETA / 4b PENDIENTE]
Partida en **4a (codeable)** + **4b (validación en vivo)**.

**4a COMPLETA**: cuit_id en cuentas (RF-07, commit `04ae303`) + mapa TAX_DETAIL→impuesto (commit `c64bfe0`) + persistencia `datos_extra` (fila CSV cruda) e `impuesto_id` en `conciliacion_filas` + `ImpuestoService::registrarDesdeConciliacion` (movimiento fiscal SUFRIDO al aplicar, imputado al CUIT de la cuenta, SIN base — 4b) + `validarImpuestoSufrido` (alerta si el CUIT no tiene el impuesto configurado) + UI de revisión (desglose impuesto + jurisdicción, badge ámbar de `alerta_validacion`, aviso "cuenta sin CUIT"). El gateway enriquece la fila normalizada con `tax_detail`/`impuesto`/`datos_extra` (provider-specific); `ConciliacionCuentaService` resuelve el `impuesto_id` y se mantiene agnóstico. Tests: +4 integración (ConciliacionCuentaServiceTest), +6 unit (ImpuestoServiceTest), +1 gateway (MercadoPagoGatewayTest). Decisiones: solo se genera ledger fiscal para impuestos IDENTIFICADOS (impuesto_id no nulo); el residuo genérico de cobros sigue siendo egreso de cuenta sin movimiento fiscal.

**4b PARCIAL (lo codeable hecho; falta una fila real con impuestos)**:
- HECHO: `asegurarConfigReporteCuenta` ahora pide las columnas `TAXES_AMOUNT`/`TAX_DETAIL`/`TAXES_DISAGGREGATED` (keys CONFIRMADAS válidas en la doc oficial de MP vía MCP) + **PUT best-effort** que mergea las columnas fiscales sobre la config existente (respeta los ajustes del usuario; si MP lo rechaza, la conciliación sigue sin desglose y se loguea). `mapearImpuestoReporte` es robusto al `TAX_DETAIL` "pelado" (nombre de provincia sólo, ej. `santa_fe`→AR-S) además del código completo; el mapeo se alimenta de `TAX_DETAIL` y cae al tipo/descripción. Tests: +2 gateway config (PUT si faltan / no-op si están) +1 mapeo provincia pelada.
- PENDIENTE (requiere una fila real con impuestos — la cuenta del usuario NO tiene impuestos para muestrear): **el esquema interno de `TAXES_DISAGGREGATED` MP NO lo documenta** (en toda la doc dice sólo "Impuestos desagregados en formato JSON"). Sin una fila real no se puede parsear la BASE IMPONIBLE ni la alícuota → el movimiento fiscal se sigue registrando sin base y la validación esperado-vs-real (D4) queda diferida. La fila cruda se conserva íntegra en `datos_extra`, así que la PRIMERA cuenta (de cualquier comercio) que sufra impuestos destraba el parseo. También queda por confirmar contra esa fila real el formato EXACTO de `TAX_DETAIL` (la doc lo muestra como provincia pelada; memoria previa lo tenía como `tax_payment_iibb_*`). Marcado en código con `REVISAR (4b)`.

### Fase 5: Aplicados en comprobantes [5a COMPLETA / 5b PENDIENTE]
Partida en **5a (IVA débito fiscal al ledger, sin tocar el payload de AFIP)** + **5b (percepciones aplicadas → AFIP, requiere homologación)**.

Motivo del corte: AFIP exige `ImpTotal = neto + IVA + tributos`, así que las percepciones aplicadas (agente) no se pueden registrar sin enviarlas a AFIP en el mismo acto. El IVA débito en cambio es puramente aditivo.

**5a COMPLETA**: `ImpuestoService::registrarDesdeComprobante(ComprobanteFiscal, ?usuarioId)` registra el IVA débito fiscal por alícuota (desde `ComprobanteFiscalIva`, sentido aplicado) y, para una nota de crédito (`comprobante_asociado_id`), contraasienta los movimientos del comprobante original. Guards de correctitud: **solo un Responsable Inscripto genera débito** (un Monotributo/Factura C no, aunque viniera importe), alícuota 0%/exento no genera, idempotente por origen. Cableado en `ComprobanteFiscalService` (factura + NC) **POST-COMMIT y best-effort** (`registrarFiscal()`): un CAE ya obtenido nunca se pierde por un fallo de ledger (se loguea y se puede backfillear). Tests +4 unit. REVISAR (Fable): la NC parcial requeriría reversa proporcional (hoy las NC del sistema son por el total).

**5b PENDIENTE (requiere homologación AFIP + Fase 9)**: `calcularTributos` en la emisión → `comprobante_fiscal_tributos` + campo `tributos` + array `Tributos` en `prepararDatosParaAFIP` (mapeo `codigo_arca`) + movimiento fiscal de la percepción aplicada (sentido aplicado), todo ATÓMICO con el total enviado a AFIP. **Depende de la Fase 9** (la jurisdicción de la percepción IIBB sale del domicilio fiscal del PV, no de la sucursal). Si hay percepción aplicada, también impacta visualmente en Nueva Venta (suma al total que paga el cliente). Solo afecta a CUITs configurados como agente de percepción; la pyme típica (no agente) ya quedó 100% correcta con 5a (tributos 0, sin cambios).

**PUNTOS CRÍTICOS 5b (revisión 2026-06-18, cruzando spec ↔ código real). Infra ya lista**: `ARCAService::prepararDatos` ya soporta `Tributos[]` + `ImpTrib`; `comprobante_fiscal_tributos` + modelo `ComprobanteFiscalTributo` + relación `ComprobanteFiscal::tributosDetalle()` existen; `calcularTributos(?string $jurisdiccion)` + `ComprobanteFiscal::jurisdiccionFiscal()` (helper PV→domicilio, fallback sucursal) ya hechos en Fase 9. **Lo que falta cablear + los riesgos:**
1. **NO best-effort post-commit para el MONTO (a diferencia de 5a).** AFIP valida `ImpTotal = ImpNeto + ImpIVA + ImpTrib`. Hay que PARTIR: (a) **pre-CAE, dentro de la transacción** de `crearComprobanteFiscal`: calcular tributos → persistir `comprobante_fiscal_tributos` + setear `comprobante.tributos` + recalcular `comprobante.total` → pasar `Tributos[]` e `imp_trib` a `prepararDatosParaAFIP` (hoy hardcodea `imp_trib=0`); si falla, no hay CAE. (b) **post-commit best-effort**: SOLO el `movimiento_fiscal` (sentido aplicado), como 5a (`registrarFiscal`).
2. **La percepción se cobra en la VENTA, no en la emisión.** Hoy `comprobante->total = venta->total_final`. Si la percepción es un extra del cliente y solo se calcula al facturar (que puede ser post-cobro), lo cobrado ≠ lo facturado. → 5b TOCA el flujo de NuevaVenta (calcular + mostrar + cobrar la percepción al cerrar la venta), no solo `ComprobanteFiscalService`. Es el mayor agregado de alcance de 5b. Definir interacción con `facturacion_fiscal_automatica` (emisión diferida vs al cierre).
3. **Correctitud — riesgo de SOBRE-percibir.** El v1 de `calcularTributos` aplica alícuota FIJA a todo RI en la jurisdicción del agente, sin mirar al cliente/padrón. Activar 5b así percibe de más a clientes exentos o con alícuota de padrón menor (riesgo operativo/legal). → **5b debe ir junto con Fase 10 (padrón)**, o ser opt-in por CUIT con semántica "alícuota fija" explícita y bajo responsabilidad del usuario.
4. **Prerequisito operativo**: la jurisdicción IIBB sale del domicilio del PV → los PV deben tener `cuit_domicilio_id` asignado (la migración de Fase 9 asignó el principal; PV nuevos se asignan en la UI `CuitPuntosVenta`). Sin domicilio → `jurisdiccionFiscal()` null → no percibe (default seguro, pero no factura la percepción que debería).
5. **Falta `codigo_arca` en el catálogo `impuestos`** (hoy solo está la columna en `comprobante_fiscal_tributos`). Para no hardcodear el mapeo tributo→código ARCA en 5b, agregar `codigo_arca` a `impuestos` (migración chica + seed del catálogo). Códigos ARCA de Tributos (confirmar contra WS): 02 = provinciales (IIBB), 06/07 = percepciones, 99 = otros.
6. **NC con percepción**: la nota de crédito de un comprobante con tributos debe revertir TAMBIÉN la percepción (incluirla en `comprobante_fiscal_tributos` de la NC con signo + en el contraasiento del ledger). Hoy la reversa de 5a es "por el total"; extenderla a tributos.
7. **`calcularTributos` evolucionará en Fase 10**: hoy recibe `?CondicionIva $receptor`; el padrón necesita la IDENTIDAD del cliente (CUIT) para el lookup por sujeto → la firma cambiará para recibir el cliente. No rediseñar dos veces: pensar 5b y Fase 10 en conjunto.

### Fase 6: Sufridos vía compras + alta manual [capa fiscal COMPLETA / módulo compras y alta manual PENDIENTES]

**Capa fiscal de compras COMPLETA (desacoplada)**: migración `add_fiscal_a_compras` → `compras.cuit_id` (atribución fiscal, FK nullable ON DELETE SET NULL) + tabla `compra_percepciones` (desglose de percepciones/retenciones sufridas, paralelo a comprobante_fiscal_tributos). Modelo `CompraPercepcion` + relaciones `cuit`/`percepciones` en `Compra`. `ImpuestoService::registrarDesdeCompra(Compra, array $ivaCredito, ?usuarioId)`: IVA crédito fiscal por alícuota (sentido sufrido; SOLO Factura A lo provee el caller) + percepciones leídas de `compra->percepciones` → ledger sufrido con la naturaleza del impuesto. `anularDesdeCompra` → contraasiento al cancelar. Guard: sin cuit_id no genera. Idempotente. Tests +5. tenant_tables.sql regenerado.

**HALLAZGO (2026-06-16)**: el módulo de compras está **inconsistente** — los modelos `Compra`/`CompraDetalle` y `CompraService` fueron rediseñados (esperan `numero_comprobante`, `total_iva`, `tipo_comprobante`, `precio_sin_iva`, `tipo_iva_id`…) pero **nunca se migraron**; las tablas reales son el baseline viejo (`numero`, `iva`, sin esos campos). `CompraService::crearCompra` hoy tiraría error. Por eso la capa fiscal se diseñó DESACOPLADA (recibe el desglose explícito, no lee columnas de compras/detalle) y **NO se cableó el hook en `CompraService`** (sería sobre código roto). El hook se agrega cuando se reconcilie/desarrolle compras de verdad: persistir las percepciones en `compra_percepciones`, calcular el IVA crédito (solo Factura A) y llamar a `registrarDesdeCompra`.

**PENDIENTE**: módulo de compras funcional (reconciliar modelo↔tabla, UI con CUIT + sección percepciones + tipo de comprobante, stock/formato), hook al confirmar/cancelar, y la **pantalla de alta manual de movimientos fiscales (RF-08)** con anulación por contraasiento (permisos `func.fiscal.movimientos_*`).

### Fase 7: Posición fiscal + libros [COMPLETO]
`app/Services/Fiscal/PosicionFiscalService.php` (solo lectura): `posicionIva`
(débito − crédito − percep/ret IVA SUFRIDAS = saldo; las percep/ret APLICADAS
como agente se reportan aparte, no integran la posición), `posicionIibb` por
jurisdicción ISO (a-cuenta desde el ledger + base imponible de ventas desde
comprobantes por `sucursal.provincia`, que ya guarda el ISO → reconcilia con el
ledger sin mapeo; **Fase 9 lo cambia a la jurisdicción del domicilio del PV**),
`libroIvaVentas` (desde `comprobantes_fiscales` autorizados,
fuente con detalle por comprobante/alícuota) + `totalesLibroVentas`,
`libroIvaCompras` (desde el ledger `movimientos_fiscales` origen `Compra`, porque
el módulo de compras sigue inconsistente; vacío en la práctica hasta reconciliar
compras). 2 componentes full-page `App\Livewire\Fiscal\{PosicionFiscal,LibrosIva}`
(`#[Lazy]` + skeleton + gate `func.fiscal.{posicion,libros}`) con export CSV
(streamDownload, BOM UTF-8). Rutas `/fiscal/posicion` y `/fiscal/libros`.
Migración `add_fiscal_menu_y_permisos` (pymes): menú top-level "Fiscal" + hijos
"Posición fiscal"/"Libros IVA" (MenuItemObserver crea los `menu.*`) + 4 permisos
funcionales RF-10 (`fiscal.posicion/libros/movimientos/configuracion`), asignados
a Administrador/Super Admin en todos los tenants. Admin-only (no se tocó
ProvisionComercioCommand: su `seedRolesYPermisos` es data-driven → comercios
nuevos quedan provistos). 31 traducciones es/en/pt. Tests: 5 unit
`PosicionFiscalServiceTest` (semántica de signos, saldo a favor, agrupamiento
IIBB, libros) + 4 smoke `SmokeFiscalTest`; suite del área verde (69), Pint OK.
NO se tocaron tablas tenant (no regenerar tenant_tables.sql).

### Fase 9: Domicilios fiscales y jurisdicción de la operación [COMPLETO] — PREREQUISITO DE 5b

> Cerrada 2026-06-18. Los 7 pasos implementados + extras de UI pedidos por el usuario.
> **Hecho**: (1) reseed `localidades` desde GeoRef (3873, con geo; remapeo de `cuits.localidad_id`; CP fuera de la UI) — `database/data/localidades_georef.json` + `LocalidadesSeeder` reescrito + migración `2026_06_18_000001`. (2) Tablas `cuit_domicilios` + `puntos_venta.cuit_domicilio_id` + `sucursales.localidad_id` (migración `..._000002`, tenant_tables.sql regenerado por mysqldump). Modelo `CuitDomicilio` + relaciones (`Cuit hasMany domicilios`, `PuntoVenta belongsTo cuitDomicilio` + helper `jurisdiccionFiscal()`, `Sucursal belongsTo localidad`, `ComprobanteFiscal::jurisdiccionFiscal()`). (3) Migración de datos `..._000003` (domicilio principal por CUIT, PV→principal, backfill localidad sucursal). (4) Trait reutilizable `ManejaDomicilio` + partial `livewire/partials/domicilio-form` (provincia ISO→localidad dependiente + geo). (5) UI: componentes embebidos `CuitDomicilios` + `CuitPuntosVenta` (botones por CUIT, mismo patrón que Impuestos) + domicilio físico de sucursal standalone; selector de domicilio por PV. (6) Jurisdicción fiscal desde el domicilio del PV en `calcularTributos` (ahora recibe `?string $jurisdiccion`) y `posicionIibb` (agrupa por `comprobante->puntoVenta->cuitDomicilio->provincia`, fallback sucursal→AR). (7) Traducciones es/en/pt (30 claves) + smoke tests (80 verdes: SmokeConfiguracion 34 + ImpuestoService + PosicionFiscal).
> **Extras de UI (pedidos en la sesión)**: ABM de PV sacado del modal de alta/edición del CUIT → componente/botón aparte; domicilio fiscal sacado del modal del CUIT (fuente única = `cuit_domicilios`, la card muestra el principal); card de CUIT compactada; barra de botones homogénea y responsive (mobile sin overflow); `wire:confirm` nativo reemplazado por componente `<x-bcn-confirm>` (modal in-app, z-[60]); persistencia de tab por URL (`#[Url] tabActivo`, F5 mantiene pestaña); fix bug preexistente `PuntoVenta::eliminarCertificados()` al borrar CUIT.

#### Implementación original (referencia)
### Fase 9 (plan original): Domicilios fiscales y jurisdicción de la operación — PREREQUISITO DE 5b
> Agregada 2026-06-17 (RF-11). Debe completarse ANTES de la Fase 5b, porque las
> percepciones aplicadas viajan a AFIP con la jurisdicción del PV. Decisiones del
> usuario en "Notas" (2026-06-17).

Orden sugerido de implementación:
1. **Padrón de localidades**: migración que reseed `localidades` (config) desde GeoRef Argentina (provincia→localidad correctas y actuales), **remapeando** `cuits.localidad_id` por (provincia, nombre) para no romper referencias. CP nullable sin uso en UI. Quitar el `(codigo_postal)` del label de `Localidad::paraSelect()` y de los selects (no mostrar dato incorrecto).
2. **Tablas**: migración tenant `cuit_domicilios` + `puntos_venta.cuit_domicilio_id` + `sucursales.localidad_id`. Regenerar `tenant_tables.sql`. Modelos `CuitDomicilio` + relaciones (`Cuit hasMany domicilios`, `PuntoVenta belongsTo cuitDomicilio`, `Sucursal belongsTo localidad` soft).
3. **Migración de datos**: por cada CUIT, crear su `cuit_domicilios` `es_principal` desde `cuits.direccion`+`localidad_id`; cada PV → ese principal; sucursales → backfill `localidad_id` best-effort desde el texto.
4. **Componente de domicilio reutilizable**: trait/Blade compartido (select provincia ISO → select localidad dependiente + lat/lng opcional). Usado por domicilios del CUIT, sucursal y futuros.
5. **UI**: (a) gestión de domicilios por CUIT en tab-cuits de `ConfiguracionEmpresa` (sección "Domicilios", CRUD, marcar principal); (b) selector de domicilio por PV (filtrado a los del CUIT del PV) en la gestión de puntos de venta; (c) **domicilio físico estructurado en la config de sucursales — editable SIN depender de CUIT ni de integración de pago** (provincia/localidad/geo como dato propio de la sucursal, para tienda digital / logística / correctitud); MP reusa los mismos campos vía el componente compartido. Gate: `func.fiscal.configuracion` (ya creado) o el de ConfiguracionEmpresa.
6. **Cambio de jurisdicción en lo fiscal**: `ImpuestoService::calcularTributos` deja de recibir `Sucursal` y recibe la **jurisdicción de la operación** (ISO) resuelta desde el domicilio del PV — actualizar firma + tests (Fase 2). `PosicionFiscalService::posicionIibb` agrupa la base imponible por `comprobante->puntoVenta->cuitDomicilio->provincia` en vez de `sucursal->provincia` — actualizar método + test (Fase 7). Helper común para resolver la jurisdicción desde un comprobante/PV.
7. Traducciones es/en/pt + smoke de las pantallas tocadas.

### Fase 10: Perfil fiscal del cliente + padrones [10a VERIFICADA / 10b IMPLEMENTADA] — refina 5b
> **10b IMPLEMENTADA (2026-06-23, rama `feat/fiscal-fase10b-padrones`)** — D8 RESUELTO con
> los diseños de registro oficiales de ARBA y AGIP (ver [[reference_padron_arba_agip_formato]]).
> Parsers `app/Services/Fiscal/Padron/{PadronParser,AbstractPadronParser,ArbaPadronParser,AgipPadronParser,PadronFila,ResumenImportacion}`
> + `PadronImportService` (streaming `fgets`, filtra por CUIT de clientes, upsert idempotente
> por (cliente,impuesto,vigente_desde), respeta `origen='manual'`, exención conservadora
> 0,00/baja⇒exento) + componente full-page `Fiscal\PadronImport` (upload + selector agencia +
> resumen, **panel de aclaraciones en la UI**) bajo `fiscal.padrones` (permiso reusa
> `func.fiscal.configuracion`, menú nuevo). Mapeo: ARBA→`perc_iibb_ar_b`, AGIP→`perc_iibb_ar_c`.
> Tests: 9 unit parsers + 5 integración service + 2 smoke (16 verdes; 122 en el área fiscal).
> Traducciones es/en/pt (23 claves). Techo de upload Livewire subido a 100MB. **Pendiente:
> validación en vivo del usuario con archivos reales de cada agencia + docs (Fase 8) + PR.**
> **10a VERIFICADA (/sdd-verify APROBADO, 2026-06-19)**: 10/10 criterios en alcance con
> test que pasó (57 tests verdes) + matriz completa validada EN VIVO por el usuario
> (emisión AFIP homologación OK, exento/padrón/D7/consumidor final, mixto con 2 FP fiscales).
> **Bug AFIP 10051 detectado y corregido durante la validación**: en facturas con descuento/
> recargo por forma de pago, `formatearDesgloseParaAFIP`/`recalcularDesgloseIvaFiscal`
> (WithPagosDesglose, código compartido Ventas+Pedidos) tomaban el `neto` SIN el ajuste FP
> mientras el total SÍ lo incluía → el residuo deformaba el IVA (IVA ≠ neto×alícuota). Fix:
> usar `neto_con_ajuste_fp`/`iva_con_ajuste_fp`. Era PREEXISTENTE de 5b (cualquier FC con
> ajuste FP fallaba), la percepción solo lo hizo visible. + percepción visible en el listado
> de ventas (accessor `VentaPago::percepcion` derivado, sin columna nueva).
> **10a COMPLETA (2026-06-19)**, rama `feat/fiscal-fase10-perfil-cliente`. Implementado:
> migraciones (`add_domicilio_fiscal_a_clientes`, `create_cliente_impuesto_configs_table`,
> `add_percibir_no_empadronados_a_cuit_impuesto_configs`) + modelo `ClienteImpuestoConfig`
> + relaciones `Cliente::impuestoConfigs()`/`localidad()` + domicilio fiscal en el form de
> cliente (trait `ManejaDomicilio`, partial con flags `conGeo`/`provinciaRequerida` nuevos)
> + componente embebido `Clientes\ClienteImpuestos` (evento `abrir-impuestos-cliente`, botón
> por fila en móvil y desktop) + flag D7 `percibir_no_empadronados` en `CuitImpuestos` (Fase 3)
> + **cambio de firma `calcularTributos(?CondicionIva → ?Cliente)`** con lógica IIBB por sujeto
> (exento / alícuota override / D7) y base mínima del cliente. Tests: 6 nuevos en
> `ImpuestoServiceTest` (49 verdes), 2 smoke `ClienteImpuestos`, `PercepcionFiscalVentaTest`
> actualizado a D7. Traducciones es/en/pt (13 claves). `tenant_tables.sql` regenerado.
> **10b PENDIENTE**: importador de padrón ARBA/AGIP (RF-14), bloqueado por D8 (archivo real).
> **Pendiente verificación en vivo del usuario** + `/sdd-verify` + docs (Fase 8) + PR.

> Agregada 2026-06-18, detallada 2026-06-19. Surge de la pregunta del usuario: la
> percepción de IIBB depende también del CLIENTE, no solo del agente (ver
> [[project-iibb-percepcion-depende-del-cliente]]). La Fase 5b (YA MERGEADA, PR #136)
> percibe con **alícuota fija** por jurisdicción del agente, a todo RI → riesgo de
> **sobre-percibir** a clientes exentos o con alícuota de padrón menor. Fase 10 mete
> al cliente en la ecuación: provincia/localidad del cliente + perfil de percepción
> por sujeto (manual + padrón ARBA/AGIP). Consumidor único: `calcularTributos` (la
> emisión de 5b, ya cableada).

**Decisión de alcance (2026-06-19, con el usuario):**
- **Percepción de IVA = AUTOMÁTICA**, NO se configura por cliente (igual que en
  `cuit_impuesto_configs` el IVA débito/crédito no se gestiona: lo determina la
  condición de IVA). La alícuota de percepción de IVA es nacional y fija; se aplica
  a RI según la config del agente. Queda EXCLUIDA del perfil del cliente.
- **IIBB y demás percepciones provinciales = CONFIGURABLES por cliente** (dependen
  de jurisdicción + padrón + exención del sujeto).
- **Varios impuestos por cliente** (no un solo porcentaje): un cliente puede tener
  percepción de IIBB de una jurisdicción y otra percepción a la vez.
- Alcance COMPLETO: override **manual** + **importador de padrón** ARBA/AGIP.

#### RF-12: Domicilio fiscal del cliente
- Columnas nuevas en `clientes` (tenant): `provincia` (varchar(6) NULL, ISO 3166-2) +
  `localidad_id` (bigint unsigned NULL, ref soft a `localidades` config). Hoy el
  cliente solo tiene `cuit` (string) + `direccion` (texto libre) — ver
  `app/Models/Cliente.php` fillable.
- Reusar el trait `App\Traits\ManejaDomicilio` + partial
  `resources/views/livewire/partials/domicilio-form.blade.php` (ya probados en CUIT
  y sucursal en Fase 9) en el form de alta/edición de clientes.
- Define la **jurisdicción destino** de la operación (para el match de IIBB) y el
  fallback cuando el padrón no resuelve.

#### RF-13: Perfil fiscal del cliente (`cliente_impuesto_configs`)
- Tabla nueva tenant `cliente_impuesto_configs`, **espejo** de `cuit_impuesto_configs`
  (`database/migrations/2026_06_12_150001_create_cuit_impuesto_configs_table.php`)
  pero por cliente y con semántica de SUJETO PERCIBIDO (no de agente):
  | Campo | Tipo | Notas |
  |-------|------|-------|
  | `id` | bigint PK | |
  | `cliente_id` | bigint FK→clientes ON DELETE CASCADE | |
  | `impuesto_id` | bigint FK→impuestos | |
  | `exento` | tinyint(1) default 0 | si true ⇒ NO se le percibe este impuesto |
  | `alicuota` | decimal(6,4) NULL | % a percibir (override del fijo del agente) |
  | `alicuota_minimo_base` | decimal(12,2) NULL | umbral de base imponible |
  | `numero_padron` | varchar(30) NULL | N° de inscripción/constancia del sujeto |
  | `origen_alicuota` | enum('manual','padron') default 'manual' | |
  | `vigente_desde` / `vigente_hasta` | date NULL | del período del padrón en import |
  | `datos_extra` | json NULL | fila cruda del padrón (trazabilidad, solo origen padrón) |
  | timestamps | | |
  | UNIQUE | (`cliente_id`,`impuesto_id`,`vigente_desde`) | igual que el espejo |
  - **Diferencias con `cuit_impuesto_configs`**: se quitan `es_agente_percepcion`/
    `es_agente_retencion` (el cliente no es agente en nuestro sistema) y se agrega
    `exento` (marca explícita de no-percibir, para exentos / con certificado).
  - Modelo `App\Models\ClienteImpuestoConfig` (conexión tenant) + scope `vigentes()`
    (copiar el de `CuitImpuestoConfig:74`) + relaciones `cliente()`/`impuesto()`.
  - Relación `Cliente::impuestoConfigs(): HasMany`.

#### RF-14: Importador de padrón ARBA/AGIP
- Pantalla/acción de importación de archivos de padrón (CSV/TXT). Parseo con el patrón
  del proyecto (`str_getcsv` + `preg_split`, detección dinámica de separador — ver
  `MercadoPagoGateway::parsearCsvReporteCuenta`), **un parser por agencia** (ARBA,
  AGIP; extensible). Estrategia/interface `PadronParser` con impl por agencia.
- Flujo: archivo → filas normalizadas `[cuit, impuesto/jurisdicción, alícuota_percepción,
  vigencia]` → match por CUIT contra `clientes` del comercio → **upsert** en
  `cliente_impuesto_configs` con `origen_alicuota='padron'`.
- **Precedencia: el override manual gana.** La importación NO pisa filas con
  `origen_alicuota='manual'` (solo crea/actualiza las `padron`).
- El padrón trae tasas de percepción Y retención por sujeto; **acá solo se usa la de
  percepción** (la retención IIBB a proveedores es del lado compras, fuera de alcance).
- Idempotente por (cliente_id, impuesto_id, vigente_desde). Log de filas sin match
  (CUIT no es cliente) sin abortar la corrida.

#### RF-15: Acople con el motor (`calcularTributos`)
- **Cambio de firma (una sola vez)**: `calcularTributos(Cuit $emisor, ?CondicionIva
  $receptor, ...)` → `calcularTributos(Cuit $emisor, ?Cliente $receptor, ...)`
  (`app/Services/Fiscal/ImpuestoService.php:221`). Internamente usa
  `$receptor?->condicionIva` para el gate RI. Actualizar el caller de 5b
  (`calcularPercepcionesComprobante` ya recibe `Cliente` — pasa a delegar directo) y
  los tests de `ImpuestoServiceTest` (hoy pasan `CondicionIva`).
- **Lógica por impuesto:**
  - **Percepción de IVA**: automática — si el emisor es agente de `perc_iva` y el
    receptor es RI ⇒ aplica la alícuota fija del agente (comportamiento 5b actual,
    sin tocar). NO mira `cliente_impuesto_configs`.
  - **Percepción de IIBB**: resolver contra `cliente_impuesto_configs` del receptor
    para el impuesto de la jurisdicción de la operación:
    - config con `exento=true` ⇒ NO percibe.
    - config con `alicuota` (manual o padrón) ⇒ usa esa (alícuota por sujeto).
    - sin config ⇒ **DECISIÓN ABIERTA D7** (ver abajo).
  - Respeta `alicuota_minimo_base` (del cliente si está, si no del agente).

#### Pantallas UI (Fase 10)
- **Domicilio en el form de cliente**: incluir el partial `domicilio-form` (provincia
  ISO → localidad dependiente) en el alta/edición de clientes.
- **Impuestos del cliente**: componente/modal espejo de `CuitImpuestos` (Fase 3),
  abierto desde un botón "Impuestos" por fila de cliente. Combobox de alta sobre el
  catálogo **excluyendo tipo IVA y naturalezas débito/crédito fiscal** (solo IIBB y
  otras percepciones); lista editable (exento, alícuota, base mínima, N° padrón,
  vigencia, origen). Permiso: reusa el gate de gestión de clientes o
  `func.fiscal.configuracion`.
- **Importador de padrón**: pantalla bajo Fiscal (o Configuración) con upload de
  archivo + selección de agencia (ARBA/AGIP) + período/vigencia + resumen de la
  corrida (filas importadas, sin match, actualizadas). Permiso `func.fiscal.configuracion`.

#### Migraciones (Fase 10, orden)
1. `add_domicilio_fiscal_a_clientes`: `clientes.provincia` + `clientes.localidad_id` (tenant). Regenerar `tenant_tables.sql`.
2. `create_cliente_impuesto_configs_table` (tenant). Regenerar `tenant_tables.sql`.
3. (sin migración de menú si el importador va dentro de un componente ya enrutado; si es pantalla nueva → item de menú + permiso, como RF-08).

#### Decisiones ABIERTAS (resolver durante la implementación)
- **D7 — cliente sin entrada en padrón ni config manual** → **RESUELTA (2026-06-19,
  usuario): flag configurable por agente.** Agregar a `cuit_impuesto_configs` (la
  config del AGENTE) la columna `percibir_no_empadronados` (tinyint(1) default 0).
  Semántica en `calcularTributos`, percepción IIBB, receptor RI sin
  `cliente_impuesto_configs` para ese impuesto:
  - agente con `percibir_no_empadronados=true` ⇒ percibe a su `alicuota` fija (la del
    agente, comportamiento 5b).
  - agente con `percibir_no_empadronados=false` (DEFAULT seguro) ⇒ NO percibe.
  Así el usuario decide por CUIT/jurisdicción si quiere percibir a no empadronados. La
  alícuota default es la `alicuota` ya existente del agente (no se agrega otra). Requiere
  migración que agregue la columna a `cuit_impuesto_configs` + casteo en el modelo +
  exponerla en la UI de `CuitImpuestos` (Fase 3).
- **D8 — formato real de los archivos de padrón ARBA/AGIP** → **RESUELTA (2026-06-23)**:
  se obtuvieron los **diseños de registro oficiales** de ambas agencias (PDF). Ambos son
  `;`-separados, CUIT 11 díg sin guiones, alícuota `9,99` (coma decimal), fechas `DDMMAAAA`.
  ARBA: archivos percepción/retención separados (`PadronRGSPerMMAAAA.txt`), 10 campos, campo
  Régimen R/P. AGIP: padrón unificado, 12 campos, ambas alícuotas + razón social. Layout
  completo en [[reference_padron_arba_agip_formato]]. La fila cruda se conserva en
  `datos_extra` para trazabilidad. Falta solo confirmar contra archivos reales en vivo.

#### Criterios de aceptación (Fase 10)
- Un cliente RI con domicilio en AR-B y `cliente_impuesto_configs` exento de perc IIBB
  AR-B ⇒ una venta facturada NO le percibe IIBB (pero sí percepción de IVA si aplica).
- Un cliente con alícuota de padrón 1.5% (origen padrón) ⇒ se percibe 1.5%, no la fija
  del agente.
- Importar un padrón no pisa las configs manuales existentes.
- Cambiar la firma de `calcularTributos` no rompe 5b (comprobantes siguen cerrando) ni
  los tests existentes (actualizados).
- Percepción de IVA sigue siendo automática (no aparece en el perfil del cliente).

#### Sub-fases de implementación (corte sugerido)
- **10a (manual, sin padrón)**: migraciones + modelo + domicilio en cliente + UI de
  `cliente_impuesto_configs` + cambio de firma de `calcularTributos` + lógica IIBB con
  lookup del cliente (D7 resuelto). Entrega el refinamiento de 5b sin depender de D8.
- **10b (padrón)**: importador ARBA/AGIP (depende de D8 / archivo real).

#### Puntos críticos (revisión 2026-06-19 contra el código real)
> Pasada de revisión cruzando el spec con el código existente, antes de implementar.

1. **El motor SOLO percibe IVA + IIBB.** `calcularTributos`
   (`ImpuestoService.php:221`) filtra `impuesto->tipo IN [TIPO_IVA, TIPO_IIBB]` y
   `naturaleza_default = percepcion`. Por lo tanto "los demás impuestos configurables
   por cliente" = **IIBB** en v1 (no hay otra percepción que el motor calcule). El
   `cliente_impuesto_configs` gobierna IIBB; soportar otras percepciones provinciales
   exigiría extender el motor (fuera de alcance v1, anotar).
2. **Cambio de firma — blast radius CONFIRMADO y acotado** (bajo riesgo):
   - `ImpuestoService.php:221` firma `?CondicionIva` → `?Cliente`.
   - `ImpuestoService.php:243` el gate del receptor: `$receptor->codigo` →
     `$receptor->condicionIva?->codigo`.
   - `ImpuestoService.php:331` wrapper `calcularPercepcionesComprobante`: pasar
     `$cliente` directo (no `$cliente?->condicionIva`).
   - `WithPagosDesglose::calcularTributosFiscales:342` y `NuevaVenta:1311` NO cambian
     (van por el wrapper).
   - **18 tests** en `ImpuestoServiceTest` a migrar (CondicionIva → helper `cliente()`);
     el caso `null` (línea 191) queda igual. El test Livewire `PercepcionFiscalVentaTest`
     NO cambia. Hacerlo en un solo commit con la firma.
3. **Lookup del cliente = refinamiento, no trigger.** El motor itera las configs del
   AGENTE (`emisor->impuestoConfigs`); para cada IIBB cuya jurisdicción matchea la
   operación, busca en `cliente_impuesto_configs` por el **mismo `impuesto_id`**:
   - `exento=true` ⇒ no percibe;
   - con `alicuota` ⇒ usa la del cliente (padrón/manual) en vez de la del agente;
   - sin config del cliente ⇒ aplica **D7** (`percibir_no_empadronados` del agente).
   La percepción de IVA (tipo IVA) NO consulta al cliente (automática, comportamiento 5b).
   `percibir_no_empadronados` aplica **solo a IIBB**.
4. **Performance / N+1**: el motor corre reactivo en cada recálculo de venta. Hoy
   `calcularTributosFiscales` hace `Cliente::find()` pelado. Al pasar `?Cliente`, cargar
   con `Cliente::with(['condicionIva', 'impuestoConfigs.impuesto'])->find(...)` para
   evitar N+1 en el loop de configs.
5. **Gotcha FK string en el componente espejo**: `ClienteImpuestos` debe usar
   `whereKey($id)` (no `where('id', $id)` con `===`) para update/delete, igual que
   `CuitImpuestos:178,212` (ver [[project-gotcha-fk-string-comparacion]]).
6. **Catálogo del selector (cliente)**: excluir naturalezas `debito_fiscal`/
   `credito_fiscal` (como `CuitImpuestos:128`) **y además** `tipo = iva` (la percepción
   de IVA es automática). Quedan IIBB y percepciones no-IVA.
7. **Padrón (10b)**:
   - Match por **CUIT normalizado** (sin guiones/espacios) contra `clientes.cuit`
     (nullable); clientes sin CUIT no matchean (no son percibibles).
   - `vigente_desde`/`vigente_hasta` salen del **período del padrón** (el usuario lo
     elige al importar); el scope `vigentes()` del motor ya filtra por fecha.
   - Agregar `datos_extra` (json null) a `cliente_impuesto_configs` para conservar la
     fila cruda del padrón (trazabilidad, como `conciliacion_filas`).
   - **Parser por agencia** (interface `PadronParser`); NO reusar el parser privado de
     `MercadoPagoGateway` (acoplaría a MP) — sí reusar la TÉCNICA (str_getcsv, BOM,
     detección de separador). Formato exacto = **D8** (archivo real).
8. **Upsert idempotente del padrón**: `updateOrCreate` por
   (`cliente_id`,`impuesto_id`,`vigente_desde`) con `origen_alicuota='padron'`; **NO
   pisar filas `origen_alicuota='manual'`** (filtrarlas antes del upsert).
9. **Limitación v1 conocida (REVISAR)**: no se modela la **exclusión de percepción de
   IVA por cliente** (certificado de exclusión RG): el IVA es automático a todo RI. Un
   cliente con certificado de no-percepción de IVA hoy requeriría corrección por alta
   manual (RF-08). Anotar como deuda.
10. **GestionarClientes es SucursalAware** pero el domicilio fiscal es atributo
    **global** del cliente (tabla `clientes`, no el pivot `clientes_sucursales`). El
    partial `domicilio-form` se inserta en su modal (después de "Dirección"). El alta
    rápida del carrito (`WithBusquedaClientes::guardarClienteRapido`) NO incorpora
    domicilio estructurado (queda con `direccion` texto).

### Fase 8: Verificación + docs [PENDIENTE]
/sdd-verify, @docs-sync, manual de usuario.

---

## Revisión pendiente (pasada de Fable)

> El desarrollo se hizo con Opus mientras Fable estaba deshabilitado.
> **PASADA DE FABLE REALIZADA el 2026-07-01** (rama feat/fiscal-revision-fable):
> se auditaron todos los tags `REVISAR (Fable)` + este checklist. Resultado:
> la mayoría CONFIRMADOS correctos, 2 bugs nuevos encontrados y corregidos
> (NC cross-período y precedencia manual/padrón) + 3 profundizaciones
> (exclusión IVA por certificado, monto mínimo de percepción, desglose de
> ingresos IIBB). Quedan abiertas solo preguntas normativas para el contador.

**Fase 2 — `ImpuestoService::calcularTributos`:**
- [ ] **(CONTADOR)** Matriz "receptor RI": correcta para percepción de IVA, pero para IIBB los padrones ARBA/AGIP incluyen monotributistas inscriptos en IIBB — el gate por condición de IVA los saltea aunque estén empadronados con alícuota. Relajarlo re-abre D6: decidir con el contador si para IIBB el gate pasa a ser "tiene config/padrón vigente" en vez de "es RI".
- [x] Monto mínimo de percepción: RESUELTO (2026-07-01, Fable) — campo `monto_minimo_percepcion` en `cuit_impuesto_configs` (migración 2026_07_01) aplicado sobre el importe resultante + UI. El "monto no sujeto" (deducción de base, típico de RETENCIONES que el sistema no calcula) queda fuera de alcance a propósito.
- [ ] **(CONTADOR/futuro)** Convenio Multilateral: sigue diferido (reparto de base por coeficiente). El padrón del receptor YA refina la alícuota (Fase 10).
- [x] Condición del emisor: RESUELTO (2026-06-16) — gate RI del emisor. Test `test_no_percibe_si_el_emisor_no_es_responsable_inscripto`.
- [x] Fecha de la operación: RESUELTO Y CONFIRMADO (2026-07-01, Fable) — el carrito pasa `now()` al cobrar (`WithPagosDesglose::calcularTributosFiscales`) y el comprobante se emite en el mismo acto; la NC copia los tributos del original sin recalcular (correcto).
- [x] Redondeo por tributo: CONFIRMADO (2026-07-01, Fable) — WSFEv1 trabaja con 2 decimales por tributo; `ImpTrib` se arma sumando los montos ya redondeados (consistente con lo cobrado).
- [x] Convención alícuota = porcentaje: CONFIRMADO (2026-07-01, Fable) — el campo `Alic` de ARCA es porcentaje; validado en vivo en 5b.
- [x] **BUG (encontrado y corregido 2026-07-01, Fable)**: precedencia manual vs padrón del perfil del cliente — coexistían vigentes la fila manual (`vigente_desde` null) y la del padrón (fechada) y el `keyBy` se quedaba con la del padrón (última por PK), ignorando el override del contador. Fix: desempate explícito manual > padrón > vigencia más reciente; ídem dedup de configs solapadas del emisor (percibía dos veces). Tests `test_config_manual_del_cliente_gana_sobre_la_del_padron`, `test_configs_solapadas_del_emisor_no_duplican_la_percepcion`.
- [x] Exclusión de percepción de IVA por certificado (RG 2226): RESUELTO (2026-07-01, Fable) — perfil fiscal del cliente `exento` sobre el impuesto de percepción de IVA ⇒ no se percibe (antes la percepción de IVA era incondicional). Salda la limitación v1 anotada en Fase 10.

**Fase 3 — config UI por CUIT (todos cerrados 2026-07-01, Fable):**
- [x] Historial de vigencias sin UI: ACEPTADO v1 — los movimientos persisten los valores calculados al operar; editar una config no re-escribe historia.
- [x] `numero_inscripcion` vs `cuits.numero_iibb`: NO es redundancia dañina — `cuits.numero_iibb` es el número que se muestra/imprime (sede/CM); `numero_inscripcion` es el detalle por impuesto-jurisdicción.
- [x] Permisos del componente embebido: ACEPTADO — solo lo renderiza ConfiguracionEmpresa (gateada) y las acciones Livewire requieren componente renderizado.

**Fase 7 — posición fiscal / libros:**
- [x] Base IIBB: RESUELTO (2026-07-01, Fable) — `posicionIibb` ahora desglosa gravado / no gravado / exento + ingresos totales por jurisdicción (vista + CSV); qué componentes integran la base lo decide el contador con las columnas a la vista.
- [ ] **(CONTADOR/futuro)** Convenio Multilateral (ídem Fase 2).
- [ ] Presentación libro vs posición para CUITs monotributo (libro muestra comprobantes, posición 0): pendiente menor de UX, sin riesgo fiscal.
- [ ] Export CSV "simplificado" (no formato Libro IVA Digital de ARCA): fase futura.

**Fase 2 — ledger / contraasiento:**
- [x] **BUG (encontrado y corregido 2026-07-01, Fable — el hallazgo más importante de la pasada)**: la NC anulaba retroactivamente los movimientos del comprobante original en el período ORIGINAL. Una venta de junio anulada el 1° de julio le borraba el débito a junio (que puede estar ya declarado) y rompía la reconciliación libro↔posición (el Libro IVA computa la NC en julio). Fix: la NC registra SUS PROPIOS movimientos activos con monto NEGATIVO en SU período (`registrarDesdeComprobante`); `anularMovimientoFiscal` queda solo para corrección de errores de carga (retro correcto). La posición suma montos, así que las filas negativas restan sin cambios aguas abajo. Con esto la "NC parcial" tampoco necesita `revertirParcial()`: si algún día existe, sus propios detalles salen en negativo. Tests `test_nota_de_credito_registra_debito_negativo_en_su_propio_periodo`, `test_posicion_iva_resta_las_reversas_negativas_de_nc`. NOTA: NCs históricas (pre-fix) quedaron registradas con el esquema viejo (anulación retro); no se migran.
- [x] Contrato de posición "solo activos": CONFIRMADO (2026-07-01, Fable) con la corrección de arriba — anulación (error de carga) = retro por estado; reversa de NC = fila negativa en su período. Los dos ejes conviven bien.
- [x] `configVigente` con vigencias solapadas: CONFIRMADO (2026-07-01, Fable) — desempate determinístico y razonable; además `calcularTributos` ahora deduplica por impuesto con la misma regla (antes duplicaba la percepción).
- [ ] **(futuro, Fase 6)** `anularDesdeCompra` sigue siendo anulación retro total: cuando el módulo de compras se desarrolle, la cancelación cross-período debe seguir el patrón de la NC (reversa negativa en su período).

## Notas y Decisiones

- 2026-06-12 (Fase 1): descubierto y reparado de paso un bug del PR #132 ya mergeado — la migración `140001_add_tipo_impuesto` alteraba `conciliaciones_filas` pero la tabla real es `conciliacion_filas` (singular): el try/catch tenant se tragó el error y el enum quedó sin `impuesto` en comercios existentes. Fix: migración `150006` re-aplica el ALTER con el nombre correcto. Lección: tras una migración tenant, verificar el efecto en la BD real (el patrón try/catch convierte errores en no-ops silenciosos).
- 2026-06-12 (Fase 1): los COMMENT de columnas en tenant_tables.sql NO pueden contener `;` — el provisioning de tests splitea el SQL por punto y coma.
- 2026-06-12 (Fase 1): el catálogo canónico de impuestos vive en `create_impuestos_table::catalogo()` (estático); ProvisionComercioCommand lo consume vía `require` para no duplicar las 56 filas.

- 2026-06-12: D1-D4 decididas con el usuario (ver Contexto).
- 2026-06-12: ~~La jurisdicción de la operación se infiere de la PROVINCIA de la sucursal del comprobante.~~ **REVISADO 2026-06-17 (RF-11/Fase 9)**: la jurisdicción se infiere del **domicilio fiscal del punto de venta** del comprobante (`comprobante.puntoVenta.cuitDomicilio.provincia`), no de la sucursal física — una caja puede facturar con un PV cuyo CUIT está domiciliado en otra provincia.

- 2026-06-17 (RF-11, con el usuario): se decide modelar **domicilios fiscales por CUIT** (espejo AFIP: N domicilios por CUIT, cada PV asignado a uno) ANTES de la Fase 5b. Decisiones: (1) la jurisdicción de la operación sale del domicilio del PV (física y fiscal "deberían coincidir", pero la fiscal manda). (2) Modelo AFIP completo (CUIT→N domicilios + PV→domicilio), no atajo de un domicilio por CUIT. (3) La palanca de "una caja factura con distintos CUITs" YA existe (pivot `punto_venta_caja` + PV→CUIT); no se toca. En AFIP un PV pertenece a UN solo CUIT. (4) Domicilio estructurado y un **componente reutilizable** compartido por CUIT/sucursal/futuros (no tabla polimórfica: `cuit_domicilios` propia + columnas estructuradas en sucursal + componente común). (5) **CP**: se quita del display (el padrón actual tiene errores, ej. Mercedes BA 6100 vs 6600 real); se difiere como campo editable por domicilio si a futuro hace falta imprimir/declarar. (6) Padrón `localidades` reemplazado por GeoRef Argentina (oficial/actual), remapeando referencias existentes. (7) Migrar el domicilio único actual de cada CUIT como su principal y asignar los PV existentes a él.
- 2026-06-12: El formato exacto del Libro IVA Digital de ARCA queda para fase futura; fase 1 exporta CSV simplificado para el contador.
- 2026-06-12: Padrones provinciales (ARBA/AGIP): estructura preparada (`origen_alicuota`), implementación fuera de alcance.
- 2026-06-12 (D5, usuario): retenciones que aplican CLIENTES agentes al pagar → **alta manual (RF-08) por ahora**; si a futuro resulta frecuente se integra al flujo de cobranza como fase nueva.

- 2026-06-16 (Fase 2, D6 usuario): `calcularTributos` v1 **conservador** — una percepción se aplica solo si el CUIT emisor es agente de percepción (config vigente, inscripto, con alícuota) Y el receptor es Responsable Inscripto. Percepción IVA nacional: sin condicionar jurisdicción. Percepción IIBB provincial: solo si `impuesto.jurisdiccion === sucursal.provincia` (la operación). El match fino por provincia del RECEPTOR queda para la fase de padrones (no tenemos ese dato confiable hoy). Consumidor final / monotributo / exento / receptor null → sin percepción. Respeta `alicuota_minimo_base`. Si a futuro alguna jurisdicción exige percibir a monotributistas, se amplía.
- 2026-06-16 (Fase 2): convención de alícuota = **porcentaje** (ej. 3.0000 = 3%), consistente con ComprobanteFiscalIva → `monto = base * alicuota / 100`.
- 2026-06-16 (Fase 2): contraasiento fiscal — el modelo NO tiene eje con signo (a diferencia de MovimientoCuentaEmpresa); `anular` marca el original `estado=anulado` y crea una reversa linkeada (`movimiento_anulado_id`, `estado=anulado`) como traza inmutable. La posición fiscal sumará **solo `estado=activo`** → la anulación saca limpio el original sin aritmética con signo (monto siempre positivo). Guarda contra doble anulación y contra anular un contraasiento.
- 2026-06-16 (Fase 2): se agregaron `movimientos_fiscales`/`cuit_impuesto_configs`/`impuestos` a `WithTenant::$testTables` (limpieza entre tests). Las tablas fiscales ya estaban en `tenant_tables.sql` (Fase 1) pero el tenant de test persiste de antes → la primera corrida necesita `TEST_FORCE_RECREATE=1`.
