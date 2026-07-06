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
 * Eventos Livewire (escuchados con x-on:*.window en el blade):
 *  - zona-dibujo-iniciar {poligono}  → entra en modo dibujo con ese path.
 *  - zona-dibujo-fin                 → sale del modo dibujo.
 *  - zonas-actualizadas {zonas, radioKm, centro} → redibuja overlays.
 */

import { cargarGoogleMaps } from './domicilio-mapa.js';

const CENTRO_AR = { lat: -38.4161, lng: -63.6167 };

// Paleta de zonas (borde/relleno). Se cicla por índice.
const COLORES = ['#0891b2', '#d97706', '#7c3aed', '#dc2626', '#059669', '#db2777', '#2563eb', '#65a30d'];

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

        map: null,
        cargando: false,
        error: false,
        editando: false,
        zonaEditandoId: null,

        // Fuera del estado reactivo de Alpine (los objetos de Maps no toleran
        // el Proxy — mismo gotcha que el marker del domicilio): se guardan en
        // propiedades planas del closure vía Alpine.raw al usarlos.
        _overlays: [],
        _labels: [],
        _circulo: null,
        _dibujo: null,

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

            this.map = new Map(this.$refs.mapa, {
                center: this.centro || CENTRO_AR,
                zoom: this.centro ? 13 : 5,
                mapId: this.mapId,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                clickableIcons: false,
            });

            // Click en modo dibujo = agregar vértice al polígono en curso.
            this.map.addListener('click', (ev) => {
                if (this.editando && this._dibujo) {
                    window.Alpine.raw(this._dibujo).getPath().push(ev.latLng);
                }
            });

            this.redibujar();
        },

        mapa() {
            return window.Alpine.raw(this.map);
        },

        /** Redibuja círculo del radio general + polígonos de zonas (sin la editada). */
        redibujar() {
            if (!this.map) {
                return;
            }

            this._overlays.forEach((o) => o.setMap(null));
            this._labels.forEach((m) => (m.map = null));
            this._overlays = [];
            this._labels = [];
            if (this._circulo) {
                this._circulo.setMap(null);
                this._circulo = null;
            }

            // Radio general (referencia, rige solo sin zonas dibujadas).
            if (this.centro && this.radioKm) {
                this._circulo = new google.maps.Circle({
                    map: this.mapa(),
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
                if (!Array.isArray(zona.poligono) || zona.poligono.length < 3) {
                    return;
                }
                if (this.editando && zona.id === this.zonaEditandoId) {
                    return; // la editada se dibuja aparte (editable)
                }

                const color = COLORES[i % COLORES.length];
                const poly = new google.maps.Polygon({
                    map: this.mapa(),
                    paths: zona.poligono,
                    strokeColor: color,
                    strokeOpacity: zona.activo ? 0.9 : 0.4,
                    strokeWeight: 2,
                    fillColor: color,
                    fillOpacity: zona.activo ? 0.14 : 0.05,
                    clickable: false,
                });
                this._overlays.push(poly);

                const c = centroide(zona.poligono);
                if (c) {
                    const div = document.createElement('div');
                    div.textContent = zona.nombre;
                    div.style.cssText =
                        `color:${color};font-size:11px;font-weight:700;` +
                        'background:rgba(255,255,255,.85);padding:1px 6px;border-radius:8px;' +
                        `border:1px solid ${color};white-space:nowrap;` +
                        (zona.activo ? '' : 'opacity:.5;');
                    const label = new google.maps.marker.AdvancedMarkerElement({
                        map: this.mapa(),
                        position: c,
                        content: div,
                    });
                    this._labels.push(label);
                }
            });
        },

        /** Entra en modo dibujo con el path dado (edición) o vacío (alta). */
        iniciarDibujo(detail = {}) {
            if (!this.map) {
                return;
            }

            this.terminarDibujo();
            this.editando = true;
            this.zonaEditandoId = detail.zonaId ?? null;

            const path = Array.isArray(detail.poligono) ? detail.poligono : [];

            this._dibujo = new google.maps.Polygon({
                map: this.mapa(),
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
            this._dibujo.addListener('rightclick', (ev) => {
                if (ev.vertex != null) {
                    window.Alpine.raw(this._dibujo).getPath().removeAt(ev.vertex);
                }
            });

            this.observarPath();
            this.redibujar();

            const c = centroide(path);
            if (c) {
                this.mapa().setCenter(c);
            }
        },

        /** Reconecta los listeners del MVCArray y empuja el path a Livewire. */
        observarPath() {
            const dibujo = window.Alpine.raw(this._dibujo);
            const mvc = dibujo.getPath();
            ['insert_at', 'remove_at', 'set_at'].forEach((ev) => mvc.addListener(ev, () => this.pushPath()));
            this.pushPath();
        },

        pushPath() {
            const dibujo = this._dibujo ? window.Alpine.raw(this._dibujo) : null;
            const path = dibujo ? dibujo.getPath().getArray().map(aLatLng) : [];
            // Deferred: viaja con el próximo request (guardarZona).
            this.$wire.set('zonaPoligono', path, false);
            this.vertices = path.length;
        },

        vertices: 0,

        /** Botón "Rehacer dibujo": vacía el path (los clicks vuelven a sumar). */
        rehacerDibujo() {
            if (this._dibujo) {
                window.Alpine.raw(this._dibujo).getPath().clear();
            }
        },

        terminarDibujo() {
            if (this._dibujo) {
                window.Alpine.raw(this._dibujo).setMap(null);
                this._dibujo = null;
            }
            this.editando = false;
            this.zonaEditandoId = null;
            this.vertices = 0;
        },

        /** Evento zonas-actualizadas: payload nuevo desde Livewire. */
        actualizarZonas(detail = {}) {
            this.zonas = detail.zonas ?? this.zonas;
            this.radioKm = detail.radioKm !== undefined ? detail.radioKm : this.radioKm;
            this.centro = detail.centro !== undefined ? detail.centro : this.centro;
            this.redibujar();
        },
    }));
});
