# Ronda "Canje de puntos avanzado" — RF-T58..T61

> Spec de la ronda que profundiza el canje de puntos por artículo: la
> habilitación por artículo pasa a mandar también en el canje-pago (POS y
> tienda), aparece el modo de opcionales, y se rediseñan las UI del panel y
> de la tienda. Diseño cerrado con Facu el 2026-08-03 (preguntas/respuestas
> en la sesión). Depende de la ronda "ajustes cuenta y puntos" (RF-T54
> ajustado: costo configurado o derivado del precio) — mergear esa primero.

---

## Decisiones de diseño (2026-08-03)

1. **Flag único + switch de restricción**: `articulos_sucursales.canje_tienda`
   pasa a leerse como "canjeable por puntos" a secas (por sucursal). Un
   switch nuevo en el programa de puntos — "restringir canje a artículos
   habilitados" — apagado por default (= comportamiento actual del POS:
   todo canjeable). Con el switch prendido, la restricción rige en POS y
   tienda, y hay **acción masiva "habilitar todos" / "quitar todos"** para
   no configurar artículo por artículo.
2. **Modo de opcionales ortogonal**: enum de 3 valores válido con costo
   fijo Y derivado (6 combinaciones), con UI que explique cada combinación.
3. **Alcance**: `puntos_canje` y el modo de opcionales son GLOBALES del
   artículo (los comparten POS y todas las sucursales); solo el ON/OFF de
   canje es por sucursal (pivot actual).
4. **UI "pts."** en panel y tienda (la estrella violeta se confunde con la
   ámbar de destacado). En la tienda: el modal del artículo NO menciona
   puntos; todo el canje vive en el carrito.

---

## Requisitos Funcionales

### RF de CORE

#### RF-T58: La habilitación por artículo manda también en el canje-pago
- **Switch de programa**: `configuracion_puntos.restringir_canje_articulos`
  (bool, default `false`). Off = comportamiento actual (POS canjea
  cualquier artículo; canje-pago sobre el total). On = solo los artículos
  con `canje_tienda` prendido en la sucursal participan del canje, en POS
  y tienda.
- **Canje-pago (pagar la venta con puntos)** con el switch prendido, en
  POS (`WithPuntos`: canje como descuento RF-24) y tienda (`usar_puntos`,
  `PuntosTiendaService::calcularCanjeMaximo`):
  - Máximo canjeable = **suma de los renglones habilitados** (con sus
    promos/descuentos prorrateados por el motor), neteando los renglones ya
    canjeados como artículo.
  - Los renglones NO habilitados y el costo de envío quedan afuera (el
    envío ya está excluido hoy en la tienda: cotización y alta pasan el
    total sin `costo_envio` — el POS delivery debe quedar igual).
- **Canje por artículo en el POS** con el switch prendido: solo artículos
  con `canje_tienda` de la sucursal (`canjearArticuloConPuntos` valida; hoy
  no valida nada). Con el switch apagado, POS sigue como siempre.
- **Canje por artículo en la tienda**: manda el toggle por sucursal como
  hoy, independiente del switch (sin toggle no se publica `puntos_canje`).
- **Costo unificado POS/tienda**: el costo del canje por artículo pasa a
  un helper compartido (`PuntosService` o service propio): fijo
  (`articulos.puntos_canje`) si está cargado, derivado del precio si no.
  El POS hoy deriva SIEMPRE del precio: con esta ronda respeta el fijo
  igual que la tienda (sin puntos cargados no cambia nada).
- **Acción masiva**: en la UI nueva (RF-T60), botones "Habilitar todos" /
  "Quitar todos" sobre el pivot de la sucursal activa.

#### RF-T59: Modo de opcionales en el canje por artículo
- Columna nueva `articulos.canje_opcionales` enum
  `incluidos | en_plata | en_puntos`, default `incluidos` (reproduce el
  comportamiento efectivo del POS, que deriva del precio CON opcionales).
- **Matriz de costo** (costo del artículo × modo):

  | Costo | Modo | Puntos del renglón | Plata del renglón |
  |---|---|---|---|
  | Derivado | incluidos | `ceil((precio + opcionales) / valor_punto)` | $0 |
  | Derivado | en_plata | `ceil(precio / valor_punto)` | opcionales en $ |
  | Derivado | en_puntos | `ceil(precio / valor_punto) + ceil(opcionales / valor_punto)` | $0 |
  | Fijo N | incluidos | N (elija lo que elija) | $0 |
  | Fijo N | en_plata | N | opcionales en $ |
  | Fijo N | en_puntos | N + `ceil(opcionales / valor_punto)` | $0 |

- **Deroga la prohibición actual** de canjear renglones con opcionales
  pagos (`CotizadorCarritoTienda::construirItem` hoy lanza Exception; el
  POS no la tiene). El modo decide qué pasa con el opcional.
- **Motor**: en `en_plata` el renglón canjeado ya NO vale $0 — resta solo
  la parte artículo y deja los opcionales como monto a pagar. Tocar con
  cuidado `WithCalculoVenta` / `articulos_canjeados_monto` (hoy resta el
  renglón entero) y el desglose de IVA.
- **Ledger**: `puntos_usados` del detalle = costo efectivo según la matriz
  (lo que consume `MovimientoPunto` al convertir), POS y tienda.

#### RF-T60: UI de panel — pts., desplegable y sección en el programa
- **Configuración de tienda** (`ConfiguracionTiendaArticulos`):
  - El header del artículo conserva SOLO el toggle de canje, con chip
    **"pts."** en lugar de la estrella violeta (confusión con destacado).
  - Los inputs (puntos fijos "Auto" + selector de modo de opcionales) se
    mueven **al desplegable del artículo** (editor expandido), no al header.
- **Programa de fidelización** (`ProgramaPuntos`): sección nueva "Canje de
  artículos":
  - Switch "restringir canje a artículos habilitados" (RF-T58) con copy
    claro de qué implica.
  - Buscador + tabla de artículos: habilitado (sucursal activa), puntos
    fijos (vacío = Auto), modo de opcionales. Botones "Habilitar todos" /
    "Quitar todos".
  - **UI explicativa de las combinaciones**: cada modo con una línea de
    ejemplo en vivo (ej. "Hamburguesa 100 pts + opcional $50 → 100 pts y
    $50" según el modo elegido) — pedido explícito de Facu.
- Ambas pantallas escriben los MISMOS campos (global + pivot).

### RF de TIENDA (bcn-tienda)

#### RF-T61: UX de canje en la tienda — todo en el carrito (ajustado 2026-08-03 2ª vuelta)
- **Modal/detalle del artículo**: NO menciona puntos (se quita el chip
  "Canjealo por N puntos" de `detalle-articulo.blade.php`).
- **Indicador = ESTRELLA** (Facu 2026-08-03: "queda mejor" que el chip
  pts.): card del catálogo "⭐ N puntos", botón por ítem = estrella con el
  costo del renglón al lado (el de `items[].puntos_canje` de la
  cotización, con opcionales según la matriz), renglón canjeado = estrella
  llena que deshace + badge "⭐ Canje por N puntos".
- **Botón "Usar todos"** (reemplaza el check `usar_puntos`): prenderlo
  canjea UNA unidad de CADA renglón canjeable (como tocar cada estrella,
  con sus opcionales — la matriz decide; ej. en_plata deja el opcional
  cobrándose) mientras el saldo alcance, y prende además el canje-pago
  para que el resto del saldo descuente lo demás. Apagarlo deshace TODO.
  `CarritoService::canjearTodo()/desCanjearTodo()` en UNA re-cotización;
  estado del botón = `carrito['usar_puntos']`.
- **Resumen estético** en "Tus datos": "⭐ Tenés N puntos" + mini-card con
  "En artículos canjeados −N", "Como pago del resto −N (−$M)" y "Te
  quedan N puntos".
- El costo por renglón CON opcionales lo devuelve la cotización (la tienda
  nunca calcula): consumir el campo nuevo del contrato.

---

## Contrato API v1 (cambios ADITIVOS — documentar en api-v1-delivery.md)

- `GET /tiendas/{slug}/catalogo`: `articulos[].canje_opcionales`
  (`incluidos|en_plata|en_puntos`, solo si viaja `puntos_canje`).
- `POST carrito/cotizar` / alta: la respuesta suma por renglón canjeado el
  costo efectivo (`items[].puntos_canje` ya cotizado con opcionales según
  el modo) y, en `en_plata`, el monto que el renglón sigue pagando.
  `puntos.usados_en_articulos` ya existe y pasa a reflejar la matriz.
- Shape actual intacto: todo aditivo, sin versionar.

---

## Modelo de Datos

- **Migración tenant**: `articulos.canje_opcionales` enum/string default
  `incluidos`; `configuracion_puntos.restringir_canje_articulos` bool
  default `false`. (`TEST_FORCE_RECREATE=1` tras columnas tenant nuevas.)
- Sin cambios en `articulos_sucursales` (el pivot `canje_tienda` ya existe).

---

## Criterios de Aceptación

- [ ] RF-T58: switch off ⇒ suites actuales de POS y tienda intactas; switch
      on ⇒ canje-pago tope = suma de renglones habilitados (POS y tienda),
      canje por artículo del POS rechaza no habilitados; masivo
      habilita/deshabilita el pivot de la sucursal activa.
- [ ] RF-T59: las 6 combinaciones de la matriz cotizan y persisten
      `puntos_usados`/`articulos_canjeados_monto` correctos end-to-end
      (incluida la conversión a venta y su MovimientoPunto); `en_plata`
      deja el renglón pagando los opcionales.
- [ ] RF-T60: chip "pts.", inputs en el desplegable, sección en
      ProgramaPuntos con switch + tabla + masivo + ejemplos por combinación.
- [ ] RF-T61: detalle sin puntos; carrito con indicador y costo por ítem,
      disponibles/restantes visibles, botón "Usar todos" toggleable.
- [ ] Contrato actualizado; contract tests de la tienda con fixtures
      nuevos; Pint + suites verdes en ambos repos.

---

## Plan de Implementación

### Fase 0: cerrar la ronda anterior [PENDIENTE — merges bloqueados por permisos]
Validar en vivo RF-T51..T57 + ajuste RF-T54; mergear bcn#191 (squash) y
después tienda#55, EN ORDEN, borrando ramas.

### Fase 1 (CORE): motor [COMPLETO 2026-08-03 — falta validación en vivo]
Migración tenant + `PuntosService::costoCanjeArticulo()` (matriz) y
`restringeCanjeArticulos()`; POS (`WithPuntos` + motor) y tienda
(cotizador/alta) con matriz, `en_plata` y tope del canje-pago; catálogo +
contrato; 9 unit + 6 feature nuevos, regresión POS (51) y API (28) verdes.

### Fase 2 (CORE): paneles [COMPLETO 2026-08-03]
Chip "pts." + inputs al desplegable en ConfiguracionTiendaArticulos;
sección "Canje de artículos" en ProgramaPuntos (switch, ejemplos vivos,
tabla, masivos). Gotcha: ProgramaPuntos es #[Lazy] — en tests,
withoutLazyLoading se agota tras el primer ciclo (una instancia por test).

### Fase 3 (TIENDA): consumo [COMPLETO 2026-08-03]
Detalle sin puntos; carrito con chip "pts." + costo por renglón (de la
cotización), "Te quedan", botón "Usar todos"; canje con opcionales;
fixtures aditivos; suite completa 256 verdes. Gotcha: en
carrito.blade.php `@php($expr)` compila como bloque crudo — usar
`@php ... @endphp`.

### Ramas (apiladas sobre la ronda anterior)
- Core: `feat/canje-puntos-avanzado` (base `feat/tienda-ajustes-canje-puntos`).
- Tienda: `feat/canje-avanzado-tienda` (base `feat/ajustes-cuenta-puntos-ui`).
- Tras mergear las bases: rebase `--onto master` y actualizar los PRs.

### Pendientes de la ronda
- Manual de usuario + knowledge base (commit de docs al cierre).
- Validación en vivo completa (matriz, restricción, paneles y carrito).
