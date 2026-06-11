# Vínculo CuentaEmpresa ↔ Integraciones de Pago - Especificación

## Estado: EN REVISIÓN

> Paso 2 del roadmap de integraciones-pago (posterior a `qr_libre` — PR #129 ya mergeado).
> Vincula automáticamente la `CuentaEmpresa` (ledger de saldo) con la cuenta del proveedor
> de pago externo, **de forma genérica y extensible a cualquier integración** (no solo MP).
> Habilita el Paso 3 (conciliación real vía API de Reportes), que NO entra en este spec.

---

## Contexto y Motivación

Hoy una `FormaPago` con `cuenta_empresa_id` registra automáticamente el ingreso en el saldo de esa cuenta al cobrar **por cobro manual** (`CobroService.php:251-266` → `CuentaEmpresaService::registrarMovimientoAutomatico`). Pero los cobros **por integración** (`CobroIntegracionService`) **NO** registran ese movimiento, y además el `cuenta_empresa_id` se carga a mano sin relación con la cuenta real del proveedor.

Para poder **conciliar el saldo del sistema contra el saldo real de Mercado Pago** (Paso 3), primero necesitamos que:
1. exista una `CuentaEmpresa` que represente la cuenta del proveedor (identificada por su id externo),
2. la `FormaPago` de la integración apunte a esa cuenta,
3. cada cobro por integración (en producción) genere el `MovimientoCuentaEmpresa` correspondiente.

El requisito explícito del usuario es **no hacerlo MP-específico**: igual que el framework de integraciones de formas de pago abstrae gateways intercambiables, el vínculo de cuenta debe funcionar para cualquier proveedor futuro (Ualá, MODO, PayPal, etc.) sin tocar el core.

---

## Principios de Diseño

1. **Genérico por contrato, específico por gateway**: el ÚNICO código por-proveedor es un método nuevo en `IntegracionPagoGatewayContract`. El resto (columna, servicio de vínculo, autocompletado, registro de movimiento) es provider-agnostic.
2. **Match por identidad de cuenta, no por fila de catálogo**: una cuenta del proveedor se identifica por `(subtipo, identificador_externo)`. MP tiene varias filas de catálogo (`mercadopago_qr`, `mercadopago_point`) que comparten la MISMA cuenta real → no se ata a `integracion_pago_id`.
3. **Solo producción afecta el ledger**: el vínculo y el movimiento monetario se aplican únicamente cuando la config está en modo `produccion`. El modo `test` no ensucia el saldo real.
4. **Idempotente y no intrusivo**: el auto-vínculo es `findOrCreate` (re-guardar credenciales no duplica); el registro de movimiento captura excepciones y nunca rompe la confirmación del cobro.
5. **Reutilizar el ledger existente**: mismo `CuentaEmpresaService::registrarMovimientoAutomatico()` y patrón append-only que ya usan los cobros manuales (origen polimórfico).
6. **Default editable, no imposición**: el `cuenta_empresa_id` autocompletado en la UI es una sugerencia que el usuario puede cambiar.

---

## Requisitos Funcionales

### RF-01: Identidad de cuenta por gateway (seam de extensibilidad)
- Nuevo método en `IntegracionPagoGatewayContract`:
  `identidadCuentaEmpresa(IntegracionPagoSucursal $config): ?array`.
- Devuelve `['subtipo' => string, 'identificador_externo' => string, 'nombre_sugerido' => string]` o `null` si el proveedor no se mapea a una `CuentaEmpresa` conciliable (o le faltan datos).
- `MercadoPagoGateway` lo implementa: `subtipo='mercadopago'`, `identificador_externo=$config->user_id_externo`, `nombre_sugerido='MercadoPago'`. Devuelve `null` si `user_id_externo` está vacío.

### RF-02: Auto-crear/ubicar la CuentaEmpresa al guardar credenciales de producción
- Al crear/actualizar una `IntegracionPagoSucursal` en modo `produccion` con identidad resoluble, el sistema hace `findOrCreate` de la `CuentaEmpresa` por `(subtipo, identificador_externo)`.
- Si se crea: `tipo=billetera_digital`, `subtipo`, `identificador_externo`, `nombre`=nombre_sugerido, `activo=true`.
- Lookup (ver D5): (a) buscar por `(subtipo, identificador_externo)` exacto → si existe, reutilizar; (b) si no, y existe **una única** cuenta del `subtipo` con `identificador_externo` NULL → completarla; (c) si no, crear nueva. Idempotente: re-guardar no duplica.
- En modo `test`: no-op.

### RF-03: Autocompletar `cuenta_empresa_id` en la Forma de Pago
- En `GestionarFormasPago`, cuando una `FormaPago` tiene una integración cuya config de sucursal (prod) tiene una `CuentaEmpresa` vinculada, pre-seleccionar ese `cuenta_empresa_id` como **default editable**.
- El usuario puede cambiarlo o dejarlo vacío.

### RF-04: Registrar el movimiento en el ledger al confirmar un cobro por integración
- En `CobroIntegracionService::confirmarCobro()` y `confirmarManual()`, tras confirmar la transacción:
  - SI `transaccion.formaPago->cuenta_empresa_id` existe **Y** la config de la integración `esProduccion()` → registrar `MovimientoCuentaEmpresa` (ingreso) vía `CuentaEmpresaService::registrarMovimientoAutomatico()`.
  - Concepto `cobro_integracion_pago`; origen polimórfico `origen_tipo='IntegracionPagoTransaccion'`, `origen_id=transaccion.id`; monto=`transaccion.monto`.
  - Excepciones capturadas (log warning), nunca rompen la confirmación.
- Cubre todos los modos (`qr_dinamico`, `qr_estatico`, `qr_libre`, `point`) y futuros proveedores.

### RF-05: Solo producción
- RF-02 y RF-04 se aplican únicamente con `IntegracionPagoSucursal::esProduccion()`. En `test` no hay vínculo ni movimiento.

---

## Modelo de Datos

### Tablas modificadas

#### `{NNNNNN}_cuentas_empresa` (tenant) — Cambios
- Agregar: `identificador_externo` (`varchar(100)` NULL) AFTER `subtipo`.
  - Guarda el id de la cuenta en el proveedor (para MP = `user_id_externo`).
  - Match cuenta↔proveedor = `(subtipo, identificador_externo)`.
  - Nullable: las cuentas bancarias/manuales existentes no lo usan.
- Índice: `(subtipo, identificador_externo)` para el lookup del `findOrCreate`/autocompletado.

> Sin cambios de esquema en `formas_pago` (ya tiene `cuenta_empresa_id`), `integraciones_pago_sucursales` (ya tiene `user_id_externo`, `modo`) ni en el pivote `forma_pago_integraciones`.

> **Regenerar `database/sql/tenant_tables.sql`** tras la migración.

---

## Pantallas UI

### Pantalla: Configuración → Formas de Pago (`/configuracion/formas-pago`)
**Componente**: `App\Livewire\Configuracion\GestionarFormasPago` (existente)
**Traits**: sin cambios
- Al cargar/editar una FormaPago con integración: autocompletar `cuenta_empresa_id` (RF-03) como default editable.
- (Opcional, menor) Hint visual de que la cuenta fue vinculada automáticamente desde la integración.

> No hay pantallas nuevas. La config de credenciales (`IntegracionesPago`) no cambia su UI; el auto-vínculo ocurre server-side al guardar (RF-02).

---

## Servicios

### `CuentaEmpresaService` — `app/Services/CuentaEmpresaService.php` (existente)
- `registrarMovimientoAutomatico(...)`: **ya existe**, se reutiliza tal cual desde `CobroIntegracionService` (RF-04).
- `findOrCreateParaIntegracion(IntegracionPagoSucursal $config): ?CuentaEmpresa` (**nuevo**, genérico): si `esProduccion()`, pide `identidadCuentaEmpresa()` al gateway de la integración; si `null` → no-op; `findOrCreate` por `(subtipo, identificador_externo)`. Devuelve la cuenta o `null`.

> Decisión de ubicación: el método vive en `CuentaEmpresaService` (no en un service nuevo) por cohesión con el resto de la lógica de cuentas. Reevaluar a `VinculoCuentaIntegracionService` solo si crece.

### `IntegracionPagoSucursalService` — `app/Services/IntegracionesPago/IntegracionPagoSucursalService.php` (existente)
- En `crear()`/`actualizar()` (o donde persista credenciales): tras guardar, si `modo=produccion`, invocar `CuentaEmpresaService::findOrCreateParaIntegracion($config)` (RF-02). No-op/silencioso en test.

### `CobroIntegracionService` — `app/Services/IntegracionesPago/CobroIntegracionService.php` (existente)
- `confirmarCobro()` y `confirmarManual()`: tras confirmar, registrar el movimiento (RF-04) con guard solo-producción y captura de excepciones.

### `MercadoPagoGateway` — `app/Services/IntegracionesPago/MercadoPagoGateway.php` (existente)
- Implementar `identidadCuentaEmpresa()` (RF-01). Único código provider-specific.

---

## Migraciones Necesarias

1. `add_identificador_externo_to_cuentas_empresa` — Agregar `identificador_externo` (varchar(100) NULL) + índice `(subtipo, identificador_externo)` a la tabla tenant `cuentas_empresa`. Iterar TODOS los comercios, SQL raw con prefijo, try/catch por comercio. Regenerar `tenant_tables.sql`.

---

## Traducciones

Claves nuevas (solo si se agrega el hint de RF-03; mínimo):
| Clave (es) | en | pt |
|------------|----|----|
| Cuenta vinculada automáticamente desde la integración de pago | Account auto-linked from the payment integration | Conta vinculada automaticamente da integração de pagamento |

---

## Criterios de Aceptación

- [ ] Guardar credenciales MP en **producción** con `user_id_externo` crea (o reutiliza) una `CuentaEmpresa` subtipo `mercadopago` con ese `identificador_externo`. Re-guardar no duplica.
- [ ] Guardar credenciales MP en **test** NO crea ninguna `CuentaEmpresa`.
- [ ] En `GestionarFormasPago`, una FP con integración MP (prod vinculada) muestra el `cuenta_empresa_id` autocompletado y editable.
- [ ] Confirmar un cobro por integración (cualquier modo) en **producción** con FP que tiene `cuenta_empresa_id` registra un `MovimientoCuentaEmpresa` de ingreso por el monto, con origen `IntegracionPagoTransaccion`.
- [ ] El mismo cobro en **test** NO registra movimiento.
- [ ] `confirmarManual()` de `qr_libre` en producción también registra el movimiento.
- [ ] Una excepción al registrar el movimiento NO rompe la confirmación del cobro (queda log warning).
- [ ] `identidadCuentaEmpresa()` devuelve `null` cuando `user_id_externo` está vacío → sin vínculo, sin error.
- [ ] El diseño no introduce ninguna referencia a "mercadopago" fuera de `MercadoPagoGateway` (genericidad verificable).
- [ ] `tenant_tables.sql` regenerado; lint (Pint) y tests verdes.

---

## Plan de Implementación

### Fase 1: BD + modelo [PENDIENTE]
1. Migración `add_identificador_externo_to_cuentas_empresa` (tenant, iterar comercios, índice). Correr en dev y testing.
2. `CuentaEmpresa`: agregar `identificador_externo` al `$fillable` + scope `scopePorIdentidad($subtipo, $identificador)` (o similar).
3. Regenerar `tenant_tables.sql`.

### Fase 2: Contrato + gateway (seam) [PENDIENTE]
1. `IntegracionPagoGatewayContract::identidadCuentaEmpresa()`.
2. Implementación en `MercadoPagoGateway`.
3. Test: MP devuelve la identidad correcta; `null` sin `user_id_externo`.

### Fase 3: Servicio de vínculo + auto-crear al guardar prod [PENDIENTE]
1. `CuentaEmpresaService::findOrCreateParaIntegracion()` (genérico, solo prod, idempotente).
2. Invocación desde `IntegracionPagoSucursalService` al guardar credenciales prod.
3. Tests: crea en prod, no-op en test, idempotencia, `null`-identidad.

### Fase 4: Registrar movimiento en cobro por integración [PENDIENTE]
1. Registrar movimiento en `CobroIntegracionService::confirmarCobro()` y `confirmarManual()` (guard solo-prod, captura de excepciones).
2. Tests: ingreso en prod (todos los modos incl. qr_libre), no-op en test, excepción no rompe confirmación, origen polimórfico correcto.

### Fase 5: Autocompletar UI + cierre [PENDIENTE]
1. Autocompletar `cuenta_empresa_id` en `GestionarFormasPago` (default editable) + (opcional) hint + traducciones.
2. Smoke test del componente. `/sdd-verify`. Docs (`@docs-sync`). PR.

---

## Mapa de conflictos con Point (#128)

> Esta rama sale de **master** (que ya tiene qr_libre #129). El PR #128 (Point) sigue **abierto** y toca varios de estos archivos. El **segundo PR en mergearse** verá conflictos mecánicos → conservar AMBOS aportes.

| # | Archivo | Qué toca este Paso 2 | Posible choque con Point | Resolución |
|---|---------|----------------------|--------------------------|------------|
| 1 | `IntegracionPagoGatewayContract.php` | Agrega método `identidadCuentaEmpresa()` | Point puede agregar otros métodos al contrato | Conservar todos los métodos nuevos |
| 2 | `MercadoPagoGateway.php` | Implementa `identidadCuentaEmpresa()` | Point agrega `iniciarCobroPoint()` y constantes | Conservar ambas adiciones (métodos independientes) |
| 3 | `IntegracionPagoSucursalService.php` | Invoca auto-vínculo al guardar prod | Point puede tocar el guardado/sync | Conservar ambos (insertar la llamada sin pisar lo de Point) |
| 4 | `GestionarFormasPago.php` | Autocompleta `cuenta_empresa_id` | Point toca load/save (`config_point`) | Conservar ambos en load y save |
| 5 | `CobroIntegracionService.php` | Registra movimiento al confirmar | Point puede tocar confirmarCobro | Conservar ambos (el guard de Point y el registro de movimiento conviven) |
| 6 | `database/sql/tenant_tables.sql` | Columna `identificador_externo` en `cuentas_empresa` | Point: `config_point` en otra tabla | **Regenerar** el SQL tras mergear (no resolver a mano) |

> Las migraciones no conflictúan entre sí (archivos/columnas/tablas distintas); el único artefacto compartido es `tenant_tables.sql` (#6).

---

## Notas y Decisiones

- 2026-06-09: **D1** — Vínculo Y movimiento **solo en producción** (recomendado y adoptado). El modo test no debe ensuciar el saldo real ni la conciliación.
- 2026-06-09: **D2** — Auto-crear `CuentaEmpresa` al guardar credenciales prod, vía `findOrCreate` idempotente (adoptado).
- 2026-06-09: **D3** — Match por `(subtipo, identificador_externo)`, NO por `integracion_pago_id` (adoptado): MP comparte una cuenta entre `mercadopago_qr` y `mercadopago_point`.
- 2026-06-09: **D4** — Registrar el movimiento en `confirmarCobro()`/`confirmarManual()` de `CobroIntegracionService` (adoptado), reutilizando `registrarMovimientoAutomatico()`.
- 2026-06-09: **D5** — Si ya existe una `CuentaEmpresa` del mismo `subtipo` creada a mano SIN `identificador_externo`: si hay **exactamente UNA** del subtipo sin identificador, el auto-vínculo le **completa** el `identificador_externo` (la reutiliza). Si hay **varias o ambigüedad**, **crea una nueva**. Adoptado. Esto refina el `findOrCreate` de RF-02: el lookup busca primero por `(subtipo, identificador_externo)` exacto; si no hay match y existe una única del subtipo con `identificador_externo` NULL, la completa; si no, crea.
- 2026-06-09: **Genericidad** — El seam es el método del contrato. Criterio de aceptación explícito: ninguna mención a "mercadopago" fuera de `MercadoPagoGateway`.
- 2026-06-09: Paso 3 (conciliación vía API de Reportes/Liquidaciones) queda FUERA de este spec; este Paso 2 lo habilita (ver memoria `project_integraciones_pago_conciliacion_mp`).
