# QR Monto-Libre (qr_libre) — Especificación

## Estado: EN REVISIÓN

> Modo `qr_libre` dentro de la integración `mercadopago_qr`. Cobro con QR de monto libre: el sistema NO empuja monto a MP, muestra una imagen de QR "Cobrar" subida por el comercio (opcionalmente en 2da pantalla) y el cajero confirma el pago manualmente. Deja la transacción registrada como riel para conciliación/webhook futuros.

---

## Contexto y Motivación

Los modos ya implementados (`qr_dinamico`, `qr_estatico`, `point`) **empujan el monto** a MP vía Orders API y obtienen confirmación automática. En el QR de **monto libre**, el monto lo ingresa el cliente en su app (escanea el QR "Cobrar" de la cuenta MP), por lo que **no hay nada que enviar a MP** y el matching automático fue descartado (ver `project_integraciones_pago_point_scope`). 

El comercio igual necesita: (a) tener "QR Mercado Pago (monto libre)" como forma de cobro, (b) **mostrar ese QR en la 2da pantalla** al cliente, y (c) confirmarlo manualmente como una transferencia. Conceptualmente es casi una forma de pago manual, pero se modela como **integración** para reusar la pantalla cliente, la confirmación manual (Fase 8) y **dejar armado el riel** para la futura asistencia por webhook y la conciliación contra la API de Reportes de MP (ver `project_integraciones_pago_conciliacion_mp`).

**Objetivo MVP (declarado por el usuario)**: "por ahora sin funcionamiento extra más que mostrar el QR en la 2da pantalla" + dejar la transacción registrada para B/C futuros.

---

## Principios de Diseño

1. **Reuso máximo, footprint mínimo** — `qr_libre` es un 4º modo de `mercadopago_qr` (Opción A de la exploración), NO una integración separada. Reusa gateway, transacción, modal, pantalla cliente y confirmación manual existentes.
2. **Sin llamada a la API de MP** — el cobro `qr_libre` no crea Order ni consulta estado. La transacción nace local (`external_id = null`) y solo se cierra por confirmación manual.
3. **Multi-tenant** — la imagen del QR se guarda por comercio respetando el aislamiento tenant; la config vive en el pivote `forma_pago_integraciones` (tenant).
4. **Append-only / trazabilidad** — la transacción `qr_libre` queda persistida con su `metadata`, sirviendo de ancla para conciliación y matching futuros.
5. **No romper los otros modos** — todo cambio es aditivo (rama nueva por modo); `qr_dinamico`/`qr_estatico`/`point` no se tocan.

---

## Requisitos Funcionales

### RF-01: Modo `qr_libre` en el catálogo
- Agregar `qr_libre` a `modos_disponibles` de la integración `mercadopago_qr` (semilla + comercios existentes).
- `MercadoPagoGateway` y `IntegracionPagoTransaccion` exponen la constante/`MODO_QR_LIBRE`.

### RF-02: Configuración en la FormaPago (subir el QR)
- En `GestionarFormasPago`, al elegir la integración `mercadopago_qr` con modo `qr_libre`, aparece un **campo de carga de imagen** ("QR Mercado Pago para cobro de monto libre").
- La imagen se persiste en una **columna nueva `config_qr_libre` (JSON)** del pivote `forma_pago_integraciones` (análogo a `config_point`), guardando la ruta/URL de la imagen subida.
- Al editar la FormaPago, se carga la imagen ya guardada (preview).
- Validación: imagen (`jpg`/`jpeg`/`png`/`webp`), tamaño máximo razonable (ej. 4 MB).

### RF-03: Inicio de cobro `qr_libre` (sin API)
- `iniciarCobro` ramifica a `iniciarCobroQrLibre()` cuando `modo_usado = qr_libre`: **NO** llama a `/v1/orders`.
- Crea `IntegracionPagoTransaccion` en estado `pendiente`, `external_id = null`, `modo_usado = qr_libre`, `metadata` con flag `qr_libre` + la URL de la imagen a mostrar.
- Devuelve `qr_image_url` = la imagen subida (para que el modal/pantalla cliente la muestren vía la rama `<img>` existente).
- **Validación pre-cobro**: si la FormaPago no tiene imagen `qr_libre` configurada → error claro ("Configurá la imagen del QR de Mercado Pago en la forma de pago antes de cobrar"). NO requiere POS sincronizado ni terminal.

### RF-04: Confirmación manual como acción principal
- El modal de espera, en modo `qr_libre`, muestra la imagen del QR + un **botón de confirmación de pago prominente** (no el link discreto de fallback que usan los otros modos).
- Reusa `confirmarCobroIntegracionManual()` / `CobroIntegracionService::confirmarManual()` (Fase 8), gateado por permiso `integraciones_pago.confirmar_manual`. Estado resultante: `confirmado_manual` (auditado con usuario/motivo).
- **No hay polling de auto-confirmación**: `pollearCobroIntegracion()` es no-op para `qr_libre` (no hay order que consultar). El texto "esperando confirmación automática" se ajusta a "esperá a que el cliente pague y confirmá".
- Cancelar y expiración (Fase 8, timeout) funcionan igual que los otros modos (al expirar/cancelar reabre el flujo de cobro).

### RF-05: Pantalla cliente (2da pantalla)
- Reusar el mecanismo de `qr_estatico`: si la caja tiene pantalla cliente activa, la imagen del QR se envía al 2º monitor (rama `<img>` / `qr_image_url`, BroadcastChannel). Sin desarrollo nuevo, solo asegurar que `cobroIntegracionQrImagenUrl` se setea para `qr_libre`.

### RF-06: Webhook no auto-matchea `qr_libre`
- El webhook MP ignora (no intenta matchear) transacciones cuyo `modo_usado = qr_libre`. Queda documentado como punto de extensión para la asistencia futura por webhook/conciliación.

### RF-07: Sin credenciales API obligatorias (MVP)
- `qr_libre` funciona **sin** `access_token`/`user_id_externo` cargados (solo mostrar imagen + confirmar manual). Si el comercio igual cargó credenciales, quedan disponibles para el riel futuro (webhook/conciliación) sin cambios.

---

## Modelo de Datos

### Tablas modificadas

#### `integraciones_pago` (tenant, semilla) — Cambios
- Fila `mercadopago_qr`: `modos_disponibles` pasa a incluir `"qr_libre"` → `["qr_dinamico","qr_estatico","qr_libre"]`. (Vía migración UPDATE de semilla + `ProvisionComercioCommand::seedIntegracionesPago`.)

#### `forma_pago_integraciones` (pivote N:M, tenant) — Cambios
- Agregar: `config_qr_libre` (JSON, NULL) AFTER `config_point`. Contiene p.ej. `{"imagen_path": "...", "imagen_url": "..."}`.

#### `integraciones_pago_transacciones` (tenant) — Sin cambios estructurales
- Usa columnas existentes: `modo_usado = 'qr_libre'`, `external_id = NULL`, `metadata` con `{"qr_libre": true, "qr_image_url": "..."}`.

### Almacenamiento de la imagen
- Subida con Livewire (`WithFileUploads`), guardada en disco **público tenant-aware** siguiendo la convención de uploads ya usada por el proyecto (ej. la del ícono de pantalla cliente — ver `project_pantalla_cliente_personalizacion`). Se persiste la ruta relativa + se expone URL pública accesible para modal y 2da pantalla.
- **Decisión a confirmar en implementación**: ruta exacta del disco/carpeta tenant y si se versiona el archivo al reemplazar.

---

## Pantallas UI

### Configuración: `GestionarFormasPago` (`/configuracion/formas-pago`)
**Componente**: `App\Livewire\Configuracion\GestionarFormasPago` — **Traits**: (los actuales)
- En el bloque de integración de la FormaPago: cuando el modo elegido es `qr_libre`, mostrar campo de upload de imagen + preview de la actual.
- Cargar/guardar `config_qr_libre` (espejo de `config_point`: load en ~línea 311, save en ~líneas 404-412).
- `WithFileUploads` para el archivo.

### Cobro: modal compartido `_modal-esperando-pago-integracion.blade.php`
- Rama `qr_libre`: imagen del QR (reusa la rama `<img>` existente vía `cobroIntegracionQrImagenUrl`) + panel de **confirmación manual prominente** + textos adaptados (sin "detección automática").
- `WithCobroIntegracion`: rama `qr_libre` en `iniciarCobroIntegracion()` (resuelve imagen del pivote, arma metadata, setea `cobroIntegracionQrImagenUrl`, NO llama API) y short-circuit en `pollearCobroIntegracion()`.

---

## Servicios

### `MercadoPagoGateway` — `app/Services/IntegracionesPago/MercadoPagoGateway.php`
- `const MODO_QR_LIBRE = 'qr_libre'` + incluir en `modosSoportados()`.
- `iniciarCobro()`: rama temprana `if ($transaccion->modo_usado === self::MODO_QR_LIBRE) return $this->iniciarCobroQrLibre(...)`.
- `iniciarCobroQrLibre($config, $transaccion)`: NO hace HTTP. Devuelve `['qr_data' => null, 'qr_image_url' => <imagen de metadata>, 'external_reference' => 'BCN-TX-'.$transaccion->id, 'external_id' => null, 'payload' => []]`.

### `CobroIntegracionService` — sin cambios funcionales
- Reusa `iniciarCobro` (persiste `qr_image_url` en `metadata`, ya lo hace desde Fase 7) y `confirmarManual` (Fase 8). 

### `WithCobroIntegracion` (concern) — rama `qr_libre`
- Resuelve `config_qr_libre.imagen` del pivote de la FormaPago, lo pasa en `$datos`/metadata, setea `cobroIntegracionQrImagenUrl`, no exige POS/terminal.

---

## Migraciones Necesarias

1. `add_qr_libre_to_mercadopago_qr_modos` — UPDATE semilla `integraciones_pago` (iterar comercios, agregar `qr_libre` a `modos_disponibles` de `mercadopago_qr`, idempotente).
2. `add_config_qr_libre_to_forma_pago_integraciones` — columna `config_qr_libre` JSON NULL AFTER `config_point` (iterar comercios, prefijo tenant, try/catch).
3. Regenerar `database/sql/tenant_tables.sql`.
4. Actualizar `ProvisionComercioCommand::seedIntegracionesPago` (modos_disponibles con `qr_libre`).

---

## Traducciones

Claves nuevas (es/en/pt), p.ej.:
| Clave (es) | en | pt |
|------------|----|----|
| QR Mercado Pago (monto libre) | Mercado Pago QR (free amount) | QR Mercado Pago (valor livre) |
| QR de Mercado Pago para cobro | Mercado Pago QR for charging | QR do Mercado Pago para cobrança |
| Subí la imagen del QR de cobro de Mercado Pago | Upload your Mercado Pago charge QR image | Envie a imagem do QR de cobrança do Mercado Pago |
| Configurá la imagen del QR de Mercado Pago en la forma de pago antes de cobrar | Set the Mercado Pago QR image in the payment method before charging | Configure a imagem do QR do Mercado Pago na forma de pagamento antes de cobrar |
| Esperá a que el cliente pague y confirmá | Wait for the customer to pay and confirm | Aguarde o cliente pagar e confirme |
| Confirmar pago | Confirm payment | Confirmar pagamento |

(Lista final se completa durante implementación; usar skill `/traducir`.)

---

## Criterios de Aceptación

- [ ] CA-01: `mercadopago_qr` ofrece `qr_libre` como modo en `GestionarFormasPago`.
- [ ] CA-02: Se puede subir y guardar la imagen del QR en la config de la integración de la FormaPago; al reabrir se ve el preview.
- [ ] CA-03: Al cobrar con una FormaPago `qr_libre`, NO se hace ninguna llamada HTTP a MP (verificado con `Http::fake`/`assertNothingSent`).
- [ ] CA-04: El modal muestra la imagen del QR y el botón de confirmación manual; al confirmar, la transacción queda `confirmado_manual` y la venta/pedido se materializa.
- [ ] CA-05: Si la caja tiene pantalla cliente activa, la imagen se muestra en el 2º monitor.
- [ ] CA-06: Cobrar `qr_libre` sin imagen configurada da error claro y no crea venta.
- [ ] CA-07: El webhook ignora transacciones `qr_libre` (no las matchea).
- [ ] CA-08: `qr_libre` funciona sin credenciales API cargadas.
- [ ] CA-09: Los modos `qr_dinamico`/`qr_estatico`/`point` siguen funcionando sin regresión (suite verde).
- [ ] CA-10: Smoke test de `GestionarFormasPago` + tests gateway/service nuevos en verde. Pint OK. `tenant_tables.sql` regenerado.

---

## Plan de Implementación

### Fase 1: BD + catálogo [PENDIENTE]
1. Migración modos_disponibles (`qr_libre` en `mercadopago_qr`).
2. Migración `config_qr_libre` en `forma_pago_integraciones`.
3. `ProvisionComercioCommand` actualizado. Regenerar `tenant_tables.sql`. Correr migración en dev y testing.

### Fase 2: Backend gateway/service [PENDIENTE]
1. `MODO_QR_LIBRE` en `MercadoPagoGateway` + `IntegracionPagoTransaccion` + `modosSoportados()`.
2. `iniciarCobroQrLibre()` (sin HTTP) + rama en `iniciarCobro()`.
3. Tests: `MercadoPagoGatewayTest` (qr_libre no llama API, devuelve qr_image_url, sin imagen lanza), `CobroIntegracionServiceTest` (metadata qr_libre).

### Fase 3: UI configuración [PENDIENTE]
1. `GestionarFormasPago`: selector modo `qr_libre` + upload imagen + load/save `config_qr_libre`.
2. Vista: campo upload + preview.
3. Tests: guardar FP con qr_libre + imagen; smoke `SmokeConfiguracion`.

### Fase 4: UI cobro + pantalla cliente [PENDIENTE]
1. `WithCobroIntegracion`: rama `qr_libre` (imagen, metadata, no API) + `pollear` no-op.
2. Modal: rama `qr_libre` (img + confirmación manual prominente + textos).
3. Pantalla cliente: verificar envío de imagen.
4. Tests integración: cobro `qr_libre` feliz (confirma manual → materializa), cancelar, sin imagen.

### Fase 5: Cierre [PENDIENTE]
1. Webhook: guard `qr_libre` (no matchear).
2. Traducciones es/en/pt (`/traducir`).
3. Docs (`@docs-sync`: `manual-usuario.md` + `ai-knowledge-base.md`).
4. `/sdd-verify` + PR.

---

## Mapa de conflictos con Point (#128)

> Esta rama (`feat/integraciones-pago-qr-monto-libre`) sale de **master**, que NO tiene los cambios de Point (#128). Ambos PRs agregan ramas aditivas en los mismos archivos. **El SEGUNDO PR que se mergee** verá estos conflictos (todos mecánicos: agregar un `if`/`elseif`/elemento de array al lado del de Point). Resolución = **conservar AMBAS ramas/constantes**.

| # | Archivo | Qué agrega Point | Qué agrega qr_libre | Resolución |
|---|---------|------------------|---------------------|------------|
| 1 | `app/Models/FormaPago.php` (~124) | `config_point` en `withPivot([...])` | `config_qr_libre` en `withPivot([...])` | Dejar ambos: `withPivot(['modo_default','modos_permitidos','es_principal','config_point','config_qr_libre'])` |
| 2 | `app/Models/IntegracionPagoTransaccion.php` (~97-101) | `const MODO_POINT` | `const MODO_QR_LIBRE` | Conservar ambas constantes |
| 3 | `app/Services/IntegracionesPago/MercadoPagoGateway.php` `iniciarCobro()` (guard top) | `if modo===MODO_POINT return iniciarCobroPoint()` | `if modo===MODO_QR_LIBRE return iniciarCobroQrLibre()` | Conservar ambos guards (orden indistinto) |
| 4 | `MercadoPagoGateway` constantes + `modosSoportados()` | `MODO_POINT` | `MODO_QR_LIBRE` | Conservar ambos |
| 5 | `resources/views/livewire/carrito/_modal-esperando-pago-integracion.blade.php` | `@elseif modo==='point'` (sin QR) | `@elseif modo==='qr_libre'` (img + confirmación manual primaria) | Conservar ambas ramas `@elseif` |
| 6 | `app/Livewire/Concerns/Carrito/WithCobroIntegracion.php` `iniciarCobroIntegracion()` | rama `point` (valida terminal) | rama `qr_libre` (valida imagen) | Conservar ambas ramas |
| 7 | `app/Livewire/Configuracion/GestionarFormasPago.php` (load ~311 + save ~404-412) | `default_type`/`config_point` | imagen/`config_qr_libre` | Conservar ambos en load y save |
| 8 | `database/sql/tenant_tables.sql` (tabla `forma_pago_integraciones`) | columna `config_point` | columna `config_qr_libre` | **Regenerar el SQL** tras mergear ambos (no resolver a mano) |
| 9 | `app/Console/Commands/ProvisionComercioCommand.php` `seedIntegracionesPago()` | fila catálogo `mercadopago_point` | edita `modos_disponibles` de `mercadopago_qr` | Conservar la fila nueva de Point + el modo agregado a qr_libre |

**No conflictúan** (importante): `IntegracionPagoSucursal::sincronizarIndiceColector` (~203) — Point cambia el guard a `in_array([QR, POINT])`; qr_libre **NO toca esa línea** porque es un MODO de `mercadopago_qr` (el `=== CODIGO_MERCADOPAGO_QR` ya lo cubre). Las **migraciones** no conflictúan entre sí (archivos distintos, columnas distintas); el único artefacto compartido es `tenant_tables.sql` (#8).

---

## Notas y Decisiones

- 2026-06-08: Enfoque **Opción A** (4º modo de `mercadopago_qr`), confirmado por el usuario.
- 2026-06-08: El QR es la imagen "Cobrar" de la cuenta MP **subida en la config de la FormaPago** (NO el `mp_pos_qr_url` del POS, que tiene `fixed_amount=true` y no sirve para monto libre). Corrige la suposición de la exploración inicial.
- 2026-06-08: Confirmación **solo manual** (sin polling auto). Webhook no matchea `qr_libre`. Transacción se registra igual para conciliación futura.
- 2026-06-08: Vínculo `CuentaEmpresa` ↔ cuenta MP y conciliación vía API de Reportes quedan como features **posteriores** (ver `project_integraciones_pago_conciliacion_mp`), NO en este spec.
- Pendiente confirmar en implementación: ruta/disco tenant para la imagen subida (seguir convención de uploads existente).
