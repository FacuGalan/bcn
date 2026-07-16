#!/usr/bin/env bash
#
# Deploy a producción — automatiza el flujo de .claude/docs/deploy-playbook.md.
# Correr desde la raíz del proyecto en el server: ./deploy.sh
#
# Todo el cuerpo vive dentro de main() y se invoca al final: bash lee el
# script de a pedazos mientras ejecuta, y el git pull del paso 1 puede
# actualizar ESTE archivo — con main() el script completo queda parseado
# antes de ejecutar nada (la versión nueva rige recién en el próximo deploy).

set -euo pipefail

main() {
    cd "$(dirname "$0")"

    echo "==> [1/6] git pull (el hook post-merge corre optimize:clear)"
    git pull origin master

    # Composer SIN scripts (Gotcha 4 del playbook): el hook post-autoload-dump
    # bootea Laravel y carga TODA la config antes de terminar de instalar; si
    # un paquete nuevo está referenciado en config/ (caso Sanctum, #161) el
    # install aborta con "Class not found". Se instala sin scripts y después
    # se corre a mano lo que esos scripts hacían, ya con el vendor completo.
    echo "==> [2/6] composer install (--no-scripts) + package:discover"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-scripts
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
    COMPOSER_ALLOW_SUPERUSER=1 php artisan package:discover --ansi

    echo "==> [3/6] migraciones (incluye tenant: iteran TODOS los comercios — revisar el output)"
    php artisan migrate --force

    # Gotcha 5 del playbook: los permisos/menú se cachean por usuario en el
    # cache STORE de la app (TTL 5 min) + la caché de Spatie. Ni deploy:warm
    # ni optimize:clear los tocan → sin esto, los ítems de menú nuevos de una
    # migración no aparecen (ni como admin) hasta 5 minutos después.
    echo "==> [3b] invalidar caché de permisos/menú"
    php artisan permission:cache-reset || true
    php artisan cache:clear

    echo "==> [4/6] build del front (public/build está gitignored; cambia el hash del SW)"
    npm ci && npm run build

    echo "==> [5/6] warm de caches SEGURAS (view+route+event+icons — NUNCA config:cache)"
    php artisan deploy:warm

    echo "==> [6/6] reload FPM (obligatorio: opcache.validate_timestamps=0)"
    sudo systemctl reload php*-fpm

    echo ""
    echo "Deploy OK. Verificación sugerida (playbook):"
    echo "  for p in \"\" app/login; do curl -s -o /dev/null -w \"/\$p -> %{http_code} %{time_total}s\\n\" https://<DOMINIO>/\$p; done"
}

main "$@"
