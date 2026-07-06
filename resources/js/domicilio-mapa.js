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

// Loader del bootstrap oficial de Google Maps — carga una sola vez por página.
let mapsPromise = null;

function cargarGoogleMaps(key) {
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

        init() {
            // Carga PEREZOSA: no cargamos el SDK ni construimos el mapa al montar.
            // Recién al tocar "Abrir mapa" (abrir()) se llama a la API de Google.
            // Así, si el usuario solo edita otros datos de la sucursal, no se hace
            // ninguna llamada (ni costo) de mapas.
        },

        /** Carga el SDK y construye el mapa la primera vez; reabre si ya existe. */
        async abrir() {
            this.abierto = true;

            // Si ya estaba construido, solo re-mostramos y reacomodamos el centro
            // (estuvo display:none, conviene reencuadrar).
            if (this.map) {
                await this.$nextTick();
                const c = this.coordActual() || this.centroLocalidad();
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

        async construir() {
            const [{ Map }, { AdvancedMarkerElement }, { PlaceAutocompleteElement }] = await Promise.all([
                google.maps.importLibrary('maps'),
                google.maps.importLibrary('marker'),
                google.maps.importLibrary('places'),
            ]);

            const coord = this.coordActual();
            const centro = this.centroLocalidad();
            const inicio = coord || centro || CENTRO_AR;
            const zoom = coord ? 16 : centro ? 12 : 5;

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

            this.autocomplete = new PlaceAutocompleteElement({ includedRegionCodes: ['ar'] });
            this.autocomplete.classList.add('w-full');
            this.$refs.autocompleteSlot.appendChild(this.autocomplete);
            this.autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
                const place = placePrediction.toPlace();
                await place.fetchFields({
                    fields: this.autocompletarDireccion ? ['location', 'addressComponents'] : ['location'],
                });
                const loc = aLatLng(place.location);
                if (loc) {
                    // La predicción ya trae los componentes: evita el reverse
                    // geocoding que colocar() haría sin dirección explícita.
                    this.colocar(loc.lat, loc.lng, 17, direccionDesdeComponents(place.addressComponents));
                }
            });

            this.aplicarLocalidad(this.centroLocalidad());
            this.$wire.$watch('domLocalidadCentro', (c) => this.aplicarLocalidad(c));
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

            if (!centro || !this.map) {
                if (this.autocomplete) {
                    this.autocomplete.locationRestriction = null;
                }

                return;
            }

            const d = 0.18; // ~20km alrededor del centro de la localidad
            if (this.autocomplete) {
                this.autocomplete.locationRestriction = {
                    north: centro.lat + d,
                    south: centro.lat - d,
                    east: centro.lng + d,
                    west: centro.lng - d,
                };
            }

            if (!this.coordActual()) {
                // Pin inicial arrastrable en el centro de la localidad (sin
                // guardar coords aún: el usuario lo arrastra o busca la dirección).
                this.mostrarMarker(centro);
                this.map.setCenter(centro);
                this.map.setZoom(12);
            }
        },

        /**
         * Pin de marca con forma clásica de marcador (globo + punta) en naranja, con
         * el ícono BCN de la PWA chico adentro, sobre un disco blanco para que
         * contraste. El cuerpo es un SVG (forma de gota precisa); la punta del SVG
         * cae en el bottom-center del contenido, que es donde AdvancedMarkerElement
         * ancla la posición geográfica. Estilos inline a propósito (ganan al preflight
         * de Tailwind, que con `img { height:auto }` rompería el tamaño del ícono).
         */
        crearPin() {
            const wrap = document.createElement('div');
            wrap.style.cssText =
                'position:relative;width:40px;height:51px;cursor:grab;' +
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
            this.marker = new this.AdvancedMarkerElement({
                map: window.Alpine.raw(this.map),
                position: pos,
                gmpDraggable: true,
                content: this.crearPin(),
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
