#!/usr/bin/env bash
# Package the local database and photos for upload to the host (docs/HOSTING.md, step 3).
# Run in Git Bash from the project folder. Reads DB_* from .env.
set -euo pipefail

env_value() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_HOST=$(env_value DB_HOST); DB_PORT=$(env_value DB_PORT); DB_DATABASE=$(env_value DB_DATABASE)
DB_USERNAME=$(env_value DB_USERNAME); DB_PASSWORD=$(env_value DB_PASSWORD)
MYSQLDUMP=${MYSQLDUMP:-$(command -v mysqldump || echo /c/xampp/mysql/bin/mysqldump.exe)}

OUT=storage/app/private/hosting-export
mkdir -p "$OUT"

echo "Dumping ${DB_DATABASE}…"
"$MYSQLDUMP" -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
    --single-transaction --default-character-set=utf8mb4 --no-tablespaces \
    --ignore-table="${DB_DATABASE}.sessions" --ignore-table="${DB_DATABASE}.cache" --ignore-table="${DB_DATABASE}.cache_locks" \
    --ignore-table="${DB_DATABASE}.jobs" --ignore-table="${DB_DATABASE}.failed_jobs" \
    "$DB_DATABASE" | gzip > "$OUT/database.sql.gz"

echo "Packing photos…"
tar -czf "$OUT/media.tar.gz" -C storage/app/public media

ls -lh "$OUT"
echo "Upload both files to the server (docs/HOSTING.md, step 3)."
