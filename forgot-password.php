<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

$showToken = null;   // dev-only: shown when mail is not configured
$mailEnabled = (int)setting('mail_enabled', '0') === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/forgot-password.php'); }

    $email = strtolower(trim($_POST['email'] ?? ''));

    // Per-IP throttle to slow enumeration
    $ipFails = login_attempts_recent_failures('reset:' . client_ip(), 60);
    if ($ipFails > 10) {
        audit_log('password_reset.throttled', null, null, ['email' => $email]);
        flash('warning', 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.');
        redirect('/forgot-password.php');
    }
    login_attempts_record('reset:' . client_ip(), false);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1) {
                $token = password_reset_create((int)$user['id']);
                $resetUrl = url('/reset-password.php?token=' . $token);
                audit_log('password_reset.requested', 'user', (int)$user['id']);

                if ($mailEnabled) {
                    // Plug your mailer here (PHPMailer / symfony/mailer).
                    // Example placeholder:
                    @mail(
                        $user['email'],
                        'Şifre Sıfırlama',
                        "Merhaba " . $user['name'] . ",\n\n"
                            . "Şifrenizi sıfırlamak için 30 dakika içinde aşağıdaki bağlantıyı açın:\n"
                            . $resetUrl . "\n\nBu isteği siz yapmadıysanız bu e-postayı görmezden gelin."
                    );
                } else {
                    // Dev fallback: surface the link to admin via audit. Never to the requester.
                    audit_log('password_reset.dev_url', 'user', (int)$user['id'], ['url' => $resetUrl]);
                }
            }
        } catch (Throwable $e) {
            error_log('[forgot-password] ' . $e->getMessage());
        }
    }

    // Always show the same neutral message to avoid email enumeration.
    flash('success', 'E-posta adresiniz kayıtlıysa, şifre sıfırlama bağlantısı gönderildi.');
    redirect('/forgot-password.php');
}

$pageTitle = 'Şifremi Unuttum';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Hesap kurtarma</span>
        <h1 class="auth-title mt-2">Şifremi Unuttum</h1>
        <p class="auth-subtitle">E-posta adresinizi girin; kayıtlıysa size sıfırlama bağlantısı gönderilir.</p>

        <form method="POST" class="form-glass" autocomplete="on">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label class="form-label">E-posta</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" required
                           autocomplete="username"
                           placeholder="ornek@ornek.com">
                </div>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-send me-2"></i>Sıfırlama Bağlantısı Gönder
            </button>

            <p class="text-center mt-3" style="color:var(--text-muted); font-size:0.9rem;">
                <a href="/login.php">Girişe dön</a>
            </p>
        </form>

        <?php if (!$mailEnabled): ?>
            <div class="alert alert-warning mt-3" style="font-size:0.85rem;">
                <i class="bi bi-info-circle me-1"></i> Mail gönderimi henüz açık değil. Yönetici, audit log üzerinden sıfırlama bağlantısını görüntüleyebilir.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
