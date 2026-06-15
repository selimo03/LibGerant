<?php
// pages/register.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Si déjà connecté, rediriger vers l'espace approprié
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard-adherent.php");
    exit();
}

$error   = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom_complet'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $mdp     = $_POST['mot_de_passe'] ?? '';
    $confirm = $_POST['confirmer_mdp'] ?? '';

    if (empty($nom) || empty($email) || empty($mdp) || empty($confirm)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (strlen($mdp) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($mdp !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $chk = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = :email");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $error = "Cette adresse email est déjà utilisée.";
            } else {
                $hash = password_hash($mdp, PASSWORD_BCRYPT);

                $pdo->beginTransaction();

                // 1. Créer le compte utilisateur (rôle adherent)
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, role, statut)
                                       VALUES (:nom, :email, :mdp, 'adherent', 'actif')");
                $stmt->execute([':nom' => $nom, ':email' => $email, ':mdp' => $hash]);
                $id_utilisateur = $pdo->lastInsertId();

                // 2. Créer la fiche client liée (code unique garanti)
                do {
                    $code_client = 'CLI-' . rand(1000, 9999);
                    $chk2 = $pdo->prepare("SELECT 1 FROM clients WHERE code_client = :c");
                    $chk2->execute([':c' => $code_client]);
                } while ($chk2->fetch());

                $stmt2 = $pdo->prepare("INSERT INTO clients (id_utilisateur, code_client, nom, email, pays, statut)
                                        VALUES (:uid, :code, :nom, :email, 'Tchad', 'Nouveau')");
                $stmt2->execute([
                    ':uid'   => $id_utilisateur,
                    ':code'  => $code_client,
                    ':nom'   => $nom,
                    ':email' => $email
                ]);

                $pdo->commit();
                $success = true;
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Erreur SQL : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGerant — Créer un compte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .reg-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 3rem;
            border-radius: var(--radius-xl);
            width: 100%; max-width: 480px;
            box-shadow: var(--shadow-xl);
        }
        .reg-input {
            background: var(--bg-card-hover) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-heading) !important;
            border-radius: var(--radius-md) !important;
            padding: 0.8rem 1rem !important;
        }
        .reg-input::placeholder { color: var(--text-muted) !important; }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(40px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
    </style>
</head>
<body>

    <a href="../index.php" class="btn btn-light position-absolute top-0 start-0 m-4 rounded-pill shadow-sm px-4 py-2 border" style="color:var(--text-body)">
        <i class="fas fa-arrow-left me-2"></i> Retour
    </a>
    <a href="#" id="themeToggle" class="btn btn-light position-absolute top-0 end-0 m-4 rounded-pill shadow-sm px-3 py-2 border" style="color:var(--text-body)">
        <i class="fas fa-moon"></i>
    </a>

    <div class="reg-card" style="animation:slideUp 0.5s cubic-bezier(0.34,1.56,0.64,1) both;">

        <div class="text-center mb-4">
            <div class="stat-card-icon mx-auto mb-3"
                 style="background:rgba(99,102,241,0.1); color:var(--primary); width:56px; height:56px; font-size:1.3rem">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="h3 mb-1" style="font-weight:800; color:var(--text-heading)">Créer un compte</h1>
            <p class="small" style="color:var(--text-muted)">Rejoignez la communauté LibGerant.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius:var(--radius-md)">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 text-center mb-4" style="border-radius:var(--radius-md)">
                <i class="fas fa-check-circle me-2"></i> <strong>Compte créé avec succès !</strong>
                <div class="mt-3">
                    <a href="login.php" class="btn btn-success rounded-pill px-5 py-2 shadow-sm fw-bold">
                        Se connecter <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST" action="register.php" id="registerForm">
            <div class="mb-3">
                <label class="form-label small mb-2" style="font-weight:600; color:var(--text-muted)">Nom complet *</label>
                <input type="text" name="nom_complet" class="form-control reg-input"
                       placeholder="Prénom Nom"
                       value="<?= htmlspecialchars($_POST['nom_complet'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label small mb-2" style="font-weight:600; color:var(--text-muted)">Adresse email *</label>
                <input type="email" name="email" class="form-control reg-input"
                       placeholder="nom@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label small mb-2" style="font-weight:600; color:var(--text-muted)">
                    Mot de passe * <span class="fw-normal" style="color:var(--text-muted)">(min. 6 caractères)</span>
                </label>
                <div class="position-relative">
                    <input type="password" name="mot_de_passe" class="form-control reg-input pe-5"
                           id="pwInput" placeholder="••••••••" required minlength="6">
                    <button type="button" id="togglePw"
                            class="btn position-absolute end-0 top-50 translate-middle-y me-2 p-0 border-0"
                            style="background:transparent; color:var(--text-muted); z-index:5">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small mb-2" style="font-weight:600; color:var(--text-muted)">Confirmer le mot de passe *</label>
                <input type="password" name="confirmer_mdp" class="form-control reg-input"
                       placeholder="••••••••" required minlength="6" id="pwConfirm">
                <div id="pwMatchMsg" class="form-text mt-1" style="display:none"></div>
            </div>
            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-3 rounded-pill shadow" style="font-weight:700; font-size:1rem">
                    Créer mon compte <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
            <p class="text-center small mb-0" style="color:var(--text-muted)">
                Déjà membre ?
                <a href="login.php" class="text-decoration-none fw-bold" style="color:var(--primary)">Se connecter</a>
            </p>
        </form>

        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
// Toggle affichage mot de passe
var pwInput  = document.getElementById('pwInput');
var toggleBtn = document.getElementById('togglePw');
var pwIcon   = document.getElementById('pwIcon');
if (toggleBtn && pwInput) {
    toggleBtn.addEventListener('click', function () {
        var hidden = pwInput.type === 'password';
        pwInput.type = hidden ? 'text' : 'password';
        pwIcon.className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
}

// Vérification confirmation mot de passe en temps réel
var pwConfirm = document.getElementById('pwConfirm');
var pwMsg     = document.getElementById('pwMatchMsg');
if (pwConfirm && pwInput && pwMsg) {
    function checkMatch() {
        if (!pwConfirm.value) { pwMsg.style.display = 'none'; return; }
        if (pwInput.value === pwConfirm.value) {
            pwMsg.style.display = '';
            pwMsg.style.color   = 'var(--accent, #10b981)';
            pwMsg.textContent   = '✓ Les mots de passe correspondent.';
        } else {
            pwMsg.style.display = '';
            pwMsg.style.color   = '#ef4444';
            pwMsg.textContent   = '✗ Les mots de passe ne correspondent pas.';
        }
    }
    pwConfirm.addEventListener('input', checkMatch);
    pwInput.addEventListener('input', checkMatch);
}
</script>
</body>
</html>
