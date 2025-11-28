#!/bin/sh
set -e

if [ "${DB_CONNECTION}" = "sqlite" ] && [ -n "${DB_DATABASE}" ]; then
  mkdir -p "$(dirname "${DB_DATABASE}")"

  if [ ! -f "${DB_DATABASE}" ]; then
    echo "Creating sqlite database at ${DB_DATABASE}"

    touch "${DB_DATABASE}"
    BOOST_ENABLED=false php artisan migrate:fresh --force || true
  fi
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running database migrations..."
  BOOST_ENABLED=false php artisan migrate --force || true
fi

if [ "${APP_ENV}" = "production" ]; then
  echo "Refreshing Laravel caches..."
  BOOST_ENABLED=false php artisan config:clear || true
  BOOST_ENABLED=false php artisan route:clear || true
  BOOST_ENABLED=false php artisan view:clear || true

  BOOST_ENABLED=false php artisan config:cache || true
  BOOST_ENABLED=false php artisan route:cache || true
  BOOST_ENABLED=false php artisan view:cache || true
fi

echo "Starting FrankenPHP..."
exec frankenphp run --config /app/Caddyfile
