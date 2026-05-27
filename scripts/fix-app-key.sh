#!/bin/sh
# Regenerate a single valid APP_KEY in backend/.env (quoted for Docker)
set -e
cd "$(dirname "$0")/.."

ENV_FILE="backend/.env"

if [ ! -f "$ENV_FILE" ]; then
    cp backend/.env.example "$ENV_FILE"
fi

grep -v '^APP_KEY=' "$ENV_FILE" > "${ENV_FILE}.tmp"
mv "${ENV_FILE}.tmp" "$ENV_FILE"
echo 'APP_KEY=' >> "$ENV_FILE"

docker compose exec app php artisan key:generate --force --no-interaction

KEY=$(grep '^APP_KEY=' "$ENV_FILE" | cut -d= -f2- | tr -d '"')
grep -v '^APP_KEY=' "$ENV_FILE" > "${ENV_FILE}.tmp"
mv "${ENV_FILE}.tmp" "$ENV_FILE"
echo "APP_KEY=\"${KEY}\"" >> "$ENV_FILE"

docker compose exec app php artisan config:clear
docker compose restart app worker scheduler

echo "Done: $(grep '^APP_KEY=' "$ENV_FILE")"
