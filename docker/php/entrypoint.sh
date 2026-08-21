#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

tries=0
until composer install --no-interaction --prefer-dist --no-progress; do
    tries=$((tries+1))
    if [ "$tries" -ge 3 ]; then
        echo "composer install failed after $tries attempts" >&2
        exit 1
    fi
    echo "composer install failed, retrying ($tries/3)..." >&2
    sleep 3
done

if ! grep -q "^APP_KEY=base64" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8080 --no-reload
