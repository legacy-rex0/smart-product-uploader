#!/bin/sh

# Exit on any error
set -e

echo "Starting Laravel application..."

# Wait for database to be ready (if using external database)
# Uncomment and modify if using MySQL/PostgreSQL instead of SQLite
# echo "Waiting for database..."
# while ! php artisan db:show --quiet; do
#     echo "Database not ready, waiting..."
#     sleep 2
# done

# Use existing .env file if present, otherwise create one
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cp .env.example .env 2>/dev/null || echo "APP_KEY=base64:$(openssl rand -base64 32)" > .env
    # Generate application key only for new .env files
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Using existing .env file..."
    # Only generate key if APP_KEY is not set
    if ! grep -q "APP_KEY=" .env || grep -q "APP_KEY=base64:" .env; then
        echo "Generating application key..."
        php artisan key:generate --force
    else
        echo "Application key already exists in .env"
    fi
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

# Ensure database file is writable
if [ -f database/database.sqlite ]; then
    echo "Setting database permissions..."
    chown www-data:www-data database/database.sqlite
    chmod 664 database/database.sqlite
fi

# Create storage symlink if it doesn't exist
if [ ! -L public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
fi

# Start supervisor (which manages nginx, php-fpm, and queue workers)
echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
