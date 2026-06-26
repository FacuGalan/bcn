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
    if (elAudioUnlock) elAudioUnlock.style.display = 'none';
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
}

// ───────────────────────── Personalización ─────────────────────────
function aplicarConfig(data) {
    const cfg = data.config || {};
    sonidoHabilitado = cfg.sonido !== false;
    const root = document.documentElement;
    if (cfg.color_fondo) root.style.setProperty('--llm-bg', cfg.color_fondo);
    if (cfg.color_preparacion) root.style.setProperty('--llm-prep', cfg.color_preparacion);
    if (cfg.color_listo) root.style.setProperty('--llm-listo', cfg.color_listo);

    const titulo = $('#llm-titulo');
    if (titulo) titulo.textContent = cfg.titulo || 'Pedidos';
    const sucursal = $('#llm-sucursal');
    if (sucursal) sucursal.textContent = data.sucursal?.nombre || '';

    const logo = $('#llm-logo');
    if (logo) {
        if (cfg.mostrar_logo && data.logo) {
            logo.src = data.logo;
            logo.style.display = '';
        } else {
            logo.style.display = 'none';
        }
    }
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
}

async function iniciar(token) {
    try {
        const data = await cargarSnapshot(token);
        localStorage.setItem(STORAGE_KEY, token);
        aplicarConfig(data);
        renderSnapshot(data.pedidos || {});
        elVincular.style.display = 'none';
        elPantalla.style.display = '';
        suscribir(token);
    } catch (err) {
        // Token inválido/regenerado: olvidarlo y volver a la vinculación.
        localStorage.removeItem(STORAGE_KEY);
        mostrarVinculacion();
    }
}

function mostrarVinculacion() {
    elPantalla.style.display = 'none';
    elVincular.style.display = '';
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
