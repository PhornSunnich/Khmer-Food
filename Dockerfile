FROM php:8.3-apache

# លុបស៊ីមលីង MPM modules ទាំងអស់ចេញពី mods-enabled និងបើក mpm_prefork តែមួយគត់
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* /etc/apache2/mods-enabled/mpm_prefork.* \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/ \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/ \
    && a2enmod rewrite headers

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# กำหนด ServerName เพื่อป้องกัน Warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ចម្លង Source Code ទាំងអស់ចូល Apache directory
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# លុប PID ចាស់ និងរៀបចំ Port តាម Railway Dynamic PORT
CMD ["/bin/sh", "-c", "rm -f /var/run/apache2/apache2.pid && sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
