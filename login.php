<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/login.php'); }

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Per-IP / per-email throttle
    if ($email !== '') {
        $blockSeconds = login_throttle_check($email);
        if ($blockSeconds !== null) {
            audit_log('login.blocked', 'user', null, ['email' => $email]);
            flash('danger', 'Çok fazla başarısız deneme. Lütfen birkaç dakika sonra tekrar deneyin.');
            redirect('/login.php');
        }
    }

    $user = null;
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    $authOk = $user
        && (int)$user['is_active'] === 1
        && password_verify($password, $user['password']);

    if ($authOk) {
        login_attempts_clear('ip:' . client_ip());
        login_attempts_clear('email:' . $email);
        login_attempts_record('ip:' . client_ip(), true);

        maybe_rehash_password((int)$user['id'], $password, $user['password']);
        login_user($user);
        merge_cart_on_login((int)$user['id']);
        audit_log('login.success', 'user', (int)$user['id']);

        flash('success', 'Tekrar hoş geldiniz, ' . e($user['name']) . '!');
        redirect($user['role'] === 'admin' ? '/admin/' : '/account.php');
    } else {
        // Always record failure under both keys to deter enumeration
        login_attempts_record('ip:' . client_ip(), false);
        if ($email !== '') login_attempts_record('email:' . $email, false);
        audit_log('login.failure', 'user', $user['id'] ?? null, ['email' => $email]);

        // Small delay to slow brute force (random to mask timing)
        usleep(random_int(150000, 400000));
        flash('danger', 'Geçersiz e-posta veya şifre.');
    }
}

$pageTitle = 'Giriş';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Hesap erişimi</span>
        <h1 class="auth-title mt-2">Tekrar Hoş Geldiniz</h1>
        <p class="auth-subtitle">Siparişlerinizi takip edin, bilgilerinizi kaydedin, hızlıca alışverişe devam edin.</p>

        <form method="POST" class="form-glass" autocomplete="on">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" required
                           autocomplete="username"
                           value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@ornek.com">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Şifre</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" required
                           autocomplete="current-password" placeholder="Şifreniz">
                </div>
            </div>

            <div class="mb-4 text-end">
                <a href="/forgot-password.php" style="font-size:0.85rem;">Şifremi unuttum</a>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap
            </button>

            <p class="text-center mt-3" style="color:var(--text-muted); font-size:0.9rem;">
                Hesabınız yok mu? <a href="/register.php">Kayıt olun</a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
