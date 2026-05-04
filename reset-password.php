<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Lightweight token shape validation (do not consume yet)
$tokenValidShape = ($token !== '' && ctype_xdigit($token) && strlen($token) === 64);

if (!$tokenValidShape) {
    flash('danger', 'Geçersiz veya süresi dolmuş bağlantı.');
    redirect('/forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/reset-password.php?token=' . $token); }

    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        flash('warning', 'Şifre en az 8 karakter olmalı.');
        redirect('/reset-password.php?token=' . $token);
    }
    if ($newPass !== $confirm) {
        flash('warning', 'Şifreler eşleşmiyor.');
        redirect('/reset-password.php?token=' . $token);
    }

    $userId = password_reset_consume($token);
    if (!$userId) {
        audit_log('password_reset.invalid_token');
        flash('danger', 'Bağlantı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.');
        redirect('/forgot-password.php');
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = ?")
            ->execute([$hash, $userId]);
    } catch (PDOException $e) {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    }

    audit_log('password_reset.completed', 'user', $userId);

    flash('success', 'Şifre başarıyla güncellendi. Şimdi giriş yapabilirsiniz.');
    redirect('/login.php');
}

$pageTitle = 'Şifre Sıfırla';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Yeni şifre belirleyin</span>
        <h1 class="auth-title mt-2">Şifre Sıfırla</h1>
        <p class="auth-subtitle">En az 8 karakter olacak şekilde yeni bir şifre belirleyin.</p>

        <form method="POST" class="form-glass" autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="mb-3">
                <label class="form-label">Yeni Şifre</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="new_password" class="form-control border-start-0" required
                           autocomplete="new-password" minlength="8" placeholder="En az 8 karakter">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Yeni Şifre (Tekrar)</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-shield-check"></i></span>
                    <input type="password" name="confirm_password" class="form-control border-start-0" required
                           autocomplete="new-password" placeholder="Şifreyi tekrar girin">
                </div>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-check2-circle me-2"></i>Şifreyi Güncelle
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
