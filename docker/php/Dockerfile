FROM php:8.4-fpm

# =========================
# System packages
# =========================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    zip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    ffmpeg \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    webp \
    supervisor \
    nginx \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        zip \
        opcache

# Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# =========================
# Install dependencies
# =========================
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

# =========================
# Copy project
# =========================
COPY . .

RUN composer dump-autoload --optimize

RUN php artisan package:discover --ansi || true

RUN mkdir -p storage bootstrap/cache

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# =========================
# Copy configs
# =========================
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY docker/php/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]