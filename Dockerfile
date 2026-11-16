FROM php:8.3-apache

# # Fix: keep only ONE MPM (mod_php needs prefork)
# RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
#  && a2enmod mpm_prefork rewrite headers

 # Keep exactly ONE MPM (prefork) - remove every other MPM symlink directly
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* \
          /etc/apache2/mods-enabled/mpm_worker.* \
          /etc/apache2/mods-enabled/mpm_prefork.* \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers \
    && apache2ctl -M 2>&1 | grep -c mpm_ | grep -qx 1

# PHP extensions
RUN docker-php-ext-install mysqli

# Copy the website (adjust the path to match your repo)
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Railway gives a PORT variable; make Apache listen on it
CMD ["/bin/sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
