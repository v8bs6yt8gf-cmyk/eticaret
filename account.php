<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = current_user_id();
$stmt   = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/account.php'); }

    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $zip     = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if ($name === '') {
        flash('warning', 'Name is required.');
    } else {
        $stmt = $pdo->prepare("
            UPDATE users SET name=?, phone=?, address=?, city=?, zip_code=?, country=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$name, $phone, $address, $city, $zip, $country, $userId]);
        $_SESSION['user_name'] = $name;
        flash('success', 'Profile updated.');
        redirect('/account.php');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/account.php'); }

    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        flash('danger', 'Current password is incorrect.');
    } elseif (strlen($newPass) < 6) {
        flash('warning', 'New password must be at least 6 characters.');
    } elseif ($newPass !== $confirm) {
        flash('warning', 'Passwords do not match.');
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        flash('success', 'Password changed.');
        redirect('/account.php');
    }
}

// Fetch orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Account';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="eyebrow">Customer dashboard</span>
                <h1 class="section-title mb-1">My <span class="text-gradient">Account</span></h1>
                <p class="text-secondary mb-0">Manage your profile, password, and recent orders.</p>
            </div>
            <a href="/products.php" class="btn-gradient"><i class="bi bi-bag"></i>Shop New Arrivals</a>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5">

    <div class="row g-4">
        <!-- Profile -->
        <div class="col-lg-6 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-person me-2"></i>Profile</h5>

                <form method="POST" class="form-glass">
                    <?= csrf_field() ?>
                    <input type="hidden" name="update_profile" value="1">

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= e($user['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= e($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= e($user['zip_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="<?= e($user['country'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-gradient">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Password Change -->
        <div class="col-lg-6 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>

                <form method="POST" class="form-glass">
                    <?= csrf_field() ?>
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn-gradient">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Order History -->
        <div class="col-12 reveal">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-bag-check me-2"></i>Order History</h5>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="bi bi-bag"></i>
                        <h3>No Orders Yet</h3>
                        <p>Start shopping to see your orders here.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="table-glass">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td style="font-weight:600; color:var(--text-primary);">#<?= e($o['order_number']) ?></td>
                                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                                        <td style="font-weight:600;"><?= format_price($o['total']) ?></td>
                                        <td><span class="order-status <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                        <td><a href="/order.php?id=<?= (int)$o['id'] ?>" class="btn-sm-gradient">View</a></td>
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
