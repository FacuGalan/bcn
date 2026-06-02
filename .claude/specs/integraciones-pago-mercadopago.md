# Integraciones de Pago — MercadoPago (y framework extensible) — Especificación

## Estado: EN IMPLEMENTACIÓN — Fases 1-9 COMPLETAS (2026-06-02), resta Fase 10 (docs + PR final)

> Framework genérico para conectar pasarelas de pago (MercadoPago en MVP, luego MODO, Cuenta DNI, PayPal, etc.) a Formas de Pago del sistema. Cada FormaPago puede tener una integración asignada con un **modo** específico (QR dinámico, QR estático, etc.) y, al cobrar, se dispara el flujo correspondiente (generar QR, esperar confirmación por webhook+Reverb, materializar la venta).
>
> **Próximo paso**: Fase 10 — documentación (`@docs-sync`), instrucciones de webhook en `server-config.md`, y PR final con `/sdd-verify`. Las Fases 1-9 ya están mergeadas (PRs #107 a #120) salvo Fase 9, en su rama.

---

## Contexto y Motivación

Hoy las FormasPago son “contenedores contables” puros: el sistema asume que el cliente ya pagó (efectivo, tarjeta vía POSnet externo, transferencia confirmada manualmente, etc.) y el cajero solo registra el cobro. No hay integración con pasarelas que generen QRs, links o intentos de pago verificables online.

Cada vez es más común que el cliente quiera pagar con MercadoPago (QR) en el mostrador, MODO, Cuenta DNI, etc. La conciliación manual hoy es propensa a errores: el cajero ve la app del cliente, asume que pagó y confirma. Si no pagó realmente, la venta queda mal cobrada.

**Necesidad concreta:**

- Cada FormaPago puede tener una **integración de pago** asociada con su **configuración técnica** (credenciales por sucursal, modo de operación).
- Al cobrar una venta con una FormaPago integrada, el sistema **genera el evento correspondiente** (ej: QR dinámico de MercadoPago) y **espera confirmación real del proveedor** antes de materializar el cobro.
- La arquitectura debe ser **extensible**: agregar mañana MODO o Cuenta DNI implica crear un Gateway nuevo, no rediseñar el sistema.
- Múltiples módulos del sistema cobrarán con esto: NuevaVenta, PedidosMostrador, y futuros (atención salón, delivery, cobranza CC). Todos consumen el mismo contrato.

**Por qué empezar con MercadoPago:**

- Es la pasarela más usada en Argentina.
- API pública madura, documentación abundante, SDK PHP oficial.
- Soporta los modos que más interesan en mostrador (QR dinámico, QR estático).
- Sirve como caso de uso de referencia para validar la abstracción antes de agregar otros proveedores.

---

## Principios de Diseño

1. **Framework extensible por contrato (Gateway pattern)**: existe un contrato `IntegracionPagoGatewayContract` que define los métodos universales que cualquier proveedor debe implementar (`iniciarCobro`, `consultarEstado`, `procesarWebhook`, etc.). MercadoPago es la primera implementación (`MercadoPagoGateway`). Agregar otro proveedor en el futuro = crear otra clase Gateway + agregar fila al catálogo de integraciones. Cero cambios al consumidor.

2. **Service contract único para consumidores**: `CobroIntegracionService` es la API que llaman NuevaVenta, PedidosMostrador y futuros módulos. Métodos atómicos (`iniciarCobro`, `cancelarCobro`, `consultarEstado`) que devuelven una estructura uniforme. El consumidor NO sabe qué proveedor hay detrás.

3. **Tres capas de configuración**:
   - **`integraciones_pago`** (catálogo, semilla): lista de proveedores soportados (`mercadopago`, futuro `modo`, `paypal`). Define qué modos soporta cada uno.
   - **`integraciones_pago_sucursales`** (config por comercio y sucursal): credenciales prod+test por sucursal, modo activo (test/prod), timeout configurable. Una sucursal puede tener N integraciones activas (ej: MP + MODO).
   - **`FormaPago` extendida**: una FP apunta a una integración + un modo default + lista de modos overridables.

4. **Cobro sincrónico con webhook + Reverb**: el cajero queda esperando en pantalla con el QR visible. MP llama al webhook → broadcast Reverb → modal se cierra solo → venta se materializa. Sin polling continuo, sin delays innecesarios. Timeout configurable (default 5 min).

5. **Transaccionalidad estricta**: la venta NO se materializa (no toca stock, caja, CC, AFIP) hasta que el pago esté confirmado. Mientras tanto, vive como `IntegracionPagoTransaccion` con estado `pendiente`. Si timeout o cancela, se anula limpio. Si confirma, se materializa todo en una sola transacción.

6. **Modo híbrido FP↔modo**: cada FP tiene `modo_default` y `modos_permitidos` (JSON). En el momento de cobrar, el cajero puede mantener el default o cambiar a otro modo permitido. Permite tener "MP - QR" como entrada principal pero ofrecer "link" o "qr estático" si conviene.

7. **Credenciales por sucursal**: cada sucursal carga su propio access_token MP (cuenta MP). Permite que franquicias / sucursales independientes tengan acreditaciones bancarias separadas. La URL de webhook es **única global**; el sistema resuelve la sucursal a partir del `user_id` MP del payload.

8. **Encriptación at-rest**: las credenciales (access_token, refresh_token, client_secret) se guardan **encriptadas** usando `Crypt::encryptString()` de Laravel. Nunca en texto plano.

9. **Sandbox/Prod por integración**: cada `IntegracionPagoSucursal` tiene `modo` (`test` | `produccion`) + ambos pares de credenciales cargadas. Cambio instantáneo con un toggle. Permite tener una sucursal QA en test mientras producción opera normal.

10. **Pagos mixtos compatibles**: si una venta es mixta (parte efectivo + parte MP), el sistema genera el QR solo por la porción MP. La venta se materializa cuando ese pago confirma. El efectivo queda registrado en el desglose y se asienta junto al MP en la misma transacción de materialización.

11. **Webhook idempotente y firmado**: MP puede llamar al mismo webhook varias veces. El handler es idempotente (busca por `payment_id` + estado, ignora duplicados). Verifica firma `x-signature` de MP para rechazar payloads spoofeados.

12. **Trazabilidad completa**: cada transacción guarda payload entrante, respuesta del proveedor, eventos de cambio de estado, y referencia polimórfica al origen (venta, pedido, cobro_cc). Útil para soporte y conciliación.

13. **Permisos granulares**: configurar integraciones requiere `integraciones_pago.administrar` (típicamente solo Admin del comercio). Cobrar con integración usa los permisos existentes del módulo (venta, pedido, etc.).

14. **Reusable para futuros canales**: el contrato `CobroIntegracionService::iniciarCobro()` recibe un `contexto` polimórfico (`venta`, `pedido_mostrador`, `cobro_cc`, futuros). Mañana, atención salón / delivery / cobranza llaman al mismo método sin cambios al framework.

---

## Requisitos Funcionales

### RF-01: Catálogo de Integraciones de Pago

- El sistema tiene una tabla semilla `integraciones_pago` (tenant) con las integraciones soportadas.
- MVP: una sola fila → `mercadopago` con modos `qr_dinamico`, `qr_estatico`.
- Cada fila tiene: `codigo` (slug único), `nombre`, `descripcion`, `modos_disponibles` (JSON con lista de modos soportados por ese proveedor), `gateway_class` (FQCN de la implementación PHP), `activo`.
- Se siembra vía `ProvisionComercioCommand` al provisionar un comercio nuevo.
- NO es editable por UI (es catálogo de sistema). Para agregar nuevo proveedor → migración + nuevo Gateway PHP.

### RF-02: Configuración de Integración por Sucursal

- Pantalla `Configuración → Integraciones de Pago` (componente Livewire `App\Livewire\Configuracion\IntegracionesPago`).
- Lista las integraciones del catálogo (`mercadopago`, …) en tarjetas/secciones.
- Por cada integración: lista las sucursales del comercio y muestra "Configurado" / "Sin configurar".
- Click en una sucursal abre modal de configuración con:
  - `modo` (radio: `test` | `produccion`).
  - `access_token_produccion` (password input, encriptado).
  - `access_token_test` (password input, encriptado).
  - `public_key_produccion` (text input).
  - `public_key_test` (text input).
  - `user_id_mp` (text input, **crítico** para resolver webhooks).
  - `timeout_segundos` (number input, default 300 = 5 min).
  - `webhook_secret` (password input, encriptado, opcional pero recomendado).
  - `activo` (toggle).
- Botón "Probar conexión" que llama al gateway para validar credenciales (intenta listar payments o consultar cuenta).
- Requiere permiso `integraciones_pago.administrar`.

### RF-03: Asignar Integración a una FormaPago

- En el formulario de alta/edición de FormaPago (componente `App\Livewire\Configuracion\FormasPago` o similar), agregar un nuevo bloque "Integración de Pago" (visible solo si el `ConceptoPago` lo permite — ver RF-04):
  - Selector "Integración" (dropdown con integraciones activas del comercio + opción "Ninguna").
  - Si se elige una: aparece selector "Modo default" (dropdown con `modos_disponibles` de esa integración).
  - Y un multi-select "Modos overridables" (cuáles modos puede elegir el cajero al cobrar, además del default).
- Si la FP no tiene integración (`integracion_pago_id IS NULL`): se comporta como hoy (cobro manual).

### RF-04: ConceptoPago habilita integraciones

- Agregar columna `permite_integracion` (boolean, default false) en `conceptos_pago`.
- Solo conceptos con `permite_integracion=true` muestran el bloque de integración en FormaPago. MVP: setear `permite_integracion=true` para `wallet` y `transferencia`. Otros conceptos (efectivo, cheque) no aplican.
- Las FormasPago que no apuntan a un concepto válido no pueden tener integración (validación en el modelo).

### RF-05: Iniciar cobro con integración (NuevaVenta MVP)

- En `NuevaVenta`, cuando el cajero confirma la venta y la FP elegida tiene integración:
  1. La venta se persiste en estado `pendiente_pago_integracion` (estado nuevo, sin stock, sin caja, sin CC, sin AFIP).
  2. Se crea `IntegracionPagoTransaccion` con estado `pendiente`, monto, modo, referencia a la venta, `expira_en = now() + timeout_sucursal`.
  3. Se llama `CobroIntegracionService::iniciarCobro(...)` que delega al Gateway correspondiente.
  4. El Gateway devuelve `qr_data` (base64 o string del QR) + `external_reference` que se guardan en la transacción.
  5. La UI abre modal "Esperando pago" con QR visible, contador regresivo, botón "Cancelar".
  6. Se suscribe a un canal Reverb privado: `comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}`.

### RF-06: Modo cambiable al momento de cobrar

- Si la FP tiene `modos_permitidos` con más de una opción, el cajero ve un selector "Modo" antes de confirmar el cobro. Default = `modo_default` de la FP.
- Al elegir y confirmar, se usa ese modo. La transacción guarda `modo_usado`.

### RF-07: Cobro sincrónico — espera y resolución

- El modal de espera muestra: QR, monto, contador regresivo (segundos al timeout), botón "Cancelar cobro", botón "Confirmar manualmente" (oculto por default, RF-12).
- **Si llega evento Reverb `pago_confirmado`**:
  - Modal cierra automáticamente.
  - Venta se materializa: stock, caja, CC, AFIP, eventos.
  - Toast "Pago confirmado" verde.
  - Se redirige al flujo post-venta normal (imprimir, nueva venta, etc.).
- **Si llega evento Reverb `pago_fallido` o `pago_rechazado`**:
  - Modal muestra mensaje + botón "Reintentar" (vuelve a iniciar el cobro) + "Cancelar venta".
- **Si el cajero presiona "Cancelar"**:
  - Se llama `CobroIntegracionService::cancelarCobro(transaccion)` que avisa al gateway (si el modo lo permite) y marca la transacción `cancelado`.
  - La venta se anula (estado `anulada_por_integracion`).
- **Si vence el timeout**:
  - Job programado (`ExpirarTransaccionesIntegracionPagoJob`) cada minuto marca transacciones vencidas como `expirado`.
  - Se broadcast `pago_expirado` por Reverb si todavía hay alguien esperando.
  - Modal muestra "Tiempo agotado, el cliente no pagó" + "Reintentar" o "Cancelar venta".

### RF-08: Webhook MP — recepción y resolución

- Endpoint público: `POST /api/integraciones/mercadopago/webhook` (sin auth, pero con verificación de firma `x-signature`).
- El handler:
  1. Valida firma con el `webhook_secret` (si está configurado).
  2. Parsea el payload (notification de MP: `topic=payment`, `id=...`).
  3. Consulta el detalle del payment via API MP usando access_token.
  4. Extrae `payment.collector_id` (= `user_id_mp` de la cuenta MP).
  5. Busca `IntegracionPagoSucursal` por `user_id_mp` (con `comercio_id` resuelto vía relación).
  6. Si encuentra: resuelve la conexión tenant correspondiente, busca la transacción pendiente por `external_reference` o `external_id` y procesa.
  7. Procesamiento:
     - `payment.status = approved` → transacción `confirmado` → materializar venta → broadcast Reverb.
     - `payment.status = rejected` → transacción `fallido` → broadcast.
     - `payment.status = cancelled` → transacción `cancelado`.
     - Otros estados (`pending`, `in_process`) → ignorar (no es estado terminal).
  8. Idempotente: si la transacción ya está en estado terminal, devuelve `200 OK` sin hacer nada.
- Loguea todo el payload en tabla `integraciones_pago_eventos` (auditoría).

### RF-09: QR estático (monto libre) con matching automático

- Para FP con modo `qr_estatico`, el flujo es diferente:
  1. Al iniciar el cobro, el sistema genera o reutiliza un QR estático configurado (asociado a la cuenta MP de la sucursal).
  2. La transacción se crea con `modo_usado=qr_estatico` y monto esperado.
  3. El cajero muestra el QR (puede ser el mismo siempre, impreso en caja).
  4. Cliente paga "monto libre" en su app MP.
  5. Webhook llega → no hay `external_reference` único (porque el QR es compartido). El handler hace matching:
     - Filtra transacciones pendientes en la sucursal correcta (vía `user_id_mp`).
     - Match por `monto` exacto + ventana temporal (creadas en últimos N segundos, donde N = timeout de la sucursal).
     - Si **match único**: confirma esa transacción.
     - Si **múltiple match** o **ningún match**: marca el pago en `integraciones_pago_eventos` como `sin_match_automatico` (para revisión manual / conciliación).

### RF-10: Pagos mixtos con integración

- Si la venta tiene FP mixtas (efectivo + MP, por ejemplo):
  - El sistema solo genera el QR por la porción MP.
  - La venta queda `pendiente_pago_integracion` igual que en RF-05.
  - Al confirmar MP, la venta se materializa con TODOS los pagos (efectivo se asienta junto con MP en la misma transacción).
  - Si MP falla o se cancela: se anula la venta entera (no se cobra el efectivo solo).

### RF-11: Cancelación y reintento

- Cancelar transacción pendiente:
  - `CobroIntegracionService::cancelarCobro(transaccion)` → Gateway intenta cancelar en el proveedor (si MP lo soporta para QR pendiente), marca transacción `cancelado` y anula la venta asociada.
- Reintentar después de fallo/expiración:
  - El cajero presiona "Reintentar" → se crea una NUEVA transacción (no se reutiliza la anterior), se vuelve a llamar `iniciarCobro`.
  - La venta sigue en `pendiente_pago_integracion` (no se recrea).

### RF-12: Confirmación manual de fallback (QR estático)

- Para modo `qr_estatico` (y opcionalmente para `qr_dinamico` con flag), el modal muestra botón "Confirmar manualmente" (con permiso `integraciones_pago.confirmar_manual`).
- Al click: abre sub-modal "¿Confirmás que el cliente pagó? Solo usar si el sistema no detecta el pago automáticamente. Esta acción queda auditada."
- Confirma → marca la transacción `confirmado_manual` (estado nuevo, distinto de `confirmado`) y materializa la venta.
- Auditoría: queda registro de qué usuario confirmó manualmente, cuándo y por qué (campo opcional `motivo`).

### RF-13: Estado de la venta y transiciones

- Nuevo estado de venta: `pendiente_pago_integracion`.
- Transiciones permitidas:
  - `pendiente_pago_integracion` → `activa` (al confirmar pago).
  - `pendiente_pago_integracion` → `anulada` (al cancelar o expirar).
- Las ventas en `pendiente_pago_integracion` **NO aparecen en**: reportes de ventas, listado normal, conciliación de caja.
- Sí aparecen en una nueva sección "Ventas pendientes de pago" (componente futuro) para auditoría.

### RF-14: Reverb broadcasts

- Channel: `comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}` (privado).
- Eventos:
  - `IntegracionPagoConfirmado` (con `transaccion_id`, `venta_id`, `payment_id_externo`).
  - `IntegracionPagoFallido` (con `transaccion_id`, `motivo`).
  - `IntegracionPagoExpirado` (con `transaccion_id`).
  - `IntegracionPagoCancelado` (con `transaccion_id`).
- Todos implementan `ShouldBroadcastNow` (latencia mínima).
- Channel autorizado en `routes/channels.php`: solo usuarios del comercio dueño.

### RF-15: Auditoría y eventos

- Cada cambio de estado de una transacción se guarda en `integraciones_pago_eventos`:
  - `transaccion_id`, `evento` (`creado`, `iniciado_en_gateway`, `webhook_recibido`, `confirmado`, `fallido`, `expirado`, `cancelado`, `confirmado_manual`).
  - `payload_externo` (JSON con lo que respondió el gateway).
  - `metadata` (JSON adicional).
  - `created_at`.
- Útil para soporte: "¿qué pasó con el pago X?"

### RF-16: Job de expiración

- Comando programado `php artisan integraciones-pago:expirar-pendientes` que corre cada minuto en el scheduler.
- Itera todas las transacciones `pendiente` con `expira_en < now()`:
  - Las marca `expirado`.
  - Broadcast `IntegracionPagoExpirado` por Reverb.
  - Anula la venta asociada (a `anulada` con motivo `timeout_integracion_pago`).
- Itera comercios (multi-tenant) usando el patrón estándar.

### RF-17: Test connection desde UI

- En la pantalla de config (RF-02), botón "Probar conexión" llama a `Gateway::probarConexion(integracionSucursal)` que:
  - Para MP: GET `/users/me` con el access_token. Si responde 200 con `id` que matchea `user_id_mp` configurado → OK.
  - Si no → error con detalle (token inválido, user_id no matchea, etc.).
- UI muestra resultado: ✓ verde "Conexión OK, usuario MP: {nickname}" o ✗ rojo con detalle del error.

### RF-18: Multi-tenant safety

- Las credenciales están en tablas tenant (`{prefijo}_integraciones_pago_sucursales`).
- El webhook global resuelve el comercio buscando en TODOS los tenants por `user_id_mp` (índice global o catálogo en `config`). **Decisión técnica clave**: para evitar query N×tenants, se mantiene una tabla `mercadopago_collector_index` en la conexión `config` con `user_id_mp` → `comercio_id` + `sucursal_id`. Se actualiza al crear/editar/borrar `IntegracionPagoSucursal`.

---

## Modelo de Datos

### Tablas nuevas (TENANT — prefijo `{NNNNNN}_`)

#### `integraciones_pago` (catálogo, semilla)

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `codigo` | varchar(50) UNIQUE | — | Slug único (`mercadopago`, `modo`, …) |
| `nombre` | varchar(100) | — | Nombre visible |
| `descripcion` | text NULL | — | Texto descriptivo opcional |
| `modos_disponibles` | json | `[]` | Lista de modos soportados (ej: `["qr_dinamico","qr_estatico"]`) |
| `gateway_class` | varchar(255) | — | FQCN (ej: `App\Services\IntegracionesPago\MercadoPagoGateway`) |
| `activo` | tinyint(1) | 1 | |
| `orden` | int | 0 | Orden en UI |
| `created_at` / `updated_at` | timestamps | — | |

**Semilla**: 1 fila → `mercadopago` con modos `["qr_dinamico","qr_estatico"]` y `gateway_class=App\Services\IntegracionesPago\MercadoPagoGateway`.

#### `integraciones_pago_sucursales`

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `integracion_pago_id` | bigint FK | — | → `integraciones_pago` |
| `sucursal_id` | bigint FK | — | → `sucursales` |
| `modo` | enum('test','produccion') | 'test' | Cuál set de credenciales usar |
| `access_token_produccion` | text NULL | — | Encriptado (Crypt) |
| `access_token_test` | text NULL | — | Encriptado |
| `public_key_produccion` | varchar(255) NULL | — | |
| `public_key_test` | varchar(255) NULL | — | |
| `user_id_externo` | varchar(100) NULL | — | `user_id` MP (clave para resolución webhook) |
| `webhook_secret` | text NULL | — | Encriptado, para verificar firma |
| `config_adicional` | json NULL | — | Campos específicos del proveedor (ej: `qr_estatico_url`) |
| `timeout_segundos` | int | 300 | Timeout cobro sincrónico |
| `activo` | tinyint(1) | 1 | |
| `created_at` / `updated_at` | timestamps | — | |

**Constraints**:
- UNIQUE(`integracion_pago_id`, `sucursal_id`): una sucursal solo puede tener una config por integración.

#### `integraciones_pago_transacciones`

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `integracion_pago_sucursal_id` | bigint FK | — | → `integraciones_pago_sucursales` |
| `forma_pago_id` | bigint FK | — | → `formas_pago` |
| `sucursal_id` | bigint FK | — | denormalizado (consultas) |
| `caja_id` | bigint FK NULL | — | denormalizado |
| `usuario_iniciador_id` | bigint FK | — | → `users` |
| `modo_usado` | varchar(50) | — | `qr_dinamico`, `qr_estatico`, … |
| `monto` | decimal(15,2) | — | |
| `moneda_id` | bigint FK NULL | — | Si aplica |
| `external_reference` | varchar(100) UNIQUE NULL | — | Ref enviada al gateway (para matching) |
| `external_id` | varchar(100) NULL | — | `payment_id` de MP (al confirmar) |
| `qr_data` | text NULL | — | Base64 del QR o string completo |
| `link_pago` | varchar(500) NULL | — | URL si modo `link` (futuro) |
| `estado` | enum | 'pendiente' | `pendiente`, `confirmado`, `confirmado_manual`, `fallido`, `expirado`, `cancelado`, `sin_match` |
| `expira_en` | timestamp | — | `created_at + timeout_segundos` |
| `confirmado_en` | timestamp NULL | — | |
| `payload_respuesta` | json NULL | — | Lo que devolvió el gateway al iniciar |
| `metadata` | json NULL | — | Extra |
| `cobrable_type` | varchar(255) | — | Polimórfico: `App\Models\Venta`, `App\Models\PedidoMostrador`, … |
| `cobrable_id` | bigint | — | ID del cobrable |
| `created_at` / `updated_at` | timestamps | — | |

**Índices**:
- INDEX(`estado`, `expira_en`) para el job de expiración.
- INDEX(`integracion_pago_sucursal_id`, `estado`, `monto`, `created_at`) para matching QR estático.
- INDEX(`cobrable_type`, `cobrable_id`) para buscar la transacción de una venta.

#### `integraciones_pago_eventos`

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `transaccion_id` | bigint FK NULL | — | → `integraciones_pago_transacciones` (NULL si webhook sin match) |
| `integracion_pago_sucursal_id` | bigint FK NULL | — | denormalizado |
| `evento` | varchar(50) | — | `creado`, `webhook_recibido`, `confirmado`, `confirmado_manual`, `fallido`, `expirado`, `cancelado`, `sin_match`, `error` |
| `payload_externo` | json NULL | — | |
| `metadata` | json NULL | — | |
| `created_at` | timestamp | — | (sin updated_at) |

### Tablas nuevas (CONFIG — DB compartida)

#### `mercadopago_collector_index`

Resolución rápida de webhook sin recorrer tenants.

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `user_id_externo` | varchar(100) | — | `user_id` MP |
| `modo` | enum('test','produccion') | — | |
| `comercio_id` | bigint FK | — | → `comercios` |
| `sucursal_id` | bigint | — | (sin FK, vive en tenant) |
| `integracion_pago_sucursal_id` | bigint | — | (sin FK, vive en tenant) |
| `activo` | tinyint(1) | 1 | |
| `created_at` / `updated_at` | timestamps | — | |

**Constraint**: UNIQUE(`user_id_externo`, `modo`) — un user_id MP no puede estar en dos sucursales/comercios a la vez.

### Tablas modificadas

#### `formas_pago` — Cambios (TENANT)

- Agregar `integracion_pago_id` (bigint FK NULL) AFTER `concepto_pago_id`. → `integraciones_pago`.
- Agregar `modo_default` (varchar(50) NULL) AFTER `integracion_pago_id`.
- Agregar `modos_permitidos` (json NULL) AFTER `modo_default`. Lista de modos overridables al cobrar.

#### `conceptos_pago` — Cambios (TENANT)

- Agregar `permite_integracion` (tinyint(1), default 0) AFTER `permite_vuelto`.
- Seed update: setear `permite_integracion=1` para códigos `wallet` y `transferencia`.

#### `ventas` — Cambios (TENANT)

- Agregar `estado_pago_integracion` (enum NULL: `pendiente`, `confirmado`, `expirado`, `cancelado`, `fallido`, `confirmado_manual`) AFTER `estado`.
- Agregar `integracion_pago_transaccion_id` (bigint FK NULL) AFTER `estado_pago_integracion`. → `integraciones_pago_transacciones`.
- Modificar enum `estado` para incluir `pendiente_pago_integracion`.

#### `pedido_mostrador` — Cambios (TENANT) — Fase 2

- Similar a ventas: `estado_pago_integracion`, `integracion_pago_transaccion_id`. (Diferido a fase 2 por scope.)

---

## Modelos PHP

### `App\Models\IntegracionPago`

- Conexión: `pymes_tenant`.
- Tabla: `integraciones_pago`.
- Casts: `modos_disponibles => array`, `activo => boolean`.
- Relaciones: `sucursales() HasMany IntegracionPagoSucursal`.
- Helper: `getGatewayInstance(): IntegracionPagoGatewayContract` → instancia el gateway desde `gateway_class`.
- Scope: `scopeActivas`.

### `App\Models\IntegracionPagoSucursal`

- Conexión: `pymes_tenant`.
- Tabla: `integraciones_pago_sucursales`.
- Casts: `config_adicional => array`, `activo => boolean`.
- **Mutators encriptados**: `access_token_produccion`, `access_token_test`, `webhook_secret` usan `Crypt::encryptString` al setear y `Crypt::decryptString` al obtener (vía accessor/mutator de Eloquent).
- Helper: `getAccessTokenActivo(): string` → devuelve prod o test según `modo`.
- Helper: `getPublicKeyActivo(): string`.
- Relaciones: `integracion()`, `sucursal()`.
- Hook `saved`/`deleted`: actualiza `mercadopago_collector_index` (config DB).

### `App\Models\IntegracionPagoTransaccion`

- Conexión: `pymes_tenant`.
- Tabla: `integraciones_pago_transacciones`.
- Casts: `payload_respuesta => array`, `metadata => array`, `expira_en => datetime`, `confirmado_en => datetime`.
- Relaciones:
  - `integracionSucursal()`.
  - `formaPago()`.
  - `sucursal()`.
  - `cobrable()` MorphTo → Venta, PedidoMostrador, futuros.
  - `usuarioIniciador()`.
  - `eventos() HasMany`.
- Scopes: `scopePendientes`, `scopeExpiradas`, `scopeConfirmadas`, `scopePorCobrable($type, $id)`.

### `App\Models\IntegracionPagoEvento`

- Conexión: `pymes_tenant`.
- Tabla: `integraciones_pago_eventos`.
- Solo timestamps de `created_at`.
- Relaciones: `transaccion()`, `integracionSucursal()`.

### `App\Models\MercadoPagoCollectorIndex` (config)

- Conexión: `config`.
- Tabla: `mercadopago_collector_index`.

---

## Services y Gateways

### Contrato `App\Services\IntegracionesPago\Contracts\IntegracionPagoGatewayContract`

```php
interface IntegracionPagoGatewayContract
{
    /**
     * Inicia un cobro en el proveedor. Devuelve datos para mostrar al cliente (QR, link).
     * @param IntegracionPagoSucursal $config Credenciales y modo
     * @param IntegracionPagoTransaccion $transaccion Transacción pre-creada con estado pendiente
     * @return array{qr_data?: string, link?: string, external_reference: string, payload: array}
     */
    public function iniciarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array;

    /**
     * Consulta el estado actual del cobro en el proveedor.
     * @return array{estado: string, payload: array}  Estados normalizados: 'pendiente','aprobado','rechazado','cancelado'
     */
    public function consultarEstado(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array;

    /**
     * Cancela un cobro pendiente (si el proveedor lo soporta para el modo).
     */
    public function cancelarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): bool;

    /**
     * Procesa un webhook entrante. Devuelve la transacción matcheada y el nuevo estado.
     * @return array{transaccion: ?IntegracionPagoTransaccion, estado: string, payload: array, match_type: string}
     */
    public function procesarWebhook(array $payload, array $headers): array;

    /**
     * Verifica que las credenciales sean válidas. Retorna info de la cuenta o lanza excepción.
     */
    public function probarConexion(IntegracionPagoSucursal $config): array;

    /**
     * Lista los modos que este gateway soporta.
     */
    public function modosSoportados(): array;
}
```

### `App\Services\IntegracionesPago\MercadoPagoGateway implements IntegracionPagoGatewayContract`

- Usa SDK oficial de MP (paquete `mercadopago/dx-php`).
- Implementa los 6 métodos del contrato.
- Cada método loguea con `Log::info`/`Log::error`.
- Helper privado para construir cliente HTTP MP (`$this->getClient($config)`).
- `iniciarCobro`:
  - Para `qr_dinamico`: crea un "Order" o usa API de QR de tienda asociada al user_id.
  - Para `qr_estatico`: devuelve la URL/data del QR ya configurado en `config_adicional.qr_estatico_url` (sin crear orden en MP).
- `procesarWebhook`:
  - Lee `payload.type=payment` y `payload.data.id`.
  - GET `/v1/payments/{id}` con access_token correcto (resuelve sucursal vía `mercadopago_collector_index` por `payment.collector_id`).
  - Si es modo `qr_dinamico`: busca transacción por `external_reference == payment.external_reference`.
  - Si es modo `qr_estatico`: matching por (`monto`, `created_at` en ventana, `sucursal_id`).

### `App\Services\IntegracionesPago\CobroIntegracionService`

API que consumen NuevaVenta y futuros módulos. **Es el contrato único de cobro**.

```php
class CobroIntegracionService
{
    /**
     * Inicia un cobro con integración. Crea la transacción y dispara al gateway.
     */
    public function iniciarCobro(array $data): IntegracionPagoTransaccion;
    // $data: ['forma_pago_id', 'sucursal_id', 'caja_id', 'modo', 'monto', 'cobrable_type', 'cobrable_id', 'usuario_id']

    /**
     * Cancela una transacción pendiente.
     */
    public function cancelarCobro(IntegracionPagoTransaccion $transaccion): void;

    /**
     * Confirma manualmente una transacción (RF-12).
     */
    public function confirmarManual(IntegracionPagoTransaccion $transaccion, int $usuarioId, ?string $motivo = null): void;

    /**
     * Procesa el resultado de un webhook ya parseado.
     * Materializa el cobrable (venta) en estado activa, broadcast Reverb.
     */
    public function procesarConfirmacion(IntegracionPagoTransaccion $transaccion, array $payload): void;

    /**
     * Expira transacciones vencidas (llamado por el job programado).
     */
    public function expirarPendientesVencidas(): int;
}
```

- Usa `DB::connection('pymes_tenant')->transaction()` en cada operación crítica.
- Loguea cada operación.
- Maneja eventos `IntegracionPagoConfirmado`, `IntegracionPagoFallido`, etc.

### `App\Services\IntegracionesPago\WebhookResolverService`

- `resolverDesdeMercadoPago(array $payload): ?IntegracionPagoSucursal`:
  - Lee `collector_id` del payload (puede requerir consulta a MP API).
  - Busca en `MercadoPagoCollectorIndex` por `user_id_externo`.
  - Devuelve el comercio + sucursal correspondiente.
  - Setea contexto tenant.
- Tabla de resolución vive en DB `config` para evitar recorrer N tenants.

### `App\Services\IntegracionesPago\IntegracionPagoSucursalService`

- `actualizar(IntegracionPagoSucursal $config, array $data)`: actualiza la config y sincroniza el índice global.
- `crear(array $data)`: crea + sincroniza.
- `eliminar(IntegracionPagoSucursal $config)`: borra + limpia índice.
- `sincronizarIndice(IntegracionPagoSucursal $config)`: actualiza `mercadopago_collector_index` (UPSERT).

### Modificaciones a `VentaService`

- Agregar `crearVentaConIntegracion(array $data, array $detalles)`: crea la venta en estado `pendiente_pago_integracion` SIN tocar caja, stock, CC, AFIP. Devuelve la venta.
- Agregar `materializarVentaPendiente(Venta $venta, IntegracionPagoTransaccion $transaccion)`: ejecuta el flujo completo de materialización (stock, caja, CC, AFIP, eventos) cuando llega el confirmado.
- Agregar `anularVentaPendiente(Venta $venta, string $motivo)`: anula una venta que estaba esperando integración.

---

## Pantallas UI

### Pantalla 1: Configuración de Integraciones de Pago (`/configuracion/integraciones-pago`)

**Componente**: `App\Livewire\Configuracion\IntegracionesPago`
**Traits**: `SucursalAware = NO` (gestiona TODAS las sucursales desde un solo lugar). Ninguno.

- Header: "Integraciones de Pago" + breve descripción.
- Por cada integración del catálogo (activa): card con nombre + descripción + lista de sucursales del comercio.
- Por cada sucursal: badge "Configurado / Sin configurar / Inactivo".
- Acciones por sucursal: "Configurar" (abre `<x-bcn-modal>` con form), "Probar conexión" (botón secundario, solo si configurado), "Eliminar" (con confirmación, solo si configurado).
- Permisos: requiere `integraciones_pago.administrar`.

### Pantalla 2: Modal de Configuración (sub-componente del anterior)

- `<x-bcn-modal>` (header color azul, slot body + footer).
- Form con campos de RF-02. Validación: si `modo=produccion`, `access_token_produccion` requerido. Si `modo=test`, `access_token_test` requerido.
- Botón "Guardar" + "Probar conexión".

### Pantalla 3: Formulario FormaPago — bloque "Integración" (modificar existente)

- En el componente actual de FormaPago, agregar un acordeón o sección "Integración de pago" (colapsada por default).
- Visible solo si el concepto seleccionado tiene `permite_integracion=true`.
- Campos: selector integración, modo default, modos overridables.

### Pantalla 4: Modal "Esperando pago" (sub-componente reutilizable)

**Componente**: `App\Livewire\IntegracionesPago\ModalEsperandoPago` (Livewire para tener listener Reverb).
- Recibe `transaccion_id` y se suscribe al canal Reverb.
- Muestra: QR (img de base64), monto grande, countdown, botón "Cancelar", botón "Confirmar manual" (si permiso + modo permite).
- Listener Echo:
  - `IntegracionPagoConfirmado` → emite evento al padre `pago-confirmado` → padre cierra modal + redirige.
  - `IntegracionPagoFallido` → muestra error + opciones reintentar/cancelar.
  - `IntegracionPagoExpirado` → idem.
- Polling como fallback: cada 30s consulta endpoint interno por estado (red de seguridad). **Diferido a fase 5**.

### Pantalla 5: Integración en NuevaVenta

- Al confirmar venta, si alguna FP del desglose tiene integración:
  - Antes de procesar normalmente, abre `ModalEsperandoPago` con la transacción creada.
  - Si confirma → procesa la venta como hoy (impresión, nueva venta, etc.).
  - Si cancela → no procesa, deja al usuario en el carrito.

---

## Endpoints / Routes

### Webhook público

```php
// routes/api.php
Route::post('/integraciones/mercadopago/webhook', [WebhookMercadoPagoController::class, 'handle'])
    ->name('integraciones.mercadopago.webhook')
    ->middleware('throttle:60,1'); // protección DoS
```

### Consulta de estado (interno, para polling fallback)

```php
// routes/web.php (con auth)
Route::get('/integraciones-pago/transacciones/{transaccion}/estado', ...)
    ->middleware(['auth', 'tenant'])
    ->name('integraciones-pago.estado');
```

### Controllers

- `App\Http\Controllers\Api\WebhookMercadoPagoController` → maneja el webhook, delega a `MercadoPagoGateway::procesarWebhook` y `CobroIntegracionService::procesarConfirmacion`.

---

## Eventos / Broadcasts

```php
namespace App\Events\IntegracionesPago;

class IntegracionPagoConfirmado extends TenantBroadcastEvent implements ShouldBroadcastNow
{
    // payload: transaccion_id, venta_id, payment_id_externo
    // channel: comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}
}

class IntegracionPagoFallido extends TenantBroadcastEvent implements ShouldBroadcastNow { ... }
class IntegracionPagoExpirado extends TenantBroadcastEvent implements ShouldBroadcastNow { ... }
class IntegracionPagoCancelado extends TenantBroadcastEvent implements ShouldBroadcastNow { ... }
```

`routes/channels.php`:
```php
Broadcast::channel('comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}', function ($user, $comercioId, $transaccionId) {
    return $user->comercios->contains($comercioId);
});
```

---

## Comando programado

### `php artisan integraciones-pago:expirar-pendientes`

- `App\Console\Commands\ExpirarTransaccionesIntegracionPagoCommand`.
- Recorre comercios → setea contexto tenant → llama `CobroIntegracionService::expirarPendientesVencidas()`.
- Registrado en `Kernel::schedule()` cada minuto.

---

## Permisos nuevos

| Permiso | Descripción | Asignado por default a |
|---------|-------------|------------------------|
| `integraciones_pago.administrar` | Configurar integraciones por sucursal | Admin |
| `integraciones_pago.ver_transacciones` | Ver historial de transacciones (pantalla futura) | Admin, Cajero |
| `integraciones_pago.confirmar_manual` | Confirmar manualmente un cobro pendiente | Admin, Encargado |
| `integraciones_pago.cancelar` | Cancelar un cobro pendiente | Admin, Encargado, Cajero |

Agregar a `ProvisionComercioCommand::seedRolesYPermisos()`.

---

## Item del menú

Agregar item en `menu_items` (shared):
- `parent=Configuración`, `nombre=Integraciones de Pago`, `slug=integraciones-pago`, `ruta=configuracion.integraciones-pago`, `icono=...`, `permission=integraciones_pago.administrar`.

---

## Migraciones Necesarias (en orden)

1. **`create_mercadopago_collector_index`** (config DB) — crea tabla resolutora.
2. **`create_integraciones_pago_table`** (tenant) — catálogo.
3. **`create_integraciones_pago_sucursales_table`** (tenant) — config por sucursal.
4. **`create_integraciones_pago_transacciones_table`** (tenant) — transacciones.
5. **`create_integraciones_pago_eventos_table`** (tenant) — auditoría.
6. **`add_integracion_a_formas_pago`** (tenant) — agrega columnas a `formas_pago`.
7. **`add_permite_integracion_a_conceptos_pago`** (tenant) — agrega columna + actualiza seed.
8. **`add_estado_pago_integracion_a_ventas`** (tenant) — modifica enum + agrega FK.
9. **`add_estado_pago_integracion_a_pedido_mostrador`** (tenant) — FASE 2 (diferido).

Cada migración tenant: itera comercios, SQL raw con prefijo, try/catch por comercio. **Regenerar `database/sql/tenant_tables.sql` después de cada batch**.

Semillas via `ProvisionComercioCommand`:
- Seed `integraciones_pago` con fila MP.
- Update conceptos `wallet` y `transferencia` con `permite_integracion=1`.
- Agregar permisos nuevos en `seedRolesYPermisos`.

---

## Traducciones

Claves nuevas (en `lang/es.json`, `lang/en.json`, `lang/pt.json`, orden alfabético):

| Clave (es) | en | pt |
|------------|-----|-----|
| `Integraciones de Pago` | `Payment Integrations` | `Integrações de Pagamento` |
| `Configurar integración` | `Configure integration` | `Configurar integração` |
| `Modo (Test / Producción)` | `Mode (Test / Production)` | `Modo (Teste / Produção)` |
| `Access Token` | `Access Token` | `Access Token` |
| `Public Key` | `Public Key` | `Public Key` |
| `Probar conexión` | `Test connection` | `Testar conexão` |
| `Conexión OK` | `Connection OK` | `Conexão OK` |
| `Esperando pago...` | `Waiting for payment...` | `Aguardando pagamento...` |
| `Pago confirmado` | `Payment confirmed` | `Pagamento confirmado` |
| `Pago rechazado` | `Payment rejected` | `Pagamento rejeitado` |
| `Tiempo agotado, el cliente no pagó` | `Time out, customer did not pay` | `Tempo esgotado, o cliente não pagou` |
| `Cancelar cobro` | `Cancel charge` | `Cancelar cobrança` |
| `Confirmar manualmente` | `Confirm manually` | `Confirmar manualmente` |
| `Reintentar` | `Retry` | `Tentar novamente` |
| `Pendiente de pago (integración)` | `Pending payment (integration)` | `Pendente de pagamento (integração)` |
| `QR dinámico` | `Dynamic QR` | `QR dinâmico` |
| `QR estático` | `Static QR` | `QR estático` |
| `Mercado Pago` | `Mercado Pago` | `Mercado Pago` |
| `Modo de cobro` | `Charge mode` | `Modo de cobrança` |
| `Integración de pago` | `Payment integration` | `Integração de pagamento` |
| `Timeout (segundos)` | `Timeout (seconds)` | `Timeout (segundos)` |
| `User ID Mercado Pago` | `Mercado Pago User ID` | `User ID Mercado Pago` |
| `Webhook Secret` | `Webhook Secret` | `Webhook Secret` |
| `Sin configurar` | `Not configured` | `Não configurado` |
| `Configurado` | `Configured` | `Configurado` |

---

## Dependencias externas

- Composer: `mercadopago/dx-php` (SDK oficial PHP).
- Reverb ya configurado (no requiere cambios).
- ngrok / dominio público para testing local del webhook MP (documentar en `.claude/docs/server-config.md`).

---

## Criterios de Aceptación

### Funcionales

- [ ] **CA-01**: Existe pantalla `Configuración → Integraciones de Pago` accesible solo con permiso `integraciones_pago.administrar`.
- [ ] **CA-02**: Admin puede configurar MercadoPago en una sucursal con access_token, public_key, user_id y modo test/prod.
- [ ] **CA-03**: Las credenciales se guardan encriptadas en la BD (verificable inspeccionando la tabla).
- [ ] **CA-04**: Botón "Probar conexión" verifica credenciales contra `/users/me` y devuelve OK con nickname MP o error claro.
- [ ] **CA-05**: Una FormaPago con concepto `wallet` puede asignarse a la integración MP con modo default `qr_dinamico` y modos permitidos `[qr_dinamico, qr_estatico]`.
- [ ] **CA-06**: Crear una venta en NuevaVenta con esa FP → se persiste en estado `pendiente_pago_integracion` (NO toca stock/caja/CC).
- [ ] **CA-07**: Aparece modal "Esperando pago" con QR visible (decodificable, escaneable) y countdown.
- [ ] **CA-08**: Cliente paga en MP → webhook llega → modal cierra automáticamente → venta queda activa con todo materializado.
- [ ] **CA-09**: Si el cajero cancela → venta se anula limpiamente, no quedan residuos.
- [ ] **CA-10**: Si pasa el timeout sin pago → job marca expirada, modal muestra "Tiempo agotado", venta se anula.
- [ ] **CA-11**: Webhook idempotente: llamar 2 veces con el mismo payment_id no duplica materialización.
- [ ] **CA-12**: Pago mixto (efectivo + MP) genera QR solo por monto MP. Al confirmar MP, la venta se materializa con AMBOS pagos en la misma transacción DB.
- [ ] **CA-13**: QR estático: cliente paga monto X, webhook llega, sistema matchea por (monto + ventana temporal + sucursal). Si matchea único → confirma. Si no → queda en `sin_match` con evento auditado.
- [ ] **CA-14**: Confirmación manual: con permiso, el cajero puede forzar confirmación. Queda registrado en `integraciones_pago_eventos` con estado `confirmado_manual`.
- [ ] **CA-15**: Cambiar el modo al momento de cobrar (si FP lo permite) usa el modo elegido y queda registrado en `modo_usado` de la transacción.
- [ ] **CA-16**: Multi-tenant: webhook entrante con `user_id_mp` de comercio A NO toca datos de comercio B.

### No-funcionales

- [ ] **CA-17**: Tests unitarios pasan (Gateway, Service, modelos).
- [ ] **CA-18**: Test de integración: simulación de webhook MP confirma una venta end-to-end.
- [ ] **CA-19**: Lint Pint OK.
- [ ] **CA-20**: `database/sql/tenant_tables.sql` regenerado y commiteado.
- [ ] **CA-21**: Traducciones presentes en es/en/pt, orden alfabético.
- [ ] **CA-22**: `manual-usuario.md` y `ai-knowledge-base.md` actualizados vía `@docs-sync` al crear PR.
- [ ] **CA-23**: Smoke test `Livewire::test()->assertOk()` para `IntegracionesPago`, `ModalEsperandoPago`.
- [ ] **CA-24**: Logging completo en cada operación crítica del Service y Gateway (revisable en `storage/logs/`).

---

## Plan de Implementación (por fases)

> Cada fase produce código funcional y testeable. NO se mergea fase siguiente hasta que la anterior esté verde.

### Fase 1: Esqueleto BD + Modelos + Catálogo [COMPLETO — 2026-05-26]

1. ✅ Migración bundle tenant (4 tablas + columna `permite_integracion` en `conceptos_pago`) consolidada en un solo archivo siguiendo patrón `2026_05_11_170438_create_pedidos_mostrador_tables.php` por interdependencia de FKs.
2. ✅ Migración config para `mercadopago_collector_index`.
3. ✅ Migración de 4 permisos funcionales (`integraciones_pago.administrar`, `.ver_transacciones`, `.confirmar_manual`, `.cancelar`) asignados a Administrador y Super Administrador.
4. ✅ 5 modelos: `IntegracionPago`, `IntegracionPagoSucursal` (con cast `encrypted` para credenciales + hooks que sincronizan índice global ante create/update/delete/cambio de user_id), `IntegracionPagoTransaccion` (polimórfica), `IntegracionPagoEvento` (append-only, sin updated_at), `MercadoPagoCollectorIndex` (config DB).
5. ✅ Contrato `IntegracionPagoGatewayContract` definido (implementación en Fase 3).
6. ✅ `ProvisionComercioCommand` actualizado: nuevo paso `seedIntegracionesPago` (siembra MP en catálogo) + array de conceptos actualizado con `permite_integracion` (true para `wallet` y `transferencia`).
7. ✅ `database/sql/tenant_tables.sql` regenerado (4 tablas insertadas en posición alfabética, verificado provisioning OK contra BD limpia con 116 tablas).
8. ✅ 16 tests en `IntegracionPagoTest` (42 assertions) — encriptación, scopes, hooks de sincronización del índice, polimorfismo, helpers de estado, integridad timestamps.
9. ✅ Lint Pint OK en 12 archivos modificados.
10. ✅ `tests/Traits/WithTenant.php` actualizado con las 4 tablas nuevas en `$testTables` para limpieza selectiva entre tests.

**Entregable**: tablas creadas, modelos funcionando con encriptación at-rest y sincronización multi-tenant del índice, semillas listas para comercios nuevos, permisos asignados, todo verificado por tests.

### Fase 2: UI de Configuración + Service Sucursal [PENDIENTE]

1. `IntegracionPagoSucursalService` (crear, actualizar, eliminar, sincronizar índice).
2. Componente Livewire `Configuracion\IntegracionesPago` + vista + modal config.
3. Ruta + permiso + menu visible.
4. Sin gateway todavía: solo gestión CRUD de credenciales.
5. Smoke test del componente.
6. Test de service.

**Entregable**: admin puede cargar credenciales MP por sucursal, se guardan encriptadas. Sin probar conexión todavía.

### Fase 3: MercadoPagoGateway + Test Connection [PENDIENTE]

1. Composer: agregar SDK `mercadopago/dx-php`.
2. Contrato `IntegracionPagoGatewayContract`.
3. `MercadoPagoGateway` con métodos: `probarConexion`, `modosSoportados`.
4. UI botón "Probar conexión" funcionando.
5. Tests unitarios del gateway (mock SDK MP).

**Entregable**: admin valida sus credenciales contra API MP real.

### Fase 4: FormaPago con Integración + Modos [PENDIENTE]

1. Migración: columnas en `formas_pago`.
2. Actualizar componente Livewire de FormasPago para incluir bloque integración (solo si concepto lo permite).
3. Validaciones modelo: si integración seteada, modo default obligatorio.
4. Tests de model + livewire.
5. Regenerar `tenant_tables.sql`.

**Entregable**: admin puede crear FP "MP - QR Dinámico" apuntando a la integración MP.

### Fase 5: Flujo de cobro sincrónico — QR dinámico [PENDIENTE]

1. Migración: `estado_pago_integracion` y enum en `ventas`.
2. `CobroIntegracionService::iniciarCobro` + `cancelarCobro` + `procesarConfirmacion`.
3. `MercadoPagoGateway::iniciarCobro` para QR dinámico (crear orden MP).
4. `VentaService`: nuevos métodos para venta pendiente y materialización.
5. Modificar `NuevaVenta` Livewire para detectar FP con integración y disparar el flujo.
6. Componente `ModalEsperandoPago`.
7. Eventos Reverb + listeners frontend.
8. Tests de service + integración (mock gateway).

**Entregable**: una venta con FP MP genera QR, el modal queda esperando. Sin webhook todavía (test manual con `php artisan tinker` simulando confirmación).

### Fase 6: Webhook + Resolución Multi-Tenant [PENDIENTE]

1. Migración: `mercadopago_collector_index` (config DB).
2. `WebhookResolverService`.
3. `WebhookMercadoPagoController` + ruta API.
4. Verificación de firma (`x-signature`).
5. Hook de `IntegracionPagoSucursal` que sincroniza el índice.
6. Tests: webhook end-to-end con tenant simulation.

**Entregable**: pagando MP real desde una cuenta de prueba con ngrok apuntando al webhook, una venta se confirma sola.

### Fase 7: QR estático con matching automático [PENDIENTE]

1. `MercadoPagoGateway::iniciarCobro` para QR estático.
2. `MercadoPagoGateway::procesarWebhook` lógica de matching para QR estático.
3. UI: mostrar QR estático configurado en `config_adicional.qr_estatico_url`.
4. Tests de matching (varios casos: único, múltiple, sin match).

**Entregable**: QR estático funcional con matching auto.

### Fase 8: Confirmación manual + Job expiración [PENDIENTE]

1. `CobroIntegracionService::confirmarManual` + permiso.
2. UI: botón "Confirmar manualmente" en modal (con permiso).
3. `ExpirarTransaccionesIntegracionPagoCommand` + registrar en scheduler.
4. Evento `IntegracionPagoExpirado` + listener UI.
5. Tests del job.

**Entregable**: cobertura de casos edge (timeout + fallback manual).

### Fase 9: Pagos mixtos + estabilización [COMPLETO — 2026-06-02]

1. ✅ **Pagos mixtos verificados end-to-end**: el flujo ya estaba construido en `WithPagosDesglose` (Fase 5/7). El desglose admite N pagos, uno de los cuales puede ser de integración (QR); al confirmar el QR se materializan todos los pagos del desglose. No requirió ajustes funcionales en NuevaVenta, sólo trazabilidad.
2. ✅ **Trazabilidad del pago de integración**: nueva columna tenant `venta_pagos.integracion_pago_transaccion_id` (FK nullable a `integraciones_pago_transacciones`, `ON DELETE SET NULL`). En `procesarVentaConDesglose` se vincula al único `venta_pago` cobrado por integración (modo único por FP, Fase 7). Resuelve la ambigüedad de "cuál de los pagos del desglose fue el QR". Helpers: `VentaPago::tieneIntegracionConfirmada()`, `Venta::tieneIntegracionPagoConfirmada()`, relación `VentaPago::integracionTransaccion()`.
3. ✅ **Bloqueo de anulación/modificación (en lugar de refund)**: mientras no exista refund real contra el proveedor, una venta/pago con cobro de integración **confirmado** no puede anularse ni modificarse (la plata ya entró a la cuenta MP). Guard centralizado `VentaService::protegerContraIntegracionConfirmada()` en `cancelarVentaCompleta` (y vía ella `cancelarVenta`) y `anularPagosYPasarACtaCte`. `anularSoloParteFiscal` NO se bloquea (es ajuste fiscal puro, no toca el cobro). Modificación bloqueada en `CambioFormaPagoService::puedeModificarVentaPago()` → cubre `cambiarFormaPago`, `eliminarPagoDeVenta` y la UI de Ventas.
4. ✅ **Tests** (`CobroQrPagoMixtoTest`, 4 tests / 17 assertions): pago mixto efectivo+QR vincula la tx al pago correcto (y NO al efectivo); cobro QR es por la porción de la integración (no el total); no se puede anular venta con integración confirmada; el bloqueo no deja la venta cancelada; no se puede modificar el pago de integración.
5. ✅ Migración aplicada en dev + testing, `tenant_tables.sql` regenerado, traducciones es/en/pt, Pint OK, 30 tests de integración de pagos verdes (sin regresiones).

**Refund real → PENDIENTE FUTURO** (decisión 2026-06-02): no se desarrolla todavía. Cuando se necesite devolver un cobro de integración, hay que: (a) agregar `reembolsar(transaccion)` al Gateway/Service que pegue al endpoint de refund del proveedor, (b) registrar un `IntegracionPagoEvento` de reverso (append-only), (c) recién entonces permitir la anulación de la venta levantando el guard. La columna `integracion_pago_transaccion_id` ya deja el rastro necesario para implementarlo.

**Entregable**: pagos mixtos funcionales + trazabilidad + bloqueo seguro de anulación. ✅

### Fase 10: Documentación + PR [PENDIENTE]

1. Invocar `@docs-sync` para actualizar `manual-usuario.md` y `ai-knowledge-base.md`.
2. Actualizar `.claude/docs/server-config.md` con instrucciones de webhook (URL pública, ngrok, etc.).
3. Crear PR contra master con CI verde.
4. Ejecutar `/sdd-verify` para Spec Compliance Matrix.

**Entregable**: PR mergeable.

---

## Roadmap de crecimiento (la arquitectura está diseñada para esto)

> **Esta sección no es una lista de "deseos" — es el plan explícito para el que se diseñó el MVP.** Cada extensión futura encaja en piezas de la arquitectura SIN rediseño. El MVP construye los cimientos; estas iteraciones son construcción encima.

### Eje 1: Más proveedores de pago

Agregar un proveedor nuevo = **2 acciones**, sin tocar nada del MVP:

1. Crear `App\Services\IntegracionesPago\{Proveedor}Gateway` implementando `IntegracionPagoGatewayContract`.
2. Insertar fila en `integraciones_pago` (vía migración + seed) con `codigo`, `nombre`, `modos_disponibles`, `gateway_class`.

Cero cambios en: modelos, services consumidores (NuevaVenta, etc.), UI de config (es dinámica sobre el catálogo), tablas de transacciones, webhook controller (genérico).

Proveedores en cola:

| Proveedor | Modos esperados | Notas técnicas |
|-----------|-----------------|----------------|
| **MODO** | QR dinámico, QR estático | API REST. Diferencia clave: aglutina múltiples bancos en una sola integración |
| **Cuenta DNI (Banco Provincia)** | QR dinámico | API propia BPBA. Webhook con esquema distinto a MP |
| **PayPal** | Link de pago, checkout embebido | Más para e-commerce que mostrador |
| **Ualá Bis** | QR, link | Crece en mostrador |
| **Naranja X / GetNet** | QR, link, posnet integrado | Posnet integrado = comando POS sin SDK físico |

Cada uno → 1 Gateway PHP nuevo + 1 fila de catálogo. **Sin migración de datos, sin breaking changes, sin downtime.**

### Eje 2: Más modos de cobro dentro de un proveedor (ejemplo MP)

El MVP cubre QR dinámico y QR estático. Los modos siguientes están **previstos** en `modos_disponibles` JSON sin cambios estructurales:

| Modo | Caso de uso | Trabajo necesario |
|------|-------------|-------------------|
| **`link_pago`** (Checkout Pro) | Ventas remotas, mandar por WhatsApp/email | Implementar método en `MercadoPagoGateway::iniciarCobro` para crear preference + UI muestra link copiable en vez de QR |
| **`point`** (Posnet físico MP) | Cliente pasa tarjeta en dispositivo MP | SDK Point + integración Bluetooth/USB. Flujo distinto pero mismo contrato del Service |
| **`suscripcion`** / pagos recurrentes | Membresías, abonos mensuales | Nuevo cobrable polimórfico (`SuscripcionCliente`) + Gateway extendido |
| **`split_payment`** | Marketplaces (un pago se reparte entre varios cobradores) | Requiere campo `splits` en la transacción |

### Eje 3: Más módulos consumidores

`CobroIntegracionService::iniciarCobro($data)` recibe `cobrable_type` polimórfico. **Hoy hay un consumidor (NuevaVenta); mañana cualquier módulo se enchufa con 1 llamada:**

| Módulo futuro | `cobrable_type` | Trabajo |
|--------------|------------------|---------|
| **Pedidos Mostrador** | `App\Models\PedidoMostrador` | Migración: agregar `estado_pago_integracion` y `integracion_pago_transaccion_id` a `pedido_mostrador`. Wiring en `NuevoPedidoMostrador` Livewire. ~1 día de trabajo |
| **Cobranza Cuenta Corriente** | `App\Models\Cobro` (CC) | Wiring en componente Cobranza. Materialización = generar `MovimientoCuentaCorriente` al confirmar |
| **Atención por Salón** | `App\Models\Mesa` o `App\Models\Comanda` | Cuando el módulo exista, mismo patrón |
| **Atención Delivery** | `App\Models\Delivery` (futuro) | El repartidor puede cobrar con QR generado por el sistema |
| **Reservas / Señas** | `App\Models\Reserva` | Cobro parcial anticipado online |
| **E-commerce / Tienda online** | `App\Models\PedidoOnline` | Cliente paga desde web, no hay cajero esperando — flujo asíncrono con redirección |

Todos comparten: misma tabla `integraciones_pago_transacciones`, mismos eventos Reverb, mismo Gateway, mismo Service. **Reportes globales unificados**.

### Eje 4: Capacidades transversales (cross-cutting)

Funcionalidades que aplican a TODAS las integraciones y módulos consumidores. Se construyen una vez, benefician a todo:

| Capacidad | Descripción | Cuándo |
|-----------|-------------|--------|
| **Pantalla "Transacciones de Integración"** | Listado filtrable (estado, fecha, sucursal, integración, modo, monto) + drill-down a auditoría completa. Útil para soporte y conciliación | Tras Fase 6 |
| **Reintento automático con backoff** | Si una transacción confirma por webhook pero falla la materialización, reintentar N veces con backoff exponencial. Cola dedicada | Tras 3+ proveedores funcionando |
| **Conciliación contable automática** | Al confirmar pago, generar `MovimientoCuentaEmpresa` con fecha estimada de acreditación bancaria (MP = 2 días hábiles, MODO = 1 día, configurable por integración) | Cuando contabilidad lo pida |
| **Importación de reportes de comisiones** | Importar CSVs de comisiones MP/MODO y calcular margen real por venta | Análisis financiero avanzado |
| **Webhook polling fallback** | Job cada N segundos consulta estado de transacciones pendientes contra la API del proveedor (red de seguridad si webhook no llega) | Si vemos pérdida de webhooks en prod |
| **Dashboard de salud de integraciones** | Métricas en vivo: tasa de aprobación, tiempo promedio QR→pago, errores por proveedor, alertas | Cuando haya volumen |
| **Multi-cuenta por sucursal** | Permitir más de 1 cuenta MP por sucursal (ej: retail + eventos en la misma sucursal). Hoy es 1:1, hay UNIQUE constraint | Si surge la necesidad real |
| **API pública para terceros** | Endpoints externos para que apps de terceros (ej: app móvil propia del comercio) inicien cobros usando las integraciones configuradas | Cuando exista la app móvil |
| **Tokenización / pagos guardados** | Guardar tokens de tarjetas para cobros recurrentes (suscripciones, pagos express) | Junto con suscripciones |
| **Devoluciones automatizadas (refunds)** | Cuando se anula una venta cobrada por integración, devolver el dinero al cliente vía API del proveedor en vez de manualmente | Cuando contabilidad lo pida |
| **Reportes cruzados** | "Ventas cobradas por MP por sucursal por modo en el mes" — agregación que el modelo de datos ya soporta | Cuando se necesiten |
| **Notificaciones al cliente** | Email/SMS/WhatsApp automáticos al cliente cuando confirma el pago (recibo) | Junto con módulo de notificaciones |

### Eje 5: Decisiones estructurales del MVP que habilitan el futuro

Estas decisiones de diseño NO son por simplicidad — son inversiones explícitas para soportar el crecimiento:

1. **Contrato `IntegracionPagoGatewayContract`** → cada proveedor es intercambiable.
2. **`cobrable_type` polimórfico** → cualquier módulo es consumidor sin tocar el framework.
3. **Tabla `integraciones_pago` como catálogo (no hardcodeado)** → proveedores se agregan por datos, no por código.
4. **`modos_disponibles` JSON en catálogo + `modos_permitidos` JSON en FP** → modos nuevos no requieren ALTER TABLE.
5. **`config_adicional` JSON en `integraciones_pago_sucursales`** → cada proveedor puede tener campos propios sin migración (ej: MP `qr_estatico_url`, MODO `bank_id`, PayPal `merchant_email`).
6. **`metadata` JSON en transacciones y eventos** → extensiones por proveedor sin migrar tabla central.
7. **Eventos Reverb genéricos por transacción** → cualquier modal/UI puede suscribirse al canal sin lógica especial por proveedor.
8. **Service único `CobroIntegracionService`** → punto de entrada estable; cambios internos no impactan consumidores.
9. **Encriptación at-rest desde el día 1** → compliance lista para crecimiento (PCI-DSS si aplica, GDPR/Ley Argentina 25.326).
10. **Multi-tenant safety auditado** → cada proveedor nuevo hereda la seguridad sin reimplementar.

### Compromiso

**El MVP no es solo un release inicial: es la base de un sistema de cobros integrados que puede crecer durante años sin reescrituras.** Cada decisión de las listadas arriba se tomó con esa visión. La regla al implementar es simple: **si una decisión técnica complica un caso futuro listado en este roadmap, hay que repensarla antes de avanzar.**

---

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Webhook MP no llega (red caída, MP demora) | Job de expiración + botón "Confirmar manualmente" + fase 10 puede agregar polling periódico como fallback |
| Multi-tenant: webhook resuelve sucursal incorrecta | `MercadoPagoCollectorIndex` con UNIQUE en `user_id_externo` + tests específicos |
| Credenciales expuestas en logs | Filtrar campos sensibles en logging + `config/logging.php` agrega scrubber |
| Firma webhook no verificada → spoofing | Verificar `x-signature` siempre que `webhook_secret` esté configurado; warning en UI si no lo está |
| Cajero abandona venta con QR abierto | Job de expiración limpia automáticamente; UI muestra contador visible |
| Pagos duplicados (webhook llamado 2 veces) | Handler idempotente: si transacción está en estado terminal, ignora |
| Cambio de credenciales rompe transacciones en curso | Confirmar al usuario antes de guardar si hay transacciones pendientes con esas credenciales |
| Race condition: webhook llega mientras cajero cancela | Bloqueo optimista en transacción (`select ... for update` en estado pendiente) |

---

## Notas y Decisiones

- **2026-05-26**: Spec creado tras 3 rondas de preguntas con el usuario. Decisiones clave:
  - FP↔modo: híbrido (default + overridables).
  - MVP: QR dinámico + QR estático. No incluye link de pago ni Point.
  - Credenciales por sucursal (cada sucursal con su cuenta MP).
  - Cobro sincrónico (cajero espera en pantalla).
  - Webhook MP + Reverb para detección instantánea.
  - QR estático con matching automático + fallback manual.
  - Service único `CobroIntegracionService` consumido por todos los módulos (presentes y futuros).
  - UI: módulo nuevo `Configuración → Integraciones de Pago`.
  - Sandbox/Prod: toggle por integración, ambos sets de credenciales.
  - Timeout: 5 min configurable.
  - Pagos mixtos: QR solo por la porción MP.
  - Webhook URL única global; resolución por `user_id_externo` vía índice en DB config.

- **Decisión técnica clave**: `mercadopago_collector_index` en DB `config` para evitar escaneo N×tenants en cada webhook. Sincronizado vía hook de modelo.

- **Decisión de scope**: Pedidos Mostrador queda fuera del MVP estricto; la arquitectura lo soporta (cobrable polimórfico) pero el wiring se hace en una fase posterior cuando el flujo de venta esté estabilizado.

- **Pendiente de definir en `/sdd-apply`**:
  - Nombre exacto del paquete Composer MP a usar (`mercadopago/dx-php` o alternativa). Validar versión PHP 8.2+.
  - Icono específico para el menu_item.
  - Estructura exacta del componente FormasPago actual para integrar el bloque (a inspeccionar en fase 4).
