#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env && [ -z "${APP_KEY}" ]; then
    php artisan key:generate --force --no-interaction
fi

if [ -n "${DB_HOST}" ]; then
    echo "Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT:-5432}..."
    until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-praxis}" > /dev/null 2>&1; do
        sleep 1
    done
fi

php artisan storage:link --no-interaction > /dev/null 2>&1 || true

exec "$@"
