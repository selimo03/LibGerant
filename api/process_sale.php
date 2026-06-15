<?php
// api/process_sale.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Seuls les administrateurs et les libraires (vendeurs) peuvent enregistrer une vente
authorize(['admin', 'libraire']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: ../pages/prets.php?status=error&message=" . urlencode("Requête invalide (CSRF). Veuillez réessayer."));
        exit();
    }

    $id_client = isset($_POST['id_client']) && $_POST['id_client'] !== '' ? intval($_POST['id_client']) : null;
    $mode_reglement = $_POST['mode_reglement'] ?? 'especes';
    $id_vendeur = $_SESSION['user_id'];
    
    $cart_items = [];
    
    // Cas 1 : Panier JSON envoyé depuis le site frontend (AJAX ou formulaire caché)
    if (isset($_POST['cart_json'])) {
        $cart_items = json_decode($_POST['cart_json'], true);
    } 
    // Cas 2 : Soumission de formulaire classique depuis l'espace libraire (1 seul livre)
    elseif (isset($_POST['isbn'])) {
        $isbn = $_POST['isbn'];
        $qty = intval($_POST['quantite'] ?? 1);
        
        try {
            $stmt_price = $pdo->prepare("SELECT prix_vente, titre, quantite_stock FROM livres WHERE isbn = :isbn");
            $stmt_price->execute([':isbn' => $isbn]);
            $book_info = $stmt_price->fetch();
            
            if ($book_info) {
                $cart_items[] = [
                    'isbn' => $isbn,
                    'title' => $book_info['titre'],
                    'price' => floatval($book_info['prix_vente']),
                    'quantity' => $qty,
                    'type' => $_POST['type'] ?? 'Papier'
                ];
            }
        } catch (\PDOException $e) {
            header("Location: ../pages/prets.php?status=error&message=" . urlencode("Livre introuvable."));
            exit();
        }
    }
    
    if (empty($cart_items)) {
        header("Location: ../pages/prets.php?status=error&message=" . urlencode("Le panier est vide."));
        exit();
    }
    
    // Générer un code de transaction unique
    $code_trx = 'TRX-' . rand(10000, 99999);
    
    // Démarrer la transaction SQL
    $pdo->beginTransaction();
    
    try {
        // 1. Calculer le total et insérer la vente principale
        $total_montant = 0;
        foreach ($cart_items as $item) {
            $total_montant += floatval($item['price']) * intval($item['quantity']);
        }
        
        $sql_vente = "INSERT INTO ventes (code_transaction, id_client, id_vendeur, total_montant, mode_reglement) 
                      VALUES (:code, :client, :vendeur, :total, :reglement)";
        $stmt_vente = $pdo->prepare($sql_vente);
        $stmt_vente->execute([
            ':code' => $code_trx,
            ':client' => $id_client,
            ':vendeur' => $id_vendeur,
            ':total' => $total_montant,
            ':reglement' => $mode_reglement
        ]);
        
        $id_vente = $pdo->lastInsertId();
        
        // 2. Insérer chaque ligne de vente et décrémenter le stock
        $sql_ligne = "INSERT INTO lignes_ventes (id_vente, isbn, quantite, prix_unitaire, type_achat) 
                      VALUES (:id_vente, :isbn, :quantite, :prix, :type)";
        $stmt_ligne = $pdo->prepare($sql_ligne);
        
        $sql_update_stock = "UPDATE livres SET quantite_stock = quantite_stock - :qty WHERE isbn = :isbn";
        $stmt_update_stock = $pdo->prepare($sql_update_stock);
        
        $sql_check_stock = "SELECT quantite_stock, titre FROM livres WHERE isbn = :isbn";
        $stmt_check_stock = $pdo->prepare($sql_check_stock);
        
        foreach ($cart_items as $item) {
            $isbn = $item['isbn'];
            $qty = intval($item['quantity']);
            $price = floatval($item['price']);
            $type = $item['type'] ?? 'Papier';
            
            if ($type === 'Papier') {
                // Vérifier la disponibilité en stock avant de décrémenter
                $stmt_check_stock->execute([':isbn' => $isbn]);
                $livre = $stmt_check_stock->fetch();
                
                if (!$livre || $livre['quantite_stock'] < $qty) {
                    throw new Exception("Stock insuffisant ou livre inexistant : " . ($livre['titre'] ?? $isbn));
                }
                
                // Mettre à jour le stock
                $stmt_update_stock->execute([':qty' => $qty, ':isbn' => $isbn]);
            }
            
            // Enregistrer la ligne de vente
            $stmt_ligne->execute([
                ':id_vente' => $id_vente,
                ':isbn' => $isbn,
                ':quantite' => $qty,
                ':prix' => $price,
                ':type' => $type
            ]);
        }
        
        // Valider l'ensemble des requêtes
        $pdo->commit();
        header("Location: ../pages/prets.php?status=success&trx=" . $code_trx);
        exit();
        
    } catch (Exception $e) {
        // En cas d'erreur, annuler les changements de stock
        $pdo->rollBack();
        header("Location: ../pages/prets.php?status=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}
?>
