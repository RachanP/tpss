#!/bin/sh
set -eu

cd /var/www/html

ROLE="${CONTAINER_ROLE:-web}"
DB_PORT="${DB_PORT:-3306}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
RUN_SEEDERS="${DB_SEED:-false}"

wait_for_database() {
  echo "Waiting for database ${DB_HOST}:${DB_PORT} ..."
  i=0
  until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" --silent 2>/dev/null; do
    i=$((i+1))
    if [ "$i" -ge 60 ]; then
      echo "Database not reachable after 120s; aborting." >&2
      exit 1
    fi
    sleep 2
  done
  echo "Database is up."
}

ensure_environment() {
  [ -f .env ] || cp .env.example .env

  if [ -n "${APP_KEY:-}" ]; then
    return
  fi

  if grep -q "^APP_KEY=base64:" .env; then
    return
  fi

  if [ "${GENERATE_APP_KEY:-false}" = "true" ]; then
    php artisan key:generate --force
    return
  fi

  echo "APP_KEY is missing. Set APP_KEY in the container environment before starting ${ROLE}." >&2
  exit 1
}

optimize_laravel() {
  php artisan config:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
}

bootstrap_laravel() {
  wait_for_database
  ensure_environment

  if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force

    if [ "${RUN_SEEDERS}" = "true" ]; then
      echo "Seeding database because DB_SEED=true ..."
      php artisan db:seed --force
    fi
  fi

  php artisan storage:link 2>/dev/null || true
  optimize_laravel
}

bootstrap_laravel

case "${ROLE}" in
  web)
    exec apache2-foreground
    ;;
  worker)
    exec php artisan queue:work --verbose --tries="${QUEUE_WORKER_TRIES:-3}" --timeout="${QUEUE_WORKER_TIMEOUT:-120}" --sleep="${QUEUE_WORKER_SLEEP:-3}"
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  *)
    echo "Unsupported CONTAINER_ROLE: ${ROLE}" >&2
    exit 1
    ;;
esac
