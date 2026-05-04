<?php
require_once __DIR__ . '/includes/bootstrap.php';

// ─── Toggle active ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/users.php'); }
    $uid = (int)($_POST['id'] ?? 0);

    if ($uid === current_user_id()) {
        flash('warning', 'Kendi hesabınızı bloklayamazsınız.');
        redirect('/admin/users.php');
    }

    $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $target = $stmt->fetch();
    if (!$target) {
        flash('danger', 'Kullanıcı bulunamadı.');
        redirect('/admin/users.php');
    }

    if ($target['role'] === 'admin' && (int)$target['is_active'] === 1) {
        if (active_admin_count() <= 1) {
            flash('warning', 'En az bir aktif admin gerekli. Önce başka birini admin yapın.');
            redirect('/admin/users.php');
        }
    }

    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$uid]);
    audit_log('admin.user.toggled', 'user', $uid);
    flash('success', 'Kullanıcı durumu güncellendi.');
    redirect('/admin/users.php');
}

// ─── Change role ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/users.php'); }
    $uid  = (int)($_POST['user_id'] ?? 0);
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';

    if ($uid === current_user_id()) {
        flash('warning', 'Kendi rolünüzü değiştiremezsiniz.');
        redirect('/admin/users.php');
    }

    $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $target = $stmt->fetch();
    if (!$target) {
        flash('danger', 'Kullanıcı bulunamadı.');
        redirect('/admin/users.php');
    }

    if ($target['role'] === 'admin' && $role === 'customer' && (int)$target['is_active'] === 1) {
        if (active_admin_count() <= 1) {
            flash('warning', 'En az bir aktif admin gerekli.');
            redirect('/admin/users.php');
        }
    }

    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);
    audit_log('admin.user.role_changed', 'user', $uid, ['new_role' => $role]);
    flash('success', 'Kullanıcı rolü güncellendi.');
    redirect('/admin/users.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute();
$users = $stmt->fetchAll();

$pageTitle = 'Kullanıcılar';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-title">Kullanıcılar <span style="color:var(--text-muted); font-size:1rem;">(<?= $total ?>)</span></h1>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Ad</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Sipariş</th>
                <th>Durum</th>
                <th>Kayıt</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight:500; color:var(--text-primary);"><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <span class="order-status <?= $u['role'] === 'admin' ? 'processing' : 'shipped' ?>">
                            <?= e(ucfirst($u['role'])) ?>
                        </span>
                    </td>
                    <td><?= (int)$u['order_count'] ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="order-status delivered">Aktif</span>
                        <?php else: ?>
                            <span class="order-status cancelled">Bloklu</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.85rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['id'] !== current_user_id()): ?>
                            <div class="d-flex gap-2 align-items-center">
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="change_role" value="1">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <select name="role" onchange="this.form.submit()" class="form-select form-select-sm"
                                            style="width:auto; background:var(--bg-glass); border:1px solid var(--glass-border); color:var(--text-primary); font-size:0.8rem;">
                                        <option value="customer" <?= $u['role'] === 'customer' ? 'selected' : '' ?>>Müşteri</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" title="<?= $u['is_active'] ? 'Blokla' : 'Bloku kaldır' ?>">
                                        <i class="bi bi-<?= $u['is_active'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="font-size:0.8rem; color:var(--text-muted);">Siz</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pag['total_pages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-glass mb-0">
            <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $pag['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
