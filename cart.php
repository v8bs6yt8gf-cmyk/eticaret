<?php
/**
 * Shopping Cart
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$items    = cart_items();
$subtotal = cart_total();

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/cart.php'); }

    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty    = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
        $cartId = get_cart_id();

        if ($cartId && $itemId) {
            $stmt = $pdo->prepare("
                UPDATE cart_items ci
                JOIN products p ON p.id = ci.product_id
                SET ci.quantity = LEAST(?, GREATEST(p.stock, 1))
                WHERE ci.id = ? AND ci.cart_id = ?
            ");
            $stmt->execute([$qty, $itemId, $cartId]);
            flash('success', 'Sepet güncellendi.');
        }
    }

    if ($action === 'remove') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $cartId = get_cart_id();
        if ($cartId && $itemId) {
            $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?")->execute([$itemId, $cartId]);
            flash('success', 'Ürün sepetten kaldırıldı.');
        }
    }

    if ($action === 'apply_coupon') {
        $code   = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $cartId = get_cart_id();

        // Recompute subtotal in this request to avoid stale state
        $currentSubtotal = cart_total();

        if ($cartId && $code !== '' && mb_strlen($code) <= 50) {
            $stmt = $pdo->prepare("
                SELECT * FROM coupons
                WHERE code = ? AND is_active = 1
                  AND (starts_at IS NULL OR starts_at <= NOW())
                  AND (expires_at IS NULL OR expires_at >= NOW())
                  AND (min_order IS NULL OR min_order <= ?)
                  AND (max_uses IS NULL OR used_count < max_uses)
                LIMIT 1
            ");
            $stmt->execute([$code, $currentSubtotal]);
            $coupon = $stmt->fetch();

            if ($coupon) {
                $pdo->prepare("UPDATE carts SET coupon_id = ? WHERE id = ?")->execute([$coupon['id'], $cartId]);
                flash('success', 'Kupon uygulandı!');
            } else {
                flash('warning', 'Geçersiz veya süresi dolmuş kupon kodu.');
            }
        } elseif ($code === '' && $cartId) {
            $pdo->prepare("UPDATE carts SET coupon_id = NULL WHERE id = ?")->execute([$cartId]);
            flash('success', 'Kupon kaldırıldı.');
        }
    }

    redirect('/cart.php');
}

// Get coupon if applied
$coupon   = null;
$discount = 0;
$cartId   = get_cart_id();
if ($cartId) {
    $stmt = $pdo->prepare("SELECT c.coupon_id, cp.* FROM carts c LEFT JOIN coupons cp ON cp.id = c.coupon_id WHERE c.id = ?");
    $stmt->execute([$cartId]);
    $row = $stmt->fetch();
    if ($row && $row['coupon_id']) {
        $coupon = $row;
        if ($coupon['type'] === 'percent') {
            $discount = $subtotal * ($coupon['value'] / 100);
        } else {
            $discount = min($coupon['value'], $subtotal);
        }
    }
}

$total = max(0, $subtotal - $discount);

$pageTitle = 'Cart';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <nav class="breadcrumb small"><a href="/">Home</a><span>/</span><span>Cart</span></nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="eyebrow">Shopping bag</span>
                <h1 class="section-title mb-1">Review your <span class="text-gradient">cart</span></h1>
                <p class="text-secondary mb-0"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> ready for checkout.</p>
            </div>
            <?php if (!empty($items)): ?><a href="/products.php" class="btn-glass"><i class="bi bi-arrow-left"></i>Continue Shopping</a><?php endif; ?>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5">

    <?php if (empty($items)): ?>
        <div class="empty-state glass-card reveal">
            <i class="bi bi-bag"></i>
            <h3>Your Cart is Empty</h3>
            <p>Looks like you haven't added anything yet.</p>
            <a href="/products.php" class="btn-gradient mt-3">Browse Products</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Cart Items -->
            <div class="col-lg-8 reveal">
                <div class="glass-card p-0" style="overflow-x:auto;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item):
                                $price = $item['sale_price'] ?: $item['price'];
                                $lineTotal = $price * $item['quantity'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($item['image']): ?>
                                                <img src="/<?= e($item['image']) ?>" class="cart-item-img" alt="">
                                            <?php endif; ?>
                                            <div>
                                                <a href="/product.php?slug=<?= e($item['slug']) ?>" style="color:var(--text-primary); font-weight:500;">
                                                    <?= e($item['name']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= format_price($price) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <div class="qty-control" style="transform:scale(0.85);">
                                                <button type="button" data-action="minus"><i class="bi bi-dash"></i></button>
                                                <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>"
                                                       min="1" max="<?= (int)$item['stock'] ?>"
                                                       onchange="this.form.submit()">
                                                <button type="button" data-action="plus"><i class="bi bi-plus"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td style="font-weight:600;"><?= format_price($lineTotal) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn-danger-glass btn-sm" data-confirm="Remove this item?">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4 reveal">
                <div class="glass-card order-summary">
                    <h5 class="fw-bold mb-4">Order Summary</h5>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?= format_price($subtotal) ?></span>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="summary-row" style="color:var(--success)">
                            <span>Discount (<?= e($coupon['code']) ?>)</span>
                            <span>-<?= format_price($discount) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?= format_price($total) ?></span>
                    </div>

                    <!-- Coupon -->
                    <form method="POST" class="form-glass mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply_coupon">
                        <div class="input-group">
                            <input type="text" name="coupon_code" class="form-control" placeholder="Coupon code"
                                   style="font-size:0.85rem;"
                                   value="<?= $coupon ? e($coupon['code']) : '' ?>">
                            <button class="btn-sm-gradient" type="submit">Apply</button>
                        </div>
                    </form>

                    <a href="/checkout.php" class="btn-gradient w-100 py-3 d-block text-center mt-3">
                        <i class="bi bi-credit-card me-2"></i>Checkout
                    </a>
                    <a href="/products.php" class="btn-glass w-100 d-block text-center mt-2">Continue Shopping</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
