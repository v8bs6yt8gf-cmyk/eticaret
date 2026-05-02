<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/account.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/register.php'); }

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $errors = [];
    if ($name === '')       $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    // Check duplicate
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Email is already registered.';
    }

    if ($errors) {
        foreach ($errors as $err) flash('warning', $err);
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
        $stmt->execute([$name, $email, $hash]);

        $userId = (int)$pdo->lastInsertId();
        login_user(['id' => $userId, 'name' => $name, 'role' => 'customer']);

        merge_cart_on_login($userId);

        flash('success', 'Welcome! Your account has been created.');
        redirect('/account.php');
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="glass-card auth-card reveal">
        <span class="eyebrow">Fast checkout</span>
        <h1 class="auth-title mt-2">Create Account</h1>
        <p class="auth-subtitle">Save your details, merge your cart, and manage orders in one clean dashboard.</p>

        <form method="POST" class="form-glass">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" name="name" class="form-control border-start-0" required
                       value="<?= e($_POST['name'] ?? '') ?>" placeholder="John Doe">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" required
                       value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" required
                       minlength="8" placeholder="Min. 8 characters">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent text-secondary border-end-0"><i class="bi bi-shield-check"></i></span>
                    <input type="password" name="password_confirm" class="form-control border-start-0" required
                       placeholder="Repeat password">
                </div>
            </div>

            <button type="submit" class="btn-gradient w-100 py-3">
                <i class="bi bi-person-plus me-2"></i>Register
            </button>

            <p class="text-center mt-3" style="color:var(--text-muted); font-size:0.9rem;">
                Already have an account? <a href="/login.php">Log in</a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
