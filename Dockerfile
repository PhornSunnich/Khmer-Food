FROM php:8.3-apache

# បើក rewrite និង headers modules
RUN a2enmod rewrite headers

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# កំណត់ ServerName ដើម្បីការពារ Warning/Crash
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ចម្លង Source Code ទាំងអស់ចូល Apache directory
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# លុប PID ចាស់ចោល រៀបចំ Port តាម Railway Dynamic PORT ហើយចាប់ផ្តើម Apache
CMD ["/bin/sh", "-c", "rm -f /var/run/apache2/apache2.pid && sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
