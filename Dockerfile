FROM php:8.3-apache

# លុបឯកសារ mpm_event និង mpm_worker ចោលទាំងស្រុងពីប្រព័ន្ធដើម្បីកុំឱ្យ Apache ဖានឃើញ
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    /etc/apache2/mods-enabled/mpm_*.conf \
    /etc/apache2/mods-available/mpm_event.* \
    /etc/apache2/mods-available/mpm_worker.*

# បង្ខំតភ្ជាប់និងបើក صرف mpm_prefork តែមួយគត់
RUN ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# កំណត់ ServerName ដើម្បីការពារ Warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ចម្លង Source Code ទាំងអស់ចូល Apache directory
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# លុប PID ចាស់ និងរៀបចំ Port តាម Railway Dynamic PORT
CMD ["/bin/sh", "-c", "rm -f /var/run/apache2/apache2.pid && sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
