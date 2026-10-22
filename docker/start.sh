#!/bin/bash
set -e

PORT="${PORT:-8080}"
DATA_ROOT="${DATA_PATH:-/data}"

cp /etc/apache2/voltgym-vhost.conf /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/@PORT@/${PORT}/g" /etc/apache2/sites-available/000-default.conf

mkdir -p "$DATA_ROOT/uploads" "$DATA_ROOT/receipts"
rm -rf /var/www/html/uploads /var/www/html/storage/receipts
mkdir -p /var/www/html/storage
ln -sfn "$DATA_ROOT/uploads" /var/www/html/uploads
ln -sfn "$DATA_ROOT/receipts" /var/www/html/storage/receipts
chown -R www-data:www-data "$DATA_ROOT" /var/www/html/storage

echo "Waiting for MySQL and preparing the database..."
php /var/www/html/scripts/provision.php

if [ "${RUN_CRON:-1}" != "0" ]; then
    echo "Starting daily gym jobs in the background..."
    php /var/www/html/cron/worker.php &
fi

echo "Gym system is starting on port ${PORT}"
exec apache2-foreground
