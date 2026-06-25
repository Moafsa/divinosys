#!/bin/bash
set -euo pipefail

echo "=== DIVINO LANCHES ENTRYPOINT ==="

/usr/local/bin/minio-start.sh
/usr/local/bin/minio-setup.sh

if [ "${APP_ENV:-development}" = "production" ]; then
    exec /bin/bash /usr/local/bin/start-production.sh
fi

exec /bin/bash /usr/local/bin/start.sh
