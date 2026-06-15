<?php
// pages/reservations.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

// ── Export CSV ────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="libgerant_reservations_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Réf.','Livre','ISBN','Client','Date demande','Statut'], ';');
    $rows = $pdo->query("SELECT r.id_reservation,l.titre,r.isbn,c.nom,r.date_demande,r.statut FROM reservations r JOIN livres l ON r.isbn=l.isbn JOIN clients c ON r.id_client=c.id_client ORDER BY r.date_demande DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, ['#RES-'.str_pad($r['id_reservation'],4,'0',STR_PAD_LEFT),$r['titre'],$r['isbn'],$r['nom'],date('d/m/Y',strtotime($r['date_demande'])),$r['statut']], ';');
    }
    fclose($out); exit();
}

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (token CSRF). Veuillez réessayer.";
    } else {
        $id_res = intval($_POST['id_reservation'] ?? 0);
        $action = $_POST['action'];
        try {
            if ($action === 'notify') {
                $stmt = $pdo->prepare("UPDATE reservations SET statut='Produit Reçu' WHERE id_reservation=:id");
                $stmt->execute([':id' => $id_res]);
                $success = "Le client a été notifié de la disponibilité du produit.";
            } elseif ($action === 'cancel') {
                $stmt = $pdo->prepare("UPDATE reservations SET statut='Annulé' WHERE id_reservation=:id");
                $stmt->execute([':id' => $id_res]);
                $success = "La réservation a été annulée.";
            } elseif ($action === 'convert_emprunt') {
                // Convertir la réservation en emprunt
                $duree = max(1, intval($_POST['duree_jours'] ?? 14));
                $res   = $pdo->prepare("SELECT r.*,l.quantite_stock,l.titre FROM reservations r JOIN livres l ON r.isbn=l.isbn WHERE r.id_reservation=:id");
                $res->execute([':id' => $id_res]);
                $rdata = $res->fetch();
                if (!$rdata) {
                    $error = "Réservation introuvable.";
                } elseif ($rdata['quantite_stock'] < 1) {
                    $error = "Stock insuffisant pour créer l'emprunt.";
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO emprunts (id_client,isbn,date_emprunt,date_retour_prev,statut,note,cree_par) VALUES (:cid,:isbn,CURDATE(),DATE_ADD(CURDATE(),INTERVAL :d DAY),'En cours','Converti depuis réservation #RES-{$id_res}',:uid)")
                        ->execute([':cid'=>$rdata['id_client'],':isbn'=>$rdata['isbn'],':d'=>$duree,':uid'=>$_SESSION['user_id']]);
                    $pdo->prepare("UPDATE livres SET quantite_stock=quantite_stock-1 WHERE isbn=:isbn")->execute([':isbn'=>$rdata['isbn']]);
                    $pdo->prepare("UPDATE reservations SET statut='Conclu' WHERE id_reservation=:id")->execute([':id'=>$id_res]);
                    $pdo->commit();
                    $success = "Réservation convertie en emprunt pour « {$rdata['titre']} » ({$duree} jours).";
                }
            }
            regenerate_csrf_token();
        } catch (\PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

try {
    $stmt_res = $pdo->query("
        SELECT r.id_reservation, r.date_demande, r.statut, r.isbn, r.id_client,
               l.titre AS livre_titre, c.nom AS client_nom
        FROM reservations r
        JOIN livres l ON r.isbn=l.isbn
        JOIN clients c ON r.id_client=c.id_client
        ORDER BY r.date_demande DESC
    ");
    $reservations = $stmt_res->fetchAll();

    $cnt_attente = count(array_filter($reservations, fn($r) => $r['statut'] === 'En attente stock'));
    $cnt_recu    = count(array_filter($reservations, fn($r) => $r['statut'] === 'Produit Reçu'));
    $cnt_conclu  = count(array_filter($reservations, fn($r) => $r['statut'] === 'Conclu'));
    $cnt_annule  = count(array_filter($reservations, fn($r) => $r['statut'] === 'Annulé'));

} catch (\PDOException $e) {
    $reservations = [];
    $cnt_attente = $cnt_recu = $cnt_conclu = $cnt_annule = 0;
    $error = "Erreur : " . $e->getMessage();
}

$page_title   = 'Pré-commandes & Réservations';
$current_page = 'reservations';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-5 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Pré-commandes & Réservations</h1>
                <p class="text-muted mb-0">Gérez les demandes de clients pour les articles hors-stock ou à paraître.</p>
            </div>
            <a href="reservations.php?action=export_csv" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                <i class="fas fa-file-csv me-2"></i> Exporter CSV
            </a>
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
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, <?= ($cnt_attente > 0) ? '#fffbeb' : '#f8f9fc' ?>); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold <?= ($cnt_attente > 0) ? 'text-warning' : 'text-muted' ?> text-uppercase tracking-wide">En Attente Stock</p>
                            <h3 style="font-weight:800;color:<?= ($cnt_attente > 0) ? '#d97706' : 'var(--text-heading)' ?>"><?= $cnt_attente ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:#f59e0b;width:48px;height:48px;border-radius:12px;"><i class="fas fa-hourglass-half fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f0fdf4); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-success text-uppercase tracking-wide">Produits Reçus</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= $cnt_recu ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:#10b981;width:48px;height:48px;border-radius:12px;"><i class="fas fa-box-open fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #eef2ff); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-primary text-uppercase tracking-wide">Conclus (Vendus)</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= $cnt_conclu ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-handshake fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #fef2f2); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-danger text-uppercase tracking-wide">Annulées</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= $cnt_annule ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(239,68,68,.1);color:#ef4444;width:48px;height:48px;border-radius:12px;"><i class="fas fa-times-circle fs-5"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-0 shadow-sm border-0 overflow-hidden mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white p-4 border-bottom-0">
                <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Liste des Réservations</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableReservations">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">Réf.</th>
                            <th class="border-0 py-3">Livre & ISBN</th>
                            <th class="border-0 py-3">Client</th>
                            <th class="border-0 py-3">Date Demande</th>
                            <th class="border-0 py-3">Statut</th>
                            <th class="border-0 pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php foreach ($reservations as $res): ?>
                        <?php
                            if ($res['statut'] === 'Produit Reçu') {
                                $sbadge = '<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 shadow-sm"><i class="fas fa-box-open me-1"></i>Produit Reçu</span>';
                            } elseif ($res['statut'] === 'En attente stock') {
                                $sbadge = '<span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 shadow-sm"><i class="fas fa-hourglass-half me-1"></i>En attente stock</span>';
                            } elseif ($res['statut'] === 'Conclu') {
                                $sbadge = '<span class="badge rounded-pill bg-light text-secondary border px-3 py-2 shadow-sm"><i class="fas fa-check-double me-1"></i>Conclu</span>';
                            } else {
                                $sbadge = '<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 shadow-sm"><i class="fas fa-ban me-1"></i>Annulé</span>';
                            }
                        ?>
                        <tr>
                            <td class="ps-4 small text-muted font-monospace fw-bold">#RES-<?= str_pad($res['id_reservation'],4,'0',STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded shadow-sm border" style="width:40px;height:50px;flex-shrink:0">
                                        <i class="fas fa-book text-primary opacity-75"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($res['livre_titre']) ?>">
                                            <?= htmlspecialchars($res['livre_titre']) ?>
                                        </div>
                                        <div class="small text-muted font-monospace"><i class="fas fa-barcode me-1 opacity-50"></i><?= htmlspecialchars($res['isbn']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($res['client_nom']) ?></div>
                            </td>
                            <td class="text-secondary"><?= date('d M Y', strtotime($res['date_demande'])) ?></td>
                            <td><?= $sbadge ?></td>
                            <td class="pe-4 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ($res['statut'] === 'En attente stock'): ?>
                                        <form method="POST" action="reservations.php" class="d-inline">
                                            <input type="hidden" name="action" value="notify">
                                            <input type="hidden" name="id_reservation" value="<?= $res['id_reservation'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm transition-hover" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;" title="Marquer le produit comme reçu">
                                                <i class="fas fa-bell me-1"></i> Notifier
                                            </button>
                                        </form>
                                        <form method="POST" action="reservations.php" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id_reservation" value="<?= $res['id_reservation'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle shadow-sm transition-hover bg-white" style="width: 32px; height: 32px; padding: 0;"
                                                    onclick="return confirm('Annuler définitivement cette réservation ?')" title="Annuler la réservation">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($res['statut'] === 'Produit Reçu'): ?>
                                        <a href="prets.php" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm transition-hover" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
                                            <i class="fas fa-shopping-cart me-1"></i> Vendre
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm transition-hover"
                                            onclick="openConvert(<?= $res['id_reservation'] ?>, '<?= htmlspecialchars(addslashes($res['livre_titre'])) ?>')">
                                            <i class="fas fa-book-reader me-1"></i> Emprunt
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<!-- Modal Conversion en Emprunt -->
<div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title fw-bold"><i class="fas fa-book-reader me-2 text-primary"></i>Convertir en Emprunt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="reservations.php">
        <input type="hidden" name="action" value="convert_emprunt">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="id_reservation" id="conv_id">
        <div class="modal-body p-4">
            <p class="text-muted mb-4">Livre : <strong id="conv_titre"></strong></p>
            <label class="form-label small fw-bold text-muted text-uppercase">Durée de l'emprunt (jours) *</label>
            <input type="number" name="duree_jours" class="form-control" value="14" min="1" max="90" required>
            <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Pénalité de 500 FCFA/jour en cas de retard.</div>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Créer l'emprunt</button>
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
$datatable_ids = ['tableReservations'];
$extra_js = <<<JS
<script>
function openConvert(id, titre) {
    document.getElementById('conv_id').value = id;
    document.getElementById('conv_titre').textContent = titre;
    new bootstrap.Modal(document.getElementById('convertModal')).show();
}
</script>
JS;
require_once __DIR__ . '/../includes/foot.php';
?>
