FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/voltgym.ini
COPY docker/apache-vhost.conf /etc/apache2/voltgym-vhost.conf
COPY docker/start.sh /usr/local/bin/start.sh

WORKDIR /var/www/html
COPY . /var/www/html

RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p /var/www/html/uploads /var/www/html/storage/receipts \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
