<?php
/**
 * Installation Test — kurulum_test.php
 * Sadece kurulum tamamlanmadan önce kullanılabilir.
 * Üretimde bu dosyayı SİLİN.
 */

if (file_exists(__DIR__ . '/installed.lock')) {
    http_response_code(404);
    exit('Not found');
}

$checks = [];

// 1. PHP version
$checks[] = ['label' => 'PHP 8.2+', 'pass' => version_compare(PHP_VERSION, '8.2.0', '>='), 'value' => PHP_VERSION];

// 2. PDO MySQL
$checks[] = ['label' => 'PDO MySQL Extension', 'pass' => extension_loaded('pdo_mysql'),
             'value' => extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing'];

// 3. Other required extensions
foreach (['mbstring','fileinfo','json','openssl'] as $ext) {
    $checks[] = ['label' => "ext: {$ext}", 'pass' => extension_loaded($ext),
                 'value' => extension_loaded($ext) ? 'Loaded' : 'Missing'];
}

// 4. config/local.php
$configExists = file_exists(__DIR__ . '/config/local.php');
$checks[] = ['label' => 'config/local.php', 'pass' => $configExists,
             'value' => $configExists ? 'Found' : 'Not found'];

// 5. schema.sql
$schemaExists = file_exists(__DIR__ . '/schema.sql');
$checks[] = ['label' => 'schema.sql', 'pass' => $schemaExists,
             'value' => $schemaExists ? 'Found' : 'Not found'];

// 6. uploads dir
$uploadsDir = __DIR__ . '/uploads/products';
$uploadsWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
$checks[] = ['label' => 'uploads/products writable', 'pass' => $uploadsWritable,
             'value' => $uploadsWritable ? 'Writable' : 'Not writable or missing'];

$allPassed = count(array_filter($checks, fn($c) => !$c['pass'])) === 0;
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Installation Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="installer-wrap">
    <div class="glass-card installer-card">
        <h2 class="installer-title mb-1">
            <?php if ($allPassed): ?>
                <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
            <?php else: ?>
                <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
            <?php endif; ?>
        </h2>
        <h2 class="installer-title">Kurulum Ön Kontrolü</h2>
        <p class="auth-subtitle mb-4">
            <?= $allPassed ? 'Tüm kontroller geçti.' : 'Bazı kontroller başarısız.' ?>
        </p>

        <?php foreach ($checks as $check): ?>
            <div class="installer-step">
                <span class="step-icon <?= $check['pass'] ? 'done' : 'fail' ?>">
                    <i class="bi <?= $check['pass'] ? 'bi-check-lg' : 'bi-x-lg' ?>"></i>
                </span>
                <div style="flex:1">
                    <strong><?= htmlspecialchars($check['label']) ?></strong>
                    <div class="small" style="color:var(--text-muted)"><?= htmlspecialchars($check['value']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="alert alert-warning mt-4" style="font-size:0.85rem;">
            <i class="bi bi-shield-exclamation me-1"></i>
            Üretim ortamında bu dosyayı (<code>kurulum_test.php</code>) ve <code>kurulum.php</code>'yi silin.
        </div>

        <div class="mt-3 d-flex gap-2 justify-content-center">
            <a href="kurulum.php" class="btn-gradient">Kuruluma Git</a>
        </div>
    </div>
</div>
</body>
</html>
