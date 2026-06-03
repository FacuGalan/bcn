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
 *   { type: 'config', config }          → aplica la personalización de la sucursal
 *   { type: 'ping' }                    → responde { type: 'pong' } (heartbeat)
 *
 * La personalización (colores, animación, logo, mensaje) viaja por
 * BroadcastChannel desde el POS y se persiste en localStorage, de modo que al
 * abrir la PWA ya se ve con la marca al instante (sin esperar al host).
 *
 * Ref: Fase 5 integraciones de pago (cobro QR) + personalización 2da pantalla.
 */
const CANAL = 'bcn-pantalla-cliente';
const STORAGE_KEY = 'bcn-pc-config';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('pc-root');
    const elIdle = document.getElementById('pc-idle');
    const elQr = document.getElementById('pc-qr');
    const elQrSvg = document.getElementById('pc-qr-svg');
    const elMonto = document.getElementById('pc-monto');
    const elLeyenda = document.getElementById('pc-leyenda');
    const elLogo = document.getElementById('pc-logo');
    const elNombre = document.getElementById('pc-nombre');
    const elIdleMsg = document.getElementById('pc-idle-msg');
    const elThemeColor = document.getElementById('pc-theme-color');

    // ---- Auto-contraste: blanco o negro según la luminancia del fondo ----
    const contraste = (hex) => {
        const c = String(hex || '').replace('#', '');
        if (c.length !== 6) return '#ffffff';
        const r = parseInt(c.substr(0, 2), 16);
        const g = parseInt(c.substr(2, 2), 16);
        const b = parseInt(c.substr(4, 2), 16);
        return 0.299 * r + 0.587 * g + 0.114 * b > 140 ? '#111827' : '#ffffff';
    };

    // ---- Aplicar la personalización de la sucursal ----
    const aplicarConfig = (config) => {
        if (!config || typeof config !== 'object' || !root) return;

        const bg = config.color_fondo || '#222036';
        const acento = config.color_acento || '#22d3ee';
        const texto = !config.color_texto || config.color_texto === 'auto'
            ? contraste(bg)
            : config.color_texto;

        root.style.setProperty('--pc-bg', bg);
        root.style.setProperty('--pc-acento', acento);
        root.style.setProperty('--pc-texto', texto);
        // También en :root (html) para que el fondo del body acompañe y no asome
        // ningún borde blanco alrededor de la pantalla.
        document.documentElement.style.setProperty('--pc-bg', bg);
        if (elThemeColor) elThemeColor.setAttribute('content', bg);

        // Animación
        root.classList.remove('pc-anim-respiracion', 'pc-anim-aurora');
        if (config.animacion === 'respiracion') root.classList.add('pc-anim-respiracion');
        else if (config.animacion === 'aurora') root.classList.add('pc-anim-aurora');

        // Logo
        const logoFallback = root.dataset.logoFallback || '';
        const logoUrl = config.logo_url || logoFallback;
        if (elLogo) {
            elLogo.classList.remove('pc-logo-sm', 'pc-logo-md', 'pc-logo-lg');
            elLogo.classList.add('pc-logo-' + (config.tamano_logo || 'md'));
            if (config.mostrar_logo && logoUrl) {
                elLogo.src = logoUrl;
                elLogo.classList.remove('hidden');
            } else {
                elLogo.classList.add('hidden');
            }
        }

        // Nombre (se muestra si está habilitado; o como fallback si no hay logo)
        const nombre = config.nombre || root.dataset.nombreFallback || '';
        if (elNombre) {
            const mostrarNombre = config.mostrar_nombre && nombre;
            const sinLogoVisible = !(config.mostrar_logo && logoUrl);
            if (mostrarNombre || (sinLogoVisible && nombre)) {
                elNombre.textContent = nombre;
                elNombre.classList.remove('hidden');
            } else {
                elNombre.classList.add('hidden');
            }
        }

        // Mensaje de espera
        if (elIdleMsg && config.mensaje_idle) elIdleMsg.textContent = config.mensaje_idle;
    };

    // Aplicar config persistida (si existe) apenas carga, para ver la marca al instante.
    try {
        const guardada = localStorage.getItem(STORAGE_KEY);
        if (guardada) aplicarConfig(JSON.parse(guardada));
    } catch (e) {
        /* localStorage no disponible o JSON corrupto: se usa el render server-side */
    }

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
            case 'config':
                aplicarConfig(data.config);
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(data.config));
                } catch (e) {
                    /* sin localStorage: la config aplica solo en esta sesión */
                }
                break;
            case 'ping':
                channel.postMessage({ type: 'pong' });
                break;
        }
    };

    // Avisar a la pestaña del cajero que esta pantalla ya está lista (el host
    // responde con la config, así la marca llega aunque el primer envío se
    // perdiera por timing).
    channel.postMessage({ type: 'pong' });

    // --- Instalación del PWA (para abrirla fullscreen y separada del navegador) ---
    const installBtn = document.getElementById('pc-install-btn');
    const installHelp = document.getElementById('pc-install-help');
    let deferredPrompt = null;

    // Tres contextos posibles:
    //  - fullscreen  → es la PWA dedicada ya instalada bien: no ofrecer nada.
    //  - standalone  → corre DENTRO de la app del sistema (scope "/" la captura):
    //                  NO se puede instalar aparte desde acá → instruir abrir en
    //                  el navegador.
    //  - navegador   → ofrecer instalar (botón nativo o instrucciones).
    const esFullscreenApp = window.matchMedia('(display-mode: fullscreen)').matches;
    const esStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;

    if (esFullscreenApp) {
        // PWA dedicada instalada correctamente: nada para mostrar.
    } else if (esStandalone) {
        // Corre dentro de la app del sistema (scope "/"): no se puede instalar
        // aparte desde acá. No mostramos nada intrusivo; el botón "Enviar a la
        // 2da pantalla" cubre el caso de uso de mandarla al otro monitor.
    } else if (installBtn) {
        // Navegador normal: ofrecer instalar (aunque el prompt nativo tarde).
        installBtn.classList.remove('hidden');
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn && !esFullscreenApp && !esStandalone) installBtn.classList.remove('hidden');
        if (installHelp) installHelp.classList.add('hidden');
    });

    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
                installBtn.classList.add('hidden');
                if (installHelp) installHelp.classList.add('hidden');
            } else if (installHelp) {
                // Sin prompt nativo (ya instalada o navegador sin soporte):
                // mostrar/ocultar las instrucciones manuales bajo demanda.
                installHelp.classList.toggle('hidden');
            }
        });
    }

    window.addEventListener('appinstalled', () => {
        if (installBtn) installBtn.classList.add('hidden');
        if (installHelp) installHelp.classList.add('hidden');
    });

    // --- Botones flotantes: pantalla completa + enviar al 2do monitor ---
    const hint = document.getElementById('pc-fullscreen-hint');
    const btnEnviar = document.getElementById('pc-enviar-2da');
    // Solo se puede "enviar al 2do monitor" si existe la Multi-Screen Window
    // Placement API (multi-monitor). El conteo real de pantallas requiere
    // permiso, que se pide al hacer clic.
    const puedeEnviar = !!btnEnviar && 'getScreenDetails' in window;

    const entrarFullscreen = () => {
        const el = document.documentElement;
        if (!document.fullscreenElement && el.requestFullscreen) {
            el.requestFullscreen().catch(() => {
                /* sin gesto/activación: queda el hint para que el cajero haga clic */
            });
        }
    };

    // Mostrar los botones solo fuera de pantalla completa (al entrar en fullscreen
    // se ocultan; al salir vuelven a aparecer).
    const actualizarBotones = () => {
        const enFs = !!document.fullscreenElement;
        if (hint) hint.classList.toggle('hidden', enFs);
        if (btnEnviar) btnEnviar.classList.toggle('hidden', enFs || !puedeEnviar);
    };

    document.addEventListener('fullscreenchange', actualizarBotones);

    // Intento automático al abrir (puede ser bloqueado si no hay activación).
    entrarFullscreen();

    // Fallback: cualquier clic/tecla en la pantalla entra en pantalla completa.
    if (hint) hint.addEventListener('click', entrarFullscreen);
    document.addEventListener('click', entrarFullscreen);
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') entrarFullscreen();
    });

    // Enviar al 2do monitor y poner fullscreen ahí. Resuelve el caso del cajero
    // que no puede arrastrar la ventana entre monitores.
    if (btnEnviar && puedeEnviar) {
        btnEnviar.addEventListener('click', async (e) => {
            // Evitar que el click global dispare fullscreen en la pantalla actual.
            e.stopPropagation();
            try {
                const details = await window.getScreenDetails();
                const otra =
                    details.screens.find((s) => s !== details.currentScreen) || details.currentScreen;

                // Reposicionar la ventana en la otra pantalla (si el navegador lo
                // permite) y pedir fullscreen apuntando a esa pantalla.
                try {
                    window.moveTo(otra.availLeft, otra.availTop);
                } catch (_) {
                    /* moveTo puede estar restringido; el fullscreen dirigido alcanza */
                }
                await document.documentElement.requestFullscreen({ screen: otra });
            } catch (err) {
                // Sin permiso / una sola pantalla: caer al fullscreen normal.
                entrarFullscreen();
            }
        });
    }

    actualizarBotones();
});
