<?php
/**
 * Installation Test — kurulum_test.php
 * Checks that the system is properly installed.
 * Does NOT include project files or connect unless config exists.
 */
$checks = [];

// 1. Check PHP version
$checks[] = [
    'label' => 'PHP 8.2+',
    'pass'  => version_compare(PHP_VERSION, '8.2.0', '>='),
    'value' => PHP_VERSION,
];

// 2. Check PDO MySQL
$checks[] = [
    'label' => 'PDO MySQL Extension',
    'pass'  => extension_loaded('pdo_mysql'),
    'value' => extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing',
];

// 3. Check config/local.php
$configExists = file_exists(__DIR__ . '/config/local.php');
$checks[] = [
    'label' => 'config/local.php',
    'pass'  => $configExists,
    'value' => $configExists ? 'Found' : 'Not found',
];

// 4. Check installed.lock
$lockExists = file_exists(__DIR__ . '/installed.lock');
$checks[] = [
    'label' => 'installed.lock',
    'pass'  => $lockExists,
    'value' => $lockExists ? 'Found' : 'Not found',
];

// 5. Check schema.sql
$schemaExists = file_exists(__DIR__ . '/schema.sql');
$checks[] = [
    'label' => 'schema.sql',
    'pass'  => $schemaExists,
    'value' => $schemaExists ? 'Found' : 'Not found',
];

// 6. Check uploads directory
$uploadsDir = __DIR__ . '/uploads/products';
$uploadsWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
$checks[] = [
    'label' => 'uploads/products writable',
    'pass'  => $uploadsWritable,
    'value' => $uploadsWritable ? 'Writable' : 'Not writable or missing',
];

// 7. Check DB connection
$dbOk = false;
$dbMsg = 'Skipped (no config)';
if ($configExists) {
    try {
        $config = require __DIR__ . '/config/local.php';
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $dbOk = true;
        $dbMsg = 'Connected';
    } catch (PDOException $e) {
        $dbMsg = $e->getMessage();
    }
}
$checks[] = [
    'label' => 'Database connection',
    'pass'  => $dbOk,
    'value' => $dbMsg,
];

// 8. Check settings table
$settingsOk = false;
$settingsMsg = 'Skipped';
if ($dbOk) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
        $count = $stmt->fetchColumn();
        $settingsOk = $count > 0;
        $settingsMsg = "{$count} entries";
    } catch (PDOException $e) {
        $settingsMsg = $e->getMessage();
    }
}
$checks[] = [
    'label' => 'Settings table',
    'pass'  => $settingsOk,
    'value' => $settingsMsg,
];

// 9. Check site_name in settings
$siteNameOk = false;
$siteNameMsg = 'Skipped';
if ($dbOk) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $siteNameOk = !empty($val);
        $siteNameMsg = $siteNameOk ? $val : 'Empty';
    } catch (PDOException $e) {
        $siteNameMsg = $e->getMessage();
    }
}
$checks[] = [
    'label' => 'site_name setting',
    'pass'  => $siteNameOk,
    'value' => $siteNameMsg,
];

// 10. Check admin user
$adminOk = false;
$adminMsg = 'Skipped';
if ($dbOk) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $count = $stmt->fetchColumn();
        $adminOk = $count > 0;
        $adminMsg = "{$count} admin(s)";
    } catch (PDOException $e) {
        $adminMsg = $e->getMessage();
    }
}
$checks[] = [
    'label' => 'Admin user',
    'pass'  => $adminOk,
    'value' => $adminMsg,
];

$allPassed = count(array_filter($checks, fn($c) => !$c['pass'])) === 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="public/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="floating-bg" aria-hidden="true">
    <div class="floating-orb orb-1"></div>
    <div class="floating-orb orb-2"></div>
</div>

<div class="installer-wrap">
    <div class="glass-card installer-card">
        <h2 class="installer-title mb-1">
            <?php if ($allPassed): ?>
                <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
            <?php else: ?>
                <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
            <?php endif; ?>
        </h2>
        <h2 class="installer-title">Installation Test</h2>
        <p class="auth-subtitle mb-4">
            <?= $allPassed ? 'All checks passed. Your system is ready!' : 'Some checks failed. Please review below.' ?>
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

        <div class="mt-4 d-flex gap-2 justify-content-center">
            <?php if ($allPassed): ?>
                <a href="/" class="btn-gradient">Visit Store</a>
                <a href="/admin/" class="btn-glass">Admin Panel</a>
            <?php else: ?>
                <a href="/kurulum.php" class="btn-gradient">Run Installer</a>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
