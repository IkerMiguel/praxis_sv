#!/bin/sh
# Prepara el .env de la API dentro del contenedor (se ejecuta antes de iniciar Nginx/PHP-FPM).
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi
