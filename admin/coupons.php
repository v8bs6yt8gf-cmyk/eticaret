<?php
require_once __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

// ─── DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/coupons.php'); }
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$delId]);
        audit_log('admin.coupon.deleted', 'coupon', $delId);
        flash('success', 'Kupon silindi.');
    }
    redirect('/admin/coupons.php');
}

// ─── SAVE (only when explicit action=save) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/coupons.php'); }

    $id        = (int)($_POST['id'] ?? 0);
    $code      = strtoupper(trim($_POST['code'] ?? ''));
    $type      = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = ($_POST['min_order'] !== '' && $_POST['min_order'] !== null) ? max(0, (float)$_POST['min_order']) : null;
    $maxUses   = ($_POST['max_uses'] !== '' && $_POST['max_uses'] !== null) ? max(1, (int)$_POST['max_uses']) : null;
    $startsAt  = $_POST['starts_at'] ?: null;
    $expiresAt = $_POST['expires_at'] ?: null;
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,50}$/', $code)) {
        $errors[] = 'Kupon kodu zorunludur (2–50 karakter, A–Z, 0–9, _, -).';
    }
    if ($value <= 0) {
        $errors[] = "Değer 0'dan büyük olmalı.";
    }
    if ($type === 'percent' && $value > 100) {
        $errors[] = "Yüzde değeri 100'ü geçemez.";
    }
    if ($startsAt && $expiresAt && strtotime($startsAt) >= strtotime($expiresAt)) {
        $errors[] = 'Bitiş tarihi başlangıç tarihinden sonra olmalı.';
    }

    if ($errors) {
        foreach ($errors as $err) flash('warning', $err);
        redirect('/admin/coupons.php' . ($id ? '?action=edit&id=' . $id : ''));
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, starts_at=?, expires_at=?, is_active=? WHERE id=?");
        $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt, $isActive, $id]);
        audit_log('admin.coupon.updated', 'coupon', $id, ['code' => $code, 'type' => $type, 'value' => $value]);
        flash('success', 'Kupon güncellendi.');
    } else {
        $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetch()) {
            flash('warning', 'Bu kupon kodu zaten mevcut.');
            redirect('/admin/coupons.php');
        }
        $stmt = $pdo->prepare("INSERT INTO coupons (code, type, value, min_order, max_uses, starts_at, expires_at, is_active) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt, $isActive]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('admin.coupon.created', 'coupon', $newId, ['code' => $code]);
        flash('success', 'Kupon oluşturuldu.');
    }
    redirect('/admin/coupons.php');
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

$editCoupon = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editCoupon = $stmt->fetch();
}

$pageTitle = 'Kuponlar';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-title">Kuponlar</h1>
</div>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-4 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1.5rem;"><?= $editCoupon ? 'Kuponu Düzenle' : 'Kupon Ekle' ?></h5>

            <form method="POST" class="form-glass">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editCoupon): ?>
                    <input type="hidden" name="id" value="<?= (int)$editCoupon['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Kod</label>
                    <input type="text" name="code" class="form-control" required style="text-transform:uppercase;"
                           pattern="[A-Za-z0-9_-]{2,50}" maxlength="50"
                           value="<?= e($editCoupon['code'] ?? '') ?>" placeholder="örn. SAVE20">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Tip</label>
                        <select name="type" class="form-select">
                            <option value="percent" <?= ($editCoupon['type'] ?? '') === 'percent' ? 'selected' : '' ?>>Yüzde (%)</option>
                            <option value="fixed" <?= ($editCoupon['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Sabit Tutar</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Değer</label>
                        <input type="number" name="value" class="form-control" step="0.01" min="0.01" max="100000" required
                               value="<?= e($editCoupon['value'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Min. Sipariş</label>
                        <input type="number" name="min_order" class="form-control" step="0.01" min="0"
                               value="<?= e($editCoupon['min_order'] ?? '') ?>" placeholder="Opsiyonel">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Maks. Kullanım</label>
                        <input type="number" name="max_uses" class="form-control" min="1"
                               value="<?= e($editCoupon['max_uses'] ?? '') ?>" placeholder="Sınırsız">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Başlangıç</label>
                    <input type="datetime-local" name="starts_at" class="form-control"
                           value="<?= $editCoupon && $editCoupon['starts_at'] ? date('Y-m-d\TH:i', strtotime($editCoupon['starts_at'])) : '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Bitiş</label>
                    <input type="datetime-local" name="expires_at" class="form-control"
                           value="<?= $editCoupon && $editCoupon['expires_at'] ? date('Y-m-d\TH:i', strtotime($editCoupon['expires_at'])) : '' ?>">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="couponActive"
                        <?= ($editCoupon['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="couponActive">Aktif</label>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $editCoupon ? 'Güncelle' : 'Oluştur' ?>
                </button>

                <?php if ($editCoupon): ?>
                    <a href="/admin/coupons.php" class="btn-glass w-100 d-block text-center mt-2">İptal</a>
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
                        <th>Kod</th>
                        <th>İndirim</th>
                        <th>Min. Sipariş</th>
                        <th>Kullanım</th>
                        <th>Bitiş</th>
                        <th>Durum</th>
                        <th>İşlem</th>
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
                                <?= $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : 'Süresiz' ?>
                            </td>
                            <td>
                                <?php if ($c['is_active']): ?>
                                    <span class="order-status delivered">Aktif</span>
                                <?php else: ?>
                                    <span class="order-status cancelled">Pasif</span>
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
                                        <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Bu kupon silinsin mi?">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted);">Henüz kupon yok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
