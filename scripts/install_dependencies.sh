#!/bin/bash
cd /var/www/html/
sudo chown ubuntu:ubuntu -R daverson_treasurer
sudo cp /var/www/html/daverson_treasurer_old/.env  /var/www/html/daverson_treasurer/.env
cd /var/www/html/daverson_treasurer/
sudo chown www-data:www-data -R storage
composer install --no-dev --prefer-dist --optimize-autoloader
composer update
sudo apt install npm
npm run build
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
