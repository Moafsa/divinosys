#!/bin/bash
set -euo pipefail

MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://127.0.0.1:9000}"
MINIO_BUCKET="${MINIO_BUCKET:-divinosys}"
MINIO_ROOT_USER="${MINIO_ROOT_USER:-${MINIO_ACCESS_KEY:-divinosys_minio}}"
MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:-${MINIO_SECRET_KEY:-divinosys_minio_secret}}"

echo "Configuring MinIO bucket: ${MINIO_BUCKET}"

mc alias set localminio "$MINIO_ENDPOINT" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" >/dev/null 2>&1 || \
mc alias set localminio "$MINIO_ENDPOINT" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"

mc mb "localminio/${MINIO_BUCKET}" --ignore-existing >/dev/null 2>&1 || true
mc anonymous set download "localminio/${MINIO_BUCKET}" >/dev/null 2>&1 || true

echo "MinIO bucket '${MINIO_BUCKET}' ready"
