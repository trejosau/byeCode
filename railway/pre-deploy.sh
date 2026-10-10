#!/usr/bin/env bash
set -Eeuo pipefail
cd /app
if [[ "${APP_ENV:-}" != "production" || -z "${APP_KEY:-}" ]]; then
    echo 'Production environment and existing APP_KEY are required.' >&2
    exit 1
fi
php artisan config:clear
php artisan byecode:setup --force --no-interaction
