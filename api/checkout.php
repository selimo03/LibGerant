<?php
// api/checkout.php — Processus de commande en ligne pour LibGérant
require_once __DIR__ . '/../config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit();
}

// Récupération des données POST (supporte JSON brut ou urlencoded)
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (!$input) {
    $input = $_POST;
}

$cart_json = $input['cart_json'] ?? '';
$cart_items = json_decode($cart_json, true);

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'Votre panier est vide.']);
    exit();
}

$mode_reglement = $input['mode_reglement'] ?? 'especes';
if (!in_array($mode_reglement, ['especes', 'carte', 'mobile_money', 'cheque'])) {
    $mode_reglement = 'especes';
}

$id_client = null;
$id_vendeur = 1; // Par défaut, Administrateur (ID 1) pour les commandes en ligne

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if (in_array($user_role, ['admin', 'libraire'])) {
        $id_vendeur = $user_id;
    }
    
    // Rechercher le profil client associé
    $stmt_cli = $pdo->prepare("SELECT id_client FROM clients WHERE id_utilisateur = :uid LIMIT 1");
    $stmt_cli->execute([':uid' => $user_id]);
    $client = $stmt_cli->fetch();
    
    if ($client) {
        $id_client = $client['id_client'];
    } else {
        // Créer un profil client automatiquement s'il n'existe pas
        $code_client = 'CLI-' . rand(10000, 99999);
        $nom = $_SESSION['user_name'] ?? 'Utilisateur Connecté';
        $email = $_SESSION['user_email'] ?? '';
        
        $stmt_ins = $pdo->prepare("INSERT INTO clients (id_utilisateur, code_client, nom, email, statut) VALUES (:uid, :code, :nom, :email, 'Nouveau')");
        $stmt_ins->execute([
            ':uid' => $user_id,
            ':code' => $code_client,
            ':nom' => $nom,
            ':email' => !empty($email) ? $email : null
        ]);
        $id_client = $pdo->lastInsertId();
    }
} else {
    // Commande Invité (sans compte)
    $nom = trim($input['nom'] ?? '');
    $email = trim($input['email'] ?? '');
    $telephone = trim($input['telephone'] ?? '');
    
    if (empty($nom)) {
        echo json_encode(['success' => false, 'message' => 'Le nom complet est obligatoire pour passer commande.']);
        exit();
    }
    
    // Vérifier si un client existe déjà avec cet email ou téléphone
    if (!empty($email)) {
        $stmt_check = $pdo->prepare("SELECT id_client FROM clients WHERE email = :email LIMIT 1");
        $stmt_check->execute([':email' => $email]);
        $existing = $stmt_check->fetch();
        if ($existing) {
            $id_client = $existing['id_client'];
        }
    }
    
    if (!$id_client && !empty($telephone)) {
        $stmt_check = $pdo->prepare("SELECT id_client FROM clients WHERE telephone = :tel LIMIT 1");
        $stmt_check->execute([':tel' => $telephone]);
        $existing = $stmt_check->fetch();
        if ($existing) {
            $id_client = $existing['id_client'];
        }
    }
    
    // Si aucun profil client existant, en créer un nouveau
    if (!$id_client) {
        $code_client = 'CLI-' . rand(10000, 99999);
        $stmt_ins = $pdo->prepare("INSERT INTO clients (code_client, nom, email, telephone, statut) VALUES (:code, :nom, :email, :tel, 'Nouveau')");
        $stmt_ins->execute([
            ':code' => $code_client,
            ':nom' => $nom,
            ':email' => !empty($email) ? $email : null,
            ':tel' => !empty($telephone) ? $telephone : null
        ]);
        $id_client = $pdo->lastInsertId();
    }
}

// Générer un code de transaction unique
$code_trx = 'TRX-' . rand(10000, 99999);

$pdo->beginTransaction();
try {
    $total_montant = 0;
    $processed_items = [];
    
    foreach ($cart_items as $item) {
        $title = $item['title'] ?? '';
        $type = $item['type'] ?? 'Papier';
        
        // Recherche du livre dans la base
        if (!empty($item['isbn'])) {
            $stmt_bk = $pdo->prepare("SELECT isbn, prix_vente, titre, quantite_stock FROM livres WHERE isbn = :isbn LIMIT 1");
            $stmt_bk->execute([':isbn' => $item['isbn']]);
        } else {
            $stmt_bk = $pdo->prepare("SELECT isbn, prix_vente, titre, quantite_stock FROM livres WHERE titre = :titre LIMIT 1");
            $stmt_bk->execute([':titre' => $title]);
        }
        
        $book = $stmt_bk->fetch();
        if (!$book) {
            throw new Exception("Livre non trouvé dans le catalogue : " . $title);
        }
        
        $price = floatval($book['prix_vente']);
        if ($type === 'E-book') {
            // Le format E-book bénéficie d'une réduction (calcul identique à index.php)
            $price = round($price * 0.47 / 100) * 100;
        }
        
        $qty = 1; // Dans le panier frontend, chaque clic ajoute une unité
        
        if ($type === 'Papier') {
            if ($book['quantite_stock'] < $qty) {
                throw new Exception("Stock insuffisant pour le livre : " . $book['titre']);
            }
        }
        
        $total_montant += $price * $qty;
        $processed_items[] = [
            'isbn' => $book['isbn'],
            'price' => $price,
            'quantity' => $qty,
            'type' => $type
        ];
    }
    
    // 2. Insérer la vente principale
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
    
    // 3. Insérer les lignes de vente et décrémenter le stock
    $sql_ligne = "INSERT INTO lignes_ventes (id_vente, isbn, quantite, prix_unitaire, type_achat) 
                  VALUES (:id_vente, :isbn, :quantite, :prix, :type)";
    $stmt_ligne = $pdo->prepare($sql_ligne);
    
    $sql_update_stock = "UPDATE livres SET quantite_stock = quantite_stock - :qty WHERE isbn = :isbn";
    $stmt_update_stock = $pdo->prepare($sql_update_stock);
    
    foreach ($processed_items as $p_item) {
        $stmt_ligne->execute([
            ':id_vente' => $id_vente,
            ':isbn' => $p_item['isbn'],
            ':quantite' => $p_item['quantity'],
            ':prix' => $p_item['price'],
            ':type' => $p_item['type']
        ]);
        
        if ($p_item['type'] === 'Papier') {
            $stmt_update_stock->execute([
                ':qty' => $p_item['quantity'],
                ':isbn' => $p_item['isbn']
            ]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Votre commande a été passée avec succès !',
        'code_transaction' => $code_trx,
        'id_vente' => $id_vente
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
