# Vínculo CuentaEmpresa ↔ Integraciones de Pago - Especificación

## Estado: APROBADO (revisado 2026-06-11)

> Paso 2 del roadmap de integraciones-pago (qr_libre #129 y Point #128 ya mergeados a master).
> Vincula automáticamente la `CuentaEmpresa` (ledger de saldo) con la cuenta del proveedor
> de pago externo, **de forma genérica y extensible a cualquier integración** (no solo MP).
> Habilita el Paso 3 (conciliación real vía API de Reportes), que NO entra en este spec.
>
> **Revisión 2026-06-11**: re-análisis profundo contra el código real. Se detectó y corrigió
> un DOBLE REGISTRO que el diseño original producía (D6), se cambió la resolución de la
> cuenta a la identidad real de la config (D7), se definió la semántica de anulaciones (D8)
> y se agregaron el concepto de ledger faltante y el índice UNIQUE (D9, D10).

---

## Contexto y Motivación

Hoy una `FormaPago` con `cuenta_empresa_id` registra automáticamente el ingreso en el saldo de esa cuenta al cobrar, en TRES sitios de materialización:
- `NuevaVenta.php:~1421` (venta con pago simple)
- `WithPagosDesglose.php:~2190` (desglose de pagos — NuevaVenta y NuevoPedidoMostrador)
- `CobroService.php:~255` (cobranzas de cuenta corriente y pagos planificados de pedidos)

Pero ese registro está atado a la FP (un solo `cuenta_empresa_id` global) y NO distingue si el pago vino por una integración ni en qué cuenta del proveedor cayó realmente la plata. Además el `cuenta_empresa_id` se carga a mano sin relación con la cuenta real del proveedor.

Para poder **conciliar el saldo del sistema contra el saldo real de Mercado Pago** (Paso 3), necesitamos que:
1. exista una `CuentaEmpresa` que represente la cuenta real del proveedor (identificada por su id externo),
2. cada cobro por integración (en producción) genere el `MovimientoCuentaEmpresa` **en la cuenta real donde cayó la plata** (la de la config de la sucursal que cobró),
3. el movimiento se registre **al confirmar la transacción** (cuando MP ve la plata), no al materializar la venta,
4. el flujo existente por-FP NO duplique ese registro.

El requisito explícito del usuario es **no hacerlo MP-específico**: igual que el framework de integraciones abstrae gateways intercambiables, el vínculo de cuenta debe funcionar para cualquier proveedor futuro (Ualá, MODO, PayPal, etc.) sin tocar el core.

---

## Principios de Diseño

1. **Genérico por contrato, específico por gateway**: el ÚNICO código por-proveedor es un método nuevo en `IntegracionPagoGatewayContract`. El resto (columna, servicio de vínculo, autocompletado, registro de movimiento) es provider-agnostic.
2. **Match por identidad de cuenta, no por fila de catálogo**: una cuenta del proveedor se identifica por `(subtipo, identificador_externo)`. MP tiene varias filas de catálogo (`mercadopago_qr`, `mercadopago_point`) que comparten la MISMA cuenta real → no se ata a `integracion_pago_id`.
3. **El movimiento sigue a la plata, no a la venta**: se registra al confirmar la transacción (la plata ya entró a MP), en la cuenta resuelta desde la **config de la sucursal** de esa transacción. Un cobro confirmado cuyo cobrable nunca se materializa (cajero cerró el navegador) igual impacta la cuenta — eso ES lo que la conciliación del Paso 3 necesita.
4. **Solo producción afecta el ledger**: vínculo y movimiento se aplican únicamente con config en modo `produccion`. El modo `test` no ensucia el saldo real. Guard en UN solo lugar (confirmación), no repartido por los flujos de venta.
5. **Idempotente y no intrusivo**: el auto-vínculo es `findOrCreate` (re-guardar credenciales no duplica); el registro de movimiento es idempotente por transacción, captura excepciones y nunca rompe la confirmación del cobro.
6. **Reutilizar el ledger existente**: mismo `CuentaEmpresaService::registrarMovimientoAutomatico()` y patrón append-only (origen polimórfico).
7. **Default editable, no imposición**: el `cuenta_empresa_id` autocompletado en la UI de FP es una sugerencia editable; rige para cobros MANUALES con esa FP. Para cobros por integración manda la identidad real de la config.

---

## Requisitos Funcionales

### RF-01: Identidad de cuenta por gateway (seam de extensibilidad)
- Nuevo método en `IntegracionPagoGatewayContract`:
  `identidadCuentaEmpresa(IntegracionPagoSucursal $config): ?array`.
- Devuelve `['subtipo' => string, 'identificador_externo' => string, 'nombre_sugerido' => string]` o `null` si el proveedor no se mapea a una `CuentaEmpresa` conciliable (o le faltan datos).
- `MercadoPagoGateway` lo implementa: `subtipo='mercadopago'`, `identificador_externo=$config->user_id_externo`, `nombre_sugerido='Mercado Pago '.$user_id_externo`. Devuelve `null` si `user_id_externo` está vacío.

### RF-02: Auto-crear/ubicar la CuentaEmpresa al guardar credenciales de producción
- Al crear/actualizar una `IntegracionPagoSucursal` en modo `produccion` con identidad resoluble, el sistema hace `findOrCreate` de la `CuentaEmpresa` por `(subtipo, identificador_externo)`.
- Si se crea: `tipo=billetera_digital`, `subtipo`, `identificador_externo`, `nombre`=nombre_sugerido, `activo=true`.
- Lookup (ver D5): (a) buscar por `(subtipo, identificador_externo)` exacto → si existe, reutilizar; (b) si no, y existe **una única** cuenta del `subtipo` con `identificador_externo` NULL → completarla; (c) si no, crear nueva. Idempotente: re-guardar no duplica.
- En modo `test`: no-op.

### RF-03: Autocompletar `cuenta_empresa_id` en la Forma de Pago
- En `GestionarFormasPago`, cuando una `FormaPago` tiene una integración cuya config de sucursal (prod) tiene una `CuentaEmpresa` vinculada, pre-seleccionar ese `cuenta_empresa_id` como **default editable**.
- El usuario puede cambiarlo o dejarlo vacío. Este campo rige solo para cobros manuales con esa FP; los cobros por integración resuelven la cuenta por identidad (RF-04).
- Hint visual de que la cuenta fue vinculada automáticamente desde la integración.

### RF-04: Registrar el movimiento al CONFIRMAR la transacción, en la cuenta REAL (D6 + D7)
- En `CobroIntegracionService::confirmarCobro()` y `confirmarManual()`, tras confirmar la transacción y SOLO si la config `esProduccion()`:
  1. Resolver la `CuentaEmpresa` desde la **config de la transacción** (`transaccion->integracionSucursal`): `identidadCuentaEmpresa()` del gateway → lookup/findOrCreate por `(subtipo, identificador_externo)` (robustez: si la cuenta no existe aún, se crea acá igual que en RF-02).
  2. Fallback: si la identidad es `null` (gateway no mapeable) → usar `transaccion->formaPago->cuenta_empresa_id` si existe. Si tampoco → no registrar (sin error).
  3. Registrar `MovimientoCuentaEmpresa` (ingreso) vía `CuentaEmpresaService::registrarMovimientoAutomatico()`: concepto `cobro_integracion`; origen polimórfico `origen_tipo='IntegracionPagoTransaccion'`, `origen_id=transaccion->id`; monto=`transaccion->monto`; `sucursal_id=transaccion->sucursal_id`; usuario = confirmador (manual) o `usuario_iniciador_id` (webhook/automático).
- **Idempotencia**: antes de registrar, verificar que NO exista ya un `MovimientoCuentaEmpresa` con ese origen polimórfico (webhook + polling + manual pueden converger; `confirmarCobro` ya es idempotente en estado, esto lo extiende al ledger).
- Excepciones capturadas (log warning), nunca rompen la confirmación.
- Cubre todos los modos (`qr_dinamico`, `qr_estatico`, `qr_libre`, `point`) y futuros proveedores.

### RF-05: Suprimir el registro por-FP cuando el pago vino por integración (anti doble registro, D6)
- Los TRES sitios de materialización que registran por `formaPago->cuenta_empresa_id` deben **saltear** el registro cuando el pago proviene de un cobro por integración (el movimiento ya lo registró RF-04 con origen `IntegracionPagoTransaccion`):
  - `NuevaVenta.php` (~1421, pago simple)
  - `WithPagosDesglose.php` (~2190, desglose)
  - `CobroService.php` (~255, cobranzas/pagos planificados)
- Mecanismo: el flujo de cobro por integración (concern `WithCobroIntegracion` / hosts) marca el pago con un flag explícito (ej. `via_integracion => true` en el array del pago, o equivalente que el host ya conozca vía `cobroIntegracionTransaccionId`). El sitio de registro lo chequea y saltea SOLO ese pago (en desglose mixto, los demás pagos de la venta registran normal).
- Consecuencia deliberada: `venta_pagos.movimiento_cuenta_empresa_id` queda NULL para pagos por integración → los flujos de anulación/cambio de FP (que revierten por ese link) no tienen nada que contraasentar (ver D8).

### RF-06: Solo producción
- RF-02 y RF-04 se aplican únicamente con `IntegracionPagoSucursal::esProduccion()`. En `test` no hay vínculo ni movimiento. Como el registro vive en la confirmación (un solo lugar), el guard NO se replica en los sitios de RF-05.

---

## Modelo de Datos

### Tablas modificadas

#### `{NNNNNN}_cuentas_empresa` (tenant) — Cambios
- Agregar: `identificador_externo` (`varchar(100)` NULL) AFTER `subtipo`.
  - Guarda el id de la cuenta en el proveedor (para MP = `user_id_externo`).
  - Match cuenta↔proveedor = `(subtipo, identificador_externo)`.
  - Nullable: las cuentas bancarias/manuales existentes no lo usan.
- Índice: **UNIQUE** `(subtipo, identificador_externo)` (D10). MySQL permite múltiples NULL en índices únicos, así que las cuentas manuales no chocan. Refuerza la idempotencia de D5 a nivel BD.

#### `{NNNNNN}_conceptos_movimiento_cuenta` (tenant) — Datos (D9)
- Concepto nuevo: `codigo='cobro_integracion'`, nombre "Cobro por integración de pago" (afecta saldo: ingreso).
- Seed en `ProvisionComercioCommand` (junto a los existentes: `venta`, `cobro`, `ajuste`, ...) **y** migración de datos para comercios existentes (insert idempotente por código).
- Razón de un concepto propio (no reusar `venta`): el Paso 3 necesita filtrar los movimientos generados por integraciones para matchearlos contra el reporte de MP.

> Sin cambios de esquema en `formas_pago` (ya tiene `cuenta_empresa_id`), `integraciones_pago_sucursales` (ya tiene `user_id_externo`, `modo`) ni en el pivote `forma_pago_integraciones`.

> **Regenerar `database/sql/tenant_tables.sql`** tras las migraciones. RECORDATORIO: los COMMENT del dump NO pueden contener `;` (rompe el `explode(';')` de WithTenant/Provision — incidente 2026-06-11).

---

## Pantallas UI

### Pantalla: Configuración → Formas de Pago (`/configuracion/formas-pago`)
**Componente**: `App\Livewire\Configuracion\GestionarFormasPago` (existente)
**Traits**: sin cambios
- Al cargar/editar una FormaPago con integración: autocompletar `cuenta_empresa_id` (RF-03) como default editable + hint de vínculo automático.

> No hay pantallas nuevas. La config de credenciales (`IntegracionesPago`) no cambia su UI; el auto-vínculo ocurre server-side al guardar (RF-02).

---

## Servicios

### `CuentaEmpresaService` — `app/Services/CuentaEmpresaService.php` (existente)
- `registrarMovimientoAutomatico(...)`: **ya existe**, se reutiliza tal cual (RF-04).
- `findOrCreateParaIntegracion(IntegracionPagoSucursal $config): ?CuentaEmpresa` (**nuevo**, genérico): si `esProduccion()`, pide `identidadCuentaEmpresa()` al gateway de la integración; si `null` → no-op; lookup según D5 (exacto → única-sin-identificador → crear). Devuelve la cuenta o `null`. Lo usan RF-02 (al guardar credenciales) y RF-04 (al confirmar, como resolución/robustez).

> Decisión de ubicación: el método vive en `CuentaEmpresaService` (no en un service nuevo) por cohesión con el resto de la lógica de cuentas. Reevaluar a `VinculoCuentaIntegracionService` solo si crece.

### `IntegracionPagoSucursalService` — `app/Services/IntegracionesPago/IntegracionPagoSucursalService.php` (existente)
- En `crear()`/`actualizar()` (o donde persista credenciales): tras guardar, si `modo=produccion`, invocar `CuentaEmpresaService::findOrCreateParaIntegracion($config)` (RF-02). No-op/silencioso en test.
- OJO: si cambia `user_id_externo` de una config prod ya vinculada, el próximo guardado resuelve/crea la cuenta de la identidad NUEVA; la vieja queda (no se borra — puede tener movimientos históricos).

### `CobroIntegracionService` — `app/Services/IntegracionesPago/CobroIntegracionService.php` (existente)
- `confirmarCobro()` y `confirmarManual()`: tras confirmar, registrar el movimiento (RF-04: guard solo-prod, resolución por config, idempotencia por origen, captura de excepciones).

### `MercadoPagoGateway` — `app/Services/IntegracionesPago/MercadoPagoGateway.php` (existente)
- Implementar `identidadCuentaEmpresa()` (RF-01). Único código provider-specific.

### Sitios de materialización (RF-05)
- `NuevaVenta.php`, `WithPagosDesglose.php`, `CobroService.php`: skip del registro por-FP cuando el pago trae el flag de integración.

---

## Migraciones Necesarias

1. `add_identificador_externo_to_cuentas_empresa` — Columna `identificador_externo` (varchar(100) NULL) + índice UNIQUE `(subtipo, identificador_externo)` en tabla tenant `cuentas_empresa`. Iterar TODOS los comercios, SQL raw con prefijo, try/catch por comercio.
2. `seed_concepto_cobro_integracion` — Insert idempotente del concepto `cobro_integracion` en `conceptos_movimiento_cuenta` de TODOS los comercios + agregarlo al seed de `ProvisionComercioCommand`.
3. Regenerar `tenant_tables.sql` (sin `;` en COMMENTs).

---

## Traducciones

| Clave (es) | en | pt |
|------------|----|----|
| Cuenta vinculada automáticamente desde la integración de pago | Account auto-linked from the payment integration | Conta vinculada automaticamente da integração de pagamento |
| Cobro por integración de pago | Payment integration charge | Cobrança por integração de pagamento |

---

## Criterios de Aceptación

- [ ] Guardar credenciales MP en **producción** con `user_id_externo` crea (o reutiliza según D5) una `CuentaEmpresa` subtipo `mercadopago` con ese `identificador_externo`. Re-guardar no duplica (verificable también por el UNIQUE).
- [ ] Guardar credenciales MP en **test** NO crea ninguna `CuentaEmpresa`.
- [ ] Dos sucursales con configs prod del MISMO `user_id_externo` comparten UNA sola `CuentaEmpresa`.
- [ ] Dos sucursales con `user_id_externo` DISTINTOS generan DOS cuentas, y cada cobro impacta la de SU sucursal (D7).
- [ ] En `GestionarFormasPago`, una FP con integración MP (prod vinculada) muestra el `cuenta_empresa_id` autocompletado y editable.
- [ ] Confirmar un cobro por integración (cualquier modo incl. `qr_libre` manual y `point`) en **producción** registra UN único `MovimientoCuentaEmpresa` de ingreso por `transaccion->monto`, concepto `cobro_integracion`, origen `IntegracionPagoTransaccion`.
- [ ] **Anti doble registro**: materializar la venta de ese cobro NO genera un segundo movimiento (el `venta_pagos.movimiento_cuenta_empresa_id` del pago por integración queda NULL). En un desglose mixto (integración + efectivo), el pago en efectivo de una FP con cuenta SÍ registra el suyo.
- [ ] Confirmación que converge por más de un camino (webhook + polling + manual) registra UN solo movimiento (idempotencia por origen).
- [ ] El mismo cobro en **test** NO registra movimiento.
- [ ] Anular la venta de un cobro por integración NO revierte el movimiento de la cuenta (D8).
- [ ] Una excepción al registrar el movimiento NO rompe la confirmación del cobro (queda log warning).
- [ ] `identidadCuentaEmpresa()` devuelve `null` cuando `user_id_externo` está vacío → sin vínculo, sin error; si la FP tiene `cuenta_empresa_id`, se usa como fallback.
- [ ] El diseño no introduce ninguna referencia a "mercadopago" fuera de `MercadoPagoGateway` (genericidad verificable).
- [ ] `tenant_tables.sql` regenerado; lint (Pint) y tests verdes.

---

## Plan de Implementación

### Fase 1: BD + modelo [COMPLETO]
1. Migración `add_identificador_externo_to_cuentas_empresa` (tenant, iterar comercios, índice UNIQUE). Correr en dev y testing.
2. Migración/seed del concepto `cobro_integracion` + `ProvisionComercioCommand`.
3. `CuentaEmpresa`: `identificador_externo` en `$fillable` + scope `scopePorIdentidad($subtipo, $identificador)`.
4. Regenerar `tenant_tables.sql`.

### Fase 2: Contrato + gateway (seam) [COMPLETO]
1. `IntegracionPagoGatewayContract::identidadCuentaEmpresa()`.
2. Implementación en `MercadoPagoGateway`.
3. Test: MP devuelve la identidad correcta; `null` sin `user_id_externo`.

### Fase 3: Servicio de vínculo + auto-crear al guardar prod [PENDIENTE]
1. `CuentaEmpresaService::findOrCreateParaIntegracion()` (genérico, solo prod, lookup D5, idempotente).
2. Invocación desde `IntegracionPagoSucursalService` al guardar credenciales prod.
3. Tests: crea en prod, no-op en test, idempotencia, identidad-null, D5 (completa única sin identificador / crea ante ambigüedad), identidades distintas → cuentas distintas.

### Fase 4: Movimiento al confirmar + anti doble registro [PENDIENTE]
1. Registrar movimiento en `CobroIntegracionService::confirmarCobro()` y `confirmarManual()` (RF-04: solo-prod, resolución por config con fallback FP, idempotencia por origen, captura de excepciones).
2. Flag de pago-por-integración en el flujo de cobro (`WithCobroIntegracion`/hosts) + skip en los 3 sitios de materialización (RF-05).
3. Tests: ingreso en prod (todos los modos incl. qr_libre/point), no-op en test, UN solo movimiento con venta materializada (anti doble registro), desglose mixto registra solo el pago no-integración, idempotencia multi-camino, excepción no rompe confirmación, anulación no revierte, origen polimórfico correcto.

### Fase 5: Autocompletar UI + cierre [PENDIENTE]
1. Autocompletar `cuenta_empresa_id` en `GestionarFormasPago` (default editable) + hint + traducciones.
2. Smoke test del componente. `/sdd-verify`. Docs (`@docs-sync`). PR.

---

## Notas y Decisiones

- 2026-06-09: **D1** — Vínculo Y movimiento **solo en producción** (adoptado). El modo test no debe ensuciar el saldo real ni la conciliación.
- 2026-06-09: **D2** — Auto-crear `CuentaEmpresa` al guardar credenciales prod, vía `findOrCreate` idempotente (adoptado).
- 2026-06-09: **D3** — Match por `(subtipo, identificador_externo)`, NO por `integracion_pago_id` (adoptado): MP comparte una cuenta entre `mercadopago_qr` y `mercadopago_point`.
- 2026-06-09: ~~**D4** — Registrar el movimiento en confirmarCobro usando `formaPago->cuenta_empresa_id`~~ **REEMPLAZADA por D6+D7** (la versión original producía doble registro y cuenta equivocada en multi-sucursal).
- 2026-06-09: **D5** — Si ya existe una `CuentaEmpresa` del mismo `subtipo` creada a mano SIN `identificador_externo`: si hay **exactamente UNA**, el auto-vínculo le **completa** el `identificador_externo`. Si hay **varias o ambigüedad**, crea una nueva. (adoptado)
- 2026-06-11: **D6** — El movimiento se registra **al confirmar la transacción** (confirmarCobro/confirmarManual) y se **suprime** el registro por-FP en los 3 sitios de materialización para pagos por integración (flag explícito). Motivo: el diseño original duplicaba el ingreso (la materialización de la venta YA registraba por `formaPago->cuenta_empresa_id`); además registrar al confirmar refleja cuándo la plata entró a MP y cubre cobros confirmados sin venta materializada. (decidido con el usuario)
- 2026-06-11: **D7** — La cuenta del movimiento se resuelve por la **identidad de la config de la sucursal** de la transacción (subtipo + identificador_externo del gateway), con fallback a `formaPago->cuenta_empresa_id` si la identidad no es resoluble. Motivo: con sucursales que usan cuentas MP distintas, la FP (valor único global) impactaría la cuenta equivocada. `FormaPago.cuenta_empresa_id` queda como default UI y para cobros manuales. (decidido con el usuario)
- 2026-06-11: **D8** — Anular una venta cobrada por integración **NO revierte** el movimiento de la cuenta: la plata sigue en MP salvo refund real (flujo futuro; ahí se registrará el egreso). Implementación natural: el pago por integración no liga `movimiento_cuenta_empresa_id` en `venta_pagos` (RF-05), y los flujos de anulación/cambio de FP revierten por ese link → no encuentran nada. Nota: si se usa "cambio de forma de pago" sobre un pago por integración, el movimiento original (origen transaccion) persiste — coherente con esta decisión. (decidido con el usuario)
- 2026-06-11: **D9** — Concepto de ledger propio `cobro_integracion` (no reusar `venta`): el Paso 3 necesita filtrar estos movimientos para matchear contra el reporte de MP. Requiere seed + migración de datos (el spec original lo omitía: el concepto no existe en `conceptos_movimiento_cuenta`).
- 2026-06-11: **D10** — Índice **UNIQUE** `(subtipo, identificador_externo)` (el original decía índice común). Refuerza D5 a nivel BD; los NULL múltiples de cuentas manuales no chocan en MySQL.
- 2026-06-11: **D11** — **Sin backfill** de transacciones confirmadas históricas: el ledger arranca desde el deploy; la conciliación del Paso 3 (que compara contra el reporte completo de MP por período) absorbe la diferencia inicial como ajuste.
- 2026-06-11: **Moneda** — `registrarMovimientoAutomatico()` no maneja moneda extranjera; las integraciones MP operan en ARS (moneda principal). Si un futuro proveedor opera en otra moneda, extender ahí (fuera de alcance).
- 2026-06-09: **Genericidad** — El seam es el método del contrato. Criterio de aceptación explícito: ninguna mención a "mercadopago" fuera de `MercadoPagoGateway`.
- 2026-06-09: Paso 3 (conciliación vía API de Reportes/Liquidaciones) queda FUERA de este spec; este Paso 2 lo habilita (ver memoria `project_integraciones_pago_conciliacion_mp`). Decidido 2026-06-11: se especifica DESPUÉS de implementar este Paso 2.

> El "Mapa de conflictos con Point (#128)" del spec original quedó obsoleto: Point y qr_libre ya están mergeados en master; esta rama sale de master limpio.
