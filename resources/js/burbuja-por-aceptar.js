/**
 * Componente Alpine `burbujaPorAceptar` — burbuja flotante de pedidos por
 * aceptar del panel de delivery (spec delivery-burbuja-y-mapa, RF-01/02/03).
 *
 * Cerrada muestra SOLO la cantidad; al clickearla se expande un panel de
 * media pantalla que se despliega DESDE el borde donde está anclada. Con la
 * burbuja cerrada se puede arrastrar (estilo widget mobile, acá en desktop):
 * al soltar se ancla al borde más cercano y la posición (borde + fracción a
 * lo largo del borde) se persiste en localStorage por comercio+sucursal.
 *
 * Morph-safety: el x-data es ESTÁTICO (solo la clave de storage, que cambia
 * únicamente al cambiar de sucursal); la cantidad y la lista las morphea
 * Livewire adentro sin re-inicializar Alpine. La posición se aplica con
 * :style desde el estado, que sobrevive a los morphs.
 *
 * El click se distingue del arrastre por umbral de movimiento (6px): un
 * arrastre NO abre el panel (y suprime el click que el browser dispara
 * después del pointerup).
 */

const BORDES = ['left', 'right', 'top', 'bottom'];

document.addEventListener('alpine:init', () => {
    window.Alpine.data('burbujaPorAceptar', (config = {}) => ({
        clave: config.clave || 'bcn-burbuja-por-aceptar',

        borde: 'right',
        offset: 0.5, // fracción 0..1 a lo largo del borde (resize-safe)
        abierto: false,
        destello: false,
        arrastrando: false,
        dragX: 0,
        dragY: 0,

        _suprimirClick: false,

        init() {
            try {
                const guardado = JSON.parse(localStorage.getItem(this.clave) || 'null');
                if (guardado && BORDES.includes(guardado.borde)) {
                    this.borde = guardado.borde;
                    const offset = Number(guardado.offset);
                    this.offset = Number.isFinite(offset) ? Math.min(0.95, Math.max(0.05, offset)) : 0.5;
                }
            } catch {
                // localStorage corrupto/bloqueado: posición default, sin drama.
            }
        },

        /** Posición de la burbuja cerrada: pegada al borde anclado. */
        get estiloBurbuja() {
            if (this.arrastrando) {
                return `left:${this.dragX}px;top:${this.dragY}px;right:auto;bottom:auto;transform:none;`;
            }

            const pct = `${(this.offset * 100).toFixed(2)}%`;
            switch (this.borde) {
                case 'left':
                    return `left:0.5rem;top:${pct};transform:translateY(-50%);`;
                case 'right':
                    return `right:0.5rem;top:${pct};transform:translateY(-50%);`;
                case 'top':
                    return `top:0.5rem;left:${pct};transform:translateX(-50%);`;
                default:
                    return `bottom:0.5rem;left:${pct};transform:translateX(-50%);`;
            }
        },

        /** Geometría del panel expandido: media pantalla desde el borde anclado. */
        get clasesPanel() {
            switch (this.borde) {
                case 'left':
                    return 'left-0 top-0 h-full w-full max-w-[50vw] border-r';
                case 'right':
                    return 'right-0 top-0 h-full w-full max-w-[50vw] border-l';
                case 'top':
                    return 'top-0 left-0 w-full h-[50vh] border-b';
                default:
                    return 'bottom-0 left-0 w-full h-[50vh] border-t';
            }
        },

        /** Cerrado: el panel queda 100% fuera de la pantalla por su borde. */
        get clasePanelOculto() {
            switch (this.borde) {
                case 'left':
                    return '-translate-x-full';
                case 'right':
                    return 'translate-x-full';
                case 'top':
                    return '-translate-y-full';
                default:
                    return 'translate-y-full';
            }
        },

        clickBurbuja() {
            // El browser dispara un click después del pointerup del arrastre:
            // ese no debe abrir. El de teclado (Enter/Espacio) sí.
            if (this._suprimirClick) {
                this._suprimirClick = false;

                return;
            }
            this.abierto = true;
        },

        empezarDrag(ev) {
            if (this.abierto || ev.button !== 0) {
                return;
            }

            const el = this.$refs.burbuja;
            const inicioX = ev.clientX;
            const inicioY = ev.clientY;
            let movido = false;

            const mover = (e) => {
                if (!movido && Math.abs(e.clientX - inicioX) + Math.abs(e.clientY - inicioY) <= 6) {
                    return;
                }
                movido = true;
                this.arrastrando = true;
                this.dragX = e.clientX - el.offsetWidth / 2;
                this.dragY = e.clientY - el.offsetHeight / 2;
            };
            const soltar = (e) => {
                window.removeEventListener('pointermove', mover);
                window.removeEventListener('pointerup', soltar);
                if (movido) {
                    this._suprimirClick = true;
                    this.anclar(e.clientX, e.clientY);
                }
                this.arrastrando = false;
            };

            window.addEventListener('pointermove', mover);
            window.addEventListener('pointerup', soltar);
        },

        /** Suelta la burbuja: borde más cercano + fracción a lo largo, persistido. */
        anclar(x, y) {
            const w = window.innerWidth;
            const h = window.innerHeight;
            const distancias = { left: x, right: w - x, top: y, bottom: h - y };
            this.borde = BORDES.reduce((a, b) => (distancias[a] <= distancias[b] ? a : b));

            const fraccion = this.borde === 'left' || this.borde === 'right' ? y / h : x / w;
            this.offset = Math.min(0.95, Math.max(0.05, fraccion));

            try {
                localStorage.setItem(this.clave, JSON.stringify({ borde: this.borde, offset: this.offset }));
            } catch {
                // Sin storage la posición vive solo esta página.
            }
        },
    }));
});
