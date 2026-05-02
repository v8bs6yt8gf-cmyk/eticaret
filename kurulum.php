<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    session_save_path(sys_get_temp_dir());
}
session_start();

const MIN_PHP_VERSION = '8.1.0';
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'json', 'openssl'];
$requiredTables = ['settings','users','categories','products','carts','cart_items','coupons','orders','order_items'];

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function installer_base_path(): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptName), '/');
    return ($basePath === '/' || $basePath === '.') ? '' : $basePath;
}

function installer_url(string $path): string {
    return (installer_base_path() ?: '') . '/' . ltrim($path, '/');
}

function installer_csrf(): string {
    if (empty($_SESSION['_installer_csrf'])) {
        $_SESSION['_installer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_installer_csrf'];
}

function split_sql_statements(string $sql): array {
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $items = array_filter(array_map('trim', explode(';', $sql)));
    return array_values(array_map(static function (string $statement): string {
        if (stripos($statement, 'INSERT INTO `settings`') === 0) {
            return preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $statement, 1) ?? $statement;
        }
        return $statement;
    }, $items));
}

function add_step(array &$steps, string $type, string $title, string $message): void {
    $steps[] = ['type' => $type, 'title' => $title, 'message' => $message];
}

$errors = [];
$steps = [];
$success = false;
$locked = is_file(__DIR__ . '/installed.lock');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$envOk = version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) $envOk = false;
}

if ($requestMethod === 'POST' && !$locked) {
    if (!hash_equals($_SESSION['_installer_csrf'] ?? '', (string)($_POST['_csrf_token'] ?? ''))) {
        $errors[] = 'Güvenlik anahtarı geçersiz. Sayfayı yenileyip tekrar deneyin.';
    }

    $siteName = trim((string)($_POST['site_name'] ?? ''));
    $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $createDb = !empty($_POST['create_db']);
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!$envOk) $errors[] = 'Sunucu PHP sürümü veya zorunlu eklentiler için uygun değil.';
    if ($siteName === '' || mb_strlen($siteName) > 100) $errors[] = 'Site adı zorunludur ve 100 karakteri geçmemelidir.';
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) $errors[] = 'Base URL geçerli olmalıdır. Örnek: https://site.com/etic';
    if ($dbHost === '') $errors[] = 'Veritabanı host alanı zorunludur.';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = 'Veritabanı adı sadece harf, rakam ve alt çizgi içerebilir.';
    if ($dbUser === '') $errors[] = 'Veritabanı kullanıcı adı zorunludur.';
    if ($adminName === '' || mb_strlen($adminName) > 100) $errors[] = 'Admin adı zorunludur ve 100 karakteri geçmemelidir.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin e-posta adresi geçerli olmalıdır.';
    if (strlen($adminPass) < 8) $errors[] = 'Admin şifresi en az 8 karakter olmalıdır.';

    if (!$errors) {
        try {
            $serverDsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($serverDsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            add_step($steps, 'ok', 'MySQL bağlantısı', 'Sunucu bağlantısı başarılı.');

            if ($createDb) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                add_step($steps, 'ok', 'Veritabanı', "{$dbName} hazırlandı.");
            }
            $pdo->exec("USE `{$dbName}`");

            $schemaFile = __DIR__ . '/schema.sql';
            if (!is_file($schemaFile)) throw new RuntimeException('schema.sql bulunamadı.');
            foreach (split_sql_statements((string)file_get_contents($schemaFile)) as $statement) {
                $pdo->exec($statement);
            }
            add_step($steps, 'ok', 'Şema', 'Tablolar idempotent şekilde oluşturuldu/doğrulandı.');

            $tableCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
            foreach ($requiredTables as $table) {
                $tableCheck->execute([$dbName, $table]);
                if ((int)$tableCheck->fetchColumn() === 0) throw new RuntimeException('Eksik tablo: ' . $table);
            }

            $pdo->beginTransaction();
            $settingStmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach (['site_name'=>$siteName,'base_url'=>$baseUrl,'admin_email'=>$adminEmail,'app_env'=>'production'] as $key => $value) {
                $settingStmt->execute([$key, $value]);
            }

            $adminCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $adminCheck->execute([$adminEmail]);
            $adminId = $adminCheck->fetchColumn();
            if ($adminId) {
                $pdo->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE id = ?")->execute([$adminId]);
                add_step($steps, 'warning', 'Admin', 'E-posta mevcut olduğu için kullanıcı admin yapıldı/aktif edildi.');
            } else {
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)")->execute([$adminName, $adminEmail, $hash]);
                add_step($steps, 'ok', 'Admin', 'Admin kullanıcısı oluşturuldu.');
            }
            $pdo->commit();

            $configDir = __DIR__ . '/config';
            if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) throw new RuntimeException('config dizini oluşturulamadı.');
            $config = "<?php\nreturn [\n"
                . "    'db_host' => " . var_export($dbHost, true) . ",\n"
                . "    'db_name' => " . var_export($dbName, true) . ",\n"
                . "    'db_user' => " . var_export($dbUser, true) . ",\n"
                . "    'db_pass' => " . var_export($dbPass, true) . ",\n"
                . "    'app_env' => 'production',\n"
                . "];\n";
            if (file_put_contents($configDir . '/local.php', $config, LOCK_EX) === false) throw new RuntimeException('config/local.php yazılamadı.');
            @chmod($configDir . '/local.php', 0640);

            $uploadDir = __DIR__ . '/uploads/products';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) throw new RuntimeException('uploads/products oluşturulamadı.');
            if (!is_file(__DIR__ . '/uploads/.htaccess') && is_writable(__DIR__ . '/uploads')) {
                file_put_contents(__DIR__ . '/uploads/.htaccess', "Options -Indexes\n\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\n", LOCK_EX);
            }

            file_put_contents(__DIR__ . '/installed.lock', date('c'), LOCK_EX);
            @chmod(__DIR__ . '/installed.lock', 0640);
            add_step($steps, 'ok', 'Kilit', 'installed.lock oluşturuldu.');
            $success = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$old = static fn(string $key, string $default = ''): string => h((string)($_POST[$key] ?? $default));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mağaza Kurulumu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-2">E-Ticaret Kurulumu</h1>
                    <p class="text-secondary mb-4">Veritabanı, ayarlar ve admin hesabını güvenli biçimde hazırlar.</p>

                    <?php if ($locked): ?>
                        <div class="alert alert-warning"><strong>Kurulum kilitli.</strong> <code>installed.lock</code> mevcut. Üretimde <code>kurulum.php</code> dosyasını silin veya sunucudan engelleyin.</div>
                        <a href="<?= h(installer_url('/')) ?>" class="btn btn-primary">Mağazaya Git</a>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><strong>Kurulum tamamlandı.</strong> Sonraki adım: <code>kurulum.php</code> ve <code>kurulum_test.php</code> dosyalarını silin veya erişime kapatın.</div>
                        <?php foreach ($steps as $step): ?>
                            <div class="border rounded p-3 mb-2"><span class="badge text-bg-<?= $step['type'] === 'ok' ? 'success' : 'warning' ?> me-2"><?= h(strtoupper($step['type'])) ?></span><strong><?= h($step['title']) ?></strong><div class="text-secondary small"><?= h($step['message']) ?></div></div>
                        <?php endforeach; ?>
                        <div class="d-flex gap-2 mt-4"><a href="<?= h(installer_url('/')) ?>" class="btn btn-primary">Mağazaya Git</a><a href="<?= h(installer_url('/admin/')) ?>" class="btn btn-outline-primary">Admin Paneli</a></div>
                    <?php else: ?>
                        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
                        <div class="table-responsive mb-4"><table class="table table-sm"><tbody>
                            <tr><td>PHP <?= h(MIN_PHP_VERSION) ?>+</td><td class="text-end"><?= version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Hata</span>' ?></td></tr>
                            <?php foreach ($requiredExtensions as $extension): ?><tr><td><?= h($extension) ?></td><td class="text-end"><?= extension_loaded($extension) ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Eksik</span>' ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="_csrf_token" value="<?= h(installer_csrf()) ?>">
                            <div class="col-md-6"><label class="form-label">Site Adı</label><input name="site_name" class="form-control" required maxlength="100" value="<?= $old('site_name', 'Mağazam') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Base URL</label><input type="url" name="base_url" class="form-control" required value="<?= $old('base_url', 'http://localhost/etic') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Host</label><input name="db_host" class="form-control" required value="<?= $old('db_host', 'localhost') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Adı</label><input name="db_name" class="form-control" required pattern="[A-Za-z0-9_]+" value="<?= $old('db_name', 'ecommerce_db') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Kullanıcı</label><input name="db_user" class="form-control" required value="<?= $old('db_user', 'root') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Şifre</label><input type="password" name="db_pass" class="form-control" autocomplete="new-password"></div>
                            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="create_db" value="1" id="createDb" checked><label class="form-check-label" for="createDb">Veritabanı yoksa oluştur</label></div></div>
                            <div class="col-md-4"><label class="form-label">Admin Adı</label><input name="admin_name" class="form-control" required maxlength="100" value="<?= $old('admin_name', 'Admin') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Admin E-posta</label><input type="email" name="admin_email" class="form-control" required value="<?= $old('admin_email', 'admin@example.com') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Admin Şifre</label><input type="password" name="admin_pass" class="form-control" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-2"></i>Kurulumu Başlat</button></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
