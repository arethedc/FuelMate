#!/usr/bin/env sh
set -eu

cd /var/www/html

# Create environment file when not supplied.
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Keep this project no-database friendly by default.
: "${SESSION_DRIVER:=file}"
: "${CACHE_STORE:=file}"
: "${QUEUE_CONNECTION:=sync}"
export SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION

# Generate key only when not provided in env and missing in .env.
if [ -z "${APP_KEY:-}" ] && ! grep -Eq '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

# Ensure package discovery exists even when composer scripts were skipped.
php artisan package:discover --ansi --no-interaction

PORT="${PORT:-10000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"

