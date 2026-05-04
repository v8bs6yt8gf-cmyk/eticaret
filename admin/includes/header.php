<?php
// Bootstrap (idempotent: safe to include twice)
require_once __DIR__ . '/bootstrap.php';

$siteName  = e(setting('site_name', 'Store'));
$baseUrl   = rtrim(setting('base_url', ''), '/');
$pageTitle = isset($pageTitle) ? e($pageTitle) . ' — Admin' : 'Admin — ' . $siteName;
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= $baseUrl ?>/public/assets/css/app.css" rel="stylesheet">
</head>
<body class="admin-body">

<div class="ambient-shell" aria-hidden="true">
    <span class="ambient-shape shape-one"></span>
    <span class="ambient-shape shape-two"></span>
</div>

<!-- Sidebar -->
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <a href="<?= $baseUrl ?>/admin/"><i class="bi bi-speedometer2 me-2"></i><?= $siteName ?></a>
    </div>

    <div class="nav-section">Ana</div>
    <a href="<?= $baseUrl ?>/admin/" class="sidebar-link <?= $currentPage === 'index' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <div class="nav-section mt-3">Mağaza</div>
    <a href="<?= $baseUrl ?>/admin/products.php" class="sidebar-link <?= $currentPage === 'products' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Ürünler
    </a>
    <a href="<?= $baseUrl ?>/admin/categories.php" class="sidebar-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> Kategoriler
    </a>
    <a href="<?= $baseUrl ?>/admin/orders.php" class="sidebar-link <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> Siparişler
    </a>
    <a href="<?= $baseUrl ?>/admin/coupons.php" class="sidebar-link <?= $currentPage === 'coupons' ? 'active' : '' ?>">
        <i class="bi bi-ticket-perforated"></i> Kuponlar
    </a>

    <div class="nav-section mt-3">Sistem</div>
    <a href="<?= $baseUrl ?>/admin/users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Kullanıcılar
    </a>
    <a href="<?= $baseUrl ?>/admin/audit.php" class="sidebar-link <?= $currentPage === 'audit' ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i> Denetim Kayıtları
    </a>
    <a href="<?= $baseUrl ?>/" class="sidebar-link">
        <i class="bi bi-shop"></i> Mağazayı Gör
    </a>
    <a href="<?= $baseUrl ?>/logout.php" class="sidebar-link">
        <i class="bi bi-box-arrow-left"></i> Çıkış
    </a>
</aside>

<!-- Content -->
<div class="admin-content">
    <button class="admin-menu-toggle mb-3"><i class="bi bi-list"></i></button>

    <!-- Flash Messages -->
    <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show glass-alert" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
