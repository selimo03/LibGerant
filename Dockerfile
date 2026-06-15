FROM php:8.0-apache

# Extensions PHP
RUN docker-php-ext-install pdo pdo_mysql

# Supprimer les modules MPM en conflit directement
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
    && a2enmod mpm_prefork rewrite

# Copier le projet
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
