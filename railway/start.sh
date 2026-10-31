#!/bin/sh

set -eu

: "${APP_KEY:?APP_KEY wajib diatur di Railway Variables}"

export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/data/database.sqlite}"
export FILESYSTEM_DISK="${FILESYSTEM_DISK:-public}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export CACHE_STORE="${CACHE_STORE:-database}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"

mkdir -p /data/uploads "$(dirname "$DB_DATABASE")"
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs
touch "$DB_DATABASE"

rm -rf storage/app/public
ln -s /data/uploads storage/app/public

rm -rf public/storage
php artisan storage:link
php artisan migrate --force --no-interaction

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
