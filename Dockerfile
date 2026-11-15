FROM php:8.2-apache

# ដោះស្រាយបញ្ហា Apache MPM conflict
RUN a2dismod mpm_event && a2enmod mpm_prefork

# ysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo pdo_mysql

# កំណត់ឱ្យ Apache ប្រើប្រាស់ Port តាម Railway Environment Variable ($PORT)
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# ចម្លងកូដទាំងអស់ទៅកាន់ Apache web root
COPY . /var/www/html/

EXPOSE 80