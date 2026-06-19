FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        sqlite-dev \
        curl-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        curl \
        mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/bot

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN mkdir -p /var/www/bot/data /var/www/bot/runtime/logs \
    && chown -R www-data:www-data /var/www/bot/data /var/www/bot/runtime
