# Mantener las versiones documentadas en el README; los lockfiles no se modifican.
ARG PHP_TAG=8.0.30-apache-bullseye
ARG NODE_TAG=22.23.2-bookworm-slim
ARG COMPOSER_TAG=2.9
ARG POSTGRES_TAG=15.0

FROM composer:${COMPOSER_TAG} AS composer_bin
RUN composer self-update 2.9.6

FROM postgres:${POSTGRES_TAG} AS pg_client
RUN cp -L "$(ldconfig -p | awk '$1 == "libpq.so.5" { print $NF; exit }')" /tmp/libpq.so.5

FROM node:${NODE_TAG} AS frontend_build
WORKDIR /build/frontend
RUN test "$(node --version)" = v22.23.2 \
    && test "$(npm --version)" = 10.9.8
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_API_URL
ENV VITE_API_URL=${VITE_API_URL}
RUN test -n "$VITE_API_URL" && npm run build

FROM php:${PHP_TAG} AS app
WORKDIR /var/www/sciem/backend
ENV COMPOSER_ALLOW_SUPERUSER=1

# La base PHP 8.0.30 es Debian Bullseye. Los paquetes apt se instalan
# dentro de la imagen, independientemente del SO del servidor Docker.
RUN sed -i 's#deb.debian.org/debian-security#archive.debian.org/debian-security#g; s#deb.debian.org/debian#archive.debian.org/debian#g; /bullseye-updates/d' /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends \
      apache2=2.4.62-1~deb11u1 apache2-bin=2.4.62-1~deb11u1 \
      apache2-data=2.4.62-1~deb11u1 apache2-utils=2.4.62-1~deb11u1 \
      libpq-dev libzip-dev libpng-dev libonig-dev \
      libjpeg62-turbo-dev libfreetype6-dev unzip gosu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
      pdo_pgsql pgsql mbstring bcmath zip gd opcache \
    && a2enmod rewrite headers \
    && test "$(apache2 -v | sed -n 's#Server version: Apache/\([^ ]*\).*#\1#p')" = 2.4.62 \
    && rm -rf /var/lib/apt/lists/*

# El Compose de desarrollo usa phpredis 6.0.2; mantener el mismo cliente.
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && php --ri redis | grep -F "Version => 6.0.2" \
    && rm -rf /tmp/pear

# El proyecto necesita pg_dump 15.0 para sus respaldos y restauraciones.
COPY --from=pg_client /usr/lib/postgresql/15/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=pg_client /usr/lib/postgresql/15/bin/pg_restore /usr/local/bin/pg_restore
COPY --from=pg_client /tmp/libpq.so.5 /usr/local/lib/libpq.so.5
RUN ldconfig \
    && pg_dump --version | grep -F '15.0' \
    && pg_restore --version | grep -F '15.0'

COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
RUN composer --version | grep -F '2.9.6'
COPY backend/ ./
RUN mkdir -p storage/app/evidencias/reportes storage/app/public \
      storage/framework/cache/data storage/framework/sessions \
      storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction \
      --optimize-autoloader --no-scripts \
    && php artisan package:discover --ansi \
    && composer check-platform-reqs --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend_build /build/frontend/dist/ ./public/
COPY deployment/docker/apache/sciem.conf \
     /etc/apache2/sites-available/000-default.conf
COPY deployment/docker/apache/php-production.ini \
     /usr/local/etc/php/conf.d/sciem-production.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY deployment/docker/scripts/bootstrap-admin.php /usr/local/lib/sciem/bootstrap-admin.php
COPY deployment/docker/scripts/init-app.sh /usr/local/bin/sciem-init-app
COPY deployment/docker/scripts/entrypoint.sh /usr/local/bin/sciem-start
RUN chmod 0755 /usr/local/bin/sciem-init-app /usr/local/bin/sciem-start

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/sciem-start"]
CMD ["apache2-foreground"]
