#!/bin/bash
set -e

echo "=== LibGerant Startup ==="
echo "Waiting for MySQL to be ready..."

# Wait for MySQL to accept connections (max 30 seconds)
for i in $(seq 1 30); do
    if php -r "
        \$host = getenv('MYSQLHOST') ?: 'localhost';
        \$port = getenv('MYSQLPORT') ?: '3306';
        \$user = getenv('MYSQLUSER') ?: 'root';
        \$pass = getenv('MYSQLPASSWORD') ?: '';
        \$db   = getenv('MYSQLDATABASE') ?: 'libgerant_db';
        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
            echo 'connected';
        } catch(Exception \$e) {
            exit(1);
        }
    " 2>/dev/null | grep -q 'connected'; then
        echo "MySQL is ready!"
        break
    fi
    echo "Attempt $i/30 - MySQL not ready yet, waiting..."
    sleep 1
done

echo "Running database migrations..."
php /app/migrate.php && echo "Migration done!" || echo "Migration skipped (already done)"

echo "Starting PHP server on port ${PORT:-80}..."
exec php -S "0.0.0.0:${PORT:-80}" -t /app
