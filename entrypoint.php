<?php
// entrypoint.php — Railway startup: migrate DB then launch PHP server

$host = getenv('MYSQLHOST')     ?: 'localhost';
$db   = getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'railway');
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: '');
$port = getenv('MYSQLPORT')     ?: '3306';
$appPort = getenv('PORT')       ?: '80';

echo "=== LibGerant entrypoint ===\n";
echo "Host: $host | Port: $port | DB: $db\n";

// Wait for MySQL (max 30 attempts)
$connected = false;
for ($i = 1; $i <= 30; $i++) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "MySQL connected on attempt $i!\n";
        $connected = true;
        break;
    } catch (Exception $e) {
        echo "Attempt $i/30 — waiting for MySQL... ({$e->getMessage()})\n";
        sleep(1);
    }
}

if (!$connected) {
    echo "ERROR: Could not connect to MySQL after 30 attempts. Starting anyway...\n";
} else {
    // Run migration if tables don't exist
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        if ($stmt->rowCount() === 0) {
            echo "Importing schema.sql...\n";
            $sql = file_get_contents(__DIR__ . '/schema.sql');

            // Remove CREATE DATABASE / USE statements (Railway DB already exists)
            $sql = preg_replace('/CREATE\s+DATABASE\b.*?;/si', '', $sql);
            $sql = preg_replace('/USE\s+`[^`]+`\s*;/i', '', $sql);

            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s)
            );

            foreach ($statements as $stmt_sql) {
                $pdo->exec($stmt_sql);
            }
            echo "Schema imported successfully!\n";
        } else {
            echo "Tables already exist — skipping migration.\n";
        }
    } catch (Exception $e) {
        echo "Migration error: " . $e->getMessage() . "\n";
    }
}

echo "Starting PHP server on 0.0.0.0:$appPort ...\n";
passthru("php -S 0.0.0.0:$appPort -t /app");
