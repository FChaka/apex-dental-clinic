#!/bin/sh
set -e

# ── Working directory ─────────────────────────────────────────
cd /var/www/html

# ── Generate app key if not set ───────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] Generating application key..."
    php artisan key:generate --force
fi

# ── Cache configuration for production ───────────────────────
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "[entrypoint] Caching config, routes, and views..."
    php artisan config:cache  || true
    php artisan route:cache   || true
    php artisan view:cache    || true
fi

# ── Run database migrations ───────────────────────────────────
echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction || true

# ── Storage symlink ───────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

# ── Start the application ─────────────────────────────────────
PORT="${PORT:-8000}"
echo "[entrypoint] Starting Laravel on port ${PORT}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
