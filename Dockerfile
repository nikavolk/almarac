FROM php:8.2-apache AS builder

# Install system dependencies needed for composer
RUN apt-get update && apt-get install -y \
    curl \
    zip \
    unzip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only Composer files first
COPY composer.json composer.lock ./

# Install dependencies to /app/vendor
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader


# Stage 2: Create the final application image
FROM php:8.2-apache

# Install system dependencies for the final image
RUN apt-get update && apt-get install -y \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client # git & curl might not be needed in final image unless app uses them

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite

WORKDIR /var/www/html

# Clean the web root
RUN rm -rf ./*

# Copy application source code 
COPY src/. /var/www/html/

# Copy the vendor directory from the 'builder' stage
COPY --from=builder /app/vendor/ /var/www/html/vendor/

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html 