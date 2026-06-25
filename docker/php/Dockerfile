# syntax=docker/dockerfile:1
# ── AlphaCMS backend — Laravel (PHP 8.4-FPM) production image ───────────────────
# Used by three roles (same image, different commands): php-fpm (API), queue workers, scheduler.
# Includes ffmpeg (video transcode) + image optimizers (spatie/image-optimizer) + Redis ext.

FROM php:8.4-fpm AS base

# System libraries + media tooling. ffmpeg/ffprobe → video HLS/MP4 transcode; the optimizer
# binaries are auto-detected by spatie/laravel-image-optimizer.
RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
      ffmpeg jpegoptim optipng pngquant gifsicle webp \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd intl zip bcmath exif pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-alphacms.ini

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-alphacms.ini

WORKDIR /var/www/html

# Vendor layer (cached on composer.lock). --no-scripts: artisan isn't bootable until app is copied.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

# Application code, then finalize the autoloader + package manifest.
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi || true \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
