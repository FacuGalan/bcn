# Picker de Google Maps para el domicilio — Especificación

## Estado: IMPLEMENTADO — PENDIENTE VALIDACIÓN EN VIVO (Fase 4.2)

> Mejora del componente de domicilio reutilizable (Fase 9, RF-11): reemplazar la
> carga manual de latitud/longitud por un picker de Google Maps. Flujo invertido:
> primero provincia → localidad (catálogo, fuente de verdad), y eso **acota** el
> mapa/autocomplete a esa localidad dentro de Argentina. Aprobado por el usuario
> 2026-06-24 → `/sdd-apply` fase por fase.

---

## Contexto y Motivación

El componente reutilizable de domicilio (trait `ManejaDomicilio` + partial
`livewire/partials/domicilio-form.blade.php`, Fase 9) hoy captura `latitud`/`longitud`
como **inputs numéricos manuales**. Tipear coordenadas a mano es mala UX y propenso a
error. La nota `[[project_domicilio_google_maps_futuro]]` ya previó integrar Google Maps
y dejó el diseño preparado para que el cambio sea acotado y en un solo lugar que se
propaga a las **3 pantallas** que usan el domicilio:

- Domicilio físico de la **sucursal** (`ConfiguracionEmpresa` → `tab-sucursales`, `conGeo`).
- Domicilios fiscales del **CUIT** (`CuitDomicilios`).
- Domicilio fiscal del **cliente** (`GestionarClientes`).

Se prioriza **confiabilidad** sobre costo → Google Maps Platform (mejor cobertura en
Argentina que las alternativas gratuitas tipo OSM/Nominatim).

---

## Principios de Diseño

1. **Un solo lugar**: toda la UI del mapa vive en el partial `domicilio-form` y la lógica
   de bridge en el trait `ManejaDomicilio`. Las 3 pantallas lo heredan sin cambios propios.
2. **El catálogo manda, el mapa ayuda**: provincia y localidad salen de los selects del
   catálogo (`provincias`/`localidades`) y son la fuente de verdad. El mapa NO autocompleta
   provincia/localidad; al revés, la localidad **restringe** el mapa. Sólo lat/lng salen del mapa.
3. **Degradación segura**: sin `GOOGLE_MAPS_API_KEY` configurada, el form cae a los inputs
   lat/lng manuales actuales. Nunca se rompe la edición de domicilio.
4. **API vigente**: usar los componentes nuevos de Google (el widget legacy `Autocomplete`
   no está disponible para clientes nuevos desde 2025): `PlaceAutocompleteElement`,
   `AdvancedMarkerElement`, `importLibrary`.
5. **Sin tocar persistencia**: `datosDomicilio()`/`reglasDomicilio()`/`cargarDomicilio()` del
   trait y los campos `sucursales/clientes/cuit_domicilios.{latitud,longitud}` quedan igual.
6. **Multi-PWA/Vite**: el JS se registra como `Alpine.data()` desde `resources/js` (no script
   inline), bundle Vite. Ver [[feedback_alpine_data_bundle]].

---

## Requisitos Funcionales

### RF-01: Configuración de la API key
- **Una sola key global** (de BCN, no una por comercio): `GOOGLE_MAPS_API_KEY` en `.env`
  → `config/services.php` (`services.google_maps.key`). NO hay campo de key por tenant.
- Helper `mapsHabilitado()` = key no vacía. El partial decide map vs inputs manuales.
- La key se inyecta al front sólo en las vistas que usan el partial. Restricción por
  dominio/HTTP referrer la hace el usuario en Google Cloud (doc en el spec, no código).

### RF-02: Coordenadas en el catálogo de localidades
- Agregar `latitud`/`longitud` a `localidades` (config) + backfill desde
  `database/data/localidades_georef.json` (ya trae `lat`/`lon`).
- Sirven para **centrar y acotar** el mapa a la localidad elegida (y a futuro: tienda
  online, logística, distancias).

### RF-03: Flujo invertido provincia → localidad → mapa acotado
- El usuario elige provincia (ya `wire:model.live`) → localidad. Al elegir localidad, el
  mapa se centra en su coordenada y el autocomplete queda **restringido a Argentina
  (`includedRegionCodes: ['ar']`) + al área de la localidad (`locationRestriction`,
  bounds alrededor del centro)**.
- Sin localidad elegida: el mapa muestra un estado vacío/deshabilitado con hint
  ("Elegí provincia y localidad para ubicar el domicilio").

### RF-04: Selección del punto
Tres formas de fijar lat/lng, todas escriben en `domLatitud`/`domLongitud` vía el trait:
1. **Autocomplete de dirección** (`PlaceAutocompleteElement`) acotado a la localidad → al
   elegir una predicción, centra el mapa, coloca el marcador y setea lat/lng.
2. **Marcador arrastrable** (`AdvancedMarkerElement` `gmpDraggable`) → en `dragend` setea lat/lng.
3. **Botón "Usar mi ubicación actual"** (`navigator.geolocation.getCurrentPosition`) → centra,
   coloca marcador y setea lat/lng. Maneja permiso denegado/timeout con aviso.

### RF-05: Bridge mapa → Livewire
- Método del trait `setCoordenadasDesdeMapa(float|string|null $lat, float|string|null $lng)`
  que valida rango y setea las props. Invocado por el JS vía `$wire`.
- El centro de la localidad se expone al JS para que reaccione al cambio (ver Diseño técnico).

### RF-06: Fallback manual
- Con la key activa, además del mapa se ofrece un toggle "Ingresar coordenadas manualmente"
  (los inputs actuales), por si el usuario quiere pegar lat/lng. Sin key, sólo los inputs.

---

## Modelo de Datos

### Tablas modificadas

#### `localidades` (config, compartida) — Cambios
- Agregar: `latitud` (decimal(10,7), NULL) AFTER `nombre`
- Agregar: `longitud` (decimal(10,7), NULL) AFTER `latitud`
- Backfill desde `localidades_georef.json` matcheando por `provincia_id` (ISO→id) + `nombre`
  (normalizado). Las que no matcheen quedan NULL (el mapa cae a centro de provincia o AR).

> No se tocan `sucursales`, `clientes`, `cuit_domicilios` (ya tienen `latitud`/`longitud`).

---

## Pantallas UI

No hay pantallas nuevas. Se modifica el **partial compartido** y, por herencia, las 3
pantallas que lo incluyen. Sin cambios de ruta, menú ni permisos.

### Partial `livewire/partials/domicilio-form.blade.php`
- Bloque `@if($conGeo)`:
  - Si `mapsHabilitado()`: contenedor del mapa (Alpine `x-data="domicilioMapa(...)"`) con
    `PlaceAutocompleteElement` + `<div>` del mapa + botón "usar mi ubicación" + toggle manual.
  - Si no: los inputs lat/lng actuales (sin cambios).
- El contenedor recibe por data-attrs/props: key, centro de la localidad, lat/lng actuales,
  id del componente para el `$wire`.

### Trait `app/Traits/ManejaDomicilio.php`
- `localidad` pasa a `wire:model.live` (para que el server resuelva el centro al cambiar).
- Nueva prop computada `getDomLocalidadCentroProperty(): ?array` → `['lat'=>, 'lng'=>]` de la
  localidad elegida (o null). Alpine la entabla/observa.
- `setCoordenadasDesdeMapa($lat, $lng)`: valida y setea `domLatitud`/`domLongitud`.

---

## Servicios

No requiere service nuevo. Lógica mínima:
- Config helper `mapsHabilitado()` (en el trait o un pequeño helper/config accessor).
- El backfill de coordenadas vive en la **migración** (lee el JSON, igual que el reseed de Fase 9).

### JS — `resources/js/domicilio-mapa.js` (bundle Vite)
- Registra `Alpine.data('domicilioMapa', (config) => ({...}))`.
- Carga Maps JS API una sola vez vía el *bootstrap loader* (`importLibrary`) con libraries
  `places`, `marker`, `geocoding` (guard de doble carga entre instancias del form).
- Métodos: `init()`, `aplicarRestriccionLocalidad(centro)`, `onAutocomplete(place)`,
  `onDragEnd(e)`, `usarMiUbicacion()`, `push(lat,lng) → $wire.setCoordenadasDesdeMapa`.
- Observa el centro de la localidad (entangle/`$watch`) y re-centra + re-restringe.
- Importado en `resources/js/app.js`.

---

## Migraciones Necesarias

1. `add_geo_to_localidades` (config) — Agregar `latitud`/`longitud` a `localidades` +
   backfill desde `database/data/localidades_georef.json`. NO es tenant (tabla compartida);
   no toca `tenant_tables.sql`.

---

## Traducciones

Claves nuevas (es/en/pt) — provisorias:
| Clave (es) | en | pt |
|------------|----|----|
| Ubicar en el mapa | Locate on map | Localizar no mapa |
| Buscar dirección | Search address | Buscar endereço |
| Usar mi ubicación actual | Use my current location | Usar minha localização atual |
| Elegí provincia y localidad para ubicar el domicilio | Choose province and locality to locate the address | Escolha província e localidade para localizar o endereço |
| Ingresar coordenadas manualmente | Enter coordinates manually | Inserir coordenadas manualmente |
| No pudimos obtener tu ubicación | We couldn't get your location | Não foi possível obter sua localização |
| Arrastrá el marcador para ajustar el punto | Drag the marker to adjust the point | Arraste o marcador para ajustar o ponto |

---

## Criterios de Aceptación

- [ ] Con `GOOGLE_MAPS_API_KEY` seteada, el form de domicilio muestra el mapa en las 3 pantallas.
- [ ] Sin la key, el form muestra los inputs lat/lng manuales y todo funciona como antes.
- [ ] Elegir provincia→localidad centra el mapa en la localidad y acota el autocomplete a AR + esa zona.
- [ ] El autocomplete NO sugiere direcciones de otros países.
- [ ] Elegir una predicción / arrastrar el marcador / "usar mi ubicación" setea lat/lng y persiste al guardar.
- [ ] Provincia y localidad NO se pisan desde el mapa (las maneja el catálogo).
- [ ] `localidades` tiene `latitud`/`longitud` pobladas (backfill) para la mayoría de las localidades.
- [ ] Smoke tests de los 3 componentes siguen verdes; `setCoordenadasDesdeMapa` testeado a nivel trait.
- [ ] Pint + build Vite OK. Dark mode y responsive correctos en el bloque del mapa.

---

## Plan de Implementación

### Fase 1: Datos — geo en localidades [COMPLETO]
1. Migración `add_geo_to_localidades` (config) + backfill desde el JSON GeoRef.
2. `Localidad`: `latitud`/`longitud` en `$fillable` + casts decimal; método para exponer el centro.
3. Test de datos: tras la migración, X% de localidades con coords (o casos puntuales conocidos).

### Fase 2: Config + bridge [COMPLETO]
1. `config/services.php` → `google_maps.key`; helper `mapsHabilitado()`.
2. Trait `ManejaDomicilio`: `domLocalidadCentro` (computed), `setCoordenadasDesdeMapa()`,
   `localidad` a `.live`. Tests unitarios del método (rango, null).

### Fase 3: JS + UI [COMPLETO]
1. `resources/js/domicilio-mapa.js` (`Alpine.data`), import en `app.js`, loader de Maps.
2. Partial `domicilio-form`: bloque map (con key) / inputs (sin key) + toggle manual + botón ubicación.
3. Traducciones es/en/pt. `npm run build`.

### Fase 4: Verificación [EN CURSO]
1. Smoke de los 3 componentes (montan con y sin key) — ✅ + test del bridge `setCoordenadasDesdeMapa`.
2. Validación en vivo del usuario con la key real (las 3 pantallas, los 3 métodos de selección) — PENDIENTE.
3. `@docs-sync` + PR — PENDIENTE (tras validación).

---

## Notas y Decisiones

- 2026-06-24: Proveedor = **Google Maps** (confiabilidad > costo). Key configurable en `.env`,
  el usuario la provee y la restringe por dominio.
- 2026-06-24: **Flujo invertido** (corrección del usuario): provincia→localidad acotan el mapa,
  el mapa NO autocompleta provincia/localidad. Restricción `includedRegionCodes:['ar']` + bounds de la localidad.
- 2026-06-24: UX = autocomplete + marcador arrastrable + "usar mi ubicación actual".
- 2026-06-24: Coordenadas de localidad vía **columnas + backfill** del JSON GeoRef (no geocodificar al vuelo).
- API confirmada (doc oficial): `PlaceAutocompleteElement` (legacy Autocomplete no disponible
  para clientes nuevos desde 2025-03), `AdvancedMarkerElement` `gmpDraggable`, `importLibrary`.
- Relacionado: [[project_domicilio_google_maps_futuro]], [[project_sucursal_campos_pendiente_propagar]],
  Fase 9 del sistema impositivo (RF-11).
