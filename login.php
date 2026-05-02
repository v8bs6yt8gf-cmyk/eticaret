<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/login.php'); }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password']   ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);

        merge_cart_on_login((int)$user['id']);

        flash('success', 'Welcome back, ' . e($user['name']) . '!');
        redirect($user['role'] === 'admin' ? '/admin/' : '/account.php');
    } else {
        flash('danger', 'Invalid email or password.');
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Account access</span>
        <h1 class="auth-title mt-2">Welcome Back</h1>
        <p class="auth-subtitle">Log in to track orders, save details, and checkout faster.</p>

        <form method="POST" class="form-glass">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" required
                       value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" required
                       placeholder="Your password">
                </div>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
            </button>

            <p class="text-center mt-3" style="color:var(--text-muted); font-size:0.9rem;">
                Don't have an account? <a href="/register.php">Register</a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
