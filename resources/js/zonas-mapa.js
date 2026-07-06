/**
 * Componente Alpine `zonasMapa` — mapa ÚNICO de "costo de envío y alcance"
 * de la config de delivery (RF-06, zonas = polígonos).
 *
 * Siempre visible (la sección solo se renderiza con georreferenciación ON):
 * dibuja el radio general de entrega (círculo punteado desde la sucursal) y
 * TODOS los polígonos de zonas con color propio + etiqueta, para que al
 * dibujar una zona nueva se vean las vecinas (suelen estar pegadas
 * compartiendo límites).
 *
 * Modo dibujo (alta/edición de zona): cada click en el mapa agrega un
 * vértice; el polígono es editable (arrastrar vértices, arrastrar el punto
 * medio agrega, click derecho sobre un vértice lo quita). El path se empuja
 * DEFERRED a la prop Livewire `zonaPoligono` (viaja recién al guardar).
 *
 * GOTCHA (aprendido con el marker del domicilio, acá es PEOR): los objetos
 * de Google Maps NO pueden vivir en el estado reactivo de Alpine — el Proxy
 * rompe la identidad interna (Polygon.getPath() devuelve undefined, los
 * listeners del MVCArray no cuelgan). TODOS los objetos de Maps viven en un
 * WeakMap a nivel módulo keyed por el elemento raíz del componente; en el
 * estado Alpine solo queda data plana (flags, contadores, payload de zonas).
 *
 * Eventos Livewire (escuchados con x-on:*.window en el blade):
 *  - zona-dibujo-iniciar {poligono, zonaId} → entra en modo dibujo.
 *  - zona-dibujo-fin                        → sale del modo dibujo.
 *  - zonas-actualizadas {zonas, radioKm, centro} → redibuja overlays.
 */

import { cargarGoogleMaps } from './domicilio-mapa.js';

const CENTRO_AR = { lat: -38.4161, lng: -63.6167 };

// Paleta de zonas (borde/relleno). Se cicla por índice.
const COLORES = ['#0891b2', '#d97706', '#7c3aed', '#dc2626', '#059669', '#db2777', '#2563eb', '#65a30d'];

// Objetos de Google Maps por componente, FUERA del Proxy de Alpine.
const STORES = new WeakMap();

function aLatLng(pos) {
    const lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
    const lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;

    return { lat: Number(lat), lng: Number(lng) };
}

function centroide(path) {
    if (!path.length) {
        return null;
    }
    const sum = path.reduce((a, p) => ({ lat: a.lat + p.lat, lng: a.lng + p.lng }), { lat: 0, lng: 0 });

    return { lat: sum.lat / path.length, lng: sum.lng / path.length };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('zonasMapa', (config = {}) => ({
        key: config.key || '',
        mapId: config.mapId || 'DEMO_MAP_ID',
        centro: config.centro || null, // sucursal {lat,lng} (null = sin georreferenciar)
        radioKm: config.radioKm ?? null,
        zonas: config.zonas || [], // [{id, nombre, poligono, activo}]

        cargando: false,
        error: false,
        editando: false,
        zonaEditandoId: null,
        vertices: 0,

        /** Objetos de Maps del componente (no reactivos). */
        st() {
            let s = STORES.get(this.$root);
            if (!s) {
                s = { map: null, overlays: [], labels: [], circulo: null, dibujo: null, sucursalMarker: null };
                STORES.set(this.$root, s);
            }

            return s;
        },

        async init() {
            if (!this.key) {
                this.error = true;

                return;
            }

            this.cargando = true;
            try {
                await cargarGoogleMaps(this.key);
                await this.construir();
            } catch (e) {
                console.error('[zonas-mapa]', e);
                this.error = true;
            }
            this.cargando = false;
        },

        async construir() {
            const [{ Map }] = await Promise.all([
                google.maps.importLibrary('maps'),
                google.maps.importLibrary('marker'),
            ]);

            const s = this.st();
            s.map = new Map(this.$refs.mapa, {
                center: this.centro || CENTRO_AR,
                zoom: this.centro ? 13 : 5,
                mapId: this.mapId,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                clickableIcons: false,
            });

            // Click en modo dibujo = agregar vértice al polígono en curso.
            s.map.addListener('click', (ev) => {
                const st = this.st();
                if (this.editando && st.dibujo) {
                    st.dibujo.getPath().push(ev.latLng);
                }
            });

            this.marcarSucursal();
            this.redibujar();
        },

        /**
         * Pin del LOCAL de la sucursal, SIEMPRE visible (referencia para
         * dibujar las zonas). Mismo estilo del pin del domicilio (gota
         * naranja) con el ícono BCN adentro. No participa del redibujado de
         * overlays: se crea una vez y solo se reposiciona si cambia el centro.
         */
        marcarSucursal() {
            const s = this.st();
            if (!s.map || !this.centro) {
                return;
            }

            if (s.sucursalMarker) {
                s.sucursalMarker.position = this.centro;

                return;
            }

            const wrap = document.createElement('div');
            wrap.style.cssText =
                'position:relative;width:40px;height:51px;' +
                'filter:drop-shadow(0 2px 3px rgba(0,0,0,.4));';
            wrap.innerHTML =
                '<svg width="40" height="51" viewBox="0 0 40 51" xmlns="http://www.w3.org/2000/svg">' +
                '<path d="M20 50 C14 38 4 27 4 17 A16 16 0 1 1 36 17 C36 27 26 38 20 50 Z" ' +
                'fill="#FFAF22" stroke="#ffffff" stroke-width="2"/></svg>';
            const disco = document.createElement('div');
            disco.style.cssText =
                'position:absolute;top:4px;left:7px;width:26px;height:26px;' +
                'border-radius:50%;background:#ffffff;box-sizing:border-box;';
            wrap.appendChild(disco);
            const icon = document.createElement('img');
            icon.src = '/pwa-icons/icon-192x192.png';
            icon.alt = '';
            icon.style.cssText =
                'position:absolute;top:5px;left:8px;width:24px;height:24px;' +
                'border-radius:50%;object-fit:cover;display:block;';
            wrap.appendChild(icon);

            s.sucursalMarker = new google.maps.marker.AdvancedMarkerElement({
                map: s.map,
                position: this.centro,
                content: wrap,
                title: 'Sucursal',
                zIndex: 1000,
            });
        },

        /** Redibuja círculo del radio general + polígonos de zonas (sin la editada). */
        redibujar() {
            const s = this.st();
            if (!s.map) {
                return;
            }

            s.overlays.forEach((o) => o.setMap(null));
            s.labels.forEach((m) => (m.map = null));
            s.overlays = [];
            s.labels = [];
            if (s.circulo) {
                s.circulo.setMap(null);
                s.circulo = null;
            }

            // Radio general (referencia, rige solo sin zonas dibujadas).
            if (this.centro && this.radioKm) {
                s.circulo = new google.maps.Circle({
                    map: s.map,
                    center: this.centro,
                    radius: Number(this.radioKm) * 1000,
                    strokeColor: '#6b7280',
                    strokeOpacity: 0.7,
                    strokeWeight: 1.5,
                    fillColor: '#6b7280',
                    fillOpacity: 0.05,
                    clickable: false,
                });
            }

            this.zonas.forEach((zona, i) => {
                const poligono = Array.isArray(zona.poligono) ? zona.poligono : [];
                if (poligono.length < 3) {
                    return;
                }
                if (this.editando && zona.id === this.zonaEditandoId) {
                    return; // la editada se dibuja aparte (editable)
                }

                const color = COLORES[i % COLORES.length];
                s.overlays.push(new google.maps.Polygon({
                    map: s.map,
                    paths: poligono.map((v) => ({ lat: Number(v.lat), lng: Number(v.lng) })),
                    strokeColor: color,
                    strokeOpacity: zona.activo ? 0.9 : 0.4,
                    strokeWeight: 2,
                    fillColor: color,
                    fillOpacity: zona.activo ? 0.14 : 0.05,
                    clickable: false,
                }));

                const c = centroide(poligono);
                if (c) {
                    const div = document.createElement('div');
                    div.textContent = zona.nombre;
                    div.style.cssText =
                        `color:${color};font-size:11px;font-weight:700;` +
                        'background:rgba(255,255,255,.85);padding:1px 6px;border-radius:8px;' +
                        `border:1px solid ${color};white-space:nowrap;` +
                        (zona.activo ? '' : 'opacity:.5;');
                    s.labels.push(new google.maps.marker.AdvancedMarkerElement({
                        map: s.map,
                        position: c,
                        content: div,
                    }));
                }
            });
        },

        /** Entra en modo dibujo con el path dado (edición) o vacío (alta). */
        iniciarDibujo(detail = {}) {
            const s = this.st();
            if (!s.map) {
                return;
            }

            this.terminarDibujo();
            this.editando = true;
            this.zonaEditandoId = detail.zonaId ?? null;

            const path = (Array.isArray(detail.poligono) ? detail.poligono : [])
                .map((v) => ({ lat: Number(v.lat), lng: Number(v.lng) }));

            s.dibujo = new google.maps.Polygon({
                map: s.map,
                paths: path,
                strokeColor: '#0e7490',
                strokeOpacity: 1,
                strokeWeight: 2.5,
                fillColor: '#06b6d4',
                fillOpacity: 0.2,
                editable: true,
                clickable: true,
            });

            // Click derecho sobre un vértice: quitarlo.
            s.dibujo.addListener('rightclick', (ev) => {
                if (ev.vertex != null) {
                    this.st().dibujo?.getPath().removeAt(ev.vertex);
                }
            });

            this.observarPath();
            this.redibujar();

            const c = centroide(path);
            if (c) {
                s.map.setCenter(c);
            }
        },

        /** Conecta los listeners del MVCArray y empuja el path a Livewire. */
        observarPath() {
            const mvc = this.st().dibujo.getPath();
            ['insert_at', 'remove_at', 'set_at'].forEach((ev) => mvc.addListener(ev, () => this.pushPath()));
            this.pushPath();
        },

        pushPath() {
            const dibujo = this.st().dibujo;
            const path = dibujo ? dibujo.getPath().getArray().map(aLatLng) : [];
            // Deferred: viaja con el próximo request (guardarZona).
            this.$wire.set('zonaPoligono', path, false);
            this.vertices = path.length;
        },

        /** Botón "Rehacer dibujo": vacía el path (los clicks vuelven a sumar). */
        rehacerDibujo() {
            this.st().dibujo?.getPath().clear();
        },

        terminarDibujo() {
            const s = this.st();
            if (s.dibujo) {
                s.dibujo.setMap(null);
                s.dibujo = null;
            }
            this.editando = false;
            this.zonaEditandoId = null;
            this.vertices = 0;
            this.redibujar();
        },

        /** Evento zonas-actualizadas: payload nuevo desde Livewire. */
        actualizarZonas(detail = {}) {
            this.zonas = detail.zonas ?? this.zonas;
            this.radioKm = detail.radioKm !== undefined ? detail.radioKm : this.radioKm;
            this.centro = detail.centro !== undefined ? detail.centro : this.centro;
            this.marcarSucursal();
            this.redibujar();
        },
    }));
});
