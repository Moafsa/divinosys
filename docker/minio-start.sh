#!/bin/bash
set -euo pipefail

MINIO_DATA_DIR="${MINIO_DATA_DIR:-/var/www/html/storage/minio}"
MINIO_ROOT_USER="${MINIO_ROOT_USER:-${MINIO_ACCESS_KEY:-divinosys_minio}}"
MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:-${MINIO_SECRET_KEY:-divinosys_minio_secret}}"
MINIO_API_PORT="${MINIO_API_PORT:-9000}"

mkdir -p "$MINIO_DATA_DIR"
chown -R www-data:www-data "$(dirname "$MINIO_DATA_DIR")" 2>/dev/null || true

if pgrep -x minio >/dev/null 2>&1; then
    echo "MinIO already running"
    exit 0
fi

echo "Starting MinIO on 127.0.0.1:${MINIO_API_PORT} (data: ${MINIO_DATA_DIR})"
MINIO_ROOT_USER="$MINIO_ROOT_USER" \
MINIO_ROOT_PASSWORD="$MINIO_ROOT_PASSWORD" \
nohup minio server "$MINIO_DATA_DIR" --address "127.0.0.1:${MINIO_API_PORT}" \
    > /var/log/minio.log 2>&1 &

for i in $(seq 1 30); do
    if curl -sf "http://127.0.0.1:${MINIO_API_PORT}/minio/health/live" >/dev/null 2>&1; then
        echo "MinIO is ready"
        exit 0
    fi
    sleep 1
done

echo "MinIO failed to start within 30 seconds"
tail -20 /var/log/minio.log 2>/dev/null || true
exit 1
