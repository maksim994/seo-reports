#!/bin/sh
set -e

cd /var/www/backend

if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    composer install --prefer-dist --no-interaction
fi

# Fix corrupted APP_KEY (multiple base64: segments or invalid format)
if [ -f .env ]; then
    KEY_COUNT=$(grep '^APP_KEY=' .env | grep -o 'base64:' | wc -l | tr -d ' ')
    KEY_LINE=$(grep '^APP_KEY=' .env || true)

    if [ "$KEY_COUNT" != "1" ] || ! echo "$KEY_LINE" | grep -qE '^APP_KEY="?base64:[A-Za-z0-9+/=]+="?$'; then
        echo "[entrypoint] Fixing invalid APP_KEY in .env"
        grep -v '^APP_KEY=' .env > .env.tmp && mv .env.tmp .env
        echo 'APP_KEY=' >> .env
        php artisan key:generate --force --no-interaction
        # Quote key so Docker env_file parser preserves special chars (+, =)
        KEY=$(grep '^APP_KEY=' .env | cut -d= -f2-)
        grep -v '^APP_KEY=' .env > .env.tmp && mv .env.tmp .env
        echo "APP_KEY=\"${KEY}\"" >> .env
    fi
fi

echo "[entrypoint] Running database migrations..."
attempt=1
max_attempts=10
until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "[entrypoint] ERROR: migrations failed after ${max_attempts} attempts"
        exit 1
    fi
    echo "[entrypoint] Migrate attempt ${attempt} failed, retrying in 2s..."
    attempt=$((attempt + 1))
    sleep 2
done
echo "[entrypoint] Migrations complete."

exec "$@"
