FROM php:8.2-apache

# ដោះស្រាយបញ្ហា Apache MPM conflict (បិទ mpm_event និងបើក mpm_prefork)
RUN a2dismod mpm_event && a2enmod mpm_prefork

# បើកដំណើរការ mysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo pdo_mysql

# ចម្លងកូដទាំងអស់ទៅកាន់ Apache web root
COPY . /var/www/html/

EXPOSE 80
