#!/usr/bin/env bash
# Update the live site after `git push` (run on the server, in the project folder).
# The CSS/JS bundle (public/build) is not in git. Small hosts run out of memory in `npm ci`
# (alwaysdata Free kills it), so build on your PC and upload it first:
#     bash scripts/upload-build.sh <ssh-target>
# Set BUILD_ON_SERVER=1 to build here instead, on a host with enough memory.
set -euo pipefail

php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
if [ "${BUILD_ON_SERVER:-0}" = "1" ]; then
    npm ci && npm run build && rm -rf node_modules
fi
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan queue:restart

# Every Vite entry point must be in the uploaded build, or pages that use it fail with an error.
missing=0
for entry in $(grep -o "resources/[^']*\.\(js\|css\)" vite.config.js); do
    if ! grep -q "\"$entry\"" public/build/manifest.json 2>/dev/null; then
        echo "MISSING from public/build: $entry"
        missing=1
    fi
done

php artisan up
echo "Deployed $(git rev-parse --short HEAD)."
if [ "$missing" = "1" ]; then
    echo "WARNING: the CSS/JS build is out of date. Run scripts/upload-build.sh on your PC now."
    exit 1
fi
