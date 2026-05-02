<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';

// ─── Update Status ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/orders.php'); }

    $orderId = (int)$_POST['order_id'];
    $status  = $_POST['status'];
    $allowed = ['pending','processing','shipped','delivered','cancelled','refunded'];

    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
        flash('success', 'Order status updated.');
    }
    redirect('/admin/orders.php?action=view&id=' . $orderId);
}

// ─── VIEW ORDER ───────────────────────────────────────
if ($action === 'view' && isset($_GET['id'])):
    $orderId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) { flash('danger', 'Order not found.'); redirect('/admin/orders.php'); }

    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
?>

<div class="admin-topbar">
    <div>
        <a href="/admin/orders.php" style="color:var(--text-muted); font-size:0.85rem;"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
        <h1 class="admin-title mt-1">Order #<?= e($order['order_number']) ?></h1>
    </div>
    <span class="order-status <?= e($order['status']) ?>" style="font-size:0.85rem;"><?= e(ucfirst($order['status'])) ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-8 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Order Items</h5>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td style="color:var(--text-primary); font-weight:500;"><?= e($item['product_name']) ?></td>
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

    <div class="col-lg-4 reveal">
        <!-- Summary -->
        <div class="glass-card order-summary mb-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Summary</h5>
            <div class="summary-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
            <?php if ($order['discount'] > 0): ?>
                <div class="summary-row" style="color:var(--success)"><span>Discount</span><span>-<?= format_price($order['discount']) ?></span></div>
            <?php endif; ?>
            <?php if ($order['shipping'] > 0): ?>
                <div class="summary-row"><span>Shipping</span><span><?= format_price($order['shipping']) ?></span></div>
            <?php endif; ?>
            <?php if ($order['tax'] > 0): ?>
                <div class="summary-row"><span>Tax</span><span><?= format_price($order['tax']) ?></span></div>
            <?php endif; ?>
            <div class="summary-row total"><span>Total</span><span><?= format_price($order['total']) ?></span></div>

            <div class="mt-3" style="font-size:0.85rem; color:var(--text-muted);">
                <div><strong>Payment:</strong> <?= e(ucfirst(str_replace('_',' ',$order['payment_method']))) ?></div>
                <div><strong>Customer:</strong> <?= e($order['user_name'] ?? 'Guest') ?></div>
                <div><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></div>
            </div>
        </div>

        <!-- Shipping -->
        <div class="glass-card p-4 mb-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Shipping</h5>
            <div style="font-size:0.9rem; color:var(--text-secondary); line-height:1.8;">
                <strong><?= e($order['shipping_name']) ?></strong><br>
                <?= e($order['shipping_email']) ?><br>
                <?php if ($order['shipping_phone']): ?><?= e($order['shipping_phone']) ?><br><?php endif; ?>
                <?= nl2br(e($order['shipping_address'])) ?><br>
                <?= e($order['shipping_city']) ?> <?= e($order['shipping_zip']) ?><br>
                <?= e($order['shipping_country']) ?>
            </div>
        </div>

        <!-- Update Status -->
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Update Status</h5>
            <form method="POST" class="form-glass">
                <?= csrf_field() ?>
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <select name="status" class="form-select mb-3">
                    <?php foreach (['pending','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                        <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-gradient w-100">Update</button>
            </form>
        </div>
    </div>
</div>

<?php else: // LIST ?>

<?php
$statusFilter = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = '1=1';
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending','processing','shipped','delivered','cancelled','refunded'])) {
    $where = 'o.status = ?';
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name
    FROM orders o LEFT JOIN users u ON u.id = o.user_id
    WHERE {$where}
    ORDER BY o.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<div class="admin-topbar">
    <h1 class="admin-title">Orders <span style="color:var(--text-muted); font-size:1rem;">(<?= $total ?>)</span></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/orders.php" class="btn-glass <?= !$statusFilter ? 'active' : '' ?>" style="padding:0.4rem 0.8rem; font-size:0.8rem;">All</a>
        <?php foreach (['pending','processing','shipped','delivered'] as $s): ?>
            <a href="/admin/orders.php?status=<?= $s ?>" class="btn-glass <?= $statusFilter === $s ? 'active' : '' ?>" style="padding:0.4rem 0.8rem; font-size:0.8rem;"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td style="font-weight:600; color:var(--text-primary);">#<?= e($o['order_number']) ?></td>
                    <td><?= e($o['user_name'] ?? 'Guest') ?></td>
                    <td style="font-weight:600;"><?= format_price($o['total']) ?></td>
                    <td><span class="order-status <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                    <td style="font-size:0.85rem;"><?= e(ucfirst(str_replace('_',' ',$o['payment_method']))) ?></td>
                    <td style="font-size:0.85rem;"><?= date('M d, H:i', strtotime($o['created_at'])) ?></td>
                    <td><a href="/admin/orders.php?action=view&id=<?= (int)$o['id'] ?>" class="btn-sm-gradient">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted);">No orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pag['total_pages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-glass mb-0">
            <?php for ($i = 1; $i <= $pag['total_pages']; $i++):
                $qs = http_build_query(array_merge($_GET, ['page' => $i]));
            ?>
                <li class="page-item <?= $i === $pag['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
