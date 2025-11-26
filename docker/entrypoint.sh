#!/bin/sh
set -e

# Ensure sqlite file exists if using sqlite
if [ "${DB_CONNECTION}" = "sqlite" ] && [ -n "${DB_DATABASE}" ]; then
  mkdir -p "$(dirname "${DB_DATABASE}")"

  if [ ! -f "${DB_DATABASE}" ]; then
    touch "${DB_DATABASE}"
  fi
fi

# Run migrations once
BOOST_ENABLED=false php artisan migrate --force || true

# If the users table is missing the expected soft delete column (stale schema), rebuild once
if BOOST_ENABLED=false php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class); \$kernel->bootstrap(); echo (\\Illuminate\\Support\\Facades\\Schema::hasTable('users') && \\Illuminate\\Support\\Facades\\Schema::hasColumn('users', 'deleted_at')) ? 'ok' : 'missing';" | grep -q missing; then
  echo \"Detected stale schema, refreshing database...\"
  BOOST_ENABLED=false php artisan migrate:fresh --force || true
fi

BOOST_ENABLED=false php artisan queue:work --tries=3 --timeout=120 &
exec frankenphp run --config /app/Caddyfile
