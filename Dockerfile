FROM php:8.2-apache

# mysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo pdo_mysql

# Apache web root
COPY . /var/www/html/

EXPOSE 80
