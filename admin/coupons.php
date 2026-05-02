<?php
$pageTitle = 'Coupons';
require_once __DIR__ . '/includes/header.php';

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/coupons.php'); }
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('success', 'Coupon deleted.');
    redirect('/admin/coupons.php');
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/coupons.php'); }

    $id        = (int)($_POST['id'] ?? 0);
    $code      = strtoupper(trim($_POST['code'] ?? ''));
    $type      = $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = ($_POST['min_order'] !== '') ? (float)$_POST['min_order'] : null;
    $maxUses   = ($_POST['max_uses'] !== '') ? (int)$_POST['max_uses'] : null;
    $startsAt  = $_POST['starts_at'] ?: null;
    $expiresAt = $_POST['expires_at'] ?: null;
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($code === '') {
        flash('warning', 'Coupon code is required.');
    } elseif ($value <= 0) {
        flash('warning', 'Value must be greater than 0.');
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, starts_at=?, expires_at=?, is_active=? WHERE id=?");
            $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt, $isActive, $id]);
            flash('success', 'Coupon updated.');
        } else {
            $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
            $check->execute([$code]);
            if ($check->fetch()) {
                flash('warning', 'Coupon code already exists.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO coupons (code, type, value, min_order, max_uses, starts_at, expires_at, is_active) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt, $isActive]);
                flash('success', 'Coupon created.');
            }
        }
        redirect('/admin/coupons.php');
    }
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

$editCoupon = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editCoupon = $stmt->fetch();
}
?>

<div class="admin-topbar">
    <h1 class="admin-title">Coupons</h1>
</div>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-4 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1.5rem;"><?= $editCoupon ? 'Edit' : 'Add' ?> Coupon</h5>

            <form method="POST" class="form-glass">
                <?= csrf_field() ?>
                <?php if ($editCoupon): ?>
                    <input type="hidden" name="id" value="<?= (int)$editCoupon['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control" required style="text-transform:uppercase;"
                           value="<?= e($editCoupon['code'] ?? '') ?>" placeholder="e.g. SAVE20">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="percent" <?= ($editCoupon['type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                            <option value="fixed" <?= ($editCoupon['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Value</label>
                        <input type="number" name="value" class="form-control" step="0.01" min="0.01" required
                               value="<?= e($editCoupon['value'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Min Order</label>
                        <input type="number" name="min_order" class="form-control" step="0.01" min="0"
                               value="<?= e($editCoupon['min_order'] ?? '') ?>" placeholder="Optional">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Max Uses</label>
                        <input type="number" name="max_uses" class="form-control" min="1"
                               value="<?= e($editCoupon['max_uses'] ?? '') ?>" placeholder="Unlimited">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Starts At</label>
                    <input type="datetime-local" name="starts_at" class="form-control"
                           value="<?= $editCoupon['starts_at'] ? date('Y-m-d\TH:i', strtotime($editCoupon['starts_at'])) : '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Expires At</label>
                    <input type="datetime-local" name="expires_at" class="form-control"
                           value="<?= $editCoupon['expires_at'] ? date('Y-m-d\TH:i', strtotime($editCoupon['expires_at'])) : '' ?>">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="couponActive"
                        <?= ($editCoupon['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="couponActive">Active</label>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $editCoupon ? 'Update' : 'Create' ?>
                </button>

                <?php if ($editCoupon): ?>
                    <a href="/admin/coupons.php" class="btn-glass w-100 d-block text-center mt-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="col-lg-8 reveal">
        <div class="glass-card p-0" style="overflow-x:auto;">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Uses</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--accent-light); font-family:monospace;"><?= e($c['code']) ?></td>
                            <td>
                                <?php if ($c['type'] === 'percent'): ?>
                                    <?= e($c['value']) ?>%
                                <?php else: ?>
                                    <?= format_price($c['value']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $c['min_order'] ? format_price($c['min_order']) : '—' ?></td>
                            <td><?= (int)$c['used_count'] ?><?= $c['max_uses'] ? '/' . (int)$c['max_uses'] : '' ?></td>
                            <td style="font-size:0.85rem;">
                                <?= $c['expires_at'] ? date('M d, Y', strtotime($c['expires_at'])) : 'Never' ?>
                            </td>
                            <td>
                                <?php if ($c['is_active']): ?>
                                    <span class="order-status delivered">Active</span>
                                <?php else: ?>
                                    <span class="order-status cancelled">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="/admin/coupons.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Delete this coupon?">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted);">No coupons yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
