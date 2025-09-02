# Multi-stage build for Laravel production application
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    libzip-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        zip \
        mbstring \
        gd \
        fileinfo \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy application code (including .env if it exists)
COPY . .

# Ensure .env file has correct permissions if it exists
RUN if [ -f .env ]; then \
        chown www-data:www-data .env && \
        chmod 644 .env; \
    fi

# Install Composer autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Build frontend assets
FROM node:18-alpine AS node-builder
WORKDIR /var/www/html
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Production stage
FROM base AS production

# Copy built assets from node stage
COPY --from=node-builder /var/www/html/public/build ./public/build

# Create necessary directories and set permissions
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache \
    /var/log/supervisor \
    /var/run/supervisor \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        public \
        database \
    && chmod -R 775 \
        storage \
        bootstrap/cache \
        database

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /start.sh

# Make start script executable
RUN chmod +x /start.sh

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start services
CMD ["/start.sh"]
