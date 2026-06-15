FROM php:8.2-cli

# Install PHP extensions needed for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
COPY . /app

WORKDIR /app

# Railway sets $PORT dynamically — we must listen on it
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80} -t /app"]
