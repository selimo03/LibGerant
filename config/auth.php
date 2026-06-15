<?php
// config/auth.php
// Sécurisation de session, rôles utilisateurs et protection CSRF pour LibGérant

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si un utilisateur est connecté.
 * Si ce n'est pas le cas, redirige vers la page de connexion.
 */
function check_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /LibGerant/pages/login.php");
        exit();
    }
}

/**
 * Vérifie si l'utilisateur possède l'un des rôles spécifiés.
 * @param array $allowed_roles Liste des rôles autorisés (ex: ['admin', 'libraire'])
 */
function authorize(array $allowed_roles) {
    check_logged_in();
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        if ($_SESSION['user_role'] === 'adherent') {
            header("Location: /LibGerant/pages/dashboard-adherent.php?error=unauthorized");
        } else {
            header("Location: /LibGerant/pages/dashboard-libraire.php?error=unauthorized");
        }
        exit();
    }
}

/**
 * Génère (ou réutilise) un token CSRF sécurisé stocké en session.
 * À inclure dans chaque formulaire POST :
 *   <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie qu'un token CSRF soumis est valide.
 * Utilise hash_equals() pour éviter les attaques par timing.
 * @param string $submitted_token Token reçu depuis le formulaire
 */
function verify_csrf_token(string $submitted_token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * Régénère le token CSRF après une soumission réussie pour éviter
 * la réutilisation du même token (CSRF double submit).
 */
function regenerate_csrf_token(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Détruit la session en cours (Déconnexion).
 */
function logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: /LibGerant/pages/login.php?status=logged_out");
    exit();
}
?>
