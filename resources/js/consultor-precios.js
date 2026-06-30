/**
 * Consultor de precios público (pantalla Clase B remota, sin sesión).
 *
 * Orientado a SCANNER de código de barras: el input está oculto y siempre
 * enfocado, así el escaneo (que se comporta como un teclado) entra ahí. Al
 * escanear se muestra, grande y centrado, el nombre + precio + promociones
 * activas del artículo, durante N segundos (configurable), y vuelve a la frase
 * de espera para el siguiente escaneo.
 *
 * No usa Reverb: es búsqueda a demanda (request/response).
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b, RF-05).
 */

const STORAGE_KEY = 'bcn_consultor_token';
const boot = window.__CONSULTOR__ || {};
const I18N = boot.i18n || {};

const $ = (sel) => document.querySelector(sel);
const elVincular = $('#vincular');
const elPantalla = $('#pantalla');
const elDesactivado = $('#desactivado');
const elInput = $('#cp-input');
const elIdle = $('#cp-idle');
const elResult = $('#cp-result');
const elNotFound = $('#cp-notfound');
const elNombre = $('#cp-nombre');
const elPrecio = $('#cp-precio');
const elPromosTitulo = $('#cp-promos-titulo');
const elPromos = $('#cp-promos');
const elNfCod = $('#cp-nf-cod');

let tokenActivo = null;
let focoActivo = false;
let duracionMs = 5000;
let timerResultado = null;
const money = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

// ───────────────────────── Audio + pantalla completa ─────────────────────────
// Política de autoplay: el AudioContext se crea/reanuda recién con un gesto del
// usuario (toque o el primer escaneo, que llega como keydown real). El mismo
// gesto entra a pantalla completa (no se puede disparar F11 desde JS, pero sí la
// Fullscreen API dentro de un gesto). Se hace una sola vez.
let audioCtx = null;
let interaccionLista = false;

function activarInteraccion() {
    if (interaccionLista) return;
    interaccionLista = true;
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    } catch (e) {
        audioCtx = null;
    }
    entrarPantallaCompleta();
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

// Chime de "éxito": arpegio ascendente (Do–Mi–Sol) breve y luminoso, distinto
// del chime de atención del llamador. Suena al encontrar un precio.
function playSuccess() {
    if (!audioCtx) return;
    const now = audioCtx.currentTime;
    [523.25, 659.25, 783.99].forEach((freq, i) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'triangle';
        osc.frequency.value = freq;
        const t = now + i * 0.085;
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(0.32, t + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
        osc.connect(gain).connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + 0.36);
    });
}

// ───────────────────────── Personalización ─────────────────────────
function aplicarConfig(data) {
    const cfg = data.config || {};
    const root = document.documentElement;
    if (cfg.color_fondo) root.style.setProperty('--cp-bg', cfg.color_fondo);
    if (cfg.color_acento) root.style.setProperty('--cp-acento', cfg.color_acento);

    const titulo = $('#cp-titulo');
    if (titulo) titulo.textContent = cfg.titulo || 'Consultor de precios';
    if (elIdle) elIdle.textContent = cfg.mensaje_idle || 'Escanee un artículo';

    const dur = parseInt(cfg.duracion_resultado, 10);
    duracionMs = (Number.isFinite(dur) && dur > 0 ? dur : 5) * 1000;

    const logo = $('#cp-logo');
    if (logo) {
        if (cfg.mostrar_logo && data.logo) {
            logo.src = data.logo;
            logo.style.display = 'block';
        } else {
            logo.style.display = 'none';
        }
    }
}

// ───────────────────────── Estados de la pantalla ─────────────────────────
function mostrarIdle() {
    elResult.style.display = 'none';
    elNotFound.style.display = 'none';
    elIdle.style.display = 'block';
}

function mostrarResultado(item) {
    elIdle.style.display = 'none';
    elNotFound.style.display = 'none';

    elNombre.textContent = item.nombre;
    elPrecio.textContent = item.precio != null ? money.format(item.precio) : (I18N.sinPrecio || '');

    elPromos.innerHTML = '';
    const promos = Array.isArray(item.promos) ? item.promos : [];
    if (promos.length) {
        elPromosTitulo.style.display = 'block';
        promos.forEach((p) => {
            const chip = document.createElement('span');
            chip.className = 'cp-promo';
            chip.textContent = p;
            elPromos.appendChild(chip);
        });
    } else {
        elPromosTitulo.style.display = 'none';
    }

    elResult.style.display = 'flex';
    playSuccess();
    programarVueltaAIdle();
}

function mostrarNoEncontrado(codigo) {
    elIdle.style.display = 'none';
    elResult.style.display = 'none';
    if (elNfCod) elNfCod.textContent = codigo || '';
    elNotFound.style.display = 'flex';
    programarVueltaAIdle();
}

function programarVueltaAIdle() {
    clearTimeout(timerResultado);
    timerResultado = setTimeout(mostrarIdle, duracionMs);
}

// ───────────────────────── Búsqueda (por escaneo) ─────────────────────────
let buscarSeq = 0;
async function buscar(codigo) {
    const q = codigo.trim();
    if (q.length < 2) return;

    const seq = ++buscarSeq;
    try {
        const resp = await fetch(`/clase-b/precios/${tokenActivo}/buscar?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!resp.ok) throw new Error('buscar_error');
        const data = await resp.json();
        if (seq !== buscarSeq) return; // llegó un escaneo más nuevo

        const resultados = data.resultados || [];
        if (resultados.length) {
            mostrarResultado(resultados[0]);
        } else {
            mostrarNoEncontrado(q);
        }
    } catch (e) {
        if (seq === buscarSeq) mostrarNoEncontrado(q);
    }
}

// ───────────────────────── Foco del scanner ─────────────────────────
// El input debe estar SIEMPRE enfocado para capturar el escaneo. Si pierde el
// foco (toque accidental, etc.) lo recuperamos.
function reenfocar() {
    if (focoActivo && elInput && document.activeElement !== elInput) {
        elInput.focus({ preventScroll: true });
    }
}

// ───────────────────────── Conexión ─────────────────────────
async function cargarConfig(token) {
    const resp = await fetch(`/clase-b/precios/${token}/config`, {
        headers: { Accept: 'application/json' },
    });
    if (resp.status === 404) throw new Error('token_invalido');
    if (!resp.ok) throw new Error('config_error');
    return resp.json();
}

async function iniciar(token) {
    try {
        const data = await cargarConfig(token);
        tokenActivo = token;
        localStorage.setItem(STORAGE_KEY, token);

        // La sucursal apagó el consultor: cartel claro en vez de quedar colgado
        // tras vincular. No habilitamos el foco/escáner (buscar() está 404).
        if (data.activo === false) {
            focoActivo = false;
            mostrarDesactivado();
            return;
        }

        aplicarConfig(data);
        elVincular.style.display = 'none';
        if (elDesactivado) elDesactivado.style.display = 'none';
        elPantalla.style.display = 'flex';
        mostrarIdle();
        focoActivo = true;
        reenfocar();
    } catch (err) {
        localStorage.removeItem(STORAGE_KEY);
        focoActivo = false;
        mostrarVinculacion();
    }
}

function mostrarVinculacion() {
    elPantalla.style.display = 'none';
    if (elDesactivado) elDesactivado.style.display = 'none';
    elVincular.style.display = 'flex';
}

function mostrarDesactivado() {
    elPantalla.style.display = 'none';
    elVincular.style.display = 'none';
    if (elDesactivado) elDesactivado.style.display = 'flex';
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
    // Primera interacción (toque o el primer escaneo): desbloquea el audio y
    // entra a pantalla completa. El scanner llega como keydown, así que también
    // cuenta como gesto válido para reanudar el AudioContext.
    document.addEventListener('pointerdown', activarInteraccion);
    document.addEventListener('keydown', activarInteraccion);

    if (elInput) {
        // El scanner termina con Enter (sufijo más común). Como fallback, si no
        // hay Enter, un pequeño silencio tras el último carácter cierra el código.
        let idleId = null;
        const procesar = () => {
            const val = elInput.value;
            elInput.value = '';
            if (val) buscar(val);
        };
        elInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(idleId);
                procesar();
            }
        });
        elInput.addEventListener('input', () => {
            clearTimeout(idleId);
            idleId = setTimeout(procesar, 120);
        });
        // Mantener el foco en el input del scanner.
        elInput.addEventListener('blur', () => setTimeout(reenfocar, 50));
        document.addEventListener('click', reenfocar);
        document.addEventListener('keydown', reenfocar);
    }

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
                if (error) error.textContent = I18N.codigoInvalido || 'Código inválido';
            }
        });
    }

    // Prioridad de bootstrap: token de URL (QR) > código de URL > localStorage.
    if (boot.token) {
        iniciar(boot.token);
        return;
    }
    if (boot.codigo) {
        try {
            iniciar(await canjearCodigo(boot.codigo));
            return;
        } catch (e) {
            // Código de la URL inválido: caer al token ya vinculado si existe.
        }
        const guardado = localStorage.getItem(STORAGE_KEY);
        if (guardado) {
            iniciar(guardado);
        } else {
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
