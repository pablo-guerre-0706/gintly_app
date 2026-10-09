#!/bin/bash
set -e

echo "=== [1/3] Limpiando y optimizando cachés de Laravel para producción ==="
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== [2/3] Verificando permisos de almacenamiento ==="
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "=== [3/3] Iniciando servidor Apache ==="
exec apache2-foreground