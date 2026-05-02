<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirect('/account.php');

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    flash('danger', 'Order not found.');
    redirect('/account.php');
}

$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$pageTitle = 'Order #' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <nav class="breadcrumb small"><a href="/account.php">My Account</a><span>/</span><span>Order #<?= e($order['order_number']) ?></span></nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="eyebrow">Order details</span>
                <h1 class="section-title mb-1">Order <span class="text-gradient">#<?= e($order['order_number']) ?></span></h1>
                <p class="text-secondary mb-0">Placed <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
            </div>
            <span class="order-status <?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5">
    <div class="row g-4">
        <!-- Items -->
        <div class="col-lg-8 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3">Items</h5>
                <div style="overflow-x:auto;">
                    <table class="table-glass">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($item['product_image']): ?>
                                                <img src="/<?= e($item['product_image']) ?>" class="cart-item-img" alt="">
                                            <?php endif; ?>
                                            <span style="color:var(--text-primary); font-weight:500;"><?= e($item['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= format_price($item['price']) ?></td>
                                    <td><?= (int)$item['quantity'] ?></td>
                                    <td style="font-weight:600;"><?= format_price($item['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary & Shipping -->
        <div class="col-lg-4 reveal">
            <div class="glass-card order-summary mb-4">
                <h5 class="fw-bold mb-3">Summary</h5>

                <div class="summary-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="summary-row" style="color:var(--success)">
                        <span>Discount<?= $order['coupon_code'] ? ' ('.e($order['coupon_code']).')' : '' ?></span>
                        <span>-<?= format_price($order['discount']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($order['shipping'] > 0): ?>
                    <div class="summary-row"><span>Shipping</span><span><?= format_price($order['shipping']) ?></span></div>
                <?php endif; ?>
                <?php if ($order['tax'] > 0): ?>
                    <div class="summary-row"><span>Tax</span><span><?= format_price($order['tax']) ?></span></div>
                <?php endif; ?>
                <div class="summary-row total"><span>Total</span><span><?= format_price($order['total']) ?></span></div>

                <div class="mt-3" style="font-size:0.85rem; color:var(--text-muted);">
                    <div><strong>Payment:</strong> <?= e(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></div>
                    <div><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></div>
                </div>
            </div>

            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-truck me-2"></i>Shipping</h5>
                <div style="font-size:0.9rem; color:var(--text-secondary); line-height:1.8;">
                    <strong><?= e($order['shipping_name']) ?></strong><br>
                    <?= e($order['shipping_email']) ?><br>
                    <?php if ($order['shipping_phone']): ?><?= e($order['shipping_phone']) ?><br><?php endif; ?>
                    <?= nl2br(e($order['shipping_address'])) ?><br>
                    <?= e($order['shipping_city']) ?> <?= e($order['shipping_zip']) ?><br>
                    <?= e($order['shipping_country']) ?>
                </div>
                <?php if ($order['notes']): ?>
                    <div class="mt-3 p-2" style="background:var(--bg-glass); border-radius:var(--radius-sm); font-size:0.85rem;">
                        <strong>Notes:</strong> <?= e($order['notes']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
