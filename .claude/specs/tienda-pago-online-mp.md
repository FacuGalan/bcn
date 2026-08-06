# Tienda — Pago Online con Mercado Pago (Checkout Pro) - Especificación

## Estado: EN PROGRESO (Fase 1 completa)

> Spec creado 2026-08-06. Exploración completa (framework integraciones + circuito pedidos tienda + API MP verificada).
> Numeración RF-T continúa desde RF-T75 (T66..T74 = spec tienda-sesion-persistente).
> **Orden de implementación condicionado por PRs abiertos**: Fases 1-2 (core) no conflictúan con PR #198; la Fase 3 (tienda) espera el merge de bcn-tienda #65 (toca los mismos archivos del checkout).

---

## Contexto y Motivación

La tienda online hoy solo permite **declarar** la forma de pago (pago contra entrega/retiro): el pago real ocurre después, en el mundo físico. El framework de integraciones de pago (spec `integraciones-pago-mercadopago`, 10 fases + Point + conciliación, todo mergeado) cubre el cobro presencial (QR dinámico/estático, Point, QR libre) pero dejó explícitamente diferido el **checkout online** — el campo `integraciones_pago_transacciones.link_pago` existe desde la Fase 1 esperando este feature.

Objetivo: el consumidor elige "Mercado Pago" en el checkout de la tienda, paga en la página segura de MP (Checkout Pro), y el pedido entra al circuito YA PAGADO. El comercio deja de perseguir transferencias/efectivo en la entrega.

Pedido explícito de Facu (2026-08-06) sobre reportes: el comercio puede cobrar MP por dos canales (QR en el local, Checkout en la tienda) y los reportes/cierres por forma de pago deben poder **unificar o desglosar** ambos; y si ambas integraciones usan las mismas credenciales, ambas DEBEN impactar la misma cuenta bancaria (CuentaEmpresa).

---

## Principios de Diseño

1. **Producto MP = fila de catálogo separada** (patrón Point): `mercadopago_checkout` con su app y access_token propios, misma `gateway_class` (`MercadoPagoGateway`) con rama por modo.
2. **Pedido primero, cobro después** (Opción B de la exploración): el `PedidoDelivery` se crea como cobrable ANTES de redirigir a MP. El webhook no necesita el carrito (regla existente: "el webhook no materializa, confirma"); acá solo transiciona un pedido que ya existe.
3. **Una FP multicanal, no dos FPs**: la MISMA FormaPago lleva la integración presencial (QR/Point) Y la de checkout — el pivote `forma_pago_integraciones` ya es N:M para esto. El CANAL decide qué integración se usa: panel → principal presencial; tienda → la de checkout. Reportes por FP unifican solos; el desglose por canal sale de las transacciones (cada una con su `integracion_pago_id` + `modo_usado`).
4. **Misma cuenta bancaria con mismas credenciales**: se preserva D7 del spec vinculo-cuenta ("cuenta por identidad de config") — QR/Point/Checkout con el mismo `user_id_externo` convergen a UNA CuentaEmpresa (ya validado en vivo para QR+Point; se agrega test con checkout).
5. **Contrato aditivo**: claves nuevas en `formas_pago`, respuesta del alta y endpoint de re-consulta — nada se renombra ni se quita (política v1).
6. **El back_url de MP NO es fuente de verdad**: la acreditación la decide el webhook (con firma + re-consulta autenticada); el retorno del navegador solo consulta estado.
7. **API**: Checkout Pro = `POST /checkout/preferences` → `init_point` (verificado 2026-08-06; la Orders API `type: online` es Checkout API con formulario propio + tokenización — descartada: más complejidad y PCI sin beneficio para la tienda).

---

## Requisitos Funcionales

### RF-T75: Catálogo y credenciales `mercadopago_checkout` (core)
- Fila de catálogo nueva (molde: seed de Point): `codigo='mercadopago_checkout'`, `nombre='Mercado Pago - Checkout Online'`, `modos_disponibles=['checkout_pro']`, `gateway_class=MercadoPagoGateway`.
- Config por sucursal en `integraciones_pago_sucursales` (UI existente de Integraciones de Pago): credenciales prod/test propias de la app "Checkout Pro" de MP, `probarConexion` reutilizado.
- `IntegracionPagoSucursal::sincronizarIndiceColector()`: ampliar el guard hardcodeado (QR+Point) con el código nuevo — sin esto el webhook nunca resuelve el tenant.
- `ProvisionComercioCommand`: seed para comercios nuevos.

### RF-T76: Gateway Checkout Pro (core)
- `MODO_CHECKOUT_PRO = 'checkout_pro'` + rama `iniciarCobroCheckoutPro()` en `MercadoPagoGateway::iniciarCobro()`:
  - `POST /checkout/preferences` con: `items` (un ítem "Pedido {numero} - {tienda}" por el total — el detalle vive en el pedido), `external_reference='BCN-TX-{id}'` (patrón existente), `notification_url` (webhook único global), `back_urls` + `auto_return='approved'` (URL de retorno de la TIENDA, viaja en `$datos`), `expires`+`expiration_date_to` (del timeout de la tx), `binary_mode=true` (sin estados intermedios `in_process`: aprueba o rechaza — simplifica el circuito), `statement_descriptor` con el nombre del comercio.
  - Devuelve `['link' => init_point]` → `CobroIntegracionService` ya lo persiste en `link_pago` (cableado sin uso desde Fase 1).
- `consultarEstado()` rama checkout: el recurso NO es una order — buscar por `GET /v1/payments/search?external_reference=BCN-TX-{id}` y normalizar (`approved→aprobado`, `rejected/cancelled→fallido/cancelado`, sin resultados→`pendiente`).
- `procesarWebhook()` rama topic `payment` (hoy solo parsea topic order): extraer `payment_id`, resolver la tx re-consultando el payment (su `external_reference`).
- `cancelarCobro()` rama checkout: no hay cancel de preferencia con pago en vuelo — se marca cancelada local y la preferencia muere por `expiration_date_to` (documentar).
- Firma del webhook: mismo `verificarFirma()` HMAC existente (x-signature es transversal a topics).

### RF-T77: Alta de pedido con pago online (core, API v1)
- `TiendaController::formasPagoPublicas()` (aditivo): si la FP tiene integración checkout activa en la sucursal → claves nuevas `pago_online: true` y `pago_online_modo: 'checkout_pro'`. **Decisión Facu 2026-08-06: si la FP tiene checkout, en la tienda es SOLO online** (la variante declarable de esa FP no se ofrece por ahora). El shape ya contempla la evolución: cuando se quiera ofrecer "pagar ahora vs al recibir", se agrega `pago_online_opcional: true` (aditivo) y la tienda muestra las dos variantes — el backend acepta ambas desde el día uno (el alta con FP checkout SIN pedir pago online se trata como declarable). Si la FP NO es declarable pero tiene checkout, ahora viaja (antes se filtraba).
- `POST /v1/tiendas/{slug}/pedidos` con FP online:
  - Crea el pedido como **BORRADOR "esperando pago"** (`esBorrador=true` + `config_adicional`/flag `esperando_pago_online`): NO entra a "por aceptar", NO suena la burbuja, el comercio no lo comanda.
  - Pago registrado `planificado` (como hoy) + tx de integración via `CobroIntegracionService::iniciarCobro($config, $datos, $pedido)` (el cobrable YA existe — parámetro soportado).
  - Respuesta (aditivo): `pago_online: {transaccion_id, url_pago (init_point), expira_en, estado: 'pendiente'}` junto al token de seguimiento habitual.
- Timeout: `timeout_segundos` de la config checkout con **default 1800 (30 min)** — el consumidor navega la página de MP, 5 min presenciales no alcanzan.

### RF-T78: Acreditación por webhook (core)
- `MercadoPagoWebhookService`: hook post-confirmación cuando `cobrable_type = PedidoDelivery`:
  1. Materializa el pago planificado → `activo` (`afecta_caja=0`, `creado_por_usuario_id=NULL` — el esquema lo previó), vincula `pedidos_delivery_pagos.integracion_pago_transaccion_id` (columna nueva), `estado_pago = pagado`.
  2. Transiciona el borrador según config del comercio: aceptación manual → "por aceptar" (AHORA sí burbuja/chime); automática → confirmado.
  3. Broadcast por el canal PÚBLICO de seguimiento `pedidos-delivery.seguimiento.{token}` (la tienda ya lo escucha) además del canal de comercio existente.
- `CuentaEmpresa`: el movimiento lo registra `confirmarCobro` como siempre (D6/D7) — mismas credenciales ⇒ misma cuenta que QR/Point. **Test explícito de convergencia** QR+checkout ⇒ 1 sola CuentaEmpresa, 2 movimientos.

### RF-T79: Expiración / fallo / re-pago (core)
- `expirarPendientesVencidas()` + confirmación de estados terminales: hook nuevo — si la tx tiene cobrable `PedidoDelivery` en "esperando pago": el pedido borrador se **cancela automáticamente** (motivo "pago online no completado") + broadcast al seguimiento. Stock/caja: nunca se tocaron (borrador).
- Re-pago: `POST /v1/tiendas/{slug}/pedidos/{token}/pago` (token = credencial, throttle) — si el pedido sigue "esperando pago" y la tx anterior murió, crea una tx NUEVA sobre el mismo pedido y devuelve `pago_online` fresco. Evita que el consumidor pierda el pedido armado por un fallo de MP.
- Re-consulta para el retorno del navegador: `GET /v1/tiendas/{slug}/pedidos/{token}/pago` → `{estado, url_pago?, expira_en?}` (nunca confía en el back_url).

### RF-T80: Panel del comercio (core)
- Listado/burbuja de pedidos: el borrador "esperando pago online" NO aparece en por-aceptar (decisión RF-T77); aparece en el listado general con badge "Esperando pago online" (naranja) y pasa a por-aceptar al acreditarse.
- Detalle del pedido: pago online acreditado se ve como pago activo con marca de integración (patrón `venta_pagos` ya existente).
- Reportes/cierres: por FP unifican solos (misma FP); el detalle de transacciones de Integraciones de Pago ya desglosa por integración/modo — se agrega el modo `checkout_pro` a los filtros existentes. La conciliación MP (PR #132) toma las tx nuevas sin cambios (mismo colector).

### RF-T82: Devolución al rechazar/cancelar un pedido pagado online (core)
- Pregunta de Facu (2026-08-06) que origina este RF: el comercio DEBE poder rechazar un pedido aunque ya esté pagado — ¿qué pasa con la plata? Respuesta: **devolución automática**.
- Al rechazar/cancelar un pedido con pago online acreditado:
  1. El core ejecuta el refund TOTAL contra MP: `POST /v1/payments/{payment_id}/refunds` (método nuevo `MercadoPagoGateway::reembolsar()` — el diferido "refund real" del spec de integraciones se implementa acá, solo para checkout).
  2. Refund OK ⇒ tx pasa a estado nuevo `devuelto` + evento append-only; pago del pedido → `anulado`; **contraasiento** de egreso en la CuentaEmpresa (patrón ledger: nunca se edita el ingreso original); aviso al consumidor por el canal de seguimiento ("pedido rechazado, te devolvimos el pago").
  3. Refund FALLA (MP caído, saldo insuficiente en la cuenta MP) ⇒ el pedido queda rechazado igual pero el pago queda marcado **"a devolver"** (estado previsto por el spec maestro de tienda): badge rojo en el panel + reintento manual desde el detalle de la transacción. El rechazo nunca se bloquea por un fallo del refund.
- **Bloqueo de modificaciones**: un pedido con pago online acreditado NO permite cambios de monto (editar items, corregir dirección con delta de costo) — paridad con el guard de Fase 9 en ventas. Las opciones del comercio son: aceptarlo como está, o rechazarlo (con devolución). Si hace falta un ajuste chico, se resuelve contra entrega por fuera del pago online.
- La conversión pedido→venta de un pedido pagado online conserva el vínculo tx (RF del modelo de datos) y la venta hereda el bloqueo de anulación existente de Fase 9 — anular esa venta exige primero la devolución (mismo circuito).

### RF-T83: Propina online (estructura para recibir y registrar; la rendición al repartidor es módulo futuro)
- Pedido de Facu (2026-08-06): el comercio puede ACTIVAR propina en el pago online; el consumidor la agrega al pagar; la plata queda registrada y discriminada para que un módulo futuro la rinda al repartidor (posiblemente junto a la rendición de la vuelta). **Este spec construye la estructura de recepción/registro; el procesamiento (rendición) queda explícitamente fuera.**
- Config: flag `propina_habilitada` + presets (`propina_opciones`, ej. `[5, 10, 15]` % y monto libre) en la config de la tienda (donde viven las demás opciones de checkout de la sucursal).
- Tienda (con RF-T81): si está habilitada, el checkout ofrece la propina ANTES de confirmar (chips de % + monto libre + "sin propina"). La propina NO pasa por la cotización (no es parte del pedido ni lleva promos/ajustes): se suma al monto a pagar.
- Alta del pedido: `propina` (decimal ≥ 0) en el payload (aditivo). Se persiste en columna nueva `pedidos_delivery.propina_online`. La preferencia de MP lleva DOS ítems: "Pedido {numero}" (total_final) + "Propina" (monto) — la tx de integración es por la SUMA y su metadata discrimina `{total_pedido, propina}`.
- Registro contable al acreditar: DOS movimientos en la CuentaEmpresa — el ingreso del cobro (total_final, concepto `cobro_integracion` como siempre) y un ingreso separado con **concepto propio `propina_online`** (mismo origen tx). Así la propina nunca se mezcla con la venta: no infla reportes de ventas, NO integra el total facturable (la conversión pedido→venta la excluye), y el módulo futuro de rendición filtra por concepto + repartidor del pedido (el pedido ya conoce su repartidor al entregarse) para calcular lo pendiente de rendir.
- Devolución (RF-T82): el refund total incluye la propina (contraasiento de ambos movimientos).
- Seguimiento/panel: la propina se muestra discriminada en el detalle del pedido ("Propina para el repartidor: $X").

### RF-T81: Checkout de la tienda (bcn-tienda, fase POSTERIOR al merge de #65)
- FP con `pago_online: true` se muestra con badge "Pagás online de forma segura" y al confirmar: alta → recibe `pago_online.url_pago` → redirect a MP (misma pestaña; en webview IG funciona — no es OAuth de Google).
- Página de retorno `/tienda/{slug}/pedido/{token}/pago` (back_url): consulta `GET .../pago` y muestra acreditado / pendiente (con polling breve + canal Reverb del seguimiento) / fallido con botón "Reintentar pago" (`POST .../pago`).
- Seguimiento: badge de estado de pago en tiempo real (canal ya suscripto).
- El carrito NO se vacía hasta que el pago acredite o el consumidor abandone explícitamente (hoy se vacía al 201 — con pago online se vacía al acreditar, para que un fallo no pierda el carrito).

### Decisión de diseño respondida (pedido de Facu): ¿una FP o dos?
**UNA FP multicanal** (recomendada e implementada por este spec): el pivote N:M ya lo soporta, el canal elige la integración, los reportes por FP unifican gratis y el desglose por canal sale de `integraciones_pago_transacciones` (cada tx sabe su integración y modo). Dos FPs separadas quedan PERMITIDAS (nada lo impide: una FP solo-tienda con checkout y otra solo-local con QR) pero no son necesarias ni recomendadas — parten los reportes en dos filas. La cuenta bancaria converge por identidad de credenciales (D7), use una FP o dos.

---

## Modelo de Datos

### Tablas modificadas (todas tenant → regenerar tenant_tables.sql)

#### `integraciones_pago_transacciones`
- `usuario_iniciador_id` → **NULLABLE** (un checkout de tienda no tiene operador; NULL = iniciado por el consumidor).

#### `pedidos_delivery_pagos`
- Agregar `integracion_pago_transaccion_id` (bigint unsigned nullable, FK → `integraciones_pago_transacciones`, ON DELETE SET NULL) AFTER `comprobante_fiscal_id` equivalente — espejo de `venta_pagos` (Fase 9). Trazabilidad + no doble-registrar en conversión pedido→venta (`migrarPagosAVenta` debe copiar el vínculo, mismo fix que el bug (2) de la auditoría de junio).

#### `forma_pago_integraciones`
- Agregar `config_checkout` (json nullable) — p. ej. `{cuotas_max}` (patrón `config_point`/`config_qr_libre`).

#### `pedidos_delivery`
- Agregar `propina_online` (decimal(12,2) default 0) — RF-T83.
- "Esperando pago online" = borrador + existencia de tx pendiente con cobrable el pedido (query), o flag en config del pedido si la implementación lo pide (decidir en Fase 2 con el código a la vista — preferencia: sin columna extra).

### Seeds / catálogo
- Migración seed `mercadopago_checkout` (molde Point, itera comercios, idempotente) + `ProvisionComercioCommand`.

---

## Pantallas UI

1. **Core — Integraciones de Pago**: la integración nueva aparece con el modal de credenciales existente (cero UI nueva salvo textos).
2. **Core — Gestionar Formas de Pago**: el bloque de integraciones ya permite agregar N — verificar que checkout conviva con la principal presencial (es_principal sigue siendo la presencial; checkout se selecciona por canal, no por es_principal).
3. **Core — Pedidos**: badge "Esperando pago online" + entrada a por-aceptar al acreditar (RF-T80).
4. **Tienda — Checkout/Retorno/Seguimiento** (RF-T81, fase posterior).

---

## Servicios

- `MercadoPagoGateway`: `iniciarCobroCheckoutPro()`, ramas en `consultarEstado`/`procesarWebhook`/`cancelarCobro`, `MODO_CHECKOUT_PRO`.
- `CobroIntegracionService`: hook de expiración con cobrable (callback o match por clase — decidir en implementación).
- `MercadoPagoWebhookService`: hook de acreditación para `PedidoDelivery` (materializar + transicionar + broadcast).
- `PedidoTiendaService`: rama FP online en `crearPedidoExterno()` (borrador + tx) + `reiniciarPagoOnline()`.
- `PedidoDeliveryService`: materializar pago planificado online (paridad con `confirmarPagoPlanificado` del mostrador, sin caja).
- `FormaPago`: helper `integracionCheckout(int $sucursalId)`.
- Tienda: `CoreApi::pagoDelPedido()/reintentarPago()`, cambios en `Checkout`/`Seguimiento` (fase posterior).

---

## Migraciones Necesarias

1. `seed_mercadopago_checkout_integracion` (tenant, molde Point).
2. `make_usuario_iniciador_nullable_en_integraciones_pago_transacciones` (tenant).
3. `add_integracion_pago_transaccion_id_to_pedidos_delivery_pagos` (tenant).
4. `add_config_checkout_to_forma_pago_integraciones` (tenant).
5. `add_propina_online_to_pedidos_delivery` (tenant, RF-T83) + concepto de movimiento `propina_online` (seed si los conceptos son data).
→ Regenerar `database/sql/tenant_tables.sql` tras todas.

---

## Traducciones

es/en/pt (orden alfabético): "Mercado Pago - Checkout Online", "Esperando pago online", "Pago online no completado", "Pagás online de forma segura", "Reintentar pago", "El pago se acreditó", etc. — lista final en implementación. (Tienda: solo-español, las claves son el texto.)

---

## Criterios de Aceptación

- [ ] FP con integración checkout viaja a la tienda con `pago_online: true`; sin checkout, shape idéntico al actual (contract tests tienda NO rompen).
- [ ] Alta con FP online: pedido BORRADOR (no aparece en por-aceptar, sin chime) + respuesta con `url_pago` real de MP (fake en tests) + tx `pendiente` con cobrable el pedido.
- [ ] Webhook payment aprobado (firma OK + re-consulta): pago planificado→activo sin caja, `estado_pago=pagado`, pedido pasa a por-aceptar (manual) o confirmado (automática), broadcast en canal de seguimiento, movimiento en CuentaEmpresa.
- [ ] QR (local) y Checkout (tienda) con mismas credenciales ⇒ movimientos en la MISMA CuentaEmpresa.
- [ ] Reporte/cierre por FP: cobros de ambos canales suman en la misma FP; el detalle de integraciones desglosa por modo.
- [ ] Tx expirada: pedido borrador cancelado con motivo + broadcast; re-pago crea tx nueva mientras el pedido espera.
- [ ] Re-consulta `GET .../pago` refleja el estado real; el back_url solo consulta, nunca acredita.
- [ ] Conversión pedido→venta copia `integracion_pago_transaccion_id` (sin doble movimiento).
- [ ] Rechazar pedido pagado online: refund automático en MP (fake), tx `devuelto`, contraasiento en CuentaEmpresa, aviso por seguimiento; si el refund falla, pedido rechazado + pago "a devolver" con reintento manual.
- [ ] Propina: alta con propina suma al monto de la tx pero NO al total del pedido; al acreditar genera movimiento separado con concepto `propina_online`; la conversión a venta NO la incluye en el total facturable; el refund la devuelve completa.
- [ ] Pedido pagado online: modificaciones de monto bloqueadas (aceptar o rechazar).
- [ ] Contrato api-v1-delivery.md actualizado (todo aditivo); suites core y tienda verdes.

---

## Plan de Implementación

### Fase 1 — Core: catálogo + esquema [COMPLETO]
Seed + 3 migraciones + guard del índice colector + constantes + config UI (textos) + tenant_tables.sql + tests de modelos/seed. Mergeable sola (nada consume checkout aún). **Sin conflicto con PR #198.**
> Implementado 2026-08-06 en `feat/tienda-pago-online-mp`: 6 migraciones (las 5 del plan + seed concepto `propina_online` orden 19), guard del colector con `CODIGO_MERCADOPAGO_CHECKOUT`, `MODO_CHECKOUT_PRO` en gateway, pivote `config_checkout` en FormaPago::integraciones(), `propina_online` en PedidoDelivery, `integracionTransaccion()` en PedidoDeliveryPago, ayuda del modal de credenciales con rama "Pagos online"/"Checkout Pro" (+3 traducciones), tenant_tables.sql regenerado (splice quirúrgico de las 4 tablas), tests `MercadoPagoCheckoutFase1Test` (6 verdes).

### Fase 2 — Core: gateway + circuito de pedido online [PENDIENTE]
`iniciarCobroCheckoutPro` + ramas consultar/webhook + `reembolsar()` + RF-T77/T78/T79/T80/T82 + contrato + tests (MP fakeado; molde `CobroQrFlujoFelizTest`/`MercadoPagoWebhookTest`). **Sin conflicto con #198** (roce trivial en routes/api.php y el .md del contrato, aditivo).

### Fase 3 — Tienda: checkout online [BLOQUEADA hasta merge de bcn-tienda #65]
RF-T81 completo + contract tests con fixtures nuevos.

### Fase 4 — Validación en vivo + docs [PENDIENTE]
Sandbox MP con credenciales de prueba de Facu (app Checkout Pro nueva en el panel de MP), webhook vía server o ngrok, @docs-sync, /sdd-verify.

---

## Notas y Decisiones

- 2026-08-06: **Checkout Pro por `/checkout/preferences`** (init_point). Orders API `type: online` = Checkout API (formulario propio + tokenización) descartada: complejidad/PCI sin beneficio. Fuentes: doc oficial MP (checkout-pro/preferences) verificada por web.
- 2026-08-06: **Pedido primero (Opción B)** — el webhook por diseño no materializa desde cero; acá el cobrable ya existe. Descartada Opción A (cobro primero puro): exigiría persistir el carrito server-side para materializar desde el webhook.
- 2026-08-06: **UNA FP multicanal** (pedido de Facu resuelto): N:M existente, canal elige integración, reportes unificados por FP + desglose por tx. Dos FPs quedan posibles pero no recomendadas.
- 2026-08-06: **Cuenta bancaria por identidad de credenciales** (D7 preservada): requisito de Facu cubierto por diseño existente + test nuevo.
- 2026-08-06: `binary_mode=true` — sin `in_process` (boleto/efectivo MP quedan fuera; para comida con entrega inmediata un pago "en proceso" no sirve).
- 2026-08-06: pedido esperando pago = **BORRADOR** (no molesta al comercio hasta que la plata esté); expiración cancela el borrador; re-pago soportado por token.
- Riesgo a validar en Fase 4: comportamiento de `auto_return` y del webhook `payment` en sandbox (orden de llegada webhook vs retorno del navegador) — el diseño no depende del orden (webhook manda).
- 2026-08-06 — decisiones de producto de Facu: (1) pedido esperando pago = **invisible** (borrador) hasta acreditar; (2) devolución **automática** por API; (3) FP con checkout = **solo online por ahora**, variante dual "pagar ahora o al recibir" diseñada aditiva para integrarse fácil al escalar; (4) timeout default **30 min** (configurable).
- 2026-08-06 — comisión MP en devoluciones (pregunta de Facu): política estándar de MP = en devolución total el comprador recibe el 100% y MP **reintegra la comisión al vendedor** (devolución sin costo). Es política comercial (no garantía de API): la verdad contable final la trae la conciliación MP existente (`MP_FEE_AMOUNT` por operación en el reporte). Si MP cambiara la política, el comercio puede compensar el canal online con el `ajuste_porcentaje` de la FP.
- 2026-08-06 (pregunta de Facu): **rechazo de pedido pagado ⇒ devolución automática** (RF-T82). "Sin tocar caja" = la plata va a la CuentaEmpresa MP, no al cajón físico; el pago es real y contabilizado. El diferido "refund real" de junio se implementa acá solo para checkout; QR/Point presenciales conservan el bloqueo de Fase 9 (plata en mano, sin refund).
- 2026-08-06: verificación por MCP oficial de MP (search_documentation MLA): Checkout Pro = preferencia de pago → `init_point`, `back_urls`+`auto_return`, vigencia configurable. Coincide con la verificación web previa; decisión de API firme.
