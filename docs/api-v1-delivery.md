# API v1 — Pedidos Delivery

API REST del módulo de pedidos delivery/take-away (spec `pedidos-delivery`, RF-11).
Base: `https://{host}/api/v1`. Todas las respuestas son JSON; los errores tienen
forma uniforme:

```json
{ "error": { "code": "operacion_invalida", "message": "...", "details": null } }
```

| Código HTTP | `error.code` | Cuándo |
|---|---|---|
| 401 | `no_autenticado` / `no_autorizado` | Token faltante/inválido |
| 403 | `sin_permiso` | Token sin la ability requerida |
| 404 | `no_encontrado` / `tienda_no_encontrada` | Recurso o slug inexistente |
| 422 | `validacion` | Payload inválido (`details` trae los campos) |
| 422 | `operacion_invalida` | Regla de negocio (mensaje legible) |
| 429 | — | Throttle superado |
| 500 | `error_interno` | Error del servidor (sin detalle) |

## Audiencias

1. **Público por tienda** (sin auth, throttle 60/min): rutas bajo
   `/v1/tiendas/{slug}/...`. El `slug` identifica comercio+sucursal (la tienda
   es POR SUCURSAL).
2. **Integración** (Bearer token, throttle 120/min): token emitido por el
   comercio en *Configuración → Tokens de API* con **abilities**. La sucursal
   se indica con el header `X-Sucursal-Id` (default: la principal).
3. **Consumidores** (proyecto tienda, RF-T1..T3): cuenta GLOBAL
   cross-comercio con Bearer Sanctum propio (ver *Endpoints de consumidores*).
   El endpoint público de pedidos y `carrito/cotizar` aceptan opcionalmente
   ese Bearer (precios por cliente donde exista mapping). El token vive en la
   SESIÓN server-side de la tienda, nunca en el navegador del consumidor.
4. **Marketplace** (público, throttle 30/min): landing global de tiendas
   (`GET /v1/tiendas`, `GET /v1/rubros`), sin tenant.

### Abilities de los tokens de integración

| Ability | Da acceso a |
|---|---|
| `pedidos:read` | `GET /pedidos-delivery`, `GET /pedidos-delivery/{id}` |
| `pedidos:write` | `POST /pedidos-delivery`, `PATCH /pedidos-delivery/{id}` |
| `config:read` | `GET /delivery/config`, `GET /repartidores` |
| `catalogo:read` | (reservada para catálogo autenticado) |

## Endpoints públicos (por slug)

### `GET /v1/tiendas/{slug}`
Datos públicos de la tienda: nombre, ubicación, si está abierta ahora,
horarios/calendario, config de entrega, **contrato de promesa** y **formas de
pago declarables**:

```json
{
  "entrega": {
    "modo_promesa": "franjas|automatica|manual",
    "acepta_lo_antes_posible": true,
    "demora_base_min": 20,        // solo modo automatica (estimación "~X min")
    "demora_min_por_km": 5,       // solo modo automatica
    "usa_franjas": false          // true ⇒ consultar GET /franjas
  },
  "formas_pago": [
    { "id": 1, "nombre": "Efectivo", "codigo": "efectivo", "permite_vuelto": true,
      "ajuste_porcentaje": -10 }
  ]
}
```

**Checkout** *(aditivo 2026-07-21, RF-T19)*: `GET /tiendas/{slug}` suma
`checkout: { pedir_email: "no"|"opcional"|"obligatorio", pedir_cumpleanios:
bool, pedir_entre_calles: "no"|"opcional"|"obligatorio" }` — qué datos del
cliente pide el paso "tus datos" de la tienda. `entre_calles` (solo delivery)
viaja como `direccion.entre_calles` en `POST /pedidos`; con config
"obligatorio" y sin dato → 422. El core lo persiste DENTRO de la referencia
de entrega ("Entre calles: X · {referencia}"), visible en panel y comanda. Con
`pedir_email: "obligatorio"` el alta sin email (de payload o de la cuenta del
consumidor) da 422. El cumpleaños NUNCA es obligatorio: la tienda lo muestra
con la leyenda "se solicita para participar de promociones y descuentos" y
viaja como `cliente.fecha_nacimiento` (date, pasada) en `POST /pedidos`; el
core lo persiste en el cliente del comercio y, con Bearer, también en la
cuenta global del consumidor (`GET /consumidores/me` lo devuelve para
pre-llenar).

**Modalidades** *(aditivo 2026-07-29, RF-T30)*: `GET /tiendas/{slug}` suma
`delivery_habilitado` (bool), en paridad con el `takeaway_habilitado`
existente: qué modalidades ofrece la sucursal. Una tienda solo-take-away es
legítima — la tienda ofrece envío a domicilio solo con
`delivery_habilitado: true` y retiro solo con `takeaway_habilitado: true`.
`POST /pedidos` con un `tipo` de modalidad deshabilitada se rechaza
server-side (mismo tratamiento que el take-away deshabilitado; `cotizar` no
valida modalidad — cotiza igual, el bloqueo es del alta).
La card del marketplace (`GET /v1/tiendas`) y `GET /delivery/config`
(integraciones) suman el mismo campo. Campo ausente ⇒ asumir `true`
(retrocompatibilidad).

`formas_pago` son las declarables **contra entrega/retiro** más las de pago
ONLINE (aditivo 2026-08-06, abajo); `permite_vuelto: true` habilita el campo
`paga_con` del alta de pedido. `ajuste_porcentaje` es el descuento (negativo)
o recargo (positivo) de esa FP — mostrarlo junto a la opción ("Efectivo
−10%"); el monto exacto lo calcula `carrito/cotizar` con `forma_pago_id`.

**Pago online** *(aditivo 2026-08-06, RF-T77)*: cada FP suma `pago_online`
(bool) y `pago_online_modo` (`"checkout_pro" | null`). Con `pago_online:
true` la FP se paga EN LÍNEA (Mercado Pago Checkout Pro): al confirmar el
pedido la tienda recibe `pago_online.url_pago` y redirige ahí (badge sugerido:
"Pagás online de forma segura"). Decisión 2026-08-06: una FP con checkout es
**SOLO online** en la tienda (no se ofrece su variante declarable); cuando se
quiera ofrecer "pagar ahora o al recibir" se sumará `pago_online_opcional`
(aditivo). `checkout` suma además `propina: { activo: bool, opciones: [5,10,
15] }` *(RF-T83)*: con `activo`, el checkout ofrece propina (chips de % +
monto libre) SOLO al pagar online.

*(Aditivo 2026-07-21, RF-T18)* la lista viene ordenada por el `orden` que el
comercio definió en el panel (la tienda la muestra tal cual llega, sin
reordenar) y excluye las FP marcadas como no disponibles en tienda online
para esa sucursal (filtro server-side; el shape de cada ítem no cambia).

**Analytics, tema y comportamiento** (aditivo 2026-07-17, RF-T7 + RF-T6):

```json
{
  "analytics": {
    "ga4_measurement_id": "G-XXXXXXXXXX",   // null ⇒ NO inyectar gtag
    "meta_pixel_id": "123456789012345"      // null ⇒ NO inyectar fbq
  },
  "tema": {
    "colores": { "primario": "#4f46e5", "acento": "#f59e0b",
                 "fondo": "#f9fafb", "superficie": "#ffffff",
                 "texto": "#111827" },
    "tipografia": { "fuente": "system" },   // system|inter|poppins|roboto|montserrat|lora (self-hosted en la tienda)
    "radios": "md",                          // none|sm|md|lg|full
    "densidad": "normal",                    // compacta|normal|amplia
    "portada": {                             // (aditivo 2026-07-18, RF-T13)
      "overlay": false,                      // DEPRECADA (RF-T25, 2026-07-28): el core emite SIEMPRE false y la tienda la ignora (portada cruda). Se conserva por tolerancia (quitarla exige v2).
      "posicion": "center"                   // encuadre vertical: top|center|bottom (object-position)
    },
    "logo": {                                // (aditivo 2026-07-28, RF-T25)
      "radio": "full"                        // forma del logo del hero: none|sm|md|lg|full. INDEPENDIENTE de "radios" (que aplica a tarjetas/carrito/UI). Default full = círculo (comportamiento previo). El anillo de historias (RF-T24) acompaña esta forma.
    },
    "textos": {
      "slogan": "",                          // hero, bajo el nombre ('' ⇒ no se muestra)
      "descripcion": ""                      // texto del panel "Información del comercio" ('' ⇒ no se muestra). RF-T33 (2026-07-29): DEJA de ser sección propia de la home; hasta 5000 chars, render con whitespace-pre-line
    },
    "redes": {
      "facebook": "",                        // URL del perfil ('' ⇒ sin botón en el hero)
      "instagram": ""
    },
    "catalogo": {
      "layout": "grilla",                    // grilla|lista (renglón-tarjeta)
      "categorias_plegables": false          // (aditivo 2026-07-29, RF-T31) true ⇒ los títulos de categoría son acordeón plegable; ausente/false = como siempre
    },
    "destacados": {
      "modo": "banner",                      // banner|tarjeta_grande|ninguno
      "adorno": "ninguno",                   // glow|badge|ambos|ninguno. RF-T35 (2026-07-29): en tarjeta_grande decora la card (como siempre); en banner decora el TÍTULO del carrusel (los artículos del banner no llevan adorno)
      "titulo": "",                          // (aditivo 2026-07-29, RF-T35) título de la sección ('' ⇒ "Destacados")
      "color": ""                            // (aditivo 2026-07-30, RF-T38) color hex del adorno glow (glow de la tarjeta grande / latido del título del banner). ''/ausente/inválido ⇒ la tienda usa el color primario del tema
    },
    "promos": { "mostrar_home": false }      // true ⇒ agregar las promociones vigentes como PRIMER slide de las historias (RF-T24, 2026-07-28; antes pintaba el pill "Promociones de hoy" en la home, que se eliminó). También activa el anillo del logo aunque no haya fotos.
  },
  "comportamiento": {}                       // reservado (Principio 10); v1 sin seteos
}
```

Sub-objetos de RF-T13: los defaults replican el comportamiento previo al RF
(snapshot viejo sin las claves ⇒ la tienda usa estos defaults y se ve igual
que siempre — tolerancia a clave ausente en ambos lados).

`tema` es el resultado EFECTIVO (defaults del core + JSON configurado en el
panel): la tienda lo vuelca a sus design tokens sin defaults propios. Las
claves son contrato: agregar claves es aditivo; renombrar/quitar exige v2.

**Identidad visual** (aditivo 2026-07-17, RF-T11):

```json
{
  "logo_url": "https://core.example/storage/tiendas/1/uuid.webp",   // null ⇒ sin logo
  "portada_url": "https://core.example/storage/tiendas/1/uuid.webp" // null ⇒ sin portada (banner del header)
}
```

URLs ABSOLUTAS (host del core) porque la tienda corre en otro origen.
Imágenes re-encodeadas a WebP por el panel (logo ≤800px, portada ≤1600×900).

**Historias destacadas** (aditivo 2026-07-28, RF-T24):

```json
{
  "historias": [                             // [] ⇒ sin historias
    { "id": "uuid", "url": "https://core.example/storage/tiendas/1/historias/uuid.webp" }
  ]
}
```

En orden de reproducción; WebP vertical ≤1080×1920, máximo 3. Con historias
(o `tema.promos.mostrar_home` + promos vigentes de `GET /catalogo` →
`promociones_genericas`), la tienda rodea el logo del hero con el anillo tipo
historias; al tocarlo se abre el visor (promos como PRIMER slide, después las
fotos). El estado visto/no visto es de la TIENDA (sesión server-side, misma
vida que el carrito): la API no lo conoce.

### `GET /v1/tiendas/{slug}/franjas?tipo=delivery|take_away`
Horarios de entrega/retiro de la JORNADA con lugar (modo `franjas`):
```json
{ "modo_promesa": "franjas", "acepta_lo_antes_posible": true,
  "franjas": [ { "hora": "2026-07-08T20:30:00-03:00", "label": "20:30" } ] }
```
Vacío si la sucursal no trabaja por franjas. El valor `hora` es el que se
manda en `entrega.franja` del alta (la API rechaza horarios inventados o
vencidos). Los cupos por franja llegan en Fase 8.

### `GET /v1/tiendas/{slug}/puntos` *(Bearer consumidor — RF-T8, Fase 3)*

Saldo y reglas del programa de puntos DEL comercio de la tienda para el
consumidor logueado:

```json
{ "data": { "activo": true, "saldo": 120, "saldo_en_pesos": 6000,
            "valor_punto_canje": 50, "minimo_canje": 10,
            "puede_canjear": true } }
```

Sin cliente materializado (mapping D11), programa inactivo (comercio o
sucursal) o cliente excluido → `activo: false` con saldo 0 (nunca un error).
La consulta NO crea el cliente. Saldo por sucursal solo si el programa está
en modo `por_sucursal`.

### `GET /v1/tiendas/{slug}/catalogo?tipo=delivery|take_away`
Catálogo visible según RF-17 (activo + vendible + visible en tienda +
disponible para el tipo). Los **agotados vienen marcados** `"agotado": true,
"pedible": false` — se muestran pero la API bloquea pedirlos. Los precios son
FINALES (motor de precios del sistema: listas + promociones vigentes).

`imagen_url` (de artículos y categorías) es SIEMPRE una URL absoluta con el
host de la API (fix 2026-07-17): la tienda corre en otro origen y una ruta
relativa se rompería contra su propio host. `null` si no hay imagen.

**Precios tachados y promos genéricas** (aditivo 2026-07-18, RF-T13):

- Cada artículo suma `precio_lista`: el precio ANTES de promociones, SOLO
  cuando difiere del `precio` final (si no, `null`). La tienda lo muestra
  tachado junto al precio de oferta. Deriva del mismo motor de precios
  (nunca lo calcula la tienda).
- La respuesta suma `promociones_genericas: [{ "nombre", "descripcion" }]`:
  promociones vigentes HOY — comunes automáticas (sin cupón) y especiales
  automáticas (NxM/combos/grupos) del canal tienda. Alimenta el aviso
  "Promociones de hoy" de la home (visible según
  `tema.promos.mostrar_home`). Vacío ⇒ sin aviso. *(Cambio de contenido
  2026-07-29: las comunes con condición por artículo, antes excluidas por
  reflejarse en el precio tachado, ahora TAMBIÉN se listan — el comerciante
  espera verlas en la historia de promos.)*
- *(Aditivo 2026-07-21, RF-T21)* cada promo genérica suma `precio_fijo`
  (number|null — el precio fijo de la promo/combo, para destacarlo) y
  `condiciones` (list<string> legibles y listas para mostrar: mínimos de
  cantidad/total, forma de pago, categoría, mecánica NxM "Llevás 3, pagás
  2", días y horario). Lista vacía ⇒ promo sin condiciones. *(Contenido
  2026-07-29: se suma el ALCANCE — "Artículo: X" en comunes por artículo y
  "Aplica a: A, B, C y N más" en especiales, con tope de 3 nombres.)*

**Galería y badges por artículo** (aditivo 2026-07-20, RF-T14):

- Cada artículo suma `imagenes: ["https://...", ...]`: la galería de fotos
  ESPECÍFICAS de la tienda (config del panel, máx 5, ordenada, URLs
  absolutas). `[]` ⇒ sin galería: la tienda usa `imagen_url` (imagen
  operativa) como fallback. Con galería, `imagenes[0]` es la foto principal
  de las cards y el detalle muestra carrusel si hay más de una.
- Cada artículo suma `badges: [{ "tipo", "texto" }]` (máx 4). `tipo` ∈
  `sin_tacc | vegetariano | vegano | picante | nuevo | mas_vendido |
  artesanal | sin_azucar | sin_lactosa | kosher | con_frutos_secos |
  custom`. Con `custom`, `texto` trae el label libre (≤30 chars); en los
  predefinidos `texto` es `null` y el icono/color/label los resuelve la
  tienda. Tipos desconocidos NO viajan (el core sanea), pero la tienda
  debe IGNORAR tipos que no reconozca (tolerancia a catálogo futuro).
  `[]` ⇒ sin badges.
- Cada artículo suma `alergenos: ["soja", "huevos", ...]`: texto libre del
  comercio (máx 15 ítems de ≤40 chars, saneado por el core). La tienda
  muestra el aviso "Contiene: ..." en el DETALLE del artículo. `[]` ⇒ sin
  aviso.
- `descripcion` pasa a servir la descripción ESPECÍFICA de tienda cuando
  el comercio la cargó en el panel (campo por artículo, RF-T14); vacía ⇒
  la descripción operativa del artículo, como siempre (misma clave, sin
  cambio de shape).

**Canje de artículos por puntos** (aditivo 2026-07-30, RF-T47; costo
ajustado 2026-08-03, RF-T54; modo de opcionales 2026-08-03, RF-T59): cada
artículo puede sumar `puntos_canje` (int): el costo BASE en puntos de
canjearlo HOY, sin opcionales — solo viaja si el comercio lo habilitó para
la tienda (toggle por sucursal en el panel), el programa de puntos está
activo y el artículo tiene precio y no está agotado. AUSENTE ⇒ no canjeable
(la tienda no muestra nada). El costo es el CONFIGURADO del artículo
(`articulos.puntos_canje`, la misma fuente que usa el POS) si está cargado;
sin configurar se deriva del precio del día (`ceil(precio /
valor_punto_canje)`, la regla con la que el POS canjea artículos sin puntos
propios) — con Bearer, del precio DE ESE cliente. Junto con `puntos_canje`
viaja `canje_opcionales` (`incluidos|en_plata|en_puntos`, aditivo RF-T59):
cómo juegan los opcionales con precio en el canje — el costo REAL del
renglón con sus opcionales lo devuelve la cotización por ítem (ver esa
sección). El canje efectivo viaja en cotización/alta con
`items[].canjear_con_puntos`.

**Badges por categoría** (aditivo 2026-07-29, RF-T36): cada elemento de
`categorias[]` suma `badges: [{ "tipo", "texto" }]` con el MISMO catálogo,
tope y semántica que los badges de artículo (RF-T14, arriba) — incluida la
tolerancia a tipos desconocidos. La tienda los muestra como chips junto al
título del grupo en el catálogo. `[]` ⇒ sin badges.

**Banner por categoría** (aditivo 2026-08-03, RF-T62): el `imagen_url` de
`categorias[]` (que ya existía en el contrato, siempre absoluto, `null` sin
imagen) pasa a tener semántica definida: es el BANNER decorativo del
encabezado del grupo en el catálogo — la tienda lo renderiza como fondo de
la barra de la categoría (franja panorámica con `object-cover`, scrim
oscuro y el nombre encima; sin imagen, el encabezado queda como siempre).
Se administra desde el panel de configuración de tienda (subida por
categoría con re-encode WebP) o desde Artículos → Categorías. Cada elemento
de `categorias[]` suma además `imagen_focal: { "x": number, "y": number }`
(porcentajes 0–100, default `{50, 50}`): el punto focal elegido en el panel
para `object-position` — la foto original casi nunca tiene la proporción de
la franja y el focal decide qué parte se ve. La clave viaja SIEMPRE (con o
sin imagen); la tienda debe tolerar su ausencia (core viejo ⇒ centro).

**Encargos — pedidos para día futuro** (aditivo 2026-07-20, RF-T16):

- `GET /tiendas/{slug}` suma `encargos: { activo, anticipacion_horas,
  max_dias_adelante }`. Con `activo: true` la tienda ofrece "Encargar para
  otro día" — incluso con `abierta_ahora: false` (el encargo valida contra
  SU calendario, no contra el de atención).
- Cada artículo del catálogo suma `permite_encargo` (bool): apto para
  encargos. La tienda avisa antes de cotizar; el core valida igual.
- Endpoint nuevo `GET /tiendas/{slug}/encargos[?fecha=Y-m-d]`: sin fecha ⇒
  `{ activo, fechas: [{fecha, label}] }` (días de la ventana
  [ahora+anticipación, hoy+max_días] con al menos un slot); con fecha ⇒
  `{ activo, fecha, slots: [{hora: ISO8601, label: "HH:MM"}] }` (slots de
  30 min de los rangos del calendario de encargos). Inactivo ⇒
  `activo: false` con listas vacías (nunca error).
- `POST carrito/cotizar` y `POST /pedidos` aceptan
  `entrega.programado_para` (ISO 8601, un slot de GET /encargos). Slot
  inválido/vencido o artículo sin `permite_encargo` ⇒ 422 con
  `encargo_invalido` (cotizar) / `validacion` (alta) y mensaje claro. El
  alta persiste el encargo con `hora_pactada` = ese momento; el
  seguimiento no cambia de shape.

Los grupos de opcionales son los ASIGNADOS al artículo en la sucursal de la
tienda (paridad con el panel), con el precio de la asignación (override por
artículo, no el del catálogo global). Grupos sin opciones vivas no se
publican. `disponible: false` = mostrar deshabilitada (agotada):

```json
"opcionales": [
  { "grupo_id": 1, "nombre": "Extras", "tipo": "seleccionable|cuantitativo",
    "obligatorio": false, "min": 0, "max": 3,
    "opciones": [
      { "opcional_id": 4, "nombre": "Extra cheddar", "precio_extra": 250,
        "disponible": true }
    ] }
]
```

El `opcional_id` es el que se manda en `items.*.opcionales` de
`carrito/cotizar` y del alta. La cotización/alta **rechaza (422) opcionales
no asignados al artículo en esa sucursal o no disponibles**, y suma al total
el `precio_extra` de la asignación — el mismo cálculo del panel (el precio
del ítem que ve el motor incluye los opcionales; las promos aplican sobre
ese precio, igual que en el mostrador).

**Cache HTTP (RF-T5)**: la respuesta trae `ETag` y `Cache-Control:
public, max-age=60`. Revalidar con `If-None-Match` → `304` sin payload si el
catálogo no cambió. El armado además se cachea SERVER-SIDE 60s (los cambios
de catálogo/precios pueden demorar hasta un minuto en verse en la tienda).
`ETag` está en `exposed_headers` de CORS para consumo browser-side.

### `POST /v1/tiendas/{slug}/envios/cotizar`
```json
{ "latitud": -34.6037, "longitud": -58.3816, "hora_pactada": "2026-07-10 22:30:00" }
```
→ `{ alcance: "ok"|"fuera_de_alcance"|"desconocido", pedible, costo_envio,
distancia_km, zona, demora_estimada_min }`. Fuera de alcance **no es
pedible** por la API (el forzado es solo del panel).

`hora_pactada` es opcional: evalúa las franjas de costo de la zona para ese
momento (p. ej. envío más caro de noche); sin ella se cotiza para ahora.
Las zonas son polígonos dibujados en la config: si la sucursal tiene zonas
activas, ellas definen el alcance (fuera de todas ⇒ `fuera_de_alcance`);
sin zonas rige el radio general con costo por km.

### `POST /v1/tiendas/{slug}/carrito/cotizar`
Cotización server-side del carrito completo — el contrato que la tienda
muestra en el checkout. **Nunca calcular precios localmente.**
```json
{
  "tipo": "delivery",
  "items": [
    { "articulo_id": 12, "cantidad": 2,
      "opcionales": [{ "opcional_id": 5, "cantidad": 1 }] }
  ],
  "cupon_codigo": "PROMO10",
  "forma_pago_id": 1
}
```
→ items con promociones atribuidas, `subtotal`, `iva`, `descuento`,
`total_final`, `cupon`, `forma_pago`, `total_a_pagar`, `desglose_iva`. El
costo de envío va aparte (endpoint anterior) y lo suma el alta del pedido.

`cupon` *(enriquecido aditivo 2026-07-22)*: `{ id, codigo, descripcion,
descuento, aplica_a, articulos, articulos_bonificados }`. `aplica_a` es
`total` o `articulos`; con `articulos`, `articulos` son los NOMBRES de los
artículos objetivo del cupón y `articulos_bonificados` los IDs que efectivamente
matchearon en el carrito. Un cupón de artículos puntuales sin match cotiza OK
con `descuento: 0` y `articulos_bonificados: []` — la tienda debe avisar para
qué artículo es el cupón en vez de aplicarlo en silencio.

`forma_pago_id` (opcional): la FP que el consumidor piensa declarar. Participa
del precio con los **mismos cálculos del panel**: promociones y listas de
precios condicionadas por forma de pago, cupones restringidos a FP, y el
descuento/recargo de la FP (`forma_pago.ajuste_monto`). `total_final` sigue
siendo el total de bienes; **`total_a_pagar` = total_final + ajuste** es lo que
el consumidor paga (sin envío). Recomendado: re-cotizar al cambiar la FP en el
checkout. Un cupón restringido a formas de pago exige `forma_pago_id` (422 si
falta o no coincide).

`usar_puntos` (opcional bool — RF-T9, Fase 3, requiere Bearer de consumidor
con cliente): canjea el **MÁXIMO** de puntos posible como PAGO (no toca
precios ni `total_final`): `monto = min(saldo × valor_punto_canje,
total_a_pagar)`, `usados = ceil(monto / valor_punto_canje)`. Con programa
activo (canjee o no) la respuesta suma el bloque `puntos` y el
`total_a_pagar` queda NETO del canje:

```json
"puntos": { "usados": 40, "monto": 2000, "saldo": 120, "saldo_restante": 80,
            "puede_canjear": true, "a_ganar": 5 },
"total_a_pagar": 4551.43
```

`a_ganar` es el ESTIMADO de acumulación del pedido (fórmula real del panel:
monto pagado sin puntos × multiplicador de la FP ÷ monto_por_punto, con el
redondeo de la config; sin envío). El crédito verdadero lo hace la conversión
a venta.

**Canje de artículos por puntos** *(aditivo 2026-07-30, RF-T47; costo
ajustado 2026-08-03, RF-T54; opcionales y restricción 2026-08-03, RF-T58/
RF-T59; requiere Bearer con cliente)*: `items[].canjear_con_puntos` (bool)
marca ese renglón como CANJEADO. El costo por unidad sale de la MATRIZ del
artículo — costo base (el `puntos_canje` CONFIGURADO o, sin configurar,
derivado del precio del día `ceil(precio / valor_punto_canje)`) × modo
`canje_opcionales`:

- `incluidos` (default): el canje cubre artículo + opcionales; con costo
  derivado, los opcionales SUMAN al cálculo; con costo fijo, van incluidos.
- `en_plata`: el canje cubre solo el artículo pelado — los opcionales con
  precio SE SIGUEN COBRANDO (el renglón no queda en $0).
- `en_puntos`: los opcionales se convierten a puntos
  (`ceil(precio_opcional / valor_punto)`) y se suman al costo del renglón.

`articulos_canjeados_monto` agrega lo cubierto por canjes (en `en_plata`,
solo la parte artículo). La respuesta suma POR RENGLÓN (aditivo, solo
renglones habilitados con costo resoluble): `items[].puntos_canje` (costo
con los opcionales elegidos), `items[].canje_monto` ($ que cubre el canje),
`items[].canje_opcionales` (el modo) y `items[].pagado_con_puntos` (bool).
Reglas: solo artículos con `puntos_canje` en el catálogo (toggle prendido),
cantidad 1 por renglón, incompatible con 2 FP. El bloque `puntos` suma
`usados_en_articulos` (int) y el canje-pago (`usar_puntos`) se calcula sobre
el saldo NETO de esos puntos. **Restricción del programa (RF-T58)**: si el
comercio la activó, el canje-pago solo cubre la suma de los renglones
HABILITADOS no canjeados (los demás y el envío se pagan aparte) — sin
cambio de shape, solo cambia el tope de `puntos.monto`. Errores:
`422 canje_no_disponible` (sin Bearer/cliente/programa) y
`422 puntos_insuficientes` (saldo corto) — la UI no debería llegar a
ninguno.

**Multi-pago** *(aditivo 2026-07-21, RF-T18)*: `pagos` (opcional, hasta **2**
FP) reemplaza a `forma_pago_id` (si viajan ambos, gana `pagos`). Cada ítem
lleva el **monto que esa FP cubre SIN su ajuste** (los ajustes los calcula y
devuelve el core, sumados encima). `costo_envio` (opcional) es la cotización
que la tienda ya obtuvo de `/envios/cotizar`, para desglosar el total completo:

```json
{ "pagos": [ { "forma_pago_id": 1, "monto": 6000 },
             { "forma_pago_id": 3, "monto": 4000 } ],
  "costo_envio": 500 }
```

Reglas: los montos deben **sumar `total_final` + `costo_envio`** (±0.05, si no
422 `pagos_invalidos`); a lo sumo UN pago puede viajar **sin `monto`** y cubre
EL RESTO (recomendado: la tienda manda el monto de la primera FP y la segunda
sin monto — nunca calcula el resto localmente); FP repetida → 422; ambas FP
deben ser declarables en la tienda.

**Semántica del precio con 2 FP** *(cambio de comportamiento 2026-07-24 —
mismo shape, RF-01/RF-03 del spec multi-pago-consistente)*:
- **TODAS las FP declaradas participan del precio**: una promo, lista de
  precios o cupón condicionado por FP aplica solo si acepta el **set
  completo** (misma regla anti-abuso que los cupones en el POS). El resultado
  NO depende del orden de los pagos. *(Antes: solo `pagos[0]` participaba y
  el orden cambiaba el total.)*
- **Base del ajuste de cada FP = su porción de BIENES, bienes-primero con
  tope**: los bienes (total sin envío) se asignan a los pagos priorizando la
  FP de mayor descuento, con tope en el monto de cada pago y sin superar
  nunca el total de bienes; el envío (valor fijo, D17) no recibe descuentos
  ni recargos — vale también para RECARGOS (una FP con recargo que solo
  cubre envío no recarga nada, igual que en single-FP). Ej.: bienes $1000 +
  envío $500, efectivo −10% por $900 → genera **−$90** (10% de los $900 de
  bienes que cubre). *(Antes: prorrateo proporcional del envío — el mismo
  caso daba −$60.)* La regla es idéntica a la del panel delivery (fuente
  única `AsignadorBasesAjustePagos`).
- **Traslado del ajuste al pago "resto"** *(2026-07-24)*: un pago con monto
  DECLARADO se cobra por su monto exacto ("pago con un billete de $1000" →
  `monto_final` **$1000**) y el ajuste que **genera** (campo aditivo
  `ajuste_generado`) se aplica al pago SIN monto, que cubre el resto ya
  ajustado (`monto_ajuste` del resto = propio + trasladados). Si el pago con
  ajuste ES el resto, se lo aplica a sí mismo (no hay siguiente). Sin pago
  resto (ambos montos declarados), cada ajuste aplica sobre su propio pago.
  Invariantes: `monto_base + monto_ajuste = monto_final` y
  `Σ ajuste_generado = Σ monto_ajuste`.

Respuesta: `forma_pago` viene null y se suma `pagos[]`; **`total_a_pagar` =
Σ monto_final e INCLUYE el `costo_envio` informado** (a diferencia del modo
single-FP). Ej. (bienes $9500 + envío $500, efectivo $6000 declarado −10% y
transferencia el resto):

```json
"pagos": [
  { "forma_pago_id": 1, "nombre": "Efectivo", "monto_base": 6000,
    "ajuste_porcentaje": -10, "ajuste_generado": -600, "monto_ajuste": 0,
    "monto_final": 6000, "permite_vuelto": true },
  { "forma_pago_id": 3, "nombre": "Transferencia", "monto_base": 4000,
    "ajuste_porcentaje": 0, "ajuste_generado": 0, "monto_ajuste": -600,
    "monto_final": 3400, "permite_vuelto": false }
],
"total_a_pagar": 9400
```

Limitación v1: `pagos` + `usar_puntos` → 422 (el canje de puntos sigue
disponible solo con FP única).

### `POST /v1/tiendas/{slug}/pedidos`
Alta de pedido (throttle 15/min). Mismo payload del carrito **+**:
```json
{
  "cliente": { "nombre": "Juan", "telefono": "11...", "email": "j@x.com" },
  "direccion": { "direccion": "Av. Siempreviva 742", "referencia": "3B",
                 "latitud": -34.60, "longitud": -58.38, "localidad_id": null },
  "entrega": { "lo_antes_posible": true },
  "pago": { "forma_pago_id": 1, "paga_con": 20000 },
  "observaciones": "sin cebolla",
  "datos_fiscales": { "cuit": "20-...-3" }
}
```
`items[].observaciones` *(aditivo 2026-07-22)*: aclaración del cliente POR
ÍTEM (string, máx 255 — ej. "sin pepino"). Se persiste en el renglón del
pedido, se imprime en la comanda y se muestra en el panel; el seguimiento
(`GET /pedidos/{token}`) la devuelve en `items[].observaciones` (re-pedir la
conserva).

`entrega` (opcional — "¿cuándo lo querés?"):
- `franja` (solo modo `franjas`): un `hora` de `GET /franjas`; inventada o
  vencida → 422. Sin franja: default "lo antes posible" si la config lo
  ofrece; si no, 422 pidiendo elegir.
- `lo_antes_posible: true`: solo si `acepta_lo_antes_posible`; si no → 422.
- Modo `automatica`: la hora la calcula el sistema por distancia. Modo
  `manual` + aceptación manual: la pacta el comercio al aceptar.

`pago` (opcional — "¿cómo pagás?"): declara el pago **contra entrega/retiro**
como planificado (no cobra nada): `forma_pago_id` de `GET /tiendas/{slug}` y,
si `permite_vuelto`, `paga_con` (efectivo con el que paga → el repartidor sale
con el vuelto). `paga_con` menor al total → 422.

La FP declarada **impacta el precio del pedido** con los mismos cálculos que
`carrito/cotizar` (promos/listas por FP + ajuste por FP): el `total_final` del
pedido queda con el ajuste aplicado y el pago planificado se descompone como
en el panel (`monto_base + monto_ajuste = monto_final`). El **envío queda
fuera** de la base del ajuste (es un valor fijo): efectivo −10% sobre $1000 de
productos + $500 de envío = $1400. Checkout con la misma FP y pedido muestran
el MISMO total.

**Multi-pago** *(aditivo 2026-07-21, RF-T18)*: `pagos` (hasta 2, mismas reglas
que en `carrito/cotizar`; con `pagos`, el `pago` singular se ignora) admite
`paga_con` POR pago (solo FP con `permite_vuelto`; menor a su `monto_final` →
422). Los montos deben sumar `total_final` de bienes **+ el costo de envío que
cotiza el alta** (mismo valor de `/envios/cotizar`). El pedido queda con N
pagos **planificados** idénticos a un pedido cargado a mano en el panel
(desglose `monto_base/monto_ajuste/monto_final/monto_recibido/vuelto` por FP)
y `total_final` = Σ `monto_final`. Limitación v1: incompatible con
`usar_puntos` (422).

Reglas:
- Tienda cerrada (calendario/horarios) → 422.
- Con georreferenciación activa: coordenadas obligatorias, fuera de alcance → 422.
- Artículo agotado / no disponible para el tipo → 422 con el nombre.
- Según la config de la sucursal el pedido entra **"por aceptar"**
  (`por_aceptar: true`, sin número — el comercio lo confirma o rechaza) o
  **confirmado** directo (aceptación automática).
- Respuesta 201 con el pedido, incluido `token_seguimiento`.
- `usar_puntos: true` (RF-T9, con Bearer de consumidor con cliente): el core
  recalcula el canje MÁXIMO con saldo FRESCO y registra el pago con puntos
  como planificado (FP interna "Canje Puntos") + la FP declarada por el
  RESTO. El descuento de saldo real (MovimientoPunto) ocurre al convertir a
  venta — si el saldo se gastó en el medio, esa parte del canje falla en la
  conversión y lo resuelve el comercio (ventana asumida).
- `items[].canjear_con_puntos` *(aditivo 2026-07-30, RF-T47)*: mismas reglas
  que en `carrito/cotizar` (Bearer con cliente + programa, artículo
  canjeable, cantidad 1, sin opcionales pagos, sin 2 FP). El saldo se valida
  FRESCO contra artículos + canje-pago juntos; el renglón queda
  `pagado_con_puntos` y el ledger real (MovimientoPunto canje-artículo) lo
  crea la conversión a venta. El pedido persiste
  `puntos_canjeados_articulos` y `articulos_canjeados_monto` (visibles en el
  panel como en un pedido cargado a mano).
- **RF-T40** *(aditivo 2026-07-30)*: Bearer de consumidor con la gracia de
  verificación VENCIDA (`verificacion_vence_el` pasado) → `403
  verificacion_requerida` con mensaje accionable. Sin Bearer (invitado) no
  aplica. La tienda debería interceptar ANTES con su propio CTA.
- Consumidor logueado (Bearer del guard consumidores): el pedido guarda su
  identidad; el alta de cliente en el comercio depende de la política del
  comercio. El `carrito/cotizar` con ese mismo Bearer cotiza con su cliente
  (precios especiales) — checkout y pedido muestran el MISMO total.

**Pago ONLINE** *(aditivo 2026-08-06, RF-T77/RF-T83)* — con una FP de
`pago_online: true` en `pago.forma_pago_id`:

- Payload: `pago.retorno_url` (string, opcional — URL de la página de retorno
  de la tienda; admite el placeholder `{token}`, que el core reemplaza por el
  `token_seguimiento` real) y `propina` (decimal ≥ 0, opcional — solo con
  `checkout.propina.activo`; 422 si la tienda no la acepta).
- El pedido nace **BORRADOR "esperando pago"** (incluso con aceptación
  automática): invisible para el comercio, sin número y sin stock. La
  respuesta 201 suma:

```json
"pago_online": { "transaccion_id": 8, "url_pago": "https://www.mercadopago.com.ar/checkout/...",
                 "expira_en": "2026-08-06T19:30:00-03:00", "estado": "pendiente" }
```

- La tienda redirige a `url_pago` (misma pestaña). El pago vence a los 30 min
  (configurable). La acreditación la decide el **webhook** del core — el
  retorno del navegador SOLO consulta (`GET .../pago`), nunca acredita.
- Acreditado el pago: el pedido entra al circuito normal (por aceptar o
  confirmado según la config del comercio) con el pago YA registrado
  (`estado_pago: "pagado"`).
- La `propina` NO pasa por la cotización ni integra `total_final`: se suma al
  monto del pago online y queda discriminada ("Propina para el repartidor").
- Restricciones v1 (422): FP online no combina con `pagos` (multi-pago) ni
  con `usar_puntos`.
- Si la creación del pago en MP falla, el alta devuelve 422 y NO queda pedido
  (la tienda conserva el carrito y puede reintentar).

### `GET /v1/tiendas/{slug}/pedidos/{token_seguimiento}`
Seguimiento público (el token ULID es la credencial): estado + label, hora
pactada / `lo_antes_posible`, `demorado` (por aceptar con el timeout del
comercio vencido), repartidor en camino, timestamps y el canal de tiempo real.

**Máquina de estados del seguimiento** (render por `estado`; el `estado_label`
ya viene resuelto por tipo):

| `estado` | delivery | take_away |
|---|---|---|
| `borrador` + `por_aceptar` | esperando confirmación del comercio | ídem |
| `confirmado` | confirmado | confirmado |
| `en_preparacion` | en preparación | en preparación |
| `listo` | listo para enviar (**salteable** si la sucursal no usa este paso) | listo (salteable) |
| `en_camino` | en camino 🛵 (`repartidor_en_camino`) | **"Para retirar"** — el cliente pasa a buscarlo (`repartidor_en_camino` siempre null) |
| `entregado` | entregado | retirado/entregado |
| `cancelado` | con `cancelado_motivo` | ídem |

El estado interno `facturado` (convertido en venta) **nunca se expone**: el
GET lo devuelve como `entregado` y el canal de tiempo real no lo emite.
Cualquier estado puede saltearse (p. ej. aceptación automática con comanda
directa pasa confirmado→en_preparacion al toque): renderizar por progreso
acumulado, no por secuencia estricta.

La respuesta incluye `items[]` (agregado ADITIVO 2026-07-17, RF-T3
"re-pedir"): los renglones pedibles del pedido, EXCLUYENDO el
renglón-concepto del costo de envío y los conceptos sin artículo:

```json
"items": [
  { "articulo_id": 12, "nombre": "Hamburguesa clásica", "cantidad": 2,
    "opcionales": [
      { "opcional_id": 5, "nombre": "Cheddar extra", "cantidad": 1 }
    ] }
]
```

Sirve para mostrar qué se pidió en el seguimiento y para que la tienda arme
"re-pedir": rearma el carrito con `articulo_id`/`opcional_id`/`cantidad` y
**re-cotiza** (precios de hoy, nunca históricos).

**Bloque `puntos`** *(aditivo 2026-07-31, RF-T56)*: si el programa de puntos
del comercio está activo, el pedido no está cancelado y hay algo que ganar,
la respuesta suma:

```json
"puntos": { "activo": true, "a_ganar": 10, "vinculado": false }
```

`a_ganar` usa la fórmula real de acumulación (multiplicador de la FP
declarada incluido) sobre lo que se paga sin puntos; `vinculado` dice si el
pedido ya tiene una cuenta de consumidor atada. Con esto la tienda arma el
CTA de invitados: "este pedido hubiese sumado N puntos — registrate y los
sumás". `null` o clave ausente ⇒ no mostrar nada.

**Aditivos 2026-08-03 (RF-T63/RF-T64)** — la pantalla de seguimiento se
completa desde CUALQUIER dispositivo (el token es la credencial; antes la
dirección y los datos del cliente vivían solo en la sesión del navegador que
hizo el pedido):

```json
"direccion":  { "direccion": "Calle 117 n°547", "referencia": "Timbre B" },
"cliente":    { "nombre": "Arian Garelli", "telefono": "+542324313167" },
"repartidor": { "nombre": "Juan", "telefono": "+5492324000000" },
"palabra_clave": "Tigre"
```

- `direccion`: solo delivery con dirección; `null` en take_away. `referencia`
  puede ser `null`.
- `cliente`: nombre/teléfono declarados en el checkout (o los del cliente
  vinculado); `null` si el pedido no registró ninguno.
- `repartidor`: desde que el comercio lo ASIGNA (no solo `en_camino`), con
  teléfono para contactarlo. `repartidor_en_camino` (string) SIGUE viajando
  igual que siempre por compatibilidad.
- `palabra_clave` *(RF-T64)*: palabra generada al confirmar si la sucursal
  activa "Palabra clave de entrega". Viaja solo con repartidor asignado y
  pedido no cancelado — el consumidor la dice al recibir el pedido; el
  repartidor la conoce por el panel. `null` ⇒ no mostrar nada.
- El canal de tiempo real (`SeguimientoActualizado`) suma los espejos
  `repartidor_asignado` (`{nombre, telefono} | null`) y `palabra_clave` con
  las mismas reglas, y se emite TAMBIÉN al asignar repartidor (sin cambio de
  estado).

**Esperando pago online** *(aditivo 2026-08-06, RF-T77)*: el GET suma
`esperando_pago` (bool) — `true` mientras el pago online del pedido no
acredite (en ese lapso `por_aceptar` es `false`: el comercio todavía no ve el
pedido). El canal `SeguimientoActualizado` suma `pago_online`
(`{ estado: "aprobado" | "devuelto" } | null`): se emite al acreditarse el
pago y al devolverse (pedido rechazado con refund).

### `GET /v1/tiendas/{slug}/pedidos/{token_seguimiento}/pago`
*(aditivo 2026-08-06, RF-T79 — throttle 30/min)*

Estado del pago online del pedido — lo consume la página de retorno del
navegador (el `back_url` de MP **no es fuente de verdad**: esto consulta,
nunca acredita):

```json
{ "data": { "estado": "pendiente|aprobado|fallido|devuelto|sin_pago",
            "url_pago": "https://...|null", "expira_en": "ISO8601|null" } }
```

`pendiente` incluye `url_pago` para re-ofrecer el link vigente; `fallido`
habilita el botón "Reintentar pago" (POST de abajo). Con la transacción
pendiente el core re-consulta el estado VIVO a MP, así el retorno ve
`aprobado` aunque el webhook venga en camino (la transición del pedido sigue
siendo del webhook).

### `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/pago`
*(aditivo 2026-08-06, RF-T79 — throttle 10/min)*

Re-pago: si el pedido sigue "esperando pago" y la transacción anterior murió
(expiró / falló), crea una transacción NUEVA sobre el MISMO pedido — un fallo
de MP no le hace perder al consumidor el pedido armado. Body opcional:
`retorno_url` (mismas reglas del alta). Respuesta: el mismo shape de
`pago_online` del alta. 422 si el pedido ya no espera pago (cancelado,
acreditado o aceptado).

### `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/cancelar`
Cancelación por el consumidor: permitida hasta `confirmado` (antes de que
entre en preparación). Después, solo el comercio. *(Aditivo 2026-08-06,
RF-T82)*: si el pedido tiene un pago online acreditado, la cancelación (del
consumidor o del comercio) dispara la **devolución automática total** en MP
— propina incluida; el seguimiento avisa por el canal con
`pago_online: { estado: "devuelto" }`.

### `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/vincular`
*(aditivo 2026-07-31, RF-T56 — requiere Bearer de consumidor)*

Vinculación retroactiva de un pedido hecho como INVITADO a la cuenta del
consumidor logueado. La posesión del token de seguimiento es la credencial
sobre el pedido (misma regla que el seguimiento y la cancelación).

- Pedido sin cuenta → setea `consumidor_id`, resuelve/crea el cliente del
  comercio (política D11 `tienda_alta_cliente_automatica`; con OFF el pedido
  queda rastreable por `consumidor_id` sin cliente) y, si el pedido YA se
  convirtió a venta, adopta la venta y **acredita los puntos ganados** con
  la fórmula real. Si aún no se convirtió, no acredita nada: la conversión
  normal lo hará al encontrar el cliente.
- **Idempotente**: pedido ya vinculado a ESTA cuenta → `200` no-op con
  `puntos_acreditados: 0`. Vinculado a OTRA cuenta → `200` con
  `vinculado: false` (no se pisa).
- Respuesta: `{data: {vinculado: bool, puntos_acreditados: int}}`.
- Errores: `401` sin Bearer; `404` token inexistente o de otra tienda.
- Flujo tienda: el seguimiento muestra el CTA con `puntos.a_ganar` (GET de
  arriba); tras registro/login la tienda llama a este endpoint y comunica
  el resultado ("sumaste N puntos" / "se acreditan al completarse el
  pedido").

## Endpoints de consumidores (RF-T1..T3 + RF-T39..T42 + RF-T49, cuenta global de la tienda)

Base `/v1/consumidores`. Sin tenant (la cuenta es cross-comercio). Decisión
RF-T1: **se puede pedir sin verificar el email**; la verificación desbloquea
el historial. **RF-T40 (2026-07-30)**: a los **7 días** de creada sin
verificar, la cuenta queda RESTRINGIDA — el alta de pedido LOGUEADO responde
`403 verificacion_requerida` (comprar como invitado sigue abierto; verificar
des-restringe al instante). Throttle agresivo por endpoint (registro 5/min,
login 10/min, emails 3/min).

### Auth

- `POST /registro` — `{nombre, email, password (min 8), telefono?}` → `201`
  `{data: {token, consumidor}}` + email de verificación. El token sirve YA.
- `POST /login` — `{email, password}` → `{data: {token, consumidor}}`.
  Credenciales malas → `422 validacion` (mismo error genérico si la cuenta
  es solo-Google y no tiene password).
- `POST /auth/google` *(aditivo 2026-07-30, RF-T49)* — `{credential}` (el
  ID token de Google Identity Services que la tienda obtiene en el
  navegador). El core verifica firma/emisor/audiencia contra el
  `GOOGLE_CLIENT_ID` compartido y resuelve la cuenta: ya linkeada → login;
  email existente → linkea; si no → la CREA sin password. Respuesta
  `{data: {token, consumidor, creado}}` (`201` si creó, `200` si no). Si
  Google es autoritativo sobre el email (Gmail, o Workspace con
  `email_verified`), la cuenta queda **verificada** (sin mail de
  verificación ni plazo RF-T40); si no, flujo de verificación normal.
  Credential inválido → `422 validacion`; feature sin configurar → `503`
  `google_no_configurado` (la tienda no debería mostrar el botón sin el
  client ID). Throttle 10/min.
- `POST /logout` *(Bearer)* — revoca el token actual.
- `GET /me` *(Bearer)* — perfil: `{id, nombre, email, telefono,
  fecha_nacimiento, email_verificado, verificacion_vence_el}`.
  `verificacion_vence_el` (aditivo RF-T40): ISO-8601 con la fecha límite de
  la gracia de verificación, `null` si ya verificó — la tienda muestra la
  cuenta regresiva sin calcular nada.
- `PATCH /me` *(Bearer, aditivo 2026-07-30, RF-T39)* — edita el perfil:
  `{nombre?, telefono?, fecha_nacimiento?}` → perfil actualizado (mismo
  shape del GET). El EMAIL no se cambia por acá (es la sal del token de
  verificación); el password va por recuperar/restablecer.
- `POST /verificar` — `{token}` (del link del email, la tienda lo reenvía
  desde su página `/verificar`) → marca verificado (idempotente). Token
  inválido/vencido → `422 operacion_invalida`.
- `POST /reenviar-verificacion` *(Bearer)* — reenvía si falta verificar.
- `POST /recuperar` — `{email}` → siempre `200` (no revela existencia);
  si existe manda el link de reset (vence en 60 min, single-use).
- `POST /restablecer` — `{token, password}` → cambia el password y **revoca
  todos los tokens Y los dispositivos recordados** (la tienda debe
  re-loguear; las cookies remember quedan muertas).

**Aditivo 2026-08-06 (RF-T66, dispositivos recordados)**: `registro`,
`login` y `auth/google` aceptan `recordarme?: bool`. Con `true`, la
respuesta suma `dispositivo: {selector, validator}` — un par estilo
recaller que la tienda guarda en una cookie cifrada (el Bearer NUNCA viaja
al navegador; el validator se persiste solo hasheado en el core). Sin
`recordarme`, `dispositivo` viaja `null` (shape estable).

**Aditivo 2026-08-06 (RF-T73, lockout por email)**: además del throttle por
IP, `login` bloquea por EMAIL tras 5 intentos fallidos (15 min, se duplica
por lockout consecutivo hasta 4 h). Durante el lockout responde el MISMO
`422` genérico de credenciales (no revela cuenta ni lockout). El login
exitoso limpia el contador. Depende de que la tienda siga reenviando la IP
real (`X-Forwarded-For`).

### `POST /v1/consumidores/auth/recordar` *(aditivo 2026-08-06, RF-T66)*

Re-login silencioso: `{selector, validator}` → `200` `{data: {token,
consumidor, dispositivo}}`. El canje **ROTA el validator** (el par devuelto
en `dispositivo` reemplaza al de la cookie; el viejo queda inválido) y
desliza el vencimiento (+365 días). Par inexistente, vencido o validator
que no matchea → `401 dispositivo_invalido`. **Detección de robo**:
selector válido + validator inválido = alguien usó una copia vieja de la
cookie ⇒ se revocan TODOS los dispositivos del consumidor (la tienda verá
el 401 y borra su cookie). Máx. 10 dispositivos por consumidor (emitir el
11° poda el menos usado). Throttle 10/min.

### `POST /v1/consumidores/auth/magic-link` + `POST /v1/consumidores/auth/magic-login` *(aditivo 2026-08-06, RF-T69/T70)*

Login sin password por email. `magic-link` — `{email, volver?, pairing?}` →
**siempre `200`** (no revela existencia, patrón `recuperar`); si la cuenta
existe manda un mail con link a `{tienda}/entrar?token=...` (vence en
**15 min**, single-use) arrastrando `volver`/`pairing` como query OPACOS
(los valida la tienda al aterrizar; el core no los interpreta). Tope
silencioso de **1 mail cada 10 min por casilla** (nadie puede usar el
endpoint neutro para bombardear un email ajeno). Throttle 3/min. Este mismo
endpoint es la "detección de cuenta en checkout" (RF-T70): la tienda lo
llama con el email del invitado y `volver` al checkout — el aviso de la UI
es idéntico exista o no la cuenta.

`magic-login` — `{token, recordarme?}` → `{data: {token, consumidor,
dispositivo}}`. Canjear **verifica el email** si faltaba (probó control de
la casilla, mismo criterio que Google autoritativo). Token inválido,
vencido o ya usado → `422 operacion_invalida`. Throttle 10/min.

**Aditivo 2026-08-06 (RF-T72, Cloudflare Turnstile)**: con
`TURNSTILE_SECRET_KEY` configurado en el core, `registro`, `recuperar` y
`restablecer` exigen `turnstile_token` (el widget lo renderiza la tienda
con el site key). Token faltante o rechazado → `422 turnstile_invalido`.
Sin secret el feature queda APAGADO (nada cambia). Cloudflare inaccesible ⇒
fail-open (un registro legítimo no depende de la disponibilidad de CF).

### `GET|POST /v1/consumidores/dispositivos` + `DELETE /v1/consumidores/dispositivos[/{id}]` *(Bearer, aditivo 2026-08-06, RF-T66/T68/T74)*

"Mis dispositivos" de la cuenta. `GET` → `{data: [{id, nombre, ip_ultima,
ultimo_uso_at, creado_el, actual}]}` (más usado primero). El selector NUNCA
viaja en el listado: la tienda manda el suyo en el header `X-Dispositivo` y
el core marca `actual: true` en la fila que corresponda. `DELETE /{id}`
revoca uno (`404 no_encontrado` si no es del consumidor); `DELETE` sin id =
"cerrar sesión en los demás": revoca todos menos el del header
`X-Dispositivo` (sin header revoca todos) → `{data: {ok, revocados}}`.

`POST` (sin body) → `201` `{data: {dispositivo: {selector, validator}}}`:
emite un par EXTRA para el consumidor autenticado — es la pieza del pairing
webview↔navegador (RF-T68): tras loguear en el navegador real la tienda
pide un segundo dispositivo y lo deja en su cache para que el webview lo
canjee al volver. El `nombre` del dispositivo se refresca con el User-Agent
de quien CANJEA (el par nace con el UA del navegador que lo pidió y se
corrige al primer canje desde el webview). Throttle 10/min.

Un Bearer de INTEGRACIÓN (comercio) sobre estos endpoints → `403 sin_permiso`.

### `GET /v1/consumidores/favoritos` + `PUT|DELETE /v1/consumidores/favoritos/{slug}` *(Bearer, aditivo 2026-07-30, RF-T41)*

Tiendas favoritas del consumidor. `GET` → `{data: [{slug, nombre, comercio,
logo_url, localidad, habilitada, abierta_ahora}]}` (más reciente primero;
una tienda deshabilitada viaja con `habilitada: false`, la UI decide). `PUT`
marca y `DELETE` desmarca — ambos IDEMPOTENTES → `{data: {ok, favorito}}`.
Slug inexistente → `404 no_encontrado`.

### `GET /v1/consumidores/puntos` *(Bearer, aditivo 2026-07-30, RF-T42)*

Saldos de puntos CROSS-comercio para "mis puntos" de la cuenta: `{data:
[{tienda: {slug, nombre, habilitada}, saldo, saldo_en_pesos}]}`. Solo
comercios donde el consumidor ya tiene cliente mapeado Y el programa está
activo (sin programa no es "0 puntos": no viaja). Tenant caído se saltea.
Throttle 20/min.

### `GET|POST|PATCH|DELETE /v1/consumidores/direcciones[/{id}]` *(Bearer)*

CRUD de direcciones guardadas (máx. 10): `{alias?, direccion, referencia?,
localidad_id?, latitud?, longitud?, es_default?}`. La primera queda default;
marcar otra la desplaza; borrar la default promueve a la más nueva. El
checkout las precarga — el pedido sigue copiando snapshot.

### `GET /v1/consumidores/pedidos?page=&per_page=` *(Bearer, email verificado)*

Historial CROSS-comercio (fan-out a los tenants con tienda, merge por fecha
desc): `{data: [{fecha, numero, tipo, estado, por_aceptar, total_final,
token_seguimiento, tienda: {slug, habilitada, nombre}}], meta: {page,
per_page, total, has_more}}`. `estado` usa la misma verdad pública del
seguimiento (`facturado` = `entregado`). Sin verificar → `403 sin_permiso`.
"Re-pedir": la tienda arma el carrito desde `GET /pedidos/{token}` y
**re-cotiza** (precios de hoy, no históricos).

## Endpoints de marketplace (RF-T4, público)

### `GET /v1/tiendas?lat=&lng=&rubro_id=`

Tiendas habilitadas para la landing global. Con `lat/lng` excluye las que no
llegan (zonas dibujadas o radio; misma semántica de `envios/cotizar`) y
ordena por distancia; una tienda sin georreferenciar devuelve
`alcance: "desconocido"` (no se inventa alcance, D5). Sin coordenadas lista
todas en orden alfabético. Card: `{slug, nombre, comercio, rubro: {id,
nombre}, logo_url, direccion, localidad, latitud, longitud, abierta_ahora,
delivery_habilitado, takeaway_habilitado, alcance, distancia_km}`. Los datos
por tienda se
cachean ~5 min. `logo_url` (RF-T11): prima el logo propio de la tienda
(config del panel); fallback al logo de pantalla-cliente/empresa de la
sucursal. Por el cache, un cambio de logo puede demorar ~5 min en verse.

### `GET /v1/rubros`

Catálogo global de rubros activos: `[{id, nombre, slug}]` (cache 1 h).

## Endpoints de integración (Bearer + `X-Sucursal-Id`)

### `GET /v1/pedidos-delivery` *(pedidos:read)*
Listado paginado. Filtros query: `estado`, `tipo`, `origen`, `desde`, `hasta`,
`per_page` (max 100). Respuesta `{ data: [...], meta: {...} }`.

### `GET /v1/pedidos-delivery/{id}` *(pedidos:read)*

### `POST /v1/pedidos-delivery` *(pedidos:write)*
Alta con el mismo payload del endpoint público (origen `api` +
`origen_referencia` del integrador). Respeta la aceptación configurada.

### `PATCH /v1/pedidos-delivery/{id}` *(pedidos:write)*
Modificaciones operativas puntuales:
```json
{ "estado": "en_preparacion|listo|en_camino|entregado",
  "repartidor_id": 3, "observaciones": "...", "observacion_estado": "..." }
```
`en_camino` con repartidor asignado crea la salida de reparto implícita
(mismo circuito que el panel); para **take-away** significa "listo para
retirar" (sin salida). `entregado` sobre un pedido que está EN una salida de
reparto → 422: la entrega de un pedido en la calle se registra con la VUELTA
del repartidor desde el panel (ahí se cargan los cobros contra entrega).
La edición completa del carrito es del panel.

### `GET /v1/delivery/config` *(config:read)*
Config operativa de la sucursal (horarios, radio, costos, aceptación, etc.).

### `GET /v1/repartidores` *(config:read)*

## Tiempo real (Reverb)

- **Seguimiento público** (canal público, sin auth):
  `pedidos-delivery.seguimiento.{token_seguimiento}` — evento
  `SeguimientoActualizado` `{ estado, estado_label, repartidor,
  hora_pactada_at, lo_antes_posible, at }` en cada cambio de estado de un
  pedido externo (también cuando el comercio edita la hora pactada).
- **Panel/integraciones** (canal privado del comercio):
  `comercios.{comercioId}.pedidos-delivery` — evento `PedidoDeliveryBroadcast`
  `{ pedidoId, sucursalId, tipo, at }` con tipos `creado`, `estado_cambiado`,
  `pago_cambiado`, `cancelado`, `convertido_venta`.

## Alta de una tienda (registro global)

La tabla `config.tiendas` mapea `slug → comercio+sucursal` y habilita las
rutas públicas. v1 se administra por consola/soporte:

```php
Tienda::create(['comercio_id' => 1, 'sucursal_id' => 2,
                'slug' => 'mi-hamburgueseria', 'habilitada' => true]);
```
