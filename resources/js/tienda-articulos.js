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
});
