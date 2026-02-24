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

# Crear .env si no existe
RUN cp -n .env.example .env || true

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Generar APP_KEY
RUN php artisan key:generate --force

# Permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache
