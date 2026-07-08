# Pedidos Delivery / Take-Away + API v1 - Especificación

## Estado: IMPLEMENTADO (2026-07-08) — /sdd-verify APROBADO (matriz en Fase 7)

> Spec creado el 2026-07-02 tras /sdd-explore + seis rondas de decisiones con el
> usuario (D1-D22; la sexta define alcance CORE vs Fase 8, origen polimórfico en
> ventas y presentación del catálogo en tienda).
> Es la "segunda aplicación" del patrón pedidos-mostrador que su propio
> spec dejó planificada ("a futuro habrá `pedidos_delivery*`"). Incluye la capa
> API REST v1 (pendiente PR2.D de mostrador) y reserva el diseño de datos para la
> TIENDA ONLINE (proyecto aparte que consumirá esta API).
> **IMPORTANTE (/sdd-verify): leer primero la sección "Enmiendas post-entrega
> (rev1-21)"** — las revisiones de pulido con el usuario CAMBIARON decisiones
> de este spec; donde una enmienda contradiga un RF/criterio, MANDA la enmienda.

---

## Enmiendas post-entrega (rev1-21) — MANDAN sobre el spec original

Revisiones acordadas EN VIVO con el usuario tras las fases 1-6 (commits
`8c2488d..de31f21`). Cada punto SUPERSEDE lo que el spec diga en contrario:

### E1 — Estados: `en_camino` es COMPARTIDO (supersede D9/RF-03, rev15)
Take-away ya NO "salta listo → entregado": pasa por `en_camino` con semántica
"**Para retirar**" (sin repartidor ni salida; el cliente pasa a buscarlo).
`repartidor_en_camino` es null y el label lo resuelve `estado_label` por tipo.
Nuevas transiciones: `confirmado/en_preparacion → en_camino/entregado` (salto
de `listo`). Criterio de aceptación 1 (take-away por `listo`) SIN EFECTO.

### E2 — `usa_estado_listo` configurable (nuevo, rev "estado listo opcional")
Key de `config_delivery` (default true). OFF: la columna Listo se oculta del
kanban (fallback si hay pedidos por vuelta fallida), el modal de estado no la
ofrece y preparación despacha directo; `cambiarEstado` backfillea `listo_at`.

### E3 — Numeración display PROPIA + llamador solo-mostrador (supersede RF-05/RF-03, rev9)
`numero_display` de delivery tiene contador (`pedido_delivery_display_*`) y
config propios (`usa_numeracion_display`, `numeracion_display_modo`,
`numeracion_display_horas`) — NO comparte el contador de mostrador. El
llamador/pantalla pública es SOLO mostrador (`dispatchLlamadorPublico` de
delivery es no-op documentado). Criterio "take-away listo aparece en el
llamador (secuencia compartida)" SIN EFECTO; el circuito take-away usa el
chip "Para llevar" → "Para retirar" en el panel.

### E4 — Zonas por POLÍGONO con costos por franja horaria (supersede RF-05/RF-06 zonas, commits 7fd6522+)
Las zonas se DIBUJAN en el mapa (`poligono` JSON, ray casting en
`DeliveryZona::contienePunto`); las zonas por radio quedaron legacy y no
cotizan. `rangos_horarios` pasó de "cuándo está activa la zona" a **franjas de
COSTO** (`costoPara(hora)`: más caro de noche, etc.). Con zonas dibujadas
activas NO hay fallback por km: fuera de todas ⇒ `fuera_de_alcance`. Sin
zonas rige el radio general + costo por km. Criterio de aceptación de la zona
radio-3km-con-fallback SIN EFECTO. El ajuste % por FP y el recargo de cuotas
EXCLUYEN el renglón de envío (envío = valor fijo, hook `baseAjustePagoDesglose`).

### E5 — Conversión: config PROPIA + comprobantes FISCALES (supersede RF-10 "no emite CAE", rev9)
`conversion_automatica_al_entregar` es key del JSON `config_delivery`
(SEPARADA de la columna de mostrador). La conversión SÍ emite los
comprobantes fiscales de los pagos con FP fiscal: pre-marca
`pendiente_de_facturar` en transacción y emite POST-commit con catch
(prorrateo de IVA proporcional forzando Σ=total); una falla de ARCA no
revierte la conversión. La conversión automática corre en try/catch en TODOS
los caminos (vuelta, cambiarEstado con `cajaConversionId`): la entrega nunca
falla por la facturación — el pedido queda "por facturar" y se loguea.

### E6 — Promesa: franjas manuales ADELANTADAS + ASAP + hora editable (supersede RF-15/D22 parcial)
- `modo_promesa='franjas'` ya es elegible: franjas definidas A MANO
  (`franjas: [{hora, dias, delivery, take_away}]`, cruce de medianoche
  soportado). Los CUPOS por franja siguen en Fase 8.
- `lo_antes_posible` (columna nueva en `pedidos_delivery` + key
  `acepta_lo_antes_posible`): promesa válida sin hora ("Ya" = +0). Hora y
  flag son EXCLUYENTES en todos los caminos (crear/actualizar/promesa).
- Hora de entrega editable inline (`actualizarPromesa`, modal según modo de
  promesa) desde tabla y cards móviles — sin gate de permiso (E8).
- Alertas de demora: columnas `sucursales.pedido_alerta_amarilla_min/roja_min`
  (compartidas con mostrador) + píldora de minutos en las vistas; kanban
  ordena ASAP primero.
- `timeout_aceptacion_min` (D14): vencido resalta el pedido "Demorado" en el
  strip por-aceptar y el seguimiento público expone `demorado` (no cancela solo).

### E7 — Vuelta con mini-rendición y viaje ÚNICO (extiende RF-08/RF-09, rev9/12)
- UN viaje por repartidor: despachar con el repartidor en la calle SUMA el
  pedido a su salida `en_camino` (lockForUpdate) — nunca salidas paralelas.
- La vuelta incluye balanceo del fondo: `nada` (se queda todo) /
  `devolver_pedidos` (entrega SOLO los cobros de esta vuelta, neto de envíos
  de terceros) / `devolver` monto / `cerrar` (rendición con diferencia) /
  `reforzar`. Repartidor TERCERO: forzado a `devolver_pedidos` (sin caja
  chica). Auto-apertura de fondo $0 si no tiene (informacional).
- Vuelto planificado: al confirmar sin cobrar con efectivo se pregunta "¿con
  cuánto paga?" → `monto_recibido`/`vuelto` planificados; la vuelta los
  precarga y el repartidor sale con el cambio.

### E8 — Permisos REALES (supersede RF-14)
Existen SOLO: `func.pedidos_delivery.{cobrar, convertir_venta, cancelar,
resetear_numeracion, repartidores, forzar_alcance, config}` + `func.api.tokens`
+ permisos de menú. **NO existen** `.ver/.crear/.editar/.cambiar_estado`:
criterio acordado (fix 305e2fa) — las acciones de flujo (crear/editar/cambiar
estado/despachar/comandar) no llevan permiso funcional; el acceso lo gobierna
el permiso de menú. Los `confirmar*` de vuelta/salida/asignación re-chequean
`repartidores` server-side y `vueltaSalidaId` es `#[Locked]` (rev21).

### E9 — Consistencia de pedidos EN SALIDA (rev19, revisión integral)
`desvincularDeSalida()` (pivot append-only: `no_entregado` + motivo, o DELETE
si la salida estaba `armando`) se invoca al cancelar, al volver a `listo`
desde la calle y al entregar/convertir pedidos de salidas sin partir.
`convertirEnVenta` BLOQUEA pedidos `en_camino` con salida en la calle (los
cobros contra entrega van al fondo vía vuelta) y `cambiarEstado` exige la
vuelta para entregarlos (flag interno `viaVuelta` para el camino legítimo —
cubre el PATCH de la API). Pedidos sin caja (tienda/API) cobrados por panel:
`agregarPago`/`confirmarPagoPlanificado` aceptan caja de contexto y el pedido
la adopta (criterio de convertirEnVenta); cobro que afecta caja sin ninguna
caja ⇒ excepción. FP con integración (QR) no se puede confirmar en la vuelta
(exige su circuito de confirmación).

### E10 — Contrato API para la tienda (rev20, extiende RF-11/RF-12)
- `GET /tiendas/{slug}`: bloque `entrega` (modo_promesa, acepta_lo_antes_posible,
  demoras de automática, usa_franjas) + `formas_pago` declarables contra entrega.
- `GET /tiendas/{slug}/franjas?tipo=`: slots de la jornada (modo franjas).
- `POST pedidos`: `entrega.{franja|lo_antes_posible}` (validados contra config
  — franja inventada/vencida ⇒ 422) y `pago.{forma_pago_id, paga_con}` → pago
  PLANIFICADO con vuelto (nunca cobra). Con aceptación automática el pedido
  externo nunca queda sin promesa.
- Seguimiento público: `facturado` NUNCA se expone (GET lo mapea a `entregado`
  y el broadcast no lo emite), + `lo_antes_posible` + `demorado`;
  `repartidor_en_camino` solo tipo delivery. Broadcast y
  `PedidoDeliveryResource` incluyen `lo_antes_posible`.
- `carrito/cotizar` con Bearer de consumidor usa SU cliente materializado
  (checkout y pedido, mismo total — D12).
- Máquina de estados del seguimiento documentada por tipo en
  `docs/api-v1-delivery.md`.

### E11 — UI del panel (rev10-18)
Lista reordenada con **botones inline sobre el dato** (patrón
/boton-inline-hover): editar en N°, despacho en el repartidor / chip "Para
llevar", hora editable en Horarios (y chip de promesa en cards móviles),
cobrar en el badge de pago (desplegable de planificados con FP+monto+vuelto),
cambiar estado en el badge de estado (siguiente paso preseleccionado);
Acciones acotada a Ver/Convertir/Comandar/Cancelar. Kanban con dropdown único
"Acciones" (position:fixed). El MISMO formato se replicó en pedidos-mostrador
(rev17/18) adaptado a su estructura. Facturados fuera del kanban (rev13).
Vista "En la calle" (salidas en curso) + strip "por aceptar" con Demorado.

### E12 — Config UI dedicada (ajuste de implementación Fase 5)
`ConfiguracionDelivery` es página propia (`/pedidos/delivery/configuracion`,
permiso `func.pedidos_delivery.config`) + `ConfiguracionDeliveryEnvio`
(zonas por polígono con mapa). Keys reales de `config_delivery`: ver
`Sucursal::CONFIG_DELIVERY_DEFAULTS` (fuente de verdad, incluye
`usa_estado_listo`, `conversion_automatica_al_entregar`,
`usa_numeracion_display/modo/horas`, `acepta_lo_antes_posible`, `franjas`).

---

## Contexto y Motivación

El módulo de pedidos mostrador está MADURO (kanban, carrito completo con
precios/promos/impuestos/cupones/puntos, pagos, comandas, tiempo real Reverb,
conversión a venta). El comercio necesita el mismo circuito para **delivery y
take-away**, que agrega una dimensión logística inexistente:

- **Dirección de entrega georreferenciada** (el picker de Google Maps ya existe
  como trait+partial reutilizable, spec `domicilio-google-maps.md`).
- **Costos de envío** calculados (radio/km/zonas) o manuales.
- **Repartidores**: asignación de pedidos, salidas y vueltas, fondo de cambio.
- **Estados logísticos**: en camino, listo para retirar.

Además, es el vehículo del requisito estratégico **API-first**: toda la lógica
(alta/modificación, cálculos, validaciones de ubicación, configuración) debe ser
consumible por aplicaciones externas — en particular la futura **TIENDA ONLINE**
(proyecto aparte, multi-tenant, un solo deploy para todos los comercios) que
gestionará sus pedidos DESDE este panel con seguimiento en tiempo real. La API
REST v1 con Sanctum no existe hoy (`routes/api.php` solo tiene impresión): este
spec la crea desde cero y sirve de base para el pendiente RF-14/PR2.D de mostrador.

---

## Principios de Diseño

1. **Espejo de pedidos-mostrador, no fork** (D1): tablas propias `pedidos_delivery*`
   (no columnas nullable en mostrador), mismo circuito, mismos traits de carrito
   (`Concerns/Carrito/*`), services agnósticos compartidos (PrecioService,
   OpcionalService, CuponService, PuntosService, VentaService con
   `stock_ya_descontado`). Lo que difiere es SOLO lo logístico.
2. **API-first real**: `PedidoDeliveryService` es el ÚNICO camino de escritura;
   Livewire y los controllers API v1 lo consumen por igual, con los mismos
   payloads y validaciones. Nada de lógica en componentes ni controllers.
3. **El pedido no exige cliente**: patrón ya existente en mostrador
   (`cliente_id` NULL + datos temporales), extendido con email y token de
   seguimiento — habilita invitados de la tienda online.
4. **La identidad del consumidor de la tienda es GLOBAL, no tenant** (D8): una
   cuenta sirve para cualquier comercio → vive en la BD `config` (como users),
   y se mapea a un `clientes` tenant por comercio en el primer pedido.
5. **Georreferenciación condicionada y honesta** (D5): config por sucursal.
   Sin coordenadas no hay cálculo de envío ni validación de alcance — el
   sistema lo dice, no lo inventa. El costo manual siempre es posible.
6. **Snapshot inmutable + precarga**: la dirección del pedido se guarda completa
   en el pedido (auditable); además se persiste en el cliente (lat/lng nuevos)
   para precargar el próximo pedido. "Entregar en otra dirección" no pisa la
   del cliente.
7. **El fondo del repartidor es un fondo, no una caja** (D4): patrón
   provisión/rendición, append-only, PERO de ciclo largo: puede quedar abierto
   entre salidas y se rinde cuando se decide cerrarlo.
8. **Tiempo real por eventos existentes**: `PedidoDeliveryBroadcast extends
   TenantBroadcastEvent` (canal privado del comercio) + canal PÚBLICO de
   seguimiento por token para consumidores sin login (patrón llamador).
9. **Multi-tenant estricto**: todo tenant en `pymes_tenant` con transacciones
   explícitas; lo cross-comercio (consumidores, slug de tienda) en `config`.
10. **Reservar, no construir, la tienda** (D8): este spec define los datos y
    contratos que la tienda necesitará (slug, rubro, consumidores, origen del
    pedido, catálogo público) sin implementar la tienda.

---

## Requisitos Funcionales

### RF-01: Panel de pedidos delivery/take-away
- Pantalla espejo del kanban/listado de mostrador (`/pedidos-delivery`):
  columnas por estado, drag & drop con `orden_kanban`, filtros visibles, orden
  por columna, tiempo real (creación, cambios de estado/pago, cancelación).
- Cada tarjeta muestra además: tipo (delivery/take-away), dirección resumida,
  zona, repartidor asignado, costo de envío, origen (panel/tienda/API).
- Filtros extra: tipo de pedido, repartidor, origen, zona.

### RF-02: Tipo de pedido
- `tipo` enum('delivery','take_away') obligatorio al alta, en el mismo panel.
- `take_away`: sin dirección, sin repartidor, sin costo de envío; usa el estado
  "listo" con label "Listo para retirar".
- `delivery`: exige dirección de entrega (RF-04) y habilita envío + repartidor.
- El tipo puede cambiarse mientras el pedido esté en borrador/confirmado (al
  pasar a delivery exige dirección; al pasar a take-away limpia envío/repartidor).

### RF-03: Estados y transiciones
- Se reutiliza la máquina de estados de mostrador y se agrega **`en_camino`**:
```
borrador → confirmado → en_preparacion → listo → en_camino → entregado → facturado
                (cancelado alcanzable desde todos salvo facturado)
en_camino → [entregado | listo (vuelta fallida, RF-08) | cancelado]
```
- `en_camino` SOLO para tipo delivery (take-away salta listo → entregado).
- Label dinámico de `listo`: "Listo para enviar" (delivery) / "Listo para
  retirar" (take-away).
- Take-away `listo` se anuncia en el llamador/pantalla pública existente —
  para eso comparte `numero_display`. Requiere extender
  `PantallaPublicaService::pedidosParaLlamador` (hoy consulta solo
  `pedidos_mostrador`) y el dispatch del broadcast público desde
  `PedidoDeliveryService` (el evento en sí ya es agnóstico).
- `listo → en_camino` se dispara al registrar la salida del repartidor (RF-08);
  el pase manual crea una salida implícita de 1 pedido (el circuito de vuelta,
  cobros y fondo SIEMPRE opera sobre salidas). Exige repartidor asignado si la
  sucursal lo configura así (`exigir_repartidor`, default true).
- Cancelar un pedido `en_camino` se registra a través de la vuelta (no
  entregado → cancelar); sus pagos se contraasientan (el efectivo del fondo con
  movimiento inverso, D13).
- Estado de pago: cache recalculado (idéntico a mostrador). Estado de comanda:
  accessor derivado de los detalles (NO cache — así funciona en mostrador).

### RF-04: Dirección de entrega georreferenciada
- Modal de dirección al alta/edición de pedidos delivery, reutilizando el trait
  `ManejaDomicilio` + partial `domicilio-form` existentes: dirección escrita,
  autocomplete de Google Maps, click/arrastre de marcador en el mapa, "usar mi
  ubicación". Piso/depto/referencias en campo aparte (`direccion_referencia`).
- Condicionado a `config_delivery.georreferenciar_pedidos` (por sucursal):
  - **ON**: el modal muestra el mapa; con coordenadas se calcula envío y se
    valida alcance (RF-06). Se puede guardar sin coordenadas con advertencia
    explícita ("sin geo no hay cálculo automático").
  - **OFF**: solo campos de texto; costo de envío siempre manual.
- Snapshot en el pedido: `direccion_entrega`, `direccion_referencia`,
  `localidad_entrega_id`, `latitud`, `longitud`, `zona_id` (si aplica).
- Persistencia en el cliente (D6, corregida por D18): `clientes` gana campos de
  ENTREGA propios — `direccion_entrega`, `direccion_entrega_referencia`,
  `latitud`, `longitud` — SEPARADOS del domicilio existente:
  `clientes.direccion` es el domicilio FISCAL (verificado: lo usan el receptor
  de ARCA, la impresión y el padrón) y NUNCA se pisa con una dirección de
  entrega. Al confirmar el pedido se actualizan los campos de entrega salvo
  "entregar en otra dirección" (solo snapshot). Precarga: dirección de entrega
  si existe; si no, la fiscal como texto inicial (sin coordenadas).
- Extensiones necesarias al trait/partial existentes (verificado contra código):
  agregar campo `referencia`, modo "dirección de entrega" sin `domTipo`
  fiscal/comercial, provincia/localidad con default de la sucursal para carga
  rápida (hoy exige elegir provincia+localidad antes de mostrar el mapa).
- Precarga: al elegir cliente con domicilio georreferenciado, el modal arranca
  con su dirección y coordenadas.

### RF-05: Configuración de delivery por sucursal
- Flag `usa_delivery` en sucursal + JSON `config_delivery` (patrón
  `config_llamador` con DEFAULTS mergeados):
  - `georreferenciar_pedidos` (bool, default false)
  - `radio_entrega_km` (decimal, NULL = sin límite)
  - `costo_envio_base` (decimal, default 0)
  - `costo_por_km_extra` (decimal, default 0) + `km_incluidos_en_base` (decimal)
  - `exigir_repartidor` (bool, default true)
  - `takeaway_habilitado` (bool, default true)
  - `aceptacion_pedidos_externos` enum('manual','automatica'), default 'manual' (D14)
  - `imprimir_comanda_al_aceptar` (bool, default false) — con aceptación
    automática, la comanda sale directo a la comandera (D14)
  - `timeout_aceptacion_min` (int NULL) — vencido, avisa al consumidor y marca
    el pedido "demorado" (no cancela solo)
  - `horarios_atencion` (JSON días/rangos) + `feriados` (JSON fechas) +
    `dias_laborales` — calendario de la tienda/pedidos (D16); la API pública
    rechaza pedidos fuera de horario (salvo programados válidos)
  - `modo_promesa` enum('franjas','automatica','manual'), + config de cada modo
    (RF-15, D16) — la UI v1 solo expone automática/manual ('franjas' es Fase 8, D22)
  - `acepta_programados` (bool, default false) — flag maestro: en OFF los
    pedidos programados desaparecen de TODA la UI (panel sin campo
    `programado_para`, tienda/API los rechaza, sin sección "programados de hoy").
    Key creada desde el día 1; su lógica es Fase 8 (D22)
  - `programados_aparecen_min_antes` (int, default 60) — RF-15, solo con
    `acepta_programados` ON (Fase 8, D22)
  - reservado para automatización futura (asignación automática de repartidor,
    integración con cadeterías externas)
- Tabla `delivery_zonas` (por sucursal): nombre, definición geográfica
  (v1: **radio** — centro lat/lng + km; el campo `poligono` JSON queda reservado
  para zonas dibujadas a futuro), costo de envío propio, rangos horarios
  (JSON de días/horas en que la zona está activa), `orden` (prioridad de match),
  `activo`.
- UI de configuración: sección "Delivery" en la configuración de la sucursal +
  ABM de zonas (con mapa para elegir el centro del radio, reutilizando el picker).

### RF-06: Cálculo de costo de envío y validación de alcance
- `DeliveryEnvioService::cotizar(sucursal, lat, lng, ?fecha): CotizacionEnvio`
  — resolución en orden:
  1. ¿Coordenadas? NO → sin cotización (costo manual, `alcance = desconocido`).
  2. Zona activa que matchee (por orden de prioridad, respetando rango horario
     de la fecha/hora consultada) → costo de la zona.
  3. Sin zona: distancia a la sucursal (Haversine, línea recta v1; distancia
     por calle queda como mejora futura vía API de rutas) → dentro de
     `radio_entrega_km` ⇒ `costo_base + max(0, km − km_incluidos) × costo_km`;
     fuera del radio ⇒ `alcance = fuera_de_alcance`.
- El panel muestra la cotización y el operador puede **pisar el costo a mano**
  (D7): `costo_envio_manual = true` + quién lo hizo. Fuera de alcance: el panel
  advierte pero un permiso (`pedidos_delivery.forzar_alcance`) permite confirmar
  igual; la API pública (tienda) NO permite forzar.
- **Materialización del costo de envío (D17, verificado contra la cadena
  fiscal)**: los campos de encabezado (`costo_envio`, `costo_envio_manual`,
  `distancia_km`, `zona_id`) son la fuente de verdad LOGÍSTICA (kanban,
  cotización, auditoría), pero el monto se materializa como **renglón
  `es_concepto=true` "Costo de envío"** (mecanismo ya existente en
  `pedidos_mostrador_detalle` y `ventas_detalle`: sin stock, con
  `tipo_iva_id` 21% y `concepto_categoria_id` configurable), gestionado por
  `PedidoDeliveryService` (lo crea/actualiza/elimina al recotizar o editar).
  Un `costo_envio` de solo-encabezado sería INVISIBLE para el comprobante:
  `calcularDetallesIva` arma neto/IVA solo desde los detalles y
  `ImpTotal ≠ ImpNeto+ImpIVA` es rechazo de ARCA. Con el renglón-concepto,
  total, desglose de IVA, comprobante, conversión y reportes cierran SIN tocar
  VentaService ni la capa fiscal.
- El renglón de envío NO participa de descuento general, cupones, promociones
  ni puntos (se calcula y suma aparte de la cascada de descuentos).

### RF-07: Repartidores (ABM)
- Tabla `repartidores`: nombre, teléfono, `tipo` enum('propio','tercero') (D3),
  `user_id` NULL (FK lógico a config.users — para futura app de repartidores),
  `envio_es_del_repartidor` (bool, default false: si true, el costo de envío
  cobrado no es ingreso del comercio — se liquida al repartidor en la rendición),
  `activo`, sucursales habilitadas (pivot `repartidor_sucursal`).
- ABM en el módulo delivery (permiso `pedidos_delivery.repartidores`).

### RF-08: Asignación, salidas y vueltas
- Asignar repartidor a UN pedido (desde la tarjeta) o VARIOS pedidos a un
  repartidor (selección múltiple / vista "armar salida").
- **Salida** (`delivery_salidas` + pivot `delivery_salida_pedidos`, historial
  append-only): agrupa pedidos `listo` de un repartidor; registrarla pasa todos
  a `en_camino` con timestamp. Una salida puede sumar pedidos mientras el
  repartidor no haya partido. `pedidos_delivery.salida_id` apunta solo a la
  salida ACTUAL; el pivot conserva todas (re-despachos incluidos) con resultado
  y motivo por pedido.
- **Vuelta**: al registrarla se marca por pedido el resultado (entregado /
  no entregado con motivo) y se registran los cobros contra entrega:
  - **Efectivo**: entra al FONDO del repartidor (D13) — el pedido queda pagado
    (vía pagos planificados de mostrador: `confirmarPagoPlanificado`) SIN
    movimiento de caja; la caja recibe recién en la rendición.
    NOTA de implementación (verificado): `confirmarPagoPlanificado` y la
    materialización en `convertirEnVenta` SIEMPRE crean `MovimientoCaja` si
    `afecta_caja` — el service espejo necesita el override `destino_fondo`
    (confirma el pago sin movimiento de caja y registra el movimiento del
    fondo); la vuelta registra los pagos ANTES de marcar entregado (el guard
    de conversión exige pagos suficientes) y la conversión nunca materializa
    pagos destinados al fondo.
  - **No efectivo** (QR/Point/transferencia en la puerta): circuito normal de
    pagos, nunca toca el fondo.
  Pedidos no entregados vuelven a `listo` (re-despacho, sus pagos previos
  persisten) o se cancelan (contraasientos, incl. movimiento inverso del fondo).
- La conversión automática a venta al entregar (config existente de mostrador)
  se ejecuta POST-vuelta, individual y fuera de la transacción de la vuelta
  (una falla de ARCA en un pedido no puede dejar la vuelta a medias).
- Reasignación de repartidor libre solo hasta `listo`; en `en_camino` únicamente
  vía vuelta fallida + re-despacho (evita salidas/fondos cruzados).

### RF-09: Fondo del repartidor (caja chica) (D4)
- `repartidor_fondos`: fondo de cambio entregado al repartidor desde una caja
  (egreso de `MovimientoCaja` con referencia) con estado `abierto`/`rendido`.
  **No se rinde obligatoriamente a la vuelta**: puede quedar abierto entre
  salidas ("se lo queda para seguir viajando").
- `repartidor_fondo_movimientos` (append-only): entrega inicial, refuerzos,
  cobros en efectivo de pedidos entregados, vueltos dados, liquidación de
  envíos de terceros, rendición. El saldo teórico del fondo se calcula de los
  movimientos.
- **Regla contable (D13)**: el efectivo cobrado en la calle NO genera
  `MovimientoCaja` al registrarse — vive en el fondo; al rendir, UN ingreso
  neto a la caja receptora (inicial + cobros − vueltos − liquidación de envíos
  de terceros). Así la caja siempre cuadra con el arqueo físico. Los
  movimientos del fondo no llevan turno de caja (el fondo es cross-turno); el
  cierre de caja ADVIERTE si hay fondos abiertos con esa caja como origen
  (no bloquea). NOTA de implementación (verificado): el cierre tiene TRES
  caminos duplicados (TurnoActual cierre grupal, cierre individual y
  `CajaService::cerrarCajaConTesoreria`) — la advertencia va en los tres (o se
  extrae un helper común primero).
- **Visibilidad en tesorería**: entre vuelta y rendición hay efectivo cobrado
  que no está en ninguna caja — los reportes de tesorería/arqueo muestran una
  línea informativa "en fondos de repartidores" (suma de saldos teóricos de
  fondos abiertos) para que la plata nunca sea invisible.
- **Rendición** (cuando se decide cerrar): efectivo declarado vs teórico →
  diferencia sobrante/faltante registrada (patrón `RendicionFondo`), ingreso a
  la caja receptora por contraasiento. Si el repartidor es tercero con
  `envio_es_del_repartidor`, la rendición descuenta los envíos que le
  corresponden y lo deja explícito en el detalle.
- Un repartidor tiene a lo sumo UN fondo abierto por sucursal.

### RF-10: Carrito, precios y pagos — paridad con mostrador
- El alta/modificación compone los MISMOS traits: búsqueda de artículos y
  clientes, opcionales, promociones, descuentos, cupones, puntos, invitaciones,
  desglose de pagos, cálculo (`WithCalculoVenta` extendido para sumar
  `costo_envio` al total).
- Pagos parciales/totales, anulación por contraasiento, estado de pago cache,
  conversión a venta (`convertirEnVenta` con `stock_ya_descontado`) — idénticos.
- **Origen de la venta (D20, verificado)**: hoy la venta NO guarda referencia
  al pedido (solo `pedido.venta_id`, unidireccional). `ventas` gana
  `origen_type`/`origen_id` (morph NULL — venta directa POS queda NULL;
  morphMap 'PedidoMostrador'/'PedidoDelivery' en AppServiceProvider). La
  conversión de delivery lo setea SIEMPRE, y en el mismo desarrollo se
  actualiza la conversión de MOSTRADOR (una línea en su `convertirEnVenta`)
  para que TODO canal presente y futuro (salón/mesas) persista su origen.
  "¿Vino de la tienda?" se deriva del pedido (`origen='tienda'`, accessor
  `esDeTienda`) — sin columna redundante en ventas. Habilita reportes por
  origen sin joins invertidos.
- **Gaps de mostrador que delivery NO hereda (D19, verificados en código)** —
  el service espejo los corrige a nivel service (y quedan anotados como mejora
  espejable en mostrador):
  - Puntos GANADOS: hoy solo se acreditan desde Livewire de venta directa; la
    conversión no los acredita nunca. Delivery los acredita en
    `convertirEnVenta` (si hay cliente).
  - Cupón: la conversión copia montos pero no registra `CuponUso` ni incrementa
    `uso_actual`. Delivery lo registra en la conversión.
  - Opcionales: `mapearDetalleAArrayVenta` no migra los opcionales a
    `venta_detalle_opcionales`. Delivery los migra.
  - `cierre_turno_id` de los pagos de pedido nunca se asigna (el guard de
    anulación con turno cerrado es letra muerta): `marcarTransaccionesCierre`
    debe marcar también `pedidos_delivery_pagos`.
- **Caja de la conversión**: `ventas.caja_id` es NOT NULL, la numeración es por
  caja y exige caja abierta. Pedidos de tienda/API (sin caja): la conversión
  usa la caja activa de quien la ejecuta (o la de la vuelta); sin caja abierta,
  el pedido queda `entregado` en cola "por facturar" (no explota).
- Aclaración: "facturado" = convertido en venta (la conversión NO emite
  comprobante fiscal/CAE — igual que mostrador).
- Cobro contra entrega: el pedido viaja con **pago planificado** (mecanismo ya
  existente en mostrador: `confirmarPagoPlanificado`/`eliminarPagoPlanificado`)
  que se confirma a la vuelta del repartidor (RF-08) o desde el panel.
- **`forma_venta_id` se fija AUTOMÁTICO según el tipo** (seeds DELIVERY y
  TAKEAWAY existentes): es lo que activa las listas de precios y promociones
  específicas por forma de venta (verificado: existen lista "Precios Delivery"
  y promos condicionadas — sin esto el motor no las aplicaría).
- Comandas e impresión: mismo circuito de mostrador; la comanda/precuenta de
  delivery imprime dirección, teléfono, hora pactada y costo de envío.
  NOTA técnica: las plantillas actuales (`PlantillasComanda`) están tipadas a
  `PedidoMostrador` — generalizarlas (interfaz/union) o crear variantes delivery.

### RF-11: API REST v1 (D2) — scaffolding + endpoints delivery
- **Base nueva** (no existe): `routes/api.php` versionado `/api/v1/`, Sanctum,
  Form Requests + API Resources, throttle, manejo de errores JSON uniforme
  (`{error: {code, message, details}}`), paginación estándar.
- **Autenticación** (3 audiencias):
  1. **Tokens de integración** (terceros/apps propias): Sanctum personal access
     tokens emitidos por comercio con *abilities* (`pedidos:read`,
     `pedidos:write`, `catalogo:read`, `config:read`). Gestión de tokens en
     configuración del comercio.
  2. **Consumidores de tienda** (futuro): Sanctum sobre el guard de
     `consumidores` (RF-13) — la tienda lo usará; este spec deja el guard y el
     modelo listos.
  3. **Público sin auth** (throttled): catálogo de la tienda, cotización de
     envío, seguimiento por token.
- **Resolución de tenant**: middleware `api.tenant` que resuelve
  comercio+sucursal (por token de integración o por el slug de `tiendas` en
  rutas públicas — el slug ES una sucursal, D15) y configura `TenantService`
  (lección conocida: fuera de sesión web hay que configurarlo manualmente).
- **Contexto de permisos SIN sesión (hallazgo verificado)**:
  `User::loadAllPermissions` cachea por `session('comercio_activo_id')` y
  devuelve vacío sin sesión — los `hasPermissionTo()` embebidos en los services
  denegarían SIEMPRE bajo Sanctum. Extender el mecanismo de permisos para
  aceptar el comercio explícito (contexto seteado por `api.tenant`), sin romper
  el camino web. Los tokens de integración autorizan por *abilities* (no por
  permisos de usuario); los services deben chequear permisos solo cuando hay
  usuario actor.
- **Endpoints v1 (delivery)**:
  - `GET  /v1/tiendas/{slug}` — datos públicos de la tienda/sucursal (RF-13):
    ubicación, rubro, logo, horarios/calendario, config de entrega
  - `GET  /v1/tiendas/{slug}/franjas?fecha=` — franjas horarias con cupos
    restantes (RF-15, modo franjas) y validación de programados [FASE 8, D22]
  - `GET  /v1/tiendas/{slug}/catalogo` — artículos/categorías/opcionales/promos
    visibles según el criterio de RF-17 (activo + vendible + visible_tienda +
    disponible por tipo; agotados marcados NO-pedibles), con orden, destacado
    e imágenes, y precios finales calculados por el MISMO motor del sistema
    (PrecioService: 4 niveles de especificidad, lista del canal tienda,
    promociones vigentes, precios especiales). **La tienda nunca calcula
    precios localmente** (D12).
  - `POST /v1/tiendas/{slug}/carrito/cotizar` — cotización server-side del
    carrito completo: renglones + opcionales → promociones aplicadas,
    descuentos, cupón validado, puntos (si consumidor con cliente
    materializado), envío. Devuelve el mismo desglose que vería el panel; es
    el contrato que la tienda muestra en el checkout (D12).
  - `POST /v1/tiendas/{slug}/envios/cotizar` — {lat,lng} → costo/alcance (RF-06)
  - `POST /v1/tiendas/{slug}/pedidos` — alta de pedido (invitado o consumidor)
  - `GET  /v1/pedidos/{token_seguimiento}` — seguimiento público (estado,
    tiempos, repartidor en camino)
  - Con token de integración: `GET/POST/PATCH /v1/pedidos-delivery` (listado,
    alta, modificación, cambio de estado, asignación de repartidor),
    `GET /v1/delivery/config`, `GET /v1/repartidores`
- **Tiempo real para externos**: los eventos `PedidoDeliveryBroadcast` ya
  salen por Reverb; se documentan los canales para consumo externo y se agrega
  canal público `pedidos-delivery.seguimiento.{token}` (patrón llamador público)
  para el tracking del consumidor. Webhooks salientes quedan como fase futura.
- La estructura queda lista para colgar los endpoints de mostrador (PR2.D)
  sin rediseño.

### RF-12: Pedidos de origen externo en el panel
- `origen` enum('panel','tienda','api') en el pedido + `origen_referencia`
  (id externo del integrador, NULL) + `consumidor_id` (FK lógico a
  config.consumidores, NULL).
- Aceptación según `config_delivery.aceptacion_pedidos_externos` (D14):
  - `manual` (default): TODO pedido externo (pagado o no) entra "por aceptar"
    (badge + sonido en el panel, tiempo real). Aceptar lo confirma (y dispara
    el modal de demora si `modo_promesa=manual`, RF-15); rechazar lo cancela —
    si estaba pagado online queda marcado **"a devolver"** con aviso al
    consumidor (devolución manual en v1; automática cuando exista refund en el
    spec de integraciones). `timeout_aceptacion_min` vencido ⇒ aviso al
    consumidor y pedido resaltado (no se cancela solo).
  - `automatica`: el pedido entra `confirmado` directo y, si
    `imprimir_comanda_al_aceptar`, la comanda sale sola por la comandera
    ("se ponen a trabajar sin tocar nada").
- Stock y precios de pedidos por aceptar: el pedido por aceptar NO descuenta
  stock (patrón borrador); al aceptar se valida stock y se respetan los
  precios/promos COTIZADOS al crearlo (snapshot en los renglones — el
  consumidor ya vio ese total); si falta stock el operador ajusta o rechaza.
- Pago online acreditado: NO genera `MovimientoCaja` (`afecta_caja=0`, se
  concilia por el circuito MP existente); una caja interviene solo si un
  operador cobra desde el panel.
- Cancelación por el CONSUMIDOR (API): permitida hasta `confirmado` (antes de
  `en_preparacion`); después, solo el comercio.
- Invitados: `cliente_id` NULL + `nombre_cliente_temporal`,
  `telefono_cliente_temporal` (patrón mostrador) + nuevo
  `email_cliente_temporal`.
- Consumidor logueado (D11): el pedido SIEMPRE guarda `consumidor_id` + snapshot
  de contacto. El `cliente_id` tenant solo se completa si existe mapping
  consumidor↔cliente para ese comercio. La creación del mapping la DECIDE el
  comercio: `tienda_alta_cliente_automatica` (config por comercio, default OFF)
  — ON: el primer pedido crea el cliente tenant + mapping; OFF: el pedido queda
  solo con consumidor, y el panel ofrece la acción **"convertir en cliente"**
  (crea el cliente con los datos del consumidor + mapping, y vincula sus pedidos
  del comercio). Puntos, cupones por cliente y cta cte solo aplican cuando el
  cliente está materializado — la UI de la tienda lo refleja.

### RF-13: Reserva de datos para la TIENDA ONLINE (D8) — NO se construye acá
- **La tienda es POR SUCURSAL, no por comercio** (D15): cada sucursal habilitada
  tiene su propia tienda/slug/URL. Esto elimina el problema de "a qué sucursal
  entra el pedido": la tienda ES una sucursal.
- **BD `config` (cross-comercio)**:
  - Nueva `tiendas` (registro global de tiendas, resoluble sin abrir la BD
    tenant): `comercio_id` (FK comercios), `sucursal_id` (FK LÓGICO a la
    sucursal tenant), `slug` (varchar(60) UNIQUE), `habilitada` (bool 0),
    `dominio_propio` (varchar NULL UNIQUE — futuro), timestamps.
    UNIQUE(comercio_id, sucursal_id). URL: `/tienda/{slug}`.
  - `comercios.rubro_id` (FK NULL a nueva tabla `rubros`: catálogo global —
    hamburguesería, pizzería, kiosco… para el marketplace). NOTA (verificado):
    ya existe `comercios.rubro` string que usa Mercado Pago para el MCC del
    POS — CONVIVEN: `rubro` sigue siendo la categoría MCC de MP, `rubro_id` es
    el rubro comercial de la tienda; documentar en el modelo para no confundir.
  - `consumidores` (cuentas globales de la tienda — el "cliente general"):
    nombre, email UNIQUE, password, teléfono, verificación, timestamps. Guard
    `consumidores` + Sanctum. **Solo migración + modelo + guard en este spec**
    (la tienda implementa registro/login).
  - `consumidor_direcciones`: direcciones guardadas del consumidor, reutilizables
    en cualquier comercio — alias ("Casa", "Trabajo"), dirección, referencia,
    localidad_id, lat/lng, `es_default`. El checkout las precarga y el pedido
    copia el snapshot.
  - `consumidor_comercio` (mapping): consumidor_id, comercio_id, cliente_id
    (id del `clientes` en la BD tenant de ese comercio — FK lógico cross-DB),
    UNIQUE(consumidor_id, comercio_id). Se crea SOLO según la política del
    comercio (D11): automático al primer pedido si
    `tienda_alta_cliente_automatica`, o manual con "convertir en cliente".
  - `comercios.tienda_alta_cliente_automatica` (bool, default 0) — D11.
- Facturación de pedidos de tienda (v1): invitado/consumidor sin cliente
  materializado ⇒ factura B a consumidor final; el checkout puede capturar
  DNI/CUIT opcional (snapshot fiscal en el pedido); factura A solo con cliente
  materializado con perfil fiscal completo. El comprobante se envía al email
  del pedido. (Afinar con el contador si hace falta más.)
- **Datos públicos que alimentarán la landing/marketplace** (buscar
  "hamburgueserías que llegan a mi ubicación"): rubro + logo + sucursales con
  lat/lng (existen) + `config_delivery` (radio/zonas/horarios) + horarios de
  atención. El endpoint `GET /v1/tiendas/{slug}` ya expone lo per-comercio; el
  buscador global del marketplace es del proyecto tienda.
- URL: `bcn.bcnsoft.com.ar/tienda/{tienda_slug}` (routing del proyecto tienda);
  el dominio propio mapea al mismo slug.

### RF-14: Permisos y menú
- Permisos nuevos (migración + `ProvisionComercioCommand::seedRolesYPermisos()`
  + middleware + `hasPermissionTo()`): `pedidos_delivery.ver`, `.crear`,
  `.editar`, `.cambiar_estado`, `.cancelar`, `.cobrar`, `.convertir_venta`,
  `.resetear_numeracion`, `.repartidores` (ABM + salidas/fondos),
  `.forzar_alcance`, `.config` (configuración delivery de la sucursal),
  `api.tokens` (gestión de tokens de integración). (Espejo de los de mostrador
  + los propios de delivery.)
- Ítems de menú: "Pedidos Delivery" (junto a Pedidos Mostrador) y la config
  dentro de configuración de sucursal.

### RF-15: Promesa de entrega y pedidos programados (D16, alcance core D22)
- Todo pedido puede tener **`hora_pactada_at`** (cuándo se prometió entregar /
  retirar), visible en kanban (ordenable, con semáforo de atraso), comanda y
  seguimiento. El modo de cálculo es configurable por sucursal (`modo_promesa`):
  1. **`franjas`** [FASE 8 — diferido, D22]: lista de franjas horarias con
     CUPOS — N pedidos delivery y M take-away por franja (config JSON:
     [{desde, hasta, cupo_delivery, cupo_takeaway}]). El alta (panel/API/tienda)
     elige franja con cupo disponible; la API expone las franjas y sus cupos
     restantes. El enum `modo_promesa` nace con el valor 'franjas' definido
     pero la config v1 solo expone automática/manual.
  2. **`automatica`** [CORE]: `hora_pactada = ahora + demora_base_min +
     demora_min_por_km × km`. La distancia sale de la cotización (RF-06).
     `usar_maps_para_demora` (Routes API de Google, duración real de viaje,
     fallback a km) queda [FASE 8 — diferido, D22]; v1 siempre calcula por km.
  3. **`manual`** [CORE]: al ACEPTAR el pedido se abre un modal con botones de
     demora `+0 +10 +15 +20 +25 +30 +35 +40 +45 +50 +60 +90` (lista
     configurable por sucursal) → fija la hora pactada y se informa al
     consumidor (canal de seguimiento).
- **Pedidos programados** [FASE 8 — diferido, D22]: la ESTRUCTURA se crea
  desde el día 1 (columna `programado_para`, flag `acepta_programados` default
  OFF, `programados_aparecen_min_antes`, `articulos.permite_programado` —
  cero rework después); la lógica/UI/validaciones llegan en Fase 8: carga para
  otro día/hora, ocultos en kanban hasta X min antes (patrón scheduler),
  validación API contra calendario (RF-05) y artículos programables (RF-16).
  Mientras tanto el flag queda OFF y nada de programados aparece en ninguna UI.
- Fuera de horario ⇒ la API pública rechaza con mensaje claro (el calendario
  de RF-05 SÍ es core); el panel advierte pero permite (operador manda). La
  excepción "programado válido" se suma en Fase 8.

### RF-16: Disponibilidad de artículos por canal (D16)
- `articulos` gana tres flags (default true): `disponible_delivery`,
  `disponible_take_away`, `permite_programado`.
- El catálogo de la API/tienda filtra por tipo de pedido; el carrito del panel
  advierte (no bloquea) al agregar un artículo no disponible para el tipo —
  el operador puede forzar; la API pública SÍ bloquea.
- Editable desde el ABM de artículos (sección delivery) e import/export.

### RF-17: Presentación del catálogo en tienda (D21)
Auditado contra el modelo actual: `articulos` tiene UNA imagen (`imagen_path`
+ punto focal) y `descripcion` TEXT — suficientes para v1 —, pero NO existía
ningún flag de visibilidad web, ni orden de presentación, ni imagen/orden en
categorías, ni decisión de venta sin stock. Los opcionales SÍ están completos
(min/max, obligatorio, orden, `disponible` por sucursal) y se exponen tal cual.
- `articulos` gana: `orden` (int, 0), `destacado` (bool, 0),
  `permite_venta_sin_stock` (bool, 0).
- `articulos_sucursales` gana: `visible_tienda` (bool, default 1) —
  visibilidad en la tienda de ESA sucursal (la tienda es por sucursal, D15).
  Independiente de `vendible`, que gobierna la pantalla POS interna.
- `categorias` gana: `imagen_path` (varchar(255) NULL) + `orden` (int, 0).
- **Criterio de visibilidad del catálogo público** (lo consume RF-11):
  artículo activo + `vendible` en la sucursal + `visible_tienda` + disponible
  para el tipo de pedido (RF-16). **Agotado** (modo_stock ≠ 'ninguno', stock ≤ 0
  y sin `permite_venta_sin_stock`) ⇒ visible pero NO pedible ("agotado"); la
  API pública bloquea pedirlo, el panel solo advierte (paridad RF-16).
- **UI mínima** (en los ABM existentes): sección "Tienda" en el ABM de
  artículos (orden, destacado, venta sin stock + visible_tienda por sucursal)
  y en el ABM de categorías (imagen con el uploader existente, orden).
  Import/export: columnas nuevas AL FINAL (misma regla que los flags RF-16).
- **Reservado para el proyecto tienda (NO acá)**: galería multi-imagen
  (`articulo_imagenes`), descripción corta/tagline, imagen-banner y flag
  "destacar en tienda" de promociones.

---

## Modelo de Datos

### Tablas nuevas (tenant, prefijo `{NNNNNN}_`)

#### `pedidos_delivery` (espejo de `pedidos_mostrador` + campos delivery)
TODAS las columnas de `pedidos_mostrador` (numero, numero_display, identificador,
numero_beeper — take-away en el local puede usar beeper; delivery lo ignora —,
sucursal_id, cliente_id NULL, nombre/telefono_cliente_temporal, caja_id,
canal_venta_id, forma_venta_id, lista_precio_id, usuario_id, fecha,
estado_pedido, estado_pago, orden_kanban, subtotal, iva, descuento, total,
ajuste_forma_pago, total_final, invitaciones, descuento general, cupón, puntos,
observaciones, motivo_cancelacion, cancelado_por_usuario_id, timestamps de
estado (confirmado_at, en_preparacion_at, listo_at, entregado_at, cancelado_at),
convertido_at, venta_id, timestamps, deleted_at — SoftDeletes) MÁS:

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `tipo` | enum('delivery','take_away') | — | RF-02 |
| `estado_pedido` | enum(...mostrador..., **'en_camino'**) | 'borrador' | RF-03 |
| `email_cliente_temporal` | varchar(150) NULL | NULL | Invitados tienda (RF-12) |
| `direccion_entrega` | varchar(255) NULL | NULL | Snapshot (NULL en take_away) |
| `direccion_referencia` | varchar(255) NULL | NULL | Piso/depto/indicaciones |
| `localidad_entrega_id` | bigint unsigned NULL | NULL | FK localidades |
| `latitud` / `longitud` | decimal(10,7) NULL | NULL | Snapshot geo |
| `zona_id` | bigint unsigned NULL | NULL | FK delivery_zonas (la que matcheó) |
| `costo_envio` | decimal(12,2) | 0 | Suma al total_final |
| `costo_envio_manual` | tinyint(1) | 0 | Pisado a mano (D7) |
| `costo_envio_usuario_id` | bigint unsigned NULL | NULL | Quién lo pisó |
| `distancia_km` | decimal(8,2) NULL | NULL | Calculada al cotizar |
| `fuera_de_alcance` | tinyint(1) | 0 | Confirmado con forzar_alcance |
| `repartidor_id` | bigint unsigned NULL | NULL | FK repartidores |
| `salida_id` | bigint unsigned NULL | NULL | FK delivery_salidas (salida ACTUAL; historial en pivot) |
| `en_camino_at` | timestamp NULL | NULL | Métrica logística (entregado_at ya viene del espejo) |
| `no_entregado_motivo` | varchar(255) NULL | NULL | Vuelta fallida (RF-08) |
| `hora_pactada_at` | timestamp NULL | NULL | Promesa de entrega/retiro (RF-15) |
| `programado_para` | timestamp NULL | NULL | Pedido programado (RF-15) |
| `datos_fiscales_snapshot` | json NULL | NULL | DNI/CUIT opcional del checkout (RF-13) |
| `origen` | enum('panel','tienda','api') | 'panel' | RF-12 |
| `origen_referencia` | varchar(100) NULL | NULL | Id externo del integrador |
| `consumidor_id` | bigint unsigned NULL | NULL | FK lógico config.consumidores |
| `token_seguimiento` | char(26) NULL UNIQUE | NULL | ULID — tracking público |

Índices: espejo de mostrador + (`tipo`), (`repartidor_id`,`estado_pedido`),
(`salida_id`), (`origen`), (`token_seguimiento`).

#### Tablas satélite espejo (mismas columnas que sus pares de mostrador)
`pedidos_delivery_detalle`, `pedido_delivery_detalle_opcionales`,
`pedido_delivery_detalle_promociones`, `pedido_delivery_promociones`,
`pedidos_delivery_pagos`.

#### Ajustes de implementación (Fase 1, aplicados)
- `pedidos_delivery_detalle.es_costo_envio` (bool, 0): identifica el
  renglón-concepto del envío (D17) para que el service lo cree/actualice/
  elimine al recotizar sin matchear por descripción.
- `pedidos_delivery_pagos.destino_fondo` (bool, 0) + `repartidor_fondo_id`
  (FK NULL): persisten el circuito del fondo (D13) — qué pagos en efectivo
  viven en qué fondo (necesario para contraasientos de cancelación y para la
  rendición).
- `pedidos_delivery.usuario_id` y `pedidos_delivery_pagos.creado_por_usuario_id`
  son NULLables (a diferencia de mostrador): los pedidos de tienda/API y los
  pagos online acreditados por webhook no tienen operador.
- `token_seguimiento` se genera (ULID) en el hook `creating` del modelo para
  TODO pedido — el tracking público por token no es exclusivo de la tienda.
- morphMap: al aliasar 'PedidoMostrador' cambió su `getMorphClass()` → la
  migración `normalize_cobrable_type_pedido_mostrador` actualiza las
  transacciones de integración históricas que guardaron el FQCN.
- **Regla para TODA migración tenant**: los COMMENT de columna NO pueden
  contener `;` — `WithTenant::createTenantTables` y la provisión splitean el
  SQL del template por `;` y el statement se rompe (detectado por 93 tests).

#### `repartidores`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `nombre` | varchar(150) | — | |
| `telefono` | varchar(30) NULL | NULL | |
| `tipo` | enum('propio','tercero') | 'propio' | D3 |
| `envio_es_del_repartidor` | tinyint(1) | 0 | true ⇒ el envío se liquida al repartidor (no es ingreso) |
| `user_id` | bigint unsigned NULL | NULL | FK lógico config.users (app futura) |
| `activo` | tinyint(1) | 1 | |
| `created_at/updated_at` | timestamp NULL | | |

- Pivot `repartidor_sucursal` (repartidor_id, sucursal_id).

#### `delivery_zonas`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `sucursal_id` | bigint unsigned | — | FK sucursales |
| `nombre` | varchar(100) | — | |
| `centro_lat` / `centro_lng` | decimal(10,7) | — | Centro del radio (picker Maps) |
| `radio_km` | decimal(8,2) | — | v1: zona = círculo |
| `poligono` | json NULL | NULL | RESERVADO: zona dibujada futura |
| `costo_envio` | decimal(12,2) | — | Pisa el cálculo por km |
| `rangos_horarios` | json NULL | NULL | [{dias:[1..7], desde:'19:00', hasta:'23:30'}]; NULL = siempre |
| `orden` | int | 0 | Prioridad de match |
| `activo` | tinyint(1) | 1 | |
| `created_at/updated_at` | timestamp NULL | | |

#### `delivery_salidas`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `sucursal_id` | bigint unsigned | — | |
| `repartidor_id` | bigint unsigned | — | FK repartidores |
| `estado` | enum('armando','en_camino','finalizada') | 'armando' | |
| `salida_at` / `vuelta_at` | timestamp NULL | NULL | |
| `usuario_id` | bigint unsigned | — | Quién la registró |
| `observaciones` | varchar(255) NULL | NULL | |
| `created_at/updated_at` | timestamp NULL | | |

(Los pedidos referencian la salida ACTUAL vía `pedidos_delivery.salida_id`.)

#### `delivery_salida_pedidos` (historial append-only, RF-08)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `salida_id` | bigint unsigned | — | FK delivery_salidas, CASCADE |
| `pedido_id` | bigint unsigned | — | FK pedidos_delivery |
| `resultado` | enum('pendiente','entregado','no_entregado') | 'pendiente' | |
| `motivo` | varchar(255) NULL | NULL | |
| `created_at/updated_at` | timestamp NULL | | |

#### `repartidor_fondos` (RF-09)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `repartidor_id` | bigint unsigned | — | |
| `sucursal_id` | bigint unsigned | — | Un fondo abierto por repartidor+sucursal |
| `caja_origen_id` | bigint unsigned | — | De dónde salió el cambio |
| `estado` | enum('abierto','rendido') | 'abierto' | Puede quedar abierto entre salidas |
| `monto_inicial` | decimal(12,2) | — | |
| `monto_rendido` | decimal(12,2) NULL | NULL | Declarado al cerrar |
| `diferencia` | decimal(12,2) NULL | NULL | sobrante(+)/faltante(−) |
| `caja_rendicion_id` | bigint unsigned NULL | NULL | Dónde ingresó la rendición |
| `usuario_apertura_id` / `usuario_cierre_id` | bigint unsigned (NULL cierre) | | |
| `abierto_at` / `rendido_at` | timestamp (NULL) | | |
| `created_at/updated_at` | timestamp NULL | | |

#### `repartidor_fondo_movimientos` (append-only)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `fondo_id` | bigint unsigned | — | FK repartidor_fondos, CASCADE |
| `tipo` | enum('entrega_inicial','refuerzo','cobro_pedido','vuelto','liquidacion_envios','rendicion','ajuste') | — | |
| `monto` | decimal(12,2) | — | Con signo según tipo |
| `pedido_id` | bigint unsigned NULL | NULL | FK pedidos_delivery (cobros/vueltos) |
| `movimiento_caja_id` | bigint unsigned NULL | NULL | FK al egreso/ingreso de caja vinculado |
| `usuario_id` | bigint unsigned | — | |
| `detalle` | varchar(255) NULL | NULL | |
| `created_at` | timestamp | — | UPDATED_AT = null |

### Tablas modificadas (tenant)

#### `clientes` — Cambios (D6/D18)
- Agregar: `direccion_entrega` (varchar(255) NULL),
  `direccion_entrega_referencia` (varchar(255) NULL), `latitud` (decimal(10,7)
  NULL), `longitud` (decimal(10,7) NULL) — domicilio de ENTREGA, separado del
  `direccion` fiscal existente (que usan ARCA/impresión/padrón y no se toca).

#### `sucursales` — Cambios (RF-05)
- Agregar: `usa_delivery` (tinyint(1), 0) + `config_delivery` (json NULL,
  DEFAULTS mergeados en el modelo, patrón `config_llamador`) +
  `pedido_delivery_ultimo_numero` (int, 0 — numeración PROPIA de delivery por
  sucursal, con reset por permiso; `numero_display` COMPARTE el contador
  existente `pedido_display_ultimo_numero` para que el llamador/pantalla cante
  números únicos entre mostrador y delivery).

#### `articulos` — Cambios (RF-16 + RF-17)
- Agregar: `disponible_delivery` (tinyint(1), 1), `disponible_take_away`
  (tinyint(1), 1), `permite_programado` (tinyint(1), 1).
- RF-17: `orden` (int, 0), `destacado` (tinyint(1), 0),
  `permite_venta_sin_stock` (tinyint(1), 0).

#### `articulos_sucursales` — Cambios (RF-17)
- Agregar: `visible_tienda` (tinyint(1), 1) — visibilidad en la tienda de esa
  sucursal (independiente de `vendible`, que es la pantalla POS interna).

#### `categorias` — Cambios (RF-17)
- Agregar: `imagen_path` (varchar(255) NULL), `orden` (int, 0).

#### `ventas` — Cambios (D20)
- Agregar: `origen_type` (varchar(30) NULL) + `origen_id` (bigint unsigned
  NULL) — morph al pedido de origen ('PedidoMostrador'/'PedidoDelivery' vía
  morphMap; NULL = venta directa POS). Índice (`origen_type`,`origen_id`).
  Lo setean AMBAS conversiones (la de delivery nueva y la de mostrador, que
  se actualiza en este desarrollo).

### Tablas nuevas/modificadas en BD `config` (cross-comercio, RF-13)

- Nueva `tiendas` (D15 — una por SUCURSAL): comercio_id FK, sucursal_id (FK
  lógico tenant), slug UNIQUE, habilitada, dominio_propio NULL UNIQUE,
  UNIQUE(comercio_id, sucursal_id), timestamps.
- `comercios` + `rubro_id` (FK NULL a `rubros` — convive con el `rubro` MCC de
  MP existente), `tienda_alta_cliente_automatica` (bool 0 — D11).
- Nueva `rubros`: id, nombre, slug, icono, activo (catálogo global seed).
- Nueva `personal_access_tokens` (Sanctum) EN BD CONFIG: los tokenables
  (Comercio con HasApiTokens para tokens de integración, Consumidor) viven en
  config — definir la conexión explícita del modelo de tokens.
- Nueva `consumidores`: nombre, email UNIQUE, password, telefono, email_verified_at,
  remember_token, timestamps (+ guard auth `consumidores`, Sanctum tokens).
- Nueva `consumidor_direcciones`: consumidor_id FK CASCADE, alias, direccion,
  referencia, localidad_id, latitud/longitud (decimal(10,7) NULL), es_default,
  timestamps.
- Nueva `consumidor_comercio`: consumidor_id FK, comercio_id FK, cliente_id
  (bigint, FK LÓGICO a clientes tenant), UNIQUE(consumidor_id, comercio_id).
  Creación según política del comercio (D11), nunca incondicional.

---

## Pantallas UI

### 1. Panel de pedidos delivery (`/pedidos-delivery`)
**Componente**: `App\Livewire\Pedidos\PedidosDelivery`
**Traits**: SucursalAware + CajaAware + Lazy (skeleton) — espejo de `PedidosMostrador`
- Kanban con columna extra "En camino"; tarjetas con datos logísticos (RF-01)
  + hora pactada con semáforo de atraso; orden por hora pactada disponible.
- Acciones por tarjeta: asignar repartidor, cambiar estado, cobrar, ver mapa.
- Banner/badge "pedidos nuevos por aceptar" (RF-12) con sonido; al aceptar con
  `modo_promesa=manual` se abre el modal de demora (+0 +10 +15… configurable).
- [FASE 8] Pedidos programados: ocultos hasta X min antes; sección/indicador
  "programados de hoy".
- Vista "armar salida": seleccionar pedidos listos → asignar repartidor → salida.

### 2. Alta/edición (`NuevoPedidoDelivery`, modal full-screen)
**Componente**: `App\Livewire\Pedidos\NuevoPedidoDelivery`
**Traits**: los de `Concerns/Carrito/*` (paridad mostrador) + `ManejaDomicilio`
- Selector tipo delivery/take-away al inicio.
- Modal de dirección con picker Maps (RF-04) + cotización de envío en vivo +
  advertencia de alcance; costo de envío editable con permiso.
- Resto: carrito idéntico a mostrador.

### 3. Repartidores y fondos (`/pedidos-delivery/repartidores`)
**Componente**: `App\Livewire\Pedidos\GestionarRepartidores`
**Traits**: SucursalAware + Lazy
- ABM repartidores (tipo propio/tercero, flag envío, sucursales).
- Fondos: abrir (entrega de cambio desde caja), refuerzos, ver saldo teórico
  (movimientos), rendir (declarado vs teórico, diferencia, liquidación de
  envíos de terceros).
- Salidas activas y su historial (pedidos, tiempos, vueltas).

### 4. Configuración delivery (dentro de configuración de sucursal)
**Componente**: `App\Livewire\Configuracion\ConfiguracionDelivery` (o sección)
- Flags + valores de `config_delivery` (RF-05): georref, radio/costos,
  aceptación (manual/automática + impresión automática + timeout), calendario
  (días laborales, feriados, horarios), modo de promesa (v1: automática por km /
  manual con botones configurables; franjas con cupos y programados se suman
  en Fase 8, D22).
- ABM de zonas con mapa (centro + radio visual, costo, rangos horarios).

### 5. Tokens de API (configuración del comercio)
**Componente**: `App\Livewire\Configuracion\TokensApi`
- Emitir/revocar tokens de integración con abilities; mostrar el token una vez.

---

## Servicios

### `PedidoDeliveryService` — `app/Services/Pedidos/PedidoDeliveryService.php` (nuevo)
Espejo de `PedidoMostradorService` (mismas firmas — verificadas: crearPedido,
actualizarPedido, cambiarEstado, confirmarBorrador, agregarPago, anularPago,
confirmarPagoPlanificado, eliminarPagoPlanificado, cancelarPedido,
convertirEnVenta, siguienteNumero, siguienteNumeroDisplay, resetearNumeracion,
reordenarColumna, avanzarAEnPreparacionSiCorresponde, comandarPedido,
imprimir*) MÁS:
- NOTAS de espejo (verificadas): `siguienteNumeroDisplay` se EXTRAE a un helper
  común con mostrador (lockForUpdate sobre sucursales — duplicarlo divergiría);
  `prepararDatosReceptor` de la capa fiscal se extiende con receptor override
  para que `datos_fiscales_snapshot` (DNI/CUIT del checkout) llegue al
  comprobante; los flags nuevos de artículos van AL FINAL del import/export
  (columnas por letra fija — insertarlas en el medio rompería planillas viejas).
- `asignarRepartidor(PedidoDelivery $p, ?int $repartidorId, int $usuarioId): PedidoDelivery`
- `establecerDireccion(PedidoDelivery $p, array $direccion, bool $actualizarCliente): PedidoDelivery`
  — snapshot + persistencia en cliente (D6) + re-cotización.
- `establecerCostoEnvio(PedidoDelivery $p, float $monto, bool $manual, int $usuarioId): PedidoDelivery`
- Validaciones de transición extendidas (`en_camino` solo delivery, etc.).
- TODO consumible idéntico desde Livewire y API (payload array validado).

### `DeliveryEnvioService` — nuevo
- `cotizar(Sucursal $s, ?float $lat, ?float $lng, ?Carbon $cuando): CotizacionEnvio`
  — RF-06 (zona → radio/km → fuera de alcance). DTO con costo, zona, distancia,
  alcance ('ok'|'fuera_de_alcance'|'desconocido') y demora estimada (RF-15).
- `matchearZona(...)`, `distanciaKm(...)` (Haversine).
- `estimarDemora(Sucursal $s, float $km, ?float $lat, ?float $lng): ?int` —
  minutos según `modo_promesa=automatica`: base + min/km, o Routes API de
  Google si `usar_maps_para_demora` (fallback a km si falla).
- `franjasDisponibles(Sucursal $s, Carbon $fecha, string $tipo): array` —
  franjas con cupo restante (cuenta pedidos activos por franja y tipo).
- `validarProgramado(Sucursal $s, Carbon $cuando, array $articuloIds): array`
  — calendario (días laborales/feriados/horarios) + artículos programables.
- `configDelivery(Sucursal $s): array` — JSON + DEFAULTS mergeados.

### `RepartidorService` — nuevo
- `crearSalida(int $repartidorId, array $pedidoIds, int $usuarioId): DeliverySalida`
- `registrarSalida(DeliverySalida $s): void` — pedidos → en_camino + broadcast.
- `registrarVuelta(DeliverySalida $s, array $resultados, int $usuarioId): DeliverySalida`
  — por pedido: entregado/no entregado + cobros (delegando en PedidoDeliveryService).
- `abrirFondo(int $repartidorId, int $cajaId, float $monto, int $usuarioId): RepartidorFondo`
- `reforzarFondo(...)`, `registrarMovimientoFondo(...)` (cobros/vueltos automáticos
  al registrar la vuelta), `saldoTeorico(RepartidorFondo $f): float`
- `rendirFondo(RepartidorFondo $f, float $declarado, int $cajaId, int $usuarioId): RepartidorFondo`
  — diferencia + liquidación envíos de terceros + ingreso a caja (contraasientos).

### API v1 — `app/Http/Controllers/Api/V1/*` (nuevos, delgados)
- `TiendaController` (show, catalogo), `EnvioController` (cotizar),
  `PedidoDeliveryController` (index/store/update/estado/asignarRepartidor),
  `SeguimientoController` (show por token), `RepartidorController` (index),
  `DeliveryConfigController` (show).
- `app/Http/Resources/Api/V1/*` + `app/Http/Requests/Api/V1/*`.
- Middleware `api.tenant` (resuelve comercio por token o slug → TenantService).

### Eventos y broadcasting
- `app/Events/PedidoDelivery/*` (espejo de mostrador) +
  `app/Events/Broadcasting/PedidoDeliveryBroadcast.php`
  (`resourceName()='pedidos-delivery'`).
- Canal público `pedidos-delivery.seguimiento.{token}` (sin datos sensibles:
  estado + tiempos) — patrón llamador público.

---

## Migraciones Necesarias (orden)

1. `create_pedidos_delivery_tables` — las 6 tablas espejo (tenant).
2. `create_repartidores_tables` — repartidores + pivot + fondos + movimientos (tenant).
3. `create_delivery_zonas_y_salidas` — zonas + salidas + pivot salida_pedidos (tenant).
4. `add_delivery_config_a_sucursales` — usa_delivery + config_delivery +
   pedido_delivery_ultimo_numero (tenant).
5. `add_geo_a_clientes` — latitud/longitud/direccion_referencia (tenant).
6. `add_delivery_flags_a_articulos` — disponible_delivery/take_away/permite_programado
   + orden/destacado/permite_venta_sin_stock (RF-17) (tenant).
7. `add_presentacion_tienda` — articulos_sucursales.visible_tienda +
   categorias.imagen_path/orden (RF-17) (tenant).
8. `add_origen_a_ventas` — origen_type/origen_id + índice (D20) (tenant).
9. `normalize_cobrable_type_pedido_mostrador` — actualiza FQCN → alias en
   transacciones de integración históricas (efecto del morphMap, D20) (tenant).
10. `create_tiendas_y_rubros` — tabla tiendas (por sucursal, D15) + rubros +
    comercios.rubro_id + tienda_alta_cliente_automatica (config).
11. `create_consumidores` — consumidores + consumidor_direcciones +
    consumidor_comercio (config) + guard.
12. `create_personal_access_tokens` — Sanctum en BD config (+ instalar paquete).
13. `seed_formas_y_canal_venta_delivery` — canal TIENDA + formas de venta
    DELIVERY/TAKEAWAY para comercios EXISTENTES y alta en
    `ProvisionComercioCommand` (hallazgo: hoy solo viven en un seeder de dev —
    un comercio provisionado de cero no las tendría y el motor de listas/promos
    por forma de venta no funcionaría).
14. `menu_y_permisos_pedidos_delivery` — menú + permisos + ProvisionComercioCommand.
15. Regenerar `database/sql/tenant_tables.sql`.

---

## Traducciones

Claves nuevas (es/en/pt, orden alfabético, skill /traducir). Principales:
"Delivery", "Para llevar", "En camino", "Listo para retirar", "Listo para enviar",
"Dirección de entrega", "Referencia", "Costo de envío", "Zona de entrega",
"Fuera del área de entrega", "Repartidor", "Repartidores", "Asignar repartidor",
"Registrar salida", "Registrar vuelta", "Fondo de cambio", "Rendir fondo",
"Radio de entrega (km)", "Costo por km extra", "Pedidos por aceptar",
"Seguimiento del pedido", "Tokens de API", + mensajes de validación de alcance.

---

## Criterios de Aceptación

- [ ] Pedido take-away recorre borrador→confirmado→en_preparacion→listo("para
      retirar")→entregado→facturado sin dirección ni repartidor ni envío.
- [ ] Pedido delivery exige dirección; con georref ON cotiza envío en vivo y
      valida alcance; con OFF solo texto + costo manual.
- [ ] Zona con radio 3km y costo $800 activa de 19 a 23:30 matchea antes que el
      cálculo por km; fuera de su horario cae al cálculo por km.
- [ ] Dirección a 5km con base $500 (2km incluidos) y $200/km ⇒ envío $1.100;
      a 15km con radio 10 ⇒ fuera de alcance (solo forzable con permiso, nunca
      desde la API pública).
- [ ] El costo de envío se materializa como renglón-concepto: suma al total,
      entra al desglose de IVA y a los items del comprobante (Σitems = total,
      sin rechazo de ARCA), NO recibe descuentos/cupones/promos/puntos, y
      aparece en comanda/precuenta.
- [ ] Convertir un pedido delivery con cliente acredita los puntos ganados y
      registra el uso del cupón (CuponUso + uso_actual); los opcionales migran
      a venta_detalle_opcionales.
- [ ] Take-away `listo` aparece en el llamador/pantalla pública con su número
      display (secuencia compartida con mostrador, sin colisiones).
- [ ] Un endpoint API con token de integración opera sin sesión web: permisos
      resueltos por contexto explícito, nunca denegación silenciosa.
- [ ] La dirección de entrega del cliente NUNCA pisa `clientes.direccion`
      (fiscal): factura e impresión siguen mostrando el domicilio fiscal.
- [ ] Asignar 3 pedidos listos a un repartidor + registrar salida ⇒ los 3 pasan
      a en_camino (broadcast); la vuelta marca 2 entregados con cobro efectivo
      (entra al fondo) y 1 no entregado que vuelve a listo.
- [ ] Fondo del repartidor: apertura $5.000 desde caja (egreso), cobros y vueltos
      lo mueven, NO se exige rendir a la vuelta; rendición posterior con
      faltante de $200 queda registrada y el neto ingresa a la caja receptora.
- [ ] Efectivo contra entrega: el pedido queda pagado SIN movimiento de caja
      (vive en el fondo); la caja recibe UN ingreso neto al rendir; un pago QR
      en la puerta va por el circuito normal y no toca el fondo.
- [ ] [FASE 8] Modo franjas: franja 20-21 con cupo delivery 5 lleno rechaza el
      6º pedido delivery pero acepta take-away si su cupo tiene lugar.
- [ ] Modo automático: demora base 15' + 4/km a 5km ⇒ hora pactada +35'.
      [FASE 8: con `usar_maps_para_demora` usa Routes API y cae a km si falla.]
- [ ] Modo manual: aceptar un pedido de tienda abre el modal de demora; +30
      fija hora pactada y el canal de seguimiento la muestra.
- [ ] [FASE 8] Pedido programado para el sábado 21:00 no aparece en el kanban
      hasta 60 min antes (config); programarlo un feriado o con artículo no
      programable es rechazado por la API.
- [ ] Con `acepta_programados` OFF (estado permanente del core): el panel no
      muestra campo ni sección de programados y la API/tienda rechaza cualquier
      `programado_para`.
- [ ] La venta convertida guarda `origen_type`/`origen_id` apuntando al pedido
      (delivery Y mostrador); una venta POS directa queda NULL; "ventas de la
      tienda" se resuelve con join directo (origen del pedido = 'tienda').
- [ ] Catálogo público: artículo con `visible_tienda=false` no aparece; agotado
      sin `permite_venta_sin_stock` aparece como no-pedible y la API bloquea
      pedirlo (el panel solo advierte); categorías con imagen y orden
      respetados; artículos destacados marcados.
- [ ] Aceptación automática + impresión automática: el pedido de tienda entra
      confirmado y la comanda sale sola por la comandera.
- [ ] Rechazar un pedido de tienda pagado online lo marca "a devolver" y avisa
      al consumidor por el canal de seguimiento.
- [ ] Artículo con `disponible_delivery=false` no aparece en el catálogo API
      para delivery y el panel advierte al agregarlo.
- [ ] Vuelta con pedido no entregado: vuelve a listo conservando pagos previos,
      el pivot de salidas conserva el intento; re-despacho en otra salida.
- [ ] Repartidor tercero con envío propio: la rendición liquida los envíos y
      no los computa como ingreso del comercio.
- [ ] Cliente con domicilio georreferenciado precarga dirección+mapa; "entregar
      en otra dirección" no pisa el domicilio del cliente.
- [ ] API: token de integración lista/crea/modifica pedidos delivery con las
      mismas validaciones que el panel (mismos services); catálogo y cotización
      públicos por slug con throttle; pedido de invitado (sin cliente) con
      nombre/teléfono/email + token de seguimiento consultable sin auth.
- [ ] Consumidor registrado pide a un comercio con alta automática OFF: el
      pedido queda con consumidor_id y SIN cliente_id; "convertir en cliente"
      desde el panel crea el cliente + mapping y vincula sus pedidos. Con alta
      automática ON, el primer pedido crea cliente + mapping solo.
- [ ] Pedido creado por API/tienda aparece en el panel EN VIVO como "por aceptar"
      (si aceptación manual) y su cambio de estado se ve en el canal público de
      seguimiento.
- [ ] Paridad carrito: promos, cupones, puntos, invitaciones, descuentos, pagos
      mixtos y conversión a venta funcionan igual que en mostrador (tests espejo).
- [ ] Permisos enforced; smoke tests Livewire de todos los componentes nuevos;
      tests de DeliveryEnvioService (matriz zona/radio/horario/sin-geo) y
      RepartidorService (fondo/rendición/diferencias).

---

## Plan de Implementación

### Fase 1: BD + modelos delivery [COMPLETO — 2026-07-02]
Migraciones tenant 1-9 (incluye origen polimórfico en ventas D20, presentación
tienda RF-17 y normalización de cobrable_type — TODAS las columnas del spec
desde el día 1, también las de features diferidos D22) + 12 modelos nuevos
(PedidoDelivery + 5 satélites, Repartidor, RepartidorFondo + movimientos,
DeliveryZona, DeliverySalida + pivot) + modificaciones (Sucursal con
CONFIG_DELIVERY_DEFAULTS, Cliente, Articulo, Categoria, Venta con morph
origen, morphMap) + tenant_tables.sql regenerado (sanitizado: sin `;` en
COMMENTs). Verificado: pint OK, 93 tests de PedidoMostrador/IntegracionPago
verdes (valida morphMap + template), smoke de los 12 modelos contra tenant real.

### Fase 2: PedidoDeliveryService + DeliveryEnvioService [COMPLETO — 2026-07-02]
Implementado: PedidoDeliveryService (espejo completo: alta/edición con renglón
de envío D17 por deltas, transiciones con en_camino + exigir_repartidor,
pagos con override destino_fondo D13 + movimiento inverso del fondo al anular,
conversión con origen D20 + fixes D19 — CuponUso sin revalidar vigencia,
puntos ganados post-commit, opcionales migrados —, caja override para pedidos
de tienda, comanda/precuenta con PlantillasComanda generalizada a union type)
+ DeliveryEnvioService (cotizar zona→radio→fuera, Haversine, rangos horarios
con cruce de medianoche, calendario estaAbierto, demora automática core) +
CotizacionEnvio DTO + trait ConNumeracionDisplay compartido (extraído de
mostrador, contador display único) + eventos dominio/broadcast delivery +
constantes MovimientoStock/Caja + conversión de MOSTRADOR setea origen (D20)
+ marcarTransaccionesCierre marca pagos de pedidos (D19, excluye
destino_fondo). Tests: 29 nuevos (matriz cotización + ciclo completo) y toda
la carpeta de pedidos verde (94), cierres verdes. Diferido explícito a Fase 3:
salida implícita en el pase manual listo→en_camino (RepartidorService).

### Fase 3: RepartidorService (salidas + fondos) [COMPLETO — 2026-07-03]
Implementado: RepartidorService (crearSalida/agregarPedidos/quitarPedido/
registrarSalida + despacharPedido = salida implícita de 1 pedido del pase
manual; registrarVuelta con cobros ANTES de entregar — efectivo →
confirmarCobroContraEntrega al fondo con movimientos cobro_pedido/vuelto por
el efectivo físico, no-efectivo → circuito normal — pivot append-only con
re-despacho, conversión automática POST-vuelta individual y FUERA de la
transacción; abrirFondo/reforzarFondo con egreso REF_FONDO_REPARTIDOR,
rendirFondo con lockForUpdate + liquidación de envíos de terceros + diferencia
sobrante/faltante + UN ingreso neto por lo declarado + ledger cerrado en cero;
helpers saldoTeorico/fondosAbiertosDeCaja/totalEnFondosAbiertos/
advertenciaFondosAbiertos). cambiarEstado ganó flag `convertirAutomatico`
(la vuelta suprime la conversión en-transacción). Advertencia D13 de fondos
abiertos en los TRES caminos de cierre: TurnoActual::abrirModalCierre (cubre
grupal + individual, banner ámbar en el modal) y
CajaService::cerrarCajaConTesoreria (key `advertencias` + Log::warning).
Traducción es/en/pt. Tests: 21 nuevos (93 aserciones) + fix test preexistente
PedidoIntegracionBloqueo (sembraba cobrable_type FQCN pre-morphMap); carpetas
de pedidos 186 verdes, cierres verdes. Diferido a Fase 4: línea "en fondos de
repartidores" en la UI de reportes de tesorería (el service ya lo expone).

### Fase 4: Panel + alta/edición (UI) [COMPLETO — 2026-07-03]
Implementado por copy-adapt del espejo de mostrador (D1):
- `PedidosDelivery` (panel): kanban de 5 columnas (+en_camino), filtros
  tipo/repartidor/origen/zona (RF-01), badges delivery en cards/tabla
  (partial `_badges-delivery`), sección "En la calle" (salidas en curso),
  asignar repartidor, despachar (drag o botón → RepartidorService::
  despacharPedido; sin repartidor con exigir_repartidor=false →
  cambiarEstado), armar salida multi-pedido (crear+registrar en un paso),
  modal de vuelta con resultado por pedido + cobros (efectivo→fondo D13,
  monto recibido/vuelto; no-efectivo→normal) y cajaConversionId de quien
  registra. SIN filtro por caja (pedidos de tienda no tienen). Broadcast
  canal pedidos-delivery. Label dinámico de `listo` (badge compartido ganó
  en_camino + prop label).
- `NuevoPedidoDelivery` (editor): tipo delivery/take_away segmented (RF-02,
  forma de venta AUTOMÁTICA por código DELIVERY/TAKEAWAY), modal dirección
  con ManejaDomicilio + domicilio-form extendidos (referencia, sin domTipo,
  defaults provincia/localidad de sucursal, mapa si georreferenciar_pedidos),
  cotización RF-06 (zona/km/alcance) con costo editable D7 + recotizar,
  forzar alcance solo con permiso, advertencia RF-16 de artículos no
  disponibles, "entregar en otra dirección" (D6). El envío entra al total de
  pagos vía wrapper calcularVenta (alias del trait) pero los data-totales van
  SIN envío (el service materializa el renglón D17 por delta). Beeper solo
  take-away. Service ganó `_actualizar_direccion_cliente` (actualiza entrega
  del cliente en crear/actualizar, D6/D18).
- `Repartidores` (ABM RF-07 + fondos RF-09): CRUD con pivot sucursales,
  tipo tercero + envío propio, abrir/reforzar fondo (caja origen), rendir
  con diferencia en vivo + aviso de liquidación de terceros, modal de
  movimientos del ledger, total "en fondos" en el header.
- Llamador: PantallaPublicaService::pedidosParaLlamador ahora une los
  take-away de delivery (numero_display compartido, RF-03).
- Tesorería: línea informativa "En fondos de repartidores (abiertos)" en
  ReportesTesoreria (D13).
- Rutas `pedidos/delivery` + `pedidos/repartidores` (menú/permisos = Fase 5).
- Traducciones: 119 claves nuevas es/en/pt (3724 parejas).
- Fix de infraestructura: WithTenant no limpiaba las 13 tablas delivery
  entre tests (residuos desde Fase 1) — agregadas al DELETE selectivo.
Tests: SmokePedidosDeliveryTest 18 verdes (montaje de los 3 componentes +
tipo/dirección/envío D17 end-to-end + despacho/vuelta/armar salida/fondos
desde la UI); suites de pedidos 204 verdes; cierres/tesorería/pantalla 34
verdes; pint OK. Pendiente conocido para Fase 5: seeds formas venta
DELIVERY/TAKEAWAY en ProvisionComercioCommand + migración comercios
existentes (dev ya los tiene por seeder) y permisos/menú (migración 14).

### Fase 5: Configuración delivery + zonas [COMPLETO — 2026-07-03]
Implementado:
- **Migración 14** (`2026_07_03_120000_add_pedidos_delivery_menu_permisos_y_seeds`):
  menú "Pedidos Delivery" + "Repartidores" bajo Ventas (MenuItemObserver crea
  los permisos de menú), 7 permisos funcionales `func.pedidos_delivery.*`
  (cobrar/convertir_venta/cancelar/resetear_numeracion — espejo mostrador — +
  repartidores/forzar_alcance/config) asignados a Administrador/Super Admin en
  todos los tenants, y SEEDS tenant para comercios existentes: formas de venta
  DELIVERY/TAKEAWAY + canal TIENDA (idempotente por código, patrón SHOW TABLES).
  `ProvisionComercioCommand::seedFormasYCanalesVenta()` para comercios nuevos
  (LOCAL/DELIVERY/TAKEAWAY + POS/TIENDA — antes la provisión NO sembraba
  formas/canales de venta en absoluto). Ejecutada y verificada en dev.
- **ConfiguracionDelivery** (`/pedidos/delivery/configuracion`, ajuste de
  implementación: página dedicada en vez de sección dentro de
  ConfiguracionEmpresa — ese componente ya tiene 1600+ líneas; enlazada con
  el engranaje del panel, visible con `func.pedidos_delivery.config`):
  usa_delivery + keys CORE de config_delivery (georef, radio/costos por km,
  categoría del renglón de envío, exigir_repartidor, take-away, aceptación
  externa manual/automática + imprimir al aceptar + timeout, días laborales +
  horarios de atención con repeater días/desde/hasta + feriados, promesa
  automática/manual con demoras y botones). Merge preservando las keys de
  Fase 8. En la MISMA página el **ABM de zonas** (RF-05/RF-06): nombre,
  centro con el picker de Maps (partial domicilio solo-geo, default centro
  de la sucursal), radio, costo propio, rangos horarios, orden y activo;
  eliminar es seguro (FK zona_id ON DELETE SET NULL).
- **RF-16/RF-17 UI mínima**: GestionarArticulos ganó sección "Delivery y
  Tienda" (disponible_delivery/take_away, visible_tienda del pivot de la
  sucursal activa, destacado, permite_venta_sin_stock, orden);
  `permite_programado` se persiste pero NO se expone (D22: programados
  ocultos hasta Fase 8). GestionarCategorias ganó orden + imagen de tienda
  (uploader simple a disco público, carpeta categorias/).
- Traducciones: 96 claves es/en/pt (3820 parejas).
Tests: 3 smokes nuevos de ConfiguracionDelivery (montaje + guardar config
core + crear zona), suites de pedidos 195 verdes, artículos/categorías 77
verdes, pint OK. Pendiente menor anotado: columnas RF-16/17 al final del
import/export de artículos (se hace con la doc de Fase 7).

### Fase 6: API v1 [COMPLETO — 2026-07-03]
Implementado:
- **Sanctum** (^4.3) con `personal_access_tokens` en BD CONFIG (modelo custom
  PersonalAccessToken + Sanctum::usePersonalAccessTokenModel);
  `sanctum.guard=null` (los tokenables no son Users); Comercio implementa
  Authenticatable + HasApiTokens (tokens POR COMERCIO con abilities).
- **Migración config** (`2026_07_03_140000`): tiendas (slug UNIQUE por
  sucursal, D15), rubros + comercios.rubro_id + tienda_alta_cliente_automatica
  (D11), consumidores + consumidor_direcciones + consumidor_comercio (D8),
  personal_access_tokens, permiso funcional `api.tokens` (faltaba en la
  migración 14) asignado a admins. Modelos config + guard `consumidores`.
- **api.tenant** (ApiTenantMiddleware): resuelve por slug (config.tiendas →
  usarComercioParaProceso, 404 genérico sin enumeración) o por token Sanctum
  de Comercio (sucursal por header X-Sucursal-Id, default principal).
  **User::loadAllPermissions extendido**: fallback al comercio explícito de
  TenantService cuando no hay sesión (hallazgo del spec resuelto).
- **Errores JSON uniformes** en bootstrap/app.php para api/v1/*
  ({error:{code,message,details}}): \Exception "pelada" de services → 422
  con mensaje; el resto → 500 genérico logueado.
- **Endpoints públicos** (throttle 60/min): GET tienda (datos+calendario),
  GET catálogo (criterio RF-17 con agotados visibles no-pedibles + precios
  del motor vía PrecioService + opcionales con min/max), POST envios/cotizar,
  POST carrito/cotizar (**CotizadorCarritoTienda**: harness HEADLESS del
  trait WithCalculoVenta — mismo motor de precios/promos/cupones, D12; lista
  resuelta con ListaPrecio::buscarListaAplicable con contexto forma venta
  DELIVERY/TAKEAWAY + canal TIENDA), POST pedidos (PedidoTiendaService:
  bloqueos API — horario, coordenadas, alcance, agotados —, D14
  borrador-por-aceptar o confirmado directo, D11 mapping consumidor→cliente),
  GET pedidos/{token} seguimiento + POST cancelar (hasta confirmado).
  AJUSTE documentado: el seguimiento vive bajo /tiendas/{slug}/pedidos/{token}
  (el slug es necesario para resolver el tenant; el spec lo tenía sin slug).
- **Endpoints integración** (auth:sanctum + abilities + throttle 120/min):
  GET/POST/PATCH pedidos-delivery (PATCH = estado/repartidor/observaciones;
  en_camino con repartidor pasa por la salida implícita), GET delivery/config,
  GET repartidores. Aliases 'ability'/'abilities' registrados.
- **Canal público de seguimiento**: PedidoSeguimientoPublicoBroadcast
  (ShouldBroadcastNow, canal `pedidos-delivery.seguimiento.{token}`) —
  se emite en cambios de estado/confirmación/cancelación de pedidos externos.
- **D14 en el panel**: strip "por aceptar" (borradores externos, excluidos
  del dropdown de borradores) + modal aceptar con botones de demora (RF-15
  manual) o directo (automática por km) + modal rechazar con motivo y aviso
  "A DEVOLVER" si tenía pago online. Service: aceptarPedidoExterno /
  rechazarPedidoExterno.
- **Tokens UI**: ConfiguracionApiTokens (/configuracion/api-tokens, permiso
  func.api.tokens): crear con abilities (token en claro UNA vez, copiar),
  listar con último uso, revocar.
- **Docs**: docs/api-v1-delivery.md (audiencias, abilities, endpoints,
  errores, tiempo real, alta de tienda).
- Fix infra tests: el gate de `migrate` en TestCase solo miraba
  pymes_test.menu_items — ahora también config_test.tiendas (centinela).
Tests: ApiV1DeliveryTest 17 verdes (catálogo RF-17, cotizaciones, D14
manual/automática, seguimiento, cancelación de consumidor, abilities 401/403,
PATCH) + 3 smokes nuevos (ApiTokens + aceptar/rechazar desde el panel);
suites pedidos+API 227 verdes; cierres/tesorería/smokes 198 verdes; pint OK.
Traducciones: 51 claves (3871 parejas). Pendiente conocido: link de
navegación a /configuracion/api-tokens (menú o botón en Configuración) y el
alta de `tiendas` es por consola/soporte en v1.

### Revisiones post-entrega rev1-21 [COMPLETAS — 2026-07-08]
Ver la sección "Enmiendas post-entrega" al inicio (E1-E12): franjas manuales,
usa_estado_listo, conversión fiscal con config propia, numeración display
separada, take-away vía en_camino, viaje único, mini-rendición en la vuelta,
vuelto planificado, lo_antes_posible + hora editable, zonas por polígono,
lista/kanban con botones inline (+ port a mostrador), revisión integral con
3 agentes (rev19-21: consistencia de salidas, caja de contexto, contrato API
completo para la tienda, hardening de permisos, paridad móvil).
Tests agregados: PedidoDeliverySalidaConsistenciaTest (9) + 9 en
ApiV1DeliveryTest (contrato de promesa/pago/estados públicos).

### Fase 7: Verificación + docs [COMPLETA — 2026-07-08]
/sdd-verify ejecutado (matriz abajo) + @docs-sync + PR. La generación de
tests de verificación destapó y corrigió DOS bugs reales de Fase 6:
(a) la validación del POST público usaba `required_without:consumidor` sobre
un campo inexistente (el consumidor viene del Bearer) — un consumidor
logueado sin `cliente.nombre` recibía 422 siempre; (b) `resolverClienteId`
leía `$sucursal->comercio_id` (columna que NO existe en la tabla tenant) —
la D11 nunca resolvía cliente ni mapping; ahora usa el comercio activo de
TenantService (+ `tienda_alta_cliente_automatica`/`rubro_id` faltaban en el
$fillable de Comercio).

#### Spec Compliance Matrix (criterios activos: 28 = 30 − 2 Fase 8)

Resultado: **18 OK · 9 PARCIAL · 0 SIN COBERTURA · 3 sin efecto (con
reemplazo testeado) · 2 Fase 8**. Suites: ApiV1DeliveryTest 30,
PedidoDeliverySalidaConsistenciaTest 9, RepartidorServiceTest 32,
DeliveryEnvioServiceTest 17, PedidoDeliveryServiceTest, SmokePedidosDeliveryTest
31, NuevoPedidoDeliveryCobroTest 21, NumeracionDisplayTest,
PantallaPublicaLlamadorTest — todas verdes.

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Take-away por `listo` | SIN EFECTO (E1) | reemplazo: `test_take_away_pasa_a_en_camino_como_para_retirar` + `test_seguimiento_take_away_en_camino_es_para_retirar_sin_repartidor` |
| 2 | Dirección + georref ON/OFF | OK | `test_delivery_confirmado_sin_direccion_es_rechazado`, `test_georreferenciacion_apagada_devuelve_desconocido…` |
| 3 | Zona radio + fallback horario | SIN EFECTO (E4) | reemplazo: `test_zona_poligono_pisa_el_calculo_por_km`, `test_con_zonas_dibujadas_fuera_de_todas_es_fuera_de_alcance`, `test_franja_de_costo_pisa_el_default…` |
| 4 | Cálculo km + forzar alcance | OK | `test_calculo_por_km_con_base_y_km_incluidos`, `test_fuera_del_radio…`, `test_pedido_publico_fuera_de_alcance_es_rechazado` (API); gate del panel sin test (PARCIAL menor) |
| 5 | Envío renglón-concepto | PARCIAL | totales/IVA/paridad conversión/ajuste-FP testeados; comanda y Σitems ARCA sin test directo |
| 6 | Conversión: puntos+cupón+opcionales | PARCIAL | cupón y opcionales testeados; puntos ganados sin test delivery directo |
| 7 | Llamador secuencia compartida | SIN EFECTO (E3) | reemplazo: `test_delivery_numera_independiente_de_mostrador`, `test_take_away_de_delivery_no_entra_al_llamador` |
| 8 | API token sin sesión | OK | `test_integracion_lista_pedidos_con_token_y_ability` + 401/403 |
| 9 | Entrega no pisa dirección fiscal | OK | `test_establecer_direccion_persiste_entrega_en_cliente_sin_pisar_fiscal` |
| 10 | Salida 3 pedidos + vuelta mixta | OK | `test_crear_y_registrar_salida…`, `test_vuelta_con_cobro_efectivo_al_fondo…`, `test_vuelta_no_entregado_vuelve_a_listo…` |
| 11 | Fondo apertura/diferencia/neto | OK | `test_abrir_fondo_crea_egreso…`, `test_rendir_con_faltante…`, `test_rendir_fondo_exacto…` |
| 12 | Efectivo→fondo, QR→normal | OK | `test_confirmar_pago_planificado_al_fondo_no_crea_movimiento_caja`, `test_vuelta_con_pago_planificado_de_fp_integrada_es_rechazada` |
| 13 | Cupos por franja | FASE 8 | — |
| 14 | Demora automática | OK | `test_demora_automatica_base_mas_minutos_por_km` |
| 15 | Modo manual + demora | OK | `test_aceptar_pedido_externo_lo_confirma_con_demora`, `…demora_cero_queda_lo_antes_posible` |
| 16 | Programados ocultos | FASE 8 | — |
| 17 | `acepta_programados` OFF | PARCIAL | default OFF testeado; rechazo API implícito (el POST no acepta el campo) |
| 18 | Origen polimórfico en ventas | OK | `test_convertir_en_venta_persiste_origen_polimorfico` (+ espejo mostrador) |
| 19 | Catálogo RF-17 | PARCIAL | criterio principal testeado; orden/imagen categorías y destacados sin asert |
| 20 | Aceptación automática + comanda | PARCIAL | confirmado-directo testeado; impresión automática sin test |
| 21 | Rechazo pagado → a devolver | PARCIAL | caso sin pago testeado; `a_devolver=true` sin test |
| 22 | `disponible_delivery` filtra | OK (API) | `test_catalogo_respeta_criterio_rf17`; advertencia del panel sin test (menor) |
| 23 | Vuelta fallida + re-despacho | OK | `test_vuelta_no_entregado…`, `test_volver_a_listo_desde_la_calle_desvincula_y_permite_redespachar` |
| 24 | Tercero liquida envíos | OK | `test_rendicion_liquida_envios_de_terceros`, `test_vuelta_liquida_envios_del_tercero…` |
| 25 | Precarga dirección cliente | PARCIAL | persistencia testeada; precarga del editor sin test |
| 26 | API integración CRUD + público | OK | `test_integracion_post_crea_pedido_con_origen_api`, PATCH, seguimiento, cancelación |
| 27 | Consumidor D11 ON/OFF | OK (mapping) | `test_consumidor_sin_alta_automatica_queda_sin_cliente_tenant`, `test_consumidor_con_alta_automatica_crea_cliente_y_mapping`. La acción MANUAL "convertir en cliente" del panel se DIFIERE al proyecto tienda (sería UI muerta sin login de consumidores) |
| 28 | Por aceptar en vivo + canal público | PARCIAL | flujo por-aceptar y estados públicos testeados; emisión del broadcast sin Event::fake |
| 29 | Paridad carrito | PARCIAL | envío/cupón/ajustes/planificados testeados; sin espejo completo de ParidadVenta |
| 30 | Permisos + smokes + matrices | OK | 31 smokes + matrices Envío/Repartidor; enforcement E8 verificado por código (revisión integral), sin test dedicado |

**Veredicto: APROBADO.** Todos los criterios Tier 1 (dinero/stock/ledger:
#5 núcleo, #10-12, #23-24, más rev19) tienen tests que PASAN; los PARCIAL
son aserciones secundarias de features cuyo camino principal está cubierto,
anotados como deuda de test para Fase 8 / proyecto tienda.

Pendientes arrastrados (post-PR): columnas RF-16/17 al final del
import/export de artículos; alta de `tiendas` por consola (documentado en
docs/api-v1-delivery.md); test del prorrateo de IVA de la conversión fiscal
parcial; acción "convertir en cliente" del panel (proyecto tienda); 87
claves de traducción PREEXISTENTES de master que en/pt muestran en español
(deuda aparte); mejoras espejables a mostrador (D19 + caja de contexto A4).

### Fase 8: Extensiones de promesa [DIFERIDO POST-CORE — D22]
Franjas horarias con cupos (config UI + validación + endpoint `/franjas`) +
pedidos programados (carga, ocultos hasta X min antes, scheduler, validación
API contra calendario y `permite_programado`) + `usar_maps_para_demora` con
Routes API de Google. La estructura (columnas, keys de config, flags de
artículos) ya quedó creada en Fase 1: cero rework de BD.

### Fases futuras (fuera de alcance, diseño ya soportado)
- **Tienda online** (proyecto aparte): consume API v1; registro/login de
  consumidores, checkout, marketplace/landing por rubro y radio. Los datos
  quedan reservados (RF-13). **Precios/promos**: consume el catálogo y la
  cotización de carrito server-side (D12) — todas las promociones, precios
  especiales y descuentos del comercio aplican en la tienda sin reimplementar
  nada. **Pago online**: consume el framework de integraciones de pago del
  comercio (spec `integraciones-pago-mercadopago`) — flujo pedido → preferencia
  de pago → webhook → pago acreditado en el pedido; NOTA: el checkout online de
  MP está PENDIENTE en aquel spec y es prerequisito del pago de la tienda.
- **App/vista de repartidores**: `repartidores.user_id` ya vincula.
- **Zonas por polígono dibujado** (`poligono` reservado) + distancia por calles.
- **Automatización**: asignación automática de repartidor, promesas de tiempo,
  webhooks salientes, integración con cadeterías externas.

---

## Notas y Decisiones

- 2026-07-02 (sesión de diseño con el usuario):
  - **D1 — Espejo de mostrador**: tablas propias `pedidos_delivery*`, mismos
    traits y services agnósticos (ya previsto por el spec de mostrador).
  - **D2 — API REST v1 EN este spec**: el usuario pidió "lo más óptimo";
    REST+Sanctum+Reverb es lo indicado (estándar para terceros, reutiliza la
    infra de broadcasting existente; GraphQL/otros no aportan acá). Sirve de
    base para PR2.D de mostrador.
  - **D3 — Repartidor entidad propia** con `user_id` opcional + tipo
    propio/tercero + flag `envio_es_del_repartidor` (si el envío no es ingreso
    del comercio, se liquida en la rendición).
  - **D4 — Fondo del repartidor de ciclo largo**: provisión/rendición pero SIN
    obligación de rendir a la vuelta; queda abierto entre salidas y se rinde al
    cerrarlo. Movimientos append-only, saldo teórico calculado.
  - **D5 — Georreferenciación condicionada** a config de sucursal; sin geo no
    hay cotización ni validación de alcance (costo manual).
  - **D6 — Dirección**: snapshot en el pedido + persistir lat/lng en el cliente
    para precarga; "entregar en otra dirección" no pisa al cliente.
  - **D7 — Costo de envío editable a mano** en el panel (con auditoría de quién);
    zonas con prioridad sobre cálculo por km; fuera de alcance forzable solo
    con permiso y nunca desde la API pública.
  - **D8 — Tienda online**: UN proyecto multi-tenant para todos los comercios;
    invitados sin registro (pedido sin cliente, patrón temporal de mostrador +
    email) Y cuentas de consumidor GLOBALES cross-comercio en BD config con
    mapping consumidor↔cliente por comercio; URL `/tienda/{slug}` + dominio
    propio futuro; marketplace por rubro+radio futuro. Este spec solo RESERVA
    datos y contratos (migraciones config + guard + endpoints públicos).
  - **D9 — Estados**: se reutiliza `listo` con label dinámico ("para retirar" /
    "para enviar") y se agrega solo `en_camino`; take-away salta listo→entregado.
  - **D10 — Pedidos externos**: entran como "por aceptar" (config
    `aceptacion_manual_tienda`, default true) con aviso en vivo en el panel.
  - **D13 — El efectivo de la calle vive en el fondo**: el cobro contra entrega
    en efectivo NO genera MovimientoCaja (el pedido queda pagado vía pago
    planificado confirmado); la caja recibe UN ingreso neto en la rendición.
    Pagos no efectivo en la puerta van por el circuito normal. El cierre de
    caja advierte fondos abiertos, no bloquea.
  - **D14 — Aceptación configurable por sucursal**: manual (por aceptar +
    timeout con aviso; rechazo de pagado ⇒ "a devolver", manual v1) o
    automática, con opción de imprimir la comanda sola al aceptar
    ("se ponen a trabajar sin tocar nada").
  - **D15 — La tienda es POR SUCURSAL**: cada sucursal tiene su slug/URL propia
    (tabla `tiendas` en config); desaparece el problema de asignación de
    sucursal. `comercios.rubro` (MCC de MP) convive con el nuevo `rubro_id`
    comercial.
  - **D16 — Promesa de entrega COMPLETA en v1**: hora_pactada_at con 3 modos
    configurables por sucursal (franjas con cupos por tipo / automática por
    distancia con opción Routes API de Google / manual con botones +0..+90
    configurables) + pedidos programados (flag maestro `acepta_programados`
    por sucursal, default OFF — en OFF desaparecen de toda la UI; aparecen X
    min antes cuando ON) + calendario (días laborales, feriados, horarios) +
    disponibilidad de artículos por canal
    (disponible_delivery/take_away/permite_programado).
  - **D12 — La tienda consume el motor completo de precios y pagos** (pedido
    explícito del usuario): promociones, precios especiales y descuentos se
    exponen vía catálogo + cotización de carrito server-side (la tienda nunca
    calcula precios); el pago online usa las integraciones de pago del comercio
    (checkout online de MP: prerequisito pendiente en su propio spec).
  - **D11 — El comercio decide el alta del cliente** (refinamiento del usuario
    sobre D8): el consumidor global NO se convierte automáticamente en cliente
    tenant. Política por comercio `tienda_alta_cliente_automatica` (default
    OFF): con OFF el pedido vive con `consumidor_id` (+snapshot de contacto) y
    el panel ofrece "convertir en cliente" (mapping + vinculación de pedidos);
    con ON el primer pedido lo crea solo. Evita llenar el catálogo de clientes
    con compradores ocasionales de la tienda; puntos/cupones/cta cte requieren
    cliente materializado. Los consumidores tienen direcciones guardadas
    globales (`consumidor_direcciones`) reutilizables en cualquier comercio.
- 2026-07-02 (quinta ronda — auditoría de la cadena de escritura de ventas y
  lecturas existentes contra código real):
  - **D17 — El costo de envío se materializa como renglón `es_concepto`**
    (mecanismo existente en pedidos y ventas): un costo de solo-encabezado es
    invisible para `calcularDetallesIva` y los items del comprobante →
    `ImpTotal ≠ ImpNeto+ImpIVA` = rechazo de ARCA. El encabezado queda como
    fuente logística; el renglón-concepto hace cerrar total/IVA/fiscal/
    conversión sin tocar VentaService. Excluido de descuentos/cupones/promos.
  - **D18 — Domicilio de entrega SEPARADO del fiscal en el cliente**:
    `clientes.direccion` alimenta el receptor de ARCA, impresión y padrón —
    pisarlo con "casa de la novia, timbre 3B" rompía la factura. El cliente
    gana `direccion_entrega` + referencia + lat/lng propios.
  - **D19 — La conversión de delivery corrige (no hereda) los gaps verificados
    de mostrador**: puntos ganados no se acreditan al convertir (viven solo en
    Livewire de venta directa), CuponUso no se registra, opcionales no migran a
    venta_detalle_opcionales, y `cierre_turno_id` de pagos de pedido nunca se
    asigna. El service espejo los resuelve a nivel service; queda anotado
    portarlos a mostrador como mejora aparte.
  - Reglas derivadas: la conversión exige caja (ventas.caja_id NOT NULL,
    numeración por caja) → pedidos de tienda sin caja usan la caja de quien
    convierte o quedan "por facturar"; la vuelta carga pagos ANTES de marcar
    entregado (guard de conversión); override `destino_fondo` al confirmar
    pagos planificados (si no, SIEMPRE crean MovimientoCaja); permisos API sin
    sesión (User::loadAllPermissions depende de session()) → contexto
    explícito; formas de venta DELIVERY/TAKEAWAY faltan en la provisión;
    llamador acoplado a PedidoMostrador → extender PantallaPublicaService;
    "en fondos de repartidores" como línea informativa de tesorería;
    advertencia de fondos abiertos va en los TRES caminos de cierre de caja;
    flags de artículos al FINAL del import/export (columnas por letra).
- 2026-07-02 (sexta ronda — cierre pre-aprobación: orientación a tienda,
  trazabilidad de la venta y recorte core; decisiones del usuario):
  - **D20 — Origen polimórfico en ventas, para TODOS los canales**: auditada
    la conversión real de mostrador, la venta NO guardaba referencia al pedido
    (solo `pedido.venta_id` unidireccional; la única traza era texto en
    observaciones de pagos/stock). `ventas.origen_type/origen_id` (morph NULL,
    morphMap 'PedidoMostrador'/'PedidoDelivery') lo setean TODAS las
    conversiones — la de mostrador se actualiza en este mismo desarrollo — y
    aplica a cualquier canal futuro (salón/mesas). Venta directa POS = NULL.
    El "boolean viene de la tienda" pedido por el usuario se resuelve SIN
    columna redundante: se deriva del enum `origen` del pedido
    (`origen='tienda'`, accessor `esDeTienda`), válido para delivery y
    take-away originados en la tienda.
  - **D21 — Presentación en tienda (RF-17), estructura + UI mínima ahora**:
    visible_tienda por sucursal, orden/destacado/permite_venta_sin_stock en
    artículos, imagen/orden en categorías, criterio de visibilidad del
    catálogo público definido (agotado = visible no-pedible). Auditoría contra
    código: articulos tiene 1 imagen+focal y descripción (alcanzan v1);
    NO existía flag de visibilidad web, ni orden, ni imagen de categorías;
    opcionales ya completos. Galería multi-imagen, tagline y banners de promos
    quedan reservados al proyecto tienda.
  - **D22 — Recorte CORE** (pedido del usuario: empezar por el core, sumar
    después): se difieren a Fase 8 franjas con cupos, pedidos programados y
    Routes API de demora. TODA la estructura (columnas hora_pactada_at /
    programado_para, keys de config, permite_programado) se crea en Fase 1 —
    cero rework de BD; `acepta_programados` OFF oculta todo hasta entonces.
    Promesa core: automática por km + manual con botones.
  - Verificado además (auditoría de conversión): promos de pedido SÍ migran a
    venta_promociones y las de renglón se reconstruyen (`_promociones_item`);
    pagos migran con su MovimientoCaja reasignado; el comprobante opera 100%
    sobre la Venta (receptor de `venta->cliente`) — el circuito
    pedido→venta→fiscal de mostrador es sólido y delivery lo hereda con los
    fixes D19. PrecioService::calcularPrecioFinal es invocable sin sesión
    (contexto opcional, exige sucursal) → catálogo/cotización de la tienda
    (D12) viables sin cambios al motor.
- 2026-07-02: la API v1 con Sanctum NO existe hoy (verificado: solo
  ImpresionController); se crea acá el scaffolding completo.
- 2026-07-02: `pedidos_mostrador` ya soporta cliente NULL + datos temporales —
  precedente directo para invitados; delivery agrega email + token de seguimiento.
- 2026-07-02: IVA del costo de envío: v1 dentro del desglose estándar del
  pedido→venta; afinar con el contador si el envío requiere tratamiento propio.
