<?php
// includes/sidebar.php
// Variable attendue : $current_page (string)
// Clés valides : 'dashboard','livres','adherents','prets','emprunts','reservations','statistiques'

$_lg_role = $_SESSION['user_role'] ?? 'libraire';

if (!function_exists('_lgNav')) {
    function _lgNav(string $href, string $icon, string $label, string $current, string $key): string {
        $active = ($current === $key) ? ' active' : '';
        return "<a href=\"{$href}\" class=\"list-group-item{$active}\"><i class=\"fas fa-{$icon}\"></i> {$label}</a>\n";
    }
}
?>
<div id="sidebar-wrapper">
    <div class="sidebar-heading">
        <i class="fas fa-book-open me-2"></i>LibGérant
    </div>
    <div class="list-group list-group-flush mt-3">
        <?php if ($_lg_role === 'admin'): ?>
            <?= _lgNav('dashboard-admin.php',  'chart-line',    "Vue d'ensemble",    $current_page, 'dashboard') ?>
            <?= _lgNav('livres.php',           'book',          'Catalogue & Stock', $current_page, 'livres') ?>
            <?= _lgNav('adherents.php',        'user-friends',  'Clients',           $current_page, 'adherents') ?>
            <?= _lgNav('prets.php',            'shopping-cart', 'Ventes',            $current_page, 'prets') ?>
            <?= _lgNav('emprunts.php',         'book-reader',   'Emprunts',          $current_page, 'emprunts') ?>
            <?= _lgNav('reservations.php',     'clock',         'Pré-commandes',     $current_page, 'reservations') ?>
            <?= _lgNav('statistiques.php',     'chart-pie',     'Rapports',          $current_page, 'statistiques') ?>
            <?= _lgNav('utilisateurs.php',     'users-cog',     'Utilisateurs',      $current_page, 'utilisateurs') ?>
        <?php elseif ($_lg_role === 'libraire'): ?>
            <?= _lgNav('dashboard-libraire.php','chart-line',   "Vue d'ensemble",    $current_page, 'dashboard') ?>
            <?= _lgNav('prets.php',             'shopping-cart','Ventes',            $current_page, 'prets') ?>
            <?= _lgNav('emprunts.php',          'book-reader',  'Emprunts',          $current_page, 'emprunts') ?>
            <?= _lgNav('livres.php',            'book',         'Gestion Stock',     $current_page, 'livres') ?>
            <?= _lgNav('adherents.php',         'user-friends', 'Clients',           $current_page, 'adherents') ?>
            <?= _lgNav('statistiques.php',      'chart-pie',    'Rapports',          $current_page, 'statistiques') ?>
        <?php elseif ($_lg_role === 'adherent'): ?>
            <?= _lgNav('dashboard-adherent.php','home',         'Mon Espace',        $current_page, 'dashboard') ?>
            <a href="../index.php#catalogue" class="list-group-item"><i class="fas fa-search"></i> Catalogue</a>
        <?php endif; ?>
    </div>
</div>
