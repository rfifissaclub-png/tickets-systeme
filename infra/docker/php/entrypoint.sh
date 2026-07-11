#!/bin/sh
set -eu

# Config/route/event caching must happen here, at container start, not at
# image build time — the production Dockerfile stage has no real APP_KEY or
# DB credentials available (see its comment), so anything cached during the
# build would bake in wrong values. Real env vars are only present now.
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
