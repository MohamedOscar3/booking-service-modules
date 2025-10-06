#!/bin/bash

# Exit on error
set -e

echo "Waiting for database to be ready..."
sleep 10

composer dump-autoload

if [ ! -f "/storage/init.txt" ]; then
    php artisan key:generate
fi

echo "Running migrations..."
php artisan migrate --force

echo "Generating API documentation with Scribe..."
php artisan scribe:generate

echo "Generating PHP documentation..."
if [ -f "phpdoc.xml" ]; then
    phpdoc run
else
    echo "Warning: phpdoc.xml not found, skipping PHPDoc generation"
fi

echo "Initialization complete!"
