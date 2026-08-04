/**
 * Componente Alpine `burbujaPorAceptar` — burbuja flotante de pedidos por
 * aceptar del panel de delivery (spec delivery-burbuja-y-mapa, RF-01/02/03).
 *
 * Cerrada es una píldora compacta (contador + "por aceptar") que se arrastra
 * a cualquier borde; al soltar se ancla al borde más cercano y la posición
 * (borde + fracción a lo largo del borde) se persiste en localStorage por
 * comercio+sucursal.
 *
 * Al clickearla, la MISMA burbuja se expande en una tarjeta flotante anclada
 * al mismo punto: no es un panel de borde a borde ni una ventana modal. La
 * tarjeta existe en el DOM SOLO mientras está abierta (x-show + transición de
 * escala desde el punto de anclaje), así al cambiar la burbuja de un lateral
 * a otro no se ve ninguna ventana cruzando la pantalla.
 *
 * Franja segura (`safeTop`): ni la burbuja ni la tarjeta suben por encima del
 * navbar. Sin esto, anclada arriba la tarjeta se solapaba con el menú y su X
 * quedaba fuera de alcance.
 *
 * Morph-safety: el x-data es ESTÁTICO (clave de storage + safeTop); la
 * cantidad y la lista las morphea Livewire adentro sin re-inicializar Alpine.
 * La geometría se aplica con :style desde el estado, que sobrevive al morph.
 *
 * El click se distingue del arrastre por umbral de movimiento (6px): un
 * arrastre NO abre la tarjeta (y suprime el click que el browser dispara
 * después del pointerup).
 */

const BORDES = ['left', 'right', 'top', 'bottom'];

const MARGEN = 12; // separación de la tarjeta expandida al borde de la ventana
const MARGEN_BURBUJA = 4; // la burbuja va pegada al borde
const PANEL_ANCHO = 352; // ancho ideal de la tarjeta expandida
const PANEL_ALTO_MAX = 0.66; // fracción del alto de ventana que puede ocupar
const SEMI = 24; // media altura/ancho aprox de la burbuja cerrada
const AVISO_MS = 5000; // cadencia del aviso sonoro mientras haya pedidos

const acotar = (valor, min, max) => Math.min(Math.max(valor, min), Math.max(min, max));

// ───────────────────────── Aviso sonoro ─────────────────────────
// Mismo enfoque que el llamador (resources/js/llamador.js): el chime se
// sintetiza con WebAudio, sin archivos de audio. Por la política de autoplay
// el AudioContext solo suena después de un gesto del usuario, así que se
// intenta al montar y se reintenta con el primer click/tecla de la página.
let audioCtx = null;

function desbloquearAudio() {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    } catch {
        audioCtx = null;
    }
}

function chimeAviso() {
    if (!audioCtx || audioCtx.state !== 'running') {
        return;
    }

    const now = audioCtx.currentTime;
    [880, 1174].forEach((freq, i) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        const t = now + i * 0.13;
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(0.22, t + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.28);
        osc.connect(gain).connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + 0.3);
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('burbujaPorAceptar', (config = {}) => ({
        clave: config.clave || 'bcn-burbuja-por-aceptar',

        borde: 'right',
        offset: 0.5, // fracción 0..1 a lo largo del borde (resize-safe)
        abierto: false,
        destello: false,
        vibrando: false,
        arrastrando: false,
        dragX: 0,
        dragY: 0,
        vw: 1280,
        vh: 800,

        _onResize: null,
        _avisoTimer: null,
        _vibraTimer: null,

        init() {
            this.medir();
            this._onResize = () => this.medir();
            window.addEventListener('resize', this._onResize);

            // Aviso periódico: el componente solo existe si hay pedidos por
            // aceptar (el @if de la vista), así que no hace falta chequearlo acá.
            desbloquearAudio();
            window.addEventListener('pointerdown', desbloquearAudio, { once: true });
            window.addEventListener('keydown', desbloquearAudio, { once: true });
            this._avisoTimer = setInterval(() => this.avisar(), AVISO_MS);

            try {
                const guardado = JSON.parse(localStorage.getItem(this.clave) || 'null');
                if (guardado && BORDES.includes(guardado.borde)) {
                    this.borde = guardado.borde;
                    const offset = Number(guardado.offset);
                    this.offset = Number.isFinite(offset) ? acotar(offset, 0.02, 0.98) : 0.5;
                }
            } catch {
                // localStorage corrupto/bloqueado: posición default, sin drama.
            }
        },

        destroy() {
            window.removeEventListener('resize', this._onResize);
            clearInterval(this._avisoTimer);
            clearTimeout(this._vibraTimer);
        },

        /** Chime + vibración de la burbuja: "hay pedidos esperando". */
        avisar() {
            // Pestaña en segundo plano: no sonar (el navegador además frena los
            // timers, así que sonaría a destiempo).
            if (document.hidden) {
                return;
            }

            chimeAviso();
            this.vibrando = true;
            clearTimeout(this._vibraTimer);
            this._vibraTimer = setTimeout(() => {
                this.vibrando = false;
            }, 600);
        },

        medir() {
            this.vw = window.innerWidth;
            this.vh = window.innerHeight;
        },

        /** Punto de anclaje vertical (bordes laterales). */
        get anclaY() {
            return acotar(this.offset * this.vh, MARGEN_BURBUJA + SEMI, this.vh - SEMI - MARGEN_BURBUJA);
        },

        /** Punto de anclaje horizontal (bordes superior/inferior). */
        get anclaX() {
            return acotar(this.offset * this.vw, 90, this.vw - 90);
        },

        /**
         * Posición de la burbuja cerrada: pegada al borde anclado.
         *
         * Devuelve un OBJETO, no un string: con un string Alpine hace
         * `setAttribute('style', …)` y pisa el atributo entero, borrando el
         * `display:none` que `x-show` había puesto — al arrastrar cambiaban
         * `borde`/`offset`, se recalculaba el estilo y la burbuja/tarjeta
         * reaparecían con el estado en `false` (imposibles de cerrar, porque
         * `x-show` ya no volvía a correr). Con objeto, Alpine aplica propiedad
         * por propiedad y no toca `display`. Todas las claves se declaran
         * siempre (con `auto`) para que ninguna quede pegada del borde previo.
         */
        get estiloBurbuja() {
            // El centrado va en `translate` (propiedad individual) y NO en
            // `transform`: así la animación de vibrado (que usa `transform`) no
            // lo pisa y la burbuja no salta de lugar al vibrar.
            const base = { left: 'auto', right: 'auto', top: 'auto', bottom: 'auto' };

            if (this.arrastrando) {
                return { ...base, left: `${this.dragX}px`, top: `${this.dragY}px`, translate: '-50% -50%' };
            }

            switch (this.borde) {
                case 'left':
                    return { ...base, left: `${MARGEN_BURBUJA}px`, top: `${this.anclaY}px`, translate: '0 -50%' };
                case 'right':
                    return { ...base, right: `${MARGEN_BURBUJA}px`, top: `${this.anclaY}px`, translate: '0 -50%' };
                case 'top':
                    return { ...base, top: `${MARGEN_BURBUJA}px`, left: `${this.anclaX}px`, translate: '-50% 0' };
                default:
                    return { ...base, bottom: `${MARGEN_BURBUJA}px`, left: `${this.anclaX}px`, translate: '-50% 0' };
            }
        },

        /**
         * Geometría de la tarjeta expandida: mismo anclaje que la burbuja,
         * tamaño acotado a la ventana y `transform-origin` en el punto del que
         * "sale" para que la expansión se lea como que crece la burbuja.
         */
        get estiloPanel() {
            const ancho = Math.min(PANEL_ANCHO, this.vw - 2 * MARGEN);
            const estilo = { width: `${ancho}px`, left: 'auto', right: 'auto', top: 'auto', bottom: 'auto' };
            let origenX = 'center';
            let origenY = 'top';
            let alto;

            if (this.borde === 'left') {
                estilo.left = `${MARGEN}px`;
                origenX = 'left';
            } else if (this.borde === 'right') {
                estilo.right = `${MARGEN}px`;
                origenX = 'right';
            } else {
                estilo.left = `${acotar(this.anclaX - ancho / 2, MARGEN, this.vw - ancho - MARGEN)}px`;
            }

            if (this.borde === 'top') {
                estilo.top = `${MARGEN}px`;
                alto = this.vh - 2 * MARGEN;
            } else if (this.borde === 'bottom') {
                estilo.bottom = `${MARGEN}px`;
                alto = this.vh - 2 * MARGEN;
                origenY = 'bottom';
            } else if (this.anclaY <= this.vh / 2) {
                // Ancla en la mitad de arriba: la tarjeta crece hacia abajo.
                const top = Math.max(MARGEN, this.anclaY - SEMI);
                estilo.top = `${top}px`;
                alto = this.vh - top - MARGEN;
            } else {
                // Ancla abajo: crece hacia arriba (queda alineada con la burbuja).
                const bottom = Math.max(MARGEN, this.vh - this.anclaY - SEMI);
                estilo.bottom = `${bottom}px`;
                alto = this.vh - bottom - MARGEN;
                origenY = 'bottom';
            }

            estilo['max-height'] = `${Math.max(200, Math.min(alto, this.vh * PANEL_ALTO_MAX))}px`;
            estilo['transform-origin'] = `${origenY} ${origenX}`;

            return estilo;
        },

        clickBurbuja() {
            this.abierto = true;
        },

        empezarDrag(ev) {
            if (this.abierto || ev.button !== 0) {
                return;
            }

            const inicioX = ev.clientX;
            const inicioY = ev.clientY;
            let movido = false;

            const mover = (e) => {
                if (!movido && Math.abs(e.clientX - inicioX) + Math.abs(e.clientY - inicioY) <= 6) {
                    return;
                }
                movido = true;
                this.arrastrando = true;
                this.dragX = acotar(e.clientX, MARGEN_BURBUJA + SEMI, this.vw - MARGEN_BURBUJA - SEMI);
                this.dragY = acotar(e.clientY, MARGEN_BURBUJA + SEMI, this.vh - MARGEN_BURBUJA - SEMI);
            };
            const soltar = (e) => {
                window.removeEventListener('pointermove', mover);
                window.removeEventListener('pointerup', soltar);
                // Si el gesto se cancela (el browser se queda con el puntero),
                // sin esto los listeners quedaban vivos y `arrastrando` en true.
                window.removeEventListener('pointercancel', soltar);
                if (movido) {
                    // El browser dispara un `click` justo después del pointerup:
                    // ese cierra el arrastre, NO debe abrir la tarjeta. Se traga
                    // en fase de captura (no depende de dónde caiga el click: al
                    // soltar, la burbuja salta al borde y el target ya no es ella).
                    // El listener se remueve en ese mismo click; el timeout es
                    // solo la red de seguridad para cuando el click no llega
                    // (Chrome despacha el click en un task aparte, así que un
                    // setTimeout(…, 0) le ganaba de mano y lo dejaba pasar).
                    const tragar = (ev) => {
                        ev.stopPropagation();
                        ev.preventDefault();
                        window.removeEventListener('click', tragar, true);
                    };
                    window.addEventListener('click', tragar, true);
                    setTimeout(() => window.removeEventListener('click', tragar, true), 400);

                    this.anclar(e.clientX, e.clientY);
                }
                this.arrastrando = false;
            };

            window.addEventListener('pointermove', mover);
            window.addEventListener('pointerup', soltar);
            window.addEventListener('pointercancel', soltar);
        },

        /** Suelta la burbuja: borde más cercano + fracción a lo largo, persistido. */
        anclar(x, y) {
            const w = this.vw;
            const h = this.vh;
            const px = acotar(x, 0, w);
            const py = acotar(y, 0, h);

            // El borde superior es el de la ventana: la burbuja puede quedar por
            // encima del navbar (va en z-50, así que sigue siendo clickeable).
            const distancias = { left: px, right: w - px, top: py, bottom: h - py };
            this.borde = BORDES.reduce((a, b) => (distancias[a] <= distancias[b] ? a : b));

            const fraccion = this.borde === 'left' || this.borde === 'right' ? py / h : px / w;
            this.offset = acotar(fraccion, 0.02, 0.98);

            try {
                localStorage.setItem(this.clave, JSON.stringify({ borde: this.borde, offset: this.offset }));
            } catch {
                // Sin storage la posición vive solo esta página.
            }
        },
    }));
});
