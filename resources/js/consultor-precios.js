/**
 * Consultor de precios público (pantalla Clase B remota, sin sesión).
 *
 * - Resuelve el token: de la URL (QR), del código corto (TV/tablet) o de localStorage.
 * - Carga la personalización vía el endpoint de config acotado al token.
 * - Busca artículos (nombre o código de barras) contra el endpoint público y
 *   muestra el precio de lista base + las promos vigentes (solo nombres).
 *
 * No usa Reverb: es búsqueda a demanda (request/response), no tiempo real.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b, RF-05).
 */

const STORAGE_KEY = 'bcn_consultor_token';
const boot = window.__CONSULTOR__ || {};
const I18N = boot.i18n || {};

const $ = (sel) => document.querySelector(sel);
const elVincular = $('#vincular');
const elPantalla = $('#pantalla');
const elInput = $('#cp-input');
const elResultados = $('#cp-resultados');

let tokenActivo = null;
const money = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

// ───────────────────────── Personalización ─────────────────────────
function aplicarConfig(data) {
    const cfg = data.config || {};
    const root = document.documentElement;
    if (cfg.color_fondo) root.style.setProperty('--cp-bg', cfg.color_fondo);
    if (cfg.color_acento) root.style.setProperty('--cp-acento', cfg.color_acento);

    const titulo = $('#cp-titulo');
    if (titulo) titulo.textContent = cfg.titulo || 'Consultor de precios';
    const sucursal = $('#cp-sucursal');
    if (sucursal) sucursal.textContent = data.sucursal?.nombre || '';

    const logo = $('#cp-logo');
    if (logo) {
        if (cfg.mostrar_logo && data.logo) {
            logo.src = data.logo;
            // 'block' explícito: '' revertiría al CSS (display:none).
            logo.style.display = 'block';
        } else {
            logo.style.display = 'none';
        }
    }
}

// ───────────────────────── Render de resultados ─────────────────────────
function estado(texto) {
    elResultados.innerHTML = '';
    const div = document.createElement('div');
    div.className = 'cp-estado';
    div.textContent = texto;
    elResultados.appendChild(div);
}

function renderResultados(items) {
    if (!items.length) {
        estado(I18N.sinResultados || 'Sin resultados');
        return;
    }

    elResultados.innerHTML = '';
    items.forEach((it) => {
        const item = document.createElement('div');
        item.className = 'cp-item';

        const info = document.createElement('div');
        info.className = 'cp-item-info';

        const nombre = document.createElement('div');
        nombre.className = 'cp-item-nombre';
        nombre.textContent = it.nombre;
        info.appendChild(nombre);

        if (it.unidad) {
            const unidad = document.createElement('div');
            unidad.className = 'cp-item-unidad';
            unidad.textContent = it.unidad;
            info.appendChild(unidad);
        }

        if (Array.isArray(it.promos) && it.promos.length) {
            const promos = document.createElement('div');
            promos.className = 'cp-promos';
            it.promos.forEach((p) => {
                const chip = document.createElement('span');
                chip.className = 'cp-promo';
                chip.textContent = p;
                promos.appendChild(chip);
            });
            info.appendChild(promos);
        }

        const precio = document.createElement('div');
        precio.className = 'cp-item-precio';
        precio.textContent = it.precio != null ? money.format(it.precio) : (I18N.sinPrecio || '—');

        item.appendChild(info);
        item.appendChild(precio);
        elResultados.appendChild(item);
    });
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

let buscarSeq = 0;
async function buscar(q) {
    const query = q.trim();
    if (query.length < 2) {
        estado(I18N.inicial || '');
        return;
    }

    const seq = ++buscarSeq;
    estado(I18N.buscando || '…');

    try {
        const resp = await fetch(`/clase-b/precios/${tokenActivo}/buscar?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!resp.ok) throw new Error('buscar_error');
        const data = await resp.json();
        // Ignorar respuestas viejas (el usuario siguió tipeando).
        if (seq !== buscarSeq) return;
        renderResultados(data.resultados || []);
    } catch (e) {
        if (seq === buscarSeq) estado(I18N.sinResultados || '');
    }
}

// Debounce para no pegarle al server en cada tecla; un escáner de código de
// barras tipea rápido y termina con Enter (se busca igual por el debounce).
let debounceId = null;
function buscarDebounced(q) {
    clearTimeout(debounceId);
    debounceId = setTimeout(() => buscar(q), 300);
}

async function iniciar(token) {
    try {
        const data = await cargarConfig(token);
        tokenActivo = token;
        localStorage.setItem(STORAGE_KEY, token);
        aplicarConfig(data);
        elVincular.style.display = 'none';
        elPantalla.style.display = 'flex';
        estado(I18N.inicial || '');
        if (elInput) elInput.focus();
    } catch (err) {
        localStorage.removeItem(STORAGE_KEY);
        mostrarVinculacion();
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
    if (elInput) {
        elInput.addEventListener('input', (e) => buscarDebounced(e.target.value));
        elInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceId);
                buscar(elInput.value);
            }
        });
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
