#!/usr/bin/env bash
# Update the live site after `git push` (run on the server, in the project folder).
set -euo pipefail

php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
if command -v npm >/dev/null 2>&1; then
    npm ci && npm run build && rm -rf node_modules
fi
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan queue:restart
php artisan up
echo "Deployed $(git rev-parse --short HEAD)."
