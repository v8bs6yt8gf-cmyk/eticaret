<?php
require_once __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? 'list';
$allowedStatuses = ['pending','processing','shipped','delivered','cancelled','refunded'];

// State machine: which transitions are allowed
$transitions = [
    'pending'    => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped'    => ['delivered', 'refunded'],
    'delivered'  => ['refunded'],
    'cancelled'  => [],
    'refunded'   => [],
];

// ─── Update Status ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/orders.php'); }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = (string)($_POST['status'] ?? '');

    if (!in_array($status, $allowedStatuses, true)) {
        flash('warning', 'Geçersiz durum.');
        redirect('/admin/orders.php?action=view&id=' . $orderId);
    }

    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $currentStatus = $stmt->fetchColumn();

    if ($currentStatus === false) {
        flash('danger', 'Sipariş bulunamadı.');
        redirect('/admin/orders.php');
    }

    if ($currentStatus !== $status && !in_array($status, $transitions[$currentStatus] ?? [], true)) {
        flash('warning', 'Bu durumdan "' . htmlspecialchars($status) . '" durumuna geçilemez.');
        redirect('/admin/orders.php?action=view&id=' . $orderId);
    }

    try {
        $pdo->prepare(
            "UPDATE orders SET status = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ?"
        )->execute([$status, current_user_id(), $orderId]);
    } catch (PDOException $e) {
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
    }

    audit_log('admin.order.status_changed', 'order', $orderId, ['from' => $currentStatus, 'to' => $status]);
    flash('success', 'Sipariş durumu güncellendi.');
    redirect('/admin/orders.php?action=view&id=' . $orderId);
}

// ─── VIEW ORDER ───────────────────────────────────────
$order = null;
$items = [];
if ($action === 'view' && isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) { flash('danger', 'Sipariş bulunamadı.'); redirect('/admin/orders.php'); }

    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
}

// ─── LIST ─────────────────────────────────────────────
$listData = null;
if ($action === 'list') {
    $statusFilter = $_GET['status'] ?? '';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $where  = '1=1';
    $params = [];
    if ($statusFilter && in_array($statusFilter, $allowedStatuses, true)) {
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
    $listData = ['orders' => $stmt->fetchAll(), 'pag' => $pag, 'total' => $total, 'statusFilter' => $statusFilter];
}

$pageTitle = 'Siparişler';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'view' && $order): ?>

<div class="admin-topbar">
    <div>
        <a href="/admin/orders.php" style="color:var(--text-muted); font-size:0.85rem;"><i class="bi bi-arrow-left me-1"></i>Siparişlere Dön</a>
        <h1 class="admin-title mt-1">Sipariş #<?= e($order['order_number']) ?></h1>
    </div>
    <span class="order-status <?= e($order['status']) ?>" style="font-size:0.85rem;"><?= e(ucfirst($order['status'])) ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-8 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Sipariş Kalemleri</h5>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr><th>Ürün</th><th>Fiyat</th><th>Adet</th><th>Toplam</th></tr>
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
            <h5 style="font-weight:700; margin-bottom:1rem;">Özet</h5>
            <div class="summary-row"><span>Ara Toplam</span><span><?= format_price($order['subtotal']) ?></span></div>
            <?php if ($order['discount'] > 0): ?>
                <div class="summary-row" style="color:var(--success)"><span>İndirim</span><span>-<?= format_price($order['discount']) ?></span></div>
            <?php endif; ?>
            <?php if ($order['shipping'] > 0): ?>
                <div class="summary-row"><span>Kargo</span><span><?= format_price($order['shipping']) ?></span></div>
            <?php endif; ?>
            <?php if ($order['tax'] > 0): ?>
                <div class="summary-row"><span>Vergi</span><span><?= format_price($order['tax']) ?></span></div>
            <?php endif; ?>
            <div class="summary-row total"><span>Toplam</span><span><?= format_price($order['total']) ?></span></div>

            <div class="mt-3" style="font-size:0.85rem; color:var(--text-muted);">
                <div><strong>Ödeme:</strong> <?= e(ucfirst(str_replace('_',' ',$order['payment_method']))) ?></div>
                <div><strong>Müşteri:</strong> <?= e($order['user_name'] ?? 'Misafir') ?></div>
                <div><strong>Tarih:</strong> <?= date('d M Y H:i', strtotime($order['created_at'])) ?></div>
            </div>
        </div>

        <!-- Shipping -->
        <div class="glass-card p-4 mb-4">
            <h5 style="font-weight:700; margin-bottom:1rem;">Kargo</h5>
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
            <h5 style="font-weight:700; margin-bottom:1rem;">Durumu Güncelle</h5>
            <?php
                $current = $order['status'];
                $next    = $transitions[$current] ?? [];
            ?>
            <?php if (empty($next)): ?>
                <p class="text-secondary mb-0" style="font-size:0.9rem;">Bu sipariş kapalı; durum değişikliği yok.</p>
            <?php else: ?>
                <form method="POST" class="form-glass">
                    <?= csrf_field() ?>
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <select name="status" class="form-select mb-3">
                        <?php foreach ($next as $s): ?>
                            <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-gradient w-100">Güncelle</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: // LIST ?>

<div class="admin-topbar">
    <h1 class="admin-title">Siparişler <span style="color:var(--text-muted); font-size:1rem;">(<?= (int)$listData['total'] ?>)</span></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/orders.php" class="btn-glass <?= !$listData['statusFilter'] ? 'active' : '' ?>" style="padding:0.4rem 0.8rem; font-size:0.8rem;">Tümü</a>
        <?php foreach (['pending','processing','shipped','delivered'] as $s): ?>
            <a href="/admin/orders.php?status=<?= $s ?>" class="btn-glass <?= $listData['statusFilter'] === $s ? 'active' : '' ?>" style="padding:0.4rem 0.8rem; font-size:0.8rem;"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Sipariş</th>
                <th>Müşteri</th>
                <th>Toplam</th>
                <th>Durum</th>
                <th>Ödeme</th>
                <th>Tarih</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listData['orders'] as $o): ?>
                <tr>
                    <td style="font-weight:600; color:var(--text-primary);">#<?= e($o['order_number']) ?></td>
                    <td><?= e($o['user_name'] ?? 'Misafir') ?></td>
                    <td style="font-weight:600;"><?= format_price($o['total']) ?></td>
                    <td><span class="order-status <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                    <td style="font-size:0.85rem;"><?= e(ucfirst(str_replace('_',' ',$o['payment_method']))) ?></td>
                    <td style="font-size:0.85rem;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                    <td><a href="/admin/orders.php?action=view&id=<?= (int)$o['id'] ?>" class="btn-sm-gradient">Görüntüle</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($listData['orders'])): ?>
                <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted);">Sipariş bulunamadı.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($listData['pag']['total_pages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-glass mb-0">
            <?php for ($i = 1; $i <= $listData['pag']['total_pages']; $i++):
                $qs = http_build_query(array_merge($_GET, ['page' => $i]));
            ?>
                <li class="page-item <?= $i === $listData['pag']['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
