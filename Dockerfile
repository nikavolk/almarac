FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    default-mysql-client

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory for Composer actions
WORKDIR /app

# Copy only Composer files first
COPY composer.json composer.lock ./

# Install dependencies to /app/vendor
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Set the final Apache document root as WORKDIR
WORKDIR /var/www/html

# Clean the web root (important if previous layers had different content)
RUN rm -rf ./*

# Copy your application source code from repo src/ to /var/www/html/
COPY src/. /var/www/html/

# Copy the installed vendor directory from the temporary /app location to the web root
COPY --from=0 /app/vendor/ /var/www/html/vendor/

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html 