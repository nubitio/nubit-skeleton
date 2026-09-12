#!/bin/sh
set -e

# The compose "app" service bind-mounts the whole checkout over the image
# (see compose.yaml), so a fresh clone has no vendor/ until something
# installs it — and nothing did, which meant `docker compose up` alone never
# reproduced a working app: it silently required a host-side `composer
# install` nobody documented or automated. Doing it here, once and
# idempotently, on every container start makes booting the stack exactly as
# reproducible as `frontend`'s own `pnpm install` on start already is, and
# harmless to run again on a rebuild where vendor/ was baked in at image
# build time instead.
if [ -f composer.json ] && [ ! -d vendor ]; then
    composer install --no-interaction --no-progress
fi

exec docker-php-entrypoint "$@"
