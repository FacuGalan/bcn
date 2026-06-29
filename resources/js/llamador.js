import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Monitor llamador de pedidos (pantalla Clase B remota, sin sesión).
 *
 * - Resuelve el token: de la URL (QR), del código corto (TV) o de localStorage.
 * - Carga la personalización + snapshot vía el endpoint acotado al token.
 * - Se suscribe a un canal PÚBLICO de Reverb (`llamador.{token}`) — sin auth: el
 *   token es el secreto. Mueve las tarjetas entre columnas y suena un chime al
 *   pasar un pedido a "Listo".
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b, RF-03, RF-04).
 */

const STORAGE_KEY = 'bcn_llamador_token';
const boot = window.__LLAMADOR__ || {};

const $ = (sel) => document.querySelector(sel);
const elVincular = $('#vincular');
const elPantalla = $('#pantalla');
const elAudioUnlock = $('#audio-unlock');
const colPrep = $('#col-preparacion');
const colListo = $('#col-listo');

let audioCtx = null;
let sonidoHabilitado = true;

// ───────────────────────── Audio (chime) ─────────────────────────
// Política de autoplay: el AudioContext se crea recién tras un gesto del
// usuario. Una capa "tocá para activar" lo desbloquea y desaparece.
function unlockAudio() {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    } catch (e) {
        audioCtx = null;
    }
    // El mismo gesto que desbloquea el audio entra a pantalla completa (no se
    // puede disparar F11 desde JS, pero sí la Fullscreen API dentro de un click).
    entrarPantallaCompleta();
    if (elAudioUnlock) elAudioUnlock.style.display = 'none';
}

function entrarPantallaCompleta() {
    if (document.fullscreenElement) return;
    const el = document.documentElement;
    const req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (req) {
        try {
            const r = req.call(el);
            if (r && r.catch) r.catch(() => {});
        } catch (e) { /* el navegador puede bloquearlo: best-effort */ }
    }
}

function playChime() {
    if (!sonidoHabilitado || !audioCtx) return;
    const now = audioCtx.currentTime;
    [880, 1320].forEach((freq, i) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        const t = now + i * 0.18;
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(0.4, t + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
        osc.connect(gain).connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + 0.55);
    });
}

// ───────────────────────── Render de tarjetas ─────────────────────────
function tarjeta(p) {
    const div = document.createElement('div');
    div.className = 'llm-card';
    div.dataset.numero = p.numero;
    const num = document.createElement('span');
    num.className = 'llm-num';
    num.textContent = p.numero;
    div.appendChild(num);
    if (p.nombre) {
        const nom = document.createElement('span');
        nom.className = 'llm-nombre';
        nom.textContent = p.nombre;
        div.appendChild(nom);
    }
    return div;
}

function quitarTarjeta(numero) {
    document.querySelectorAll(`.llm-card[data-numero="${numero}"]`).forEach((el) => el.remove());
}

function renderSnapshot(pedidos) {
    colPrep.innerHTML = '';
    colListo.innerHTML = '';
    (pedidos.en_preparacion || []).forEach((p) => colPrep.appendChild(tarjeta(p)));
    (pedidos.listo || []).forEach((p) => colListo.appendChild(tarjeta(p)));
    ajustarFit();
}

// Aplica un evento de cambio de estado: reubica la tarjeta y suena si entra a "Listo".
function aplicarEvento(data) {
    const numero = data.numero;
    const estabaEnListo = !!document.querySelector(`#col-listo .llm-card[data-numero="${numero}"]`);
    quitarTarjeta(numero);

    if (data.estado === 'en_preparacion') {
        colPrep.appendChild(tarjeta(data));
    } else if (data.estado === 'listo') {
        colListo.prepend(tarjeta(data));
        if (!estabaEnListo) playChime();
    }
    // Otros estados (entregado/cancelado/…): la tarjeta ya quedó removida.
    ajustarFit();
}

// ───────────────────────── Personalización ─────────────────────────
// Densidad base elegida en config. El auto-fit (--llm-fit) reduce desde acá.
const BASE_TAMANO = { compacto: 0.72, normal: 1, grande: 1.3 };

function aplicarConfig(data) {
    const cfg = data.config || {};
    sonidoHabilitado = cfg.sonido !== false;
    const root = document.documentElement;
    if (cfg.color_fondo) root.style.setProperty('--llm-bg', cfg.color_fondo);
    if (cfg.color_preparacion) root.style.setProperty('--llm-prep', cfg.color_preparacion);
    if (cfg.color_listo) root.style.setProperty('--llm-listo', cfg.color_listo);

    // Densidad base por columna (las dos listas comparten el mismo valor).
    const base = BASE_TAMANO[cfg.tamano] ?? 1;
    [colPrep, colListo].forEach((c) => c && c.style.setProperty('--llm-base', base));

    const titulo = $('#llm-titulo');
    if (titulo) titulo.textContent = cfg.titulo || 'Pedidos';
    const sucursal = $('#llm-sucursal');
    if (sucursal) sucursal.textContent = data.sucursal?.nombre || '';

    const logo = $('#llm-logo');
    if (logo) {
        if (cfg.mostrar_logo && data.logo) {
            logo.src = data.logo;
            // 'block' explícito: setear '' revierte al CSS (display:none) y el logo no aparecería.
            logo.style.display = 'block';
        } else {
            logo.style.display = 'none';
        }
    }
}

// ───────────────────────── Auto-fit (sin scroll) ─────────────────────────
// Una TV no se puede scrollear: si los pedidos no entran, reducimos la escala
// de la columna (--llm-fit) hasta que entren todos o se llegue al mínimo.
function ajustarFitCol(lista) {
    if (!lista) return;
    lista.style.setProperty('--llm-fit', '1');
    let fit = 1;
    let guard = 0;
    while (lista.scrollHeight > lista.clientHeight + 1 && fit > 0.35 && guard < 16) {
        fit -= 0.07;
        lista.style.setProperty('--llm-fit', fit.toFixed(3));
        guard++;
    }
}

let fitPendiente = false;
function ajustarFit() {
    if (fitPendiente) return;
    fitPendiente = true;
    requestAnimationFrame(() => {
        fitPendiente = false;
        ajustarFitCol(colPrep);
        ajustarFitCol(colListo);
    });
}

// ───────────────────────── Conexión ─────────────────────────
async function cargarSnapshot(token) {
    const resp = await fetch(`/clase-b/llamador/${token}/snapshot`, {
        headers: { Accept: 'application/json' },
    });
    if (resp.status === 404) throw new Error('token_invalido');
    if (!resp.ok) throw new Error('snapshot_error');
    return resp.json();
}

function suscribir(token) {
    window.Pusher = Pusher;
    const echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    // Canal PÚBLICO: no pega a /broadcasting/auth. El token es el secreto.
    echo.channel(`llamador.${token}`).listen('.PedidoLlamador', (e) => aplicarEvento(e));

    // Resiliencia: si el WS se cae (blip de red, la TV se suspende), pusher-js
    // reconecta solo, pero los eventos perdidos durante la caída NO se reproducen.
    // En una TV encendida horas/días eso deja el tablero pegado. Al RECONECTAR
    // (no en la conexión inicial, que ya trae el snapshot) re-sincronizamos.
    try {
        let primeraConexion = true;
        echo.connector.pusher.connection.bind('connected', () => {
            if (primeraConexion) {
                primeraConexion = false;
                return;
            }
            cargarSnapshot(token)
                .then((data) => renderSnapshot(data.pedidos || {}))
                .catch(() => {});
        });
    } catch (e) {
        // bind no disponible: el snapshot inicial sigue visible, se reintenta al recargar
    }
}

async function iniciar(token) {
    try {
        const data = await cargarSnapshot(token);
        localStorage.setItem(STORAGE_KEY, token);
        aplicarConfig(data);
        renderSnapshot(data.pedidos || {});
        elVincular.style.display = 'none';
        elPantalla.style.display = 'flex';
    } catch (err) {
        // Token inválido/regenerado: olvidarlo y volver a la vinculación.
        localStorage.removeItem(STORAGE_KEY);
        mostrarVinculacion();
        return;
    }

    // Tiempo real best-effort: un fallo de WS no debe tapar la pantalla ya
    // renderizada (el snapshot inicial ya está en pantalla).
    try {
        suscribir(token);
    } catch (e) {
        // se reintenta al recargar; el snapshot inicial sigue visible
    }
}

function mostrarVinculacion() {
    elPantalla.style.display = 'none';
    elVincular.style.display = 'flex';
}

async function canjearCodigo(codigo) {
    const resp = await fetch(`/clase-b/vincular/${encodeURIComponent(codigo)}`, {
        headers: { Accept: 'application/json' },
    });
    if (!resp.ok) throw new Error('codigo_invalido');
    const { token } = await resp.json();
    return token;
}

// ───────────────────────── Arranque ─────────────────────────
async function arrancar() {
    if (elAudioUnlock) elAudioUnlock.addEventListener('click', unlockAudio, { once: true });
    // Cualquier gesto desbloquea el audio (por si la capa ya no está).
    document.addEventListener('click', unlockAudio, { once: true });

    // Reajustar el tamaño si cambia el viewport (rotación, resize de ventana).
    window.addEventListener('resize', ajustarFit);

    // Form de vinculación manual (tipear el código).
    const form = $('#vincular-form');
    if (form) {
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const input = $('#vincular-codigo');
            const error = $('#vincular-error');
            if (error) error.textContent = '';
            try {
                const token = await canjearCodigo(input.value.trim());
                iniciar(token);
            } catch (e) {
                if (error) error.textContent = 'Código inválido';
            }
        });
    }

    // Prioridad de bootstrap: token de URL (QR) > código de URL (TV) > localStorage.
    if (boot.token) {
        iniciar(boot.token);
        return;
    }
    if (boot.codigo) {
        try {
            iniciar(await canjearCodigo(boot.codigo));
        } catch (e) {
            mostrarVinculacion();
        }
        return;
    }
    const guardado = localStorage.getItem(STORAGE_KEY);
    if (guardado) {
        iniciar(guardado);
    } else {
        mostrarVinculacion();
    }
}

arrancar();
