<?php
// pages/dashboard-adherent.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['adherent']);

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'];
$error      = null;
$client     = null;
$client_code   = 'CLI-TEMP';
$client_status = 'Nouveau';
$loans      = [];
$emprunts   = [];
$suggestions = [];
$total_achats         = 0;
$total_emprunts       = 0;
$total_reservations   = 0;
$total_amendes        = 0;
$reservations_actives = [];

try {
    // Fiche client liée
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_utilisateur = :uid LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $client = $stmt->fetch();

    if ($client) {
        $client_id     = $client['id_client'];
        $client_code   = $client['code_client'];
        $client_status = $client['statut'];

        // Achats (ventes)
        $s = $pdo->prepare("SELECT COALESCE(SUM(lv.quantite),0) AS total FROM ventes v JOIN lignes_ventes lv ON v.id_vente=lv.id_vente WHERE v.id_client=:cid AND v.statut='Payé'");
        $s->execute([':cid' => $client_id]);
        $total_achats = $s->fetch()['total'] ?? 0;

        // Réservations
        $s = $pdo->prepare("SELECT COUNT(*) AS total FROM reservations WHERE id_client=:cid");
        $s->execute([':cid' => $client_id]);
        $total_reservations = $s->fetch()['total'] ?? 0;

        // Emprunts actifs + amendes
        try {
            $pdo->query("UPDATE emprunts SET statut='En retard' WHERE statut='En cours' AND date_retour_prev < CURDATE()");
            $pdo->query("UPDATE emprunts SET amende_fcfa=GREATEST(0,DATEDIFF(CURDATE(),date_retour_prev))*500 WHERE statut='En retard'");
            $s = $pdo->prepare("SELECT e.*, l.titre FROM emprunts e JOIN livres l ON e.isbn=l.isbn WHERE e.id_client=:cid AND e.statut IN ('En cours','En retard') ORDER BY e.date_emprunt DESC");
            $s->execute([':cid' => $client_id]);
            $emprunts = $s->fetchAll();
            $total_emprunts = count($emprunts);
            $total_amendes  = array_sum(array_column($emprunts, 'amende_fcfa'));
        } catch (\PDOException $ex) {
            $emprunts = [];
            $total_amendes = 0;
        }

        // Réservations actives
        try {
            $s = $pdo->prepare("SELECT r.*, l.titre FROM reservations r JOIN livres l ON r.isbn=l.isbn WHERE r.id_client=:cid AND r.statut NOT IN ('Annulé','Conclu') ORDER BY r.date_demande DESC");
            $s->execute([':cid' => $client_id]);
            $reservations_actives = $s->fetchAll();
        } catch (\PDOException $ex) {
            $reservations_actives = [];
        }

        // Historique des achats
        $s = $pdo->prepare("SELECT v.date_vente, v.code_transaction, l.titre, lv.type_achat, v.total_montant FROM ventes v JOIN lignes_ventes lv ON v.id_vente=lv.id_vente JOIN livres l ON lv.isbn=l.isbn WHERE v.id_client=:cid AND v.statut='Payé' ORDER BY v.date_vente DESC LIMIT 10");
        $s->execute([':cid' => $client_id]);
        $loans = $s->fetchAll();
    }

    $stmt_sug = $pdo->query("SELECT titre, auteur, categorie FROM livres ORDER BY RAND() LIMIT 3");
    $suggestions = $stmt_sug->fetchAll();

} catch (\PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
if (count($suggestions) < 3) {
    $suggestions = [
        ['titre'=>"L'Alchimiste",'auteur'=>"Paulo Coelho", 'categorie'=>"Roman"],
        ['titre'=>"Sapiens",'auteur'=>"Y. Noah Harari", 'categorie'=>"Histoire"],
        ['titre'=>"Clean Code",'auteur'=>"Robert C. Martin", 'categorie'=>"Informatique"]
    ];
}

$page_title   = 'Mon Espace';
$current_page = 'dashboard';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-5 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Bienvenue, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> !</h1>
                <p class="text-muted small mb-0 mt-1">Code Client : <strong class="text-primary"><?= htmlspecialchars($client_code) ?></strong> — Statut : <span class="badge bg-success-subtle text-success py-1 px-2 border border-success-subtle rounded-pill shadow-sm"><?= htmlspecialchars($client_status) ?></span></p>
            </div>
            <div class="d-flex gap-2">
                <a href="profil.php" class="btn btn-outline-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                    <i class="fas fa-user-circle me-2"></i>Mon Profil
                </a>
                <a href="../index.php#catalogue" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                    <i class="fas fa-search me-2"></i>Catalogue
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger border-0 small mb-4 shadow-sm" style="border-radius:12px;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card p-4 h-100 shadow-sm border-0 text-center" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="stat-card-icon mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:60px;height:60px;border-radius:50%;"><i class="fas fa-shopping-bag fs-4"></i></div>
                    <div class="h2 font-weight-bold" style="color:var(--text-heading)"><?= $total_achats ?></div>
                    <p class="text-muted small mb-0 fw-bold">Livres Achetés</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100 shadow-sm border-0 text-center" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="stat-card-icon mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:var(--accent);width:60px;height:60px;border-radius:50%;"><i class="fas fa-book-reader fs-4"></i></div>
                    <div class="h2 font-weight-bold" style="color:var(--text-heading)"><?= $total_emprunts ?></div>
                    <p class="text-muted small mb-0 fw-bold">Emprunts en cours</p>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card p-4 h-100 shadow-sm border-0 text-center" style="background: linear-gradient(145deg, #ffffff, #fdf4e3); border-radius: 16px;">
                    <div class="stat-card-icon mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:var(--warning);width:60px;height:60px;border-radius:50%;"><i class="fas fa-clock fs-4"></i></div>
                    <div class="h2 font-weight-bold" style="color:var(--text-heading)"><?= $total_reservations ?></div>
                    <p class="text-muted small mb-0 fw-bold">Réservations</p>
                </div>
            </div>
            <?php if ($total_amendes > 0): ?>
            <div class="col-md-4 col-6">
                <div class="card p-4 h-100 shadow-sm border-0 text-center" style="background: linear-gradient(145deg, #fff5f5, #fee2e2); border-radius: 16px; border:1px solid #fecaca!important">
                    <div class="stat-card-icon mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(239,68,68,.1);color:#ef4444;width:60px;height:60px;border-radius:50%;"><i class="fas fa-coins fs-4"></i></div>
                    <div class="h2 font-weight-bold text-danger"><?= number_format($total_amendes,0,',',' ') ?></div>
                    <p class="text-danger small mb-0 fw-bold">FCFA d'amendes dues</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Colonne principale (Emprunts + Achats) -->
            <div class="col-lg-8">
                <!-- Emprunts actifs -->
                <?php if (count($emprunts) > 0): ?>
                <div class="card p-0 shadow-sm border-0 overflow-hidden mb-4" style="border-radius: 16px;">
                    <div class="card-header bg-white p-4 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold" style="color:var(--text-heading)"><i class="fas fa-book-reader text-primary me-2"></i>Mes Emprunts en cours</h5>
                        <span class="badge bg-primary-subtle text-primary rounded-pill"><?= count($emprunts) ?> livre(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                                    <th class="border-0 ps-4 py-3">Livre</th>
                                    <th class="border-0 py-3">Emprunté le</th>
                                    <th class="border-0 py-3">Retour prévu</th>
                                    <th class="border-0 pe-4 py-3 text-end">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emprunts as $e): ?>
                                <?php
                                    $diff_days = (strtotime($e['date_retour_prev']) - time()) / 86400;
                                    if ($e['statut'] === 'En retard') {
                                        $badge = '<span class="badge bg-danger-subtle text-danger shadow-sm border border-danger-subtle rounded-pill px-3 py-2"><i class="fas fa-exclamation-circle me-1"></i>En retard</span>';
                                    } elseif ($diff_days <= 3) {
                                        $badge = '<span class="badge bg-warning-subtle text-warning shadow-sm border border-warning-subtle rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i>À rendre bientôt</span>';
                                    } else {
                                        $badge = '<span class="badge bg-success-subtle text-success shadow-sm border border-success-subtle rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Dans les temps</span>';
                                    }
                                    $amende_txt = $e['amende_fcfa'] > 0 ? ' <div class="text-danger small mt-1 fw-bold">Amende : ' . number_format($e['amende_fcfa'],0,',',' ') . ' FCFA</div>' : '';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-book text-primary opacity-75"></i>
                                            </div>
                                            <div class="font-weight-bold" style="color:var(--text-heading)"><?= htmlspecialchars($e['titre']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
                                    <td class="fw-bold"><?= date('d/m/Y', strtotime($e['date_retour_prev'])) ?></td>
                                    <td class="pe-4 text-end"><?= $badge ?><?= $amende_txt ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Réservations actives -->
                <?php if (count($reservations_actives) > 0): ?>
                <div class="card p-0 shadow-sm border-0 overflow-hidden mb-4" style="border-radius:16px">
                    <div class="card-header bg-white p-4 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color:var(--text-heading)"><i class="fas fa-clock text-warning me-2"></i>Mes Réservations</h5>
                        <span class="badge bg-warning-subtle text-warning rounded-pill"><?= count($reservations_actives) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr style="font-size:.85rem;text-transform:uppercase;color:var(--text-muted)">
                                    <th class="border-0 ps-4 py-3">Livre</th>
                                    <th class="border-0 py-3">Demandé le</th>
                                    <th class="border-0 pe-4 py-3 text-end">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations_actives as $r): ?>
                                <?php $sbadge = $r['statut']==='Produit Reçu'
                                    ? '<span class="badge bg-success-subtle text-success rounded-pill px-3 py-2"><i class="fas fa-box-open me-1"></i>Disponible !</span>'
                                    : '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2"><i class="fas fa-hourglass-half me-1"></i>En attente</span>'; ?>
                                <tr>
                                    <td class="ps-4 fw-semibold" style="color:var(--text-heading)"><?= htmlspecialchars($r['titre']) ?></td>
                                    <td class="text-muted"><?= date('d/m/Y', strtotime($r['date_demande'])) ?></td>
                                    <td class="pe-4 text-end"><?= $sbadge ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Historique achats -->
                <div class="card p-0 shadow-sm border-0 overflow-hidden mb-4" style="border-radius: 16px;">
                    <div class="card-header bg-white p-4 border-bottom-0">
                        <h5 class="mb-0 font-weight-bold" style="color:var(--text-heading)"><i class="fas fa-history text-accent me-2"></i>Historique de mes achats</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                                    <th class="border-0 ps-4 py-3">Livre</th>
                                    <th class="border-0 py-3">Type</th>
                                    <th class="border-0 py-3">Date</th>
                                    <th class="border-0 pe-4 py-3 text-end">N° Transaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($loans) > 0): ?>
                                    <?php foreach ($loans as $l): ?>
                                    <tr>
                                        <td class="ps-4 font-weight-bold" style="color:var(--text-heading)"><?= htmlspecialchars($l['titre']) ?></td>
                                        <td>
                                            <span class="badge <?= $l['type_achat']==='E-book' ? 'bg-info-subtle text-info border-info-subtle' : 'bg-primary-subtle text-primary border-primary-subtle' ?> border rounded-pill px-3 py-1">
                                                <i class="<?= $l['type_achat']==='E-book' ? 'fas fa-desktop' : 'fas fa-book' ?> me-1"></i><?= $l['type_achat'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($l['date_vente'])) ?></td>
                                        <td class="pe-4 text-end small text-muted">#<?= htmlspecialchars($l['code_transaction']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <div class="mb-2"><i class="fas fa-shopping-basket fs-3 text-light"></i></div>
                                            Aucun achat enregistré pour le moment.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Colonne latérale (Suggestions) -->
            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border-0 h-100" style="border-radius: 16px; background: linear-gradient(180deg, #ffffff, #f8f9fc);">
                    <h5 class="font-weight-bold mb-4" style="color:var(--text-heading)"><i class="fas fa-lightbulb text-warning me-2"></i>Suggestions pour vous</h5>
                    
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($suggestions as $sug): ?>
                        <div class="d-flex align-items-center p-3 rounded-3 bg-white shadow-sm border border-light transition-hover">
                            <div class="stat-card-icon me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(99,102,241,.1);color:var(--primary);border-radius:12px;">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="font-weight-bold text-truncate" style="color:var(--text-heading)"><?= htmlspecialchars($sug['titre']) ?></div>
                                <div class="text-muted small text-truncate"><?= htmlspecialchars($sug['auteur']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-auto pt-4 text-center">
                        <hr style="border-color:var(--border-color); opacity: 0.5;">
                        <a href="../index.php#catalogue" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold w-100">Explorer le catalogue complet</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .transition-hover { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important; }
    </style>
<?php require_once __DIR__ . '/../includes/foot.php'; ?>
