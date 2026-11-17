FROM php:8.3-apache

# រក្សាទុក mpm_event និងបិទ mpm_prefork/mpm_worker ដើម្បីការពារការตีกัน
RUN a2dismod -f mpm_prefork mpm_worker 2>/dev/null || true \
    && a2enmod mpm_event 2>/dev/null || true \
    && a2enmod rewrite headers

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# ចម្លង Source Code ទាំងអស់ចូល Apache directory
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# រៀបចំ Port តាម Railway Dynamic PORT
CMD ["/bin/sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
