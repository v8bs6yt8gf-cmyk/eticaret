<?php
/**
 * Checkout
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$items = cart_items();
if (empty($items)) {
    flash('warning', 'Your cart is empty.');
    redirect('/cart.php');
}

$subtotal = cart_total();
$cartId   = get_cart_id();

// Get coupon
$coupon   = null;
$discount = 0;
if ($cartId) {
    $stmt = $pdo->prepare("SELECT c.coupon_id, cp.* FROM carts c LEFT JOIN coupons cp ON cp.id = c.coupon_id WHERE c.id = ?");
    $stmt->execute([$cartId]);
    $row = $stmt->fetch();
    if ($row && $row['coupon_id']) {
        $coupon = $row;
        $discount = $coupon['type'] === 'percent'
            ? $subtotal * ($coupon['value'] / 100)
            : min($coupon['value'], $subtotal);
    }
}

$shippingRate = (float)setting('shipping_flat_rate', '0');
$taxRate      = (float)setting('tax_rate', '0');
$tax          = $subtotal * ($taxRate / 100);
$total        = max(0, $subtotal - $discount + $shippingRate + $tax);

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([current_user_id()]);
$user = $stmt->fetch();

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/checkout.php'); }

    $shipName    = trim($_POST['shipping_name']    ?? '');
    $shipEmail   = trim($_POST['shipping_email']   ?? '');
    $shipPhone   = trim($_POST['shipping_phone']   ?? '');
    $shipAddress = trim($_POST['shipping_address'] ?? '');
    $shipCity    = trim($_POST['shipping_city']    ?? '');
    $shipZip     = trim($_POST['shipping_zip']     ?? '');
    $shipCountry = trim($_POST['shipping_country'] ?? '');
    $notes       = trim($_POST['notes']            ?? '');
    $payMethod   = $_POST['payment_method']        ?? 'cod';
    $allowedPayments = ['cod', 'bank_transfer'];

    $errors = [];
    if ($shipName === '')    $errors[] = 'Name is required.';
    if (!filter_var($shipEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($shipAddress === '') $errors[] = 'Address is required.';
    if ($shipCity === '')    $errors[] = 'City is required.';
    if (!in_array($payMethod, $allowedPayments, true)) $errors[] = 'Invalid payment method.';

    if ($errors) {
        foreach ($errors as $err) flash('warning', $err);
        redirect('/checkout.php');
    }

    try {
        $pdo->beginTransaction();

        $items = cart_items();
        if (empty($items)) {
            throw new Exception('Your cart is empty.');
        }

        $subtotal = 0.0;
        $lockedPrices = [];

        // Verify stock and recalculate prices inside the transaction
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT price, sale_price, stock, is_active FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$item['product_id']]);
            $p = $stmt->fetch();
            if (!$p || !$p['is_active'] || $p['stock'] < $item['quantity']) {
                throw new Exception("Not enough stock for: {$item['name']}");
            }
            $itemPrice = $p['sale_price'] ?: $p['price'];
            $lockedPrices[(int)$item['product_id']] = (float)$itemPrice;
            $subtotal += $itemPrice * $item['quantity'];
        }

        $coupon = null;
        $discount = 0.0;
        if ($cartId) {
            $stmt = $pdo->prepare("
                SELECT cp.*
                FROM carts c
                JOIN coupons cp ON cp.id = c.coupon_id
                WHERE c.id = ?
                  AND cp.is_active = 1
                  AND (cp.starts_at IS NULL OR cp.starts_at <= NOW())
                  AND (cp.expires_at IS NULL OR cp.expires_at >= NOW())
                  AND (cp.min_order IS NULL OR cp.min_order <= ?)
                  AND (cp.max_uses IS NULL OR cp.used_count < cp.max_uses)
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$cartId, $subtotal]);
            $coupon = $stmt->fetch();
            if ($coupon) {
                $discount = $coupon['type'] === 'percent'
                    ? $subtotal * ((float)$coupon['value'] / 100)
                    : min((float)$coupon['value'], $subtotal);
            }
        }

        $shippingRate = (float)setting('shipping_flat_rate', '0');
        $taxRate      = (float)setting('tax_rate', '0');
        $tax          = $subtotal * ($taxRate / 100);
        $total        = max(0, $subtotal - $discount + $shippingRate + $tax);

        // Create order
        $orderNumber = generate_order_number();
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, order_number, subtotal, discount, shipping, tax, total,
                               coupon_code, shipping_name, shipping_email, shipping_phone,
                               shipping_address, shipping_city, shipping_zip, shipping_country,
                               notes, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            current_user_id(), $orderNumber, $subtotal, $discount, $shippingRate, $tax, $total,
            $coupon ? $coupon['code'] : null,
            $shipName, $shipEmail, $shipPhone, $shipAddress, $shipCity, $shipZip, $shipCountry,
            $notes, $payMethod
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Create order items & reduce stock
        $stmtItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_image, price, quantity, total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        foreach ($items as $item) {
            $price     = $lockedPrices[(int)$item['product_id']] ?? (float)($item['sale_price'] ?: $item['price']);
            $lineTotal = $price * $item['quantity'];
            $stmtItem->execute([$orderId, $item['product_id'], $item['name'], $item['image'], $price, $item['quantity'], $lineTotal]);
            $stmtStock->execute([$item['quantity'], $item['product_id']]);
        }

        // Update coupon usage atomically (extra defence on top of FOR UPDATE)
        if ($coupon) {
            $upd = $pdo->prepare(
                "UPDATE coupons SET used_count = used_count + 1
                 WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)"
            );
            $upd->execute([$coupon['id']]);
            if ($upd->rowCount() === 0) {
                throw new RuntimeException('Bu kupon az önce kullanım limitine ulaştı. Lütfen tekrar deneyin.');
            }
        }

        // Clear cart
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
        $pdo->prepare("UPDATE carts SET coupon_id = NULL WHERE id = ?")->execute([$cartId]);

        $pdo->commit();

        audit_log('order.placed', 'order', $orderId, ['number' => $orderNumber, 'total' => $total]);

        flash('success', "Sipariş #{$orderNumber} başarıyla oluşturuldu!");
        redirect('/order.php?id=' . $orderId);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[checkout] ' . $e->getMessage());
        flash('danger', $e->getMessage());
        redirect('/checkout.php');
    }
}

$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <nav class="breadcrumb small"><a href="/">Home</a><span>/</span><a href="/cart.php">Cart</a><span>/</span><span>Checkout</span></nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="eyebrow">Secure checkout</span>
                <h1 class="section-title mb-1">Complete your order</h1>
                <p class="text-secondary mb-0">Shipping, payment, and confirmation in one focused flow.</p>
            </div>
            <div class="progress-indicator glass-card px-3 py-2">
                <span class="progress-step active"><span class="progress-number">1</span><span class="progress-label">Cart</span></span>
                <span class="progress-line"></span>
                <span class="progress-step active"><span class="progress-number">2</span><span class="progress-label">Details</span></span>
                <span class="progress-line"></span>
                <span class="progress-step"><span class="progress-number">3</span><span class="progress-label">Done</span></span>
            </div>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5">

    <form method="POST" class="form-glass">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Shipping Details -->
            <div class="col-lg-7 reveal">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-truck me-2"></i>Shipping Details</h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="shipping_name" class="form-control" required
                                   value="<?= e($user['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="shipping_email" class="form-control" required
                                   value="<?= e($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="shipping_phone" class="form-control"
                                   value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" name="shipping_country" class="form-control"
                                   value="<?= e($user['country'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="shipping_address" class="form-control" rows="2" required><?= e($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="shipping_city" class="form-control" required
                                   value="<?= e($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" name="shipping_zip" class="form-control"
                                   value="<?= e($user['zip_code'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Order Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Special requests..."></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold mt-4 mb-3"><i class="bi bi-credit-card me-2"></i>Payment</h5>
                    <div class="glass-card p-3">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" value="cod" id="payCod" checked>
                            <label class="form-check-label fw-bold" for="payCod">Cash on Delivery</label>
                            <div class="small text-secondary">Pay when your order arrives.</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="bank_transfer" id="payBank">
                            <label class="form-check-label fw-bold" for="payBank">Bank Transfer</label>
                            <div class="small text-secondary">Transfer details will be shared after the order.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-5 reveal">
                <div class="glass-card order-summary">
                    <h5 class="fw-bold mb-4">Order Summary</h5>

                    <?php foreach ($items as $item):
                        $price = $item['sale_price'] ?: $item['price'];
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:0.9rem;">
                            <div>
                                <span style="color:var(--text-primary)"><?= e($item['name']) ?></span>
                                <span style="color:var(--text-muted)"> x<?= (int)$item['quantity'] ?></span>
                            </div>
                            <span style="font-weight:600;"><?= format_price($price * $item['quantity']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <hr style="border-color:var(--glass-border)">

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?= format_price($subtotal) ?></span>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="summary-row" style="color:var(--success)">
                            <span>Discount</span>
                            <span>-<?= format_price($discount) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($shippingRate > 0): ?>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span><?= format_price($shippingRate) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($tax > 0): ?>
                        <div class="summary-row">
                            <span>Tax</span>
                            <span><?= format_price($tax) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?= format_price($total) ?></span>
                    </div>

                    <button type="submit" class="btn-gradient w-100 py-3 mt-3">
                        <i class="bi bi-check-circle me-2"></i>Place Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
