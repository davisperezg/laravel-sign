FROM php:8.3-fpm

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libicu-dev \
 && docker-php-ext-install soap bcmath \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
