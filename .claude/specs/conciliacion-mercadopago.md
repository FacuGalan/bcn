# Conciliación de Cuenta con el Proveedor de Pago (MP) - Especificación

## Estado: APROBADO (2026-06-12, usuario aprobó decisiones y diseño completo)

> Paso 3 (final) del roadmap de integraciones-pago. Concilia el ledger de la
> `CuentaEmpresa` vinculada (Paso 2, PR #131) contra los movimientos REALES de la
> cuenta del proveedor, obtenidos vía API de Reportes (MP: reporte "Dinero en
> cuenta" / account-money). Genera los movimientos que el sistema no ve
> (comisiones, retiros, devoluciones, acreditaciones/rendiciones) para que el
> saldo del sistema converja al saldo real.
>
> Como en los Pasos 1-2: **genérico por contrato** — lo único MP-específico vive
> en `MercadoPagoGateway`.

---

## Contexto y Motivación

El Paso 2 dejó el ledger registrando el ingreso BRUTO de cada cobro por integración
(concepto `cobro_integracion`, origen `IntegracionPagoTransaccion`) en la cuenta
real del proveedor. Pero el saldo vivo de MP difiere del saldo del sistema porque:

- MP acredita el **neto** (descuenta comisión + IVA por cada cobro).
- Hay **retiros** a banco, **devoluciones/contracargos** y **acreditaciones**
  que no nacen en el sistema (rendiciones de plataformas, transferencias recibidas,
  pagos por fuera del sistema).
- D11 (Paso 2): no hubo backfill — la diferencia inicial existe por diseño.

La conciliación cierra esa brecha: pide el reporte de movimientos de la cuenta al
proveedor por período, lo matchea contra el ledger y propone los movimientos
faltantes. El usuario revisa y aplica.

**Decisiones del usuario (2026-06-12, sesión de inicio del Paso 3):**
1. Conciliar contra el **saldo TOTAL** de la cuenta MP → reporte *account-money*
   (no Liquidaciones: ese mide solo el dinero liberado).
2. Disparo **manual** (botón en la cuenta) + corrida **programada opcional**
   activable/desactivable por cuenta (default: desactivada).
3. Comisión como **egreso por cada cobro** matcheado (granular) **y** las
   acreditaciones/rendiciones de MP generan su **ingreso** correspondiente.
4. **Pantalla de revisión antes de aplicar** (el usuario confirma los ajustes).

---

## Principios de Diseño

1. **Genérico por contrato**: nueva capacidad `obtenerMovimientosCuenta()` en
   `IntegracionPagoGatewayContract` que devuelve filas **normalizadas**
   provider-agnostic. Todo lo MP-específico (endpoints, CSV, columnas,
   TRANSACTION_TYPE) vive en `MercadoPagoGateway`.
2. **La conciliación es POR CuentaEmpresa** (identidad `subtipo +
   identificador_externo`), no por sucursal ni por config: una cuenta MP
   compartida por N sucursales/configs se concilia UNA vez. El access token se
   resuelve buscando cualquier config prod activa cuya identidad coincida.
3. **Solo producción** (D1 del Paso 2): solo se concilian cuentas con
   `identificador_externo` y al menos una config prod resoluble.
4. **Revisión antes de aplicar**: la corrida queda `pendiente_revision` con el
   detalle clasificado; nada toca el ledger hasta que el usuario aplica. La
   corrida programada también queda pendiente de revisión (no auto-aplica).
5. **Append-only + idempotente**: aplicar genera `MovimientoCuentaEmpresa` vía
   `registrarMovimientoAutomatico()` con origen polimórfico `ConciliacionFila`.
   Re-conciliar un período solapado NO duplica: una fila del proveedor ya
   registrada para esa cuenta (mismo `tipo + id_externo`) se marca
   `ya_registrado` y no vuelve a generar movimiento.
6. **Asíncrono sin bloquear la UI**: el reporte de MP se genera de forma
   asíncrona (202). La corrida avanza por estados con un comando que corre en el
   scheduler (patrón `precios:procesar-programados`); la UI refresca con
   `wire:poll` mientras está `generando`.
7. **El sistema nunca inventa plata**: filas "en el sistema pero no en el
   proveedor" se reportan como alerta y NO generan ajuste automático.

---

## Requisitos Funcionales

### RF-01: Capacidad de reporte en el contrato (seam de extensibilidad)
- Nuevo método en `IntegracionPagoGatewayContract`:
  `obtenerMovimientosCuenta(IntegracionPagoSucursal $config, \DateTimeInterface $desde, \DateTimeInterface $hasta): ?array`
- Devuelve `null` si el proveedor no soporta reportes de cuenta, o un array de
  filas normalizadas:
  `['tipo','id_externo','referencia','fecha','descripcion','monto_bruto','comision','monto_neto']`
  - `tipo` normalizado: `cobro | devolucion | contracargo | retiro | retiro_cancelado | acreditacion | otro`
  - `id_externo`: id de la operación en el proveedor (MP: `SOURCE_ID`)
  - `referencia`: referencia externa que el sistema seteó al cobrar (MP: `EXTERNAL_REFERENCE`)
- Por la mecánica asíncrona de MP, el contrato se materializa en DOS llamadas
  (ver RF-02): `solicitarReporteCuenta()` y `obtenerReporteCuenta()`; un gateway
  síncrono puede resolver `solicitar` como no-op.

### RF-02: Implementación MP (reporte account-money)
- `MercadoPagoGateway::solicitarReporteCuenta($config, $desde, $hasta): string` —
  `POST /v1/account/settlement_report` con `begin_date/end_date` (token prod);
  devuelve un identificador de solicitud (o el rango pedido para buscar en el listado).
- `MercadoPagoGateway::obtenerReporteCuenta($config, $solicitud): ?array` —
  `GET /v1/account/settlement_report/list` → si el reporte del rango está listo,
  `GET /v1/account/settlement_report/{file_name}` (CSV) → parsear y normalizar:
  - `SETTLEMENT` → `cobro`; `REFUND` → `devolucion`; `CHARGEBACK`/`DISPUTE` →
    `contracargo`; `WITHDRAWAL`/`PAYOUT` → `retiro`; `WITHDRAWAL_CANCEL` →
    `retiro_cancelado`; créditos sin tipo conocido → `acreditacion`; resto → `otro`.
  - Devuelve `null` si todavía no está listo (la corrida sigue esperando).
- Config del reporte (una vez por cuenta, `POST/PUT /v1/account/settlement_report/config`):
  columnas necesarias + `include_withdraw=true` + `display_timezone` ART.
- Errores de API → excepción legible (patrón `probarConexion()` existente).

### RF-03: Corrida de conciliación (modelo + máquina de estados)
- Nueva entidad `ConciliacionCuenta` (tabla tenant): cuenta, período
  (`desde`/`hasta`), estado, totales, quién/cómo se disparó.
- Estados: `generando` → `pendiente_revision` → `aplicada` | `descartada`;
  `error` (terminal, con mensaje; reintientable creando una corrida nueva).
- Solo UNA corrida activa (`generando`/`pendiente_revision`) por cuenta a la vez.
- Validación: la cuenta debe tener `identificador_externo` y una config prod
  activa resoluble por identidad (si no, no se puede conciliar).

### RF-04: Avance asíncrono — comando programado
- Comando `conciliaciones:procesar` (scheduler cada minuto, itera comercios con
  TenantService manual, patrón existente):
  1. Corridas `generando` sin reporte solicitado → `solicitarReporteCuenta()`.
  2. Corridas `generando` con solicitud → `obtenerReporteCuenta()`; si está
     listo → ejecutar match (RF-05) → `pendiente_revision`. Si falla → `error`.
  3. Cuentas con conciliación automática activa cuya corrida diaria no existe →
     crear corrida del día anterior (RF-08).
- Timeout: corrida `generando` por más de 60 min → `error` ("MP no generó el reporte").

### RF-05: Match y clasificación
- Universo sistema: `MovimientoCuentaEmpresa` de la cuenta con concepto
  `cobro_integracion` cuya `IntegracionPagoTransaccion` (origen) esté confirmada
  y con `confirmado_en` dentro del período (con tolerancia de ±1 día por timezone).
- Universo proveedor: filas normalizadas del reporte.
- Match de cobros: fila `tipo=cobro` ↔ transacción por `referencia ==
  transaccion.external_reference` o `id_externo == transaccion.external_id`.
- Clasificación de cada fila del proveedor:
  - **`matcheado`**: cobro con transacción del sistema. Si `comision > 0`,
    genera una fila hija `tipo=comision` (egreso propuesto, monto = comisión).
  - **`solo_proveedor`**: sin contraparte en el sistema → movimiento propuesto:
    - `cobro` sin match, `acreditacion`, `retiro_cancelado` → INGRESO propuesto
      (concepto `acreditacion_integracion`) por el monto neto.
    - `devolucion`, `contracargo` → EGRESO propuesto (concepto
      `devolucion_integracion`).
    - `retiro` → EGRESO propuesto (concepto `retiro_integracion`).
    - `otro` → se muestra informativo, propone movimiento según signo del monto,
      el usuario decide (default: ignorar).
- Filas **`solo_sistema`**: movimientos `cobro_integracion` del período sin fila
  en el reporte → ALERTA (no genera ajuste; puede ser timing del reporte).
- Idempotencia (principio 5): si ya existe `MovimientoCuentaEmpresa` con origen
  `ConciliacionFila` de la misma cuenta y misma `(tipo, id_externo)` (corrida
  anterior aplicada), la fila se marca `ya_registrado` y no propone nada.

### RF-06: Pantalla de revisión y aplicación
- El detalle de la corrida muestra las filas agrupadas por clasificación con
  totales: matcheados (n, $), comisiones propuestas (n, $), solo proveedor
  (n, $ por tipo), solo sistema (alertas), ya registrados.
- Cada fila propuesta tiene acción editable: `generar_movimiento` (default) o
  `ignorar` (checkbox/toggle por fila + acciones masivas por grupo).
- Botón **Aplicar**: en transacción tenant, por cada fila `generar_movimiento`
  → `registrarMovimientoAutomatico()` (concepto según tipo, origen
  `ConciliacionFila`, fecha descripción con detalle del proveedor, usuario =
  aplicador, `sucursal_id` null — la conciliación es de comercio). Corrida →
  `aplicada` (guarda `aplicada_por`/`aplicada_en`). Idempotente: re-click no
  duplica (estado guard).
- Botón **Descartar**: corrida → `descartada` (no toca el ledger; permite
  re-conciliar el período).

### RF-07: Ajuste inicial (cierre de D11)
- Si la cuenta NO tiene ninguna conciliación aplicada previa, al aplicar se
  ofrece un campo opcional "Saldo real total en el proveedor" (el saldo ACTUAL
  que el usuario ve en su app: disponible + a liberar + reserva): se registra
  un movimiento `ajuste_conciliacion` por la diferencia contra el ledger YA
  conciliado (el ajuste corre DESPUÉS de los movimientos de la corrida), de
  modo que la cuenta queda exactamente en el saldo real.
- **Revisión 2026-06-12 (validación en vivo)**: la versión original pedía el
  saldo al INICIO del período — dato que el usuario no tiene a mano. Se cambió
  al saldo al cierre, que es equivalente matemáticamente y trivial de obtener.
  Se intentó leerlo automático por API (`/users/{id}/mercadopago_account/balance`)
  pero MP lo restringe con 403 a tokens estándar → ingreso manual.

### RF-08: Conciliación automática programada (opcional, por cuenta)
- `cuentas_empresa.conciliacion_automatica` (bool, default `false`), editable en
  la gestión de la cuenta (solo visible para cuentas con `identificador_externo`).
- Si está activa, el comando (RF-04 paso 3) crea cada día una corrida por el día
  anterior (si no existe ya una corrida que cubra ese día y no hay otra activa).
- La corrida automática queda `pendiente_revision` — NUNCA auto-aplica (decisión 4).

### RF-09: Permisos y menú
- Ítem de menú nuevo bajo Bancos: "Conciliaciones" (slug `bancos-conciliaciones`).
- Permisos nuevos: `bancos.conciliaciones.ver` (ver listado/detalle, crear corrida)
  y `bancos.conciliaciones.aplicar` (aplicar/descartar). Seeds en
  `ProvisionComercioCommand::seedRolesYPermisos()` + migración para comercios
  existentes (workflow nuevo módulo).

---

## Modelo de Datos

### Tablas nuevas

#### `{NNNNNN}_conciliaciones_cuenta` (tenant)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cuenta_empresa_id` | bigint unsigned FK | — | Cuenta conciliada |
| `desde` | date | — | Inicio del período |
| `hasta` | date | — | Fin del período |
| `estado` | enum(generando, pendiente_revision, aplicada, descartada, error) | generando | Máquina de estados RF-03 |
| `origen` | enum(manual, programada) | manual | Cómo se disparó |
| `solicitud_reporte` | varchar(255) NULL | NULL | Identificador/rango de la solicitud al proveedor |
| `archivo_reporte` | varchar(255) NULL | NULL | file_name descargado (auditoría) |
| `saldo_sistema` | decimal(15,2) NULL | NULL | Saldo del ledger al generar (snapshot) |
| `total_matcheados` | int | 0 | Contadores de clasificación |
| `total_solo_proveedor` | int | 0 | |
| `total_solo_sistema` | int | 0 | |
| `monto_propuesto_ingresos` | decimal(15,2) | 0 | Suma de ingresos propuestos |
| `monto_propuesto_egresos` | decimal(15,2) | 0 | Suma de egresos propuestos |
| `error_mensaje` | text NULL | NULL | Si estado=error |
| `usuario_id` | bigint unsigned NULL | NULL | Quién la creó (NULL = programada) |
| `aplicada_por` | bigint unsigned NULL | NULL | |
| `aplicada_en` | timestamp NULL | NULL | |
| timestamps | | | |

Índices: KEY (`cuenta_empresa_id`, `estado`), KEY (`estado`).

#### `{NNNNNN}_conciliacion_filas` (tenant)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `conciliacion_cuenta_id` | bigint unsigned FK | — | Corrida |
| `tipo` | enum(cobro, comision, devolucion, contracargo, retiro, retiro_cancelado, acreditacion, otro) | — | Tipo normalizado |
| `clasificacion` | enum(matcheado, solo_proveedor, solo_sistema, ya_registrado) | — | Resultado del match |
| `id_externo` | varchar(100) NULL | NULL | Id de la operación en el proveedor (SOURCE_ID) |
| `referencia` | varchar(255) NULL | NULL | EXTERNAL_REFERENCE |
| `fecha` | datetime NULL | NULL | Fecha de la operación en el proveedor |
| `descripcion` | varchar(255) NULL | NULL | Descripción legible |
| `monto_bruto` | decimal(15,2) | 0 | |
| `comision` | decimal(15,2) | 0 | |
| `monto_neto` | decimal(15,2) | 0 | |
| `accion` | enum(generar_movimiento, ignorar, sin_accion) | sin_accion | Editable en revisión (propuestas arrancan en generar_movimiento) |
| `tipo_movimiento` | enum(ingreso, egreso) NULL | NULL | Del movimiento propuesto |
| `concepto_codigo` | varchar(50) NULL | NULL | Concepto del movimiento propuesto |
| `integracion_pago_transaccion_id` | bigint unsigned NULL | NULL | Transacción matcheada |
| `movimiento_cuenta_empresa_id` | bigint unsigned NULL | NULL | Movimiento generado al aplicar |
| timestamps | | | |

Índices: KEY (`conciliacion_cuenta_id`, `clasificacion`), KEY (`id_externo`).
La idempotencia cross-corrida (RF-05) se chequea por query (cuenta + tipo +
id_externo con movimiento generado), no por UNIQUE (filas `ignorar`/descartadas
pueden repetirse legítimamente).

### Tablas modificadas

#### `{NNNNNN}_cuentas_empresa` (tenant) — Cambios
- Agregar: `conciliacion_automatica` (boolean, default `false`) AFTER `identificador_externo`.

#### `{NNNNNN}_conceptos_movimiento_cuenta` (tenant) — Datos
Conceptos nuevos (seed idempotente por código + `ProvisionComercioCommand`, patrón Paso 2):
| código | nombre | tipo |
|--------|--------|------|
| `comision_integracion` | Comisión del proveedor de pago | egreso |
| `retiro_integracion` | Retiro a banco desde el proveedor | egreso |
| `devolucion_integracion` | Devolución/contracargo en el proveedor | egreso |
| `acreditacion_integracion` | Acreditación en el proveedor de pago | ingreso |
| `ajuste_conciliacion` | Ajuste por conciliación | ambos |

> **Regenerar `database/sql/tenant_tables.sql`** tras las migraciones (sin `;`
> en COMMENTs — incidente 2026-06-11). Nota de implementación (2026-06-12): NO
> hace falta morphMap — `movimientos_cuenta_empresa.origen_tipo` es un string
> plano sin relación Eloquent morph (igual que `IntegracionPagoTransaccion`).

---

## Pantallas UI

### Pantalla 1: Conciliaciones (`/bancos/conciliaciones`)
**Componente**: `App\Livewire\Bancos\ConciliacionesCuenta`
**Traits**: ninguno (las cuentas son globales del comercio, NO SucursalAware) + `#[Lazy]` con skeleton
- Listado de corridas (cards móvil / tabla desktop): cuenta, período, estado
  (badge), origen, totales, fecha. Filtros: cuenta, estado.
- Botón "Nueva conciliación" → `<x-bcn-modal>`: selector de cuenta conciliable
  (solo cuentas con `identificador_externo` + config prod resoluble), período
  (desde/hasta, default: últimos 7 días). Crea la corrida en `generando`.
- Detalle de corrida (vista expandida o segunda pantalla del mismo componente):
  grupos por clasificación con totales, toggle `generar/ignorar` por fila y por
  grupo, campo de ajuste inicial (RF-07) si aplica, botones Aplicar / Descartar
  (permiso `bancos.conciliaciones.aplicar`), `wire:poll` mientras `generando`.

### Pantalla 2: Gestión de Cuentas (existente, `/bancos/cuentas`)
**Componente**: `App\Livewire\Bancos\GestionCuentas` (modificación menor)
- En cuentas con `identificador_externo`: toggle "Conciliación automática
  diaria" (RF-08) + acceso directo "Conciliar" que navega a la pantalla 1 con la
  cuenta preseleccionada.

---

## Servicios

### `ConciliacionCuentaService` — `app/Services/IntegracionesPago/ConciliacionCuentaService.php` (nuevo)
- `crearCorrida(CuentaEmpresa $cuenta, Carbon $desde, Carbon $hasta, ?int $usuarioId, string $origen): ConciliacionCuenta` — valida (identidad, config prod, sin corrida activa), snapshot saldo, estado `generando`.
- `procesarPendientes(): int` — motor del comando RF-04 (solicitar/descargar/matchear, timeout, corridas programadas).
- `ejecutarMatch(ConciliacionCuenta $corrida, array $filasProveedor): void` — RF-05, persiste filas + contadores.
- `aplicar(ConciliacionCuenta $corrida, int $usuarioId, ?float $saldoInicialProveedor): ConciliacionCuenta` — RF-06/07, transacción tenant, idempotente.
- `descartar(ConciliacionCuenta $corrida, int $usuarioId): void`
- `resolverConfigParaCuenta(CuentaEmpresa $cuenta): ?IntegracionPagoSucursal` — config prod activa cuya `identidadCuentaEmpresa()` coincide con la cuenta (helper compartido).

### `IntegracionPagoGatewayContract` — Cambios
- `solicitarReporteCuenta(IntegracionPagoSucursal $config, \DateTimeInterface $desde, \DateTimeInterface $hasta): ?string` (`null` = no soporta reportes).
- `obtenerReporteCuenta(IntegracionPagoSucursal $config, string $solicitud): ?array` (`null` = aún no listo; array = filas normalizadas).

### `MercadoPagoGateway` — Cambios
- Implementar ambos métodos (RF-02): config del reporte, solicitud on-demand, listado, descarga CSV, parseo y normalización. Único código MP-específico.

### Comando `conciliaciones:procesar` — `app/Console/Commands/ProcesarConciliacionesCommand.php` (nuevo)
- Itera comercios (TenantService manual), llama `procesarPendientes()`. Scheduler: cada minuto (junto a `precios:procesar-programados`).

---

## Migraciones Necesarias

1. `create_conciliaciones_cuenta` — Tablas `conciliaciones_cuenta` y `conciliacion_filas` (tenant, iterar comercios, SQL raw con prefijo, try/catch).
2. `add_conciliacion_automatica_to_cuentas_empresa` — Boolean en `cuentas_empresa`.
3. `seed_conceptos_conciliacion` — 5 conceptos nuevos (insert idempotente por código) + `ProvisionComercioCommand`.
4. `add_menu_conciliaciones` — Menu item + permisos (BD pymes compartida + roles por comercio).
5. Regenerar `tenant_tables.sql`.

---

## Traducciones

Claves nuevas (es/en/pt, orden alfabético, `/traducir`). Principales:
| Clave (es) | en | pt |
|------------|----|----|
| Conciliaciones | Reconciliations | Conciliações |
| Nueva conciliación | New reconciliation | Nova conciliação |
| Conciliación automática diaria | Daily automatic reconciliation | Conciliação automática diária |
| Pendiente de revisión | Pending review | Pendente de revisão |
| Aplicada | Applied | Aplicada |
| Descartada | Discarded | Descartada |
| Generando reporte del proveedor... | Generating provider report... | Gerando relatório do provedor... |
| Movimientos conciliados | Matched movements | Movimentos conciliados |
| Solo en el proveedor | Only in provider | Somente no provedor |
| Solo en el sistema | Only in system | Somente no sistema |
| Ya registrado en una conciliación anterior | Already recorded in a previous reconciliation | Já registrado em uma conciliação anterior |
| Saldo real en el proveedor al inicio del período | Actual provider balance at period start | Saldo real no provedor no início do período |
| Aplicar ajustes | Apply adjustments | Aplicar ajustes |
| (resto de labels de la pantalla — completar en implementación) | | |

---

## Criterios de Aceptación

- [ ] Crear corrida manual para una cuenta con identidad + config prod la deja `generando`; para una cuenta sin identidad o sin config prod, falla con error claro.
- [ ] No se puede crear una segunda corrida activa para la misma cuenta.
- [ ] El comando solicita el reporte (POST con begin/end y token prod), detecta cuando está listo, descarga el CSV, normaliza tipos (SETTLEMENT→cobro, REFUND→devolucion, CHARGEBACK/DISPUTE→contracargo, WITHDRAWAL/PAYOUT→retiro, WITHDRAWAL_CANCEL→retiro_cancelado) y deja la corrida `pendiente_revision` (todo con Http::fake).
- [ ] Corrida `generando` >60 min pasa a `error` con mensaje.
- [ ] Match: cobro del reporte con `external_reference`/`external_id` de una transacción confirmada → `matcheado`; con comisión >0 genera fila `comision` propuesta como egreso por FEE_AMOUNT.
- [ ] Fila del proveedor sin contraparte → `solo_proveedor` con movimiento propuesto del concepto correcto según tipo (acreditación→ingreso, retiro/devolución/contracargo→egreso).
- [ ] Movimiento `cobro_integracion` del período sin fila en el reporte → `solo_sistema`, NO genera ajuste.
- [ ] Aplicar genera UN `MovimientoCuentaEmpresa` por fila `generar_movimiento` (origen `ConciliacionFila`, concepto correcto) y el saldo de la cuenta queda = saldo anterior + ingresos − egresos aplicados. Filas `ignorar` no generan nada.
- [ ] Re-conciliar un período solapado: las filas ya aplicadas quedan `ya_registrado` y aplicar de nuevo NO duplica movimientos.
- [ ] Aplicar dos veces (doble click / corrida ya aplicada) no duplica (guard de estado).
- [ ] Ajuste inicial (RF-07): solo se ofrece sin conciliaciones aplicadas previas; genera movimiento `ajuste_conciliacion` por la diferencia con el signo correcto.
- [ ] Cuenta con `conciliacion_automatica` activa: el comando crea la corrida diaria del día anterior una sola vez; queda `pendiente_revision` (nunca auto-aplica). Con el flag off no crea nada.
- [ ] Permisos: sin `bancos.conciliaciones.ver` no se accede; con ver pero sin `aplicar`, los botones Aplicar/Descartar no están disponibles.
- [ ] Genericidad: ninguna referencia a "mercadopago"/columnas del CSV fuera de `MercadoPagoGateway`.
- [ ] Smoke test Livewire (`Livewire::test(ConciliacionesCuenta::class)->assertOk()`), Pint verde, `tenant_tables.sql` regenerado, traducciones en los 3 idiomas.

---

## Plan de Implementación

### Fase 1: BD + modelos + conceptos [COMPLETO]
1. Migraciones 1-3 (tablas, boolean, conceptos) + `ProvisionComercioCommand`.
2. Modelos `ConciliacionCuenta` y `ConciliacionFila` (conexión tenant, casts, estados, relaciones, scopes) + morphMap.
3. Regenerar `tenant_tables.sql`. Tests de modelo/scopes.

### Fase 2: Contrato + gateway MP [COMPLETO]
1. Métodos nuevos en el contrato + implementación MP (config reporte, solicitar, listar, descargar, parsear CSV, normalizar).
2. Tests del gateway con Http::fake (CSV de ejemplo con todos los TRANSACTION_TYPE).

### Fase 3: Service + comando [COMPLETO]
1. `ConciliacionCuentaService` completo (crear, procesar, match, aplicar, descartar, resolver config).
2. Comando `conciliaciones:procesar` + registro en scheduler.
3. Tests de service: máquina de estados, match (todas las clasificaciones), idempotencia, aplicar, ajuste inicial, corridas programadas, timeout.

### Fase 4: UI [COMPLETO]
1. Migración 4 (menú + permisos) + ruta + componente `ConciliacionesCuenta` (#[Lazy] + skeleton) + vistas (design system: header + filtros + cards móvil + tabla desktop, x-bcn-modal).
2. Toggle conciliación automática + acceso "Conciliar" en `GestionCuentas`.
3. Traducciones (es/en/pt). Tests Livewire (smoke + crear corrida + aplicar con permisos).

### Fase 5: Cierre [EN PROGRESO — implementación completa, falta validación en vivo]
1. PENDIENTE: validación en vivo del usuario (cuenta MP real: pedir reporte, revisar match, aplicar). El parser CSV es tolerante a los dos dialectos documentados pero el formato real se confirma acá.
2. ✅ Docs (`@docs-sync`) + PR (implementación 2026-06-12, fases 1-4 con 33 tests nuevos en verde).

---

## Notas y Decisiones

- 2026-06-12: **Decisión usuario** — saldo TOTAL (account-money), no liquidaciones. Manual + programada opcional por cuenta. Comisión granular por cobro + acreditaciones/rendiciones como ingresos. Revisión antes de aplicar.
- 2026-06-12: **Asíncrono vía comando** — el reporte de MP tarda en generarse (202); en vez de bloquear la request o usar colas, la corrida avanza por estados con el comando del scheduler (cada minuto), y la UI hace `wire:poll`. Reusa el patrón de `precios:procesar-programados`.
- 2026-06-12: **No usar el schedule de MP** (`/schedule` del lado del proveedor): la programación vive de nuestro lado (on-demand con rango), así controlamos período, idempotencia y multi-cuenta de forma uniforme para futuros proveedores.
- 2026-06-12: **La corrida programada NUNCA auto-aplica** — siempre revisión humana (decisión 4 del usuario). Si más adelante se quiere auto-aplicar, es un flag nuevo explícito.
- 2026-06-12: **Idempotencia por query, no por UNIQUE** — una fila puede aparecer en varias corridas legítimamente (corrida descartada, períodos solapados); el guard es "¿ya existe movimiento generado para (cuenta, tipo, id_externo)?" al clasificar y al aplicar.
- 2026-06-12: **`solo_sistema` no genera ajuste** — un cobro del sistema que no está en el reporte suele ser timing (el reporte corre detrás); generar un egreso automático sería inventar plata. Se muestra como alerta.
- 2026-06-12: **Conceptos separados por naturaleza** (comisión/retiro/devolución/acreditación/ajuste) — habilita reportes por concepto y filtros finos; mismo razonamiento que D9 del Paso 2.
- 2026-06-12: **Punto abierto (verificar en Fase 2 con la cuenta real)**: el formato exacto del CSV de account-money (nombres de columnas con la config default, separador, encoding) se valida contra un reporte real de la cuenta del usuario antes de cerrar el parser; el spec asume las columnas documentadas (SOURCE_ID, EXTERNAL_REFERENCE, TRANSACTION_TYPE, TRANSACTION_AMOUNT, FEE_AMOUNT, SETTLEMENT_NET_AMOUNT, TRANSACTION_DATE).
- 2026-06-12: **Fuera de alcance**: refunds iniciados desde el sistema (flujo futuro; ahí se registrará el egreso en el momento), monedas extranjeras (integraciones MP operan en ARS), auto-aplicación de corridas programadas, conciliación de modo test.
