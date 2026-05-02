<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/header.php';

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/categories.php'); }
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('success', 'Category deleted.');
    redirect('/admin/categories.php');
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/categories.php'); }

    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $slug        = slugify($name);
    $description = trim($_POST['description'] ?? '');
    $parentId    = (int)($_POST['parent_id'] ?? 0) ?: null;
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash('warning', 'Category name is required.');
    } else {
        $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $check->execute([$slug, $id]);
        if ($check->fetch()) $slug .= '-' . time();

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, parent_id=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $slug, $description, $parentId, $sortOrder, $isActive, $id]);
            flash('success', 'Category updated.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id, sort_order, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $slug, $description, $parentId, $sortOrder, $isActive]);
            flash('success', 'Category created.');
        }
        redirect('/admin/categories.php');
    }
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count FROM categories c ORDER BY c.sort_order, c.name")->fetchAll();

$editCat = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editCat = $stmt->fetch();
}
?>

<div class="admin-topbar">
    <h1 class="admin-title">Categories</h1>
</div>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-4 reveal">
        <div class="glass-card p-4">
            <h5 style="font-weight:700; margin-bottom:1.5rem;">
                <?= $editCat ? 'Edit' : 'Add' ?> Category
            </h5>

            <form method="POST" class="form-glass">
                <?= csrf_field() ?>
                <?php if ($editCat): ?>
                    <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($editCat['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($editCat['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" class="form-select">
                        <option value="">— None (Top Level) —</option>
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
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= e($editCat['sort_order'] ?? '0') ?>">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="catActive"
                                <?= ($editCat['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="catActive">Active</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $editCat ? 'Update' : 'Create' ?>
                </button>

                <?php if ($editCat): ?>
                    <a href="/admin/categories.php" class="btn-glass w-100 d-block text-center mt-2">Cancel</a>
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
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                                    <span class="order-status delivered">Active</span>
                                <?php else: ?>
                                    <span class="order-status cancelled">Inactive</span>
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
                                        <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Delete this category? Products won't be deleted.">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted);">No categories yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
