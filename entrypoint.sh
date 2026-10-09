#!/bin/bash
set -Eeuo pipefail

startup_stage=initialization
trap 'startup_exit=$?; printf "[startup] Failed: stage=%s exit=%s\n" "$startup_stage" "$startup_exit" >&2; exit "$startup_exit"' ERR
cd /var/www/html

startup_stage=permissions
echo "=== [1/4] Verificando permisos de almacenamiento ==="
mkdir -p storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

startup_stage=ssh
echo "=== [2/4] Iniciando SSH interno de App Service ==="
mkdir -p /run/sshd
ssh-keygen -A
/usr/sbin/sshd -t
/usr/sbin/sshd -e

startup_stage=laravel-cache
echo "=== [3/4] Preparando Laravel con las variables de esta instancia ==="
# Azure injects environment variables BEFORE invoking this entrypoint.
# Do not flush the data cache or run migrations on container startup.
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

startup_stage=application
echo "=== [4/4] Iniciando servidor de aplicación ==="
if [ "$#" -eq 0 ]; then
    set -- apache2-foreground
fi
# Preserve php:8.3-apache's command handling and Apache as the foreground PID 1.
exec docker-php-entrypoint "$@"
