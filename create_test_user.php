<?php
require_once __DIR__ . '/config/db.php';
try {
    $pass = password_hash('adherent123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO utilisateurs (nom, email, mot_de_passe, role) VALUES ('Client Test', 'adherent@libgerant.com', :pass, 'adherent')");
    $stmt->execute(['pass' => $pass]);
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
