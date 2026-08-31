FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql opcache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-pixiepoint.ini

WORKDIR /var/www/html

EXPOSE 9000

