FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends unzip libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql opcache zip bcmath gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html

# El código se monta desde el host; mismo UID para no pelear con permisos.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data
USER www-data
