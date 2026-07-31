# Ronda: Ajustes post-merge — estética de cuenta, puntos y canje

## Estado: EN REVISIÓN

> Spec de la ronda 2026-07-31. Continúa la numeración de RFs de
> `tienda-cuenta-consumidor.md` (último: RF-T50). Ajustes sobre la
> funcionalidad mergeada en core #189/#190 y tienda #54: estética del panel
> del consumidor, presencia del botón de perfil, modal "armando", corrección
> del circuito de canje por puntos y puntos retroactivos para invitados.
> Workflow cross-repo de siempre: los RF de CORE van primero (endpoint +
> contrato + tests), la tienda consume después.

---

## Contexto y Motivación

Validación en vivo de Facu (2026-07-31) sobre la ronda "cuenta del
consumidor" ya mergeada: lo funcional está bien, pero:

1. El panel Mi Cuenta quedó visualmente pobre (todo apilado, sin jerarquía,
   estética distinta a la de la tienda). Ídem la sección de puntos.
2. No se distingue si hay un usuario logueado, y el botón de perfil
   desaparece en el header sticky y no existe en carrito/checkout.
3. El banner "¿Lo de siempre?" muestra el total en plata del último pedido
   (puede haber variado → dato engañoso) y el feedback "Armando…" es un
   texto chico que no se nota.
4. El canje de puntos NO valida qué artículos son aceptados como canje: el
   toggle `canje_tienda` se puede prender sobre cualquier artículo aunque no
   tenga `puntos_canje` configurado, y el costo se deriva del precio del día
   (divergente del POS, que usa `articulos.puntos_canje`).
5. La presentación de puntos en el carrito está desparramada (card suelta,
   `a_ganar` duplicado en carrito y checkout) y falta el canje por ítem
   desde el carrito.
6. Un pedido de invitado nunca suma puntos (`PedidoDeliveryService::
   acreditarPuntosGanados()` retorna si no hay `cliente_id`) y no hay forma
   de reclamarlos al registrarse — se pierde el incentivo de registro más
   fuerte que tiene la tienda.

---

## Principios de Diseño

1. **La tienda nunca calcula**: todo número (puntos del canje, a_ganar,
   validación de saldo) lo responde el core vía cotización/endpoints. La
   tienda solo presenta.
2. **Paridad POS↔tienda**: el costo en puntos de un artículo canjeable es
   `articulos.puntos_canje` en TODOS los canales. Se elimina la regla
   divergente "derivado del precio del día" de la tienda.
3. **Token de seguimiento como credencial**: la vinculación retroactiva usa
   el mismo criterio de seguridad que el seguimiento público y la
   cancelación (posesión del ULID = derecho sobre el pedido).
4. **Idempotencia**: vincular un pedido ya vinculado es 200 no-op; el ledger
   de puntos es append-only (nunca se re-acredita).
5. **Contrato primero**: cambios de API se documentan en
   `docs/api-v1-delivery.md` antes de consumirse desde la tienda. Cambios
   aditivos, sin romper v1.
6. **Design system de la tienda**: el restyling reusa los tokens/cards/
   patrones existentes de la tienda (bcn-sheet, capas de transición, cards
   con `ring-borde`), no inventa estética nueva.

---

## Requisitos Funcionales

### RF de CORE

#### RF-T54: El canje por puntos lo manda `articulos.puntos_canje` (fix RF-T47)
- **Toggle** (`ConfiguracionTiendaArticulos::toggleCanjeTienda()`):
  - Guard servidor: no permite prender `canje_tienda` si el artículo tiene
    `puntos_canje` null o <= 0 (apagar siempre se permite).
  - Blade (`configuracion-tienda-articulos.blade.php` ~143-150): toggle
    `@disabled` cuando `puntos_canje <= 0` + tooltip "Cargale puntos de
    canje en el artículo" (traducido es/en/pt), como pedía RF-T47.
- **Catálogo** (`CatalogoTiendaService` ~205-213): `articulos[].puntos_canje`
  publica el valor CONFIGURADO `$articulo->puntos_canje` (int) en vez del
  derivado `ceil(precio/valor_punto)`. Condición de publicación: toggle
  prendido Y `puntos_canje > 0` Y no agotado Y precio > 0. Shape del
  contrato no cambia (sigue siendo int aditivo).
- **Cotización** (`CotizadorCarritoTienda::construirItem()` ~551-564):
  guard adicional — canje rechazado si `articulo->puntos_canje <= 0`
  (defensa en profundidad aunque el toggle ya no debería permitirlo).
  `puntosUsadosEnArticulos()` (~485) suma `articulo->puntos_canje` por
  renglón canjeado en vez del derivado del precio.
- **Alta de pedido** (`PedidoTiendaService`): `pedido_delivery_detalles.
  puntos_usados` del renglón canjeado = `articulos.puntos_canje` (es lo que
  después consume `procesarCanjesPuntos()` → `canjearArticuloConPuntos()` →
  `MovimientoPunto`). Verificar con test end-to-end que el circuito
  completo persiste: `pagado_con_puntos`, `puntos_usados`,
  `puntos_canjeados_articulos` en cabecera y el movimiento en el ledger al
  convertir a venta.
- Los artículos con toggle prendido pero `puntos_canje <= 0` que hayan
  quedado de la versión anterior dejan de publicarse como canjeables
  (autosaneado por la condición del catálogo; no hace falta migración de
  datos).

#### RF-T56: Vinculación retroactiva de pedido invitado a consumidor
- `POST /v1/tiendas/{slug}/pedidos/{token}/vincular` (Bearer consumidor
  requerido; `token` = token de seguimiento ULID).
- Comportamiento:
  1. Pedido con `consumidor_id` ya seteado → **200 no-op** (idempotente),
     responde el estado actual.
  2. Pedido sin `consumidor_id` → setea `consumidor_id`, resuelve/crea el
     cliente tenant reusando la lógica de `PedidoTiendaService::
     resolverClienteId()` (respeta flag D11 `tienda_alta_cliente_automatica`
     y el mapping `consumidor_comercio`), y setea `cliente_id` en el pedido
     si correspondiera.
  3. **Puntos**: si el pedido ya se convirtió a venta (`venta_id` no null) y
     el programa está activo → acredita con `PuntosService::
     acumularPuntosPorVenta()` (número fiel a la fórmula del comercio). Si
     NO se convirtió aún → no acredita nada: la conversión normal
     encontrará `cliente_id` y acreditará sola.
  4. Respuesta: `{vinculado: true, puntos_acreditados: int}` (0 si no
     correspondió acreditar).
- **Seguimiento** (aditivo al contrato): `GET /tiendas/{slug}/pedidos/{token}`
  expone `puntos: {activo: bool, a_ganar: int}` — estimación de
  `PuntosTiendaService::estimarAGanar()` sobre el pedido — para que la
  tienda pueda decir "este pedido hubiese sumado N puntos" sin calcular.
  Solo si el programa del comercio está activo.
- Contrato documentado en `docs/api-v1-delivery.md` (sección endpoints
  públicos por slug) + tests feature (vincular ok, idempotente, token
  inexistente 404, sin Bearer 401, pedido convertido acredita, pedido no
  convertido no acredita y acredita al convertir, D11 OFF no crea cliente
  pero guarda consumidor_id).

### RF de TIENDA

#### RF-T51: Restyling del panel Mi Cuenta y de la sección puntos
- `cuenta.blade.php` (417 l., hoy un solo `flex flex-col gap-5` plano):
  reorganizar con la estética de la tienda — cards con jerarquía, iconos,
  secciones colapsables o navegación por tabs (perfil / direcciones /
  puntos / favoritos / pedidos), y una cabecera de cuenta con avatar
  (inicial del nombre), nombre y estado de verificación.
- La card de puntos cross-comercio pasa de lista gris a card con peso
  visual: saldo grande por comercio, equivalencia en pesos, logo del
  comercio (mismo tratamiento visual que favoritos).
- Sin cambios de funcionalidad: solo presentación (el estado Livewire de
  `Cuenta.php` se toca solo si el patrón de tabs/secciones lo requiere).

#### RF-T52: Indicador de logueado + botón de perfil persistente
- **Indicador**: cuando hay sesión de consumidor, el botón de perfil
  muestra la INICIAL del nombre en un círculo con color de acento de la
  tienda (variable CSS del tema) en vez del icono genérico de usuario.
  Anónimo: icono genérico actual. Fuente: `ConsumidorService::perfil()`
  (ya en sesión, cero core).
- **Persistencia**:
  - `buscador.blade.php` (~40-47): quitar `x-show="! compacto"` del botón
    de perfil — persiste en el header sticky, siempre a la derecha del
    todo (el ⋮ puede quedar a su izquierda o absorberse; mantener orden:
    […, ⋮, perfil] con perfil como último elemento a la derecha).
  - `header-pagina.blade.php` (carrito, checkout, seguimiento): agregar el
    mismo botón de perfil a la derecha de todo (después del tacho en
    carrito). Mismo partial/componente Blade compartido para no duplicar
    (crear `tienda/partials/boton-perfil.blade.php` con
    `@inject ConsumidorService`).
- Navega a `/mi-cuenta` (logueado) o `/login` con `volver` a la página
  actual (anónimo), comportamiento actual del botón.

#### RF-T53: "¿Lo de siempre?" sin total y con modal "Armando…"
- `ultimo-pedido.blade.php` línea 12: quitar `· {{ precio($ultimoPedido['total']) }}`
  (mantener fecha/resumen de ítems; el total puede haber variado y lo dirá
  la cotización real en el carrito).
- Reemplazar el `wire:loading` de texto en el botón (líneas 17-18) por un
  **modal centrado flotante** (patrón Alpine del modal "¿Vaciar el
  carrito?" de carrito.blade 7-34 + `wire:loading.delay` sobre
  `repetirUltimoPedido`/`repedirPedido`): overlay oscuro, card centrada con
  animación CSS/SVG de un paquete entrando a una bolsa de compras (bolsa
  SVG + paquete animado con keyframes; sin librerías externas), texto
  "Armando tu pedido…" y sensación de proceso hasta que Livewire redirige
  al carrito.
- El mismo modal aplica al repedir desde el sheet de pedidos anteriores y
  desde mi-cuenta (misma acción Livewire, mismo overlay).

#### RF-T55: Presentación de puntos en carrito y checkout + canje por ítem
- **Bloque de puntos del carrito** (carrito.blade ~914-949): se muda ADENTRO
  del contenedor "Tus datos" (`#seccion-datos`, ~140-197) como una fila
  más: saldo actual + toggle de canje como pago (usar_puntos). Se elimina
  la card suelta.
- **`a_ganar` solo en el último paso**: quitar las líneas ~943-947 del
  carrito; queda únicamente en checkout.blade (~370-372, "Esta compra suma
  N puntos").
- **Estrellita de canje POR ÍTEM** (carrito.blade, junto al botón borrar de
  cada renglón ~93-131): botón ⭐ que canjea SOLO ese artículo:
  - Visible solo si: el artículo permite canje (clave `puntos_canje` del
    catálogo presente) Y al consumidor le alcanzan los puntos contando los
    ya comprometidos en otros canjes del carrito (saldo del bloque
    `puntos` de la cotización − `usados_en_articulos` ≥ `puntos_canje` del
    artículo). Datos: cotización + catálogo, sin cálculo nuevo en tienda
    (la cotización ya trae saldo y usados; la comparación es presentación).
  - Renglón ya canjeado: la estrellita se muestra activa (estado actual del
    badge CANJE $0) y permite deshacer el canje de ESE renglón.
  - Reusa `CarritoService::agregarCanje()` / la acción existente de
    `canjearArticulo()` de Home adaptada al contexto carrito.
- El canje como PAGO (usar_puntos, RF-T9) no cambia: convive en "Tus datos".

#### RF-T57: Puntos retroactivos desde el seguimiento (consume RF-T56)
- En la vista de seguimiento (`seguimiento.blade.php`), si NO hay sesión de
  consumidor y el payload trae `puntos.activo` con `a_ganar > 0`:
  - Card/banner: "Este pedido hubiese sumado **N puntos** 🎁 Registrate y
    los sumás para tu próxima compra" + CTA a `/registro` propagando
    `volver` = URL del seguimiento (el guard anti-open-redirect de
    `Registro::destino()` ya lo cubre).
  - Cubrir también login ("¿Ya tenés cuenta? Ingresá").
- Al volver al seguimiento CON sesión (registro o login recién completado)
  y el pedido sin vincular: la tienda llama al endpoint de vinculación
  (`CoreApi::vincularPedido($slug, $token)`) y muestra el resultado
  ("¡Listo! Sumaste N puntos" o "Los puntos se acreditan cuando el comercio
  confirme la entrega" si aún no se convirtió).
  - La vinculación se dispara desde `Seguimiento::mount()` cuando hay
    Bearer y el pedido no tiene consumidor (una sola vez por pedido;
    marcar en sesión para no repetir el POST en cada refresh — la
    idempotencia del core igual protege).
- Usuario logueado viendo un pedido que hizo como invitado: mismo flujo de
  vinculación automática (sin banner de registro).

---

## Modelo de Datos

**Sin tablas nuevas y sin migraciones.** Todo usa columnas existentes:
- `articulos.puntos_canje`, `articulos_sucursales.canje_tienda` (RF-T54)
- `pedidos_delivery.consumidor_id`, `venta_id`, `token_seguimiento`;
  `config.consumidor_comercio`; ledger `movimientos_puntos` (RF-T56)

---

## Pantallas UI

### Core
- `ConfiguracionTiendaArticulos` (existente): toggle ⭐ deshabilitado +
  tooltip cuando `puntos_canje <= 0`.

### Tienda
- `Consumidor\Cuenta` (existente): restyling completo (RF-T51).
- `Tienda\Home` → `buscador.blade` / `hero.blade`: botón perfil con inicial
  y persistencia en sticky (RF-T52).
- `tienda/partials/header-pagina.blade.php`: + botón perfil (RF-T52).
- `tienda/partials/boton-perfil.blade.php` (NUEVO partial compartido).
- `tienda/secciones/ultimo-pedido.blade.php`: sin total + modal armando
  (RF-T53).
- `Tienda\Carrito` (blade): puntos dentro de "Tus datos", estrellita por
  ítem, sin `a_ganar` (RF-T55).
- `Tienda\Checkout` (blade): único lugar de `a_ganar` (RF-T55).
- `Tienda\Seguimiento`: banner puntos + vinculación automática (RF-T57).

---

## Servicios

### Core
- `PedidoTiendaService` (o service nuevo `VinculacionPedidoService`):
  método `vincular(PedidoDelivery $pedido, Consumidor $consumidor): array`
  — extrae/reusa `resolverClienteId()`; transacción
  `DB::connection('pymes_tenant')->transaction()`.
- `CotizadorCarritoTienda`: guard + costo desde `puntos_canje` (RF-T54).
- `CatalogoTiendaService`: publica `puntos_canje` configurado (RF-T54).
- Controller: `PedidoPublicoController` (o el que hoy sirve seguimiento) +
  ruta `POST /v1/tiendas/{slug}/pedidos/{token}/vincular` con
  `auth:sanctum` de consumidor.

### Tienda
- `CoreApi::vincularPedido(string $slug, string $token): array` (NUEVO).
- `CarritoService`: sin cambios de fondo (agregarCanje/quitar canje por
  ítem ya existen; ajustar si el contexto carrito necesita variante).

---

## Migraciones Necesarias

Ninguna.

---

## Traducciones

### Core (lang/{es,en,pt}.json)
| Clave (es) | Contexto |
|------------|----------|
| "Cargale puntos de canje en el artículo" | tooltip toggle RF-T54 |

### Tienda (repo bcn-tienda, sus lang/)
| Clave (es) | Contexto |
|------------|----------|
| "Armando tu pedido…" | modal RF-T53 |
| "Este pedido hubiese sumado :n puntos" | banner RF-T57 |
| "Registrate y los sumás para tu próxima compra" | banner RF-T57 |
| "¡Listo! Sumaste :n puntos" | post-vinculación RF-T57 |
| "Los puntos se acreditan cuando se complete el pedido" | post-vinculación RF-T57 |
| "Canjear por :n puntos" / "Deshacer canje" | estrellita RF-T55 |
| (las que surjan del restyling RF-T51) | |

---

## Criterios de Aceptación

- [ ] RF-T54: toggle no se puede prender sin `puntos_canje > 0` (UI
      deshabilitada + guard servidor); catálogo publica el `puntos_canje`
      configurado; cotización rechaza canje de artículo sin puntos_canje;
      un canje end-to-end persiste `puntos_usados` = puntos_canje del
      artículo y genera el `MovimientoPunto` correcto al convertir.
- [ ] RF-T56: vincular setea consumidor_id + cliente (según D11), acredita
      solo si hay venta, es idempotente, exige Bearer, 404 con token malo;
      seguimiento expone `puntos.a_ganar`; contrato actualizado.
- [ ] RF-T51: Mi Cuenta con jerarquía visual consistente con la tienda;
      smoke tests siguen verdes.
- [ ] RF-T52: con sesión, el botón muestra la inicial con acento; persiste
      en sticky, carrito, checkout y seguimiento, siempre último a la
      derecha; sin sesión, icono genérico. Desktop Y móvil verificados.
- [ ] RF-T53: banner sin total en plata; al repetir pedido aparece modal
      centrado con animación hasta la redirección al carrito.
- [ ] RF-T55: puntos dentro de "Tus datos"; `a_ganar` SOLO en checkout;
      estrellita por ítem visible únicamente si el artículo es canjeable y
      alcanza el saldo neto de otros canjes; canjea/deshace solo ese ítem.
- [ ] RF-T57: invitado con programa activo ve el banner con N puntos y CTA;
      tras registro/login la tienda vincula y comunica el resultado; los
      puntos aparecen en la cuenta del consumidor.
- [ ] Pint + suites completas verdes en ambos repos; contract tests de la
      tienda actualizados con fixtures nuevos.

---

## Plan de Implementación

### Fase 1 (CORE): RF-T54 — canje mandado por puntos_canje [COMPLETO]
1. Guard en `toggleCanjeTienda()` + `@disabled`/tooltip en blade + traducciones.
2. `CatalogoTiendaService`: publicar `puntos_canje` configurado.
3. `CotizadorCarritoTienda`: guard + `puntosUsadosEnArticulos()` con costo configurado.
4. `PedidoTiendaService`: `puntos_usados` del detalle = puntos_canje.
5. Tests: unit cotizador + feature API (catálogo, cotización, alta, conversión con ledger).

### Fase 2 (CORE): RF-T56 — vinculación retroactiva [COMPLETO]
1. Service de vinculación (reusa `resolverClienteId()`), transacción tenant.
2. Ruta + controller + `puntos.a_ganar` en el payload de seguimiento.
3. Contrato `docs/api-v1-delivery.md` + tests feature completos.
4. PR core (Fases 1+2 juntas) → CI → merge.

### Fase 3 (TIENDA): RF-T52 + RF-T53 — perfil persistente y modal armando [PENDIENTE]
1. Partial `boton-perfil.blade.php` (inicial/acento vs icono) + integrarlo
   en buscador (sin `x-show="! compacto"`) y header-pagina.
2. Banner sin total + modal "Armando…" con animación CSS/SVG.
3. Smoke/feature tests + verificación desktop y móvil en vivo.

### Fase 4 (TIENDA): RF-T55 — puntos en carrito/checkout [PENDIENTE]
1. Mover bloque de puntos a "Tus datos"; quitar `a_ganar` del carrito.
2. Estrellita por ítem (visibilidad por catálogo + cotización; canje/deshacer).
3. Contract tests con fixtures de cotización con canje.

### Fase 5 (TIENDA): RF-T51 — restyling Mi Cuenta [PENDIENTE]
1. Reorganización visual de cuenta.blade.php (+ Cuenta.php si hay tabs).
2. Card de puntos cross-comercio con peso visual.
3. Smoke tests verdes; validación en vivo de Facu.

### Fase 6 (TIENDA): RF-T57 — puntos retroactivos [PENDIENTE]
1. `CoreApi::vincularPedido()` + fixture.
2. Banner en seguimiento + propagación `volver` + vinculación en mount.
3. Feature tests (banner, vinculación post-registro, idempotencia, logueado
   directo). PR tienda → CI → merge.

### Verificación final [PENDIENTE]
- Pint + suites completas ambos repos; `@docs-sync` en cada PR;
  validación en vivo integral de Facu (:8000/:8001).

---

## Notas y Decisiones

- 2026-07-31: **Opción A adoptada para el canje** — `articulos.puntos_canje`
  manda (paridad POS); se elimina la regla "costo derivado del precio del
  día" que quedó implementada en la ronda anterior contradiciendo RF-T47.
- 2026-07-31: **Opción A para retroactivo** — endpoint dedicado de
  vinculación con token de seguimiento como credencial. Descartadas:
  parámetro en el registro (mezcla responsabilidades, no cubre login) y
  barrido por email (caro y riesgoso: email mal tipeado regala puntos).
- 2026-07-31: la acreditación retroactiva usa `acumularPuntosPorVenta()`
  SOLO si hay `venta_id`; pedidos aún no convertidos acreditan por el
  camino normal de la conversión (evita doble asiento).
- 2026-07-31: indicador de logueado = inicial del nombre en círculo con
  color de acento del tema de la tienda (propuesta; alternativa era solo un
  punto de color).
- Gotchas heredados: tests que rendericen Home con sesión logueada deben
  fakear `*/consumidores/favoritos`; fixtures JSON SIEMPRE con Edit tool
  (PowerShell 5 mete BOM); clases Tailwind nuevas ⇒ `npm run build`.
