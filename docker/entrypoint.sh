#!/usr/bin/env sh
set -e

cd /app

php artisan config:clear --quiet

case "${APP_ENV:-local}" in
  production|staging)
    if [ "${RUN_PRODUCTION_GUARD:-true}" = "true" ]; then
      if ! php artisan config:production-guard --env="${APP_ENV}" --json; then
        if [ "${ENFORCE_PRODUCTION_GUARD:-true}" != "false" ]; then
          exit 1
        fi

        echo "Production config guard reported violations; continuing because ENFORCE_PRODUCTION_GUARD=false." >&2
      fi
    fi

    if [ "${RUN_STORAGE_PRODUCTION_GUARD:-true}" = "true" ]; then
      if ! php artisan storage:production-guard --env="${APP_ENV}" --json; then
        if [ "${ENFORCE_PRODUCTION_GUARD:-true}" != "false" ]; then
          exit 1
        fi

        echo "Production storage guard reported violations; continuing because ENFORCE_PRODUCTION_GUARD=false." >&2
      fi
    fi
    ;;
esac

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

case "${CONTAINER_ROLE:-web}" in
  web)
    exec frankenphp run --config /etc/caddy/Caddyfile
    ;;
  worker)
    exec php artisan horizon
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  *)
    echo "Unsupported CONTAINER_ROLE: ${CONTAINER_ROLE}" >&2
    exit 64
    ;;
esac
