# Stage 1: Build frontend assets
FROM php:8.4-cli AS frontend

# Install Node.js and system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install gd zip

WORKDIR /app

COPY . .

RUN cp .env.example .env



# Install Composer dependencies

 (needed for artisan)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --no-plugins --no-scripts

# Install NPM dependencies and build
RUN npm install
RUN npm run build

# Stage 2: Nginx
FROM nginx:alpine
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/public /var/www/html/public
