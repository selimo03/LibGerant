<?php
// pages/profil.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
check_logged_in();

$user_id = $_SESSION['user_id'];
$error   = null;
$success = null;

// ── Charger les infos actuelles ───────────────────────────────────────────
$me = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur=:id");
$me->execute([':id' => $user_id]);
$user = $me->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide (CSRF). Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // ── Modifier infos personnelles ───────────────────────────────────
        if ($action === 'update_info') {
            $nom   = trim($_POST['nom_complet'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (empty($nom) || empty($email)) {
                $error = "Nom et email sont obligatoires.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse email invalide.";
            } else {
                $chk = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email=:e AND id_utilisateur!=:id");
                $chk->execute([':e'=>$email,':id'=>$user_id]);
                if ($chk->fetch()) {
                    $error = "Cet email est déjà utilisé par un autre compte.";
                } else {
                    $pdo->prepare("UPDATE utilisateurs SET nom_complet=:n, email=:e WHERE id_utilisateur=:id")
                        ->execute([':n'=>$nom,':e'=>$email,':id'=>$user_id]);
                    // Mettre à jour la fiche client si elle existe
                    $pdo->prepare("UPDATE clients SET nom=:n, email=:e WHERE id_utilisateur=:id")
                        ->execute([':n'=>$nom,':e'=>$email,':id'=>$user_id]);
                    $_SESSION['user_name']  = $nom;
                    $_SESSION['user_email'] = $email;
                    regenerate_csrf_token();
                    $success = "Informations mises à jour avec succès.";
                    // Recharger l'utilisateur
                    $me->execute([':id' => $user_id]);
                    $user = $me->fetch();
                }
            }
        }

        // ── Changer le mot de passe ───────────────────────────────────────
        elseif ($action === 'change_password') {
            $old  = $_POST['ancien_mdp'] ?? '';
            $new  = $_POST['nouveau_mdp'] ?? '';
            $conf = $_POST['confirm_mdp'] ?? '';
            if (!password_verify($old, $user['mot_de_passe'])) {
                $error = "L'ancien mot de passe est incorrect.";
            } elseif (strlen($new) < 6) {
                $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            } elseif ($new !== $conf) {
                $error = "Les mots de passe ne correspondent pas.";
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=:h WHERE id_utilisateur=:id")
                    ->execute([':h'=>$hash,':id'=>$user_id]);
                regenerate_csrf_token();
                $success = "Mot de passe modifié avec succès.";
            }
        }
    }
}

$role_label = match($user['role']) {
    'admin'    => 'Administrateur',
    'libraire' => 'Libraire',
    default    => 'Adhérent',
};

$page_title   = 'Mon Profil';
$current_page = 'profil';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-5 px-lg-5">
    <div class="mb-5">
        <h1 class="h2 fw-bold mb-1" style="color:var(--text-heading)">Mon Profil</h1>
        <p class="text-muted mb-0">Gérez vos informations personnelles et votre mot de passe.</p>
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

    <div class="row g-4">

        <!-- Carte identité -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100" style="border-radius:16px">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nom_complet']) ?>&background=6366f1&color=fff&size=96"
                     class="rounded-circle shadow mx-auto mb-3" width="96" height="96" alt="Avatar">
                <h5 class="fw-bold mb-1" style="color:var(--text-heading)"><?= htmlspecialchars($user['nom_complet']) ?></h5>
                <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge px-4 py-2 rounded-pill mb-2" style="background:rgba(99,102,241,.1);color:#6366f1;font-size:.85rem"><?= $role_label ?></span>
                <p class="text-muted small mt-3 mb-0"><i class="fas fa-calendar me-2"></i>Membre depuis le <?= date('d/m/Y', strtotime($user['date_creation'])) ?></p>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Modifier infos -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius:16px">
                <h6 class="fw-bold mb-4" style="color:var(--text-heading)"><i class="fas fa-user-edit me-2 text-primary"></i>Informations personnelles</h6>
                <form method="POST" action="profil.php">
                    <input type="hidden" name="action" value="update_info">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Nom complet *</label>
                            <input type="text" name="nom_complet" class="form-control" value="<?= htmlspecialchars($user['nom_complet']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Rôle</label>
                            <input type="text" class="form-control bg-light" value="<?= $role_label ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Statut</label>
                            <input type="text" class="form-control bg-light" value="<?= ucfirst($user['statut']) ?>" disabled>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Changer mot de passe -->
            <div class="card border-0 shadow-sm p-4" style="border-radius:16px">
                <h6 class="fw-bold mb-4" style="color:var(--text-heading)"><i class="fas fa-key me-2 text-warning"></i>Changer le mot de passe</h6>
                <form method="POST" action="profil.php">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Mot de passe actuel *</label>
                            <input type="password" name="ancien_mdp" class="form-control" required placeholder="Votre mot de passe actuel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Nouveau mot de passe *</label>
                            <input type="password" name="nouveau_mdp" id="newpw" class="form-control" required minlength="6" placeholder="Min. 6 caractères">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Confirmer *</label>
                            <input type="password" name="confirm_mdp" id="confpw" class="form-control" required minlength="6" placeholder="••••••••">
                            <div id="pwMsg" class="form-text mt-1" style="display:none"></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold shadow-sm">
                            <i class="fas fa-lock me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<JS
<script>
var np = document.getElementById('newpw'), cp = document.getElementById('confpw'), msg = document.getElementById('pwMsg');
function checkPw() {
    if (!cp.value) { msg.style.display='none'; return; }
    if (np.value === cp.value) { msg.style.display=''; msg.style.color='#10b981'; msg.textContent='✓ Les mots de passe correspondent.'; }
    else { msg.style.display=''; msg.style.color='#ef4444'; msg.textContent='✗ Les mots de passe ne correspondent pas.'; }
}
cp.addEventListener('input', checkPw);
np.addEventListener('input', checkPw);
</script>
JS;
require_once __DIR__ . '/../includes/foot.php';
?>
