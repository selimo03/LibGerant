<?php
// config/db.php
// Connexion à la base de données libgerant_db avec PDO

// En production (Railway), les variables d'environnement sont injectées automatiquement
// En local (XAMPP), les valeurs par défaut s'appliquent
// Support both Railway variable naming conventions
$host    = getenv('MYSQLHOST')        ?: 'localhost';
$db      = getenv('MYSQLDATABASE')    ?: (getenv('MYSQL_DATABASE') ?: 'railway');
$user    = getenv('MYSQLUSER')        ?: 'root';
$pass    = getenv('MYSQLPASSWORD')    ?: (getenv('MYSQL_ROOT_PASSWORD') ?: '');
$port    = getenv('MYSQLPORT')        ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lance des exceptions en cas d'erreurs SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Les résultats seront des tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Sécurité renforcée pour requêtes préparées
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // Forcer une collation uniforme pour éviter les conflits entre tables
     $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
