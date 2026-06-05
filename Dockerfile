# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: PHP dependencies (Composer)
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2.8 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Node / Vite assets
# ─────────────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS node-assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --silent

COPY . .
COPY --from=composer-deps /app/vendor ./vendor

RUN npm run build

# ─────────────────────────────────────────────────────────────────────────────
# Stage 3: Production PHP-FPM image
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

LABEL maintainer="Mines Platform <dev@infodot.co.za>"
LABEL description="Mines — Mining Fleet Management Platform"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    bash \
    git \
    icu-dev \
    oniguruma-dev \
    sqlite-dev

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp && \
    docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        gd \
        zip \
        mbstring \
        intl \
        opcache \
        pcntl

# Install Redis PHP extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps

# PHP production configuration
COPY deploy/php/php.ini /usr/local/etc/php/php.ini
COPY deploy/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# nginx configuration (container-specific — no SSL; TLS is terminated at the load balancer)
COPY deploy/nginx-container.conf /etc/nginx/nginx.conf

# Supervisor configuration
COPY deploy/queue-worker.supervisord.conf /etc/supervisor.d/mines-workers.ini
COPY deploy/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Copy application
COPY --chown=www-data:www-data . .

# Copy vendor from composer stage
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor

# Copy built assets from node stage
COPY --from=node-assets --chown=www-data:www-data /app/public/build ./public/build

# Create required directories and set permissions
RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        storage/logs \
        bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Optimise Laravel for production
RUN php artisan config:cache --no-interaction && \
    php artisan route:cache --no-interaction && \
    php artisan view:cache --no-interaction

EXPOSE 80

COPY deploy/docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
