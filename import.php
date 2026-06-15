<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql'])) {
    require_once __DIR__ . '/config/db.php';
    $sql = file_get_contents($_FILES['sql']['tmp_name']);
    try {
        $pdo->exec($sql);
        echo '<p style="color:green;font-size:20px;font-family:sans-serif">✅ Base importée avec succès ! <a href="import.php">Supprimer ce fichier maintenant.</a></p>';
    } catch (Exception $e) {
        echo '<p style="color:red;font-family:sans-serif">❌ Erreur : ' . $e->getMessage() . '</p>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Import SQL</title></head>
<body style="font-family:sans-serif;padding:40px">
<h2>📦 Importer la base de données</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="sql" accept=".sql" required><br><br>
    <button type="submit" style="padding:10px 30px;background:#6366f1;color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer">Importer</button>
</form>
</body>
</html>
