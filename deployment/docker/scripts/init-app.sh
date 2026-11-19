#!/bin/sh
set -eu
cd /var/www/sciem/backend

php artisan migrate --force
php artisan db:seed --force
php /usr/local/lib/sciem/bootstrap-admin.php --from-env
php artisan migrate:status
