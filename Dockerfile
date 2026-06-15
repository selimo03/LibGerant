FROM php:8.2-cli

# Install PHP extensions needed for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
COPY . /app

WORKDIR /app

# Make startup script executable
RUN chmod +x /app/start.sh

# Railway sets $PORT dynamically — start.sh handles migration + server start
CMD ["/app/start.sh"]
