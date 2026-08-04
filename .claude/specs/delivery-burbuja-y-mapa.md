# Panel de Delivery: burbuja de pedidos por aceptar + mapa - Especificación

## Estado: COMPLETO (pendiente validación en vivo del usuario)

> Implementado el 2026-08-04 en la rama feat/delivery-burbuja-y-mapa.
> Fase 1: 78371fd · Fase 2: 2f33ff0 · Fase 3: 64754ac.
> Tests: mapaPayload + modal alta (lazy/contexto), 12 de corrección de
> dirección, burbuja/bloque móvil; suites Delivery en verde.

---

## Contexto y Motivación

Dos frentes distintos, ambos en el panel de delivery (`app/Livewire/Pedidos/`):

**1. Los pedidos de la tienda online se pierden de vista.** Hoy los pedidos
por aceptar viven en una banda naranja plegable
(`pedidos-delivery.blade.php:268-350`) que arranca **cerrada** y está
embebida en el flujo de la página: si el operador está scrolleado en el
tablero, no la ve. Un pedido de la tienda que no se acepta a tiempo se
cae solo (hay un `timeout_aceptacion_min` configurable). La banda además
empuja el contenido cuando se abre, lo que descoloca el tablero.

**2. El mapa consume API de más y muestra poco.** El picker de Google Maps
(`resources/js/domicilio-mapa.js`) es perezoso por diseño, PERO en el alta
de pedido delivery se lo fuerza a abrir solo (`mapaAutoAbrir=true` en
`nuevo-pedido-delivery.blade.php:947-958`): cada vez que se abre el modal
de dirección se paga una carga del SDK, se abra o no el mapa. Encima el
mapa muestra un pin naranja con el ícono de la PWA (raro para marcar el
domicilio de un cliente), un botón "usar mi ubicación actual" que no tiene
sentido cuando el operador carga la dirección de OTRA persona, y **no
dibuja las zonas de reparto ni la ubicación del local** — que es
justamente el contexto que el operador necesita para saber si la dirección
cae adentro o no.

Además, una dirección mal cargada hoy no se puede corregir desde el modal
"Ver": hay que reabrir el pedido en edición completa.

---

## Principios de Diseño

1. **Cero llamadas a Google Maps sin intención explícita.** El SDK se carga
   solo cuando el operador toca "Abrir mapa". Ningún flujo de delivery lo
   fuerza.
2. **El mapa da contexto, no solo un punto.** Ver el local y las zonas
   dibujadas es lo que convierte al mapa en una herramienta de decisión.
3. **La burbuja no desplaza nada.** Flota por encima; el tablero de atrás
   queda exactamente donde estaba.
4. **Una sola forma de aceptar un pedido.** La burbuja reemplaza la banda,
   no convive con ella.
5. **Ningún cambio de plata en silencio.** Si mover el pin cambia el costo
   de envío, se muestra el delta y lo confirma el operador.
6. **Degradación honesta.** Sin API key el picker sigue siendo inputs
   manuales de lat/lng; sin lat/lng de sucursal no hay pin del local pero
   el resto funciona.

---

## Requisitos Funcionales

### RF-01: Burbuja flotante de pedidos por aceptar
- Reemplaza la banda naranja actual (`pedidos-delivery.blade.php:268-350`).
- **Estado cerrado**: burbuja compacta con SOLO la cantidad de pedidos por
  aceptar (el número grande + un rótulo corto). Sin datos de pedidos.
- Si la cantidad es 0, la burbuja **no se muestra**.
- Mantiene el destello actual ante `pedido-por-aceptar` (evento ya emitido
  por `onPedidoBroadcast`, `PedidosDelivery.php:494`) y suma el estado
  "demorado" (naranja → rojo) cuando algún pedido superó
  `timeout_aceptacion_min`.
- Solo visible en desktop (`hidden sm:flex`). En móvil se mantiene un
  acceso en el flujo normal de la página (ver RF-04).

### RF-02: Arrastre y anclaje a bordes
- Con la burbuja **cerrada**, se puede arrastrar con el mouse.
- Al soltar, se **ancla al borde más cercano** (izquierda, derecha, arriba
  o abajo) y conserva la posición a lo largo de ese borde.
- La posición (borde + desplazamiento) se persiste en `localStorage` bajo
  una clave por comercio+sucursal, de modo que sobrevive recargas y
  navegación `wire:navigate`.
- Al arrastrar NO se abre el panel (se distingue arrastre de click por
  umbral de movimiento).
- La burbuja nunca queda fuera de la ventana: al redimensionar se
  reajusta al borde.

### RF-03: Panel expandido
- Click en la burbuja cerrada → se expande a **la mitad de la pantalla**,
  desplegándose **desde el borde donde está anclada**:
  - Anclada izquierda/derecha → panel de ancho 50vw, alto completo.
  - Anclada arriba/abajo → panel de alto 50vh, ancho completo.
- Se superpone al contenido (`position: fixed`, z-index alto). **El tablero
  de atrás no se desplaza ni se re-renderiza.**
- Dentro del panel, la lista de pedidos por aceptar con los mismos datos
  que hoy muestra la banda (cliente, hora, total, dirección, origen,
  aviso de demorado) y los tres botones existentes: **Ver**
  (`verDetalle`), **Aceptar** (`abrirAceptar`), **Rechazar**
  (`abrirRechazar`).
- Se cierra con una X, con `Escape`, o clickeando fuera del panel.
- Transición de entrada/salida coherente con el borde (slide desde ese
  lado).

### RF-04: Retiro de la banda naranja
- Se elimina el bloque `:268-350` de `pedidos-delivery.blade.php`.
- En **móvil** (donde la burbuja arrastrable no aplica) se conserva un
  bloque compacto en el flujo, con el mismo contenido del panel.
- **Contrato de compatibilidad (no romper lo que anda)**: el `render()`
  sigue pasando `pedidosPorAceptar` a la vista, y los métodos
  `abrirAceptar` / `confirmarAceptar` / `confirmarAceptarComoPidio` /
  `confirmarAceptarFranja` / `abrirRechazar` / `confirmarRechazar` /
  `verDetalle` no cambian de firma. Los tests existentes
  (`SmokePedidosDeliveryTest:210-257`) operan contra ese contrato, no
  contra el markup de la banda, y deben pasar sin modificarse.
- El nuevo pin del domicilio (RF-09) reutiliza el marcador default de
  `AdvancedMarkerElement` (sin `content` custom); `crearPin()` de
  `domicilio-mapa.js` no se borra: pasa a usarse para el pin del local
  (RF-10), que es el rol para el que ese diseño tenía sentido.

### RF-05: Editar dirección y ubicación desde el modal "Ver"
- En el bloque "Datos de entrega" del modal de detalle
  (`pedidos-delivery.blade.php:1381-1428`), junto a la dirección, un botón
  con ícono de mapa.
- Abre un modal con el partial `domicilio-form` en modo delivery: permite
  cambiar **la dirección escrita**, la referencia y **la ubicación
  georreferenciada** (arrastrando el pin).
- **Sin apilar modales** (patrón existente del componente: un modal a la
  vez — ver `abrirModalEditarPedido():603`, que cierra el detalle antes de
  abrir la edición). Abrir el modal de dirección CIERRA el detalle; al
  guardar o cancelar se reabre el detalle (`verDetalle`) para que el
  operador vea el dato actualizado en contexto.
- El modal nuevo se suma a `resetEstadoComponente():542` (cierre de todos
  los modales al cambiar sucursal/caja).
- Con `georreferenciar_pedidos` **apagado** en la config de la sucursal,
  el modal muestra solo los campos de texto (dirección + referencia), sin
  mapa — espejo exacto de la rama sin geo del alta de pedido
  (`nuevo-pedido-delivery.blade.php:964-972`).
- Disponible tanto para pedidos por aceptar como para confirmados.
- **No disponible** si el pedido está cancelado, entregado o ya convertido
  a venta (el dato ya es histórico).
- Solo para pedidos `tipo = delivery`.
- Al guardar se actualizan `direccion_entrega`, `direccion_referencia`,
  `latitud`, `longitud` del pedido.

### RF-06: Recotización del envío al cambiar la ubicación
- Al mover el pin o cambiar la dirección, antes de guardar se recotiza con
  `DeliveryEnvioService::cotizar()` y el modal muestra el impacto:
  - Zona anterior → zona nueva.
  - Costo de envío anterior → nuevo, con el delta.
  - Distancia recalculada.
- El operador confirma explícitamente. Si acepta, se persisten también
  `zona_id`, `costo_envio` y `distancia_km`.
- Si la nueva ubicación queda **fuera de alcance** (ninguna zona la
  contiene, o excede el radio general), se avisa con claridad y se deja
  decidir: guardar igual (sin zona, conservando el costo anterior) o
  cancelar.
- Si el pedido ya está cobrado, el costo de envío **no** se toca: se
  guarda la dirección y se avisa que el envío no se recalculó porque el
  pedido ya tiene pagos.
- La recotización **solo corre** si `georreferenciar_pedidos` está activo
  y la sucursal tiene lat/lng cargadas: `cotizar()` devuelve
  `ALCANCE_DESCONOCIDO` sin geo (`DeliveryEnvioService.php:46-48`) y la
  distancia se calcula desde las coordenadas de la sucursal
  (`:50-55`). Sin esas condiciones, se guarda la dirección sin tocar
  zona/costo/distancia (mismo contrato que el alta).

### RF-07: Mapa perezoso en todo el circuito de delivery
- Se quita `mapaAutoAbrir=true` del modal de dirección del alta/edición de
  pedido (`nuevo-pedido-delivery.blade.php:947-958`): el mapa arranca
  cerrado con el botón "Abrir mapa", igual que en el resto del sistema.
- El nuevo modal de RF-05 nace con el mapa cerrado.
- Sin tocar el botón, **no hay ninguna llamada a la API de Google**.

### RF-08: Quitar "Usar mi ubicación actual" donde la dirección es ajena
- Nueva prop `$conGeolocalizacion` (default `true`) en `domicilio-form`.
- Se pasa en `false` en: alta/edición de pedido delivery, modal nuevo de
  RF-05 y domicilio de clientes.
- Se mantiene en `true` en: sucursales, integraciones MP y domicilios
  fiscales del CUIT (ahí el operador sí está parado en el lugar).
- El método `usarMiUbicacion()` del JS queda, sin uso desde esos flujos.

### RF-09: Pin estándar rojo para el domicilio
- El pin del domicilio deja de ser la gota naranja `#FFAF22` con el ícono
  de la PWA (`domicilio-mapa.js:328-357`) y pasa a ser el **marcador rojo
  por defecto** de Google Maps.
- Aplica a los 5 usos del picker (es el punto que se está editando).
- Sigue siendo arrastrable.

### RF-10: Pin de la sucursal en el picker
- Cuando el host provee contexto de sucursal, el mapa dibuja un segundo
  pin **no arrastrable** en la ubicación del local
  (`sucursales.latitud/longitud`), con el mismo estilo de gota naranja con
  ícono que hoy usa el mapa de zonas (`zonas-mapa.js:157-197`), título
  "Local".
- Si la sucursal no tiene lat/lng cargada, no se dibuja nada (sin error).

### RF-11: Zonas de reparto dibujadas en el picker
- Con contexto de sucursal, el mapa dibuja los polígonos de
  `delivery_zonas` activas con su **nombre** en el centroide, reutilizando
  la paleta y el criterio del mapa de zonas
  (`zonas-mapa.js:200-267`).
- Si no hay zonas dibujadas pero sí `radio_entrega_km`, se dibuja el
  círculo gris punteado del radio general.
- Es **solo visual**: la cotización real la sigue haciendo el backend
  (`DeliveryEnvioService`). El dibujo no decide nada.
- Aplica únicamente a los usos de delivery (alta de pedido y RF-05), no a
  clientes/sucursales/MP/CUIT.

---

## Modelo de Datos

**No requiere migraciones.** Todo lo que se persiste ya existe:

- Dirección del pedido: `pedidos_delivery.direccion_entrega`,
  `direccion_referencia`, `latitud`, `longitud`, `zona_id`, `costo_envio`,
  `distancia_km`.
- Ubicación del local: `sucursales.latitud`, `sucursales.longitud`.
- Zonas: tabla `delivery_zonas` (`poligono` json, `nombre`, `activo`,
  `orden`) y `sucursales.config_delivery->radio_entrega_km`.
- Posición de la burbuja: `localStorage` del navegador (es preferencia de
  puesto de trabajo, no dato de negocio; no viaja al servidor).

---

## Pantallas UI

### Pantalla 1: Pedidos Delivery (`/pedidos/delivery`)
**Componente**: `App\Livewire\Pedidos\PedidosDelivery`
**Traits**: `CajaAware`, `SucursalAware`, `WithPagination`, `WithCobroIntegracion`, **+ `ManejaDomicilio`** (nuevo, para RF-05)

- Burbuja flotante arrastrable con el conteo de por-aceptar (RF-01/02/03).
- Bloque compacto equivalente en móvil (RF-04).
- Modal de detalle: botón de mapa junto a la dirección (RF-05).
- Modal nuevo "Editar dirección de entrega" con recotización (RF-05/06).

### Pantalla 2: Alta/edición de pedido delivery (modal)
**Componente**: `App\Livewire\Pedidos\NuevoPedidoDelivery`
- Modal de dirección: mapa cerrado por defecto (RF-07), sin botón de
  geolocalización (RF-08), con pin rojo + local + zonas (RF-09/10/11).

---

## Servicios

### `DeliveryEnvioService` — `app/Services/Pedidos/DeliveryEnvioService.php`
- `cotizar(...)`: **ya existe**, se reutiliza tal cual para RF-06.
- `mapaPayload(Sucursal $sucursal): array` **(nuevo)**: centro
  (lat/lng de la sucursal), `radio_entrega_km` y zonas activas con
  `{id, nombre, poligono, activo}`. Extrae la lógica hoy duplicada en
  `ConfiguracionDeliveryEnvio::zonasMapaPayload():396`, que pasa a
  delegar acá.

### `PedidoDeliveryService` — `app/Services/Pedidos/PedidoDeliveryService.php`
- `actualizarDireccion(PedidoDelivery $pedido, array $datos, bool $recotizar): array` **(nuevo)**:
  valida el estado del pedido, persiste dirección/referencia/coords,
  recotiza si corresponde y devuelve el resumen del cambio. Transacción
  `DB::connection('pymes_tenant')->transaction()`.

---

## Migraciones Necesarias

Ninguna.

---

## Traducciones

| Clave (es) | en | pt |
|------------|----|----|
| Pedidos por aceptar | Orders to accept | Pedidos a aceitar |
| Editar dirección de entrega | Edit delivery address | Editar endereço de entrega |
| Ver en el mapa | View on map | Ver no mapa |
| El envío cambia de :antes a :despues | Delivery fee changes from :antes to :despues | O frete muda de :antes para :despues |
| La nueva ubicación queda fuera de la zona de reparto | The new location is outside the delivery area | A nova localização está fora da área de entrega |
| Guardar igual | Save anyway | Salvar mesmo assim |
| El pedido ya tiene pagos: no se recalculó el envío | The order already has payments: delivery fee was not recalculated | O pedido já tem pagamentos: o frete não foi recalculado |
| Local | Store | Loja |
| Zona de reparto | Delivery area | Área de entrega |
| Arrastrá para mover | Drag to move | Arraste para mover |

---

## Criterios de Aceptación

- [ ] Con 0 pedidos por aceptar, la burbuja no aparece.
- [ ] La burbuja cerrada muestra únicamente el número.
- [ ] Arrastrar la burbuja y soltar la ancla al borde más cercano; recargar
      la página la deja donde estaba.
- [ ] Arrastrar no abre el panel; click sí.
- [ ] El panel expandido ocupa media pantalla desde el borde anclado y el
      tablero de atrás no se mueve.
- [ ] Aceptar/rechazar desde el panel funciona igual que en la banda vieja.
- [ ] La banda naranja ya no existe en desktop.
- [ ] Abrir el modal de dirección en el alta de pedido **no** dispara
      ninguna request a `maps.googleapis.com` hasta tocar "Abrir mapa".
- [ ] "Usar mi ubicación actual" no aparece en pedido delivery ni clientes,
      y sí sigue en sucursales/MP/CUIT.
- [ ] El pin del domicilio es el rojo estándar y se puede arrastrar.
- [ ] Con la sucursal georreferenciada, se ve el pin del local.
- [ ] Las zonas activas se dibujan con su nombre.
- [ ] Desde "Ver" se puede corregir la dirección de un pedido por aceptar y
      de uno confirmado.
- [ ] Al mover el pin a otra zona, el modal muestra el cambio de costo y
      exige confirmación antes de guardar.
- [ ] Una ubicación fuera de alcance avisa y permite guardar igual.
- [ ] Un pedido con pagos guarda la dirección sin tocar el costo de envío.
- [ ] Con `georreferenciar_pedidos` apagado, el modal de RF-05 es solo
      texto y guarda sin recotizar.
- [ ] Abrir el modal de dirección cierra el detalle; al cerrar/guardar, el
      detalle se reabre solo.
- [ ] `SmokePedidosDeliveryTest` (incl. `:210` aceptar externo y `:250`
      franja) pasa **sin modificar los tests**.

---

## Plan de Implementación

### Fase 1: Mapa — contexto y consumo [PENDIENTE]
1. `DeliveryEnvioService::mapaPayload()` + `ConfiguracionDeliveryEnvio`
   delegando ahí.
2. `domicilio-form`: props `$conGeolocalizacion` y `$mapaContexto`.
3. `domicilio-mapa.js`: pin rojo estándar (RF-09), pin del local (RF-10),
   polígonos + radio + etiquetas (RF-11).
4. Quitar `mapaAutoAbrir` del alta de pedido (RF-07) y apagar
   geolocalización en pedido delivery y clientes (RF-08).
5. Tests: el partial no emite el bloque de geolocalización cuando la prop
   está en `false`; `mapaPayload()` arma centro + zonas.

### Fase 2: Editar dirección desde "Ver" [PENDIENTE]
1. `PedidoDeliveryService::actualizarDireccion()` con recotización.
2. `PedidosDelivery` + trait `ManejaDomicilio`, métodos
   `abrirEditarDireccion`, `previsualizarRecotizacion`, `guardarDireccion`.
3. Modal nuevo (`<x-bcn-modal>`) + botón de mapa en el bloque de entrega.
4. Tests de servicio: recotiza, fuera de alcance, pedido con pagos,
   estados no editables.

### Fase 3: Burbuja flotante [PENDIENTE]
1. `Alpine.data('burbujaPorAceptar')` en `resources/js/` (arrastre,
   anclaje, persistencia en localStorage, apertura por borde). El bundle
   externo es obligatorio ([[feedback_alpine_data_bundle]]); `x-data`
   ESTÁTICO (sin payload dinámico interpolado) para sobrevivir a los
   morphs del `wire:poll.60s` sin re-init
   ([[project_gotcha_xdata_dinamico_reinit]]); la posición se aplica con
   `:style` desde estado Alpine, que el morph preserva.
2. Migrar el listener `@pedido-por-aceptar.window` (destello) de la banda
   a la burbuja; el broadcast server-side no cambia.
3. Partial blade de la burbuja + panel, con la lista y los 3 botones. Al
   tocar Ver/Aceptar/Rechazar el panel se repliega (los modales del
   componente quedan al frente, sin pelear z-index).
4. Quitar la banda naranja; bloque móvil equivalente.
5. Registrar en `bootstrap.js`; `npm run build`
   ([[feedback_build_tras_clases_nuevas]]).
6. Smoke test del componente + test de que la banda ya no se renderiza,
   con los tests existentes de aceptación intactos.

### Fase 4: Verificación y cierre [PENDIENTE]
1. `php vendor/bin/pint --test` + `php artisan test --filter=Delivery`.
2. Traducciones en los 3 idiomas, orden alfabético.
3. `@docs-sync` para `manual-usuario.md` y `ai-knowledge-base.md`.
4. PR.

---

## Notas y Decisiones

- **2026-08-04**: Al cambiar la dirección desde "Ver" se **recotiza y se
  avisa antes de guardar** (opción elegida sobre aplicar en silencio o no
  tocar el costo). Motivo: el costo de envío es plata del cliente; que
  cambie sin que el operador lo vea es el peor de los mundos, pero
  tampoco tiene sentido dejar un costo que ya no corresponde a la
  distancia real.
- **2026-08-04**: "Usar mi ubicación actual" se quita **solo donde la
  dirección es de otra persona** (pedido delivery, clientes) y se mantiene
  en sucursales/MP/CUIT, donde el operador está físicamente en el lugar
  que quiere georreferenciar.
- **2026-08-04**: El panel se despliega **desde el borde donde está
  anclada la burbuja**, no siempre desde la derecha. Es lo coherente con
  el gesto de arrastre.
- **2026-08-04**: La burbuja **reemplaza** la banda naranja en desktop; no
  conviven, para no duplicar los botones de aceptar/rechazar del mismo
  pedido en la misma pantalla.
- **2026-08-04**: La posición de la burbuja va a `localStorage` y no a la
  base: es una preferencia de puesto de trabajo (dónde le queda cómodo al
  operador en ESE monitor), no un dato de negocio que deba viajar entre
  dispositivos.
- **2026-08-04**: El dibujo de zonas en el picker es **solo visual**. La
  zona y el costo los sigue resolviendo `DeliveryEnvioService` en el
  backend; duplicar el ray-casting en JS sería una segunda fuente de
  verdad.
- **2026-08-04 (repaso pre-implementación)**: verificado contra el código
  que (a) `ManejaDomicilio` no colisiona con nada de `PedidosDelivery`
  (ningún `dom*` ni `mapsHabilitado` definidos ahí, y el trait no aporta
  listeners que pisen el arbitraje CajaAware/SucursalAware); (b) el
  patrón del componente es UN modal a la vez (`abrirModalEditarPedido`
  cierra el detalle) — el modal de dirección lo respeta en vez de apilar;
  (c) los tests de aceptación existentes van contra `viewData` y métodos
  Livewire, no contra el markup de la banda: el contrato de RF-04 los
  deja intactos; (d) `cotizar()` exige `georreferenciar_pedidos` + coords
  de sucursal — la recotización de RF-06 hereda esas condiciones en vez
  de inventar otras.
