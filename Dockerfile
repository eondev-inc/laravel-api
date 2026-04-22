FROM php:8.4-cli-alpine

# Dependencias del sistema
RUN apk add --no-cache \
    postgresql-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    curl

# Extensiones PHP + phpredis en un solo layer para manejar $PHPIZE_DEPS correctamente
RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
        zip \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar dependencias primero para aprovechar cache de Docker
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copiar el resto del código
COPY . .

# Generar autoload optimizado
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
