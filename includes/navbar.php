<?php
// includes/navbar.php
// Variables optionnelles : $alertes_count (int), $alertes_stock (array)
$alertes_count = $alertes_count ?? 0;
$alertes_stock = $alertes_stock ?? [];
?>
<div id="page-content-wrapper">
<nav class="navbar navbar-expand-lg border-bottom" style="background:var(--bg-card)">
    <div class="container-fluid">
        <button class="btn btn-sm" id="sidebarToggle" style="color:var(--text-body)">
            <i class="fas fa-bars-staggered"></i>
        </button>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="#" id="themeToggle" class="nav-link" style="color:var(--text-body)">
                <i class="fas fa-moon"></i>
            </a>

            <?php if ($alertes_count > 0): ?>
            <div class="dropdown">
                <a class="nav-link px-2 py-1 position-relative" href="#" role="button" data-bs-toggle="dropdown" style="color:var(--text-body)">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem">
                        <?= $alertes_count ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius:var(--radius-md);min-width:260px">
                    <li><h6 class="dropdown-header small text-uppercase">Alertes Stock Critique</h6></li>
                    <?php foreach ($alertes_stock as $alt): ?>
                    <li>
                        <a class="dropdown-item rounded small py-2" href="livres.php">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            «<?= htmlspecialchars($alt['titre']) ?>» (<?= $alt['quantite_stock'] ?> restants)
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="dropdown">
                <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" style="color:var(--text-body)">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_name']) ?>&background=6366f1&color=fff&size=38"
                         class="rounded-circle shadow-sm me-2" width="38" height="38" alt="Avatar">
                    <span class="small font-weight-bold d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="min-width:200px;border-radius:12px">
                    <li><h6 class="dropdown-header small"><?= htmlspecialchars($_SESSION['user_name']) ?></h6></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2" href="profil.php">
                            <i class="fas fa-user-circle me-2 text-primary"></i> Mon Profil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2" href="login.php?action=logout">
                            <i class="fas fa-sign-out-alt me-2 text-danger"></i> Se déconnecter
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
