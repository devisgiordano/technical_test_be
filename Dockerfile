FROM php:8.2-fpm

# Installa dipendenze di sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    && docker-php-ext-install pdo_mysql intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Installa Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Crea le directory necessarie e imposta i permessi
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/public/bundles \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/var /var/www/html/public

EXPOSE 9000
CMD ["php-fpm"]