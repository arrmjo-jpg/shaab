#!/bin/sh

set -e

echo "🚀 Starting AlphaCMS..."

mkdir -p /run/php
mkdir -p /var/log/nginx

cd /var/www/html

# Laravel
php artisan storage:link || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start PHP-FPM
php-fpm -D

# Start Nginx
nginx -g "daemon off;"