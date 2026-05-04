<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Stats
$totalProducts  = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders    = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelled','refunded')")->fetchColumn();
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$pendingOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$lowStock       = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND stock > 0 AND is_active=1")->fetchColumn();

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.*, u.name AS user_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 10
")->fetchAll();

// Setup safety reminders
$installerStillPresent = is_file(__DIR__ . '/../kurulum.php') || is_file(__DIR__ . '/../kurulum_test.php');

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-title">Dashboard</h1>
    <span style="color:var(--text-muted); font-size:0.85rem;"><?= date('l, d F Y') ?></span>
</div>

<?php if ($installerStillPresent): ?>
    <div class="glass-alert alert alert-warning reveal mb-4">
        <i class="bi bi-shield-exclamation me-2"></i>
        <strong>Güvenlik:</strong> Üretim ortamında <code>kurulum.php</code> ve/veya <code>kurulum_test.php</code> hâlâ mevcut. Bunları silin veya web sunucusundan engelleyin.
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="glass-card stat-card reveal">
            <div class="stat-icon" style="background:rgba(124,92,255,0.15); color:var(--accent-light);">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value"><?= format_price($totalRevenue) ?></div>
            <div class="stat-label">Toplam Gelir</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="glass-card stat-card reveal">
            <div class="stat-icon" style="background:rgba(6,214,160,0.15); color:var(--accent-2);">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-value"><?= $totalOrders ?></div>
            <div class="stat-label">Toplam Sipariş</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="glass-card stat-card reveal">
            <div class="stat-icon" style="background:rgba(255,190,11,0.15); color:var(--warning);">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?= $totalProducts ?></div>
            <div class="stat-label">Ürün</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="glass-card stat-card reveal">
            <div class="stat-icon" style="background:rgba(255,77,106,0.15); color:var(--danger);">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Müşteri</div>
        </div>
    </div>
</div>

<!-- Alerts -->
<div class="row g-4 mb-4">
    <?php if ($pendingOrders > 0): ?>
        <div class="col-md-6">
            <div class="glass-alert alert alert-warning reveal">
                <i class="bi bi-clock-history me-2"></i>
                <strong><?= $pendingOrders ?></strong> bekleyen sipariş var.
                <a href="/admin/orders.php?status=pending" class="ms-2">Görüntüle</a>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($lowStock > 0): ?>
        <div class="col-md-6">
            <div class="glass-alert alert alert-danger reveal">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong><?= $lowStock ?></strong> ürün düşük stokta.
                <a href="/admin/products.php" class="ms-2">Görüntüle</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Orders -->
<div class="glass-card p-4 reveal">
    <h5 style="font-weight:700; margin-bottom:1.5rem;">Son Siparişler</h5>

    <?php if (empty($recentOrders)): ?>
        <div class="empty-state">
            <i class="bi bi-receipt"></i>
            <h3>Henüz Sipariş Yok</h3>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Sipariş</th>
                        <th>Müşteri</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--text-primary);">#<?= e($o['order_number']) ?></td>
                            <td><?= e($o['user_name'] ?? 'Misafir') ?></td>
                            <td style="font-weight:600;"><?= format_price($o['total']) ?></td>
                            <td><span class="order-status <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td style="font-size:0.85rem;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                            <td><a href="/admin/orders.php?action=view&id=<?= (int)$o['id'] ?>" class="btn-sm-gradient">Görüntüle</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
