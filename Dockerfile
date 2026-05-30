# syntax=docker/dockerfile:1

# ---------- Stage 1: build frontend assets (Vite + Tailwind v4) ----------
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
# vite.config.js (laravel-vite-plugin + bunny fonts) ต้องเห็น resources + public
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- Stage 2: PHP runtime (Apache + PHP 8.3) ----------
FROM php:8.3-apache AS app

# system libs + PHP extensions ที่ Laravel 13 + MySQL ต้องใช้
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip default-mysql-client \
        libzip-dev libpng-dev libonig-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath zip gd intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: docroot -> /public + mod_rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# ติดตั้ง vendor ก่อน (cache layer) — copy เฉพาะ composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# โค้ดทั้งหมด + built assets จาก stage แรก
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["entrypoint"]
