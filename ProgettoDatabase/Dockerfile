FROM php:8.2-apache

# Installa estensioni PHP necessarie
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Abilita mod_rewrite (utile per framework moderni)
RUN a2enmod rewrite

# Imposta la directory di lavoro
WORKDIR /var/www/html