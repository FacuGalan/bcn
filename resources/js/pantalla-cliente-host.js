/**
 * Host de la pantalla orientada al cliente (lado cajero).
 *
 * Vive en la pestaña del POS (NuevaVenta / Pedidos). Abre y posiciona la
 * ventana del cliente en el segundo monitor (Window Management API de
 * Chromium) y le envía el QR de cobro vía BroadcastChannel.
 *
 * Diseño "ventana persistente": el cajero la abre una vez (botón) y queda en
 * idle toda la jornada; cada cobro solo le manda el QR. Si el navegador no
 * soporta la API o el usuario no da permiso, igual abre la ventana y el cajero
 * la arrastra al monitor del cliente; si nada de eso funciona, el flujo de
 * cobro cae al modal normal (lo decide el modal según estaConectada()).
 *
 * Requisitos: Chrome/Edge, contexto seguro (https o localhost), monitores en
 * modo "Extender".
 *
 * Ref: Fase 5 integraciones de pago (cobro QR).
 */
const CANAL = 'bcn-pantalla-cliente';
const URL_PANTALLA = '/pantalla-cliente';
const NOMBRE_VENTANA = 'bcnPantallaCliente';

const host = {
    win: null,
    channel: 'BroadcastChannel' in window ? new BroadcastChannel(CANAL) : null,

    /** ¿Hay una ventana de cliente abierta (referenciada por esta pestaña)? */
    estaConectada() {
        return !!(this.win && !this.win.closed);
    },

    /**
     * Abre la ventana del cliente, posicionándola en el segundo monitor si se
     * puede. Devuelve true si la ventana quedó abierta. Debe llamarse desde un
     * gesto del usuario (click) para no ser bloqueada por el navegador.
     */
    async conectar() {
        if (this.estaConectada()) {
            this.win.focus();
            return true;
        }

        let left = 0;
        let top = 0;
        let width = Math.min(900, window.screen.availWidth);
        let height = Math.min(700, window.screen.availHeight);
        let pantallaSecundaria = false;

        try {
            if ('getScreenDetails' in window) {
                const details = await window.getScreenDetails(); // pide permiso "window-management"
                const actual = details.currentScreen;
                const otra =
                    details.screens.find((s) => s !== actual && !s.isInternal) ||
                    details.screens.find((s) => s !== actual);
                if (otra) {
                    left = otra.availLeft;
                    top = otra.availTop;
                    width = otra.availWidth;
                    height = otra.availHeight;
                    pantallaSecundaria = true;
                }
            }
        } catch (e) {
            // Permiso denegado o API no disponible: abrimos igual en la pantalla
            // actual; el cajero puede arrastrar la ventana al monitor del cliente.
            console.warn('[pantalla-cliente] sin Window Management API:', e?.message || e);
        }

        const features = `popup=yes,left=${left},top=${top},width=${width},height=${height}`;
        this.win = window.open(URL_PANTALLA, NOMBRE_VENTANA, features);

        if (this.win && pantallaSecundaria) {
            // Reforzar posición/tamaño (algunos navegadores ignoran las features).
            try {
                this.win.moveTo(left, top);
                this.win.resizeTo(width, height);
            } catch (e) {
                /* no-op */
            }
        }

        return this.estaConectada();
    },

    /** Cierra la ventana del cliente. */
    desconectar() {
        if (this.estaConectada()) {
            this.win.close();
        }
        this.win = null;
    },

    /** Envía el QR a la pantalla del cliente. */
    enviarQr(svg, monto, leyenda) {
        if (this.channel) {
            this.channel.postMessage({ type: 'qr', svg, monto: Number(monto) || 0, leyenda: leyenda || '' });
        }
    },

    /** Vuelve la pantalla del cliente al estado de espera. */
    limpiar() {
        if (this.channel) {
            this.channel.postMessage({ type: 'idle' });
        }
    },
};

window.bcnPantallaClienteHost = host;
