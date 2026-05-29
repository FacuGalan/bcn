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

        // Calcular las coordenadas del SEGUNDO monitor ANTES de abrir, y abrir
        // la ventana ya posicionada ahí (más fiable que moveTo posterior).
        // getScreenDetails() pide el permiso "window-management" (una vez).
        let features = `popup=yes,width=${Math.min(900, window.screen.availWidth)},height=${Math.min(700, window.screen.availHeight)}`;
        try {
            if ('getScreenDetails' in window) {
                const details = await window.getScreenDetails();
                const otra =
                    details.screens.find((s) => s !== details.currentScreen) || details.currentScreen;
                if (otra) {
                    features = `popup=yes,left=${otra.availLeft},top=${otra.availTop},width=${otra.availWidth},height=${otra.availHeight}`;
                }
            }
        } catch (e) {
            // Permiso denegado o API no disponible: se abre en la pantalla actual
            // y el cajero la arrastra al monitor del cliente.
            console.warn('[pantalla-cliente] sin Window Management API:', e?.message || e);
        }

        this.win = window.open(URL_PANTALLA, NOMBRE_VENTANA, features);
        if (this.win) {
            this.win.focus();
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
