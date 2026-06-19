# ==========================================
# Stage 1: Build PHP dependencies
# ==========================================
FROM composer:2.7 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Install dependencies without dev packages and ignoring platform requirements 
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --ignore-platform-reqs --no-scripts

# ==========================================
# Stage 2: Build frontend assets (Vite)
# ==========================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ==========================================
# Stage 3: Production Image (Apache + PHP)
# ==========================================
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies & PHP extensions (suitable for 4 core / 4GB RAM mini PC)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql pdo_sqlite zip bcmath intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Configure Apache DocumentRoot to point to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy the application code
COPY . .

# Copy vendor directory from the composer stage
COPY --from=vendor /app/vendor/ ./vendor/

# Copy built frontend assets from the node stage
# (assuming Vite builds into public/build)
COPY --from=frontend /app/public/build/ ./public/build/

# Set proper permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Optional: You can create a script to run migrations on container start, 
# or just run them manually in Coolify post-deployment.

EXPOSE 80

CMD ["apache2-foreground"]
