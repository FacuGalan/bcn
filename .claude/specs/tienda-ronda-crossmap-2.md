# Tienda — Ronda Crossmap 2 (RF-T28..T36) - Especificación

## Estado: APROBADO — Fases 1 (PR #183), 2 (PR #184) y 3 COMPLETAS — Fase 4 (tienda) PENDIENTE

> Escrita 2026-07-29 sobre 4 informes de exploración (promos core, panel tienda,
> tienda pública, usa_delivery). APROBADA por Facu 2026-07-29: las 4 decisiones
> ⚠️ confirmadas tal como están (máximos solo comunes; texto libre a Información
> del comercio; usa_delivery maestro + delivery_habilitado; badges de categorías
> con el mismo catálogo que artículos).

---

## Contexto y Motivación

Ronda de mejoras crossmap (core + tienda) pedida por Facu tras validar la tienda
en producción. Mezcla: (a) promociones más expresivas y correctamente validadas
en TODOS los circuitos de venta; (b) mejoras de presentación de la tienda
(categorías plegables, título de destacados, badges de categorías); (c) fixes de
panel (historias que se pisan, radios que rompen dropdowns, texto libre
confuso); (d) el bug estructural de `usa_delivery` que deja tiendas publicadas
pero muertas (404).

---

## Principios de Diseño

1. **El contrato manda**: todo dato nuevo que consume la tienda nace en el core,
   se documenta en `docs/api-v1-delivery.md` como ADITIVO y recién ahí se
   consume en bcn-tienda. Estructuras existentes no cambian (v1 congelada).
2. **Retrocompatibilidad plural/singular**: el patrón canónico ya probado con
   `formas_pago_ids` (columna plural JSON + singular vivo + doble escritura +
   fallback `?? (singular ? [singular] : [])`). Nunca eliminar el singular.
3. **Semántica de restricciones**: FP múltiples = "TODAS las declaradas deben
   estar permitidas" (anti-abuso multi-pago, regla existente). Forma de venta y
   canal = valor ÚNICO en el contexto ⇒ `in_array` simple (OR dentro del tipo).
4. **Máximos NULL = sin tope** (retrocompat trivial): comparaciones con
   `!== null`, nunca `empty()` (0 es un valor válido).
5. **Tema tolerante**: toda clave nueva de `tema.*` tiene default = 
   comportamiento actual (snapshot viejo sin la clave ⇒ nada cambia).
6. **No romper la tienda**: estado Alpine local en el catálogo SIEMPRE con
   `wire:key` estable por id y sin payload dinámico en `x-data` (gotchas
   documentados del repo).

---

## Requisitos Funcionales

### RF-T28: Promos — forma de venta y canal de venta MÚLTIPLES
- Wizards de promos comunes y especiales: los selects de forma de venta y canal
  pasan a listas de checkboxes múltiples (patrón visual exacto del selector de
  formas de pago: lista scrolleable + badge count + "(todas)" si vacío).
- **Comunes**: se persisten como N filas `por_forma_venta`/`por_canal` en
  `promociones_condiciones` (igual que ya hace `por_forma_pago`). Sin migración.
- **Especiales**: columnas nuevas `formas_venta_ids`/`canales_venta_ids` (TEXT
  JSON) + backfill desde el singular + doble escritura (patrón
  `formas_pago_ids`, migración 2026_04_13 como plantilla).
- Evaluación: el valor del contexto (uno solo) debe estar en la lista (OR
  dentro del tipo). Lista vacía ⇒ aplica a todas/todos.
- **Fix estructural obligatorio**: `PrecioService::validarCondicionesPromocion`
  hoy evalúa las condiciones en AND fila por fila ⇒ una promo con 2+ FP (o, al
  implementar esto, 2+ FV/canales) NUNCA aplica por esa vía. Se reescribe:
  agrupar por `tipo_condicion`, OR dentro del grupo, AND entre grupos. Esto
  arregla de paso el bug preexistente de multi-FP en catálogo tienda/pantalla.
- Alcances de la evaluación (los 4 circuitos + auxiliares):
  `WithCalculoVenta` (motor de NuevaVenta, mostrador, delivery y
  `CotizadorCarritoTienda`), `PromocionCondicion::seCumple`,
  `PrecioService`, `PromocionEspecial::cumpleCondiciones` (evaluador paralelo,
  se actualiza para no quedar inconsistente), simuladores (`WizardPromocion`,
  `SimuladorVenta`, `WizardPromocionEspecial`: aplanados, cumpleCondiciones,
  razonNoAplicada, filtros SQL con `whereHas` — semántica OR ya correcta con
  N filas), `CatalogoTiendaService` (filtro por canal de especiales línea 283 →
  plural; condiciones legibles con plural), textos de
  `PromocionCondicion::obtenerDescripcion`.
- Carga en edición de comunes: `cargarPromocionParaEdicion` acumula (`[]=`) en
  vez de pisar (hoy con N filas quedaría solo la última).

### RF-T29: Promos comunes — monto máximo y cantidad máxima
- Wizard de comunes, card "Condiciones de la compra": inputs de monto máximo y
  cantidad máxima junto a los mínimos (grid 2×2). Se conecta el input huérfano
  `cantidadMaxima` que ya existe en el modal del simulador (hoy se descarta).
- Persistencia: en LA MISMA fila `por_total_compra`/`por_cantidad` que el
  mínimo (rango). Columnas `monto_maximo`/`cantidad_maxima` YA existen en el
  schema — habilitarlas en `$fillable`/`$casts` + migración defensiva
  idempotente para tenants provisionados antes del template actual (patrón
  try/catch por comercio). De paso: corregir cast de `cantidad_minima`
  (integer → decimal, la columna es decimal(12,3)).
- Evaluación de rango copiando `ListaPrecioCondicion::evaluarTotalCompra`
  (min Y max) en: `PromocionCondicion::seCumple`, `WithCalculoVenta::
  promocionCumpleCondiciones` (ojo `!empty` actual: cambiar a `!== null`),
  simuladores (+ mensajes "Monto máximo superado" en razonNoAplicada).
- `obtenerDescripcion`: rangos legibles (patrón
  `ListaPrecioCondicion::obtenerDescripcionRangoMonto`) — alimentan el listado
  del panel y los chips de la historia de promos de la tienda.
- ⚠️ Especiales NO llevan máximos en esta ronda (tampoco tienen mínimos; su
  mecánica NxM ya define cantidades).

### RF-T30: Módulo pedidos / modalidades / auto-encendido con la tienda
- Renombrar labels (sin migración, mismo campo `usa_delivery`):
  maestro = "Habilitar módulo de pedidos" / hint "Activa el panel de pedidos,
  la tienda online y el marketplace (delivery y/o take-away)".
- Nueva modalidad explícita: `delivery_habilitado` (bool, key en el JSON
  `config_delivery`, default `true` = comportamiento actual) en paridad con
  `takeaway_habilitado`. Tilde propio en la card General.
- Validación de modalidad: `PedidoDeliveryService::validarTipoContraSucursal`
  bloquea TIPO_DELIVERY si `delivery_habilitado=false` (espejo del bloqueo
  take-away existente); ídem `PedidoTiendaService`; `NuevoPedidoDelivery`
  oculta/corrige el tipo delivery como hoy hace con take-away.
- API (aditivo): `delivery_habilitado` en `GET /v1/tiendas/{slug}`, marketplace
  y `GET /v1/delivery/config`. La tienda ofrece delivery/take-away según ambos
  flags (una tienda solo-take-away es legítima).
- **Auto-encendido**: `toggleTiendaOnline()` al PUBLICAR enciende
  `sucursales.usa_delivery` si estaba apagado (+ set del prop `usaDelivery`
  para que el autosave no lo pise + invalidación del caché
  `marketplace_tienda_{id}` + toast informativo). El middleware no cambia.
- Guarda inversa: si el maestro se APAGA con la tienda publicada, avisar en el
  panel (hint rojo bajo el tilde: "La tienda online quedará fuera de línea").

### RF-T31: Categorías desplegables en la tienda
- Clave nueva `tema.catalogo.categorias_plegables` (bool, default `false`).
  Panel: toggle en "Presentación del catálogo" (aplica al guardar, como el
  resto de la sección).
- Tienda (`catalogo.blade.php`):
  - Título de categoría SIEMPRE (plegable o no): centrado y más llamativo —
    `text-center font-display text-xl font-bold` + subrayado corto en color
    primario (decorador `after:` de 2.5rem centrado), reemplaza el h2 plano.
  - Wrapper por grupo con `wire:key="cat-{id}"` (hoy no existe — previene que
    el estado Alpine se corra al filtrar/buscar o al aparecer "Otros").
  - Modo plegable: título = botón con chevron; acordeón CSS grid-rows 1fr⇄0fr +
    fade (mismo patrón de grupos de opcionales). Estado `abierto` default true,
    LOCAL de Alpine sin payload dinámico en x-data ⇒ sobrevive los morphs de
    abrir/cerrar detalle de artículo (que re-renderizan todo Home). Al plegarse
    NO se toca el scroll (no scrollIntoView automático).
  - Verificación explícita anti-regresión: abrir artículo, cerrar, filtrar por
    chip, buscar y volver — sin plegados espontáneos ni saltos de vista (el
    scroll-lock del sheet restaura la posición; la altura del catálogo no debe
    cambiar bajo el sheet abierto).

### RF-T32: Radios del tema — excluir dropdowns y aclaraciones (tienda)
- Los 4 paneles desplegables clonados del carrito (FP 1, FP 2, franja horaria,
  día de encargo) y sus botones trigger + el textarea de aclaración del
  checkout + los 3 campos de aclaración del detalle de artículo: dejan de usar
  `rounded-ui` directo y pasan a radio CLAMPEADO
  `rounded-[min(var(--tienda-radio-ui),0.75rem)]` — con radio "full" (9999px)
  quedan "medios" (0.75rem) y nunca cortan contenido; con radios chicos siguen
  idénticos al tema.

### RF-T33: Texto libre → "Información del comercio"
- ⚠️ La descripción (`tema.textos.descripcion`) DEJA de renderizarse como
  sección propia de la home y pasa a mostrarse dentro del panel "Información
  del comercio" (paneles-tienda), con `whitespace-pre-line`.
- Panel: label nuevo "Texto descriptivo (se muestra en «Información del
  comercio»)", textarea `rows="8"` y `maxlength=5000` (validación server
  espejo); hint actualizado.
- Core: solo cambia validación (1000→5000) y labels; la clave del tema no
  cambia (aditivo de contenido). Tienda: mover el render de sección → panel.

### RF-T34: Historias — carga múltiple inmediata + drag & drop
- Portar el patrón de la galería de artículos: hook `updatedHistoriaUploads()`
  que valida, llama `ImagenTiendaService::agregarHistoria()` por archivo,
  VACÍA el array y refresca — la subida persiste al instante (adiós al pisado:
  Livewire reemplaza el array `multiple` en cada selección y hoy nadie lo
  consume hasta Guardar).
- Reordenamiento por drag & drop (SortableJS, `initFotosSortable` como
  plantilla) sobre las persistidas, llamando al `reordenarHistorias()`
  existente; se retiran las flechas ←/→.
- Se elimina el flujo "pendiente" (miniaturas temporales, descartar, tope
  combinado en `guardarTienda`); el tope MAX_HISTORIAS=3 se valida en el hook.
- El sector queda donde está (aclarado por Facu: ya no requiere Guardar ⇒ no
  hace falta moverlo).

### RF-T35: Destacados — título editable + adorno en el título del banner
- Clave nueva `tema.destacados.titulo` (string, default `''` = "Destacados"),
  editable en "Presentación del catálogo" (aplica al guardar).
- El selector de ADORNO se habilita también en modo banner: el adorno decora el
  TÍTULO del carrusel (badge = estrellita `bg-acento` junto al título; glow =
  text-shadow primario en el título; ambos = las dos) — los artículos del
  banner NO llevan adorno. En modo tarjeta_grande el adorno sigue en la card
  (comportamiento actual, sin cambios).
- Tienda: `destacados.blade.php` consume `destacadosTitulo()` (patrón
  `slogan()`) + adorno; `aria-label` usa el título efectivo.

### RF-T36: Badges de categorías
- Migración tenant: `categorias.badges_tienda` (JSON NULL, patrón RF-T14).
- Mismo catálogo cerrado de tipos que artículos (constantes de `Articulo`
  extraídas/reusadas) + `custom` (30 chars), tope 4. Saneador
  `Categoria::badgesTienda()` espejo de `Articulo::badgesTienda()`.
- Panel: edición en la grilla de categorías de `ConfiguracionTiendaArticulos`
  (donde ya viven el drag de orden), chips toggle + custom — guardado
  inmediato + invalidación de caché de catálogo.
- API (aditivo): `categorias[].badges` en `GET /catalogo`.
- Tienda: chips de badges junto al título del grupo en el catálogo
  (`badges-articulo` reutilizado, modo completo) — tolerante a tipos
  desconocidos.

### RF-T37 (fix menor, tienda): animación de entrada del carrito flotante
- La entrada con rebote quedó trabada (2 frames). Fix: pintar display+badge,
  esperar doble rAF y recién ahí animar (o CSS animation). Si en validación en
  vivo sigue sin ser fluida ⇒ REVERTIR la animación de entrada (aparición
  simple, como antes de tienda#48).

---

## Modelo de Datos

### Tablas modificadas (tenant, iterar comercios, try/catch por comercio)

#### `{NNNNNN}_promociones_especiales`
- Agregar: `formas_venta_ids` (TEXT NULL) AFTER `forma_venta_id`
- Agregar: `canales_venta_ids` (TEXT NULL) AFTER `canal_venta_id`
- Backfill: `UPDATE ... SET formas_venta_ids = CONCAT('[', forma_venta_id, ']')
  WHERE forma_venta_id IS NOT NULL AND formas_venta_ids IS NULL` (ídem canal).
- Los singulares siguen vivos (doble escritura: singular = primer elemento).

#### `{NNNNNN}_promociones_condiciones`
- Migración DEFENSIVA (idempotente): asegurar `cantidad_maxima` decimal(12,3)
  NULL y `monto_maximo` decimal(12,2) NULL (ya están en el template
  tenant_tables.sql; tenants viejos podrían no tenerlas).

#### `{NNNNNN}_categorias`
- Agregar: `badges_tienda` (JSON NULL) AFTER `imagen_path`.

#### `config.tiendas` / `sucursales`
- Sin cambios de schema: `tema.catalogo.categorias_plegables`,
  `tema.destacados.titulo` viven en el JSON `tema`; `delivery_habilitado` vive
  en el JSON `sucursales.config_delivery` (default true en
  `CONFIG_DELIVERY_DEFAULTS`).

**Post-migraciones: regenerar `database/sql/tenant_tables.sql`.**

---

## Pantallas UI

### Panel — Wizard promos comunes (`WizardPromocion` + blade)
- Paso 4 Aplicabilidad: FV y canal como checkbox-list múltiples (patrón FP).
- Paso 4 Condiciones: grid 2×2 mínimos/máximos. Modal del simulador (paso 5):
  mismos controles + persistencia real de `cantidadMaxima`/`montoMaximo`.

### Panel — Wizard promos especiales (`WizardPromocionEspecial` + blade)
- Card Aplicabilidad: FV y canal múltiples (checkbox-list).

### Panel — `ConfiguracionDelivery`
- Labels nuevos del maestro; tilde nuevo "Delivery habilitado" (modalidad) en
  paridad con take-away; hint rojo si maestro off + tienda publicada;
  auto-encendido en `toggleTiendaOnline`.

### Panel — `ConfiguracionTienda`
- Historias: hook de subida inmediata + SortableJS (sin flechas, sin flujo
  pendiente). Presentación del catálogo: toggle categorías plegables + input
  título de destacados + adorno habilitado con banner. Texto libre: label/hint
  nuevos, rows 8, maxlength 5000.

### Panel — `ConfiguracionTiendaArticulos`
- Grilla de categorías: chips de badges por categoría + custom (inmediato).

### Tienda (bcn-tienda)
- `catalogo.blade.php`: títulos centrados/llamativos, wrapper `wire:key`,
  modo plegable (Alpine + acordeón CSS), badges de categoría.
- `destacados.blade.php`: título configurable + adorno en título.
- `carrito.blade.php`/`checkout.blade.php`/`detalle-articulo.blade.php`:
  radios clampeados en dropdowns/aclaraciones.
- `paneles-tienda.blade.php`: descripción en Información del comercio;
  `secciones/texto.blade.php` se retira del render de la home.
- Selector de tipo de servicio: respeta `delivery_habilitado`.
- `carrito-flotante.js`: fix animación de entrada.

---

## Servicios

- `PrecioService::validarCondicionesPromocion` — reescritura: agrupar por tipo
  (OR intra-tipo, AND inter-tipo) + rangos min/max.
- `WithCalculoVenta` — aplanado plural FV/canal, rangos, especiales plural.
- `PedidoDeliveryService` / `PedidoTiendaService` — validación modalidad
  delivery.
- `MarketplaceTiendasService` / `TiendaController` / `ConfigController` —
  exponer `delivery_habilitado`; invalidación de caché marketplace al publicar.
- `CatalogoTiendaService` — filtro canal plural en especiales; `categorias[].
  badges`; condiciones legibles con rangos y plurales.
- `ImagenTiendaService` — sin cambios (agregar/reordenar ya existen; los
  consume el hook nuevo).
- `Categoria::badgesTienda()` nuevo (saneador espejo).

---

## Migraciones Necesarias

1. `add_multi_fv_canal_a_promociones_especiales` — plurales + backfill.
2. `ensure_maximos_en_promociones_condiciones` — defensiva idempotente.
3. `add_badges_tienda_a_categorias` — JSON NULL.
4. Regenerar `database/sql/tenant_tables.sql`.

---

## Traducciones (es/en/pt, orden alfabético)

Nuevas (lista no exhaustiva, cerrar en implementación): "Monto máximo",
"Cantidad máxima", "Monto máximo superado", "Cantidad máxima superada",
"Habilitar módulo de pedidos", "Delivery habilitado", "Permite pedidos con
envío a domicilio.", "La tienda online quedará fuera de línea",
"Título de la sección de destacados", "Categorías desplegables",
"Texto descriptivo (se muestra en «Información del comercio»)", labels de
rangos legibles ("Total entre :min y :max", "Hasta :max unidades", etc.).
La tienda (bcn-tienda) usa claves en español directo (sin archivos).

---

## Criterios de Aceptación

- [ ] Promo común con 2 FV: aplica en ambas, no en una tercera — verificado en
      NuevaVenta, mostrador, delivery y cotización de tienda.
- [ ] Promo común con 2 FP vuelve a aplicar vía PrecioService (bug AND).
- [ ] Promo especial con 2 canales: la historia de promos la muestra solo en
      el canal correcto; editar y re-guardar no pierde selecciones.
- [ ] Rango monto/cantidad: bajo el mínimo NO aplica, dentro SÍ, sobre el
      máximo NO — en los 4 circuitos y el simulador (con razón legible).
- [ ] Promos existentes (singular, sin máximos): comportamiento idéntico.
- [ ] Publicar tienda con usa_delivery apagado: se enciende solo, la tienda
      responde (no 404) y el marketplace la lista (caché invalidado).
- [ ] Tienda solo-take-away: delivery no se ofrece en la tienda ni en el panel
      de pedidos; take-away opera.
- [ ] Categorías plegables: plegar/desplegar fluido; abrir/cerrar artículo,
      filtrar por chip y buscar NO alteran plegado ni scroll.
- [ ] Radio "full": dropdowns del carrito y aclaraciones se ven "medios", sin
      recortes; radios sm/md sin cambios.
- [ ] Historias: seleccionar 2 fotos, después 1 más sin tocar Guardar ⇒ 3
      persistidas; drag & drop reordena; tope 3 respetado.
- [ ] Descripción larga (5000) visible completa en "Información del comercio";
      la home ya no muestra la sección de texto.
- [ ] Título de destacados editable con default "Destacados"; adorno visible en
      el título del banner y en la card grande según modo.
- [ ] Badges de categoría visibles en panel y tienda; tipo desconocido se
      ignora.
- [ ] Animación de entrada del carrito fluida o revertida.
- [ ] Suites completas de ambos repos + Pint en verde; contrato y docs
      actualizados; tenant_tables.sql regenerado.

---

## Plan de Implementación

### Fase 1: Core — Promos (RF-T28 + RF-T29) [COMPLETO]
1. Migraciones 1 y 2 + fillable/casts (+ fix cast cantidad_minima) + regenerar SQL.
2. Reescritura `validarCondicionesPromocion` (OR intra-tipo) + `seCumple` con rangos.
3. `WithCalculoVenta`: aplanados plurales + rangos + especiales plural.
4. Wizards (props array, blades checkbox-list, guardar/cargar, doble escritura)
   + simuladores + descripciones legibles + `CatalogoTiendaService` línea 283.
5. Tests: unit de evaluación (rangos, plural, retrocompat singular) +
   integración por circuito + ApiV1Delivery.

### Fase 2: Core — Módulo pedidos y modalidades (RF-T30) [COMPLETO]
1. `CONFIG_DELIVERY_DEFAULTS` + tilde + labels + hint rojo.
2. Validaciones de modalidad en services + `NuevoPedidoDelivery`.
3. Auto-encendido en `toggleTiendaOnline` + invalidación caché marketplace.
4. API/contrato: `delivery_habilitado` aditivo. Tests.

### Fase 3: Core — Panel tienda (RF-T33 core, T34, T35 core, T36 core, T31 core) [COMPLETO]
1. Historias: hook inmediato + SortableJS + retirar flujo pendiente. Tests.
2. Tema: `catalogo.categorias_plegables` + `destacados.titulo` + adorno con
   banner + validaciones + UI Presentación del catálogo.
3. Texto libre: labels + 5000 + rows.
4. Badges categorías: migración 3 + saneador + UI grilla + API. Contrato .md.

### Fase 4: Tienda — Consumo (RF-T31..T33, T35, T36 lado tienda + RF-T37) [PENDIENTE]
1. Catálogo: títulos llamativos + wire:key + plegables + badges de categoría.
2. Destacados: título + adorno. Radios clampeados. Texto en Información del
   comercio. Selector servicio con `delivery_habilitado`.
3. Fix animación carrito. Contract tests/fixtures actualizados.

### Fase 5: Verificación (/sdd-verify) + docs (@docs-sync) + deploy [PENDIENTE]

---

## Notas y Decisiones

- 2026-07-29: ⚠️ **Máximos solo en promos comunes** (especiales sin mínimos hoy;
  su mecánica NxM ya acota cantidades). Confirmar con Facu.
- 2026-07-29: ⚠️ **Texto libre**: se MUEVE de sección de la home al panel
  "Información del comercio" (interpretación del pedido). Confirmar.
- 2026-07-29: ⚠️ **usa_delivery**: se mantiene el campo como interruptor maestro
  renombrado + modalidad `delivery_habilitado` nueva en el JSON + auto-encendido
  al publicar la tienda. Confirmar diseño.
- 2026-07-29: ⚠️ **Badges de categorías**: mismo catálogo de tipos que artículos
  + custom. Confirmar (¿o solo custom?).
- 2026-07-29: Comunes multi-FV/canal por N FILAS (no JSON): cero migración y los
  `whereHas` del simulador conservan semántica OR correcta; exige el fix del
  AND en PrecioService (bug preexistente que igual había que arreglar).
- 2026-07-29: Historias pasan a persistencia INMEDIATA (rompe "se aplica al
  guardar" de la sección apariencia, pero eliminar/mover ya eran inmediatos y
  es el pedido explícito de Facu).
