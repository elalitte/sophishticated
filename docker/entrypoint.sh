#!/bin/bash
set -e

# Pass environment variables to cron (cron runs in a clean env)
printenv | grep -v "no_proxy" >> /etc/environment

# Sync JSON templates to database
php -r "
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/backend/Config/database.php';
(new App\Services\JsonTemplateStorage())->syncToDb(getDB());
echo \"[entrypoint] Templates synced to database.\n\";
" || echo "[entrypoint] WARNING: template sync failed"

# Start cron daemon in background
cron

# Start Apache in foreground (PID 1)
exec apache2-foreground
