#!/bin/bash
set -e -u -o pipefail

/opt/glpi/entrypoint/init-volumes-directories.sh
/opt/glpi/entrypoint/forward-logs.sh
/opt/glpi/entrypoint/wait-for-db.sh entrypoint
/opt/glpi/entrypoint/install.sh

cd /var/www/glpi

if php bin/console plugin:install -u glpi approvalbymail --force 2>/dev/null; then
  php bin/console plugin:activate approvalbymail 2>/dev/null || true
fi

exec "$@"
