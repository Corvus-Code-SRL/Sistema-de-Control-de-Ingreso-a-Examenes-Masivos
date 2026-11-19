#!/bin/sh
set -eu
cd /var/www/sciem/backend

if [ -z "${APP_KEY:-}" ]; then
  echo 'Falta APP_KEY en deployment/docker/.env.' >&2
  exit 1
fi
if [ -z "${DB_PASSWORD:-}" ] || [ "$DB_PASSWORD" = 'REEMPLAZAR_CLAVE_APP_DB' ]; then
  echo 'Falta la contraseña real de sciem_app en deployment/docker/.env.' >&2
  exit 1
fi

# Los volúmenes recién creados también necesitan estos directorios.
for dir in \
  storage storage/app storage/app/public storage/app/evidencias \
  storage/app/evidencias/reportes storage/framework \
  storage/framework/cache storage/framework/cache/data \
  storage/framework/sessions storage/framework/views \
  storage/logs bootstrap/cache
do
  install -d -m 0775 -o www-data -g www-data "$dir"
done
install -d -m 0770 -o www-data -g www-data /respaldos

# env_file ya fue inyectado; no cachear secretos durante el build.
gosu www-data php artisan config:cache

if [ "$1" = "apache2-foreground" ]; then
  exec docker-php-entrypoint "$@"
fi

exec gosu www-data docker-php-entrypoint "$@"
