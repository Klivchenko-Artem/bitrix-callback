FROM php:8.2-fpm

# Всё, что просит проверка системы Битрикса, плюс msmtp — письма уходят в Mailpit
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libzip-dev libicu-dev libxml2-dev libonig-dev \
        msmtp ca-certificates curl unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd mysqli pdo_mysql mbstring zip intl opcache exif sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-bitrix.ini
COPY docker/php/msmtprc /etc/msmtprc
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0644 /etc/msmtprc && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
