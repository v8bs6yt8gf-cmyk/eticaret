<?php
$siteName   = e(setting('site_name', 'Store'));
$baseUrl    = rtrim(setting('base_url', ''), '/');
$pageTitle  = isset($pageTitle) ? e($pageTitle) . ' - ' . $siteName : $siteName;
$cartCount  = cart_count();
$currentPath = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(setting('meta_description', 'Modern online store')) ?>">
    <meta name="theme-color" content="#0f172a">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="<?= $baseUrl ?>/public/assets/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?= $baseUrl ?>/public/assets/css/app.css" rel="stylesheet">
</head>
<body class="shop-body">
<div class="ambient-shell" aria-hidden="true">
    <span class="ambient-shape shape-one"></span>
    <span class="ambient-shape shape-two"></span>
</div>
<nav class="navbar navbar-expand-lg shop-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand brand-mark" href="<?= $baseUrl ?>/" aria-label="<?= $siteName ?> home">
            <span class="brand-icon"><i class="bi bi-bag-check-fill"></i></span>
            <span><?= $siteName ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'index.php' ? 'active' : '' ?>" href="<?= $baseUrl ?>/">Home</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === 'products.php' ? 'active' : '' ?>" href="<?= $baseUrl ?>/products.php">Products</a></li>
            </ul>
            <form action="<?= $baseUrl ?>/products.php" method="GET" class="nav-search me-lg-3 mb-3 mb-lg-0" role="search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search products" aria-label="Search products">
            </form>
            <ul class="navbar-nav align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link icon-link cart-link" href="<?= $baseUrl ?>/cart.php" aria-label="Cart">
                        <i class="bi bi-bag"></i>
                        <?php if ($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i> Account</a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/account.php"><i class="bi bi-person me-2"></i>My Account</a></li>
                            <?php if (is_admin()): ?><li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</a></li><?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $baseUrl ?>/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-gradient btn-sm px-3" href="<?= $baseUrl ?>/register.php">Create Account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080">
    <?php foreach (get_flashes() as $flash): ?>
        <div class="toast align-items-center border-0 show app-toast text-bg-<?= e($flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?>" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?= e($flash['message']) ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<main class="site-main">
