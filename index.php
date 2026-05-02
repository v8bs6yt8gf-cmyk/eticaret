<?php
/** Homepage */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 8");
$stmt->execute();
$featured = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 8");
$stmt->execute();
$latest = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name LIMIT 6")->fetchAll();
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow"><i class="bi bi-stars me-1"></i> Premium ecommerce experience</span>
                <h1 class="hero-title mt-3">Discover <span class="text-gradient">premium products</span> selected for modern living.</h1>
                <p class="hero-subtitle">Curated collections, limited deals, and a fast checkout experience designed to help you find the right product with confidence.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    <a href="/products.php" class="btn-gradient btn-lg"><i class="bi bi-bag me-2"></i>Shop Now</a>
                    <a href="#featured" class="btn-glass btn-lg"><i class="bi bi-stars me-2"></i>View Featured</a>
                </div>
                <div class="trust-strip mt-4">
                    <span><i class="bi bi-shield-check"></i> Secure checkout</span>
                    <span><i class="bi bi-arrow-repeat"></i> Easy returns</span>
                    <span><i class="bi bi-truck"></i> Fast delivery</span>
                </div>
                <div class="row g-3 mt-4">
                    <div class="col-4"><div class="glass-card p-3"><strong class="d-block fs-4"><?= count($categories) ?>+</strong><span class="small text-secondary">Collections</span></div></div>
                    <div class="col-4"><div class="glass-card p-3"><strong class="d-block fs-4"><?= count($latest) ?>+</strong><span class="small text-secondary">New drops</span></div></div>
                    <div class="col-4"><div class="glass-card p-3"><strong class="d-block fs-4">24/7</strong><span class="small text-secondary">Support</span></div></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-panel card border-0 shadow-sm reveal">
                    <div class="card-body p-4">
                        <div class="hero-product-placeholder"><i class="bi bi-bag-heart"></i></div>
                        <div class="d-flex justify-content-between gap-3 align-items-end mt-4">
                            <div>
                                <h2 class="h4 fw-bold mb-2">Fresh picks, ready to ship</h2>
                                <p class="text-secondary mb-0">A refined storefront built for browsing, confidence, and conversion.</p>
                            </div>
                            <span class="order-status delivered">Live</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($categories): ?>
<section class="container mt-section">
    <div class="section-header">
        <div><span class="eyebrow">Collections</span><h2 class="section-title">Shop by category</h2></div>
        <a href="/products.php" class="link-strong">All products <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="bento-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="/products.php?category=<?= (int)$cat['id'] ?>" class="category-card card border-0 shadow-sm">
                <div class="card-body">
                    <span class="category-icon"><i class="bi bi-grid"></i></span>
                    <span class="bento-title d-block mt-3"><?= e($cat['name']) ?></span>
                    <?php if (!empty($cat['description'])): ?><small class="text-secondary"><?= e($cat['description']) ?></small><?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section id="featured" class="container mt-section">
    <div class="section-header"><div><span class="eyebrow">Featured</span><h2 class="section-title">Editor picks</h2></div></div>
    <?php if (empty($featured)): ?>
        <div class="empty-state card border-0 shadow-sm"><i class="bi bi-stars"></i><h3>No featured products yet</h3><p>Featured products will appear here once they are selected.</p></div>
    <?php else: ?>
        <div class="row g-4"><?php foreach ($featured as $prod): ?><div class="col-6 col-md-4 col-lg-3"><?php include __DIR__ . '/includes/_product_card.php'; ?></div><?php endforeach; ?></div>
    <?php endif; ?>
</section>

<section class="container mt-section mb-5">
    <div class="section-header"><div><span class="eyebrow">New arrivals</span><h2 class="section-title">Recently added</h2></div><a href="/products.php" class="btn btn-outline-primary">View all</a></div>
    <?php if (empty($latest)): ?>
        <div class="empty-state card border-0 shadow-sm"><i class="bi bi-bag"></i><h3>No products yet</h3><p>Products will appear here once added.</p></div>
    <?php else: ?>
        <div class="row g-4"><?php foreach ($latest as $prod): ?><div class="col-6 col-md-4 col-lg-3"><?php include __DIR__ . '/includes/_product_card.php'; ?></div><?php endforeach; ?></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
