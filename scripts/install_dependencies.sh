#!/bin/bash
cd /var/www/html/
sudo chown ubuntu:ubuntu -R yaffa
sudo cp /var/www/html/yaffa_old/.env  /var/www/html/yaffa/.env
cd /var/www/html/yaffa/
sudo chown www-data:www-data -R storage
composer install --no-dev --prefer-dist --optimize-autoloader
composer update
sudo apt install npm
npm run build
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
