FROM php:8.2-apache

# MySQL extension o‘rnatish
RUN docker-php-ext-install mysqli pdo pdo_mysql

# loyiha fayllarini containerga ko'chirish
COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 8080
