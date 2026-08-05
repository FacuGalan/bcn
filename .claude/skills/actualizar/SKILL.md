---
name: actualizar
description: Deploy de producción del ecosistema BCN en el server oficial. Usar cuando el usuario diga "actualiza", "actualizar el servidor", "bajá las últimas versiones", "hacé el deploy" — baja master de AMBOS proyectos (core bcn y bcn-tienda) desde GitHub y corre el deploy de cada uno, EN ORDEN (core primero, tienda después).
---

# Actualizar producción (core + tienda)

Deploy de rutina de AMBOS proyectos en el server oficial. El orden es
OBLIGATORIO: **CORE primero, TIENDA después** — la tienda consume el contrato
del core; al revés puede quedar pidiendo campos que todavía no existen.

## Pre-chequeos (abortar y avisar si alguno falla)

1. Confirmar que esto ES el server oficial: deben existir
   `/var/www/html/bcn` y `/var/www/html/bcn-tienda`. Si no existen, esta
   skill no aplica (es solo para producción) — avisar y no tocar nada.
2. En cada repo: `git -C <dir> status --porcelain` limpio y rama `master`.
   Con cambios locales o rama distinta: FRENAR y preguntar al usuario.
3. Anticipar qué baja:
   ```bash
   git -C /var/www/html/bcn fetch -q && git -C /var/www/html/bcn log --oneline HEAD..origin/master
   git -C /var/www/html/bcn-tienda fetch -q && git -C /var/www/html/bcn-tienda log --oneline HEAD..origin/master
   ```
   Contarle al usuario qué commits vienen en cada repo. Si un repo no tiene
   nada nuevo, se puede saltear su deploy (deploy.sh igual es idempotente).
   Si algún commit menciona variables de `.env` nuevas (leer los mensajes),
   setearlas ANTES de la verificación final.

## 1. Core

```bash
cd /var/www/html/bcn
DEPLOY_KEEP_DEV=1 ./deploy.sh
```

El script hace: backup rotativo del `.env` → `git pull` → `composer install
--no-scripts` + discover → **migraciones** (¡mirar el output: las tenant
iteran TODOS los comercios!) → reset de permisos/menú → build de Vite →
`deploy:warm` → reload del SAPI (apache2).

**Si una migración falla: FRENAR ahí** — no seguir con la tienda, mostrar el
error completo al usuario.

Verificación:
```bash
curl -s -o /dev/null -w "core API -> %{http_code}\n" https://bcn.bcnsoft.com.ar/api/v1/tiendas
```

## 2. Tienda

```bash
cd /var/www/html/bcn-tienda
DEPLOY_KEEP_DEV=1 ./deploy.sh
```

Verificación (el slug real sale de la API del core):
```bash
S=$(curl -s https://bcn.bcnsoft.com.ar/api/v1/tiendas | php -r 'echo json_decode(stream_get_contents(STDIN))->data[0]->slug;')
for p in "tienda/$S" login; do
  curl -s -o /dev/null -w "/$p -> %{http_code} %{time_total}s\n" "https://tienda.bcnsoft.com.ar/$p"
done
```

## Reglas fijas (heredadas de los playbooks — NO negociables)

- **NUNCA** `php artisan optimize` ni `config:cache`, en ninguno de los dos.
- El SAPI real es **mod_php 8.3**: el reload de apache2 del script alcanza.
- "Deployé y no cambió nada" → `php artisan optimize:clear` + warm y probar
  en incógnito (suele ser el service worker del cliente), NO tocar OPcache.
- Detalle completo y troubleshooting:
  `bcn-tienda/.claude/docs/deploy-playbook.md` y el playbook del core.

## Reporte final

Resumir: rango de commits aplicado por repo (`antes..después`), migraciones
que corrieron, y el resultado de las verificaciones HTTP (código + tiempo).
