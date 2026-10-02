#!/bin/sh
set -e

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

php artisan storage:link || true
php artisan migrate --force
php artisan config:cache
php artisan view:cache

exec apache2-foreground
