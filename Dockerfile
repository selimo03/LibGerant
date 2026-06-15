FROM php:8.0-apache

# Extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activer mod_rewrite Apache
RUN a2enmod rewrite

# Copier tout le projet dans le dossier web Apache
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Port Railway
EXPOSE 80
