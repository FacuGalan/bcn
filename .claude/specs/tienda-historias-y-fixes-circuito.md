# Tienda: Historias destacadas + ajustes estéticos + fixes circuito pedidos — Especificación

## Estado: EN REVISIÓN

> Spec cross-repo (bcn_pymes CORE + bcn-tienda). Continúa la numeración del
> spec maestro `.claude/specs/tienda-online.md`: RF-T24..RF-T27.
> Creado 2026-07-28 tras exploración de ambos repos (causas raíz de los 3
> bugs identificadas y documentadas abajo).

---

## Contexto y Motivación

Cuatro necesidades detectadas usando la tienda en vivo:

1. **Historias destacadas**: la tienda no tiene un espacio de comunicación
   visual efímera (novedades, promos). Se quiere el patrón Instagram: anillo
   con glow alrededor del logo del header que invita a tocar y abre un visor
   de historias (hasta 3 fotos + opcionalmente las promociones del día).
2. **Ajustes estéticos**: la opción "fundir la portada" ya no se quiere
   (queda portada cruda); los bordes redondeados generales aplican a
   tarjetas/carrito pero el logo necesita su PROPIO control de forma; falta
   aire entre la tarjeta de datos del comercio y el carrusel de categorías.
3. **Bugs del circuito tienda→panel delivery** (causas raíz confirmadas):
   - **Opcionales**: se pierden silenciosamente. `CotizadorCarritoTienda`
     produce lista PLANA `{opcional_id, descripcion, precio, cantidad}` pero
     `PedidoDeliveryService::guardarOpcionalesDetalle()` espera el formato
     AGRUPADO del panel `{grupo_id, grupo_nombre, tipo, selecciones: [...]}`
     → el `foreach ($grupo['selecciones'] ?? [])` itera 0 veces → 0 filas en
     `pedido_delivery_detalle_opcionales`. El precio sí queda bien (por eso
     no saltó antes). Afecta también seguimiento público y conversión a venta.
   - **Horario**: el dato SÍ se persiste (`hora_pactada_at` /
     `programado_para` / `lo_antes_posible` llegan bien). Falla el panel: la
     banda "por aceptar" no lo muestra, `abrirAceptar()` no lo precarga y
     `aceptarPedidoExterno()` lo PISA con la elección del operador.
   - **Reverb**: `crearPedido()` solo emite `PedidoDeliveryBroadcast` dentro
     de `if (! $esBorrador)`; con aceptación manual (default) todo pedido de
     tienda nace borrador → nunca se emite → el panel no se entera sin F5.
4. **Banda "por aceptar" plegable**: hoy es una banda fija que ocupa lugar;
   se quiere plegada por defecto mostrando solo el contador, expandible.

---

## Principios de Diseño

1. **El contrato manda** (congelado, `docs/api-v1-delivery.md`): todo cambio
   es ADITIVO (claves nuevas). Claves existentes no se renombran ni quitan;
   las que dejan de usarse se marcan deprecadas y se siguen enviando.
2. **La tienda no calcula nada**: historias, promos y tema salen del core
   por API. La tienda solo renderiza.
3. **Paridad de formatos**: el formato agrupado de opcionales que produce
   `WithOpcionales` (panel) es el formato canónico; el cotizador de tienda
   debe producir EXACTAMENTE el mismo shape (memoria:
   `construirData*` en paridad).
4. **Tiempo real con `ShouldBroadcastNow`** sobre la infraestructura
   existente (`TenantBroadcastEvent`, canal
   `comercios.{id}.pedidos-delivery`).
5. **Respetar la elección del consumidor**: el horario elegido en la tienda
   es el valor por defecto en todo el flujo del panel; el operador puede
   cambiarlo pero nunca perderlo sin verlo.
6. **Estética con tokens**: toda forma/espaciado nuevo viaja como variable
   CSS derivada del `tema` (igual que `--tienda-radio-ui/card`), con espejo
   manual en `resources/js/preview.js` de bcn-tienda.

---

## Requisitos Funcionales

### RF-T24: Historias destacadas (cross-repo)

**Panel (core) — sección nueva "Historias destacadas" en `ConfiguracionTienda`:**
- Subir hasta **3 fotos** (mismo pipeline que logo/portada:
  `ImagenTiendaService`, WebP, ≤5MB, MIME real por finfo; dimensión máxima
  **1080×1920** con `scaleDown` — formato vertical tipo story).
- Reordenar (subir/bajar) y eliminar cada foto.
- El **toggle actual de promociones** (`tema.promos.mostrar_home`) se MUDA a
  esta sección con nuevo label: "Mostrar también las promociones disponibles
  como historia". Misma clave del contrato (aditivo: cambia la semántica
  documentada, no el nombre; ambos lados se deployan coordinados).
- Las fotos se procesan al "Guardar apariencia" (mismo flujo pendiente que
  logo/portada).

**API v1 (core) — `GET /v1/tiendas/{slug}` agrega (aditivo):**
```json
"historias": [ { "id": "uuid", "url": "https://..." } ]   // orden = orden de despliegue, [] si no hay
```
Las promos ya viajan por `GET /catalogo` → `promociones_genericas`
(incluye `condiciones[]`); no se agrega endpoint.

**Tienda (bcn-tienda) — anillo + visor:**
- **Condición de activación**: `count(historias) > 0` O
  (`tema.promos.mostrar_home` = true Y `count(promociones_genericas) > 0`).
- **Anillo**: el logo del hero se rodea con un anillo degradé
  (colores `primario`→`acento` del tema) + **glow suave latente** (animación
  pulse sutil, respeta `prefers-reduced-motion`). La forma del anillo SIGUE
  el radio configurado del logo (RF-T25). Sin historias activas: sin anillo
  (como hoy).
- **Visor**: al tocar el logo se abre overlay full-screen con animación de
  expansión desde la posición del logo (estilo IG: scale + fade desde el
  origen). Arriba: **barras de progreso**, una por historia. Navegación:
  tap mitad derecha avanza, mitad izquierda retrocede, **auto-avance cada
  5s** (la barra activa se llena en 5s), botón X y swipe-down cierran.
- **Slides**: primero las fotos (en orden); si el toggle de promos está
  activo y hay promos, **un único slide final "Promociones de hoy"** que
  lista TODAS las promos con nombre, descripción, precio y sus
  **condiciones** (horarios válidos, formas de pago válidas, unidades
  requeridas, etc.) — estilizado con el tema (colores/fuente/radios).
- **El pill "🎉 Promociones de hoy" de la home se ELIMINA**
  (`tienda/secciones/promos.blade.php` deja de renderizarse): las promos
  pasan a vivir dentro de las historias.
- **Visto / no visto**: en SESIÓN server-side de la tienda (mismo mecanismo
  y vida que el carrito: `SESSION_LIFETIME=120` → **2 horas**). Se guarda un
  fingerprint del set (ids de historias + flag promos + fecha de promos).
  Al ABRIR el visor, el set completo se marca visto → el anillo pasa a gris
  neutro sin glow (como IG). Si el comercio cambia las fotos o las promos
  del día cambian, el fingerprint cambia → vuelve a "no vista".

### RF-T25: Ajustes estéticos (cross-repo)

1. **Quitar "fundir la portada"**: se elimina el checkbox del panel
   (`$portadaOverlay`) y el efecto en la tienda → **portada cruda siempre**
   (se elimina el overlay `bg-primario/15` del hero). Contrato:
   `tema.portada.overlay` queda **deprecada** — el core la sigue enviando
   siempre `false` (tolerancia, sin v2); la tienda la ignora.
2. **Radio propio del logo**: nueva clave `tema.logo.radio`
   (`none|sm|md|lg|full`, **default `full`** = círculo actual). Nuevo
   control "Forma del logo" en el panel (mismo selector visual que "Bordes
   redondeados"). La tienda aplica var CSS `--tienda-radio-logo` al `<img>`
   del logo del hero (reemplaza el `rounded-full` hardcodeado) y al anillo
   de historias. Mapeo: `none=0`, `sm=0.75rem`, `md=1.25rem`, `lg=2rem`,
   `full=9999px` (proporcional al logo de `size-20`). Los "Bordes
   redondeados" generales (`tema.radios`) siguen aplicando SOLO a
   tarjetas/carrito/UI (ya es así — el logo estaba hardcodeado).
   Espejo en `preview.js` obligatorio.
3. **Espacio hero ↔ categorías**: agregar aire entre la tarjeta de datos
   del comercio y el carrusel de categorías. Implementar en el HERO
   (margen/padding inferior del contenedor de la tarjeta), NO en la nav de
   categorías (es `sticky`: un margen ahí corre el punto de pegado).
   Magnitud: un paso de la escala (`pb-3`/`mb-3` ≈ 0.75rem, escala con la
   densidad configurada).

### RF-T26: Fix circuito tienda→panel — opcionales y horario (core)

1. **Opcionales**: `CotizadorCarritoTienda` resuelve el grupo de cada
   opcional (`ArticuloGrupoOpcionalOpcion → articuloGrupoOpcional →
   grupo_opcional_id` + nombre del `GrupoOpcional`) y emite el **formato
   agrupado canónico** (paridad exacta con `WithOpcionales`):
   ```php
   [['grupo_id' => int, 'grupo_nombre' => string, 'tipo' => string,
     'selecciones' => [['opcional_id' => int, 'nombre' => string,
                       'cantidad' => float, 'precio_extra' => float]]]]
   ```
   Con eso `guardarOpcionalesDetalle()` inserta las filas en
   `pedido_delivery_detalle_opcionales` sin tocarse. Verificar que quedan
   sanos: detalle del panel, seguimiento público (`GET /pedidos/{token}`),
   comanda y **conversión a venta** (`venta_detalle_opcionales`).
   El shape que expone `carrito/cotizar` al público NO cambia (el formato
   agrupado es interno, entre cotizador y persistencia).
2. **Horario visible y respetado en el panel**:
   - La banda "por aceptar" muestra la promesa de cada pedido:
     "Lo antes posible" / franja elegida (label legible) /
     "Encargo: {fecha} {hora}".
   - `abrirAceptar()` precarga la promesa existente: el modal muestra
     destacado "El cliente eligió: X" con ese valor PRESELECCIONADO; el
     operador confirma con un click o lo cambia si no puede cumplirlo.
   - `aceptarPedidoExterno()` NO pisa `hora_pactada_at`/`programado_para`
     si el operador confirmó sin cambios; solo escribe si eligió otra cosa.
   - Modo aceptación `automatica`: si el pedido trae franja o encargo, se
     respeta lo elegido (no se recalcula demora por km).

### RF-T27: Tiempo real + banda "por aceptar" plegable (core)

1. **Broadcast al nacer borrador externo**: nuevo tipo
   `PedidoDeliveryBroadcast::TIPO_POR_ACEPTAR = 'por_aceptar'`. En
   `PedidoDeliveryService::crearPedido()`, cuando `$esBorrador` y el origen
   es externo (tienda/api), emitir `dispatchBroadcast($pedido,
   TIPO_POR_ACEPTAR)` (además del flujo actual sin cambios para
   no-borradores). También emitir al **rechazar** un pedido externo (tipo
   `cancelado`) para que la banda se actualice en todos los operadores
   (aceptar ya emite `TIPO_CREADO` vía `confirmarBorrador()`).
2. **Panel escucha `por_aceptar`**: `onPedidoBroadcast()` maneja el tipo
   nuevo → refresca `pedidosPorAceptar` en vivo (sin F5), badge con pulso.
   `snapshotIdsVistos()` SIGUE excluyendo borradores (el contador "Ver N
   nuevos" del tablero es solo para pedidos confirmados; los por-aceptar
   tienen su propio contador en la banda).
3. **Banda plegable**: la banda de pedidos externos por aceptar queda
   **plegada por defecto**, mostrando una cabecera compacta con badge
   pulsante "N por aceptar" (+ indicador rojo si algún pedido venció
   `timeout_aceptacion_min`). Click/tap expande y colapsa (Alpine local,
   sin persistencia). Al llegar un pedido nuevo por Reverb la cabecera
   destella pero NO se auto-expande. Expandida: mismo contenido actual
   (Ver / Aceptar / Rechazar) + la promesa de horario (RF-T26.2).

---

## Modelo de Datos

### Tablas modificadas

#### `config.tiendas` (conexión `config`, NO tenant)
- Agregar: `historias` (JSON, NULL) AFTER `portada_path`.
  Shape: `[{ "id": "uuid", "path": "tiendas/{comercio_id}/historias/{uuid}.webp", "orden": 1 }]`
  Máximo 3 elementos (validado en service, no en BD).

#### `Tienda::TEMA_DEFAULTS` (JSON `tema`, sin migración)
- Agregar: `'logo' => ['radio' => 'full']`.
- `'portada' => ['overlay' => ...]` pasa a emitirse siempre `false`
  (deprecada; la clave NO se elimina del shape).
- `'promos' => ['mostrar_home' => false]` se mantiene tal cual (la UI se
  muda de sección; la semántica pasa a "promos como historia").

**Sin tablas tenant nuevas ni modificadas** → no se regenera
`tenant_tables.sql`. Estado "historias vistas": sesión de bcn-tienda (sin BD).

---

## Pantallas UI

### 1. `ConfiguracionTienda` (existente, core)
**Componente**: `App\Livewire\Configuracion\ConfiguracionTienda` (embebido en ConfiguracionDelivery)
- Nueva sección "Historias destacadas" (entre Identidad visual y Apariencia):
  3 slots de imagen con preview, reordenar y eliminar; toggle de promos
  mudado aquí. Se persiste con el botón "Guardar apariencia".
- Quitar checkbox "Fundir la portada…".
- Nuevo selector "Forma del logo" (mismas 5 opciones visuales que Bordes
  redondeados).
- El visor en vivo del panel refleja anillo/portada/radio tras guardar
  (mismo mecanismo actual de refresh del iframe + `preview.js` espejo).

### 2. `PedidosDelivery` (existente, core)
**Componente**: `App\Livewire\Pedidos\PedidosDelivery` (SucursalAware)
- Banda "por aceptar" plegable (RF-T27.3) + promesa de horario visible.
- Modal Aceptar precargado con la elección del cliente (RF-T26.2).

### 3. Hero + visor de historias (bcn-tienda)
- `tienda/secciones/hero.blade.php`: anillo + glow condicional, radio de
  logo por var CSS, espacio inferior extra, sin overlay de portada.
- Componente nuevo de visor de historias (Alpine + Blade; sin Livewire —
  los datos ya llegan con la página): overlay, barras de progreso,
  auto-avance 5s, slide de promos temado.
- Endpoint liviano en bcn-tienda (POST interno, no API core) para marcar
  el set como visto en sesión.
- `tienda/secciones/promos.blade.php`: se elimina del flujo de la home.

---

## Servicios

### `ImagenTiendaService` (core, existente)
- `agregarHistoria(Tienda $tienda, UploadedFile $archivo): array` — valida
  (máx 3, 5MB, MIME real), procesa WebP 1080×1920 `scaleDown`, guarda en
  `tiendas/{comercio_id}/historias/`, actualiza JSON `historias`.
- `eliminarHistoria(Tienda $tienda, string $id): void` — borra archivo y
  entrada JSON.
- `reordenarHistorias(Tienda $tienda, array $idsOrdenados): void`.

### `CotizadorCarritoTienda` (core, existente)
- Cambiar el armado de `opcionales` por ítem al formato agrupado canónico
  (RF-T26.1), resolviendo `grupo_opcional_id`, `grupo_nombre` y `tipo`
  desde `ArticuloGrupoOpcional`/`GrupoOpcional` (sin N+1: eager load).

### `PedidoDeliveryService` (core, existente)
- `crearPedido()`: broadcast `TIPO_POR_ACEPTAR` para borradores externos.
- `aceptarPedidoExterno()`: nueva firma/flag para respetar promesa
  existente (no pisar si el operador no cambió).
- `rechazarPedidoExterno()` (o equivalente): broadcast al rechazar.

### `TiendaController` API v1 (core, existente)
- `show()`: agregar clave `historias` (urls absolutas con `url()`).

### bcn-tienda: `TiendaActual`
- `historias(): array`, `logoRadio(): string`, getter de "historias vistas"
  contra sesión + fingerprint. `temaCssVars()` emite `--tienda-radio-logo`.
  `portadaOverlay()` queda sin uso (se elimina su consumo en el hero).

---

## Migraciones Necesarias

1. `add_historias_to_tiendas` (conexión `config`) — columna JSON `historias`
   NULL en `config.tiendas`. Migración simple (NO itera comercios: la tabla
   vive en config).

---

## Contrato (`docs/api-v1-delivery.md`)

- Sección "Identidad visual": agregar `historias: [{id, url}]` (aditivo).
- Sección "Analytics, tema y comportamiento": agregar `tema.logo.radio`;
  marcar `tema.portada.overlay` como **deprecada** (siempre `false`);
  actualizar la nota de `tema.promos.mostrar_home` ("promos como historia";
  el pill de home ya no existe).
- Los contract tests de bcn-tienda actualizan fixtures en la fase tienda.

---

## Traducciones

| Clave (es) | en | pt |
|------------|----|----|
| Historias destacadas | Featured stories | Histórias em destaque |
| Subí hasta 3 fotos que se mostrarán como historias al tocar el logo de tu tienda | Upload up to 3 photos shown as stories when tapping your store logo | Envie até 3 fotos exibidas como histórias ao tocar no logo da sua loja |
| Mostrar también las promociones disponibles como historia | Also show available promotions as a story | Também mostrar as promoções disponíveis como história |
| Forma del logo | Logo shape | Forma do logo |
| El cliente eligió | Customer chose | O cliente escolheu |
| Lo antes posible | As soon as possible | O quanto antes |
| :count por aceptar | :count pending acceptance | :count por aceitar |
| Promociones de hoy | Today's promotions | Promoções de hoje |

(Las claves de bcn-tienda van en sus propios archivos de idioma; lista
definitiva en implementación.)

---

## Criterios de Aceptación

- [ ] Pedido de tienda con artículo + opcionales → filas correctas en
      `pedido_delivery_detalle_opcionales` (grupo, nombre, cantidad,
      precio_extra); visibles en detalle del panel, seguimiento público y
      venta convertida.
- [ ] Pedido de tienda con franja elegida → la banda la muestra, el modal
      Aceptar la trae preseleccionada, y al confirmar sin cambios
      `hora_pactada_at` NO cambia. Ídem encargo (`programado_para`).
- [ ] Pedido de tienda (aceptación manual) → aparece en la banda del panel
      SIN refrescar (Reverb, < 2s), contador se actualiza.
- [ ] Banda por aceptar plegada por defecto con contador; expande/colapsa;
      indicador rojo por timeout sigue funcionando.
- [ ] Config: subir 3 fotos, reordenar, eliminar; API expone `historias`.
- [ ] Tienda: anillo + glow solo si hay historias o promos-como-historia;
      visor con progreso, tap adelante/atrás, auto-avance 5s, cierre.
- [ ] Slide de promos único con TODAS las condiciones cuando el toggle está
      activo; el pill de la home ya no existe.
- [ ] Visto/no visto en sesión (2h); cambiar fotos o promos lo resetea.
- [ ] Portada sin overlay en TODAS las tiendas; checkbox eliminado.
- [ ] `tema.logo.radio` aplica al logo y al anillo; `tema.radios` sigue
      aplicando a tarjetas/carrito; preview del panel refleja ambos.
- [ ] Espacio visible entre tarjeta del comercio y carrusel de categorías;
      el sticky de categorías no cambia su punto de pegado.
- [ ] Contract tests de bcn-tienda verdes con fixtures actualizados.
- [ ] Pint + PHPUnit verdes en ambos repos; smoke tests de componentes
      tocados.

---

## Plan de Implementación

### Fase 1 (core): Fix opcionales [COMPLETO]
1. `CotizadorCarritoTienda`: formato agrupado canónico con resolución de
   grupo (eager load, sin N+1). ✅ (2026-07-28: mapa opcional_id→[opción,
   grupo] preservando el criterio "último gana" del keyBy previo)
2. Tests: alta de pedido vía API con opcionales → filas persistidas;
   conversión a venta con opcionales; seguimiento público las expone.
   ✅ (test_pedido_persiste_opcionales_con_grupo_y_el_seguimiento_los_devuelve;
   la conversión a venta lee la relación ya cubierta por tests D19 propios.
   Suite ApiV1DeliveryTest completa: 89 passed)

### Fase 2 (core): Horario en el panel [COMPLETO]
1. Banda por aceptar: mostrar promesa (franja/encargo/ASAP).
   ✅ (2026-07-28: badge con reloj vía `PedidoDelivery::promesaClienteInfo()`)
2. `abrirAceptar()` precarga promesa; modal "El cliente eligió: X"
   preseleccionado; `aceptarPedidoExterno()` no pisa si no hubo cambio;
   modo `automatica` respeta franja/encargo del pedido.
   ✅ (bloque destacado + botón "Aceptar como lo pidió" →
   `confirmarAceptarComoPidio()` llama al service SIN parámetros — el
   service ya respetaba la promesa existente; guard nuevo: encargos no
   reciben hora calculada por distancia)
3. Tests de aceptación (con/sin cambio de horario, 3 modos).
   ✅ (2 tests service en ApiV1DeliveryTest + 1 Livewire en
   SmokePedidosDeliveryTest; suites completas verdes: 35 smoke + 91 API)

### Fase 3 (core): Reverb + banda plegable [COMPLETO]
1. `TIPO_POR_ACEPTAR` + broadcast en borradores externos + broadcast al
   rechazar. ✅ (2026-07-28; el rechazo YA broadcasteaba TIPO_CANCELADO
   vía cancelarPedido — no hizo falta tocarlo)
2. `onPedidoBroadcast()` maneja el tipo nuevo; banda plegable (Alpine),
   contador pulsante, destello al llegar nuevo.
   ✅ (banda plegada por defecto con cabecera compacta: contador pulsante
   + badge Demorado agregado si algún pedido venció el timeout; destello
   via evento browser `pedido-por-aceptar` con estado Alpine — sobrevive
   morphs; nuevosCount intacto)
3. Tests: broadcast emitido al crear borrador externo (Event::fake sobre
   el broadcast); smoke del componente. ✅ (128 tests verdes en las dos
   suites completas)

### Fase 4 (core): Historias destacadas lado core [COMPLETO]
1. Migración `historias` en `config.tiendas` + fillable/cast + helper
   `historiasOrdenadas()`. ✅ (2026-07-28; migrada también en *_test)
2. `ImagenTiendaService`: agregar/eliminar/reordenar historia.
   ✅ (agregarHistoria valida tope 3 + WebP 1080×1920; eliminar re-numera
   orden 1..N; reordenar por lista de ids)
3. `ConfiguracionTienda`: sección UI nueva + mudanza del toggle de promos.
   ✅ (uploads pendientes hasta "Guardar apariencia" como logo/portada;
   eliminar/mover son inmediatos y recargan el visor)
4. API `show()`: clave `historias`. Contrato actualizado. Tests. ✅

### Fase 5 (core): Estética lado core [COMPLETO]
1. Quitar checkbox overlay (+ `TEMA_DEFAULTS` overlay=false fijo);
   `tema.logo.radio` (default `full`) + selector "Forma del logo".
   ✅ (2026-07-28; propiedad $portadaOverlay eliminada del componente;
   tienda-preview.js del core actualizado: manda logo.radio y overlay
   false fijo al visor)
2. Contrato actualizado. Tests + smoke ConfiguracionTienda.
   ✅ (141 tests verdes en las dos suites; assets compilados)

### Fase 6 (tienda): Todo el lado bcn-tienda [PENDIENTE]
1. `TiendaActual`: `historias()`, `logoRadio()`, `--tienda-radio-logo`,
   fingerprint de vistas en sesión; espejo en `preview.js`.
2. Hero: anillo + glow (reduced-motion), radio del logo por var, portada
   cruda (quitar overlay), espacio inferior extra.
3. Visor de historias (Alpine): overlay, progreso, tap/auto-avance 5s,
   slide de promos temado; endpoint interno "marcar visto".
4. Quitar pill de promos de la home.
5. Contract tests + fixtures actualizados.

### Fase 7: Cierre [PENDIENTE]
1. Traducciones (es/en/pt) en ambos repos.
2. Docs (`@docs-sync` al crear cada PR).
3. Validación en vivo del usuario en ambos lados.

**Orden de PRs**: F1-F5 en un PR core (o F1-F3 fixes en un PR y F4-F5
estética en otro, a definir por tamaño) → merge → F6 en PR de bcn-tienda.
Deploy coordinado: primero core, después tienda.

---

## Notas y Decisiones

- 2026-07-28: TTL de "historia vista" = vida de la sesión de la tienda
  (`SESSION_LIFETIME=120` → 2h), mismo mecanismo que el carrito (decisión
  del usuario: "misma duración que un carrito armado").
- 2026-07-28: promos como **un solo slide** con todas las promos y sus
  condiciones (horarios, formas de pago, unidades, etc.) — elegido por el
  usuario frente a "una historia por promo".
- 2026-07-28: al quitar "fundir la portada" queda **portada cruda siempre**
  (elegido por el usuario frente a "fade suave fijo").
- 2026-07-28: el **anillo sigue la forma del logo** (`tema.logo.radio`), no
  se fuerza círculo (elegido por el usuario).
- 2026-07-28: aceptación con **modal precargado** ("El cliente eligió: X",
  un click para confirmar) — elegido por el usuario frente a aceptación
  directa sin modal.
- 2026-07-28: se reutiliza `tema.promos.mostrar_home` como toggle de
  "promos como historia" (sin clave nueva): evita romper el contrato y los
  dos repos se deployan coordinados. El pill de la home desaparece.
- 2026-07-28: `snapshotIdsVistos()` sigue excluyendo borradores — el
  contador "Ver N nuevos" del tablero es de pedidos confirmados; los
  por-aceptar tienen su propio contador en la banda (evita doble conteo).
- 2026-07-28: estado de banda plegable NO se persiste (Alpine local,
  default plegada) — simplicidad; si molesta se agrega localStorage después.
