# Local TEST image only. The site itself runs on the cPanel host
# (hararipp.orcamind.dev). This container keeps the host's PHP 8.0 away from
# the project and runs tests on the same PHP line as the server.
FROM php:8.3-cli-bookworm AS base
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip ca-certificates rsync \
        libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libwebp-dev libavif-dev libonig-dev libxml2-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath gd zip pcntl opcache exif calendar \
 && pecl install pcov && docker-php-ext-enable pcov \
 && apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN printf "memory_limit=1G\npcov.enabled=1\npcov.directory=/app/app\n" > /usr/local/etc/php/conf.d/zz-test.ini
WORKDIR /app
