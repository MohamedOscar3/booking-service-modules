#!/bin/bash

# Exit on error
set -e

echo "Starting entrypoint script..."

# Run initialization script in background (migrations, docs generation)
echo "Running initialization tasks..."
/usr/local/bin/init.sh &

# Start Supervisor in the background
echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf &

# Wait a moment for supervisor to start
sleep 2

# Check if supervisor is running
if pgrep -x "supervisord" > /dev/null; then
    echo "Supervisor started successfully"
else
    echo "Warning: Supervisor may not have started properly"
fi

# Start PHP-FPM in the foreground
echo "Starting PHP-FPM..."
exec php-fpm
