import '../css/app.css';

/**
 * Pantalla orientada al cliente (segundo monitor del puesto de cobro).
 *
 * Página liviana e independiente (sin Livewire/Alpine): escucha por
 * BroadcastChannel los mensajes que envía la pestaña del cajero (mismo origen)
 * y muestra el QR de cobro a pantalla completa, orientado al cliente.
 *
 * Mensajes que recibe:
 *   { type: 'qr', svg, monto, leyenda } → muestra el QR
 *   { type: 'idle' }                    → vuelve al estado de espera
 *   { type: 'ping' }                    → responde { type: 'pong' } (heartbeat)
 *
 * Ref: Fase 5 integraciones de pago (cobro QR).
 */
const CANAL = 'bcn-pantalla-cliente';

document.addEventListener('DOMContentLoaded', () => {
    const elIdle = document.getElementById('pc-idle');
    const elQr = document.getElementById('pc-qr');
    const elQrSvg = document.getElementById('pc-qr-svg');
    const elMonto = document.getElementById('pc-monto');
    const elLeyenda = document.getElementById('pc-leyenda');

    if (!('BroadcastChannel' in window)) {
        console.error('[pantalla-cliente] BroadcastChannel no soportado en este navegador');
        return;
    }

    const channel = new BroadcastChannel(CANAL);

    const formatearMonto = (monto) => {
        const n = Number(monto) || 0;
        return '$' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const mostrarIdle = () => {
        elQr.classList.add('hidden');
        elIdle.classList.remove('hidden');
        elQrSvg.innerHTML = '';
    };

    const mostrarQr = (data) => {
        elQrSvg.innerHTML = data.svg || '';
        elMonto.textContent = formatearMonto(data.monto);
        elLeyenda.textContent = data.leyenda || '';
        elIdle.classList.add('hidden');
        elQr.classList.remove('hidden');
    };

    channel.onmessage = (e) => {
        const data = e.data || {};
        switch (data.type) {
            case 'qr':
                mostrarQr(data);
                break;
            case 'idle':
                mostrarIdle();
                break;
            case 'ping':
                channel.postMessage({ type: 'pong' });
                break;
        }
    };

    // Avisar a la pestaña del cajero que esta pantalla ya está lista.
    channel.postMessage({ type: 'pong' });

    // --- Pantalla completa (estilo F11) ---
    const hint = document.getElementById('pc-fullscreen-hint');

    const entrarFullscreen = () => {
        const el = document.documentElement;
        if (!document.fullscreenElement && el.requestFullscreen) {
            el.requestFullscreen().catch(() => {
                /* sin gesto/activación: queda el hint para que el cajero haga clic */
            });
        }
    };

    const actualizarHint = () => {
        if (hint) hint.classList.toggle('hidden', !!document.fullscreenElement);
    };

    document.addEventListener('fullscreenchange', actualizarHint);

    // Intento automático al abrir (puede ser bloqueado si no hay activación).
    entrarFullscreen();

    // Fallback: cualquier clic/tecla en la pantalla entra en pantalla completa.
    if (hint) hint.addEventListener('click', entrarFullscreen);
    document.addEventListener('click', entrarFullscreen);
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') entrarFullscreen();
    });

    actualizarHint();
});
