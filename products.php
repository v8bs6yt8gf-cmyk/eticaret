<?php
/** Products Listing */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$where = ['p.is_active = 1'];
$params = [];
if ($categoryId) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
$whereSQL = implode(' AND ', $where);
$orderMap = ['newest'=>'p.created_at DESC','price_asc'=>'COALESCE(p.sale_price, p.price) ASC','price_desc'=>'COALESCE(p.sale_price, p.price) DESC','name'=>'p.name ASC','popular'=>'p.view_count DESC'];
$orderSQL = $orderMap[$sort] ?? $orderMap['newest'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSQL}"); $stmt->execute($params); $total = (int)$stmt->fetchColumn(); $pag = paginate($total, $perPage, $page);
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE {$whereSQL} ORDER BY {$orderSQL} LIMIT {$pag['per_page']} OFFSET {$pag['offset']}"); $stmt->execute($params); $products = $stmt->fetchAll();
$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
$currentCategory = null; if ($categoryId) { foreach ($cats as $c) { if ($c['id'] == $categoryId) { $currentCategory = $c['name']; break; } } }
$pageTitle = $currentCategory ?: 'Products';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact">
    <div class="container">
        <nav class="breadcrumb small"><a href="/">Home</a><span>/</span><span>Products</span></nav>
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div><span class="eyebrow">Catalog</span><h1 class="section-title mb-1"><?= $search ? 'Search results' : e($currentCategory ?: 'All products') ?></h1><p class="text-secondary mb-0"><?= $total ?> product<?= $total !== 1 ? 's' : '' ?> found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p></div>
            <form method="GET" class="catalog-search"><input type="search" name="q" class="form-control" placeholder="Search products" value="<?= e($search) ?>"><button class="btn btn-primary"><i class="bi bi-search"></i></button></form>
        </div>
    </div>
</section>
<section class="container py-4 py-lg-5">
    <div class="row g-4">
        <aside class="col-lg-3">
            <div class="filter-card card border-0 shadow-sm sticky-lg-top">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Categories</h2>
                    <div class="filter-list">
                        <a href="/products.php" class="<?= !$categoryId ? 'active' : '' ?>">All Products</a>
                        <?php foreach ($cats as $c): ?><a href="/products.php?category=<?= (int)$c['id'] ?>" class="<?= $categoryId == $c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>
        <div class="col-lg-9">
            <div class="catalog-toolbar card border-0 shadow-sm mb-4"><div class="card-body d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center"><span class="text-secondary small">Showing page <?= (int)$pag['current'] ?> of <?= (int)$pag['total_pages'] ?></span><select class="form-select form-select-sm" style="max-width:240px" onchange="location.href=this.value"><?php $sortOptions=['newest'=>'Newest','price_asc'=>'Price: Low to High','price_desc'=>'Price: High to Low','name'=>'Name','popular'=>'Popular']; foreach($sortOptions as $key=>$label): $qs=http_build_query(array_merge($_GET,['sort'=>$key,'page'=>1])); ?><option value="?<?= $qs ?>" <?= $sort===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div></div>
            <?php if (empty($products)): ?>
                <div class="empty-state card border-0 shadow-sm"><i class="bi bi-search"></i><h3>No products found</h3><p>Try another search, category, or sort option.</p><a href="/products.php" class="btn btn-primary">Reset filters</a></div>
            <?php else: ?>
                <div class="row g-4"><?php foreach ($products as $prod): ?><div class="col-6 col-md-4"><?php include __DIR__ . '/includes/_product_card.php'; ?></div><?php endforeach; ?></div>
                <?php if ($pag['total_pages'] > 1): ?><nav class="mt-5 d-flex justify-content-center"><ul class="pagination"><?php for($i=1;$i<=$pag['total_pages'];$i++): $qs=http_build_query(array_merge($_GET,['page'=>$i])); ?><li class="page-item <?= $i===$pag['current']?'active':'' ?>"><a class="page-link" href="?<?= $qs ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
