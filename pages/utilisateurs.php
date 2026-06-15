<?php
// pages/utilisateurs.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin']);

$error   = null;
$success = null;

// ── Traitement POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (token CSRF). Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // ── Modifier les infos d'un utilisateur ───────────────────────────
        if ($action === 'edit') {
            $id      = intval($_POST['id_utilisateur'] ?? 0);
            $nom     = trim($_POST['nom_complet'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $role    = $_POST['role'] ?? '';
            $statut  = $_POST['statut'] ?? '';

            $roles_valides   = ['admin', 'libraire', 'adherent'];
            $statuts_valides = ['actif', 'bloque'];

            if (!$id || empty($nom) || empty($email)) {
                $error = "Nom et email sont obligatoires.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse email invalide.";
            } elseif (!in_array($role, $roles_valides) || !in_array($statut, $statuts_valides)) {
                $error = "Rôle ou statut invalide.";
            } else {
                try {
                    // Vérifier unicité email (sauf pour l'utilisateur lui-même)
                    $chk = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email=:e AND id_utilisateur != :id");
                    $chk->execute([':e' => $email, ':id' => $id]);
                    if ($chk->fetch()) {
                        $error = "Cette adresse email est déjà utilisée par un autre compte.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet=:nom, email=:email, role=:role, statut=:statut WHERE id_utilisateur=:id");
                        $stmt->execute([':nom'=>$nom,':email'=>$email,':role'=>$role,':statut'=>$statut,':id'=>$id]);
                        regenerate_csrf_token();
                        $success = "Utilisateur « $nom » mis à jour avec succès.";
                    }
                } catch (\PDOException $e) {
                    $error = "Erreur base de données : " . $e->getMessage();
                }
            }
        }

        // ── Réinitialiser le mot de passe ─────────────────────────────────
        elseif ($action === 'reset_password') {
            $id      = intval($_POST['id_utilisateur'] ?? 0);
            $newmdp  = $_POST['nouveau_mdp'] ?? '';
            $confirm = $_POST['confirm_mdp'] ?? '';

            if (!$id || strlen($newmdp) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } elseif ($newmdp !== $confirm) {
                $error = "Les mots de passe ne correspondent pas.";
            } else {
                try {
                    $hash = password_hash($newmdp, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=:h WHERE id_utilisateur=:id");
                    $stmt->execute([':h'=>$hash,':id'=>$id]);
                    regenerate_csrf_token();
                    $success = "Mot de passe réinitialisé avec succès.";
                } catch (\PDOException $e) {
                    $error = "Erreur base de données : " . $e->getMessage();
                }
            }
        }

        // ── Supprimer un utilisateur ──────────────────────────────────────
        elseif ($action === 'delete') {
            $id = intval($_POST['id_utilisateur'] ?? 0);
            // Empêcher l'admin de se supprimer lui-même
            if ($id === intval($_SESSION['user_id'])) {
                $error = "Vous ne pouvez pas supprimer votre propre compte.";
            } elseif ($id) {
                try {
                    $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur=:id")->execute([':id'=>$id]);
                    regenerate_csrf_token();
                    $success = "Utilisateur supprimé avec succès.";
                } catch (\PDOException $e) {
                    $error = "Impossible de supprimer : " . $e->getMessage();
                }
            }
        }
    }
}

// ── Chargement des utilisateurs ────────────────────────────────────────────
try {
    $utilisateurs = $pdo->query("SELECT id_utilisateur, nom_complet, email, role, statut, date_creation FROM utilisateurs ORDER BY date_creation DESC")->fetchAll();
    $stats = [
        'total'    => count($utilisateurs),
        'admins'   => count(array_filter($utilisateurs, fn($u) => $u['role'] === 'admin')),
        'libraires'=> count(array_filter($utilisateurs, fn($u) => $u['role'] === 'libraire')),
        'adherents'=> count(array_filter($utilisateurs, fn($u) => $u['role'] === 'adherent')),
        'bloques'  => count(array_filter($utilisateurs, fn($u) => $u['statut'] === 'bloque')),
    ];
} catch (\PDOException $e) {
    $utilisateurs = [];
    $stats = ['total'=>0,'admins'=>0,'libraires'=>0,'adherents'=>0,'bloques'=>0];
    $error = "Erreur : " . $e->getMessage();
}

$page_title   = 'Gestion des Utilisateurs';
$current_page = 'utilisateurs';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
    <div class="container-fluid py-5 px-lg-5">

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1" style="color:var(--text-heading)">Gestion des Utilisateurs</h1>
                <p class="text-muted mb-0">Modifiez les comptes, rôles et statuts des membres de l'équipe.</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px">
                <i class="fas fa-check-circle fs-4 me-3 text-success"></i>
                <div><?= htmlspecialchars($success) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px">
                <i class="fas fa-exclamation-circle fs-4 me-3 text-danger"></i>
                <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="row g-4 mb-5">
            <?php
            $kpis = [
                ['label'=>'Total Utilisateurs', 'val'=>$stats['total'],     'icon'=>'users',        'color'=>'#6366f1','bg'=>'rgba(99,102,241,.1)'],
                ['label'=>'Admins',             'val'=>$stats['admins'],    'icon'=>'user-shield',  'color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
                ['label'=>'Libraires',          'val'=>$stats['libraires'], 'icon'=>'user-tie',     'color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
                ['label'=>'Adhérents',          'val'=>$stats['adherents'],'icon'=>'user-graduate', 'color'=>'#10b981','bg'=>'rgba(16,185,129,.1)'],
                ['label'=>'Comptes bloqués',    'val'=>$stats['bloques'],   'icon'=>'user-lock',    'color'=>'#64748b','bg'=>'rgba(100,116,139,.1)'],
            ];
            foreach ($kpis as $k): ?>
            <div class="col-xl col-md-4 col-6">
                <div class="card p-4 h-100 shadow-sm border-0" style="border-radius:16px">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="small fw-bold text-muted text-uppercase mb-1"><?= $k['label'] ?></p>
                            <h3 class="fw-bold mb-0" style="color:var(--text-heading)"><?= $k['val'] ?></h3>
                        </div>
                        <div style="width:46px;height:46px;border-radius:12px;background:<?= $k['bg'] ?>;color:<?= $k['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem">
                            <i class="fas fa-<?= $k['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tableau des utilisateurs -->
        <div class="card shadow-sm border-0 p-4" style="border-radius:16px">
            <h6 class="fw-bold mb-4" style="color:var(--text-heading)">Liste des comptes</h6>
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle mb-0" style="font-size:.92rem">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($utilisateurs as $u): ?>
                        <?php
                        $role_badge = match($u['role']) {
                            'admin'    => 'danger',
                            'libraire' => 'warning',
                            default    => 'info',
                        };
                        $role_label = match($u['role']) {
                            'admin'    => 'Admin',
                            'libraire' => 'Libraire',
                            default    => 'Adhérent',
                        };
                        $is_me = ($u['id_utilisateur'] == $_SESSION['user_id']);
                        ?>
                        <tr>
                            <td class="text-muted small"><?= $u['id_utilisateur'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:34px;height:34px;border-radius:50%;background:rgba(99,102,241,.15);color:#6366f1;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">
                                        <?= mb_strtoupper(mb_substr($u['nom_complet'],0,1)) ?>
                                    </div>
                                    <span class="fw-semibold" style="color:var(--text-heading)"><?= htmlspecialchars($u['nom_complet']) ?></span>
                                    <?php if ($is_me): ?><span class="badge bg-secondary ms-1" style="font-size:.65rem">Vous</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-<?= $role_badge ?> bg-opacity-10 text-<?= $role_badge ?> px-3 py-2 rounded-pill"><?= $role_label ?></span></td>
                            <td>
                                <?php if ($u['statut'] === 'actif'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Bloqué</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($u['date_creation'])) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1"
                                    title="Modifier"
                                    onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning rounded-pill px-3 me-1"
                                    title="Réinitialiser le mot de passe"
                                    onclick="openReset(<?= $u['id_utilisateur'] ?>, '<?= htmlspecialchars(addslashes($u['nom_complet'])) ?>')">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if (!$is_me): ?>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                    title="Supprimer"
                                    onclick="openDelete(<?= $u['id_utilisateur'] ?>, '<?= htmlspecialchars(addslashes($u['nom_complet'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- ── Modal : Modifier utilisateur ──────────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2 text-primary"></i>Modifier l'utilisateur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="utilisateurs.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="id_utilisateur" id="edit_id">
        <div class="modal-body p-4">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Nom complet *</label>
                <input type="text" name="nom_complet" id="edit_nom" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Email *</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Rôle</label>
                    <select name="role" id="edit_role" class="form-select">
                        <option value="admin">Admin</option>
                        <option value="libraire">Libraire</option>
                        <option value="adherent">Adhérent</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-muted text-uppercase">Statut</label>
                    <select name="statut" id="edit_statut" class="form-select">
                        <option value="actif">Actif</option>
                        <option value="bloque">Bloqué</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal : Réinitialiser mot de passe ────────────────────────────────── -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title fw-bold"><i class="fas fa-key me-2 text-warning"></i>Réinitialiser le mot de passe</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="utilisateurs.php">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="id_utilisateur" id="reset_id">
        <div class="modal-body p-4">
            <p class="text-muted mb-4">Nouveau mot de passe pour <strong id="reset_nom"></strong> :</p>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Nouveau mot de passe *</label>
                <input type="password" name="nouveau_mdp" class="form-control" required minlength="6" placeholder="Min. 6 caractères">
            </div>
            <div class="mb-1">
                <label class="form-label small fw-bold text-muted text-uppercase">Confirmer *</label>
                <input type="password" name="confirm_mdp" class="form-control" required minlength="6" placeholder="••••••••">
            </div>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold shadow-sm">Réinitialiser</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal : Supprimer ─────────────────────────────────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div class="modal-header border-0 bg-light p-4">
        <h5 class="modal-title fw-bold text-danger"><i class="fas fa-trash me-2"></i>Confirmer la suppression</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="utilisateurs.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="id_utilisateur" id="delete_id">
        <div class="modal-body p-4">
            <p class="mb-0">Voulez-vous vraiment supprimer le compte de <strong id="delete_nom"></strong> ? Cette action est irréversible.</p>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm">Supprimer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$datatable_ids = ['usersTable'];
$extra_js = <<<JS
<script>
function openEdit(u) {
    document.getElementById('edit_id').value    = u.id_utilisateur;
    document.getElementById('edit_nom').value   = u.nom_complet;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_role').value  = u.role;
    document.getElementById('edit_statut').value= u.statut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function openReset(id, nom) {
    document.getElementById('reset_id').value  = id;
    document.getElementById('reset_nom').textContent = nom;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
function openDelete(id, nom) {
    document.getElementById('delete_id').value  = id;
    document.getElementById('delete_nom').textContent = nom;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
JS;
require_once __DIR__ . '/../includes/foot.php';
?>
