FROM php:8.3-apache

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# បើក rewrite និង headers modules សម្រាប់ Routing
RUN a2enmod rewrite headers

# ចម្លង Source Code ទាំងអស់ចូល Apache directory
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# កំណត់ Port ตาม Railway Dynamic PORT និងចាប់ផ្តើម Apache
CMD ["/bin/sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
