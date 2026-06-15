<?php
// pages/login.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Déconnexion de l'utilisateur
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}

// Si l'utilisateur est déjà connecté, le rediriger vers son espace
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: dashboard-admin.php");
    } elseif ($_SESSION['user_role'] === 'adherent') {
        header("Location: dashboard-adherent.php");
    } else {
        header("Location: dashboard-libraire.php");
    }
    exit();
}

$error = null;

// Traitement de la soumission du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    if (!empty($email) && !empty($password)) {
        try {
            // Rechercher l'utilisateur par email
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Vérifier si le compte est actif
                if ($user['statut'] === 'actif') {
                    // Initialiser les variables de session
                    $_SESSION['user_id'] = $user['id_utilisateur'];
                    $_SESSION['user_name'] = $user['nom_complet'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // Rediriger selon le rôle de l'utilisateur
                    if ($user['role'] === 'admin') {
                        header("Location: dashboard-admin.php");
                    } elseif ($user['role'] === 'adherent') {
                        header("Location: dashboard-adherent.php");
                    } else {
                        header("Location: dashboard-libraire.php");
                    }
                    exit();
                } else {
                    $error = "Votre compte a été bloqué par l'administrateur.";
                }
            } else {
                $error = "Identifiants de connexion incorrects.";
            }
        } catch (\PDOException $e) {
            $error = "Erreur de connexion à la base de données.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGerant — Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 3rem;
            border-radius: var(--radius-xl);
            width: 100%; max-width: 440px;
            box-shadow: var(--shadow-xl);
        }
        .login-input {
            background: var(--bg-card-hover) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-heading) !important;
            border-radius: var(--radius-md) !important;
            padding: 0.8rem 1rem !important;
        }
        .login-input::placeholder { color: var(--text-muted) !important; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
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

    <div class="login-card" style="animation: slideUp 0.5s cubic-bezier(0.34,1.56,0.64,1) both;">
        <div class="text-center mb-4">
            <div class="stat-card-icon mx-auto mb-3" style="background:rgba(99,102,241,0.1); color:var(--primary); width:56px; height:56px; font-size:1.3rem">
                <i class="fas fa-book-open"></i>
            </div>
            <h1 class="h3 mb-1" style="font-weight:800; color:var(--text-heading)">LibGerant</h1>
            <p class="small" style="color:var(--text-muted)">Connectez-vous à votre espace.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius:var(--radius-md)">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'logged_out'): ?>
            <div class="alert alert-success border-0 small text-center mb-4" style="border-radius:var(--radius-md)">
                <i class="fas fa-check-circle me-2"></i> Déconnexion réussie.
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <div class="mb-4">
                <label class="form-label small mb-2" style="font-weight:600; color:var(--text-muted)">Adresse email</label>
                <input type="email" name="email" class="form-control login-input" placeholder="nom@librairie.td" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <label class="form-label small mb-0" style="font-weight:600; color:var(--text-muted)">Mot de passe</label>
                    <a href="#" class="small text-decoration-none" style="color:var(--primary)">Oublié ?</a>
                </div>
                <div class="position-relative">
                    <input type="password" name="mot_de_passe" class="form-control login-input pe-5" id="passwordInput" placeholder="••••••••" required>
                    <button type="button" id="togglePassword" class="btn position-absolute end-0 top-50 translate-middle-y me-2 p-0 border-0" style="background:transparent; color:var(--text-muted); font-size:0.9rem; z-index:5">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-3 rounded-pill shadow" style="font-weight:700; font-size:1rem">
                    Se connecter <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
            <p class="text-center small mb-0" style="color:var(--text-muted)">
                Nouveau ? <a href="register.php" class="text-decoration-none" style="color:var(--primary); font-weight:600">Créer un compte</a>
            </p>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
// Toggle mot de passe
var pwInput = document.getElementById('passwordInput');
var toggleBtn = document.getElementById('togglePassword');
var icon = document.getElementById('toggleIcon');
if (toggleBtn && pwInput) {
    toggleBtn.addEventListener('click', function() {
        var isHidden = pwInput.type === 'password';
        pwInput.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
}
</script>
</body>
</html>
