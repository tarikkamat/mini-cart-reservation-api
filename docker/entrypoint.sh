#!/usr/bin/env bash
set -euo pipefail

# Wait for PostgreSQL to be reachable before running migrations / cache warm-up.
wait_for_postgres() {
    local host="${DB_HOST:-postgres}"
    local port="${DB_PORT:-5432}"
    local retries="${DB_WAIT_RETRIES:-30}"

    echo "[entrypoint] Waiting for PostgreSQL at ${host}:${port}..."
    until pg_isready -h "${host}" -p "${port}" -q || [ "${retries}" -le 0 ]; do
        retries=$((retries - 1))
        sleep 1
    done

    if [ "${retries}" -le 0 ]; then
        echo "[entrypoint] PostgreSQL did not become ready in time" >&2
        exit 1
    fi
    echo "[entrypoint] PostgreSQL is ready."
}

run_first_boot_tasks() {
    # Only the primary "app" role runs migrations and config caching to avoid
    # races between multiple replicas (queue workers, scheduler, etc.).
    if [ "${CONTAINER_ROLE:-app}" != "app" ]; then
        return 0
    fi

    echo "[entrypoint] Caching configuration, routes and views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        echo "[entrypoint] Running database migrations..."
        php artisan migrate --force --no-interaction
    fi

    if [ "${RUN_DB_SEED:-false}" = "true" ]; then
        echo "[entrypoint] Seeding database..."
        php artisan db:seed --force --no-interaction
    fi

    if [ "${OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
        php artisan event:cache || true
    fi
}

case "${CONTAINER_ROLE:-app}" in
    app)
        wait_for_postgres
        run_first_boot_tasks
        exec "$@"
        ;;
    queue)
        wait_for_postgres
        exec php artisan queue:work \
            --tries="${QUEUE_TRIES:-3}" \
            --timeout="${QUEUE_TIMEOUT:-90}" \
            --sleep="${QUEUE_SLEEP:-3}" \
            --max-jobs="${QUEUE_MAX_JOBS:-1000}" \
            --max-time="${QUEUE_MAX_TIME:-3600}"
        ;;
    scheduler)
        wait_for_postgres
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
