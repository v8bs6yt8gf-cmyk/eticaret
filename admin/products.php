<?php
require_once __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? 'list';

// ─── DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/products.php'); }
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$delId]);
        audit_log('admin.product.deleted', 'product', $delId);
        flash('success', 'Ürün silindi.');
    }
    redirect('/admin/products.php');
}

// ─── SAVE (Create / Update) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'edit'], true)) {
    if (!verify_csrf()) { flash('danger', 'Geçersiz istek.'); redirect('/admin/products.php'); }

    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $slug        = slugify($name);
    $categoryId  = (int)($_POST['category_id'] ?? 0) ?: null;
    $description = trim($_POST['description'] ?? '');
    $price       = max(0, (float)($_POST['price'] ?? 0));
    $salePrice   = ($_POST['sale_price'] !== '' && $_POST['sale_price'] !== null) ? max(0, (float)$_POST['sale_price']) : null;
    $stock       = max(0, (int)($_POST['stock'] ?? 0));
    $sku         = trim($_POST['sku'] ?? '') ?: null;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 200) {
        flash('warning', 'Ürün adı zorunludur (en fazla 200 karakter).');
        redirect('/admin/products.php?action=' . ($id ? 'edit&id=' . $id : 'create'));
    }
    if ($salePrice !== null && $price > 0 && $salePrice >= $price) {
        flash('warning', 'İndirimli fiyat normal fiyattan küçük olmalı.');
        redirect('/admin/products.php?action=' . ($id ? 'edit&id=' . $id : 'create'));
    }

    // Handle image upload
    $image = $_POST['existing_image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_image($_FILES['image']);
        if ($uploaded) $image = $uploaded;
        else flash('warning', 'Görsel yüklenemedi (jpg/png/webp/gif, ≤5MB).');
    }

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
        audit_log('admin.product.updated', 'product', $id, ['name' => $name, 'price' => $price, 'stock' => $stock]);
        flash('success', 'Ürün güncellendi.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO products (name, slug, category_id, description, price, sale_price, stock, sku, image, is_featured, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $slug, $categoryId, $description, $price, $salePrice, $stock, $sku, $image, $isFeatured, $isActive]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('admin.product.created', 'product', $newId, ['name' => $name]);
        flash('success', 'Ürün oluşturuldu.');
    }
    redirect('/admin/products.php');
}

// ─── Pre-fetch for edit ────────────────────────────────
$product = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    $product = $stmt->fetch();
    if (!$product) { flash('danger', 'Ürün bulunamadı.'); redirect('/admin/products.php'); }
}

// ─── List data ─────────────────────────────────────────
$listData = null;
if ($action === 'list') {
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
    $listData = ['products' => $stmt->fetchAll(), 'pag' => $pag, 'total' => $total];
}

// ─── Categories for dropdown ───────────────────────────
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

$pageTitle = 'Ürünler';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'edit' || $action === 'create'): ?>

<div class="admin-topbar">
    <h1 class="admin-title"><?= $product ? 'Ürünü Düzenle' : 'Yeni Ürün' ?></h1>
    <a href="/admin/products.php" class="btn-glass"><i class="bi bi-arrow-left me-2"></i>Geri</a>
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
                    <label class="form-label">Ürün Adı</label>
                    <input type="text" name="name" class="form-control" required maxlength="200"
                           value="<?= e($product['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea name="description" class="form-control" rows="5"><?= e($product['description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Fiyat</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required
                               value="<?= e($product['price'] ?? '0') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">İndirimli Fiyat</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" min="0"
                               value="<?= e($product['sale_price'] ?? '') ?>" placeholder="Boş bırakılabilir">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stock" class="form-control" min="0"
                               value="<?= e($product['stock'] ?? '0') ?>">
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Yok —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control" maxlength="50"
                           value="<?= e($product['sku'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Görsel</label>
                    <input type="file" name="image" id="productImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if (!empty($product['image'])): ?>
                        <img src="/<?= e($product['image']) ?>" id="imagePreview" alt=""
                             style="max-width:100%; margin-top:0.75rem; border-radius:var(--radius-sm);">
                    <?php else: ?>
                        <img id="imagePreview" alt="" style="display:none; max-width:100%; margin-top:0.75rem; border-radius:var(--radius-sm);">
                    <?php endif; ?>
                </div>

                <div class="mb-2">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured"
                            <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isFeatured">Öne Çıkan</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                            <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                </div>

                <button type="submit" class="btn-gradient w-100">
                    <i class="bi bi-check-lg me-2"></i><?= $product ? 'Güncelle' : 'Oluştur' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php else: // LIST ?>

<div class="admin-topbar">
    <h1 class="admin-title">Ürünler <span style="color:var(--text-muted); font-size:1rem;">(<?= (int)$listData['total'] ?>)</span></h1>
    <a href="/admin/products.php?action=create" class="btn-gradient"><i class="bi bi-plus-lg me-2"></i>Ürün Ekle</a>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Görsel</th>
                <th>Ad</th>
                <th>Kategori</th>
                <th>Fiyat</th>
                <th>Stok</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listData['products'] as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['image']): ?>
                            <img src="/<?= e($p['image']) ?>" alt="" style="width:45px; height:45px; object-fit:cover; border-radius:var(--radius-sm);">
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
                            <span class="order-status delivered">Aktif</span>
                        <?php else: ?>
                            <span class="order-status cancelled">Pasif</span>
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
                                <button type="submit" class="btn-danger-glass" style="padding:0.35rem 0.7rem; font-size:0.8rem;" data-confirm="Bu ürünü silmek istediğinize emin misiniz?">
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

<?php if ($listData['pag']['total_pages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-glass mb-0">
            <?php for ($i = 1; $i <= $listData['pag']['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $listData['pag']['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
