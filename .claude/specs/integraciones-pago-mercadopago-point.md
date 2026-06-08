# Integraciones de Pago — Modo Point (posnet físico Mercado Pago) — Especificación

## Estado: APROBADO — En implementación (Fase 1/5 ✅ completa, 2026-06-08)

> Agrega el modo de cobro **`point`** (posnet físico de Mercado Pago) al framework de integraciones de pago ya existente (ver `.claude/specs/integraciones-pago-mercadopago.md`, Fases 1-10 ✅). El sistema empuja el monto a una terminal Point física; el cliente paga con tarjeta o QR en el aparato; MP confirma por el mismo webhook que ya tenemos.
>
> **Continuación, NO rediseño**: reutiliza ~90% de la plomería existente (Orders API, `CobroIntegracionService`, webhook, Reverb, modal de espera). Point es un **producto MP separado** → fila de catálogo nueva `mercadopago_point` con credenciales propias.

---

## Contexto y Motivación

El MVP de integraciones de pago (Fases 1-10) entregó cobro con **QR dinámico** y **QR estático** de Mercado Pago, ambos con **monto definido empujado desde el sistema** y mostrados en **nuestra** pantalla (pantalla cliente).

Falta cubrir el caso del **posnet físico** (Mercado Pago Point): el comercio tiene una terminal física; el cajero cobra desde el sistema y el monto se "empuja" a la terminal, donde el cliente paga con **tarjeta** (chip/contactless) o con el **QR que muestra el propio aparato**. Es el flujo preferido cuando hay terminal y se quiere aceptar tarjeta (con cuotas), no solo billetera MP.

**Por qué ahora**: es el siguiente modo natural sobre la arquitectura ya construida (el roadmap del spec MVP, "Eje 2", ya lo previó). La investigación con el MCP oficial de MP confirmó que **Point migró a la misma Orders API** que ya usamos para el QR dinámico, así que el costo de implementación es bajo y el riesgo arquitectónico es mínimo.

**Orden de desarrollo acordado (2026-06-08)**: Point **primero (solo)**. Luego QR monto-libre (con confirmación manual del cajero). Checkout online (`link_pago`) más tarde, junto con la tienda online.

---

## Principios de Diseño

Hereda los 12 principios del spec MVP. Adicionales/específicos de Point:

1. **Point = producto MP separado, no un modo del QR**. En Mercado Pago cada producto (QR / Point / Checkout) es una **aplicación con su propio par de credenciales** (aprendizaje técnico #12; por eso el equipo ya renombró `mercadopago` → `mercadopago_qr`). Point se modela como una **fila de catálogo nueva** `mercadopago_point` en `integraciones_pago`, con su propia config por sucursal (`integraciones_pago_sucursales`) y su propio access_token. `modos_disponibles = ["point"]`. Reusa la **misma** clase `MercadoPagoGateway` (rama por modo), no un gateway nuevo.

2. **Reutilización máxima de la Orders API**. El cobro Point usa el **mismo** `POST /v1/orders` (con `type: "point"`), el **mismo** `external_reference`, el **mismo** webhook (topic `orders`) y el **mismo** cancel (`POST /v1/orders/{id}/cancel`) que el QR dinámico. No se toca el `CobroIntegracionService` ni el `MercadoPagoWebhookController`; solo se agrega una rama en `MercadoPagoGateway::iniciarCobro`.

3. **Point NO usa stores/POS**. `mp_store_id` / `mp_pos_id` son artefactos exclusivos del producto QR (aprendizaje #13). Point trabaja con **terminales** (devices) vía `GET /terminals/v1/list` y `PATCH /terminals/v1/setup` (`operating_mode: "PDV"`). El `terminal_id` se asocia **por caja** (nueva columna `cajas.mp_point_terminal_id`).

4. **El monto lo empuja el sistema** (igual que QR dinámico). El cliente NO elige el monto; la terminal pide pagar el monto exacto de la order.

5. **Medio de pago configurable por FormaPago**. El `config.payment_method.default_type` (`credit_card` / `debit_card` / `qr` / **Abierto**) se define al asociar la integración a la FormaPago. "Abierto" → no se envía `default_type` y el cliente elige en el aparato.

6. **Cuotas reutilizan el mecanismo existente**. La cantidad de cuotas elegida **al cobrar** (`FormaPagoCuota` ya existente) se envía como `config.payment_method.default_installments`. Solo aplica cuando `default_type = credit_card`. No se inventa un mecanismo de cuotas nuevo.

7. **Modal de espera sin QR propio**. La order `type: "point"` no devuelve un QR para que lo dibujemos nosotros: el aparato muestra todo en su pantalla. `qr_data` queda `null` y el modal muestra "Esperando pago en la terminal" + countdown + cancelar. Reutiliza el polling + Reverb de `WithCobroIntegracion`.

---

## Requisitos Funcionales

### RF-01: Catálogo — integración `mercadopago_point`
- Nueva fila en `integraciones_pago`: `codigo = 'mercadopago_point'`, `nombre = 'Mercado Pago - Point'`, `modos_disponibles = ["point"]`, `gateway_class = MercadoPagoGateway`, `descripcion` clara (terminal física, monto empujado desde el sistema), `activo = 1`, `orden = 2`.
- Constante `IntegracionPago::CODIGO_MERCADOPAGO_POINT = 'mercadopago_point'`.

### RF-02: Configuración por sucursal (credenciales Point)
- La pantalla **Integraciones de Pago** (ya existente) lista ahora también `Mercado Pago - Point`. Se configura igual que QR: access_token prod+test (encriptados), modo test/prod, `user_id_externo`, webhook_secret, timeout.
- "Probar conexión" reutiliza `probarConexion` (`GET /users/me`) — válido también para Point.

### RF-03: Vinculación de terminal por caja (espeja la UX del QR)
- **Mismo patrón que el QR ya usa para POS**: hoy la config del QR muestra, dentro de la ventana de integración, las **cajas** de la sucursal con un botón "Sincronizar caja" (`IntegracionesPago::sincronizarCaja` → `SincronizacionMercadoPagoService::sincronizarCaja`, que crea el POS en MP). Point replica ese mismo layout y flujo, pero con **terminales**.
- En la config de la integración Point: listar las **cajas** de la sucursal. Por cada caja, un botón **"Vincular terminal"** que llama a `GET /terminals/v1/list` y muestra los `terminal_id` disponibles de la cuenta (con su `operating_mode`) para elegir de forma intuitiva (no se escribe el id a mano).
- Al vincular, poner la terminal en modo integrado automáticamente: `PATCH /terminals/v1/setup` con `operating_mode: "PDV"`.
- El `terminal_id` elegido se guarda en `cajas.mp_point_terminal_id`. La UI muestra la terminal **en uso** por cada caja (igual que el QR muestra el POS sincronizado).
- La lógica de listar/vincular vive en `SincronizacionMercadoPagoService` (métodos nuevos `listarTerminales` / `vincularTerminalCaja`), espejando `sincronizarCaja`.

### RF-04: FormaPago con integración Point + `default_type`
- Una FormaPago puede asociarse a la integración `mercadopago_point` (mismo bloque N:M que QR, vía `forma_pago_integraciones`).
- Nuevo control en el bloque de integración: selector **"Medio de pago en la terminal"** con opciones `credit_card` / `debit_card` / `qr` / **"Abierto (el cliente elige)"**.
- Se persiste en el pivote `forma_pago_integraciones` (columna nueva `config_point` JSON, ej. `{"default_type": "credit_card"}`; "Abierto" = `null` / sin clave).

### RF-05: Cobro con modo Point
- Al cobrar una venta con una FP integrada a Point, `WithCobroIntegracion::iniciarCobroIntegracion` arma `$datos` con: `terminal_id` (de la **caja activa**), `default_type` (de la FP, si no es Abierto), `installments` (de la cuota elegida, si `default_type = credit_card`).
- `MercadoPagoGateway::iniciarCobro` detecta `modo = 'point'` y hace `POST /v1/orders` con:
  ```json
  {
    "type": "point",
    "external_reference": "<ref única>",
    "description": "<desc venta>",
    "expiration_time": "PT<timeout>S",
    "transactions": { "payments": [ { "amount": "<monto string decimal>" } ] },
    "config": {
      "point": { "terminal_id": "<id>", "print_on_terminal": "no_ticket" },
      "payment_method": { "default_type": "<credit_card|debit_card|qr>", "default_installments": <n> }
    }
  }
  ```
  - Si `default_type = Abierto` → se omite `payment_method.default_type` (y `default_installments`).
  - `amount` en string decimal `"15.00"`.
  - Header `X-Idempotency-Key`.
- La transacción se crea con `modo_usado = 'point'`, `external_id = order.id`, `qr_data = null`.

### RF-06: Validación pre-cobro Point
- Si la caja activa **no tiene** `mp_point_terminal_id`, el cobro Point falla con mensaje claro ("La caja no tiene una terminal Point asignada"). No se crea order.
- Si no hay caja activa (módulo sin caja), Point no está disponible.

### RF-07: Espera y confirmación (reutiliza Fase 5/6)
- El modal muestra estado "Esperando pago en la terminal" + countdown (timeout) + botón cancelar (+ confirmar manual con permiso, ya existente).
- El webhook `orders` confirma server-side; Reverb avisa al modal; el cobrable se materializa al re-consultar. **Sin cambios** respecto al flujo QR salvo el render del modal (sin QR).

### RF-08: Cancelación
- Cancelar reutiliza `cancelarCobro` → `POST /v1/orders/{id}/cancel` con `X-Idempotency-Key`. Si la order está `at_terminal`, agregar header `x-allow-cancelable-status: at_terminal`.

### RF-09: Webhook resuelve Point
- `IntegracionPagoSucursal::sincronizarIndiceColector` hoy sincroniza el `mercadopago_collector_index` solo si `codigo === CODIGO_MERCADOPAGO_QR`. Se extiende el guard para incluir `CODIGO_MERCADOPAGO_POINT`.
- Como el sync es **upsert por `(user_id_externo, modo)`** y el resolver del webhook ya deduce sucursal/transacción por `external_id` (no por el índice), una cuenta MP compartida entre QR y Point del mismo comercio NO colisiona: una sola entrada en el índice por `(user_id, modo)` → comercio basta. Sin otros cambios en el webhook.

### RF-10: Botón "Ver terminal/posnet" (opcional)
- En la pantalla de cajas (o en la config de la integración), si la caja tiene `mp_point_terminal_id`, mostrar un botón opcional para ver/identificar la terminal asignada (datos del device).

---

## Modelo de Datos

### Tablas nuevas
Ninguna tabla nueva.

### Tablas modificadas

#### `integraciones_pago` (tenant) — Semilla
- Insertar fila `mercadopago_point` (`modos_disponibles = ["point"]`, `gateway_class = App\Services\IntegracionesPago\MercadoPagoGateway`, `orden = 2`).

#### `cajas` (tenant) — Cambios
- Agregar: `mp_point_terminal_id` (`varchar(64)`, `NULL`) AFTER `mp_pos_qr_pdf_url` — terminal Point asignada a la caja. Formato MP `{tipo}__{serial}`.

#### `forma_pago_integraciones` (pivote, tenant) — Cambios
- Agregar: `config_point` (`json`, `NULL`) AFTER `modos_permitidos` — config específica del modo Point para esa FP. Hoy: `{"default_type": "credit_card|debit_card|qr"}`; `null`/ausente = "Abierto".

> **Nota multi-tenant**: las 3 tablas son tenant (con prefijo). Las migraciones iteran todos los comercios con SQL raw + try/catch (skill `/migration`). Regenerar `database/sql/tenant_tables.sql` al finalizar.

---

## Pantallas UI

### Pantalla 1: Integraciones de Pago (`/configuracion/integraciones-pago`) — EXTENDER
**Componente**: `App\Livewire\Configuracion\IntegracionesPago` (SucursalAware, ya existe)
- Lista ahora también `Mercado Pago - Point` (sale solo del catálogo, la UI es dinámica).
- En la config Point: además de credenciales, sección **"Terminales por caja"** que **espeja** la sección de POS/cajas del QR → tabla de cajas de la sucursal, cada una con su terminal **en uso** y un botón "Vincular terminal".
- "Vincular terminal" (`GET /terminals/v1/list`) → lista los devices disponibles de la cuenta para elegir; al confirmar, activa modo integrado (`PATCH /terminals/v1/setup` PDV) y guarda `cajas.mp_point_terminal_id`. Nuevo método `IntegracionesPago::vincularTerminalCaja($configId, $cajaId)` análogo a `sincronizarCaja`.

### Pantalla 2: Gestionar Formas de Pago (`/configuracion/formas-pago`) — EXTENDER
**Componente**: `App\Livewire\Configuracion\GestionarFormasPago` (ya existe)
- En el bloque de integración, si la integración seleccionada es `mercadopago_point` (o el modo es `point`), mostrar el selector **"Medio de pago en la terminal"** (`credit_card` / `debit_card` / `qr` / Abierto).
- Persistir en `forma_pago_integraciones.config_point`.

### Pantalla 3: Modal Esperando Pago — EXTENDER
**Partial**: `resources/views/livewire/.../_modal-esperando-pago-integracion.blade.php` (ya existe)
- Condicional: si `qr_data` es `null` y `modo_usado = 'point'`, mostrar ilustración/ícono de terminal + texto "Esperando pago en la terminal" en lugar del QR. Resto igual (countdown, cancelar, confirmar manual).

---

## Servicios

### `MercadoPagoGateway` — `app/Services/IntegracionesPago/MercadoPagoGateway.php` (EXTENDER)
- `iniciarCobro(...)`: agregar rama `modo === MODO_POINT` → construir y `POST /v1/orders` con `type: "point"` y `config.point` / `config.payment_method` según RF-05. Devuelve la misma estructura (`qr_data = null`, `external_reference`, `external_id`, `payload`).
- `cancelarCobro(...)`: contemplar header `x-allow-cancelable-status: at_terminal` cuando aplique.
- `consultarEstado(...)` y `procesarWebhook(...)`: **sin cambios** (Orders API unificada).
- Constante `MODO_POINT = 'point'` + agregarla a `modosSoportados()`.
- Helpers nuevos (pueden ir en una fase de pulido): `listarTerminales(IntegracionPagoSucursal $config): array` (`GET /terminals/v1/list`), `activarModoPDV(IntegracionPagoSucursal $config, string $terminalId): array` (`PATCH /terminals/v1/setup`).

### `SincronizacionMercadoPagoService` — `app/Services/IntegracionesPago/SincronizacionMercadoPagoService.php` (EXTENDER)
- `listarTerminales(IntegracionPagoSucursal $config): array` → `GET /terminals/v1/list` (devuelve devices con `id`, `operating_mode`, etc.).
- `vincularTerminalCaja(IntegracionPagoSucursal $config, Caja $caja, string $terminalId): void` → `PATCH /terminals/v1/setup` (PDV) + guarda `caja.mp_point_terminal_id`. Espeja `sincronizarCaja` (QR/POS).

### `IntegracionPagoSucursal` (modelo) — `sincronizarIndiceColector()` (EXTENDER)
- Cambiar el guard de `=== CODIGO_MERCADOPAGO_QR` a `in_array($integracion->codigo, [CODIGO_MERCADOPAGO_QR, CODIGO_MERCADOPAGO_POINT])` (o helper `esMercadoPago()`).

### `WithCobroIntegracion` (trait) — `iniciarCobroIntegracion()` (EXTENDER)
- Para modo `point`: leer `terminal_id` de la caja activa; validar presencia (RF-06); pasar `default_type` (de la FP) e `installments` (de la cuota elegida) en `$datos`.

### `CobroIntegracionService` — **sin cambios** (orquesta igual; los datos extra viajan en `$datos`).

---

## Migraciones Necesarias

1. `seed_mercadopago_point_integracion` — Insertar fila `mercadopago_point` en `integraciones_pago` (iterando comercios). `down`: borrar la fila.
2. `add_mp_point_terminal_id_to_cajas` — Agregar `cajas.mp_point_terminal_id` varchar(64) NULL.
3. `add_config_point_to_forma_pago_integraciones` — Agregar `forma_pago_integraciones.config_point` json NULL.

> Las 3 pueden ir en **un bundle** si conviene el orden, o separadas (no tienen FKs entre sí). Regenerar `tenant_tables.sql`. Recordar `WithTenant::$testTables` si aplica (no hay tablas nuevas, solo columnas → no requiere).

---

## Traducciones

Claves nuevas (es/en/pt), alfabéticas, vía `/traducir`:

| Clave (es) | en | pt |
|------------|----|----|
| `Mercado Pago - Point` | `Mercado Pago - Point` | `Mercado Pago - Point` |
| `Cobros con Mercado Pago Point: el monto se envía a la terminal física y el cliente paga con tarjeta o QR en el aparato.` | (trad) | (trad) |
| `Medio de pago en la terminal` | `Payment method on terminal` | `Meio de pagamento no terminal` |
| `Abierto (el cliente elige)` | `Open (customer chooses)` | `Aberto (o cliente escolhe)` |
| `Tarjeta de crédito` / `Tarjeta de débito` | … | … |
| `Esperando pago en la terminal` | `Waiting for payment on terminal` | `Aguardando pagamento no terminal` |
| `Terminal Point` / `Terminales por caja` | … | … |
| `Buscar terminales` | `Search terminals` | `Buscar terminais` |
| `La caja no tiene una terminal Point asignada` | … | … |

---

## Criterios de Aceptación

- [ ] **CA-01**: Existe la integración `mercadopago_point` en el catálogo, visible en la pantalla Integraciones de Pago.
- [ ] **CA-02**: Se pueden cargar credenciales Point por sucursal (encriptadas) y "Probar conexión" funciona.
- [ ] **CA-03**: Se puede asignar un `terminal_id` a una caja (`cajas.mp_point_terminal_id`).
- [ ] **CA-04**: Una FormaPago se asocia a Point y guarda `default_type` (o Abierto) en `config_point`.
- [ ] **CA-05**: Cobrar con FP Point hace `POST /v1/orders` con `type:"point"`, `terminal_id` correcto, `amount` string, y `default_type`/`installments` cuando corresponde (verificado con `Http::fake`).
- [ ] **CA-06**: "Abierto" omite `payment_method.default_type`. `credit_card` con cuota envía `default_installments`.
- [ ] **CA-07**: Cobrar sin terminal asignada en la caja falla con mensaje claro y NO crea order.
- [ ] **CA-08**: El modal de espera muestra "Esperando pago en la terminal" (sin QR) para Point.
- [ ] **CA-09**: El webhook `orders` de un pago Point confirma la transacción y materializa el cobrable (resuelve por el índice extendido).
- [ ] **CA-10**: `sincronizarIndiceColector` registra el índice también para `mercadopago_point`; cuenta compartida QR+Point del mismo comercio no rompe (upsert).
- [ ] **CA-11**: Cancelar un cobro Point llama al cancel con el header correcto según estado.
- [ ] **CA-12**: Smoke tests OK (IntegracionesPago, GestionarFormasPago). Lint Pint OK.
- [ ] **CA-13**: `tenant_tables.sql` regenerado. Traducciones es/en/pt consistentes.
- [ ] **CA-14**: Docs (`manual-usuario.md` + `ai-knowledge-base.md`) actualizados vía `@docs-sync` al crear el PR.

---

## Plan de Implementación

### Fase 1: BD + catálogo + modelos [COMPLETO — 2026-06-08]
1. ✅ Migración seed `mercadopago_point` (`2026_06_08_120015`, idempotente por `codigo`) + constante `IntegracionPago::CODIGO_MERCADOPAGO_POINT`. También en `ProvisionComercioCommand::seedIntegracionesPago` (comercios nuevos, orden=2).
2. ✅ Migración `cajas.mp_point_terminal_id` varchar(64) + índice (`2026_06_08_120016`) + `fillable` en `Caja`.
3. ✅ Migración `forma_pago_integraciones.config_point` json (`2026_06_08_120017`) + `withPivot(['...','config_point'])` en `FormaPago::integraciones()`.
4. ✅ `tenant_tables.sql` regenerado (columna cajas + índice, columna pivote). Migraciones aplicadas en dev + testing. Pint OK. Tests existentes no se rompen (usan `porCodigo('mercadopago_qr')`, no conteo).

**Entregable**: catálogo muestra Point; columnas listas. ✅
**Nota**: los tests de comportamiento (gateway Point, cobro Point) llegan en Fases 2 y 4 (proporcional: Fase 1 es schema/seed verificado en vivo).

### Fase 2: Gateway — cobro Point (Orders API) [COMPLETO — 2026-06-08]
1. ✅ `MercadoPagoGateway::MODO_POINT` + `modosSoportados()` (3 modos). Const espejo en `IntegracionPagoTransaccion::MODO_POINT`.
2. ✅ Rama `point` en `iniciarCobro` → `iniciarCobroPoint`: `POST /v1/orders` con `type:"point"`, `config.point.terminal_id` (de `caja->mp_point_terminal_id`), `expiration_time` acotado PT30S..PT3H (`expirationTimeIso`), `transactions.payments[].amount` string. `payment_method` solo si la FP definió `default_type` (de `metadata['point']`); `default_installments` solo en `credit_card`. `qr_data`/`qr_image_url` = null (el aparato muestra todo). Valida terminal asignada (RF-06).
3. ✅ `cancelarCobro` agrega header `x-allow-cancelable-status: at_terminal` para modo point.
4. ✅ 5 tests nuevos en `MercadoPagoGatewayTest` (type:point + terminal + abierto; credit_card+cuotas; débito sin installments; sin terminal lanza; cancel con header). 39/39 verdes, suite integraciones-pago sin regresiones. Pint OK.

**Entregable**: el gateway crea/cancela orders Point. ✅ (sin UI todavía; verificado con `Http::fake`)

### Fase 3: Config UI — terminal por caja + default_type [COMPLETO — 2026-06-08]
**3a (service, no visual)** ✅:
1. ✅ `MercadoPagoGateway::listarTerminales` (`GET /terminals/v1/list`) + `activarModoPDV` (`PATCH /terminals/v1/setup` PDV).
2. ✅ `SincronizacionMercadoPagoService::listarTerminales` + `vincularTerminalCaja` (activa PDV + persiste `mp_point_terminal_id`) + `desvincularTerminalCaja`. 3 tests `Http::fake` verdes.

**3b.1 (UI terminales por caja)** ✅:
3. ✅ `IntegracionesPago`: `buscarTerminales`/`vincularTerminal`/`desvincularTerminal` + props. Vista: sección "Terminales por caja" espejando la de POS del QR (botón "Buscar terminales", por caja muestra la en uso o selector + Vincular/Desvincular). Test Livewire verde.

**3b.2 (selector default_type en Formas de Pago)** ✅:
4. ✅ `GestionarFormasPago`: campo `default_type` por fila de integración; persiste en pivote `config_point` (`{"default_type":...}`; Abierto = null) solo si modo=point. Carga desde el pivote al editar. Vista: selector "Medio de pago en la terminal" (credit_card/debit_card/qr/Abierto) visible solo si modo=point. 2 tests (crédito persiste, abierto = null) verdes.

5. ✅ Smoke de ambos componentes OK. 19 traducciones es/en/pt. Pint OK.

**Entregable**: admin configura Point end-to-end desde la UI.

### Fase 4: Cobro end-to-end + modal [COMPLETO — 2026-06-08]
1. ✅ `WithCobroIntegracion::iniciarCobroIntegracion`: para modo `point` valida terminal de la caja (RF-06), arma `metadata['point']` (default_type de la FP `config_point`, installments de la cuota del desglose solo en credit_card) y lo pasa al service. Prop `cobroIntegracionModo` para que el modal decida. `CobroIntegracionService::iniciarCobro` ahora acepta `metadata` y la persiste en la transacción (el gateway la lee).
2. ✅ Modal `_modal-esperando-pago-integracion`: rama `@elseif modo === 'point'` muestra "Esperando pago en la terminal" + ícono posnet, sin QR. El envío a la pantalla cliente se guarda (no manda QR vacío para Point).
3. ✅ `IntegracionPagoSucursal::sincronizarIndiceColector`: guard extendido a `[CODIGO_MERCADOPAGO_QR, CODIGO_MERCADOPAGO_POINT]` → el webhook de Point resuelve (mismo topic `orders`, distingue por external_id).
4. ✅ Tests: `CobroQrFlujoFelizTest::test_flujo_feliz_point...` (end-to-end: POST type:point con terminal+medio+cuotas, sin QR, confirma → materializa venta) + `IntegracionPagoSucursalServiceTest::...config_point_tambien_sincroniza_indice` (índice registra Point). 72 tests de la suite verdes. Pint OK. 1 traducción nueva.

**Entregable**: una venta con FP Point empuja el cobro a la terminal, espera sin QR, y al confirmar materializa la venta. ✅ (verificado con `Http::fake`; webhook real reutiliza el flujo existente)

### Fase 5: Pulido + docs + PR [EN PROGRESO]
1. ✅ "Buscar terminales" + "Activar modo integrado" (PDV) ya en la UI (Fase 3b.1).
2. ✅ Botón "Ver terminal" en Gestión de Cajas (RF-10): `verTerminalPoint($cajaId)` abre modal con el terminal_id y, best-effort, el `operating_mode` del device desde MP (si hay credenciales Point). Solo aparece si la caja tiene `mp_point_terminal_id`. Smoke test verde. 5 traducciones.
3. ⬜ `@docs-sync` → manual + ai-knowledge-base (incluir procedimiento de prueba con endpoint de simulación `POST /v1/orders/{id}/events`).
4. ⬜ `/sdd-verify` + PR.

**Procedimiento de prueba (documentar)**: Point se prueba con un posnet físico **vinculado a credenciales de PRUEBA**; no se paga físicamente: se simula cada estado con `POST /v1/orders/{order_id}/events` (status processed/failed/canceled/expired/action_required) → MP manda el webhook real → el sistema confirma. Pagos reales en el aparato requieren cuenta de PRODUCCIÓN. Sin device no se prueba contra el sandbox (los tests `Http::fake` ya cubren el código).

**Entregable**: feature completo y verificado.

---

## Notas y Decisiones

- **2026-06-08**: Scope acordado con el usuario — Point primero (solo); QR monto-libre y checkout después. Decisiones: Point = catálogo separado (`mercadopago_point`, credenciales propias, aprendizaje #12); terminal por caja (`cajas.mp_point_terminal_id`, Point no usa stores/POS, #13); `default_type` configurable por FP (credit_card/debit_card/qr/Abierto); cuotas reutilizan `FormaPagoCuota` (→ `default_installments` al cobrar); webhook reutiliza todo (único fix: extender el guard de `sincronizarIndiceColector`); modal sin QR para Point.
- **Hallazgo MCP (2026-06-08)**: MP migró Point de la vieja "Payment Intents API" (deprecada) a la **Orders API** (`type: "point"`), la misma que ya usamos para QR dinámico. Esto reduce el trabajo a una rama en el gateway. Validar payloads exactos contra el MCP en Fase 2.
- **Decisiones cerradas (2026-06-08)**:
  - `config_point` = **JSON** en el pivote (extensible para futuros params Point como `print_on_terminal` por FP).
  - UX terminal↔caja = **espeja la del QR** (botón "Vincular terminal" por caja en la ventana de integración, auto-lista `GET /terminals/v1/list`, muestra la terminal en uso). NO carga manual.
  - `expiration_time` de Point **confirmado contra MCP**: mínimo `PT30S`, máximo `PT3H`, default `PT15M` (idéntico al QR). El timeout configurable de la sucursal (default 5 min) entra sin problema; el ID de order Point es alfanumérico (`ORD...`), no UUID.
