<?php
/**
 * Product Detail
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { redirect('/products.php'); }

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.slug = ? AND p.is_active = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container py-5"><div class="empty-state glass-card"><i class="bi bi-emoji-frown"></i><h3>Product Not Found</h3><p>The product you\'re looking for doesn\'t exist.</p><a href="/products.php" class="btn-gradient mt-3">Browse Products</a></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Increment view count (throttled per session per product to prevent spam)
$viewKey = 'viewed_product_' . (int)$product['id'];
if (empty($_SESSION[$viewKey]) || (time() - (int)$_SESSION[$viewKey]) > 3600) {
    $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product['id']]);
    $_SESSION[$viewKey] = time();
}

// Build a known-safe self-redirect target
$selfUrl = '/product.php?slug=' . urlencode($slug);

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect($selfUrl); }

    $qty = max(1, min(99, (int)($_POST['quantity'] ?? 1)));

    if ((int)$product['stock'] < $qty) {
        flash('warning', 'Yeterli stok yok.');
    } else {
        $cartId = get_or_create_cart();
        // Check if already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cartId, $product['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newQty = min((int)$product['stock'], (int)$existing['quantity'] + $qty);
            $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?")->execute([$newQty, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)")
                ->execute([$cartId, $product['id'], $qty]);
        }

        flash('success', e($product['name']) . ' sepete eklendi!');
    }
    redirect($selfUrl);
}

$effectivePrice = $product['sale_price'] ?: $product['price'];
$hasDiscount    = $product['sale_price'] && $product['sale_price'] < $product['price'];

// Related products
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 AND p.id != ? AND p.category_id = ?
    ORDER BY RAND() LIMIT 4
");
$stmt->execute([$product['id'], $product['category_id']]);
$related = $stmt->fetchAll();

$pageTitle = $product['name'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="container py-4 py-lg-5">
    <nav class="breadcrumb small mb-4">
        <a href="/">Home</a><span>/</span><a href="/products.php">Products</a>
        <?php if ($product['category_name']): ?><span>/</span><a href="/products.php?category=<?= (int)$product['category_id'] ?>"><?= e($product['category_name']) ?></a><?php endif; ?>
        <span>/</span><span><?= e($product['name']) ?></span>
    </nav>
    <div class="row g-5 align-items-start">
        <div class="col-lg-6">
            <div class="product-gallery card border-0 shadow-sm">
                <?php if ($product['image']): ?><img src="/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="product-detail-photo"><?php else: ?><div class="product-placeholder large"><i class="bi bi-image"></i></div><?php endif; ?>
            </div>
            <div class="product-thumb-grid mt-3">
                <button type="button" class="product-thumb active" aria-label="Product image">
                    <?php if ($product['image']): ?><img src="/<?= e($product['image']) ?>" alt=""><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                </button>
                <div class="product-thumb product-thumb-feature"><i class="bi bi-shield-check"></i><span>Verified quality</span></div>
                <div class="product-thumb product-thumb-feature"><i class="bi bi-truck"></i><span>Fast delivery</span></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="product-detail-panel">
                <?php if ($product['category_name']): ?><div class="product-category mb-2"><?= e($product['category_name']) ?></div><?php endif; ?>
                <h1 class="display-title mb-3"><?= e($product['name']) ?></h1>
                <div class="d-flex align-items-center gap-3 mb-3"><span class="detail-price"><?= format_price((float)$effectivePrice) ?></span><?php if ($hasDiscount): ?><span class="detail-original-price"><?= format_price((float)$product['price']) ?></span><span class="badge text-bg-danger"><?= round((1 - $product['sale_price'] / $product['price']) * 100) ?>% OFF</span><?php endif; ?></div>
                <?php if ($product['sku']): ?><p class="text-secondary small mb-3">SKU: <?= e($product['sku']) ?></p><?php endif; ?>
                <div class="mb-4"><?php if ($product['stock'] <= 0): ?><span class="order-status cancelled">Out of Stock</span><?php elseif ($product['stock'] <= 5): ?><span class="order-status pending">Only <?= (int)$product['stock'] ?> left</span><?php else: ?><span class="order-status delivered">In Stock</span><?php endif; ?></div>
                <?php if ($product['description']): ?><div class="product-description mb-4"><?= nl2br(e($product['description'])) ?></div><?php endif; ?>
                <div class="trust-strip product-trust mb-4"><span><i class="bi bi-shield-check"></i> Secure checkout</span><span><i class="bi bi-truck"></i> Fast delivery</span><span><i class="bi bi-arrow-repeat"></i> Easy return</span></div>
                <?php if ($product['stock'] > 0): ?>
                    <form method="POST" class="add-to-cart-form card border-0 shadow-sm p-3 p-md-4">
                        <?= csrf_field() ?><input type="hidden" name="add_to_cart" value="1">
                        <label class="form-label fw-bold">Quantity</label>
                        <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                            <div class="qty-control"><button type="button" data-action="minus" aria-label="Decrease"><i class="bi bi-dash"></i></button><input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>"><button type="button" data-action="plus" aria-label="Increase"><i class="bi bi-plus"></i></button></div>
                            <button type="submit" class="btn btn-primary btn-lg flex-fill"><i class="bi bi-bag-plus me-2"></i>Add to Cart</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php if ($related): ?>
<section class="container mt-section mb-5"><div class="section-header"><div><span class="eyebrow">You may also like</span><h2 class="section-title">Related products</h2></div></div><div class="row g-4"><?php foreach ($related as $prod): ?><div class="col-6 col-md-3"><?php include __DIR__ . '/includes/_product_card.php'; ?></div><?php endforeach; ?></div></section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
