<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$actionFilter = trim($_GET['q'] ?? '');

$where  = '1=1';
$params = [];
if ($actionFilter !== '') {
    $where .= ' AND action LIKE ?';
    $params[] = '%' . $actionFilter . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT * FROM audit_logs
    WHERE {$where}
    ORDER BY created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Denetim Kayıtları';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-title">Denetim Kayıtları <span style="color:var(--text-muted); font-size:1rem;">(<?= $total ?>)</span></h1>
    <form method="GET" class="d-flex gap-2">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Aksiyon ara…" value="<?= e($actionFilter) ?>" style="min-width:240px;">
        <button class="btn-glass" style="padding:0.4rem 0.8rem; font-size:0.85rem;"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="glass-card p-0 reveal" style="overflow-x:auto;">
    <table class="table-glass">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Aksiyon</th>
                <th>Aktör</th>
                <th>Hedef</th>
                <th>IP</th>
                <th>Detay</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $row): ?>
                <tr>
                    <td style="font-size:0.85rem; white-space:nowrap;"><?= date('d M Y H:i:s', strtotime($row['created_at'])) ?></td>
                    <td style="font-family:monospace; font-size:0.85rem;"><?= e($row['action']) ?></td>
                    <td style="font-size:0.85rem;"><?= e($row['actor_email'] ?? ('user#' . ($row['user_id'] ?? '?'))) ?></td>
                    <td style="font-size:0.85rem;"><?= e(($row['target_type'] ?? '') . ($row['target_id'] ? '#' . $row['target_id'] : '')) ?></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?= e($row['ip'] ?? '') ?></td>
                    <td style="font-size:0.8rem;">
                        <?php if ($row['payload']): ?>
                            <code style="white-space:pre-wrap; font-size:0.75rem;"><?= e($row['payload']) ?></code>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted);">Kayıt bulunamadı.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pag['total_pages'] > 1): ?>
    <nav class="mt-4 d-flex justify-content-center">
        <ul class="pagination pagination-glass mb-0">
            <?php for ($i = 1; $i <= $pag['total_pages']; $i++):
                $qs = http_build_query(array_merge($_GET, ['page' => $i]));
            ?>
                <li class="page-item <?= $i === $pag['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
