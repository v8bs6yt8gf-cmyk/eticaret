<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';

// ─── DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/products.php'); }
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('success', 'Product deleted.');
    redirect('/admin/products.php');
}

// ─── SAVE (Create / Update) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'edit'])) {
    if (!verify_csrf()) { flash('danger', 'Invalid request.'); redirect('/admin/products.php'); }

    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $slug        = slugify($name);
    $categoryId  = (int)($_POST['category_id'] ?? 0) ?: null;
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $salePrice   = ($_POST['sale_price'] !== '' && $_POST['sale_price'] !== null) ? (float)$_POST['sale_price'] : null;
    $stock       = (int)($_POST['stock'] ?? 0);
    $sku         = trim($_POST['sku'] ?? '') ?: null;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    // Handle image upload
    $image = $_POST['existing_image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_image($_FILES['image']);
        if ($uploaded) $image = $uploaded;
    }

    if ($name === '') {
        flash('warning', 'Product name is required.');
    } else {
        // Ensure unique slug
        $checkSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
        $checkSlug->execute([$slug, $id]);
        if ($checkSlug->fetch()) $slug .= '-' . time();

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE products SET name=?, slug=?, category_id=?, description=?, price=?, sale_price=?,
                stock=?, sku=?, image=?, is_featured=?, is_active=?
                WHERE id=?
            ");
            $stmt->execute([$name, $slug, $categoryId, $description, $price, $salePrice, $stock, $sku, $image, $isFeatured, $isActive, $id]);
            flash('success', 'Product updated.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, slug, category_id, description, price, sale_price, stock, sku, image, is_featured, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $categoryId, $description, $price, $salePrice, $stock, $sku, $image, $isFeatured, $isActive]);
            flash('success', 'Product created.');
        }
        redirect('/admin/products.php');
    }
}

// ─── Categories for dropdown ──────────────────────────
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

// ─── EDIT / CREATE FORM ───────────────────────────────
if ($action === 'edit' || $action === 'create'):
    $product = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $product = $stmt->fetch();
        if (!$product) { flash('danger', 'Product not found.'); redirect('/admin/products.php'); }
    }
?>

<div class="admin-topbar">
    <h1 class="admin-title"><?= $product ? 'Edit' : 'New' ?> Product</h1>
    <a href="/admin/products.php" class="btn-glass"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="glass-card p-4 reveal">
    <form method="POST" enctype="multipart/form-data" class="form-glass">
        <?= csrf_field() ?>
        <?php if ($product): ?>
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="existing_image" value="<?= e($product['image'] ?? '') ?>">
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($product['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5"><?= e($product['description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Price</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required
                               value="<?= e($product['price'] ?? '0') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sale Price</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" min="0"
                               value="<?= e($product['sale_price'] ?? '') ?>" placeholder="Leave empty for none">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" class="form-control" min="0"
                               value="<?= e($product['stock'] ?? '0') ?>">
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control"
                           value="<?= e($product['sku'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Image</label>
                    <input type="file" name="image" id="productImage" class="form-control" accept="image/*">
                    <?php if (!empty($product['image'])): ?>
                        <img src="/<?= e($product['image']) ?>" id="imagePreview"
                             style="max-width:100%; margin-top:0.75rem; border-radius:var(--radius-sm);">
                    <?php else: ?>
                        <img id="imagePreview" style="display:none; max-width:100%; margin-top:0.75rem; border-radius:var(--radius-sm);">
                    <?php endif; ?>
                </div>

                <div class="mb-2">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured"
                            <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isFeatured">Featured</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                            <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $product ? 'Update' : 'Create' ?> Product
                </button>
            </div>
        </div>
    </form>
</div>

<?php else: // LIST ?>

<?php
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$countStmt = $pdo->query("SELECT COUNT(*) FROM products");
$total     = (int)$countStmt->fetchColumn();
$pag       = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute();
$products = $stmt->fetchAll();
?>

<div class="admin-topbar">
    <h1 class="admin-title">Products <span style="color:var(--text-muted); font-size:1rem;">(<?= $total ?>)</span></h1>
    <a href="/admin/products.php?action=create" class="btn-gradient"><i class="bi bi-plus-lg me-2"></i>Add Product</a>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['image']): ?>
                            <img src="/<?= e($p['image']) ?>" style="width:45px; height:45px; object-fit:cover; border-radius:var(--radius-sm);">
                        <?php else: ?>
                            <div style="width:45px; height:45px; background:var(--bg-glass); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; color:var(--text-muted);"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:500; color:var(--text-primary);"><?= e($p['name']) ?></td>
                    <td><?= e($p['category_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['sale_price']): ?>
                            <span style="color:var(--accent-light); font-weight:600;"><?= format_price($p['sale_price']) ?></span>
                            <span style="text-decoration:line-through; color:var(--text-muted); font-size:0.8rem;"><?= format_price($p['price']) ?></span>
                        <?php else: ?>
                            <span style="font-weight:600;"><?= format_price($p['price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['stock'] <= 0): ?>
                            <span style="color:var(--danger); font-weight:600;">0</span>
                        <?php elseif ($p['stock'] <= 5): ?>
                            <span style="color:var(--warning); font-weight:600;"><?= $p['stock'] ?></span>
                        <?php else: ?>
                            <?= $p['stock'] ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['is_active']): ?>
                            <span class="order-status delivered">Active</span>
                        <?php else: ?>
                            <span class="order-status cancelled">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="/admin/products.php?action=edit&id=<?= (int)$p['id'] ?>" class="btn-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Delete this product?">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
