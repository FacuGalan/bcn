# Deploy Reverb (WebSockets) — Server de producción

> Este documento describe los pasos necesarios para activar Laravel Reverb en
> el server de producción de forma **segura**. El objetivo: tiempo real
> instantáneo en módulos como Pedidos por Mostrador, Delivery y Mesas, sin
> abrir nuevas superficies de ataque.

## Modelo de seguridad

```
Cliente browser  ─wss://bcn.bcnsoft.com.ar──▶  Apache 2 (443, TLS Let's Encrypt)
                                                    │
                                                    │ mod_proxy_wstunnel (HTTP→WS)
                                                    ▼
                                              Reverb (127.0.0.1:8080)
                                                    │
                                                    │ broadcast → fan-out a clientes suscriptos
                                                    ▼
                                              (sin acceso a internet directo)
```

**Decisiones clave:**

1. **Reverb escucha solo en `127.0.0.1:8080`** — nunca expuesto a internet
   directo. AWS Security Group no necesita abrir puertos nuevos.
2. **Apache hace reverse proxy** sobre el cert SSL ya existente. El cliente
   conecta a `wss://bcn.bcnsoft.com.ar/app/{key}` por el puerto 443.
3. **Canales privados con auth multi-tenant** — todo canal lleva
   `comercios.{id}` y se valida que el user pertenezca al comercio antes de
   aceptar la subscripción (`routes/channels.php`).
4. **CSRF-protected auth endpoint** — `/broadcasting/auth` usa la cookie de
   sesión web; sin login válido no hay handshake.
5. **Origenes CORS limitados** — `REVERB_ALLOWED_ORIGINS` solo el dominio
   público real.

## Pre-requisitos del server (verificar primero)

```bash
# Apache version (>= 2.4)
apache2 -v

# systemd disponible
systemctl --version

# PHP CLI (>= 8.2)
php --version
```

## Paso 1 — Habilitar módulos Apache requeridos

```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite   # (suele estar ya)
sudo systemctl reload apache2
```

Verificar:

```bash
apache2ctl -M | grep -E 'proxy|wstunnel'
# debe listar: proxy_module, proxy_http_module, proxy_wstunnel_module
```

## Paso 2 — Configurar reverse proxy en el vhost SSL

Editar `/etc/apache2/sites-available/bcn.bcnsoft.com.ar-le-ssl.conf` (o el
nombre real del vhost SSL) y agregar **dentro del `<VirtualHost *:443>`**,
antes del `</VirtualHost>` de cierre:

```apache
    # === Reverb (WebSockets) ===
    # Reverb corre en 127.0.0.1:8080 (no expuesto a internet).
    # Apache hace el handshake WSS sobre el cert Let's Encrypt existente.

    # Reescribir Upgrade ws-> http para mod_proxy
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /app/(.*)            ws://127.0.0.1:8080/app/$1 [P,L]
    RewriteRule /apps/(.*)/events    http://127.0.0.1:8080/apps/$1/events [P,L]

    # Fallback HTTP proxy (Reverb expone endpoints HTTP para broadcast desde servidor)
    ProxyPass        /app   http://127.0.0.1:8080/app
    ProxyPassReverse /app   http://127.0.0.1:8080/app
    ProxyPass        /apps  http://127.0.0.1:8080/apps
    ProxyPassReverse /apps  http://127.0.0.1:8080/apps
```

Recargar Apache:

```bash
sudo apache2ctl configtest    # validar sintaxis
sudo systemctl reload apache2
```

## Paso 3 — Variables de entorno en producción

**Generar nuevas credenciales** (NO reusar las de local):

```bash
cd /var/www/bcn_pymes   # o donde esté el proyecto
php artisan reverb:install --no-interaction
# Esto regenera REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET en .env
```

Editar el `.env` de producción y dejar:

```bash
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<generado>
REVERB_APP_KEY=<generado>
REVERB_APP_SECRET=<generado>

# Server: SIEMPRE 127.0.0.1, nunca el dominio público
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# CORS: solo el dominio real, NUNCA "*"
REVERB_ALLOWED_ORIGINS=bcn.bcnsoft.com.ar

# Vite: el cliente conecta al dominio público vía Apache (puerto 443)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=bcn.bcnsoft.com.ar
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Recompilar assets:

```bash
npm ci
npm run build
```

Limpiar config cache:

```bash
php artisan config:clear
php artisan config:cache
```

## Paso 4 — systemd service para Reverb

Crear `/etc/systemd/system/reverb.service`:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server (bcn_pymes)
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/bcn_pymes
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=3
StandardOutput=append:/var/log/reverb/reverb.log
StandardError=append:/var/log/reverb/reverb.error.log

# Hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
```

Crear log dir y activar:

```bash
sudo mkdir -p /var/log/reverb
sudo chown www-data:www-data /var/log/reverb

sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

## Paso 5 — Verificación end-to-end

```bash
# 1. Reverb escucha en 127.0.0.1:8080 (NO en 0.0.0.0)
sudo ss -tlnp | grep 8080
# Esperar: 127.0.0.1:8080 (NO 0.0.0.0:8080)

# 2. Apache proxy responde
curl -i https://bcn.bcnsoft.com.ar/app/<REVERB_APP_KEY>
# Esperar: 426 Upgrade Required o 400 (normal sin headers WS)

# 3. Logs sin errores
sudo journalctl -u reverb -n 50
sudo tail -50 /var/log/reverb/reverb.log
```

Test desde el browser (consola de DevTools, en la app logueada):

```javascript
window.Echo.private('comercios.1.ping')
  .listen('TenantPingEvent', (e) => console.log('Recibido', e))
```

Disparar evento desde tinker en el server:

```bash
php artisan tinker
> event(new \App\Events\Broadcasting\TenantPingEvent(1, 'hola desde server'));
```

En el browser debe verse `Recibido {message: "hola desde server", at: ...}` al
instante.

## Rotación de credenciales (cada N meses)

Si se sospecha leak de `REVERB_APP_SECRET`:

```bash
php artisan reverb:install --no-interaction   # regenera
php artisan config:clear && php artisan config:cache
npm run build                                  # rebuild con nueva key
sudo systemctl restart reverb
```

Los clientes conectados se desconectan y reconectan automáticamente con la
nueva key.

## Troubleshooting

| Síntoma | Probable causa | Fix |
|---------|---------------|-----|
| Cliente no conecta (websocket errors en consola) | mod_proxy_wstunnel no habilitado | `a2enmod proxy_wstunnel && systemctl reload apache2` |
| `Forbidden` en handshake | `REVERB_ALLOWED_ORIGINS` no incluye dominio | Agregar dominio sin protocolo |
| `Auth Error 403` al suscribir canal | User sin acceso al comercio | Verificar `hasAccessToComercio` en BD |
| Reverb se cae al cabo de horas | Memory leak / no restart | systemd Restart=always ya lo cubre, revisar logs |
| Conexión rechazada desde Apache | `:8080` no escuchando o systemd caído | `systemctl status reverb` |
| Cliente conecta pero no recibe eventos | Canal mal nombrado o subscripción a otro tenant | DevTools → Network → WS → ver mensajes; comparar `comercios.{id}` |

## Checklist post-deploy

- [ ] `ss -tlnp` confirma 127.0.0.1:8080, no 0.0.0.0:8080
- [ ] AWS Security Group SIN cambios (no se abrió puerto 8080 al mundo)
- [ ] `https://bcn.bcnsoft.com.ar/app/<KEY>` responde 426/400 (proxy OK)
- [ ] systemd `reverb.service` activo y reinicia al matar el proceso
- [ ] Test manual desde browser: evento llega < 1s
- [ ] `php artisan test --filter=TenantBroadcastSmokeTest` verde
- [ ] Logs en `/var/log/reverb/` rotan (configurar logrotate si crece mucho)

## Referencias

- Docs Laravel Reverb: https://laravel.com/docs/reverb
- Apache mod_proxy_wstunnel: https://httpd.apache.org/docs/2.4/mod/mod_proxy_wstunnel.html
- Channels privados Laravel: https://laravel.com/docs/broadcasting#authorizing-channels
