FROM php:8.0-apache

# Extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Corriger le conflit MPM Apache
RUN a2dismod mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork rewrite

# Copier tout le projet
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
