/**
 * Componente Alpine `tiendaPreview` (RF-T12): estado compartido del visor de
 * la tienda (iframe real embebido + mock fallback + drawer móvil) dentro de
 * ConfiguracionTienda.
 *
 * - Los 8 design tokens se copian del componente Livewire y se observan con
 *   $wire.$watch (NUNCA $wire.entangle acá: entangle devuelve un interceptor
 *   Alpine que solo se inicializa al construir el objeto x-data — asignado
 *   dentro de init() queda el objeto crudo, los watchers no disparan y
 *   postMessage lanza DataCloneError por las funciones que contiene). Cada
 *   cambio se portea al iframe por postMessage (debounce 150ms) con el MISMO
 *   shape del bloque `tema` del contrato (docs/api-v1-delivery.md; el canal
 *   preview es frontend-only, no toca la API v1).
 * - Logo/portada llegan por eventos Livewire (`tienda-preview-imagenes`)
 *   porque son URLs server-rendered (temporaryUrl del upload pendiente).
 * - `tienda-guardada` recarga el iframe reasignando src (NUNCA
 *   contentWindow.location: cross-origin lanza SecurityError).
 * - Config inicial por dataset del elemento raíz (data-origen-tienda,
 *   data-logo-url, data-portada-url): un cambio de dataset NO re-inicializa
 *   Alpine (el gotcha del morph es solo con el atributo x-data).
 * - Los getters de CSS vars --tp-* pintan el MOCK del panel; el iframe usa
 *   su propio mapeo (bcn-tienda resources/js/preview.js).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tiendaPreview', () => ({
        open: false,

        init() {
            this.origenTienda = this.$el.dataset.origenTienda || null;
            this.logoUrl = this.$el.dataset.logoUrl || null;
            this.portadaUrl = this.$el.dataset.portadaUrl || null;
            this._timerEnvio = null;

            // Copia reactiva de los tokens: wire:model actualiza $wire al
            // instante en el cliente, $wire.$watch refleja acá cada cambio
            // (también los server-side, ej. restablecerTema).
            const props = ['colorPrimario', 'colorAcento', 'colorFondo', 'colorSuperficie',
                'colorTexto', 'fuente', 'radios', 'densidad',
                // RF-T13 — tokens que también reflejan en vivo (layout del
                // catálogo/destacados/promos son server-rendered: recargan
                // al guardar, no viajan por acá).
                'portadaOverlay', 'portadaPosicion', 'slogan', 'descripcion',
                'redFacebook', 'redInstagram'];
            props.forEach((prop) => {
                this[prop] = this.$wire.get(prop);
                this.$wire.$watch(prop, (valor) => {
                    this[prop] = valor;
                    this.enviarEstadoDebounced();
                });
            });

            // El iframe avisa que está listo (al cargar y en cada navegación
            // interna): responder con el estado actual es idempotente.
            this._onMessage = (event) => {
                if (! this.origenTienda || event.origin !== this.origenTienda) return;
                if (! event.data || event.data.tipo !== 'tienda-preview-ready') return;
                this.enviarEstado();
            };
            window.addEventListener('message', this._onMessage);

            window.Livewire?.on('tienda-preview-imagenes', ({ logoUrl = null, portadaUrl = null }) => {
                this.logoUrl = logoUrl;
                this.portadaUrl = portadaUrl;
                this.enviarEstado();
            });
            window.Livewire?.on('tienda-guardada', () => this.recargarIframe());
            // RF-T14: la config por artículo guarda AL INSTANTE por acción
            // (toggle/drag/foto). Debounce largo para no recargar el iframe
            // en cada micro-cambio de una ráfaga.
            window.Livewire?.on('tienda-catalogo-cambiado', () => this.recargarIframeDebounced());
        },

        destroy() {
            window.removeEventListener('message', this._onMessage);
        },

        // ── Mock del panel (CSS vars --tp-*) ──────────────────────────────
        get radioCard() {
            return ({ none: '0px', sm: '4px', md: '8px', lg: '16px', full: '24px' })[this.radios] || '8px';
        },
        get radioBoton() {
            return this.radios === 'full' ? '9999px' : this.radioCard;
        },
        get pad() {
            return ({ compacta: '8px', normal: '12px', amplia: '16px' })[this.densidad] || '12px';
        },
        get fontStack() {
            return ({
                system: 'system-ui, sans-serif',
                inter: 'Inter, ui-sans-serif, system-ui, sans-serif',
                poppins: 'Poppins, ui-sans-serif, system-ui, sans-serif',
                roboto: 'Roboto, ui-sans-serif, system-ui, sans-serif',
                montserrat: 'Montserrat, ui-sans-serif, system-ui, sans-serif',
                lora: 'Lora, Georgia, serif',
            })[this.fuente] || 'system-ui, sans-serif';
        },
        get cssVars() {
            return '--tp-primario:' + this.colorPrimario + ';--tp-acento:' + this.colorAcento
                + ';--tp-fondo:' + this.colorFondo + ';--tp-superficie:' + this.colorSuperficie
                + ';--tp-texto:' + this.colorTexto + ';--tp-radio:' + this.radioCard
                + ';--tp-radio-boton:' + this.radioBoton + ';--tp-pad:' + this.pad
                + ';--tp-font:' + this.fontStack;
        },

        // ── Canal hacia el iframe de la tienda real ───────────────────────
        enviarEstado() {
            const iframe = this.$refs.iframe;
            if (! iframe || ! this.origenTienda) return;

            iframe.contentWindow?.postMessage({
                tipo: 'tienda-preview-estado',
                tema: {
                    colores: {
                        primario: this.colorPrimario,
                        acento: this.colorAcento,
                        fondo: this.colorFondo,
                        superficie: this.colorSuperficie,
                        texto: this.colorTexto,
                    },
                    tipografia: { fuente: this.fuente },
                    radios: this.radios,
                    densidad: this.densidad,
                    portada: { overlay: this.portadaOverlay, posicion: this.portadaPosicion },
                    textos: { slogan: this.slogan, descripcion: this.descripcion },
                    redes: { facebook: this.redFacebook, instagram: this.redInstagram },
                },
                logoUrl: this.logoUrl,
                portadaUrl: this.portadaUrl,
            }, this.origenTienda);
        },

        enviarEstadoDebounced() {
            clearTimeout(this._timerEnvio);
            this._timerEnvio = setTimeout(() => this.enviarEstado(), 150);
        },

        recargarIframe() {
            const iframe = this.$refs.iframe;
            if (iframe) iframe.src = iframe.src;
        },

        recargarIframeDebounced() {
            clearTimeout(this._timerReload);
            this._timerReload = setTimeout(() => this.recargarIframe(), 1200);
        },
    }));
});
