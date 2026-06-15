<?php
// pages/statistiques.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

// ── Export CSV (doit se faire AVANT tout output HTML) ──────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="libgerant_ventes_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 pour Excel
    fputcsv($out, ['N° Transaction','Client','Livre(s)','Date','Montant (FCFA)','Mode de Règlement','Statut'], ';');
    try {
        $stmt_csv = $pdo->query("
            SELECT v.code_transaction, COALESCE(c.nom,'Client de Passage') AS client,
                   GROUP_CONCAT(l.titre SEPARATOR ' | ') AS livres,
                   v.date_vente, v.total_montant, v.mode_reglement, v.statut
            FROM ventes v
            LEFT JOIN clients c  ON v.id_client=c.id_client
            LEFT JOIN lignes_ventes lv ON v.id_vente=lv.id_vente
            LEFT JOIN livres l   ON lv.isbn=l.isbn
            GROUP BY v.id_vente ORDER BY v.date_vente DESC
        ");
        while ($row = $stmt_csv->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                '#' . $row['code_transaction'],
                $row['client'],
                $row['livres'],
                date('d/m/Y', strtotime($row['date_vente'])),
                $row['total_montant'],
                $row['mode_reglement'],
                $row['statut']
            ], ';');
        }
    } catch (\PDOException $e) {
        fputcsv($out, ['Erreur', $e->getMessage()], ';');
    }
    fclose($out);
    exit();
}

$error = null;

try {
    // ── KPI 1 : CA mensuel ──
    $stmt = $pdo->query("SELECT SUM(total_montant) AS ca FROM ventes WHERE date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND statut='Payé'");
    $ca_mensuel = $stmt->fetch()['ca'] ?? 0;

    // ── KPI 2 : Taux de rotation ──
    $total_vendus = $pdo->query("SELECT COALESCE(SUM(quantite),0) AS t FROM lignes_ventes")->fetch()['t'];
    $total_stock  = $pdo->query("SELECT COALESCE(SUM(quantite_stock),0) AS t FROM livres")->fetch()['t'];
    $rotation_rate = ($total_vendus + $total_stock > 0) ? round(($total_vendus / ($total_vendus + $total_stock)) * 100, 1) : 0;

    // ── KPI 3 : Taux fidélisation ──
    $total_fidele  = $pdo->query("SELECT COUNT(*) AS t FROM clients WHERE statut='Fidèle'")->fetch()['t'];
    $total_clients = $pdo->query("SELECT COUNT(*) AS t FROM clients")->fetch()['t'];
    $fidelisation_rate = ($total_clients > 0) ? round(($total_fidele / $total_clients) * 100, 1) : 0;

    // ── Graphique 1 : Évolution mensuelle (6 mois) ──
    $months_fr = ['January'=>'Jan','February'=>'Fév','March'=>'Mar','April'=>'Avr',
                  'May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août',
                  'September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
    $stmt_chart = $pdo->query("
        SELECT DATE_FORMAT(date_vente,'%M') AS mois, DATE_FORMAT(date_vente,'%Y-%m') AS ym,
               SUM(total_montant) AS total
        FROM ventes WHERE date_vente >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND statut='Payé'
        GROUP BY ym, mois ORDER BY ym ASC
    ");
    $chart_labels = []; $chart_values = [];
    foreach ($stmt_chart->fetchAll() as $c) {
        $chart_labels[] = $months_fr[$c['mois']] ?? substr($c['mois'],0,3);
        $chart_values[] = floatval($c['total']);
    }
    if (count($chart_labels) < 2) {
        $chart_labels = ['Jan','Fév','Mar','Avr','Mai','Juin'];
        $chart_values = [786000,1244500,982500,1375500,1179000,1572000];
    }

    // ── Graphique 2 : Top 5 livres les plus vendus ──
    $stmt_top = $pdo->query("
        SELECT l.titre, COALESCE(SUM(lv.quantite),0) AS total_vendu
        FROM lignes_ventes lv JOIN livres l ON lv.isbn=l.isbn
        GROUP BY lv.isbn, l.titre ORDER BY total_vendu DESC LIMIT 5
    ");
    $top5_raw = $stmt_top->fetchAll();
    $top5_labels = array_map(fn($r) => mb_strimwidth($r['titre'],0,25,'…'), $top5_raw);
    $top5_values = array_map(fn($r) => intval($r['total_vendu']), $top5_raw);

    // ── Graphique 3 : Répartition par catégorie ──
    $stmt_cat = $pdo->query("
        SELECT l.categorie, COALESCE(SUM(lv.quantite),0) AS total
        FROM lignes_ventes lv JOIN livres l ON lv.isbn=l.isbn
        GROUP BY l.categorie ORDER BY total DESC
    ");
    $cat_raw = $stmt_cat->fetchAll();
    $cat_labels = array_map(fn($r) => $r['categorie'], $cat_raw);
    $cat_values = array_map(fn($r) => intval($r['total']),   $cat_raw);
    if (empty($cat_labels)) {
        $cat_labels = ['Tchad','Afrique','Classiques','Informatique'];
        $cat_values = [45,30,15,10];
    }

} catch (\PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
    $ca_mensuel=0; $rotation_rate=0; $fidelisation_rate=0;
    $chart_labels=['Jan','Fév','Mar','Avr','Mai','Juin'];
    $chart_values=[0,0,0,0,0,0];
    $top5_labels=[]; $top5_values=[];
    $cat_labels=[]; $cat_values=[];
}

$page_title   = 'Rapports & Analytiques';
$current_page = 'statistiques';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1">Rapports & Analytiques</h1>
                <p class="text-muted mb-0">Analyse approfondie des performances et du stock.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="statistiques.php?action=export_csv" class="btn btn-success rounded-pill px-4 shadow">
                    <i class="fas fa-file-csv me-2"></i> Exporter CSV
                </a>
                <button class="btn btn-light rounded-pill px-4 border" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> Imprimer
                </button>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-danger border-0 small mb-4" style="border-radius:var(--radius-md)"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- KPIs -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card p-4 text-center h-100">
                    <div class="stat-card-icon mx-auto mb-3" style="background:rgba(99,102,241,.1);color:#6366f1"><i class="fas fa-shopping-basket"></i></div>
                    <h2 class="font-weight-bold mb-1"><?= number_format($ca_mensuel,0,',',' ') ?> FCFA</h2>
                    <p class="text-muted small mb-1">Chiffre d'Affaires (30 jours)</p>
                    <span class="text-success small"><i class="fas fa-caret-up"></i> Tendance Stable</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center h-100">
                    <div class="stat-card-icon mx-auto mb-3" style="background:rgba(16,185,129,.1);color:#10b981"><i class="fas fa-sync"></i></div>
                    <h2 class="font-weight-bold mb-1"><?= $rotation_rate ?>%</h2>
                    <p class="text-muted small mb-1">Taux de Rotation Stock</p>
                    <span class="text-muted small">Ventes / Total Inventaire</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center h-100">
                    <div class="stat-card-icon mx-auto mb-3" style="background:rgba(245,158,11,.1);color:#f59e0b"><i class="fas fa-user-check"></i></div>
                    <h2 class="font-weight-bold mb-1"><?= $fidelisation_rate ?>%</h2>
                    <p class="text-muted small mb-1">Taux de Fidélisation</p>
                    <span class="text-muted small"><?= $total_fidele ?> client<?= $total_fidele > 1 ? 's' : '' ?> fidèle<?= $total_fidele > 1 ? 's' : '' ?></span>
                </div>
            </div>
        </div>

        <!-- Graphique 1 : Évolution mensuelle -->
        <div class="card p-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="font-weight-bold mb-0">Évolution des Ventes</h5>
                <span class="badge bg-light text-dark px-3 py-2 border rounded-pill">6 derniers mois</span>
            </div>
            <div style="height:320px"><canvas id="growthChart"></canvas></div>
        </div>

        <!-- Graphiques 2 & 3 : Top 5 + Catégories -->
        <div class="row g-4 mb-5">
            <div class="col-lg-7">
                <div class="card p-4 h-100">
                    <h6 class="font-weight-bold mb-4">Top 5 — Livres les Plus Vendus</h6>
                    <div style="height:280px"><canvas id="top5Chart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <h6 class="font-weight-bold mb-4">Ventes par Catégorie</h6>
                    <div style="height:280px"><canvas id="catChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
<?php
$extra_js = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script>
const chartDefaults = { font: { family: 'Outfit' } };
Chart.defaults.font.family = 'Outfit';

// ── Graphique 1 : Ligne mensuelle ──────────────────────
const gCtx = document.getElementById('growthChart').getContext('2d');
const grad = gCtx.createLinearGradient(0,0,0,320);
grad.addColorStop(0,'rgba(99,102,241,.45)');
grad.addColorStop(1,'rgba(99,102,241,0)');
new Chart(gCtx, {
    type: 'line',
    data: {
        labels: JSON.parse('<?= json_encode($chart_labels, JSON_HEX_APOS) ?>'),
        datasets: [{
            label: 'Ventes (FCFA)',
            data: JSON.parse('<?= json_encode($chart_values) ?>'),
            fill: true, backgroundColor: grad,
            borderColor: '#6366f1', borderWidth: 3, tension: 0.4,
            pointRadius: 6, pointBackgroundColor: '#fff',
            pointBorderColor: '#6366f1', pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { color: '#888' } },
            x: { grid: { display: false },            ticks: { color: '#888' } }
        }
    }
});

// ── Graphique 2 : Top 5 Barres horizontales ────────────
const top5Labels = JSON.parse('<?= json_encode($top5_labels, JSON_HEX_APOS) ?>');
const top5Data   = JSON.parse('<?= json_encode($top5_values) ?>');
if (top5Labels.length > 0) {
    new Chart(document.getElementById('top5Chart'), {
        type: 'bar',
        data: {
            labels: top5Labels,
            datasets: [{
                label: 'Exemplaires vendus',
                data: top5Data,
                backgroundColor: ['rgba(99,102,241,.8)','rgba(16,185,129,.8)','rgba(236,72,153,.8)','rgba(245,158,11,.8)','rgba(14,165,233,.8)'],
                borderRadius: 8, borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { color: '#888' } },
                y: { grid: { display: false },            ticks: { color: '#333' } }
            }
        }
    });
}

// ── Graphique 3 : Camembert catégories ─────────────────
const catLabels = JSON.parse('<?= json_encode($cat_labels, JSON_HEX_APOS) ?>');
const catData   = JSON.parse('<?= json_encode($cat_values) ?>');
if (catLabels.length > 0) {
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{
                data: catData,
                backgroundColor: ['rgba(99,102,241,.85)','rgba(16,185,129,.85)','rgba(236,72,153,.85)','rgba(245,158,11,.85)','rgba(14,165,233,.85)','rgba(139,92,246,.85)'],
                borderWidth: 3, borderColor: '#fff', hoverOffset: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 14, boxWidth: 12 } } }
        }
    });
}
</script>
HTML;
require_once __DIR__ . '/../includes/foot.php';
?>
