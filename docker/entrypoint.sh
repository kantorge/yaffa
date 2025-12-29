#!/bin/sh
set -e

echo "Running Laravel upgrade scripts..."

# Wait for the database to be ready
if [ -n "$DB_HOST" ]; then
  echo "Waiting for database connection..."
  until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'DB connected'; } catch (Exception \$e) { echo '...'; sleep(3); }"; do
    echo "Retrying database connection..."
  done
fi

# Run various Laravel maintenance scripts
php artisan down || true
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up

echo "Startup complete. Launching Apache..."
exec apache2-foreground
