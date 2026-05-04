<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/register.php'); }

    $name     = trim($_POST['name']             ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password']               ?? '';
    $confirm  = $_POST['password_confirm']       ?? '';

    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Ad alanı zorunludur (en fazla 100 karakter).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) $errors[] = 'Geçerli bir e-posta gerekli.';
    if (strlen($password) < 8) $errors[] = 'Şifre en az 8 karakter olmalı.';
    if ($password !== $confirm) $errors[] = 'Şifreler eşleşmiyor.';

    // Light per-IP throttle to slow registration spam
    if (!$errors) {
        $ipFails = login_attempts_recent_failures('register:' . client_ip(), 60);
        if ($ipFails > 20) {
            $errors[] = 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.';
        }
    }

    // Generic, anti-enumeration response: do not reveal whether email exists.
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            login_attempts_record('register:' . client_ip(), false);
            audit_log('register.duplicate_attempt', 'user', (int)$existing['id'], ['email' => $email]);
            flash('success', 'Kayıt isteği alındı. E-postanızı kontrol edin.');
            redirect('/login.php');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, password_changed_at)
                 VALUES (?, ?, ?, 'customer', NOW())"
            );
            $stmt->execute([$name, $email, $hash]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$name, $email, $hash]);
        }

        $userId = (int)$pdo->lastInsertId();
        login_user(['id' => $userId, 'name' => $name, 'role' => 'customer']);
        merge_cart_on_login($userId);
        audit_log('register.success', 'user', $userId);

        flash('success', 'Hoş geldiniz! Hesabınız oluşturuldu.');
        redirect('/account.php');
    }

    foreach ($errors as $err) flash('warning', $err);
}

$pageTitle = 'Kayıt';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Hızlı ödeme</span>
        <h1 class="auth-title mt-2">Hesap Oluştur</h1>
        <p class="auth-subtitle">Bilgilerinizi kaydedin, sepetinizi birleştirin ve siparişlerinizi tek panelden yönetin.</p>

        <form method="POST" class="form-glass" autocomplete="on">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Ad Soyad</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" name="name" class="form-control border-start-0" required maxlength="100"
                           autocomplete="name"
                           value="<?= e($_POST['name'] ?? '') ?>" placeholder="Ad Soyad">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" required maxlength="191"
                           autocomplete="email"
                           value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@ornek.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Şifre</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" required
                           autocomplete="new-password" minlength="8" placeholder="En az 8 karakter">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Şifreyi Tekrarla</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-shield-check"></i></span>
                    <input type="password" name="password_confirm" class="form-control border-start-0" required
                           autocomplete="new-password" placeholder="Şifreyi tekrar girin">
                </div>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-person-plus me-2"></i>Kayıt Ol
            </button>

            <p class="text-center mt-3" style="color:var(--text-muted); font-size:0.9rem;">
                Zaten hesabınız var mı? <a href="/login.php">Giriş yapın</a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
