/**
 * Componente Alpine `tiendaArticulos` (RF-T14): drag & drop de la sección
 * "Artículos de la tienda" en ConfiguracionTiendaArticulos.
 *
 * Tres niveles de Sortable (SortableJS global via bootstrap.js, mismo patrón
 * que kanban.js):
 *  - categorías ([data-sortable-categorias], handle ⠿ de categoría)
 *  - artículos dentro de cada categoría ([data-sortable-articulos], sin
 *    group compartido: NO se cruzan artículos entre categorías)
 *  - fotos de la galería del editor abierto ([data-sortable-fotos], se
 *    inicializa por x-init porque el editor entra al DOM al abrirse)
 *
 * Cada drop llama al método Livewire correspondiente, que persiste AL
 * INSTANTE, invalida el cache del catálogo y dispara la recarga debounced
 * del visor. Los contenedores de categorías/artículos sobreviven a los
 * morphs (wire:key), así las instancias creadas en init() siguen vivas.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tiendaArticulos', () => ({
        sortables: [],

        init() {
            this.$nextTick(() => this.initSortables());
        },

        destroy() {
            this.sortables.forEach((s) => s.destroy && s.destroy());
            this.sortables = [];
        },

        initSortables() {
            if (typeof window.Sortable === 'undefined') {
                console.error('[tienda-articulos] SortableJS no cargado. Ejecutar npm run build.');
                return;
            }

            const contCategorias = this.$el.querySelector('[data-sortable-categorias]');
            if (contCategorias) {
                this.sortables.push(window.Sortable.create(contCategorias, {
                    animation: 150,
                    draggable: '[data-categoria-id]',
                    handle: '[data-drag-handle-categoria]',
                    onEnd: (evt) => {
                        if (evt.oldIndex === evt.newIndex) return;
                        const ids = Array.from(contCategorias.querySelectorAll('[data-categoria-id]'))
                            .map((el) => parseInt(el.dataset.categoriaId))
                            .filter((id) => !isNaN(id) && id !== 0); // "Sin categoría" no se persiste
                        if (ids.length) this.$wire.reordenarCategorias(ids);
                    },
                }));
            }

            this.$el.querySelectorAll('[data-sortable-articulos]').forEach((cont) => {
                this.sortables.push(window.Sortable.create(cont, {
                    animation: 150,
                    draggable: '[data-articulo-id]',
                    handle: '[data-drag-handle-articulo]',
                    onEnd: (evt) => {
                        if (evt.oldIndex === evt.newIndex) return;
                        const ids = Array.from(cont.querySelectorAll('[data-articulo-id]'))
                            .map((el) => parseInt(el.dataset.articuloId))
                            .filter((id) => !isNaN(id));
                        if (ids.length) this.$wire.reordenarArticulos(ids);
                    },
                }));
            });
        },

        // El editor (galería) entra al DOM al abrirse: x-init la registra acá.
        initFotosSortable(el) {
            if (typeof window.Sortable === 'undefined') return;

            this.sortables.push(window.Sortable.create(el, {
                animation: 150,
                draggable: '[data-foto-id]',
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const ids = Array.from(el.querySelectorAll('[data-foto-id]'))
                        .map((n) => parseInt(n.dataset.fotoId))
                        .filter((id) => !isNaN(id));
                    if (ids.length) this.$wire.reordenarFotos(ids);
                },
            }));
        },
    }));

    /**
     * Selector de encuadre del banner de categoría (RF-T62): recortador
     * drag & drop. Se muestra la imagen COMPLETA y una franja (la ventana
     * de recorte, en la proporción representativa del banner en mobile)
     * se arrastra para elegir qué parte se ve. El mapeo franja →
     * object-position es exacto: top% de la franja = focalY% del espacio
     * libre (alto - franja), que es cómo object-position reparte el
     * recorte con object-cover. Persiste al soltar (pointerup) vía
     * $wire.guardarFocalBanner. Los valores iniciales viajan por dataset
     * del elemento raíz (x-data literal — gotcha de morph).
     */
    window.Alpine.data('bannerFocal', () => ({
        catId: 0,
        fx: 50,
        fy: 50,
        aspecto: 0, // w/h de la imagen; 0 hasta que carga
        arrastrando: false,

        // Proporción de la franja del banner en mobile (~390/64).
        STRIP: 6,

        init() {
            // OJO: $el solo es el raíz DENTRO de init(); en handlers de
            // hijos apunta al hijo. Por eso todo se captura acá.
            this.catId = Number(this.$el.dataset.catId) || 0;
            this.fx = Number(this.$el.dataset.fx) || 50;
            this.fy = Number(this.$el.dataset.fy) || 50;

            // $refs de hijos aún no existe durante init(); y con la imagen
            // cacheada el @load ya pasó. $nextTick cubre ambos casos.
            this.$nextTick(() => this.medir());
        },

        medir() {
            const img = this.$refs.img;
            if (img && img.naturalWidth > 0) {
                this.aspecto = img.naturalWidth / img.naturalHeight;
            }
        },

        // Foto más "alta" que la franja ⇒ el recorte es vertical (lo usual);
        // una panorámica extrema recorta horizontal.
        get vertical() {
            return this.aspecto > 0 && this.aspecto <= this.STRIP;
        },
        get bandaAltoPct() {
            return this.vertical ? (this.aspecto / this.STRIP) * 100 : 100;
        },
        get bandaAnchoPct() {
            return this.vertical ? 100 : (this.STRIP / this.aspecto) * 100;
        },

        bandaStyle() {
            const top = this.vertical ? (this.fy / 100) * (100 - this.bandaAltoPct) : 0;
            const left = this.vertical ? 0 : (this.fx / 100) * (100 - this.bandaAnchoPct);
            return `top: ${top}%; left: ${left}%; width: ${this.bandaAnchoPct}%; height: ${this.bandaAltoPct}%;`;
        },

        empezar(e) {
            if (!this.aspecto) return;
            this.arrastrando = true;
            this.mover(e);
        },

        mover(e) {
            if (!this.arrastrando || !this.aspecto) return;

            const rect = this.$refs.img.getBoundingClientRect();

            if (this.vertical) {
                const centro = ((e.clientY - rect.top) / rect.height) * 100;
                const libre = 100 - this.bandaAltoPct;
                this.fy = libre > 0
                    ? Math.max(0, Math.min(100, ((centro - this.bandaAltoPct / 2) / libre) * 100))
                    : 50;
            } else {
                const centro = ((e.clientX - rect.left) / rect.width) * 100;
                const libre = 100 - this.bandaAnchoPct;
                this.fx = libre > 0
                    ? Math.max(0, Math.min(100, ((centro - this.bandaAnchoPct / 2) / libre) * 100))
                    : 50;
            }
        },

        soltar() {
            if (!this.arrastrando) return;
            this.arrastrando = false;
            this.$wire.guardarFocalBanner(
                this.catId,
                Math.round(this.fx * 100) / 100,
                Math.round(this.fy * 100) / 100,
            );
        },
    }));
});
