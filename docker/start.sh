#!/usr/bin/env sh
set -eu

cd /var/www/html

# Create environment file when not supplied.
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Ensure APP_KEY exists at runtime.
# Priority:
# 1) Render env var APP_KEY
# 2) Existing APP_KEY in .env
# 3) Generated key fallback
if [ -z "${APP_KEY:-}" ]; then
  EXISTING_KEY="$(grep -E '^APP_KEY=base64:' .env | head -n 1 | cut -d '=' -f 2- || true)"
  if [ -n "$EXISTING_KEY" ]; then
    APP_KEY="$EXISTING_KEY"
  else
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    if grep -q '^APP_KEY=' .env; then
      sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
      echo "APP_KEY=${APP_KEY}" >> .env
    fi
  fi
fi
export APP_KEY

# Keep this project strictly no-database at runtime.
SESSION_DRIVER="file"
CACHE_STORE="file"
QUEUE_CONNECTION="sync"
export SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION

# Safety net: create sqlite file in case any package still touches DB connection.
mkdir -p database
touch database/database.sqlite

# Avoid stale cached config from previous builds.
php artisan config:clear --ansi --no-interaction

# Ensure package discovery exists even when composer scripts were skipped.
php artisan package:discover --ansi --no-interaction

PORT="${PORT:-10000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
