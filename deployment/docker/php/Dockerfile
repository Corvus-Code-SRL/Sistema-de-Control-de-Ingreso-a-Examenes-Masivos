# Runtime de desarrollo del backend SCIEM.
# Versiones fijadas por el README: PHP 8.0.30 y Composer 2.9.6.
FROM php:8.0.30-cli

# PHP 8.0.30 solo existe sobre Debian 11 (bullseye), que ya fue retirado:
# sus paquetes se descargan desde el archivo historico de Debian.
# bullseye-security no esta disponible (ni en deb.debian.org ni en el archivo),
# por eso se omite. Check-Valid-Until=false acepta los indices archivados.
RUN printf '%s\n' \
        'deb http://archive.debian.org/debian bullseye main' \
        'deb http://archive.debian.org/debian bullseye-updates main' \
        > /etc/apt/sources.list

# Extensiones exigidas por el README que la imagen base no incluye:
#   pdo_pgsql -> conexion a PostgreSQL
#   bcmath    -> requisito del README
#   zip       -> descompresion de paquetes de Composer y librerias de hojas de calculo
#   redis     -> cliente phpredis (REDIS_CLIENT por defecto en config/database.php)
RUN apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql bcmath zip \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# No existe la imagen composer:2.9.6: se parte de 2.9 y se fija la version exacta.
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer
RUN composer self-update 2.9.6

# Usuario con el mismo UID/GID que el desarrollador en WSL, para que los archivos
# generados (vendor/, storage/logs) no queden a nombre de root.
ARG UID=1000
ARG GID=1000
RUN groupadd --gid ${GID} sciem \
    && useradd --uid ${UID} --gid ${GID} --create-home --shell /bin/bash sciem

USER sciem
WORKDIR /var/www/sciem/backend
