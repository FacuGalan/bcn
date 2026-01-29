const CACHE_NAME = 'bcn-pymes-v4';
const OFFLINE_URL = '/offline.html';

// Recursos para cachear inmediatamente
const PRECACHE_ASSETS = [
    '/',
    '/dashboard',
    '/offline.html',
    '/manifest.json'
];

// Instalar Service Worker
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Cache abierto');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activar Service Worker
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Eliminando cache antiguo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Interceptar peticiones
self.addEventListener('fetch', (event) => {
    // Solo manejar peticiones GET
    if (event.request.method !== 'GET') {
        return;
    }

    // Ignorar peticiones a livewire (necesitan ser siempre frescas)
    if (event.request.url.includes('/livewire/')) {
        return;
    }

    // No cachear el manifest (siempre fresco)
    if (event.request.url.includes('/manifest.json')) {
        return;
    }

    // Estrategia: Network First con fallback a cache
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Si la respuesta es válida, guardarla en cache
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                }
                return response;
            })
            .catch(() => {
                // Si falla la red, buscar en cache
                return caches.match(event.request)
                    .then((response) => {
                        if (response) {
                            return response;
                        }
                        // Si es una navegación, mostrar página offline
                        if (event.request.mode === 'navigate') {
                            return caches.match(OFFLINE_URL);
                        }
                        return new Response('Offline', {
                            status: 503,
                            statusText: 'Service Unavailable'
                        });
                    });
            })
    );
});

// Manejar mensajes
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
});
