<?php
require_once __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

// ─── DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/categories.php'); }
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
        audit_log('admin.category.deleted', 'category', $delId);
        flash('success', 'Kategori silindi.');
    }
    redirect('/admin/categories.php');
}

// ─── SAVE (only when explicit action=save) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/categories.php'); }

    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $slug        = slugify($name);
    $description = trim($_POST['description'] ?? '');
    $parentId    = (int)($_POST['parent_id'] ?? 0) ?: null;
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 100) {
        flash('warning', 'Kategori adı zorunlu (en fazla 100 karakter).');
        redirect('/admin/categories.php' . ($id ? '?action=edit&id=' . $id : ''));
    }

    if ($parentId !== null && $id > 0 && $parentId === $id) {
        flash('warning', 'Bir kategori kendisinin alt kategorisi olamaz.');
        redirect('/admin/categories.php?action=edit&id=' . $id);
    }

    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
    $check->execute([$slug, $id]);
    if ($check->fetch()) $slug .= '-' . time();

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, parent_id=?, sort_order=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $slug, $description, $parentId, $sortOrder, $isActive, $id]);
        audit_log('admin.category.updated', 'category', $id, ['name' => $name]);
        flash('success', 'Kategori güncellendi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id, sort_order, is_active) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name, $slug, $description, $parentId, $sortOrder, $isActive]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('admin.category.created', 'category', $newId, ['name' => $name]);
        flash('success', 'Kategori oluşturuldu.');
    }
    redirect('/admin/categories.php');
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count FROM categories c ORDER BY c.sort_order, c.name")->fetchAll();

$editCat = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editCat = $stmt->fetch();
}

$pageTitle = 'Kategoriler';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-title">Kategoriler</h1>
</div>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-4 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1.5rem;">
                <?= $editCat ? 'Kategoriyi Düzenle' : 'Kategori Ekle' ?>
            </h5>

            <form method="POST" class="form-glass">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editCat): ?>
                    <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Ad</label>
                    <input type="text" name="name" class="form-control" required maxlength="100"
                           value="<?= e($editCat['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($editCat['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Üst Kategori</label>
                    <select name="parent_id" class="form-select">
                        <option value="">— Yok (üst seviye) —</option>
                        <?php foreach ($categories as $c):
                            if ($editCat && $c['id'] == $editCat['id']) continue;
                        ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($editCat['parent_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Sıralama</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= e($editCat['sort_order'] ?? '0') ?>">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="catActive"
                                <?= ($editCat['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="catActive">Aktif</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $editCat ? 'Güncelle' : 'Oluştur' ?>
                </button>

                <?php if ($editCat): ?>
                    <a href="/admin/categories.php" class="btn-glass w-100 d-block text-center mt-2">İptal</a>
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
                        <th>Ad</th>
                        <th>Slug</th>
                        <th>Ürün</th>
                        <th>Sıralama</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td style="font-weight:500; color:var(--text-primary);"><?= e($c['name']) ?></td>
                            <td style="font-size:0.85rem;"><?= e($c['slug']) ?></td>
                            <td><?= (int)$c['product_count'] ?></td>
                            <td><?= (int)$c['sort_order'] ?></td>
                            <td>
                                <?php if ($c['is_active']): ?>
                                    <span class="order-status delivered">Aktif</span>
                                <?php else: ?>
                                    <span class="order-status cancelled">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="/admin/categories.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Bu kategori silinsin mi? Ürünler silinmez.">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted);">Henüz kategori yok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
