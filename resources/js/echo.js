import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Configuracion de Laravel Echo sobre Reverb.
 *
 * Reverb habla el protocolo Pusher, por eso se importa pusher-js como cliente.
 * El servidor Reverb corre detras de un reverse proxy (Apache en prod) que
 * termina TLS, asi que el cliente conecta a wss://<dominio>/app/{key} y NUNCA
 * directo al puerto interno 8080.
 *
 * Variables Vite (todas se inyectan en build via .env):
 *   VITE_REVERB_APP_KEY  -> identifica la "app" en Reverb (publica, no es secret)
 *   VITE_REVERB_HOST     -> hostname publico al que conecta el cliente
 *   VITE_REVERB_PORT     -> 443 en prod (via reverse proxy), 8080 en local
 *   VITE_REVERB_SCHEME   -> https en prod (=> wss), http en local (=> ws)
 *
 * Auth: las suscripciones a canales privados/presence pegan a
 * /broadcasting/auth (montado automaticamente por Laravel). La cookie de sesion
 * web autentica al user, y la closure de routes/channels.php decide si el
 * comercio del canal coincide con los del user.
 */

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
