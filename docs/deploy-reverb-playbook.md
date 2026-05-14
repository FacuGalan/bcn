# Playbook — Deploy Reverb a server de producción

> **Instrucciones para Claude Code corriendo en el server.**
> Este documento es la versión ejecutable de `docs/deploy-reverb.md`, con la
> ruta real del server (`/var/www/html/bcn`) y comandos listos para copiar.
>
> **Objetivo:** activar tiempo real (Pedidos Mostrador Kanban + lista en vivo)
> en producción de forma segura. Reverb queda escuchando solo en `127.0.0.1`
> y se accede a través de Apache vía `wss://bcn.bcnsoft.com.ar/app/...`.
>
> Si algo falla en algún paso, **PARÁ**, reportá al usuario qué pasó, y no
> sigas hasta tener confirmación. Es preferible un deploy a medias revertido
> que dejar Reverb expuesto al mundo o Apache rebotando.

## Contexto previo

Lo mergeado en master que se está desplegando:

- **#83** — Infra Reverb multi-tenant (`TenantBroadcastEvent`, channels privados)
- **#84** — Lista de pedidos en vivo + acciones rápidas
- **#85** — Vista Kanban con drag&drop
- **#86** — Fixes: `ShouldBroadcastNow` (broadcast inmediato) + Alpine kanban desde bundle

El último commit de master debería ser `15ceddb chore: release 0.1.6` o
posterior.

---

## Pre-flight (verificar antes de tocar nada)

```bash
# Estoy en el proyecto
cd /var/www/html/bcn
pwd                                    # debe imprimir /var/www/html/bcn

# Apache, systemd, PHP disponibles
apache2 -v                             # >= 2.4
systemctl --version
php --version                          # >= 8.2

# Versión actual del proyecto antes del pull
git log --oneline -1
git status                             # debe estar limpio
```

**Si `git status` no está limpio:** reportar al usuario y PARAR. No deployar
sobre cambios sin commitear.

**Si la ruta no es `/var/www/html/bcn`:** reportar al usuario.

---

## Paso 1 — Pull del código nuevo

```bash
cd /var/www/html/bcn
git fetch origin
git checkout master
git pull origin master

# Verificar que se trajeron los commits de Reverb
git log --oneline -8
# Esperar ver: #86, #85, #84, #83 entre los últimos commits
```

Reportar al usuario el commit en el que quedó (`git log -1 --oneline`).

---

## Paso 2 — Dependencias

```bash
cd /var/www/html/bcn

# PHP
composer install --no-dev --optimize-autoloader

# Node + assets
npm ci
# (no correr build todavía — depende de las env vars del Paso 4)
```

Si `composer install` se queja por permisos, probablemente haya que correrlo
como el usuario dueño del proyecto (suele ser `www-data` o el usuario de
deploy). Preguntar al usuario antes de `sudo`.

---

## Paso 3 — Habilitar módulos Apache

```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite      # suele estar ya
sudo systemctl reload apache2

# Verificar
apache2ctl -M | grep -E 'proxy|wstunnel'
# debe listar: proxy_module, proxy_http_module, proxy_wstunnel_module
```

---

## Paso 4 — Reverse proxy en el vhost SSL

Buscar el vhost SSL (Let's Encrypt suele crear un archivo `*-le-ssl.conf`):

```bash
ls /etc/apache2/sites-available/ | grep -E 'ssl|443|bcn'
```

Reportar al usuario el nombre del archivo encontrado y pedir confirmación.
Probablemente sea algo como `bcn.bcnsoft.com.ar-le-ssl.conf` o
`000-default-le-ssl.conf`.

Una vez confirmado, hacer **backup** y editar:

```bash
# Backup
sudo cp /etc/apache2/sites-available/<ARCHIVO>.conf \
        /etc/apache2/sites-available/<ARCHIVO>.conf.bak-pre-reverb

# Editar el archivo
sudo nano /etc/apache2/sites-available/<ARCHIVO>.conf
```

Agregar **dentro** del bloque `<VirtualHost *:443>`, antes del `</VirtualHost>`:

```apache
    # === Reverb (WebSockets) ===
    # Reverb corre en 127.0.0.1:8080 (no expuesto a internet).
    # Apache hace el handshake WSS sobre el cert Let's Encrypt existente.

    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /app/(.*)            ws://127.0.0.1:8080/app/$1 [P,L]
    RewriteRule /apps/(.*)/events    http://127.0.0.1:8080/apps/$1/events [P,L]

    ProxyPass        /app   http://127.0.0.1:8080/app
    ProxyPassReverse /app   http://127.0.0.1:8080/app
    ProxyPass        /apps  http://127.0.0.1:8080/apps
    ProxyPassReverse /apps  http://127.0.0.1:8080/apps
```

Validar y recargar:

```bash
sudo apache2ctl configtest
# debe imprimir: Syntax OK

sudo systemctl reload apache2
sudo systemctl status apache2 --no-pager | head -20
```

**Si `configtest` falla:** restaurar el backup y reportar:

```bash
sudo cp /etc/apache2/sites-available/<ARCHIVO>.conf.bak-pre-reverb \
        /etc/apache2/sites-available/<ARCHIVO>.conf
sudo systemctl reload apache2
```

---

## Paso 5 — Variables de entorno

```bash
cd /var/www/html/bcn

# Backup del .env actual
sudo cp .env .env.bak-pre-reverb-$(date +%Y%m%d-%H%M%S)

# Generar credenciales Reverb nuevas
php artisan reverb:install --no-interaction
```

`reverb:install` actualiza `REVERB_APP_ID`, `REVERB_APP_KEY`,
`REVERB_APP_SECRET` en `.env`. Verificar que esos 3 ya están seteados:

```bash
grep -E '^REVERB_APP_' .env
```

Ahora **editar** `.env` y dejar estas variables exactas. Si alguna falta,
agregarla:

```bash
sudo nano .env
```

```env
BROADCAST_CONNECTION=reverb

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

> ⚠️ `REVERB_HOST` debe quedar en `127.0.0.1`. **NO** poner el dominio
> público acá: eso haría que Reverb escuche en 0.0.0.0 y quede expuesto.

Verificar:

```bash
grep -E '^(REVERB|VITE_REVERB|BROADCAST_CONNECTION)' .env
```

Pegar la salida al usuario para que revise (sin pegar los secrets completos —
truncar APP_SECRET).

---

## Paso 6 — Build de assets

```bash
cd /var/www/html/bcn
npm run build
```

El build empaqueta las `VITE_REVERB_*` en el JS público. Si las cambiás
después, hay que rebuildear.

---

## Paso 7 — Cachés de Laravel

```bash
cd /var/www/html/bcn
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Paso 8 — Servicio systemd para Reverb

Crear `/etc/systemd/system/reverb.service`:

```bash
sudo nano /etc/systemd/system/reverb.service
```

Contenido (¡ojo con el `WorkingDirectory` que apunta a la ruta real!):

```ini
[Unit]
Description=Laravel Reverb WebSocket Server (bcn_pymes)
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/bcn
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=3
StandardOutput=append:/var/log/reverb/reverb.log
StandardError=append:/var/log/reverb/reverb.error.log

NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
```

> Si el usuario del proyecto **no es** `www-data` (puede ser `ubuntu`,
> `deploy`, etc.), ajustá `User=` y `Group=`. Verificalo con:
> ```bash
> stat -c '%U:%G' /var/www/html/bcn
> ```

Crear el directorio de logs y arrancar:

```bash
sudo mkdir -p /var/log/reverb
sudo chown www-data:www-data /var/log/reverb   # ajustar si user es otro

sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb --no-pager
```

Esperar ver `Active: active (running)`. Si no:

```bash
sudo journalctl -u reverb -n 50 --no-pager
sudo tail -50 /var/log/reverb/reverb.error.log
```

Pegar al usuario y PARAR.

---

## Paso 9 — Verificación end-to-end

### 9.1 — Reverb escucha SOLO en localhost

```bash
sudo ss -tlnp | grep 8080
```

**Debe** verse `127.0.0.1:8080`. **NO debe** verse `0.0.0.0:8080` ni `*:8080`.
Si aparece `0.0.0.0`, parar Reverb y revisar el systemd (`--host=127.0.0.1`).

### 9.2 — AWS Security Group sin cambios

Confirmar con el usuario que **no se abrió** el puerto 8080 en el Security
Group de la EC2. No tiene que cambiar nada de red.

### 9.3 — Apache proxy responde

```bash
# Tomar la APP_KEY del .env
APP_KEY=$(grep '^REVERB_APP_KEY=' /var/www/html/bcn/.env | cut -d= -f2)

curl -i "https://bcn.bcnsoft.com.ar/app/${APP_KEY}"
# Esperar: HTTP 426 Upgrade Required, o 400 Bad Request
# (normal sin headers WebSocket completos)
```

Si responde 404, el proxy no está pasando — revisar el vhost del paso 4.

### 9.4 — Smoke test del código

```bash
cd /var/www/html/bcn
php artisan test --filter=TenantBroadcastSmokeTest
```

Debe quedar **verde**. Si falla, no es problema del server, es del código —
reportar al usuario.

### 9.5 — Test manual en el browser

Pedir al usuario que:

1. Entre a `https://bcn.bcnsoft.com.ar` y haga login con un comercio (anotar
   el `comercio_id`, p.ej. 1).
2. Abra DevTools → Console y pegue:

   ```javascript
   window.Echo.private('comercios.1.ping')
     .listen('TenantPingEvent', (e) => console.log('Recibido', e))
   ```

3. Verifique en DevTools → Network → WS que hay una conexión a
   `wss://bcn.bcnsoft.com.ar/app/...` con status 101 Switching Protocols.

Mientras tanto, en el server:

```bash
cd /var/www/html/bcn
php artisan tinker
```

Dentro de tinker:

```php
event(new \App\Events\Broadcasting\TenantPingEvent(1, 'hola desde server'));
```

(Ajustar el `1` al `comercio_id` real.)

El usuario debe ver `Recibido {message: "hola desde server", at: ...}` en
la consola del browser **al instante**.

### 9.6 — Test funcional Pedidos Mostrador

Pedir al usuario que abra **Pedidos por Mostrador** en dos ventanas (mismo
comercio, mismo user u otros del mismo comercio):

- Crear un pedido en una ventana → debe aparecer al instante en la otra.
- Mover una columna en Kanban → debe reflejarse en la otra ventana.
- Marcar Entregar → debe verse al instante.

---

## Paso 10 — Checklist final

Recorrer y reportar al usuario el resultado de cada uno:

- [ ] `ss -tlnp` confirma `127.0.0.1:8080` (no `0.0.0.0`)
- [ ] AWS Security Group sin cambios
- [ ] `https://bcn.bcnsoft.com.ar/app/<KEY>` responde 426/400
- [ ] `systemctl status reverb` activo
- [ ] `php artisan test --filter=TenantBroadcastSmokeTest` verde
- [ ] Test browser: evento llega < 1s
- [ ] Pedidos en vivo entre dos ventanas funciona

---

## Rollback (si algo no se puede arreglar en el momento)

```bash
# 1. Detener Reverb
sudo systemctl stop reverb
sudo systemctl disable reverb

# 2. Volver el .env
cd /var/www/html/bcn
sudo cp .env.bak-pre-reverb-* .env   # el backup más reciente
php artisan config:clear && php artisan config:cache

# 3. Volver el vhost
sudo cp /etc/apache2/sites-available/<ARCHIVO>.conf.bak-pre-reverb \
        /etc/apache2/sites-available/<ARCHIVO>.conf
sudo apache2ctl configtest && sudo systemctl reload apache2

# 4. (Opcional) revertir el código
# git reset --hard <commit-anterior>   # SOLO si el usuario lo pide
```

La app sigue funcionando sin tiempo real: los componentes Livewire siguen
mostrando datos al refrescar manual.

---

## Qué reportar al usuario al terminar

1. Commit final desplegado (`git log -1 --oneline`).
2. Resultado de cada item del checklist final.
3. Logs relevantes si algo no quedó del todo verde:
   - `sudo journalctl -u reverb -n 30 --no-pager`
   - `sudo tail -30 /var/log/apache2/error.log`
   - `tail -30 /var/www/html/bcn/storage/logs/laravel.log`

---

## Referencias

- Doc general (template): `docs/deploy-reverb.md`
- Memoria de la serie: `project_reverb_tiempo_real.md`
- Aprendizaje crítico: `feedback_should_broadcast_now.md` (broadcast inmediato)
