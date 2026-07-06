/**
 * Componente Alpine `demoraAlerta` — resaltado de pedidos demorados
 * (delivery + mostrador). El servidor manda los INSTANTES de corte (ISO,
 * calculados por el trait CalculaAlertaDemora según los umbrales de la
 * sucursal) y el navegador tickea cada 30s: los colores avanzan solos sin
 * round-trips a Livewire.
 *
 * Uso (card o fila):
 *   <div x-data="demoraAlerta(@js($pedido->alertaDemora($amarilla, $roja)))"
 *        :class="clases()">
 *       <span x-show="nivel !== 'ok'" x-text="edad()"></span>
 *
 * `config` null = sin alerta (umbrales en 0 o pedido fuera de juego).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('demoraAlerta', (config = null) => ({
        amarillo: config?.amarillo ? Date.parse(config.amarillo) : null,
        rojo: config?.rojo ? Date.parse(config.rojo) : null,
        desde: config?.desde ? Date.parse(config.desde) : null,
        nivel: 'ok',
        _timer: null,

        init() {
            if (!this.amarillo && !this.rojo) {
                return;
            }
            this.evaluar();
            this._timer = setInterval(() => this.evaluar(), 30000);
        },

        destroy() {
            clearInterval(this._timer);
        },

        evaluar() {
            const ahora = Date.now();
            if (this.rojo && ahora >= this.rojo) {
                this.nivel = 'rojo';
            } else if (this.amarillo && ahora >= this.amarillo) {
                this.nivel = 'amarillo';
            } else {
                this.nivel = 'ok';
            }
        },

        /** Clases de resaltado para la card (ring amarillo → rojo). */
        clases() {
            if (this.nivel === 'rojo') {
                return 'ring-2 ring-red-500 dark:ring-red-500';
            }
            if (this.nivel === 'amarillo') {
                return 'ring-2 ring-amber-400 dark:ring-amber-400';
            }

            return '';
        },

        /** Edad del pedido, para el contador ("25′" / "1h 05′"). */
        edad() {
            if (!this.desde) {
                return '';
            }
            const min = Math.max(0, Math.floor((Date.now() - this.desde) / 60000));
            if (min < 60) {
                return `${min}′`;
            }
            const h = Math.floor(min / 60);
            const m = String(min % 60).padStart(2, '0');

            return `${h}h ${m}′`;
        },
    }));
});
