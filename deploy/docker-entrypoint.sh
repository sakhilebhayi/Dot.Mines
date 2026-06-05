#!/bin/sh
set -e

# Run pending database migrations
echo "Running migrations..."
php artisan migrate --force --no-interaction

# Warm caches (in case they were cleared between builds)
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

# Start supervisord (nginx + php-fpm + queue workers)
echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
