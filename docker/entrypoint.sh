#!/bin/sh
set -e

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf

if [ -f /etc/secrets/ca.pem ]; then
  cp /etc/secrets/ca.pem /etc/ssl/certs/aiven-ca.pem
  chmod 644 /etc/ssl/certs/aiven-ca.pem
  export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aiven-ca.pem
fi

export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

cd /var/www/html
mkdir -p storage/framework
printf '%s' "${RELIC_ROLE:-web}" > storage/framework/relic-role

php artisan storage:link || true

if [ "${RELIC_ROLE}" = "finance" ]; then
  php artisan migrate --force --database=finance --path=database/migrations/finance
  php artisan config:cache
  chown -R www-data:www-data storage bootstrap/cache
  exec apache2-foreground
fi

php artisan migrate --force
if [ -z "${FINANCE_API_URL}" ]; then
  php artisan migrate --force --database=finance --path=database/migrations/finance || true
fi
php artisan db:seed --force
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
