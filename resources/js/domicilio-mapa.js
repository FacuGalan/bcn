/**
 * Componente Alpine `domicilioMapa` — picker de Google Maps para el domicilio.
 *
 * Se registra en `alpine:init` (igual que kanban.js) y lo usa el partial
 * `resources/views/livewire/partials/domicilio-form.blade.php` cuando hay API key.
 *
 * Flujo invertido: provincia → localidad (selects del catálogo, fuente de verdad)
 * acotan el mapa. El centro de la localidad llega por la prop Livewire
 * `domLocalidadCentro` (se observa con $wire.$watch). El autocomplete queda
 * restringido a Argentina + al área de la localidad. Al elegir una predicción,
 * arrastrar el marcador o "usar mi ubicación", se setean lat/lng vía
 * `$wire.setCoordenadasDesdeMapa(...)` (trait ManejaDomicilio).
 *
 * API vigente (el widget legacy Autocomplete no está para clientes nuevos desde
 * 2025): PlaceAutocompleteElement (evento `gmp-select`), AdvancedMarkerElement
 * (`gmpDraggable`), carga vía bootstrap loader + `importLibrary`.
 */

// Centro por defecto: Argentina (cuando no hay localidad ni coords).
const CENTRO_AR = { lat: -38.4161, lng: -63.6167 };

// Paleta ciclada de zonas de reparto — compartida con zonas-mapa.js para que
// una zona tenga el MISMO color en la config y en el picker de domicilio.
export const COLORES_ZONAS = ['#0891b2', '#d97706', '#7c3aed', '#dc2626', '#059669', '#db2777', '#2563eb', '#65a30d'];

/** Centroide simple (promedio de vértices) — para la etiqueta de la zona. */
export function centroide(path) {
    if (!path.length) {
        return null;
    }
    const sum = path.reduce((a, p) => ({ lat: a.lat + Number(p.lat), lng: a.lng + Number(p.lng) }), { lat: 0, lng: 0 });

    return { lat: sum.lat / path.length, lng: sum.lng / path.length };
}

/**
 * Pin de marca del LOCAL: gota clásica naranja con el ícono BCN de la PWA
 * sobre un disco blanco. Es el pin de "acá está la tienda" (config de zonas
 * y contexto del picker); el domicilio del cliente usa el marcador rojo
 * default de Maps. Estilos inline a propósito (ganan al preflight de
 * Tailwind, que con `img { height:auto }` rompería el tamaño del ícono).
 */
export function crearPinLocal() {
    const wrap = document.createElement('div');
    wrap.style.cssText =
        'position:relative;width:40px;height:51px;' +
        'filter:drop-shadow(0 2px 3px rgba(0,0,0,.4));';

    // Cuerpo del pin: gota clásica, cabeza redonda (centro 20,17 r16) y punta en (20,50).
    wrap.innerHTML =
        '<svg width="40" height="51" viewBox="0 0 40 51" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M20 50 C14 38 4 27 4 17 A16 16 0 1 1 36 17 C36 27 26 38 20 50 Z" ' +
        'fill="#FFAF22" stroke="#ffffff" stroke-width="2"/></svg>';

    // Disco blanco para separar el ícono del cuerpo naranja.
    const disco = document.createElement('div');
    disco.style.cssText =
        'position:absolute;top:4px;left:7px;width:26px;height:26px;' +
        'border-radius:50%;background:#ffffff;box-sizing:border-box;';
    wrap.appendChild(disco);

    // Ícono BCN de la PWA, centrado dentro de la cabeza.
    const icon = document.createElement('img');
    icon.src = '/pwa-icons/icon-192x192.png';
    icon.alt = '';
    icon.style.cssText =
        'position:absolute;top:5px;left:8px;width:24px;height:24px;' +
        'border-radius:50%;object-fit:cover;display:block;';
    wrap.appendChild(icon);

    return wrap;
}

// Loader del bootstrap oficial de Google Maps — carga una sola vez por página.
// Exportado: lo reutiliza zonas-mapa.js (mapa de zonas de entrega).
let mapsPromise = null;

export function cargarGoogleMaps(key) {
    if (mapsPromise) {
        return mapsPromise;
    }

    mapsPromise = new Promise((resolve, reject) => {
        if (window.google?.maps?.importLibrary) {
            resolve(window.google.maps);

            return;
        }

        // Bootstrap loader oficial (inline, parametrizado con la key).
        ((g) => {
            let h;
            const c = 'google';
            const m = document;
            let b = window;
            b = b[c] || (b[c] = {});
            const d = b.maps || (b.maps = {});
            const r = new Set();
            const e = new URLSearchParams();
            const u = () =>
                h ||
                (h = new Promise(async (f, n) => {
                    const a = m.createElement('script');
                    e.set('libraries', [...r] + '');
                    for (const k in g) {
                        e.set(
                            k.replace(/[A-Z]/g, (t) => '_' + t[0].toLowerCase()),
                            g[k]
                        );
                    }
                    e.set('callback', c + '.maps.__ib__');
                    a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                    d.__ib__ = f;
                    a.onerror = () => (h = n(Error('Google Maps no pudo cargar')));
                    a.nonce = m.querySelector('script[nonce]')?.nonce || '';
                    m.head.append(a);
                }));
            d.importLibrary
                ? console.warn('Google Maps ya estaba cargado')
                : (d.importLibrary = (f, ...n) => r.add(f) && u().then(() => d.importLibrary(f, ...n)));
        })({ key, v: 'weekly' });

        const esperar = setInterval(() => {
            if (window.google?.maps?.importLibrary) {
                clearInterval(esperar);
                resolve(window.google.maps);
            }
        }, 50);
        setTimeout(() => {
            clearInterval(esperar);
            if (!window.google?.maps?.importLibrary) {
                reject(new Error('Timeout cargando Google Maps'));
            }
        }, 15000);
    });

    return mapsPromise;
}

/** Normaliza una posición de Maps (LatLng o LatLngLiteral) a {lat,lng} numérico. */
function aLatLng(pos) {
    if (!pos) {
        return null;
    }
    const lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
    const lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;

    return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
}

/**
 * Calle y número desde address components — SIN localidad/provincia/país
 * (nada de ", Provincia de Buenos Aires, Argentina"). Soporta las dos formas
 * de la API: Place.addressComponents ({longText}) y Geocoder ({long_name}).
 */
function direccionDesdeComponents(components) {
    if (!Array.isArray(components)) {
        return '';
    }
    const texto = (c) => c?.longText ?? c?.long_name ?? '';
    const buscar = (tipo) => components.find((c) => (c.types || []).includes(tipo));
    const calle = texto(buscar('route'));
    const numero = texto(buscar('street_number'));

    return calle ? (numero ? `${calle} ${numero}` : calle) : '';
}

// Geocoder compartido (reverse geocoding del pin) — fuera del componente para
// que Alpine no lo envuelva en un Proxy reactivo.
let geocoder = null;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('domicilioMapa', (config = {}) => ({
        key: config.key || '',
        mapId: config.mapId || 'DEMO_MAP_ID',
        txtGeoError: config.txtGeoError || '',
        // Opt-in: escribir la dirección (calle y número) en domDireccion al
        // elegir/mover el punto. Solo lo activa el modal de entrega de delivery.
        autocompletarDireccion: config.autocompletarDireccion || false,
        // Opt-in: abrir el mapa apenas se monta el componente (modal de entrega:
        // el operador abrió el modal PARA cargar la dirección, sin paso extra).
        autoAbrir: config.autoAbrir || false,
        // Opt-in: buscador de Google visible fuera del mapa (flujos de delivery).
        // Al elegir una dirección abre el mapa para confirmar dónde cayó el pin.
        conBuscador: config.conBuscador || false,

        map: null,
        marker: null,
        autocomplete: null,
        AdvancedMarkerElement: null,
        abierto: false,
        cargando: false,
        error: false,
        geoError: '',
        manual: false,
        tieneCentro: false,
        coords: null,

        // Buscador de direcciones (Places) — vive fuera del mapa: se puede
        // corregir la ubicación real sin abrir el mapa, y el SDK se carga
        // recién cuando el operador toca el buscador.
        buscadorListo: false,
        cargandoBuscador: false,
        errorBuscador: false,
        _montaje: null,

        init() {
            // La localidad acota tanto el mapa como el buscador, y el buscador
            // puede montarse SIN mapa: el watch va acá, no en construir().
            this.$wire.$watch('domLocalidadCentro', (c) => this.aplicarLocalidad(c));

            // Carga PEREZOSA por defecto: no cargamos el SDK ni construimos el
            // mapa al montar — recién al tocar "Abrir mapa" (abrir()). Así, si
            // el usuario solo edita otros datos, no hay llamada (ni costo) de
            // mapas. Con autoAbrir (modal de entrega) se abre de una.
            if (this.autoAbrir) {
                this.abrir();
            }
        },

        /** Carga el SDK y construye el mapa la primera vez; reabre si ya existe. */
        async abrir() {
            this.abierto = true;

            // Si ya estaba construido, solo re-mostramos y reacomodamos el centro
            // (estuvo display:none, conviene reencuadrar).
            if (this.map) {
                await this.$nextTick();
                const c = this.coordActual() || this.coords || this.centroLocalidad();
                if (c) {
                    this.map.setCenter(c);
                }

                return;
            }

            if (!this.key) {
                return;
            }

            this.cargando = true;
            try {
                // Esperamos a que el contenedor sea visible (tiene alto definido)
                // antes de instanciar el mapa, si no Google lo pinta gris.
                await this.$nextTick();
                await cargarGoogleMaps(this.key);
                await this.construir();
            } catch (e) {
                console.error('[domicilio-mapa]', e);
                this.error = true;
            }
            this.cargando = false;
        },

        cerrar() {
            this.abierto = false;
        },

        /**
         * Monta el buscador de Google (Places) en `autocompleteSlot`. Perezoso
         * e idempotente: lo dispara el propio buscador al tocarlo o `construir()`
         * al abrir el mapa, y las llamadas concurrentes comparten la promesa.
         */
        montarBuscador() {
            if (this._montaje) {
                return this._montaje;
            }
            if (this.autocomplete || !this.key || !this.$refs.autocompleteSlot) {
                return Promise.resolve();
            }

            this.cargandoBuscador = true;
            this.errorBuscador = false;
            this._montaje = (async () => {
                try {
                    await cargarGoogleMaps(this.key);
                    const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
                    this.crearAutocomplete(PlaceAutocompleteElement);
                    this.buscadorListo = true;
                    this.aplicarLocalidad(this.centroLocalidad());
                } catch (e) {
                    console.error('[domicilio-mapa] buscador', e);
                    this.errorBuscador = true;
                    this._montaje = null;
                }
                this.cargandoBuscador = false;
            })();

            return this._montaje;
        },

        /** Buscador listo y con el foco puesto (click en el campo señuelo). */
        async enfocarBuscador() {
            await this.montarBuscador();
            setTimeout(() => this.autocomplete?.focus?.(), 50);
        },

        /** Crea el widget de autocomplete y engancha sus eventos. */
        crearAutocomplete(PlaceAutocompleteElement) {
            this.autocomplete = new PlaceAutocompleteElement({ includedRegionCodes: ['ar'] });
            this.autocomplete.classList.add('w-full');
            this.$refs.autocompleteSlot.appendChild(this.autocomplete);
            this.autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
                // Si había un Enter pendiente (ver keydown), el widget ya
                // resolvió la selección: no dupliquemos con la primera sugerencia.
                clearTimeout(this._enterPendiente);
                await this.seleccionarPrediccion(placePrediction);
            });

            // El texto tipeado vive en el shadow DOM del widget; lo espejamos
            // desde los eventos de input (composed) para poder resolver Enter.
            this._textoBusqueda = '';
            this.autocomplete.addEventListener('input', (e) => {
                const v = e.composedPath?.()[0]?.value;
                if (typeof v === 'string') {
                    this._textoBusqueda = v;
                }
            });

            // Enter = elegir la PRIMERA sugerencia. El widget solo selecciona
            // con Enter si hay una sugerencia resaltada; si la hubo, dispara
            // gmp-select enseguida y cancela este fallback.
            this.autocomplete.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                const texto = (this._textoBusqueda || '').trim();
                if (!texto) {
                    return;
                }
                clearTimeout(this._enterPendiente);
                this._enterPendiente = setTimeout(() => this.elegirPrimeraSugerencia(texto), 250);
            });
        },

        async construir() {
            const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
                google.maps.importLibrary('maps'),
                google.maps.importLibrary('marker'),
            ]);

            const coord = this.coordActual() || this.coords;
            const centro = this.centroLocalidad();
            const local = this.contexto()?.centro || null;
            const inicio = coord || centro || local || CENTRO_AR;
            const zoom = coord ? 16 : centro ? 12 : local ? 13 : 5;

            this.map = new Map(this.$refs.mapa, {
                center: inicio,
                zoom,
                mapId: this.mapId,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                clickableIcons: false,
            });

            // Guardamos la clase para crear el marcador con map+position EN EL
            // CONSTRUCTOR cada vez (camino canónico; togglear .map sobre un
            // marcador pre-creado no lo renderiza de forma confiable).
            this.AdvancedMarkerElement = AdvancedMarkerElement;
            if (coord) {
                this.mostrarMarker(coord);
            }

            // Click en el mapa = mover el pin (UX extra, sin costo).
            this.map.addListener('click', (ev) => {
                const p = aLatLng(ev.latLng);
                if (p) {
                    this.colocar(p.lat, p.lng);
                }
            });

            // El buscador puede haberse montado antes (sin mapa): idempotente.
            await this.montarBuscador();

            if (this.autoAbrir) {
                // Buscador listo para escribir (el web component delega el foco).
                setTimeout(() => this.autocomplete?.focus?.(), 150);
            }

            this.aplicarLocalidad(this.centroLocalidad());

            this.dibujarContexto();
        },

        /**
         * Contexto de reparto de la sucursal (opcional): lo provee el host en
         * un <script type="application/json"> junto al mapa. Vive fuera del
         * x-data a propósito: un payload dinámico interpolado en x-data haría
         * que Alpine re-inicialice el componente en cada morph de Livewire.
         */
        contexto() {
            try {
                return JSON.parse(this.$refs.mapaContexto?.textContent || 'null');
            } catch {
                return null;
            }
        },

        /**
         * Dibuja el contexto (solo visual, una vez por construcción del mapa):
         * pin del LOCAL, radio general de entrega y polígonos de las zonas
         * activas con su nombre. El alcance real lo decide el backend
         * (DeliveryEnvioService::cotizar) — esto solo le muestra al operador
         * dónde cae el punto respecto del reparto.
         */
        dibujarContexto() {
            const ctx = this.contexto();
            if (!ctx) {
                return;
            }
            const mapa = window.Alpine.raw(this.map);

            if (ctx.centro) {
                new this.AdvancedMarkerElement({
                    map: mapa,
                    position: ctx.centro,
                    content: crearPinLocal(),
                    title: 'Local',
                    zIndex: 1000,
                });

                if (ctx.radioKm) {
                    new google.maps.Circle({
                        map: mapa,
                        center: ctx.centro,
                        radius: Number(ctx.radioKm) * 1000,
                        strokeColor: '#6b7280',
                        strokeOpacity: 0.7,
                        strokeWeight: 1.5,
                        fillColor: '#6b7280',
                        fillOpacity: 0.05,
                        clickable: false,
                    });
                }
            }

            // Índice sobre la lista COMPLETA (como el mapa de config): así una
            // zona conserva su color aunque haya inactivas intercaladas.
            (ctx.zonas || []).forEach((zona, i) => {
                const poligono = Array.isArray(zona.poligono) ? zona.poligono : [];
                if (poligono.length < 3 || !zona.activo) {
                    return;
                }

                const color = COLORES_ZONAS[i % COLORES_ZONAS.length];
                new google.maps.Polygon({
                    map: mapa,
                    paths: poligono.map((v) => ({ lat: Number(v.lat), lng: Number(v.lng) })),
                    strokeColor: color,
                    strokeOpacity: 0.9,
                    strokeWeight: 2,
                    fillColor: color,
                    fillOpacity: 0.1,
                    clickable: false,
                });

                const c = centroide(poligono);
                if (c) {
                    const div = document.createElement('div');
                    div.textContent = zona.nombre;
                    div.style.cssText =
                        `color:${color};font-size:11px;font-weight:700;` +
                        'background:rgba(255,255,255,.85);padding:1px 6px;border-radius:8px;' +
                        `border:1px solid ${color};white-space:nowrap;`;
                    new this.AdvancedMarkerElement({ map: mapa, position: c, content: div });
                }
            });
        },

        coordActual() {
            const lat = parseFloat(this.$wire.get('domLatitud'));
            const lng = parseFloat(this.$wire.get('domLongitud'));

            return !isNaN(lat) && !isNaN(lng) ? { lat, lng } : null;
        },

        centroLocalidad() {
            const c = this.$wire.get('domLocalidadCentro');

            return c && c.lat != null && c.lng != null ? { lat: Number(c.lat), lng: Number(c.lng) } : null;
        },

        /** Acota el autocomplete a la localidad y, si no hay punto elegido, centra ahí. */
        aplicarLocalidad(c) {
            const centro = c && c.lat != null ? { lat: Number(c.lat), lng: Number(c.lng) } : null;
            this.tieneCentro = !!centro;

            // La restricción del buscador no depende del mapa: con el mapa
            // cerrado el operador igual puede buscar la dirección correcta.
            if (this.autocomplete) {
                const d = 0.18; // ~20km alrededor del centro de la localidad
                this.autocomplete.locationRestriction = centro
                    ? {
                          north: centro.lat + d,
                          south: centro.lat - d,
                          east: centro.lng + d,
                          west: centro.lng - d,
                      }
                    : null;
            }

            if (!centro || !this.map) {
                return;
            }

            if (!this.coordActual()) {
                // Pin inicial arrastrable en el centro de la localidad (sin
                // guardar coords aún: el usuario lo arrastra o busca la dirección).
                this.mostrarMarker(centro);
                this.map.setCenter(centro);
                this.map.setZoom(12);
            }
        },

        /** Crea (o recrea) el marcador en una posición y lo muestra. */
        mostrarMarker(pos) {
            if (!pos || !this.map || !this.AdvancedMarkerElement) {
                return;
            }

            if (this.marker) {
                this.marker.map = null;
            }

            // Alpine envuelve `this.map` en un Proxy reactivo. AdvancedMarkerElement
            // hace una comparación de identidad interna contra la instancia REAL del
            // mapa para adjuntarse a su overlay; con el Proxy nunca lo hace y el pin
            // no se renderiza (isConnected=false). Pasamos el mapa crudo con Alpine.raw.
            // Sin `content`, Maps usa su marcador ROJO estándar: es el lenguaje
            // universal de "este punto" y distingue el domicilio del pin del local.
            this.marker = new this.AdvancedMarkerElement({
                map: window.Alpine.raw(this.map),
                position: pos,
                gmpDraggable: true,
                title: 'Domicilio',
            });
            this.marker.addListener('dragend', () => {
                const p = aLatLng(this.marker.position);
                if (p) {
                    this.coords = p;
                    this.push(p.lat, p.lng);
                    this.reverseYPush(p.lat, p.lng);
                }
            });

            this.coords = { lat: Number(pos.lat), lng: Number(pos.lng) };
        },

        colocar(lat, lng, zoom, direccion = null) {
            // Guardamos el punto antes de dibujar: con el mapa cerrado no hay
            // marcador que crear, y este valor es el que usa construir() cuando
            // el mapa se abre después (la prop Livewire puede no haber vuelto).
            this.coords = { lat: Number(lat), lng: Number(lng) };
            this.mostrarMarker({ lat, lng });
            if (this.map) {
                this.map.setCenter({ lat, lng });
                if (zoom) {
                    this.map.setZoom(zoom);
                }
            }
            this.push(lat, lng);
            if (direccion) {
                this.pushDireccion(direccion);
            } else {
                this.reverseYPush(lat, lng);
            }
        },

        push(lat, lng) {
            this.$wire.setCoordenadasDesdeMapa(lat, lng);
        },

        pushDireccion(texto) {
            if (this.autocompletarDireccion && texto) {
                this.$wire.setDireccionDesdeMapa(texto);
            }
        },

        /** Resuelve una predicción del autocomplete: coords + dirección al form. */
        async seleccionarPrediccion(placePrediction) {
            const place = placePrediction.toPlace();
            await place.fetchFields({
                fields: this.autocompletarDireccion ? ['location', 'addressComponents'] : ['location'],
            });
            const loc = aLatLng(place.location);
            if (loc) {
                // La predicción ya trae los componentes: evita el reverse
                // geocoding que colocar() haría sin dirección explícita.
                this.colocar(loc.lat, loc.lng, 17, direccionDesdeComponents(place.addressComponents));

                // Buscó desde el campo, sin mapa: lo abrimos para que vea dónde
                // quedó el punto (y pueda ajustarlo arrastrando el pin).
                if (this.conBuscador && !this.abierto) {
                    this.abrir();
                }
            }
        },

        /** Enter en el buscador: consulta las sugerencias del texto y toma la primera. */
        async elegirPrimeraSugerencia(texto) {
            try {
                const { AutocompleteSuggestion } = await google.maps.importLibrary('places');
                const req = { input: texto, includedRegionCodes: ['ar'] };
                if (this.autocomplete?.locationRestriction) {
                    req.locationRestriction = this.autocomplete.locationRestriction;
                }
                const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions(req);
                const prediccion = suggestions?.[0]?.placePrediction;
                if (prediccion) {
                    await this.seleccionarPrediccion(prediccion);
                }
            } catch (e) {
                console.warn('[domicilio-mapa] no se pudo resolver la primera sugerencia', e);
            }
        },

        /** Reverse geocoding del punto → calle y número al input de dirección. */
        async reverseYPush(lat, lng) {
            if (!this.autocompletarDireccion) {
                return;
            }
            try {
                if (!geocoder) {
                    const { Geocoder } = await google.maps.importLibrary('geocoding');
                    geocoder = new Geocoder();
                }
                const { results } = await geocoder.geocode({ location: { lat, lng } });
                this.pushDireccion(direccionDesdeComponents(results?.[0]?.address_components));
            } catch (e) {
                // Sin dirección legible para el punto: el input queda como está.
                console.warn('[domicilio-mapa] reverse geocoding falló', e);
            }
        },

        usarMiUbicacion() {
            this.geoError = '';
            if (!navigator.geolocation) {
                this.geoError = this.txtGeoError;

                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => this.colocar(pos.coords.latitude, pos.coords.longitude, 17),
                () => {
                    this.geoError = this.txtGeoError;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },
    }));
});
