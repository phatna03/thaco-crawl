#!/bin/bash
# Railpack Laravel startup (thay script mặc định khi file này nằm ở root repo).
set -e

if [ "$IS_LARAVEL" = "true" ]; then
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/framework/cache/data
    mkdir -p storage/logs
    mkdir -p bootstrap/cache
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true

    if [ "$RAILPACK_SKIP_MIGRATIONS" != "true" ]; then
        echo "Running migrations and seeding database ..."
        php artisan migrate --force
    fi

    php artisan storage:link 2>/dev/null || true
    php artisan optimize:clear
    php artisan optimize

    echo "Starting Laravel server ..."
fi

exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
