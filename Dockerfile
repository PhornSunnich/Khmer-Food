FROM php:8.3-apache

# ដោះស្រាយបញ្ហា MPM ឱ្យបានត្រឹមត្រូវ (បិទ mpm_event/worker និងបើក mpm_prefork)
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite headers

# mysqli និង pdo extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# ចម្លងកូដទាំងអស់ទៅកាន់ Apache web root
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# កំណត់ឱ្យ Apache ប្រើប្រាស់ Port តាម Railway Environment Variable ($PORT)
CMD ["/bin/sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]