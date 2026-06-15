<?php
// index.php
require_once __DIR__ . '/config/auth.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

$db_error = false;
$all_books = [];
$feat_book = null;
$feat_price_p = '14 500 FCFA';
$feat_price_d = '6 800 FCFA';

try {
    require_once __DIR__ . '/config/db.php';
    
    // Récupérer les livres du catalogue
    $stmt_books = $pdo->query("SELECT * FROM livres ORDER BY date_ajout DESC");
    $all_books = $stmt_books->fetchAll();

    // Récupérer le livre vedette spécifique (De Bédouin à Président)
    $stmt_feat = $pdo->prepare("SELECT * FROM livres WHERE titre = :titre LIMIT 1");
    $stmt_feat->execute([':titre' => 'De Bédouin à Président']);
    $feat_book = $stmt_feat->fetch();

    if ($feat_book) {
        $feat_price_p = number_format($feat_book['prix_vente'], 0, ',', ' ') . ' FCFA';
        $feat_price_d = number_format(round($feat_book['prix_vente'] * 0.47 / 100) * 100, 0, ',', ' ') . ' FCFA';
    }
} catch (Exception $e) {
    $db_error = true;
    // Fallback static data so the page STILL looks beautiful even without a database!
    $all_books = [
        [
            'isbn' => '978-2-38600-001-3',
            'titre' => 'De Bédouin à Président',
            'auteur' => 'Mahamat Idriss Déby Itno',
            'categorie' => 'Tchad',
            'prix_vente' => 14500,
            'quantite_stock' => 15,
            'seuil_alerte' => 3,
            'cover_bg' => '#1a2a3a',
            'cover_fg' => '#e8d8b4',
            'cover_text' => '#ffffff',
            'cover_pub' => 'VA Éditions',
            'image_url' => 'assets/img/de-bedouin-a-president.webp'
        ],
        [
            'isbn' => '978-2-38600-002-3',
            'titre' => 'Le Comte de Monte-Cristo',
            'auteur' => 'Alexandre Dumas',
            'categorie' => 'Classiques',
            'prix_vente' => 8500,
            'quantite_stock' => 15,
            'seuil_alerte' => 3,
            'cover_bg' => '#172554',
            'cover_fg' => '#fbbf24',
            'cover_text' => '#ffffff',
            'cover_pub' => 'Le Livre de Poche',
            'image_url' => 'assets/img/le-comte-de-monte-cristo.jpg'
        ],
        [
            'isbn' => '978-2-38600-004-3',
            'titre' => 'Clean Code',
            'auteur' => 'Robert C. Martin',
            'categorie' => 'Informatique',
            'prix_vente' => 28500,
            'quantite_stock' => 15,
            'seuil_alerte' => 3,
            'cover_bg' => '#0c0a09',
            'cover_fg' => '#22d3ee',
            'cover_text' => '#ffffff',
            'cover_pub' => 'Prentice Hall',
            'image_url' => 'assets/img/clean-code.jpg'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGerant — Votre Librairie Premium</title>
    <meta name="description" content="LibGerant : achetez vos livres papier ou téléchargez vos E-books instantanément. La librairie hybride nouvelle génération.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <?php if ($db_error): ?>
        <div class="alert alert-warning border-0 rounded-0 text-center py-3 mb-0" style="z-index: 10000; position: relative;">
            <i class="fas fa-exclamation-triangle me-2"></i> 
            <strong>Mode Hors-ligne :</strong> La base de données n'est pas connectée. 
            Veuillez lancer MySQL dans XAMPP et importer <a href="schema.sql" class="alert-link">schema.sql</a>.
        </div>
    <?php endif; ?>

    <!-- ═══════ Search Overlay ═══════ -->
    <div class="search-overlay" id="searchOverlay">
        <div class="search-modal text-center">
            <button class="btn btn-link position-absolute top-0 end-0 m-4 fs-3" style="color:var(--text-muted)" id="closeSearch"><i class="fas fa-times"></i></button>
            <h2 class="display-6 mb-5" style="font-weight:800; color:var(--text-heading)">Que souhaitez-vous lire ?</h2>
            <input type="text" class="form-control form-control-lg border-0 text-center py-4" style="font-size:1.5rem; background:var(--bg-card-hover); border-radius:var(--radius-lg); color:var(--text-heading)" placeholder="Titre, auteur, genre...">
            <div class="mt-4">
                <span class="text-muted small me-2">Populaire :</span>
                <span class="badge bg-light text-dark rounded-pill px-3 py-2 me-1" style="cursor:pointer">Thriller</span>
                <span class="badge bg-light text-dark rounded-pill px-3 py-2 me-1" style="cursor:pointer">Science</span>
                <span class="badge bg-light text-dark rounded-pill px-3 py-2" style="cursor:pointer">Roman</span>
            </div>
        </div>
    </div>

    <!-- ═══════ Navbar ═══════ -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-book-open me-2"></i>LibGerant</a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <i class="fas fa-bars" style="color:var(--text-body)"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#" id="openSearch"><i class="fas fa-search me-1"></i> Recherche</a></li>
                    <li class="nav-item"><a class="nav-link" href="#catalogue">Catalogue</a></li>
                    <li class="nav-item"><a class="nav-link" href="#genres">Genres</a></li>
                    <li class="nav-item"><a class="nav-link" href="pages/equipe.html">Équipe</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" id="themeToggle"><i class="fas fa-moon"></i></a></li>
                    <li class="nav-item me-1">
                        <a class="nav-link position-relative px-3" href="#" id="cartToggle" style="font-size:1.1rem">
                            <i class="fas fa-shopping-bag"></i>
                            <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.55rem; display:none">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown ms-2">
                        <?php if ($is_logged_in): ?>
                            <a class="nav-link dropdown-toggle btn btn-sm btn-login rounded-pill px-4 py-2 border" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <span class="small" style="font-weight:600"><?= htmlspecialchars($user_name) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" style="border-radius:var(--radius-md)">
                                <?php if ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item rounded py-2 px-3" href="pages/dashboard-admin.php"><i class="fas fa-cog me-2 text-info"></i> Administration</a></li>
                                <?php elseif ($user_role === 'adherent'): ?>
                                    <li><a class="dropdown-item rounded py-2 px-3" href="pages/dashboard-adherent.php"><i class="fas fa-user me-2 text-info"></i> Mon Espace</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item rounded py-2 px-3" href="pages/dashboard-libraire.php"><i class="fas fa-book me-2 text-info"></i> Espace Libraire</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item rounded py-2 px-3 text-danger" href="pages/login.php?action=logout"><i class="fas fa-sign-out-alt me-2"></i> Se déconnecter</a></li>
                            </ul>
                        <?php else: ?>
                            <a class="nav-link dropdown-toggle btn btn-sm btn-login rounded-pill px-4 py-2 border" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <span class="small" style="font-weight:600">Mon Compte</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" style="border-radius:var(--radius-md)">
                                <li><a class="dropdown-item rounded py-2 px-3" href="pages/login.php"><i class="fas fa-sign-in-alt me-2 text-primary"></i> Se connecter</a></li>
                            </ul>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ═══════ Hero Section ═══════ -->
    <section class="hero-section text-center text-white">
        <div class="container z-index-1 position-relative">
            <span class="badge rounded-pill bg-white text-primary px-4 py-2 mb-4 stagger-item" style="font-weight:600"><i class="fas fa-star me-1"></i> Nouveautés 2026</span>
            <h1 class="display-2 mb-4 stagger-item delay-1" style="font-weight:800; letter-spacing:-2px">La littérature à<br>portée de main.</h1>
            <p class="lead mb-5 opacity-75 mx-auto stagger-item delay-2" style="max-width:600px">Achetez vos livres papier ou téléchargez vos E-books instantanément, où que vous soyez.</p>
            <div class="d-flex justify-content-center flex-wrap gap-3 stagger-item delay-3">
                <a href="#catalogue" class="btn btn-light btn-lg rounded-pill px-5 shadow-lg" style="font-weight:700">Explorer le catalogue</a>
                <a href="pages/login.php" class="btn btn-outline-light btn-lg rounded-pill px-5">Espace Libraire</a>
            </div>
        </div>
    </section>

    <!-- ═══════ Featured Book ═══════ -->
    <section class="container" style="margin-top:-60px; position:relative; z-index:2;">
        <div class="card featured-card p-4 p-lg-5 fade-in-hidden">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(12,46,98,0.12); color:#0c2e62; font-weight:600"><i class="fas fa-crown me-1"></i> PUBLICATION PRÉSIDENTIELLE</span>
                    <h2 class="display-5 mb-3" style="font-weight:800; color:var(--text-heading)">De Bédouin à Président</h2>
                    <p class="mb-4" style="font-size:1.1rem; color:var(--text-muted); line-height:1.7">L'autobiographie de <strong>S.E. Mahamat Idriss Déby Itno</strong>, Président de la République du Tchad. Du désert tchadien aux plus hautes fonctions de l'État : un témoignage exceptionnel sur un parcours hors du commun, publié aux Éditions VA.</p>
                    <?php $feat_isbn = $feat_book ? htmlspecialchars($feat_book['isbn']) : '978-2-38600-001-3'; ?>
                    <div class="d-flex flex-wrap gap-3">
                        <button class="btn btn-primary btn-lg rounded-pill px-4 shadow btn-buy" data-isbn="<?= $feat_isbn ?>" data-book="De Bédouin à Président" data-price="<?= $feat_price_p ?>"><i class="fas fa-shopping-bag me-2"></i> Papier — <?= $feat_price_p ?></button>
                        <button class="btn btn-outline-success btn-lg rounded-pill px-4 btn-download" data-isbn="<?= $feat_isbn ?>" data-book="De Bédouin à Président" data-price="<?= $feat_price_d ?>"><i class="fas fa-download me-2"></i> Kindle — <?= $feat_price_d ?></button>
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <img src="assets/img/de-bedouin-a-president.webp"
                         data-cover-title="De Bédouin à Président"
                         data-cover-author="Mahamat Idriss Déby Itno"
                         data-cover-bg="#1a2a3a"
                         data-cover-fg="#e8d8b4"
                         data-cover-pub="VA Éditions"
                         class="img-fluid shadow-lg"
                         style="max-height:320px; border-radius:var(--radius-lg)"
                         alt="De Bédouin à Président — Mahamat Idriss Déby Itno">
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════ Catalogue ═══════ -->
    <section id="catalogue" class="container my-5 py-5 fade-in-hidden">
        <div class="text-center mb-5">
            <h2 class="display-6 mb-2" style="font-weight:800; color:var(--text-heading)">Notre Catalogue</h2>
            <p style="color:var(--text-muted); max-width:500px; margin:0 auto">Chaque livre est disponible en format papier <strong>et</strong> numérique.</p>
        </div>

        <!-- Filtres -->
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5" id="catalogue-filters">
            <button class="btn filter-btn active" data-filter="all">Tous</button>
            <button class="btn filter-btn" data-filter="Tchad">Tchad</button>
            <button class="btn filter-btn" data-filter="Afrique">Afrique</button>
            <button class="btn filter-btn" data-filter="Classiques">Classiques</button>
            <button class="btn filter-btn" data-filter="Science-Fiction">Science-Fiction</button>
            <button class="btn filter-btn" data-filter="Informatique">Informatique</button>
        </div>

        <div class="row g-4" id="books-grid">
            <?php if (count($all_books) > 0): ?>
                <?php foreach ($all_books as $book): ?>
                    <?php
                    $price_p = number_format($book['prix_vente'], 0, ',', ' ') . ' FCFA';
                    $price_d = number_format(round($book['prix_vente'] * 0.55 / 100) * 100, 0, ',', ' ') . ' FCFA';
                    
                    // Style de badge selon la catégorie
                    $badge_html = '';
                    if ($book['categorie'] === 'Tchad') {
                        $badge_html = '<span class="badge badge-book bg-dark text-white"><i class="fas fa-flag me-1"></i> Tchadien</span>';
                    } elseif ($book['categorie'] === 'Informatique') {
                        $badge_html = '<span class="badge badge-book bg-primary text-white"><i class="fas fa-code me-1"></i> Informatique</span>';
                    } elseif ($book['categorie'] === 'Classiques') {
                        $badge_html = '<span class="badge badge-book bg-warning text-dark"><i class="fas fa-crown me-1"></i> Classique</span>';
                    } elseif ($book['categorie'] === 'Science-Fiction') {
                        $badge_html = '<span class="badge badge-book bg-info text-dark"><i class="fas fa-space-shuttle me-1"></i> SF</span>';
                    } else {
                        $badge_html = '<span class="badge badge-book bg-secondary text-white"><i class="fas fa-book-open me-1"></i> Culture</span>';
                    }
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 book-item" data-genre="<?= htmlspecialchars($book['categorie']) ?>">
                        <div class="card book-card border-0">
                            <div class="book-image-wrapper">
                                <?= $badge_html ?>
                                <img src="<?= htmlspecialchars($book['image_url'] ?: 'assets/img/cover_fallback.png') ?>"
                                     data-cover-title="<?= htmlspecialchars($book['titre']) ?>"
                                     data-cover-author="<?= htmlspecialchars($book['auteur']) ?>"
                                     data-cover-bg="<?= htmlspecialchars($book['cover_bg']) ?>"
                                     data-cover-fg="<?= htmlspecialchars($book['cover_fg']) ?>"
                                     data-cover-pub="<?= htmlspecialchars($book['cover_pub']) ?>"
                                     data-cover-text="<?= htmlspecialchars($book['cover_text']) ?>"
                                     class="book-cover"
                                     alt="<?= htmlspecialchars($book['titre']) ?> — <?= htmlspecialchars($book['auteur']) ?>">
                            </div>
                            <div class="card-body">
                                <div class="book-category"><?= htmlspecialchars($book['categorie']) ?></div>
                                <h5 class="book-title"><?= htmlspecialchars($book['titre']) ?></h5>
                                <p class="book-author"><?= htmlspecialchars($book['auteur']) ?></p>
                                <div class="book-stars mb-2">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                    <span class="rating-count">4.5</span>
                                </div>
                                <div class="book-actions">
                                    <button class="btn btn-sm btn-primary rounded-pill btn-buy" data-isbn="<?= htmlspecialchars($book['isbn']) ?>" data-book="<?= htmlspecialchars($book['titre']) ?>" data-price="<?= $price_p ?>"><i class="fas fa-shopping-bag me-1"></i> Papier — <?= $price_p ?></button>
                                    <button class="btn btn-sm btn-outline-success rounded-pill btn-download" data-isbn="<?= htmlspecialchars($book['isbn']) ?>" data-book="<?= htmlspecialchars($book['titre']) ?>" data-price="<?= $price_d ?>"><i class="fas fa-download me-1"></i> E-book — <?= $price_d ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-books fa-3x mb-3"></i>
                    <p>Aucun livre disponible dans le catalogue.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══════ Genres ═══════ -->
    <section id="genres" class="container my-5 pb-5 fade-in-hidden">
        <div class="text-center mb-5">
            <h2 class="display-6 mb-2" style="font-weight:800; color:var(--text-heading)">Explorer par Genre</h2>
            <p style="color:var(--text-muted)">Des milliers de titres dans chaque univers.</p>
        </div>
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="genre-card text-center p-4 h-100" data-filter="Tchad">
                    <div class="genre-icon" style="background:rgba(99,102,241,0.1); color:var(--primary)"><i class="fas fa-globe-africa"></i></div>
                    <h5 style="font-weight:700; color:var(--text-heading)">Tchad</h5>
                    <p class="small mb-0" style="color:var(--text-muted)">87 titres</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="genre-card text-center p-4 h-100" data-filter="Informatique">
                    <div class="genre-icon" style="background:rgba(16,185,129,0.1); color:var(--accent)"><i class="fas fa-laptop-code"></i></div>
                    <h5 style="font-weight:700; color:var(--text-heading)">Informatique</h5>
                    <p class="small mb-0" style="color:var(--text-muted)">142 titres</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="genre-card text-center p-4 h-100" data-filter="Classiques">
                    <div class="genre-icon" style="background:rgba(245,158,11,0.1); color:var(--warning)"><i class="fas fa-feather-pointed"></i></div>
                    <h5 style="font-weight:700; color:var(--text-heading)">Classiques</h5>
                    <p class="small mb-0" style="color:var(--text-muted)">128 titres</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="genre-card text-center p-4 h-100" data-filter="Afrique">
                    <div class="genre-icon" style="background:rgba(236,72,153,0.1); color:var(--secondary)"><i class="fas fa-book"></i></div>
                    <h5 style="font-weight:700; color:var(--text-heading)">Afrique</h5>
                    <p class="small mb-0" style="color:var(--text-muted)">156 titres</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════ Newsletter ═══════ -->
    <section class="container mb-5 fade-in-hidden">
        <div class="newsletter-section text-white p-5">
            <div class="row align-items-center position-relative" style="z-index:1">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 style="font-weight:800" class="mb-2">Rejoignez le Club LibGerant</h2>
                    <p class="opacity-75 mb-0">Recevez nos coups de cœur et des invitations exclusives.</p>
                </div>
                <div class="col-lg-6">
                    <form class="d-flex gap-2" id="newsletter-form">
                        <input type="email" class="form-control rounded-pill border-0 px-4 py-3" style="background:rgba(255,255,255,0.2); color:#fff" placeholder="Votre email..." required>
                        <button type="submit" class="btn btn-light rounded-pill px-4 shadow-sm" style="font-weight:700; white-space:nowrap">S'inscrire</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════ Footer ═══════ -->
    <footer class="footer border-top py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <h4 style="font-weight:800; color:var(--primary)" class="mb-3"><i class="fas fa-book-open me-2"></i>LibGerant</h4>
                    <p style="color:var(--text-muted)">La première librairie hybride papier & numérique. Commandez ou téléchargez instantanément.</p>
                </div>
                <div class="col-6 col-lg-2 ms-auto">
                    <h6 style="font-weight:700; color:var(--text-heading)" class="mb-3">Navigation</h6>
                    <ul class="list-unstyled small" style="color:var(--text-muted)">
                        <li class="mb-2"><a href="#catalogue" style="color:var(--text-muted)" class="text-decoration-none">Catalogue</a></li>
                        <li class="mb-2"><a href="#genres" style="color:var(--text-muted)" class="text-decoration-none">Genres</a></li>
                        <li class="mb-2"><a href="pages/equipe.html" style="color:var(--text-muted)" class="text-decoration-none">Notre équipe</a></li>
                        <li class="mb-2"><a href="pages/login.php" style="color:var(--text-muted)" class="text-decoration-none">Connexion</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 style="font-weight:700; color:var(--text-heading)" class="mb-3">Admin</h6>
                    <ul class="list-unstyled small" style="color:var(--text-muted)">
                        <li class="mb-2"><a href="pages/dashboard-admin.php" style="color:var(--text-muted)" class="text-decoration-none">Dashboard</a></li>
                        <li class="mb-2"><a href="pages/livres.php" style="color:var(--text-muted)" class="text-decoration-none">Stock</a></li>
                        <li class="mb-2"><a href="pages/prets.php" style="color:var(--text-muted)" class="text-decoration-none">Ventes</a></li>
                    </ul>
                </div>
            </div>
            <hr style="border-color:var(--border-color)" class="my-4">
            <p class="text-center small mb-0" style="color:var(--text-muted)">&copy; 2026 LibGerant — Librairie Connectée.</p>
        </div>
    </footer>

    <!-- Bouton retour en haut -->
    <button id="backToTop" class="btn btn-primary back-to-top shadow" title="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Modal Panier -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
        <div class="offcanvas-header border-bottom" style="background:var(--bg-card)">
            <h5 class="offcanvas-title" style="font-weight:800; color:var(--text-heading)"><i class="fas fa-shopping-bag me-2 text-primary"></i>Mon Panier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body" style="background:var(--bg-body)">
            <div id="cart-items-list">
                <div class="text-center py-5" id="cart-empty-msg">
                    <i class="fas fa-shopping-bag fa-3x mb-3" style="color:var(--border-color)"></i>
                    <p style="color:var(--text-muted)">Votre panier est vide.</p>
                </div>
            </div>
            <div id="cart-footer" style="display:none">
                <hr style="border-color:var(--border-color)">
                <div class="d-flex justify-content-between mb-3">
                    <span style="font-weight:700; color:var(--text-heading)">Total</span>
                    <span id="cart-total" style="font-weight:800; color:var(--primary); font-size:1.1rem">0 FCFA</span>
                </div>
                <button id="btn-checkout" class="btn btn-primary w-100 rounded-pill py-3 shadow" style="font-weight:700">
                    <i class="fas fa-lock me-2"></i>Passer la commande
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Finaliser Commande -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:24px; background:var(--bg-card); overflow:hidden;">
                <div class="modal-header border-bottom p-4" style="background:var(--bg-card)">
                    <h5 class="modal-title" style="font-weight:800; color:var(--text-heading)">
                        <i class="fas fa-shopping-basket me-2 text-primary"></i>Finaliser ma commande
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="checkout-form">
                    <div class="modal-body p-4">
                        <!-- Résumé Panier -->
                        <div class="mb-4 p-3 rounded-4" style="background:var(--bg-body); border:1px solid var(--border-color)">
                            <h6 class="mb-3" style="font-weight:700; color:var(--text-heading)">Résumé de vos articles</h6>
                            <div id="checkout-summary-list" class="d-flex flex-column gap-2 mb-3">
                                <!-- Dynamiquement rempli par JS -->
                            </div>
                            <hr style="border-color:var(--border-color)">
                            <div class="d-flex justify-content-between align-items-center">
                                <span style="font-weight:600; color:var(--text-muted)">Total à payer :</span>
                                <span id="checkout-summary-total" style="font-weight:800; color:var(--primary); font-size:1.2rem">0 FCFA</span>
                            </div>
                        </div>

                        <!-- Formulaire Client -->
                        <div class="mb-3">
                            <h6 class="mb-3" style="font-weight:700; color:var(--text-heading)">Informations de facturation</h6>
                            <?php if ($is_logged_in): ?>
                                <div class="p-3 rounded-4 border" style="background:rgba(99,102,241,0.05); border-color:rgba(99,102,241,0.2) !important">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:42px; height:42px; font-weight:700">
                                            <?= strtoupper(substr($user_name, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:700; color:var(--text-heading)"><?= htmlspecialchars($user_name) ?></div>
                                            <div class="small text-muted">Achat via votre compte adhérent</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info py-2 px-3 mb-3 border-0 rounded-4 small d-flex align-items-center gap-2">
                                    <i class="fas fa-info-circle text-primary"></i>
                                    <span>Déjà client ? <a href="pages/login.php" class="alert-link text-decoration-none">Connectez-vous</a> pour synchroniser votre achat.</span>
                                </div>
                                <div class="d-flex flex-column gap-3">
                                    <div>
                                        <label class="form-label small" style="font-weight:600; color:var(--text-heading)">Nom Complet *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-0"><i class="fas fa-user text-muted"></i></span>
                                            <input type="text" name="nom" class="form-control bg-light border-0 py-2.5" placeholder="Ex: Jean Dupont" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label small" style="font-weight:600; color:var(--text-heading)">Adresse Email *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-0"><i class="fas fa-envelope text-muted"></i></span>
                                            <input type="email" name="email" class="form-control bg-light border-0 py-2.5" placeholder="jean.dupont@example.com" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label small" style="font-weight:600; color:var(--text-heading)">Numéro de Téléphone</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-0"><i class="fas fa-phone text-muted"></i></span>
                                            <input type="tel" name="telephone" class="form-control bg-light border-0 py-2.5" placeholder="+235 66 00 00 00">
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Mode de Règlement -->
                        <div class="mb-3">
                            <label class="form-label small" style="font-weight:600; color:var(--text-heading)">Mode de Règlement</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-wallet text-muted"></i></span>
                                <select name="mode_reglement" class="form-select bg-light border-0 py-2.5" style="border-radius:0 12px 12px 0">
                                    <option value="especes" selected>Espèces</option>
                                    <option value="mobile_money">Mobile Money (Airtel Money / Moov Money)</option>
                                    <option value="carte">Carte Bancaire</option>
                                    <option value="cheque">Chèque</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top p-4" style="background:var(--bg-card)">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow" style="font-weight:700">
                            <i class="fas fa-check-circle me-1"></i>Confirmer & Régler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.LibGerantConfig = {
            isLoggedIn: <?= json_encode($is_logged_in) ?>,
            userRole: <?= json_encode($user_role) ?>,
            userName: <?= json_encode($user_name) ?>,
            appBasePath: <?= json_encode(app_base() . '/') ?>
        };
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>
