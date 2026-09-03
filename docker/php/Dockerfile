FROM php:8.3-fpm-alpine

# GD is used only for trusted server-side resizing/encoding of cropped avatars.
RUN apk add --no-cache libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .gd-build libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql opcache gd \
    && apk del .gd-build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-pixiepoint.ini

WORKDIR /var/www/html

EXPOSE 9000
