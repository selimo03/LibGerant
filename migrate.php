<?php
// migrate.php — Auto-import schema.sql into Railway MySQL on first deploy

$host = getenv('MYSQLHOST')     ?: 'localhost';
$db   = getenv('MYSQLDATABASE') ?: 'libgerant_db';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = getenv('MYSQLPORT')     ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if tables already exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
    if ($stmt->rowCount() > 0) {
        echo "Tables already exist — skipping migration.\n";
        exit(0);
    }

    echo "Tables not found — importing schema.sql...\n";

    $sql = file_get_contents(__DIR__ . '/schema.sql');

    // Remove CREATE DATABASE and USE statements (Railway already created the DB)
    $sql = preg_replace('/CREATE DATABASE.*?;/si', '', $sql);
    $sql = preg_replace('/USE\s+`[^`]+`\s*;/i', '', $sql);

    // Execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s)
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    echo "Schema imported successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
