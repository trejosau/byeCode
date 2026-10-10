#!/usr/bin/env bash
set -Eeuo pipefail
cd /app

if [[ "${APP_ENV:-}" != "production" || "${APP_DEBUG:-true}" != "false" || -z "${APP_KEY:-}" ]]; then
    echo 'Production requires APP_ENV=production, APP_DEBUG=false and an existing APP_KEY.' >&2
    exit 1
fi
if [[ "${RAILWAY_VOLUME_MOUNT_PATH:-}" != '/app/storage/app' ]]; then
    echo 'The persistent volume must be mounted at /app/storage/app.' >&2
    exit 1
fi

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
rm -f public/hot
php artisan config:clear
php artisan storage:link --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
chown -R www-data:www-data storage/framework bootstrap/cache

# Migrations run once in Railway's pre-deploy step. Never seed or clear shared cache here.
exec /usr/bin/supervisord -n -c /app/railway/supervisord.conf
