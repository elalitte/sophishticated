#!/bin/bash
# Wait for the database to be reachable before starting the PHP process.
# Usage: /wait-for-db.sh php worker/worker.php

set -e

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
MAX_RETRIES=30
RETRY_INTERVAL=2

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Waiting for database at ${DB_HOST}:${DB_PORT}..."

for i in $(seq 1 $MAX_RETRIES); do
    if php -r "
        try {
            new PDO(
                'mysql:host=${DB_HOST};port=${DB_PORT}',
                '${DB_USERNAME:-root}',
                '${DB_PASSWORD:-}',
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Database is ready."
        exec "$@"
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Database not ready (attempt $i/$MAX_RETRIES). Retrying in ${RETRY_INTERVAL}s..."
    sleep $RETRY_INTERVAL
done

echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Could not connect to database after $MAX_RETRIES attempts."
exit 1
