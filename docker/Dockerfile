# Stage 1: Base PHP + Apache setup (this part won't change often)
FROM php:8.3-apache AS base

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    supervisor \
    libzip-dev zip unzip git curl libxml2-dev zlib1g-dev libonig-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring zip xml

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer globally (will be cached until the next change)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Stage 2: Copy application code and dependencies
FROM base AS app

# Set working directory
WORKDIR /var/www/html

# Copy only necessary files (we copy everything after dependencies to prevent frequent cache invalidation)
COPY . .

# Set environment variables to force production mode
ENV APP_ENV=production \
    APP_DEBUG=false \
    TELESCOPE_ENABLED=false

# Ensure Telescope is not installed
RUN rm -rf vendor/laravel/telescope

# Install dependencies via Composer
RUN composer install --no-dev --no-interaction --no-progress

# Set permissions for the app files (to ensure Apache can access the app)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Update the Apache configuration to use the new document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Expose the default HTTP port
EXPOSE 80

# Add supervisord config, used only by the scheduler service
COPY ./docker/supervisord.conf /etc/supervisord.conf

# Run migrations and clear caches at startup
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && apache2-foreground"]
