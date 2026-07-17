# ==========================================
# STAGE 1: COMPOSER BUILDER
# ==========================================
FROM php:8.3-fpm-alpine AS builder

# Install composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install build dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    sqlite-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_sqlite mbstring opcache

WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev dependencies, optimized autoloader)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source code
COPY . .

# Finish autoloader optimization
RUN composer dump-autoload --no-dev --optimize

# ==========================================
# STAGE 2: PRODUCTION RUNNER
# ==========================================
FROM php:8.3-fpm-alpine

# Install system utilities & production dependencies
RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs

# Install OPcache and other PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite opcache

# Copy custom PHP OPcache config for production
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /var/www

# Copy built code from Stage 1
COPY --from=builder /var/www /var/www

# Create SQLite database file if it doesn't exist
RUN mkdir -p database \
    && touch database/database.sqlite

# Configure directory permissions for Laravel storage/bootstrap cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Switch to non-root user
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
