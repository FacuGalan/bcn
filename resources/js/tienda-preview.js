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

        // RF-T22: modo de color FORZADO en el visor (null = auto del
        // dispositivo). El toggle sol/luna lo alterna y viaja en el estado
        // del preview — la tienda setea data-modo en :root.
        modoVisor: null,

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
                // al guardar, no viajan por acá). RF-T25: overlay eliminado
                // (portada siempre cruda) y logoRadio nuevo.
                'portadaPosicion', 'logoRadio', 'slogan', 'descripcion',
                'redFacebook', 'redInstagram',
                // RF-T38: color del adorno de destacados (var CSS en vivo;
                // modo/adorno/título siguen siendo server-rendered).
                'destacadosColorPropio', 'destacadosColor'];
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
            // (toggle/drag/foto). En vez de recargar el iframe (flash blanco
            // + lento), se le pide a la tienda un re-render por Livewire
            // (morph): preview.js hace $refresh y el catálogo llega fresco
            // (el guardado ya invalidó el cache del core y el preview
            // saltea el local). Debounce corto para agrupar ráfagas.
            window.Livewire?.on('tienda-catalogo-cambiado', () => this.refrescarCatalogoDebounced());
        },

        destroy() {
            window.removeEventListener('message', this._onMessage);
            this._historiasSortable?.destroy();
        },

        // RF-T34: drag & drop de historias persistidas (espejo de
        // initFotosSortable de tienda-articulos.js). Los ids son strings —
        // no hay parseInt. El contenedor entra/sale del DOM con el morph:
        // x-init lo registra en cada aparición y se destruye el anterior.
        initHistoriasSortable(el) {
            if (typeof window.Sortable === 'undefined') return;

            this._historiasSortable?.destroy();
            this._historiasSortable = window.Sortable.create(el, {
                animation: 150,
                draggable: '[data-historia-id]',
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const ids = Array.from(el.querySelectorAll('[data-historia-id]'))
                        .map((n) => n.dataset.historiaId)
                        .filter(Boolean);
                    if (ids.length) this.$wire.reordenarHistorias(ids);
                },
            });
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
                    portada: { overlay: false, posicion: this.portadaPosicion },
                    logo: { radio: this.logoRadio },
                    textos: { slogan: this.slogan, descripcion: this.descripcion },
                    redes: { facebook: this.redFacebook, instagram: this.redInstagram },
                    // RF-T38: '' = la tienda cae al primario del tema.
                    destacados: { color: this.destacadosColorPropio ? this.destacadosColor : '' },
                },
                logoUrl: this.logoUrl,
                portadaUrl: this.portadaUrl,
                // RF-T22: modo forzado del visor (null = auto).
                modo: this.modoVisor,
            }, this.origenTienda);
        },

        // RF-T22: alterna claro/oscuro en el iframe (primer click = oscuro).
        toggleModoVisor() {
            this.modoVisor = this.modoVisor === 'oscuro' ? 'claro' : 'oscuro';
            this.enviarEstado();
        },

        enviarEstadoDebounced() {
            clearTimeout(this._timerEnvio);
            this._timerEnvio = setTimeout(() => this.enviarEstado(), 150);
        },

        recargarIframe() {
            const iframe = this.$refs.iframe;
            if (iframe) iframe.src = iframe.src;
        },

        refrescarCatalogo() {
            const iframe = this.$refs.iframe;
            if (! iframe || ! this.origenTienda) return;

            iframe.contentWindow?.postMessage(
                { tipo: 'tienda-preview-refrescar-catalogo' },
                this.origenTienda,
            );
        },

        refrescarCatalogoDebounced() {
            clearTimeout(this._timerReload);
            this._timerReload = setTimeout(() => this.refrescarCatalogo(), 400);
        },
    }));
});
