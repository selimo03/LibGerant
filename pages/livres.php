<?php
// pages/livres.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

$success = null;
$error   = null;

// ── Ajouter un livre ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF). Veuillez réessayer.";
    } else {
        $isbn      = trim($_POST['isbn'] ?? '');
        $titre     = trim($_POST['titre'] ?? '');
        $auteur    = trim($_POST['auteur'] ?? '');
        $categorie = trim($_POST['categorie'] ?? '');
        $prix      = floatval($_POST['prix_vente'] ?? 0);
        $stock     = intval($_POST['quantite_stock'] ?? 0);
        $seuil     = intval($_POST['seuil_alerte'] ?? 3);
        $description = trim($_POST['description'] ?? '');

        if ($isbn && $titre && $auteur && $categorie && $prix > 0) {
            try {
                $chk = $pdo->prepare("SELECT isbn FROM livres WHERE isbn=:isbn");
                $chk->execute([':isbn' => $isbn]);
                if ($chk->fetch()) {
                    $error = "Un livre avec cet ISBN existe déjà.";
                } else {
                    $pdo->prepare("INSERT INTO livres (isbn,titre,auteur,categorie,description,prix_vente,quantite_stock,seuil_alerte) VALUES (:isbn,:titre,:auteur,:cat,:desc,:prix,:stock,:seuil)")
                        ->execute([':isbn'=>$isbn,':titre'=>$titre,':auteur'=>$auteur,':cat'=>$categorie,':desc'=>$description,':prix'=>$prix,':stock'=>$stock,':seuil'=>$seuil]);
                    regenerate_csrf_token();
                    $success = "Livre « $titre » ajouté au catalogue.";
                }
            } catch (\PDOException $e) { $error = "Erreur : " . $e->getMessage(); }
        } else {
            $error = "Remplissez tous les champs obligatoires (*)";
        }
    }
}

// ── Modifier un livre ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF). Veuillez réessayer.";
    } else {
        $isbn_orig = trim($_POST['isbn_original'] ?? '');
        $titre     = trim($_POST['titre'] ?? '');
        $auteur    = trim($_POST['auteur'] ?? '');
        $categorie = trim($_POST['categorie'] ?? '');
        $prix      = floatval($_POST['prix_vente'] ?? 0);
        $stock     = intval($_POST['quantite_stock'] ?? 0);
        $seuil     = intval($_POST['seuil_alerte'] ?? 3);
        $description = trim($_POST['description'] ?? '');

        if ($isbn_orig && $titre) {
            try {
                $pdo->prepare("UPDATE livres SET titre=:titre,auteur=:auteur,categorie=:cat,description=:desc,prix_vente=:prix,quantite_stock=:stock,seuil_alerte=:seuil WHERE isbn=:isbn")
                    ->execute([':titre'=>$titre,':auteur'=>$auteur,':cat'=>$categorie,':desc'=>$description,':prix'=>$prix,':stock'=>$stock,':seuil'=>$seuil,':isbn'=>$isbn_orig]);
                regenerate_csrf_token();
                $success = "Livre « $titre » mis à jour avec succès.";
            } catch (\PDOException $e) { $error = "Erreur : " . $e->getMessage(); }
        } else {
            $error = "Données de modification invalides.";
        }
    }
}

// ── Supprimer un livre (POST avec CSRF) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF).";
    } else {
        $isbn_del = trim($_POST['isbn_del'] ?? '');
        if ($isbn_del) {
            try {
                $pdo->prepare("DELETE FROM livres WHERE isbn=:isbn")->execute([':isbn'=>$isbn_del]);
                regenerate_csrf_token();
                $success = "Livre retiré du catalogue.";
            } catch (\PDOException $e) {
                try {
                    $pdo->prepare("UPDATE livres SET quantite_stock=0 WHERE isbn=:isbn")->execute([':isbn'=>$isbn_del]);
                    $error = "Ce livre a des transactions associées. Son stock a été mis à 0 au lieu de le supprimer.";
                } catch (\PDOException $e2) { $error = "Erreur : " . $e2->getMessage(); }
            }
        }
    }
}

// ── Données ──────────────────────────────────────────────────────────────
try {
    $categories = $pdo->query("SELECT DISTINCT categorie FROM livres ORDER BY categorie ASC")->fetchAll(PDO::FETCH_COLUMN);
    $stmt_stats = $pdo->query("SELECT COUNT(*) as nb_refs, SUM(quantite_stock) as total_items, SUM(prix_vente * quantite_stock) as valeur_stock, SUM(CASE WHEN quantite_stock <= seuil_alerte THEN 1 ELSE 0 END) as low_stock FROM livres");
    $stats = $stmt_stats->fetch();
} catch (\PDOException $e) { 
    $categories = []; 
    $stats = ['nb_refs'=>0, 'total_items'=>0, 'valeur_stock'=>0, 'low_stock'=>0]; 
}

$search     = trim($_GET['search'] ?? '');
$cat_filter = trim($_GET['categorie'] ?? '');
$sql  = "SELECT * FROM livres WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (titre LIKE :s1 OR auteur LIKE :s2 OR isbn LIKE :s3)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
}
if ($cat_filter !== '' && $cat_filter !== 'Toutes les catégories') {
    $sql .= " AND categorie=:cat";
    $params[':cat'] = $cat_filter;
}
$sql .= " ORDER BY titre ASC";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
} catch (\PDOException $e) {
    $books = []; $error = "Erreur : " . $e->getMessage();
}

$page_title   = 'Catalogue & Stock';
$current_page = 'livres';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Catalogue & Stock</h1>
                <p class="text-muted mb-0">Gérez vos références, suivez votre inventaire et ajoutez de nouveaux ouvrages.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addBookModal" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;">
                <i class="fas fa-plus me-2"></i> Ajouter une Référence
            </button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-success-subtle shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px">
                <i class="fas fa-check-circle fs-4 me-3 text-success"></i>
                <div><?= htmlspecialchars($success) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-danger-subtle shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px">
                <i class="fas fa-exclamation-circle fs-4 me-3 text-danger"></i>
                <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KPIs Stock -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">Références Uniques</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['nb_refs'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-book fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">Articles en Stock</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['total_items'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:var(--accent);width:48px;height:48px;border-radius:12px;"><i class="fas fa-boxes fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">Valeur d'Inventaire</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['valeur_stock'] ?? 0,0,',',' ') ?> <span class="fs-6 text-muted">FCFA</span></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(236,72,153,.1);color:var(--secondary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-coins fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0 <?= ($stats['low_stock'] > 0) ? '' : '' ?>" style="background: linear-gradient(145deg, #ffffff, <?= ($stats['low_stock'] > 0) ? '#fff3cd' : '#f8f9fc' ?>); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold <?= ($stats['low_stock'] > 0) ? 'text-warning' : 'text-muted' ?> text-uppercase tracking-wide">Alertes Stock</p>
                            <h3 style="font-weight:800;color:<?= ($stats['low_stock'] > 0) ? '#b45309' : 'var(--text-heading)' ?>"><?= number_format($stats['low_stock'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:var(--warning);width:48px;height:48px;border-radius:12px;"><i class="fas fa-exclamation-triangle fs-5"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres Avancés -->
        <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
            <form method="GET" action="livres.php" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-lg shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        <span class="input-group-text bg-white border-0 text-muted px-4"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-0 bg-white" placeholder="Rechercher (Titre, Auteur, ISBN...)" value="<?= htmlspecialchars($search) ?>" style="box-shadow: none;">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-lg shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        <span class="input-group-text bg-white border-0 text-muted px-4"><i class="fas fa-filter"></i></span>
                        <select name="categorie" class="form-select border-0 bg-white" style="box-shadow: none;">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $cat_filter===$cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1 shadow-sm fw-bold" style="border-radius: 12px;">Appliquer</button>
                    <?php if ($search || $cat_filter): ?>
                        <a href="livres.php" class="btn btn-light btn-lg shadow-sm text-secondary" style="border-radius: 12px;" title="Réinitialiser"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card p-0 shadow-sm border-0 overflow-hidden" style="border-radius: 16px;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableLivres">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">ISBN</th>
                            <th class="border-0 py-3">Livre & Auteur</th>
                            <th class="border-0 py-3">Catégorie</th>
                            <th class="border-0 py-3">Prix</th>
                            <th class="border-0 py-3">Stock</th>
                            <th class="border-0 pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php foreach ($books as $b): ?>
                        <?php
                            $qty = $b['quantite_stock']; $seuil = $b['seuil_alerte'];
                            if ($qty <= 0)     $sbadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-times-circle me-1"></i>Rupture</span>';
                            elseif ($qty<=$seuil) $sbadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-exclamation-triangle me-1"></i>' . $qty . ' restants</span>';
                            else               $sbadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-check-circle me-1"></i>' . $qty . ' en stock</span>';
                        ?>
                        <tr>
                            <td class="ps-4 small text-muted font-monospace"><?= htmlspecialchars($b['isbn']) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded-3 shadow-sm border" style="width:45px;height:60px;flex-shrink:0">
                                        <i class="fas fa-book text-primary opacity-75 fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark" style="font-size: 1.05rem;"><?= htmlspecialchars($b['titre']) ?></div>
                                        <div class="small text-muted"><i class="fas fa-pen-nib me-1 opacity-50"></i><?= htmlspecialchars($b['auteur']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-secondary border px-3 py-2 rounded-pill shadow-sm"><?= htmlspecialchars($b['categorie']) ?></span></td>
                            <td class="font-weight-bold text-primary fs-6"><?= number_format($b['prix_vente'],0,',',' ') ?> FCFA</td>
                            <td><?= $sbadge ?></td>
                            <td class="pe-4 text-end">
                                <!-- Bouton Modifier -->
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm border me-1 btn-edit-book transition-hover"
                                        title="Modifier"
                                        style="width: 36px; height: 36px;"
                                        data-isbn="<?= htmlspecialchars($b['isbn']) ?>"
                                        data-titre="<?= htmlspecialchars($b['titre'],ENT_QUOTES) ?>"
                                        data-auteur="<?= htmlspecialchars($b['auteur'],ENT_QUOTES) ?>"
                                        data-categorie="<?= htmlspecialchars($b['categorie'],ENT_QUOTES) ?>"
                                        data-prix="<?= $b['prix_vente'] ?>"
                                        data-stock="<?= $b['quantite_stock'] ?>"
                                        data-seuil="<?= $b['seuil_alerte'] ?>"
                                        data-description="<?= htmlspecialchars($b['description'] ?? '',ENT_QUOTES) ?>"
                                        data-bs-toggle="modal" data-bs-target="#editBookModal">
                                    <i class="fas fa-pen text-primary"></i>
                                </button>
                                <!-- Bouton Supprimer -->
                                <form method="POST" action="livres.php" class="d-inline"
                                      onsubmit="return confirm('Supprimer définitivement « <?= addslashes($b['titre']) ?> » ?\n\nSi le livre a déjà été vendu ou emprunté, son stock sera simplement mis à zéro pour conserver l\'historique.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="isbn_del" value="<?= htmlspecialchars($b['isbn']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm border transition-hover" title="Supprimer" style="width: 36px; height: 36px;">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal : Ajouter un Livre -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title font-weight-bold" style="color:var(--text-heading);"><i class="fas fa-plus-circle me-2 text-primary"></i>Ajouter une nouvelle référence</h5>
        <button type="button" class="btn-close shadow-sm bg-white rounded-circle" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="livres.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <div class="modal-body p-4 p-md-5">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">ISBN *</label>
                    <div class="input-group">
                        <input type="text" name="isbn" id="add_isbn" class="form-control form-control-lg rounded-start-3 bg-light border-0 px-3 fs-6" placeholder="Ex: 978-2-1234-5678-9" required>
                        <button type="button" id="btnIsbnSearch" class="btn btn-outline-primary border-0 bg-light px-3" title="Rechercher les infos via ISBN">
                            <i class="fas fa-search" id="isbnSearchIcon"></i>
                        </button>
                    </div>
                    <div class="form-text mt-1"><i class="fas fa-magic me-1 text-primary"></i>Entrez l'ISBN et cliquez <i class="fas fa-search"></i> pour auto-remplir.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Catégorie *</label>
                    <input type="text" name="categorie" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" placeholder="Sélectionner ou saisir..." list="cats-list" required>
                    <datalist id="cats-list"><?php foreach($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Titre de l'ouvrage *</label>
                    <input type="text" name="titre" id="add_titre" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" placeholder="Le grand livre du savoir" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Auteur(s) *</label>
                    <input type="text" name="auteur" id="add_auteur" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" placeholder="Prénom Nom" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Description courte</label>
                    <textarea name="description" id="add_description" class="form-control rounded-3 bg-light border-0 px-3 py-2 fs-6" rows="3" placeholder="Résumé de l'œuvre..."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-primary text-uppercase tracking-wide">Prix de Vente (FCFA) *</label>
                    <div class="input-group">
                        <input type="number" name="prix_vente" step="50" class="form-control form-control-lg rounded-start-3 bg-primary-subtle border-0 px-3 fs-6 fw-bold text-primary" placeholder="0" required>
                        <span class="input-group-text bg-primary-subtle border-0 text-primary rounded-end-3">FCFA</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-success text-uppercase tracking-wide">Quantité Initiale</label>
                    <input type="number" name="quantite_stock" class="form-control form-control-lg rounded-3 bg-success-subtle border-0 px-3 fs-6 fw-bold text-success" value="1" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-warning text-uppercase tracking-wide">Seuil d'Alerte</label>
                    <input type="number" name="seuil_alerte" class="form-control form-control-lg rounded-3 bg-warning-subtle border-0 px-3 fs-6 fw-bold text-warning" value="3" min="0">
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 bg-light">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Créer la Référence</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal : Modifier un Livre -->
<div class="modal fade" id="editBookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title font-weight-bold" style="color:var(--text-heading);"><i class="fas fa-pen-square me-2 text-primary"></i>Modifier la référence</h5>
        <button type="button" class="btn-close shadow-sm bg-white rounded-circle" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="livres.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="isbn_original" id="edit_isbn_original">
        <div class="modal-body p-4 p-md-5">
            <div class="alert bg-white border shadow-sm rounded-3 mb-4 d-flex align-items-center">
                <i class="fas fa-barcode fs-3 text-muted me-3"></i>
                <div>
                    <div class="small text-muted text-uppercase tracking-wide fw-bold">Code ISBN</div>
                    <div class="fs-5 font-monospace fw-bold" id="edit_isbn_display" style="color:var(--text-heading);"></div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Titre de l'ouvrage *</label>
                    <input type="text" name="titre" id="edit_titre" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Auteur(s) *</label>
                    <input type="text" name="auteur" id="edit_auteur" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Catégorie *</label>
                    <input type="text" name="categorie" id="edit_categorie" class="form-control form-control-lg rounded-3 bg-light border-0 px-3 fs-6" list="cats-list" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Description courte</label>
                    <textarea name="description" id="edit_description" class="form-control rounded-3 bg-light border-0 px-3 py-2 fs-6" rows="3"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-primary text-uppercase tracking-wide">Prix de Vente (FCFA) *</label>
                    <div class="input-group">
                        <input type="number" name="prix_vente" id="edit_prix" step="50" class="form-control form-control-lg rounded-start-3 bg-primary-subtle border-0 px-3 fs-6 fw-bold text-primary" required>
                        <span class="input-group-text bg-primary-subtle border-0 text-primary rounded-end-3">FCFA</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-success text-uppercase tracking-wide">Quantité en Stock</label>
                    <input type="number" name="quantite_stock" id="edit_stock" class="form-control form-control-lg rounded-3 bg-success-subtle border-0 px-3 fs-6 fw-bold text-success" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-warning text-uppercase tracking-wide">Seuil d'Alerte</label>
                    <input type="number" name="seuil_alerte" id="edit_seuil" class="form-control form-control-lg rounded-3 bg-warning-subtle border-0 px-3 fs-6 fw-bold text-warning" min="0">
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 bg-light">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Mettre à jour</button>
        </div>
      </form>
    </div>
  </div>
</div>
<style>
    .transition-hover { transition: transform 0.2s, box-shadow 0.2s; }
    .transition-hover:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .tracking-wide { letter-spacing: 0.05em; }
</style>
<?php
$datatable_ids = ['tableLivres'];
$extra_js = <<<JS
<script>
// Pré-remplissage du modal d'édition via les data-attributes du bouton
document.querySelectorAll('.btn-edit-book').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_isbn_original').value  = btn.dataset.isbn;
        document.getElementById('edit_isbn_display').textContent = btn.dataset.isbn;
        document.getElementById('edit_titre').value          = btn.dataset.titre;
        document.getElementById('edit_auteur').value         = btn.dataset.auteur;
        document.getElementById('edit_categorie').value      = btn.dataset.categorie;
        document.getElementById('edit_prix').value           = btn.dataset.prix;
        document.getElementById('edit_stock').value          = btn.dataset.stock;
        document.getElementById('edit_seuil').value          = btn.dataset.seuil;
        document.getElementById('edit_description').value    = btn.dataset.description;
    });
});

// ── Recherche ISBN via Open Library ──────────────────────────────────────
document.getElementById('btnIsbnSearch').addEventListener('click', function() {
    var isbn = document.getElementById('add_isbn').value.trim().replace(/[-\s]/g, '');
    if (!isbn) { alert('Veuillez saisir un ISBN avant de rechercher.'); return; }
    var icon = document.getElementById('isbnSearchIcon');
    icon.className = 'fas fa-spinner fa-spin';
    this.disabled = true;
    var self = this;
    fetch('https://openlibrary.org/api/books?bibkeys=ISBN:' + isbn + '&format=json&jscmd=data')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var key = 'ISBN:' + isbn;
            if (!data[key]) { alert('Aucun livre trouvé pour cet ISBN sur Open Library. Remplissez les champs manuellement.'); return; }
            var book = data[key];
            if (book.title)  document.getElementById('add_titre').value = book.title;
            if (book.authors && book.authors.length > 0)
                document.getElementById('add_auteur').value = book.authors.map(function(a){return a.name;}).join(', ');
            if (book.notes || book.excerpts)
                document.getElementById('add_description').value = (book.notes || book.excerpts[0]?.text || '').substring(0,300);
            // Feedback visuel
            document.getElementById('add_titre').style.background = 'rgba(16,185,129,.1)';
            setTimeout(function(){ document.getElementById('add_titre').style.background=''; }, 2000);
        })
        .catch(function() { alert('Erreur de connexion à Open Library. Vérifiez votre connexion internet.'); })
        .finally(function() {
            icon.className = 'fas fa-search';
            self.disabled = false;
        });
});
</script>
JS;
require_once __DIR__ . '/../includes/foot.php';
?>
