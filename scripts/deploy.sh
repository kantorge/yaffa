#!/bin/bash
cd /var/www/html/yaffa
composer install --no-dev
sudo systemctl restart nginx