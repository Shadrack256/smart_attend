# Use an official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies and PHP extensions for PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache rewrite module
RUN a2enmod rewrite

# --- Render Port Configuration ---
ENV PORT 10000

# 1. Change the default 'Listen 80' directive to 'Listen 10000'
RUN sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf

# 2. Update the default virtual host to use the new port
RUN sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Set the Apache document root
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy your application code into the container
COPY . /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose the port for documentation
EXPOSE 10000

# Start Apache in the foreground
CMD ["apache2-foreground"]
