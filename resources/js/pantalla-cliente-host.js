/**
 * Host de la pantalla orientada al cliente (lado cajero).
 *
 * Vive en la pestaña del POS (NuevaVenta / Pedidos). Habla con la pantalla del
 * cliente vía BroadcastChannel (mismo origen) para mandarle la personalización
 * de la sucursal y el QR de cobro.
 *
 * Hay DOS formas de tener la pantalla del cliente abierta:
 *  1) PWA instalada "Pantalla Cliente" (recomendada): el usuario la instala una
 *     vez y la abre desde el ícono del SO en el 2do monitor. Arranca a pantalla
 *     completa por manifest (display:fullscreen) y con ícono propio en la barra
 *     de tareas. El navegador NO permite lanzarla desde un botón web, así que
 *     este host NO la abre: solo detecta que está viva (ping/pong) y le manda
 *     config + QR.
 *  2) Popup de respaldo: si no hay ninguna pantalla viva, el botón abre una
 *     ventana normal posicionada en el 2do monitor (Window Management API). No
 *     es fullscreen automática (limitación del navegador para ventanas web).
 *
 * Detección de "conectada": por referencia a la ventana propia (popup) O por
 * pong reciente en el canal (cubre la PWA, que esta pestaña no abrió).
 *
 * Requisitos: Chrome/Edge, contexto seguro (https o localhost), monitores en
 * modo "Extender".
 *
 * Ref: Fase 5 integraciones de pago (cobro QR) + personalización 2da pantalla.
 */
const CANAL = 'bcn-pantalla-cliente';
const URL_PANTALLA = '/pantalla-cliente';
const NOMBRE_VENTANA = 'bcnPantallaCliente';
const PONG_TTL = 4000; // ms que consideramos "viva" una pantalla tras su último pong

const host = {
    win: null,
    channel: 'BroadcastChannel' in window ? new BroadcastChannel(CANAL) : null,

    /** Personalización de la 2da pantalla de la sucursal (la setea el POS). */
    config: null,

    /** Epoch (ms) del último pong recibido de alguna pantalla viva. */
    ultimoPong: 0,

    /**
     * ¿Hay una pantalla del cliente disponible? Cubre el popup propio (por
     * referencia) y la PWA/otra ventana viva (por pong reciente en el canal).
     */
    estaConectada() {
        if (this.win && !this.win.closed) return true;

        return Date.now() - this.ultimoPong < PONG_TTL;
    },

    /** Pregunta por el canal si hay alguna pantalla escuchando. */
    pingear() {
        if (this.channel) this.channel.postMessage({ type: 'ping' });
    },

    /**
     * Conecta con la pantalla del cliente. Si ya hay una viva (PWA instalada o
     * popup abierto) le manda la config y listo. Si no hay ninguna, abre el
     * popup de respaldo posicionado en el 2do monitor. Debe llamarse desde un
     * gesto del usuario (click) para que el navegador no bloquee el popup.
     */
    async conectar() {
        // ¿Ya hay una pantalla viva? (p. ej. la PWA abierta en el 2do monitor)
        this.pingear();
        await new Promise((r) => setTimeout(r, 450));
        if (this.estaConectada()) {
            this.enviarConfig();
            if (this.win && !this.win.closed) this.win.focus();

            return true;
        }

        // No hay ninguna: abrir el popup de respaldo. Calcular las coordenadas
        // del SEGUNDO monitor ANTES de abrir (más fiable que moveTo posterior).
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
        if (this.win) this.win.focus();

        // La pantalla, al cargar, emite un pong y el host le responde con la
        // config (ver el listener del canal abajo). Igual reintentamos por las
        // dudas que el listener se registre tarde.
        if (this.win) setTimeout(() => this.enviarConfig(), 700);

        return this.estaConectada();
    },

    /**
     * Setea la personalización de la sucursal (la inyecta el POS server-side) y
     * la envía a la pantalla del cliente si hay canal disponible.
     */
    setConfig(config) {
        if (config && typeof config === 'object') {
            // Clonar a objeto plano: si viene un proxy reactivo de Alpine, el
            // structured clone de postMessage falla ("could not be cloned").
            try {
                this.config = JSON.parse(JSON.stringify(config));
            } catch (e) {
                return;
            }
            this.enviarConfig();
        }
    },

    /** Envía la personalización a la pantalla del cliente (si hay config y canal). */
    enviarConfig() {
        if (this.channel && this.config) {
            this.channel.postMessage({ type: 'config', config: this.config });
        }
    },

    /** Cierra el popup de respaldo (si esta pestaña lo abrió). */
    desconectar() {
        if (this.win && !this.win.closed) this.win.close();
        this.win = null;
        this.ultimoPong = 0;
    },

    /** Envía el QR a la pantalla del cliente. */
    enviarQr(svg, monto, leyenda) {
        if (this.channel) {
            // Reenviar la config antes del QR por si la pantalla se reabrió.
            this.enviarConfig();
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

// Escuchar pongs: marcan que hay una pantalla viva y son la señal para
// (re)enviarle la config apenas aparece/recarga (entrega robusta de la marca).
if (host.channel) {
    host.channel.onmessage = (e) => {
        const data = e.data || {};
        if (data.type === 'pong') {
            host.ultimoPong = Date.now();
            host.enviarConfig();
        }
    };
}

window.bcnPantallaClienteHost = host;
