<?php
// pages/prets.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

$error   = null;
$success = null;

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $trx     = $_GET['trx'] ?? '';
        $success = "Vente enregistrée avec succès ! (N° Transaction : #$trx)";
    } elseif ($_GET['status'] === 'error') {
        $error = "Erreur : " . htmlspecialchars($_GET['message'] ?? 'Erreur inconnue');
    }
}

try {
    $stmt_sales = $pdo->query("
        SELECT v.id_vente, v.code_transaction, v.date_vente, v.total_montant, v.statut, v.mode_reglement,
               COALESCE(c.nom, 'Client de Passage') AS client_nom,
               GROUP_CONCAT(CONCAT(l.titre, ' (x', lv.quantite, ')') SEPARATOR ', ') AS livres_titres
        FROM ventes v
        LEFT JOIN clients c  ON v.id_client=c.id_client
        LEFT JOIN lignes_ventes lv ON v.id_vente=lv.id_vente
        LEFT JOIN livres l   ON lv.isbn=l.isbn
        GROUP BY v.id_vente
        ORDER BY v.date_vente DESC
    ");
    $ventes = $stmt_sales->fetchAll();

    $stmt_today = $pdo->query("
        SELECT COUNT(*) AS total_today, COALESCE(SUM(total_montant), 0) AS montant_today
        FROM ventes WHERE DATE(date_vente)=CURDATE() AND statut='Payé'
    ");
    $stats_today = $stmt_today->fetch();

    $stmt_global = $pdo->query("
        SELECT COUNT(*) AS total_global, COALESCE(SUM(total_montant), 0) AS montant_global 
        FROM ventes WHERE statut='Payé'
    ");
    $stats_global = $stmt_global->fetch();

    $stmt_books   = $pdo->query("SELECT isbn, titre, prix_vente, quantite_stock FROM livres WHERE quantite_stock > 0 ORDER BY titre ASC");
    $books_available = $stmt_books->fetchAll();

    $stmt_clients = $pdo->query("SELECT id_client, nom FROM clients ORDER BY nom ASC");
    $clients_list = $stmt_clients->fetchAll();

} catch (\PDOException $e) {
    $ventes = []; $stats_today = ['total_today'=>0,'montant_today'=>0]; $stats_global = ['total_global'=>0,'montant_global'=>0];
    $books_available = []; $clients_list = [];
    $error = "Erreur de base de données : " . $e->getMessage();
}

$page_title   = 'Journal des Ventes';
$current_page = 'prets';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Journal des Ventes</h1>
                <p class="text-muted mb-0">Suivi des transactions, encaissements et facturation.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newSaleModal" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
                <i class="fas fa-shopping-cart me-2"></i> Nouvelle Vente
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

        <!-- KPIs Ventes -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f0fdf4); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-success text-uppercase tracking-wide">Encaissement du Jour</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats_today['montant_today'],0,',',' ') ?> <span class="fs-6 text-muted">FCFA</span></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:var(--accent);width:48px;height:48px;border-radius:12px;"><i class="fas fa-coins fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-primary text-uppercase tracking-wide">Ventes du Jour</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= $stats_today['total_today'] ?> <span class="fs-6 text-muted">transaction(s)</span></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-shopping-bag fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">CA Global</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats_global['montant_global'],0,',',' ') ?> <span class="fs-6 text-muted">FCFA</span></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(236,72,153,.1);color:var(--secondary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-chart-line fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">Total Transactions</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats_global['total_global'],0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:var(--warning);width:48px;height:48px;border-radius:12px;"><i class="fas fa-list-alt fs-5"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table des ventes -->
        <div class="card p-0 shadow-sm border-0 overflow-hidden mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white p-4 border-bottom-0">
                <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Historique des encaissements</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableVentes">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">Réf. Transaction</th>
                            <th class="border-0 py-3">Livre(s)</th>
                            <th class="border-0 py-3">Client</th>
                            <th class="border-0 py-3">Règlement</th>
                            <th class="border-0 py-3">Date</th>
                            <th class="border-0 py-3">Montant</th>
                            <th class="border-0 py-3">Statut</th>
                            <th class="border-0 pe-4 py-3 text-end">Facture</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php foreach ($ventes as $v): ?>
                        <tr>
                            <td class="ps-4 small text-muted font-monospace">#<?= htmlspecialchars($v['code_transaction']) ?></td>
                            <td>
                                <div class="d-flex align-items-center py-2">
                                    <div class="me-3 d-flex align-items-center justify-content-center bg-light rounded-circle shadow-sm" style="width:40px;height:40px;flex-shrink:0">
                                        <i class="fas fa-shopping-basket text-primary opacity-75"></i>
                                    </div>
                                    <div class="font-weight-bold" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($v['livres_titres']) ?>">
                                        <?= htmlspecialchars($v['livres_titres'] ?: '—') ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if($v['client_nom'] === 'Client de Passage'): ?>
                                        <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center me-2 border" style="width:30px;height:30px;font-size:0.8rem;"><i class="fas fa-walking"></i></div>
                                        <span class="text-muted fst-italic">Passage</span>
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-2 border border-primary-subtle" style="width:30px;height:30px;font-size:0.8rem;font-weight:bold;">
                                            <?= strtoupper(substr($v['client_nom'], 0, 1)) ?>
                                        </div>
                                        <span class="font-weight-bold text-dark"><?= htmlspecialchars($v['client_nom']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-secondary border border-secondary-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-credit-card me-1 opacity-50"></i><?= ucfirst($v['mode_reglement']) ?></span></td>
                            <td class="text-secondary"><?= date('d/m/Y', strtotime($v['date_vente'])) ?></td>
                            <td class="font-weight-bold text-success fs-6"><?= number_format($v['total_montant'],0,',',' ') ?> FCFA</td>
                            <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-check-circle me-1"></i><?= $v['statut'] ?></span></td>
                            <td class="pe-4 text-end">
                                <a href="../api/receipt.php?id=<?= $v['id_vente'] ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm transition-hover">
                                    <i class="fas fa-file-pdf me-1"></i> Reçu
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal Nouvelle Vente -->
<div class="modal fade" id="newSaleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title font-weight-bold" style="color:var(--text-heading);"><i class="fas fa-cash-register me-2 text-success"></i>Encaisser une Nouvelle Vente</h5>
        <button type="button" class="btn-close shadow-sm bg-white rounded-circle" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="../api/process_sale.php">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <div class="modal-body p-4 p-md-5">
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Client (Optionnel)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-user text-muted"></i></span>
                        <select name="id_client" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none">
                            <option value="">Client de Passage (Anonyme)</option>
                            <?php foreach ($clients_list as $cli): ?>
                                <option value="<?= $cli['id_client'] ?>"><?= htmlspecialchars($cli['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Sélectionner le Livre *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-book text-muted"></i></span>
                        <select name="isbn" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none" required>
                            <option value="" disabled selected>Rechercher un livre dans le stock...</option>
                            <?php foreach ($books_available as $bk): ?>
                                <option value="<?= $bk['isbn'] ?>">
                                    <?= htmlspecialchars($bk['titre']) ?> — <?= number_format($bk['prix_vente'],0,',',' ') ?> FCFA (En stock: <?= $bk['quantite_stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Quantité *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-sort-numeric-up text-muted"></i></span>
                        <input type="number" name="quantite" class="form-control form-control-lg bg-light border-0 px-3 fs-6" value="1" min="1" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Format *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-layer-group text-muted"></i></span>
                        <select name="type" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none">
                            <option value="Papier">Livre Papier</option>
                            <option value="E-book">E-book (PDF/ePub)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Règlement *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-wallet text-muted"></i></span>
                        <select name="mode_reglement" class="form-select form-select-lg bg-light border-0 px-3 fs-6 shadow-none">
                            <option value="especes">Espèces</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="carte">Carte Bancaire</option>
                            <option value="cheque">Chèque</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 bg-light">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">Valider l'encaissement</button>
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
$datatable_ids = ['tableVentes'];
require_once __DIR__ . '/../includes/foot.php';
?>
