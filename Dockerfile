FROM php:8.2-cli

# MySQL extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /app

COPY . .

# Railway port ishlatish
CMD php -S 0.0.0.0:$PORT
