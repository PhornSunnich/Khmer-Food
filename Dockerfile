FROM php:8.3-apache

# លុប MPM modules ទាំងអស់ចេញពី mods-enabled ហើយបើកតែ mpm_prefork មួយគត់
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers

# mysqli និង pdo extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# ចម្លងកូដទាំងអស់ទៅកាន់ Apache web root
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# កំណត់ឱ្យ Apache ប្រើប្រាស់ Port តាម Railway Environment Variable ($PORT)
CMD ["/bin/sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]