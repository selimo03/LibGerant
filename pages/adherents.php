<?php
// pages/adherents.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (token CSRF). Veuillez réessayer.";
    } else {
        $nom       = trim($_POST['nom'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville     = trim($_POST['ville'] ?? '');
        $pays      = trim($_POST['pays'] ?? 'Tchad');

        if (!empty($nom)) {
            // Génère un code unique en évitant les collisions
            do {
                $code_client = 'CLI-' . rand(1000, 9999);
                $chk = $pdo->prepare("SELECT 1 FROM clients WHERE code_client=:c");
                $chk->execute([':c' => $code_client]);
            } while ($chk->fetch());
            try {
                $stmt = $pdo->prepare("INSERT INTO clients (code_client,nom,email,telephone,ville,pays,statut) VALUES (:code,:nom,:email,:tel,:ville,:pays,'Nouveau')");
                $stmt->execute([':code'=>$code_client,':nom'=>$nom,':email'=>$email?:null,':tel'=>$telephone?:null,':ville'=>$ville?:null,':pays'=>$pays]);
                regenerate_csrf_token();
                $success = "Client « $nom » enregistré avec succès (Code : $code_client).";
            } catch (\PDOException $e) {
                $error = "Erreur de base de données : " . $e->getMessage();
            }
        } else {
            $error = "Le nom du client est obligatoire.";
        }
    }
}

try {
    $stmt_clients = $pdo->query("SELECT * FROM clients ORDER BY date_enregistrement DESC");
    $clients = $stmt_clients->fetchAll();

    $stmt_stats = $pdo->query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN statut='Nouveau' THEN 1 ELSE 0 END) as nouveaux,
        SUM(CASE WHEN statut='Fidèle' THEN 1 ELSE 0 END) as fideles,
        SUM(CASE WHEN statut='Inactif' THEN 1 ELSE 0 END) as inactifs
    FROM clients");
    $stats = $stmt_stats->fetch();
} catch (\PDOException $e) {
    $clients = [];
    $stats = ['total'=>0, 'nouveaux'=>0, 'fideles'=>0, 'inactifs'=>0];
    $error = "Erreur : " . $e->getMessage();
}

$page_title   = 'Fichier Clients';
$current_page = 'adherents';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h1 class="h2 font-weight-bold mb-1" style="color:var(--text-heading)">Fichier Clients</h1>
                <p class="text-muted mb-0">Gérez vos relations clients et suivez leurs préférences d'achats.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addClientModal" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none;">
                <i class="fas fa-user-plus me-2"></i> Ajouter un Client
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

        <!-- KPIs Clients -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-muted text-uppercase tracking-wide">Total Clients</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['total'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(99,102,241,.1);color:var(--primary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-users fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-success text-uppercase tracking-wide">Clients Fidèles</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['fideles'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(16,185,129,.1);color:var(--accent);width:48px;height:48px;border-radius:12px;"><i class="fas fa-heart fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-warning text-uppercase tracking-wide">Nouveaux Clients</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['nouveaux'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(245,158,11,.1);color:var(--warning);width:48px;height:48px;border-radius:12px;"><i class="fas fa-user-clock fs-5"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="background: linear-gradient(145deg, #ffffff, #f8f9fc); border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small mb-1 fw-bold text-secondary text-uppercase tracking-wide">Inactifs</p>
                            <h3 style="font-weight:800;color:var(--text-heading)"><?= number_format($stats['inactifs'] ?? 0,0,',',' ') ?></h3>
                        </div>
                        <div class="stat-card-icon shadow-sm d-flex align-items-center justify-content-center" style="background:rgba(100,116,139,.1);color:var(--secondary);width:48px;height:48px;border-radius:12px;"><i class="fas fa-user-minus fs-5"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-0 shadow-sm border-0 overflow-hidden mb-5" style="border-radius: 16px;">
            <div class="card-header bg-white p-4 border-bottom-0">
                <h5 class="mb-0" style="font-weight:700;color:var(--text-heading)">Liste des adhérents</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableClients">
                    <thead class="bg-light">
                        <tr style="color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px">
                            <th class="border-0 ps-4 py-3">Client</th>
                            <th class="border-0 py-3">Contact & Localisation</th>
                            <th class="border-0 py-3">Date d'Enregistrement</th>
                            <th class="border-0 py-3">Statut</th>
                            <th class="border-0 pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="color:var(--text-body)">
                        <?php foreach ($clients as $client): ?>
                        <?php
                            $parts    = explode(' ', $client['nom']);
                            $initials = '';
                            foreach ($parts as $p) { if (!empty($p)) $initials .= strtoupper($p[0]); }
                            $initials = substr($initials, 0, 2);
                            if ($client['statut'] === 'Fidèle') {
                                $sbadge = '<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 shadow-sm"><i class="fas fa-star me-1"></i>Fidèle</span>';
                                $abg='linear-gradient(135deg, #10b981, #059669)'; $afg='#ffffff';
                            } elseif ($client['statut'] === 'Nouveau') {
                                $sbadge = '<span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 shadow-sm"><i class="fas fa-sparkles me-1"></i>Nouveau</span>';
                                $abg='linear-gradient(135deg, #f59e0b, #d97706)'; $afg='#ffffff';
                            } else {
                                $sbadge = '<span class="badge rounded-pill bg-light text-secondary border px-3 py-2 shadow-sm"><i class="fas fa-moon me-1"></i>Inactif</span>';
                                $abg='linear-gradient(135deg, #cbd5e1, #94a3b8)'; $afg='#ffffff';
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <div class="rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm"
                                         style="width:50px;height:50px;background:<?= $abg ?>;color:<?= $afg ?>;font-weight:bold;font-size:1.1rem;flex-shrink:0;">
                                        <?= htmlspecialchars($initials ?: 'CL') ?>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark" style="font-size: 1.05rem;"><?= htmlspecialchars($client['nom']) ?></div>
                                        <div class="text-primary small font-monospace fw-bold">#<?= htmlspecialchars($client['code_client']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small mb-1 text-dark"><i class="fas fa-envelope me-2 text-muted opacity-75"></i><?= htmlspecialchars($client['email'] ?: 'Non renseigné') ?></div>
                                <div class="small mb-1 text-dark"><i class="fas fa-phone me-2 text-muted opacity-75"></i><?= htmlspecialchars($client['telephone'] ?: 'Non renseigné') ?></div>
                                <div class="small text-muted"><i class="fas fa-map-marker-alt me-2 opacity-75"></i><?= htmlspecialchars($client['ville'] ?: '—') ?>, <?= htmlspecialchars($client['pays']) ?></div>
                            </td>
                            <td class="fw-bold text-secondary"><?= date('d M Y', strtotime($client['date_enregistrement'])) ?></td>
                            <td><?= $sbadge ?></td>
                            <td class="pe-4 text-end">
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm border transition-hover" style="width: 36px; height: 36px;" title="Modifier (Bientôt dispo)">
                                    <i class="fas fa-pen text-primary"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal Nouveau Client -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title font-weight-bold" style="color:var(--text-heading);"><i class="fas fa-user-plus me-2 text-primary"></i>Enregistrer un nouveau client</h5>
        <button type="button" class="btn-close shadow-sm bg-white rounded-circle" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="adherents.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <div class="modal-body p-4 p-md-5">
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Nom Complet *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="nom" class="form-control form-control-lg bg-light border-0 px-3 fs-6" placeholder="Nom et prénom du client" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Adresse Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control form-control-lg bg-light border-0 px-3 fs-6" placeholder="nom@email.com">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Téléphone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-phone text-muted"></i></span>
                        <input type="text" name="telephone" class="form-control form-control-lg bg-light border-0 px-3 fs-6" placeholder="+235 66 00 11 22">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Ville</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-city text-muted"></i></span>
                        <input type="text" name="ville" class="form-control form-control-lg bg-light border-0 px-3 fs-6" placeholder="N'Djamena">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small font-weight-bold text-muted text-uppercase tracking-wide">Pays</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 px-4"><i class="fas fa-globe-africa text-muted"></i></span>
                        <input type="text" name="pays" class="form-control form-control-lg bg-light border-0 px-3 fs-6" value="Tchad">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 p-4 bg-light">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Créer le client</button>
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
$datatable_ids = ['tableClients'];
require_once __DIR__ . '/../includes/foot.php';
?>
