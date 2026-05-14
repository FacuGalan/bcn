/**
 * Componente Alpine `kanbanBoard` para la vista Kanban de Pedidos por Mostrador.
 *
 * Se registra en `alpine:init` desde aca (corre antes de que Livewire intente
 * resolver el `x-data` en el DOM). El registro inline en el blade fallaba
 * porque para cuando ese script corria, Alpine ya habia disparado `alpine:init`.
 *
 * Los datos dinamicos (mapa de transiciones permitidas) vienen del DOM via
 * `data-transiciones` (JSON) en el elemento raiz del componente.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('kanbanBoard', () => ({
        transiciones: {},
        sortables: [],

        init() {
            // Leer el mapa de transiciones desde data-attribute (inyectado por Blade).
            try {
                this.transiciones = JSON.parse(this.$el.dataset.transiciones || '{}');
            } catch (e) {
                console.error('[kanban] data-transiciones inválido', e);
                this.transiciones = {};
            }

            // Esperar al proximo tick: las columnas .kanban-col estan dentro del
            // mismo bloque Blade y ya estan en el DOM, pero el x-show puede no
            // haber resuelto. nextTick las garantiza renderizadas.
            this.$nextTick(() => this.initSortables());
        },

        initSortables() {
            if (typeof window.Sortable === 'undefined') {
                console.error('[kanban] SortableJS no cargado. Ejecutar npm run build.');
                return;
            }

            // Limpiar instancias previas (re-init tras morph de Livewire).
            this.sortables.forEach((s) => s.destroy && s.destroy());
            this.sortables = [];

            const cols = this.$el.querySelectorAll('.kanban-col');
            cols.forEach((col) => {
                const sortable = window.Sortable.create(col, {
                    group: 'pedidos',
                    animation: 150,
                    draggable: '.kanban-card',
                    ghostClass: 'kanban-ghost',
                    dragClass: 'kanban-dragging',
                    forceFallback: true,
                    fallbackTolerance: 5,
                    onMove: (evt) => {
                        const origen = evt.from.dataset.estado;
                        const destino = evt.to.dataset.estado;
                        if (origen === destino) return true;
                        const permitidas = this.transiciones[origen] || [];
                        return permitidas.includes(destino);
                    },
                    onEnd: (evt) => {
                        const origen = evt.from.dataset.estado;
                        const destino = evt.to.dataset.estado;
                        if (origen === destino) return;
                        const pedidoId = parseInt(evt.item.dataset.pedidoId);
                        if (!pedidoId) return;
                        this.$wire.cambiarEstadoDrag(pedidoId, destino);
                    },
                });
                this.sortables.push(sortable);
            });
        },
    }));
});
