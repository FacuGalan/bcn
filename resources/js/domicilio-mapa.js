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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('domicilioMapa', (config = {}) => ({
        key: config.key || '',
        mapId: config.mapId || 'DEMO_MAP_ID',
        txtGeoError: config.txtGeoError || '',

        map: null,
        marker: null,
        autocomplete: null,
        AdvancedMarkerElement: null,
        cargando: true,
        error: false,
        geoError: '',
        manual: false,
        tieneCentro: false,
        coords: null,

        async init() {
            if (!this.key) {
                this.cargando = false;

                return;
            }

            try {
                await cargarGoogleMaps(this.key);
                await this.construir();
            } catch (e) {
                console.error('[domicilio-mapa]', e);
                this.error = true;
            }

            this.cargando = false;
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
            const zoom = coord ? 16 : centro ? 13 : 5;

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
                await place.fetchFields({ fields: ['location'] });
                const loc = aLatLng(place.location);
                if (loc) {
                    this.colocar(loc.lat, loc.lng, 17);
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
                this.map.setZoom(13);
            }
        },

        /** Pin con estilos inline (inmune al reset CSS global). */
        crearPin() {
            const el = document.createElement('div');
            el.style.cssText =
                'width:22px;height:22px;border-radius:50% 50% 50% 0;' +
                'background:#2563eb;border:2px solid #ffffff;' +
                'box-shadow:0 1px 4px rgba(0,0,0,.5);transform:rotate(-45deg);' +
                'transform-origin:center;cursor:grab;box-sizing:border-box;';

            return el;
        },

        /** Crea (o recrea) el marcador en una posición y lo muestra. */
        mostrarMarker(pos) {
            if (!pos || !this.map || !this.AdvancedMarkerElement) {
                return;
            }

            if (this.marker) {
                this.marker.map = null;
            }

            this.marker = new this.AdvancedMarkerElement({
                map: this.map,
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
                }
            });

            this.coords = { lat: Number(pos.lat), lng: Number(pos.lng) };
        },

        colocar(lat, lng, zoom) {
            this.mostrarMarker({ lat, lng });
            if (this.map) {
                this.map.setCenter({ lat, lng });
                if (zoom) {
                    this.map.setZoom(zoom);
                }
            }
            this.push(lat, lng);
        },

        push(lat, lng) {
            this.$wire.setCoordenadasDesdeMapa(lat, lng);
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
