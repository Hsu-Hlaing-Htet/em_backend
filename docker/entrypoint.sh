#!/bin/sh
set -e

PORT="${PORT:-8000}"

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your environment or .env file." >&2
    exit 1
fi

php artisan config:cache --no-ansi
php artisan route:cache --no-ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-ansi
fi

if [ "$1" = "serve" ]; then
    exec php artisan serve --host=0.0.0.0 --port="${PORT}"
fi

exec "$@"
