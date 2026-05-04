<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = current_user_id();
$stmt   = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('/login.php');
}

// ─── Profile update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/account.php'); }

    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $zip     = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        flash('warning', 'Ad alanı zorunludur (en fazla 100 karakter).');
    } else {
        $stmt = $pdo->prepare("
            UPDATE users SET name=?, phone=?, address=?, city=?, zip_code=?, country=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$name, $phone, $address, $city, $zip, $country, $userId]);
        $_SESSION['user_name'] = $name;
        audit_log('account.profile_updated', 'user', $userId);
        flash('success', 'Profil güncellendi.');
        redirect('/account.php');
    }
}

// ─── Password change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/account.php'); }

    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        audit_log('account.password_change_failed', 'user', $userId);
        flash('danger', 'Mevcut şifre hatalı.');
    } elseif (strlen($newPass) < 8) {
        flash('warning', 'Yeni şifre en az 8 karakter olmalı.');
    } elseif ($newPass !== $confirm) {
        flash('warning', 'Şifreler eşleşmiyor.');
    } elseif (hash_equals($current, $newPass)) {
        flash('warning', 'Yeni şifre, mevcut şifreyle aynı olamaz.');
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")
                ->execute([$hash, $userId]);
        } catch (PDOException $e) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        }
        // Invalidate any outstanding password reset tokens
        try {
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                ->execute([$userId]);
        } catch (PDOException $e) { /* migration may not yet have run */ }

        // Rotate session ID and CSRF
        session_regenerate_id(true);
        rotate_csrf();

        audit_log('account.password_changed', 'user', $userId);
        flash('success', 'Şifre değiştirildi.');
        redirect('/account.php');
    }
}

// Fetch orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'Hesabım';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="eyebrow">Müşteri paneli</span>
                <h1 class="section-title mb-1"><span class="text-gradient">Hesabım</span></h1>
                <p class="text-secondary mb-0">Profilinizi, şifrenizi ve son siparişlerinizi yönetin.</p>
            </div>
            <a href="/products.php" class="btn-gradient"><i class="bi bi-bag"></i>Yeni Ürünler</a>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5">

    <div class="row g-4">
        <!-- Profile -->
        <div class="col-lg-6 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-person me-2"></i>Profil</h5>

                <form method="POST" class="form-glass" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="update_profile" value="1">

                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($user['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Şehir</label>
                            <input type="text" name="city" class="form-control" value="<?= e($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Posta Kodu</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= e($user['zip_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ülke</label>
                            <input type="text" name="country" class="form-control" value="<?= e($user['country'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-gradient">Değişiklikleri Kaydet</button>
                </form>
            </div>
        </div>

        <!-- Password Change -->
        <div class="col-lg-6 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-shield-lock me-2"></i>Şifre Değiştir</h5>

                <form method="POST" class="form-glass" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label class="form-label">Mevcut Şifre</label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-gradient">Şifreyi Güncelle</button>
                </form>
            </div>
        </div>

        <!-- Order History -->
        <div class="col-12 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-bag-check me-2"></i>Sipariş Geçmişi</h5>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="bi bi-bag"></i>
                        <h3>Henüz Sipariş Yok</h3>
                        <p>Siparişleriniz burada listelenecek.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="table-glass">
                            <thead>
                                <tr>
                                    <th>Sipariş</th>
                                    <th>Tarih</th>
                                    <th>Toplam</th>
                                    <th>Durum</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td style="font-weight:600; color:var(--text-primary);">#<?= e($o['order_number']) ?></td>
                                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                                        <td style="font-weight:600;"><?= format_price($o['total']) ?></td>
                                        <td><span class="order-status <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                        <td><a href="/order.php?id=<?= (int)$o['id'] ?>" class="btn-sm-gradient">Görüntüle</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
