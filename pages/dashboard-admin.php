<?php
// pages/dashboard-admin.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin']);

try {
    $stmt_ca = $pdo->query("SELECT SUM(total_montant) AS ca FROM ventes WHERE statut='Payé'");
    $ca_mensuel = $stmt_ca->fetch()['ca'] ?? 0;

    $stmt_ventes = $pdo->query("SELECT COUNT(*) AS total FROM ventes WHERE statut='Payé'");
    $total_ventes = $stmt_ventes->fetch()['total'] ?? 0;

    $stmt_clients = $pdo->query("SELECT COUNT(*) AS total FROM clients");
    $total_clients = $stmt_clients->fetch()['total'] ?? 0;

    $stmt_ebooks = $pdo->query("SELECT SUM(quantite) AS total FROM lignes_ventes WHERE type_achat='E-book'");
    $total_ebooks = $stmt_ebooks->fetch()['total'] ?? 0;

    $stmt_alertes = $pdo->query("SELECT isbn, titre, quantite_stock, seuil_alerte FROM livres WHERE quantite_stock <= seuil_alerte");
    $alertes_stock = $stmt_alertes->fetchAll();
    $alertes_count = count($alertes_stock);

    $stmt_recentes = $pdo->query("
        SELECT v.code_transaction, v.date_vente, v.total_montant, v.statut,
               COALESCE(c.nom,'Client de Passage') AS client_nom,
               GROUP_CONCAT(l.titre SEPARATOR ', ') AS livres_titres,
               GROUP_CONCAT(lv.type_achat SEPARATOR ', ') AS types_achat
        FROM ventes v
        LEFT JOIN clients c ON v.id_client=c.id_client
        LEFT JOIN lignes_ventes lv ON v.id_vente=lv.id_vente
        LEFT JOIN livres l ON lv.isbn=l.isbn
        GROUP BY v.id_vente
        ORDER BY v.date_vente DESC LIMIT 5
    ");
    $ventes_recentes = $stmt_recentes->fetchAll();

    // Récupérer les ventes des 7 derniers jours pour le graphique
    $stmt_chart = $pdo->query("
        SELECT DATE(date_vente) AS date_jour, SUM(total_montant) AS total
        FROM ventes
        WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND statut='Payé'
        GROUP BY date_jour
        ORDER BY date_jour ASC
    ");
    $chart_data = $stmt_chart->fetchAll();
    
    // Remplir les jours manquants avec 0
    $last_7_days = [];
    $sales_7_days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $last_7_days[] = date('d/m', strtotime($d));
        $sales_7_days[$d] = 0;
    }
    foreach ($chart_data as $row) {
        $sales_7_days[$row['date_jour']] = floatval($row['total']);
    }
    $chart_values = array_values($sales_7_days);

} catch (\PDOException $e) {
    die("Erreur de récupération : " . $e->getMessage());
}

$page_title   = 'Administration';
$current_page = 'dashboard';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-5 gap-3">
            <div>
                <h1 class="h2 mb-0" style="font-weight:800;color:var(--text-heading)">Espace Administration</h1>
                <p class="text-muted small mb-0 mt-1">Bienvenue, <strong class="text-primary"><?= htmlspecialchars($_SESSION['user_name']) ?></strong>. Voici un aperçu de vos activités.</p>
            </div>
            <a href="livres.php" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                <i class="fas fa-plus me-2"></i>Nouveau Produit
            </a>
        </div>

        <!-- KPIs -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc);">
                    <div class="stat-card-icon mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:50px;height:50px;border-radius:12px;"><i class="fas fa-coins fs-5"></i></div>
                    <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($ca_mensuel,0,',',' ') ?> FCFA</h3>
                    <p class="small mb-0 fw-bold" style="color:var(--primary)">Chiffre d'Affaires Global</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc);">
                    <div class="stat-card-icon mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:var(--accent);width:50px;height:50px;border-radius:12px;"><i class="fas fa-shopping-bag fs-5"></i></div>
                    <h3 style="font-weight:800;color:var(--text-heading)"><?= $total_ventes ?></h3>
                    <p class="small mb-0 fw-bold" style="color:var(--accent)">Ventes Totales</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc);">
                    <div class="stat-card-icon mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(236,72,153,.1);color:var(--secondary);width:50px;height:50px;border-radius:12px;"><i class="fas fa-users fs-5"></i></div>
                    <h3 style="font-weight:800;color:var(--text-heading)"><?= $total_clients ?></h3>
                    <p class="small mb-0 fw-bold" style="color:var(--secondary)">Clients Enregistrés</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc);">
                    <div class="stat-card-icon mb-3 shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:var(--warning);width:50px;height:50px;border-radius:12px;"><i class="fas fa-download fs-5"></i></div>
                    <h3 style="font-weight:800;color:var(--text-heading)"><?= $total_ebooks ?></h3>
                    <p class="small mb-0 fw-bold" style="color:var(--warning)">Téléchargements E-book</p>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Graphique d'évolution des ventes -->
            <div class="col-lg-8">
                <div class="card p-4 h-100 shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Évolution des Ventes</h5>
                        <span class="badge bg-light text-dark rounded-pill px-3 py-2 border">7 derniers jours</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="dashboardChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Actions Rapides -->
            <div class="col-lg-4">
                <div class="card p-4 h-100 shadow-lg border-0 text-white" style="background: linear-gradient(135deg, #6366f1, #8b5cf6) !important; border-radius: 16px;">
                    <h5 class="mb-4 text-white" style="font-weight:700;">Actions Rapides</h5>
                    <div class="d-grid gap-3">
                        <a href="prets.php" class="btn btn-light btn-lg text-primary shadow-sm rounded-pill text-start px-4" style="transition: transform 0.2s;">
                            <i class="fas fa-shopping-cart me-3"></i> Nouvelle Vente
                        </a>
                        <a href="emprunts.php" class="btn btn-outline-light btn-lg rounded-pill text-start px-4" style="transition: transform 0.2s;">
                            <i class="fas fa-book-reader me-3"></i> Nouvel Emprunt
                        </a>
                        <a href="reservations.php" class="btn btn-outline-light btn-lg rounded-pill text-start px-4" style="transition: transform 0.2s;">
                            <i class="fas fa-clock me-3"></i> Pré-commandes
                        </a>
                        <a href="../index.php" class="btn btn-outline-light btn-lg rounded-pill text-start px-4" style="transition: transform 0.2s;">
                            <i class="fas fa-store me-3"></i> Voir Boutique
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières Ventes -->
        <div class="card p-0 shadow-sm border-0 overflow-hidden mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white p-4 border-bottom-0">
                <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Dernières Transactions</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableDernVentes">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">Réf. Transaction</th>
                            <th class="border-0 py-3">Livre(s)</th>
                            <th class="border-0 py-3">Client</th>
                            <th class="border-0 py-3">Date</th>
                            <th class="border-0 py-3">Montant</th>
                            <th class="border-0 pe-4 py-3 text-end">Statut</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php if (count($ventes_recentes) > 0): ?>
                            <?php foreach ($ventes_recentes as $v): ?>
                            <tr>
                                <td class="ps-4 small text-muted">#<?= htmlspecialchars($v['code_transaction']) ?></td>
                                <td style="font-weight:600"><?= htmlspecialchars($v['livres_titres'] ?: '—') ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 35px; height: 35px; font-size: 0.9rem; font-weight:bold;">
                                            <?= strtoupper(substr($v['client_nom'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($v['client_nom']) ?>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y', strtotime($v['date_vente'])) ?></td>
                                <td style="font-weight:700;color:var(--primary)"><?= number_format($v['total_montant'],0,',',' ') ?> FCFA</td>
                                <td class="pe-4 text-end"><span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 border border-success-subtle shadow-sm"><?= htmlspecialchars($v['statut']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Aucune vente enregistrée récemment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white p-3 text-center border-top-0">
                <a href="prets.php" class="btn btn-link text-decoration-none text-primary fw-bold">Voir toutes les transactions <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>

    </div>
<?php 
$json_labels = json_encode($last_7_days);
$json_values = json_encode($chart_values);

$extra_js = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = 'Outfit, sans-serif';
    const ctx = document.getElementById('dashboardChart').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {$json_labels},
            datasets: [{
                label: 'Ventes (FCFA)',
                data: {$json_values},
                fill: true,
                backgroundColor: gradient,
                borderColor: '#6366f1',
                borderWidth: 3,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#6366f1',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 13 },
                    bodyFont: { size: 14, weight: 'bold' },
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y.toLocaleString('fr-FR') + ' FCFA';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks: { color: '#64748b', maxTicksLimit: 5 }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
</script>
HTML;
require_once __DIR__ . '/../includes/foot.php'; 
?>
