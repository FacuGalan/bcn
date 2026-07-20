# Tienda modo ECOMMERCE — Especificación

## Estado: EN REVISIÓN (2026-07-20)

> Spec propio (decisión 2026-07-20): MISMO proyecto bcn-tienda con
> `tienda.modo = gastronomica | ecommerce` — NO un repo aparte. Comparte
> consumidores, carrito server-side, checkout, cotización del core, tema,
> PWA, analytics y el contrato API v1; cambia la EXPERIENCIA DE CATÁLOGO
> (escala, búsqueda, filtros, navegación) y se SIMPLIFICA la entrega (sin
> atención en el momento). Spec maestro relacionado: `tienda-online.md`
> (RF-T1..T16). Regla de oro intacta: la tienda consume SOLO la API v1 y
> nunca calcula precios.

## Contexto y Motivación

La tienda actual es gastronómica: catálogo chico que viaja entero,
atención "de ahora" (abierto/cerrado, franjas, demoras), modal de detalle
rápido. Un comercio NO gastronómico (indumentaria, ferretería, dietética,
etc.) necesita: muchos más artículos (catálogo paginado con búsqueda y
filtros server-side), navegación por rubros, página de producto propia y
un checkout más simple (una dirección y listo, sin promesa de entrega en
el momento).

## Decisiones del usuario (2026-07-20 — no re-litigar)

1. **Mismo bcn-tienda, modo por tienda** (`gastronomica|ecommerce`),
   spec aparte. Orden de trabajo general: después de RF-T15/T16.
2. **Envío: MIX** — la tienda ecommerce elige su esquema de envío entre
   TODAS las herramientas: costo fijo (con "gratis desde $X" opcional),
   "a coordinar", retiro en el local, y/o las zonas/radio georreferenciadas
   existentes. El comercio combina lo que le sirva.
3. **Orden por precio: MATERIALIZAR precios de tienda** (tabla calculada
   con invalidación), NO aproximación por precio_base. El precio mostrado
   y el orden salen del mismo dato.
4. **Detalle de artículo: PÁGINA COMPLETA** en modo ecommerce (URL
   compartible; el modo gastronómico conserva su modal).
5. **Categorías: JERARQUÍA AHORA** (rubro → subrubro) — entra en este
   spec, no se difiere.

## Requisitos Funcionales

### RF-E1: Modo de tienda

- Columna `tiendas.modo` (BD **config**, migración idempotente patrón
  `2026_07_17_120000`): `varchar` `gastronomica|ecommerce`, default
  `gastronomica` (todo lo existente NO cambia).
- `Tienda::MODOS`, fillable; expuesto en `GET /tiendas/{slug}` (aditivo).
- Panel (`ConfiguracionTienda`, auto-save RF-T15): selector "Tipo de
  tienda" con confirmación (`wire:confirm`) porque reestructura la tienda
  pública. Al cambiar a ecommerce, se muestran las secciones de config
  propias del modo (envío ecommerce) y se ocultan las que no aplican
  (promesa/franjas/encargos siguen siendo de delivery y no molestan).
- bcn-tienda: `TiendaActual::modo()` (tolerante: sin clave ⇒
  gastronomica); presets de `secciones_home` POR MODO en
  `config/tienda.php` (Principio 10: datos, no ifs dispersos).

### RF-E2: Jerarquía de categorías (rubro → subrubro)

- Migración TENANT: `categorias.parent_id` (bigint null, FK a sí misma
  ON DELETE SET NULL, índice). Regenerar tenant_tables.sql. **Máximo 2
  niveles en v1** (raíz + hijo; validación server-side anti-ciclos y
  anti-nietos) — suficiente para navegar y no complica el panel.
- Panel `GestionarCategorias`: selector "Categoría padre" (solo raíces
  elegibles como padre; una categoría con hijos no puede volverse hija).
- Core: `Categoria::padre()/hijos()`; el criterio de visibilidad de
  artículos NO cambia (se asignan a cualquier nivel, como hoy).
- API: endpoint nuevo `GET /v1/tiendas/{slug}/categorias` → árbol
  `[{id, nombre, imagen_url, orden, hijos: [...]}]` (solo categorías con
  artículos visibles, mismo criterio del catálogo). El `catalogo`
  monolítico actual suma `parent_id` (aditivo) y no cambia más.
- Filtro por categoría RAÍZ incluye sus hijas (rubro completo).

### RF-E3: Precios materializados de tienda

Motivo: el precio final lo calcula PrecioService en runtime (4 niveles +
promos + IVA); para paginar/ordenar/filtrar por precio en SQL hay que
materializarlo. SOLO lo consume el catálogo ecommerce (el gastronómico
sigue calculando en vivo — catálogo chico, cero cambio de comportamiento).

- Tabla TENANT nueva `{prefix}articulo_precios_tienda`: `id`,
  `articulo_id` (FK cascade), `sucursal_id` (FK cascade), `tipo`
  (`delivery|take_away`), `precio` dec(12,2), `precio_lista` dec(12,2)
  null, `calculado_at` timestamp. UNIQUE (articulo, sucursal, tipo).
- `PreciosTiendaService::materializar(Sucursal, ?array $articuloIds)`:
  recorre los artículos VISIBLES de la tienda (criterio
  CatalogoTiendaService) con el MISMO `calcularPrecioFinal` + contexto
  canal TIENDA, y upserta. Borra los que dejaron de ser visibles.
- **Refresco**: (a) comando `tienda:materializar-precios` en el
  SCHEDULER cada 10 min (solo sucursales con tienda habilitada en modo
  ecommerce); (b) refresco DIRIGIDO tras eventos de precio conocidos:
  `precios:procesar-programados` (ya corre por minuto), guardado de
  precios/listas/promos masivos y RF-T14 (invalidarCache ya centraliza el
  hook — se extiende a materializar en cola). Promos por día/hora hacen
  que el precio dependa del momento: el scheduler de 10 min es el techo
  de desfase aceptado (documentado; el precio COBRADO siempre lo cotiza
  el core en vivo, así que nunca se cobra un precio viejo — a lo sumo la
  grilla muestra uno con hasta 10 min de atraso).
- El shape del artículo en la API NO cambia: `precio`/`precio_lista`
  salen de la tabla en el endpoint paginado.

### RF-E4: Catálogo paginado con búsqueda y filtros (API)

- Endpoint nuevo `GET /v1/tiendas/{slug}/articulos` (aditivo, contrato):
  - `q` (busca en nombre + descripcion + codigo, SQL like),
  - `categoria` (id; si es raíz incluye hijas),
  - `orden` = `relevancia (default) | nombre | precio_asc | precio_desc |
    nuevos` (precio: JOIN a articulo_precios_tienda),
  - `page` / `per_page` (default 24, máx 48),
  - `tipo` (delivery|take_away, como el catálogo).
- Respuesta: `{articulos: [MISMO shape del catálogo actual, incl.
  imagenes/badges/alergenos/permite_encargo], meta: {pagina,
  por_pagina, total, ultima_pagina}}`. Los grupos de opcionales se
  incluyen igual (mismo builder).
- Cache server-side corto (60s) por querystring normalizado + ETag;
  `CatalogoTiendaService::invalidarCache` extendido para barrer también
  estas keys (patrón de key con índice/tags simple).
- El catálogo monolítico actual queda para modo gastronómico (y como
  fallback ecommerce sin JS raro): NADA se rompe.

### RF-E5: Experiencia ecommerce en bcn-tienda

- **Home ecommerce** (preset de secciones): hero compacto + buscador
  protagonista + grilla de RUBROS (jerarquía, con imagen) + destacados +
  "novedades" (orden `nuevos` del endpoint). Sin "abierto/cerrado" como
  protagonista (se muestra info de envío en su lugar).
- **Listado** `/tienda/{slug}/c/{categoria}` y `/tienda/{slug}/buscar?q=`:
  componente Livewire nuevo con paginación (el paginador estándar),
  chips de subrubros, selector de orden, búsqueda server-side
  (`CoreApi::articulos($slug, $filtros)`).
- **Detalle** página completa `/tienda/{slug}/p/{articuloId}` (en modo
  ecommerce la ruta `articulo` renderiza página, no modal): galería
  grande (RF-T14), badges, alérgenos, descripción, opcionales, agregar al
  carrito. El modo gastronómico conserva el modal actual.
- Carrito/checkout/cuenta/puntos/cupones: LOS MISMOS (server-side, ya
  compartidos).
- **Checkout ecommerce**: sin promesa/franjas/ASAP/encargos. Paso de
  entrega según RF-E6: retiro o envío (dirección texto + referencia,
  SIN exigir coordenadas salvo esquema por zonas). Aceptación
  manual/automática como siempre; el pedido entra al MISMO panel de
  atención con badge origen tienda (sin hora pactada — el operador
  coordina si hace falta).

### RF-E6: Envío ecommerce (MIX)

- Config por tienda (sub-objeto ADITIVO `config_delivery.envio_ecommerce`,
  merge por clave como `encargos`): `{esquema: fijo | a_coordinar |
  zonas, costo_fijo: 0, gratis_desde: null, permite_retiro: true}`.
  Panel: sección "Envíos" visible solo en modo ecommerce (auto-save).
- Comportamiento del alta/cotización (core):
  - `fijo`: costo_envio = costo_fijo; si `gratis_desde` y el total lo
    supera ⇒ 0.
  - `a_coordinar`: costo_envio = 0 + flag `envio_a_coordinar` en el
    pedido (badge en el panel; el operador lo fija al aceptar con el
    editor de costo de envío existente). El total del checkout dice
    "envío a coordinar".
  - `zonas`: reusa `DeliveryEnvioService::cotizar` (radio/zonas,
    coordenadas requeridas — el checkout pide ubicación como hoy).
  - `permite_retiro`: expone take_away como "Retiro en el local".
- `GET /tiendas/{slug}` expone `envio_ecommerce` (aditivo) para que el
  checkout arme el paso de entrega sin inventar nada.
- Pedido: `pedidos_delivery.envio_a_coordinar` — evaluar en la
  implementación si hace falta columna o alcanza con costo_envio_manual
  ya existente + observación; PREFERIR no migrar si alcanza (decidir en
  F1 con el código a la vista).

## Modelo de Datos (resumen)

| Cambio | BD | Tipo |
|---|---|---|
| `tiendas.modo` | config | columna nueva |
| `categorias.parent_id` | tenant | columna + FK self |
| `articulo_precios_tienda` | tenant | tabla nueva |
| `config_delivery.envio_ecommerce` | tenant (JSON) | keys aditivas |

## Plan de Implementación (cada fase = PR mergeable)

- **F1 — Modo + envío ecommerce (core)**: migración `tiendas.modo`,
  selector en panel, sub-objeto envio_ecommerce + su UI, exposición en
  show(), lógica de costo en el alta (fijo/a_coordinar/zonas/retiro),
  contrato, tests API. `[PENDIENTE]`
- **F2 — Jerarquía de categorías (core)**: migración tenant parent_id +
  tenant_tables.sql, panel GestionarCategorias, endpoint /categorias,
  parent_id aditivo en catálogo, tests. `[PENDIENTE]`
- **F3 — Precios materializados (core)**: tabla tenant +
  PreciosTiendaService + comando/scheduler + refrescos dirigidos + tests
  (incluye "dejó de ser visible ⇒ se borra"). `[PENDIENTE]`
- **F4 — Endpoint /articulos paginado (core)**: búsqueda/filtros/orden/
  paginación + cache por filtros + contrato + tests. `[PENDIENTE]`
- **F5 — Tienda ecommerce (bcn-tienda)**: modo()/presets de secciones,
  home ecommerce, listado con paginación y buscador, detalle página
  completa, fixtures + contract tests. `[PENDIENTE]`
- **F6 — Checkout ecommerce (bcn-tienda)**: paso de entrega según
  esquema, sin promesa/encargos, "envío a coordinar" visible, tests.
  `[PENDIENTE]`
- **F7 — Cierre**: traducciones ×3 ambos repos, @docs-sync, validación
  en vivo con una tienda demo en modo ecommerce. `[PENDIENTE]`

## Criterios de Aceptación

- [ ] `modo` default gastronomica ⇒ TODA tienda existente idéntica
  (aditivo puro, contract tests viejos verdes sin tocar).
- [ ] Cambiar a ecommerce reestructura home/listado/detalle/checkout sin
  tocar código (datos del snapshot).
- [ ] Jerarquía: máx 2 niveles validado; filtro por rubro incluye
  subrubros; catálogo gastronómico no cambia (parent_id aditivo).
- [ ] Materializados: grilla y orden por precio consistentes entre sí;
  desfase máx 10 min documentado; el COBRO siempre cotiza en vivo (el
  total del checkout nunca usa la tabla).
- [ ] /articulos: pagina, busca (nombre/descripcion/codigo), filtra por
  categoría (con hijas) y ordena por precio ASC/DESC correctamente
  contra los materializados; per_page tope 48.
- [ ] Envío: fijo (con gratis_desde), a coordinar (flag visible en panel
  y checkout) y zonas conviven; retiro configurable.
- [ ] Detalle ecommerce = página con URL propia; gastronómico conserva
  modal (tests de ambos).
- [ ] Suites completas + contract tests verdes en ambos repos.

## Notas

- Los pedidos ecommerce usan el MISMO circuito pedidos_delivery, panel de
  atención, conversión a venta y fiscal — cero bifurcación de backend.
- Encargos (RF-T16) y puntos/cupones quedan DISPONIBLES también en modo
  ecommerce (son config independiente).
- Futuro (fuera de alcance): artículos relacionados, variantes
  (talle/color), stock reservado por carrito, dominio propio por tienda,
  jerarquía de más de 2 niveles.
