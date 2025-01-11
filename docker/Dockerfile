# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl \
    && docker-php-ext-install pdo_mysql zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install YAFFA dependencies with Composer in the working directory in production mode
RUN composer install --no-dev --no-interaction --no-progress --no-suggest

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Update the Apache configuration to use the new document root
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Expose the default HTTP port
EXPOSE 80

# Run migrations and clear caches at startup
CMD php artisan migrate --force && php artisan config:cache && apache2-foreground
