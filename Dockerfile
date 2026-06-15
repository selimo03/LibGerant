FROM php:8.2-cli

# Install PHP extensions needed for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
COPY . /app

WORKDIR /app

# Use PHP entrypoint (no shell script = no CRLF issues)
CMD ["php", "/app/entrypoint.php"]
