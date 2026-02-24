FROM php:8.3-fpm

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libicu-dev \
    curl \
    unzip \
    git \
 && docker-php-ext-install soap bcmath \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar código
WORKDIR /var/www
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Permisos
RUN chmod -R 775 storage bootstrap/cache
