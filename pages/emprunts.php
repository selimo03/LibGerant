<?php
// pages/emprunts.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

// ── Export CSV ────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="libgerant_emprunts_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Réf.','Livre','ISBN','Client','Code Client','Emprunté le','Retour prévu','Retour réel','Statut','Amende (FCFA)'], ';');
    $rows = $pdo->query("SELECT e.id_emprunt,l.titre,e.isbn,c.nom,c.code_client,e.date_emprunt,e.date_retour_prev,e.date_retour_reel,e.statut,e.amende_fcfa FROM emprunts e JOIN livres l ON e.isbn=l.isbn JOIN clients c ON e.id_client=c.id_client ORDER BY e.date_creation DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, ['#EMP-'.str_pad($r['id_emprunt'],4,'0',STR_PAD_LEFT),$r['titre'],$r['isbn'],$r['nom'],$r['code_client'],date('d/m/Y',strtotime($r['date_emprunt'])),date('d/m/Y',strtotime($r['date_retour_prev'])),$r['date_retour_reel']?date('d/m/Y',strtotime($r['date_retour_reel'])):'—',$r['statut'],$r['amende_fcfa']], ';');
    }
    fclose($out); exit();
}

$success = null;
$error   = null;

// ── Créer un emprunt ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF). Veuillez réessayer.";
    } else {
        $id_client = intval($_POST['id_client'] ?? 0);
        $isbn      = trim($_POST['isbn'] ?? '');
        $duree     = max(1, intval($_POST['duree_jours'] ?? 14));
        $note      = trim($_POST['note'] ?? '');

        if ($id_client > 0 && $isbn !== '') {
            try {
                // Vérifier stock disponible
                $chk = $pdo->prepare("SELECT quantite_stock, titre FROM livres WHERE isbn=:isbn");
                $chk->execute([':isbn' => $isbn]);
                $livre = $chk->fetch();
                if (!$livre || $livre['quantite_stock'] < 1) {
                    $error = "Ce livre n'est plus disponible en stock pour un emprunt.";
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO emprunts (id_client,isbn,date_emprunt,date_retour_prev,statut,note,cree_par)
                                   VALUES (:cid,:isbn,CURDATE(),DATE_ADD(CURDATE(),INTERVAL :duree DAY),'En cours',:note,:uid)")
                        ->execute([':cid'=>$id_client,':isbn'=>$isbn,':duree'=>$duree,':note'=>$note,':uid'=>$_SESSION['user_id']]);
                    $pdo->prepare("UPDATE livres SET quantite_stock=quantite_stock-1 WHERE isbn=:isbn")
                        ->execute([':isbn' => $isbn]);
                    $pdo->commit();
                    regenerate_csrf_token();
                    $success = "Emprunt enregistré pour « {$livre['titre']} ». Date de retour prévue dans $duree jours.";
                }
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Sélectionnez un client et un livre.";
        }
    }
}

// ── Marquer comme Rendu ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF).";
    } else {
        $id_emprunt = intval($_POST['id_emprunt'] ?? 0);
        if ($id_emprunt > 0) {
            try {
                $emp = $pdo->prepare("SELECT * FROM emprunts WHERE id_emprunt=:id");
                $emp->execute([':id' => $id_emprunt]);
                $emprunt = $emp->fetch();
                if ($emprunt && $emprunt['statut'] !== 'Rendu') {
                    // Calcul de l'amende si en retard
                    $jours_retard = max(0, (int)((strtotime('today') - strtotime($emprunt['date_retour_prev'])) / 86400));
                    $amende = $jours_retard * 500; // 500 FCFA par jour
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE emprunts SET date_retour_reel=CURDATE(), statut='Rendu', amende_fcfa=:amende WHERE id_emprunt=:id")
                        ->execute([':amende'=>$amende,':id'=>$id_emprunt]);
                    $pdo->prepare("UPDATE livres SET quantite_stock=quantite_stock+1 WHERE isbn=:isbn")
                        ->execute([':isbn' => $emprunt['isbn']]);
                    $pdo->commit();
                    regenerate_csrf_token();
                    $success = "Livre rendu avec succès." . ($amende > 0 ? " Amende : " . number_format($amende,0,',',' ') . " FCFA." : "");
                }
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

// ── Marquer comme Perdu ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lost') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF).";
    } else {
        $id_emprunt = intval($_POST['id_emprunt'] ?? 0);
        if ($id_emprunt > 0) {
            try {
                $pdo->prepare("UPDATE emprunts SET statut='Perdu', date_retour_reel=CURDATE(), amende_fcfa=5000 WHERE id_emprunt=:id")
                    ->execute([':id' => $id_emprunt]);
                regenerate_csrf_token();
                $success = "Emprunt marqué comme Perdu. Amende forfaitaire : 5 000 FCFA.";
            } catch (\PDOException $e) { $error = "Erreur : " . $e->getMessage(); }
        }
    }
}

// ── Mise à jour automatique des statuts et amendes ───────────────────────
try {
    // 1. Passer les emprunts dépassés à "En retard"
    $pdo->query("UPDATE emprunts SET statut='En retard' WHERE statut='En cours' AND date_retour_prev < CURDATE()");
    // 2. Recalculer les amendes en temps réel (500 FCFA par jour de retard)
    $pdo->query("UPDATE emprunts SET amende_fcfa = GREATEST(0, DATEDIFF(CURDATE(), date_retour_prev)) * 500 WHERE statut='En retard'");
} catch (\PDOException $e) { /* silencieux */ }

// ── Récupération des données ──────────────────────────────────────────────
try {
    $stmt_emp = $pdo->query("
        SELECT e.*, l.titre AS livre_titre, l.auteur AS livre_auteur, c.nom AS client_nom, c.code_client
        FROM emprunts e
        JOIN livres l   ON e.isbn=l.isbn
        JOIN clients c  ON e.id_client=c.id_client
        ORDER BY e.date_creation DESC
    ");
    $emprunts = $stmt_emp->fetchAll();

    $cnt_actifs  = count(array_filter($emprunts, fn($e) => $e['statut'] === 'En cours'));
    $cnt_retards = count(array_filter($emprunts, fn($e) => $e['statut'] === 'En retard'));
    $cnt_perdus  = count(array_filter($emprunts, fn($e) => $e['statut'] === 'Perdu'));
    $total_amendes = array_sum(array_column(array_filter($emprunts, fn($e) => $e['statut'] !== 'Rendu'), 'amende_fcfa'));

    $clients_list = $pdo->query("SELECT id_client, nom FROM clients ORDER BY nom ASC")->fetchAll();
    $livres_list  = $pdo->query("SELECT isbn, titre, quantite_stock FROM livres WHERE quantite_stock > 0 ORDER BY titre ASC")->fetchAll();

} catch (\PDOException $e) {
    $emprunts = []; $cnt_actifs = $cnt_retards = $cnt_perdus = 0; $total_amendes = 0;
    $clients_list = []; $livres_list = [];
    $error = "Erreur de base de données : " . $e->getMessage();
}

$page_title   = 'Gestion des Emprunts';
$current_page = 'emprunts';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Gestion des Emprunts</h1>
                <p class="text-muted mb-0">Suivi des prêts de livres physiques et calcul automatique des pénalités de retard.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="emprunts.php?action=export_csv" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fas fa-file-csv me-2"></i> Exporter CSV
                </a>
                <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addEmpruntModal" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;">
                    <i class="fas fa-book-reader me-2"></i> Nouvel Emprunt
                </button>
            </div>
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

        <!-- KPIs -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #eef2ff); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-primary text-uppercase tracking-wide">Emprunts en cours</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= $cnt_actifs ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-book-reader fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, <?= ($cnt_retards > 0) ? '#fef2f2' : '#f8f9fc' ?>); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold <?= ($cnt_retards > 0) ? 'text-danger' : 'text-muted' ?> text-uppercase tracking-wide">En Retard</p>
                            <h3 style="font-weight:800;color:<?= ($cnt_retards > 0) ? '#dc2626' : 'var(--text-heading)' ?>"><?= $cnt_retards ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(239,68,68,.1);color:#ef4444;width:48px;height:48px;border-radius:12px;"><i class="fas fa-clock fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, <?= ($cnt_perdus > 0) ? '#fffbeb' : '#f8f9fc' ?>); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold <?= ($cnt_perdus > 0) ? 'text-warning' : 'text-muted' ?> text-uppercase tracking-wide">Livres Perdus</p>
                            <h3 style="font-weight:800;color:<?= ($cnt_perdus > 0) ? '#d97706' : 'var(--text-heading)' ?>"><?= $cnt_perdus ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:#f59e0b;width:48px;height:48px;border-radius:12px;"><i class="fas fa-exclamation-triangle fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f0fdf4); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-success text-uppercase tracking-wide">Amendes Cumulées</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($total_amendes,0,',',' ') ?> <span class="fs-6 text-muted">FCFA</span></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:#10b981;width:48px;height:48px;border-radius:12px;"><i class="fas fa-coins fs-5"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0 shadow-sm border-0 overflow-hidden mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white p-4 border-bottom-0">
                <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Suivi des Emprunts</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableEmprunts">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">Réf.</th>
                            <th class="border-0 py-3">Livre</th>
                            <th class="border-0 py-3">Client</th>
                            <th class="border-0 py-3">Emprunté le</th>
                            <th class="border-0 py-3">Retour Prévu</th>
                            <th class="border-0 py-3">Statut</th>
                            <th class="border-0 py-3">Pénalité</th>
                            <th class="border-0 pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php foreach ($emprunts as $e): ?>
                        <?php
                            switch ($e['statut']) {
                                case 'En cours':   $badge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-hourglass-half me-1"></i>En cours</span>'; break;
                                case 'En retard':  $badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-exclamation-circle me-1"></i>En retard</span>'; break;
                                case 'Rendu':      $badge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-check-circle me-1"></i>Rendu</span>'; break;
                                case 'Perdu':      $badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-times-circle me-1"></i>Perdu</span>'; break;
                                default:           $badge = '<span class="badge bg-light text-dark border rounded-pill px-3 py-2">' . htmlspecialchars($e['statut']) . '</span>';
                            }
                            $amende_txt = $e['amende_fcfa'] > 0
                                ? '<span class="text-danger font-weight-bold bg-danger-subtle px-2 py-1 rounded">' . number_format($e['amende_fcfa'],0,',',' ') . ' FCFA</span>'
                                : '<span class="text-muted">—</span>';
                        ?>
                        <tr>
                            <td class="ps-4 small text-muted font-monospace fw-bold">#EMP-<?= str_pad($e['id_emprunt'],4,'0',STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded shadow-sm border" style="width:40px;height:50px;flex-shrink:0">
                                        <i class="fas fa-book text-primary opacity-75"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($e['livre_titre']) ?>">
                                            <?= htmlspecialchars($e['livre_titre']) ?>
                                        </div>
                                        <div class="small text-muted font-monospace"><i class="fas fa-barcode me-1 opacity-50"></i><?= htmlspecialchars($e['isbn']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($e['client_nom']) ?></div>
                                <div class="small text-primary font-monospace">#<?= htmlspecialchars($e['code_client']) ?></div>
                            </td>
                            <td class="text-secondary"><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
                            <td>
                                <div class="fw-bold <?= ($e['statut'] === 'En retard') ? 'text-danger' : 'text-dark' ?>"><?= date('d/m/Y', strtotime($e['date_retour_prev'])) ?></div>
                                <?php if ($e['date_retour_reel']): ?>
                                <div class="small text-success mt-1"><i class="fas fa-check me-1"></i><?= date('d/m/Y', strtotime($e['date_retour_reel'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= $badge ?></td>
                            <td><?= $amende_txt ?></td>
                            <td class="text-end pe-4">
                                <?php if (in_array($e['statut'], ['En cours', 'En retard'])): ?>
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- Bouton Rendu -->
                                        <form method="POST" action="emprunts.php" class="d-inline"
                                              onsubmit="return confirm('Confirmer le retour de « <?= addslashes($e['livre_titre']) ?> » ?')">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="id_emprunt" value="<?= $e['id_emprunt'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm transition-hover border-0" style="background: linear-gradient(135deg, #10b981, #059669);" title="Marquer comme Rendu">
                                                <i class="fas fa-check"></i> Rendu
                                            </button>
                                        </form>
                                        <!-- Bouton Perdu -->
                                        <form method="POST" action="emprunts.php" class="d-inline"
                                              onsubmit="return confirm('Marquer « <?= addslashes($e['livre_titre']) ?> » comme PERDU ? Amende forfaitaire : 5 000 FCFA.')">
                                            <input type="hidden" name="action" value="lost">
                                            <input type="hidden" name="id_emprunt" value="<?= $e['id_emprunt'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle shadow-sm transition-hover bg-white" style="width: 32px; height: 32px; padding: 0;" title="Marquer comme Perdu">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal Nouvel Emprunt -->
<div class="modal fade" id="addEmpruntModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title font-weight-bold" style="color:var(--text-heading);"><i class="fas fa-box-open me-2 text-primary"></i>Enregistrer un Nouvel Emprunt</h5>
        <button type="button" class="btn-close shadow-sm bg-white rounded-circle" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="emprunts.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <div class="modal-body p-4 p-md-5">
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Adhérent *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-user text-muted"></i></span>
                        <select name="id_client" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none" required>
                            <option value="" disabled selected>Sélectionner un adhérent inscrit...</option>
                            <?php foreach ($clients_list as $cl): ?>
                                <option value="<?= $cl['id_client'] ?>"><?= htmlspecialchars($cl['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Ouvrage à emprunter *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-book text-muted"></i></span>
                        <select name="isbn" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none" required>
                            <option value="" disabled selected>Rechercher un livre physique disponible...</option>
                            <?php foreach ($livres_list as $lv): ?>
                                <option value="<?= $lv['isbn'] ?>">
                                    <?= htmlspecialchars($lv['titre']) ?> (En Stock: <?= $lv['quantite_stock'] ?> exemplaires)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-primary text-uppercase tracking-wide">Durée de l'emprunt (Jours)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary-subtle border-0 px-4"><i class="fas fa-calendar-alt text-primary"></i></span>
                        <input type="number" name="duree_jours" class="form-control form-control-lg bg-primary-subtle border-0 px-3 fs-6 fw-bold text-primary" value="14" min="1" max="90">
                    </div>
                    <div class="form-text text-muted mt-2 px-1"><i class="fas fa-info-circle me-1"></i>Par défaut 14 jours. Pénalité de 500 FCFA appliquée par jour de retard.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Observations / État initial</label>
                    <textarea name="note" class="form-control rounded-3 bg-light border-0 px-3 py-2 fs-6" rows="2" placeholder="Ex: Déchirure sur la page de couverture, annotations visibles..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 bg-light">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;">Valider l'Emprunt</button>
        </div>
      </form>
    </div>
  </div>
</div>
<style>
    .transition-hover { transition: transform 0.2s, box-shadow 0.2s; }
    .transition-hover:hover { transform: translateY(-2px); box-shadow: 0 .25rem .5rem rgba(0,0,0,.1)!important; }
    .tracking-wide { letter-spacing: 0.05em; }
</style>
<?php
$datatable_ids = ['tableEmprunts'];
require_once __DIR__ . '/../includes/foot.php';
?>
