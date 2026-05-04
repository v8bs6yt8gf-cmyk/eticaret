<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    session_save_path(sys_get_temp_dir());
}
session_start();

const MIN_PHP_VERSION  = '8.1.0';
const APP_SCHEMA_VERSION_INSTALL = 2;

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'json', 'openssl'];
$requiredTables = [
    'settings','users','categories','products','carts','cart_items',
    'coupons','orders','order_items',
    'audit_logs','login_attempts','password_resets',
];

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

function installer_verify_csrf(): bool {
    $token = $_POST['_csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['_installer_csrf'])
        && hash_equals($_SESSION['_installer_csrf'], $token);
}

function detected_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    if (($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on')    return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)            return true;
    return false;
}

function detected_base_url(): string {
    $scheme = detected_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = installer_base_path();
    return $scheme . '://' . $host . $base;
}

function strong_password(string $pw): array {
    $errors = [];
    if (strlen($pw) < 10) $errors[] = '10+ karakter';
    if (!preg_match('/[A-Za-z]/', $pw)) $errors[] = 'harf';
    if (!preg_match('/\d/', $pw))       $errors[] = 'rakam';
    return $errors;
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

function ensure_dir_with_index(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $idx = $dir . '/index.html';
    if (!is_file($idx)) @file_put_contents($idx, '');
}

function write_htaccess_files(string $root): void {
    $rootHt = $root . '/.htaccess';
    $rootContent = "Options -Indexes\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule \"(^|/)\\.git\" - [F,L]\n    RewriteRule ^config/        - [F,L]\n    RewriteRule ^includes/migrations(/|\$) - [F,L]\n    RewriteRule ^installed\\.lock\$ - [F,L]\n    RewriteRule ^schema\\.sql\$    - [F,L]\n</IfModule>\n\n<FilesMatch \"\\.(sql|lock|md|log|env|bak|ini)\$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n</FilesMatch>\n\n<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options \"nosniff\"\n    Header always set X-Frame-Options \"SAMEORIGIN\"\n    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n    Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n</IfModule>\n";
    if (!is_file($rootHt)) @file_put_contents($rootHt, $rootContent);

    $denyAll = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";

    @file_put_contents($root . '/config/.htaccess', $denyAll);
    ensure_dir_with_index($root . '/config');
    @file_put_contents($root . '/includes/.htaccess', $denyAll);
    ensure_dir_with_index($root . '/includes');
    ensure_dir_with_index($root . '/includes/migrations');

    $uploadsHt = "Options -Indexes\n\n<FilesMatch \"\\.(php|phtml|phar|phps|pht|cgi|pl|py|sh|jsp|aspx?|exe|js|html?|svg)\$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n</FilesMatch>\n";
    @file_put_contents($root . '/uploads/.htaccess', $uploadsHt);
    ensure_dir_with_index($root . '/uploads');
    ensure_dir_with_index($root . '/uploads/products');
}

$selfDeleteRequest = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['action'] ?? '') === 'delete_installer';

if ($selfDeleteRequest) {
    if (!installer_verify_csrf()) {
        http_response_code(400);
        exit('CSRF dogrulanamadi.');
    }
    $deleted = [];
    $failed  = [];
    foreach ([__DIR__ . '/kurulum_test.php', __DIR__ . '/kurulum.php'] as $file) {
        if (is_file($file)) {
            if (@unlink($file)) $deleted[] = basename($file);
            else                 $failed[]  = basename($file);
        }
    }
    $migDir = __DIR__ . '/includes/migrations';
    if (is_dir($migDir)) {
        foreach (glob($migDir . '/*') as $f) @unlink($f);
        @rmdir($migDir);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset=utf-8><title>Installer silindi</title>';
    echo '<div style="font-family:system-ui;max-width:600px;margin:60px auto;padding:24px;background:#f8f9fa;border-radius:12px">';
    echo '<h2>Installer dosyalari temizlendi</h2>';
    if ($deleted) echo '<p>Silinenler: <code>' . h(implode(', ', $deleted)) . '</code></p>';
    if ($failed)  echo '<p style="color:#b91c1c">Silinemeyen: <code>' . h(implode(', ', $failed)) . '</code> &mdash; manuel silin.</p>';
    echo '<p><a href="' . h(installer_url('/admin/')) . '">Admin Paneli</a> &middot; <a href="' . h(installer_url('/')) . '">Magaza</a></p>';
    echo '</div>';
    exit;
}

$errors  = [];
$steps   = [];
$success = false;
$locked  = is_file(__DIR__ . '/installed.lock');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$envOk = version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) $envOk = false;
}

$writableChecks = [
    'config/'           => is_writable(__DIR__) || (is_dir(__DIR__ . '/config')           && is_writable(__DIR__ . '/config')),
    'uploads/'          => is_dir(__DIR__ . '/uploads')          ? is_writable(__DIR__ . '/uploads')          : is_writable(__DIR__),
    'uploads/products/' => is_dir(__DIR__ . '/uploads/products') ? is_writable(__DIR__ . '/uploads/products') : is_writable(__DIR__),
];

if ($requestMethod === 'POST' && !$locked && !$selfDeleteRequest) {
    if (!installer_verify_csrf()) {
        $errors[] = 'Guvenlik anahtari gecersiz. Sayfayi yenileyip tekrar deneyin.';
    }

    $siteName = trim((string)($_POST['site_name'] ?? ''));
    $baseUrl  = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
    $dbHost   = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbName   = trim((string)($_POST['db_name'] ?? ''));
    $dbUser   = trim((string)($_POST['db_user'] ?? ''));
    $dbPass   = (string)($_POST['db_pass'] ?? '');
    $createDb = !empty($_POST['create_db']);

    $adminName  = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $adminPass  = (string)($_POST['admin_pass'] ?? '');

    $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    $smtpUser = trim((string)($_POST['smtp_user'] ?? ''));
    $smtpPass = (string)($_POST['smtp_pass'] ?? '');
    $smtpEnc  = ($_POST['smtp_encryption'] ?? 'tls') === 'ssl' ? 'ssl' : 'tls';
    $mailFromEmail = trim((string)($_POST['mail_from_email'] ?? '')) ?: $adminEmail;
    $mailFromName  = trim((string)($_POST['mail_from_name']  ?? '')) ?: $siteName;

    $currencySymbol = trim((string)($_POST['currency_symbol'] ?? 'TL'));

    if (!$envOk)                                $errors[] = 'Sunucu PHP surumu/zorunlu eklentileri uygun degil.';
    if ($siteName === '' || mb_strlen($siteName) > 100) $errors[] = 'Site adi zorunlu (maks. 100 karakter).';
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL))     $errors[] = 'Base URL gecerli olmali.';
    if ($dbHost === '')                          $errors[] = 'DB host zorunlu.';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = 'DB adi sadece harf/rakam/_ icerebilir.';
    if ($dbUser === '')                          $errors[] = 'DB kullanici zorunlu.';
    if ($adminName === '' || mb_strlen($adminName) > 100) $errors[] = 'Admin adi zorunlu.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin e-postasi gecerli olmali.';

    $pwIssues = strong_password($adminPass);
    if ($pwIssues) $errors[] = 'Admin sifresi yetersiz: ' . implode(', ', $pwIssues) . ' icermeli.';

    foreach ($writableChecks as $name => $ok) {
        if (!$ok) $errors[] = "Dizin yazilamaz: {$name}";
    }

    if (!$errors) {
        try {
            $serverDsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($serverDsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            add_step($steps, 'ok', 'MySQL baglantisi', 'Sunucu baglantisi basarili.');

            if ($dbUser === 'root') {
                add_step($steps, 'warning', 'DB kullanicisi', 'root kullaniyorsunuz. Uretimde uygulamaya ozel bir DB kullanicisi olusturun.');
            }

            if ($createDb) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                add_step($steps, 'ok', 'Veritabani', "{$dbName} hazirlandi.");
            }
            $pdo->exec("USE `{$dbName}`");

            $schemaFile = __DIR__ . '/schema.sql';
            if (!is_file($schemaFile)) throw new RuntimeException('schema.sql bulunamadi.');
            foreach (split_sql_statements((string)file_get_contents($schemaFile)) as $statement) {
                $pdo->exec($statement);
            }
            add_step($steps, 'ok', 'Sema', 'Tablolar (guvenlik tablolari dahil) olusturuldu.');

            $tableCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
            foreach ($requiredTables as $table) {
                $tableCheck->execute([$dbName, $table]);
                if ((int)$tableCheck->fetchColumn() === 0) throw new RuntimeException('Eksik tablo: ' . $table);
            }
            add_step($steps, 'ok', 'Tablo dogrulama', count($requiredTables) . ' tablo dogrulandi.');

            $pdo->beginTransaction();
            $settingStmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $settingsToWrite = [
                'site_name'           => $siteName,
                'base_url'            => $baseUrl,
                'admin_email'         => $adminEmail,
                'currency_symbol'     => $currencySymbol,
                'app_env'             => 'production',
                'schema_version'      => (string)APP_SCHEMA_VERSION_INSTALL,
                'mail_enabled'        => $smtpHost !== '' ? '1' : '0',
                'mail_smtp_host'      => $smtpHost,
                'mail_smtp_port'      => (string)$smtpPort,
                'mail_smtp_user'      => $smtpUser,
                'mail_smtp_pass'      => $smtpPass,
                'mail_smtp_encryption'=> $smtpEnc,
                'mail_from_email'     => $mailFromEmail,
                'mail_from_name'      => $mailFromName,
            ];
            foreach ($settingsToWrite as $key => $value) {
                $settingStmt->execute([$key, $value]);
            }
            add_step($steps, 'ok', 'Ayarlar', 'Site, mail ve surum ayarlari yazildi.');

            $adminCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $adminCheck->execute([$adminEmail]);
            $adminId = $adminCheck->fetchColumn();
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            if ($adminId) {
                $pdo->prepare("UPDATE users SET role='admin', is_active=1, password=? WHERE id = ?")
                    ->execute([$hash, $adminId]);
                add_step($steps, 'warning', 'Admin', 'Bu e-posta vardi; admin yapildi, sifre guncellendi.');
            } else {
                $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)")
                    ->execute([$adminName, $adminEmail, $hash]);
                $adminId = (int)$pdo->lastInsertId();
                add_step($steps, 'ok', 'Admin', 'Admin kullanicisi olusturuldu.');
            }

            try {
                $pdo->prepare("UPDATE users SET password_changed_at = NOW() WHERE id = ?")->execute([$adminId]);
            } catch (PDOException $e) { /* ignore */ }

            try {
                $pdo->prepare("INSERT INTO audit_logs (user_id, actor_email, action, ip, user_agent) VALUES (?, ?, 'install.completed', ?, ?)")
                    ->execute([
                        $adminId, $adminEmail,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    ]);
            } catch (PDOException $e) { /* ignore */ }

            $pdo->commit();

            $configDir = __DIR__ . '/config';
            if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
                throw new RuntimeException('config dizini olusturulamadi.');
            }
            $appKey = bin2hex(random_bytes(32));
            $config = "<?php\n"
                . "/* AUTO-GENERATED. .gitignore icinde olmali. */\n"
                . "return [\n"
                . "    'db_host' => " . var_export($dbHost, true) . ",\n"
                . "    'db_name' => " . var_export($dbName, true) . ",\n"
                . "    'db_user' => " . var_export($dbUser, true) . ",\n"
                . "    'db_pass' => " . var_export($dbPass, true) . ",\n"
                . "    'app_env' => 'production',\n"
                . "    'app_key' => " . var_export($appKey, true) . ",\n"
                . "];\n";
            if (file_put_contents($configDir . '/local.php', $config, LOCK_EX) === false) {
                throw new RuntimeException('config/local.php yazilamadi.');
            }
            @chmod($configDir . '/local.php', 0600);
            add_step($steps, 'ok', 'Konfigurasyon', 'config/local.php yazildi (0600). Random APP_KEY uretildi.');

            write_htaccess_files(__DIR__);
            add_step($steps, 'ok', 'Sertlestirme', '.htaccess + index.html (config, includes, uploads) yerlestirildi.');

            file_put_contents(__DIR__ . '/installed.lock', date('c'), LOCK_EX);
            @chmod(__DIR__ . '/installed.lock', 0640);
            add_step($steps, 'ok', 'Kilit', 'installed.lock olusturuldu.');

            $success = true;

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$old = static fn(string $key, string $default = ''): string => h((string)($_POST[$key] ?? $default));
$detectedBase = detected_base_url();
$isHttps      = detected_https();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Magaza Kurulumu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-2">E-Ticaret Kurulumu</h1>
                    <p class="text-secondary mb-4">Veritabani, ayarlar, admin hesabi ve dosya sertlestirmesini tek seferde yapar.</p>

                    <?php if ($locked && !$success): ?>
                        <div class="alert alert-warning"><strong>Kurulum kilitli.</strong> installed.lock mevcut.</div>
                        <a href="<?= h(installer_url('/admin/')) ?>" class="btn btn-primary">Admin Paneli</a>
                        <a href="<?= h(installer_url('/')) ?>" class="btn btn-outline-primary">Magazaya Git</a>
                        <hr class="my-4">
                        <h6 class="fw-bold">Installer dosyalarini sil</h6>
                        <p class="text-secondary small">Guvenlik icin bu dosyalari sunucudan kaldirin.</p>
                        <form method="POST" onsubmit="return confirm('kurulum.php ve kurulum_test.php silinecek. Devam?');">
                            <input type="hidden" name="_csrf_token" value="<?= h(installer_csrf()) ?>">
                            <input type="hidden" name="action" value="delete_installer">
                            <button type="submit" class="btn btn-danger">Installer'i sil</button>
                        </form>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><strong>Kurulum tamamlandi.</strong></div>
                        <?php foreach ($steps as $step): ?>
                            <div class="border rounded p-3 mb-2">
                                <span class="badge text-bg-<?= $step['type']==='ok'?'success':'warning' ?> me-2"><?= h(strtoupper($step['type'])) ?></span>
                                <strong><?= h($step['title']) ?></strong>
                                <div class="text-secondary small"><?= h($step['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <h6 class="fw-bold mt-4">Simdi yapin</h6>
                        <ul class="text-secondary">
                            <li>Asagidaki dugmeyle <strong>installer dosyalarini silin</strong>.</li>
                            <li>HTTPS sertifikasi kurun (Let's Encrypt vb.).</li>
                            <li>HTTPS hazir oldugunda .htaccess'teki HTTPS yonlendirme blogunu acin.</li>
                            <li>SMTP girilmediyse parola sifirlama linkleri Admin -&gt; Denetim Kayitlari'na duser.</li>
                        </ul>
                        <div class="d-flex gap-2 mt-4 flex-wrap">
                            <form method="POST" class="d-inline" onsubmit="return confirm('kurulum.php ve kurulum_test.php silinecek. Devam?');">
                                <input type="hidden" name="_csrf_token" value="<?= h(installer_csrf()) ?>">
                                <input type="hidden" name="action" value="delete_installer">
                                <button type="submit" class="btn btn-danger">Installer dosyalarini sil</button>
                            </form>
                            <a href="<?= h(installer_url('/admin/')) ?>" class="btn btn-primary">Admin Paneli</a>
                            <a href="<?= h(installer_url('/')) ?>" class="btn btn-outline-primary">Magazaya Git</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
                        <h6 class="fw-bold">Sistem Kontrolu</h6>
                        <div class="border rounded p-3 mb-3 bg-white">
                            <div class="py-1 small">
                                <?= version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">FAIL</span>' ?>
                                PHP <?= h(MIN_PHP_VERSION) ?>+ <span class="text-muted">(<?= h(PHP_VERSION) ?>)</span>
                            </div>
                            <?php foreach ($requiredExtensions as $ext): ?>
                                <div class="py-1 small">
                                    <?= extension_loaded($ext) ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">FAIL</span>' ?>
                                    Eklenti: <code><?= h($ext) ?></code>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($writableChecks as $name => $ok): ?>
                                <div class="py-1 small">
                                    <?= $ok ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-warning">YAZILAMAZ</span>' ?>
                                    <code><?= h($name) ?></code>
                                </div>
                            <?php endforeach; ?>
                            <div class="py-1 small">
                                <?= $isHttps ? '<span class="badge text-bg-success">HTTPS</span>' : '<span class="badge text-bg-warning">HTTP</span>' ?>
                                <?= $isHttps ? 'aktif' : 'aktif degil &mdash; kurulum sonrasi mutlaka acin' ?>
                            </div>
                        </div>
                        <form method="POST" class="row g-3" autocomplete="off">
                            <input type="hidden" name="_csrf_token" value="<?= h(installer_csrf()) ?>">
                            <div class="col-12"><h6 class="fw-bold mt-2">Site</h6></div>
                            <div class="col-md-6"><label class="form-label">Site Adi</label><input name="site_name" class="form-control" required maxlength="100" value="<?= $old('site_name', 'Magazam') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Base URL</label><input type="url" name="base_url" class="form-control" required value="<?= $old('base_url', $detectedBase) ?>"><div class="form-text small">Otomatik tespit edildi.</div></div>
                            <div class="col-md-3"><label class="form-label">Para Birimi</label><input name="currency_symbol" class="form-control" maxlength="5" value="<?= $old('currency_symbol', 'TL') ?>"></div>
                            <div class="col-12"><hr><h6 class="fw-bold mt-2">Veritabani</h6></div>
                            <div class="col-md-6"><label class="form-label">DB Host</label><input name="db_host" class="form-control" required value="<?= $old('db_host', 'localhost') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Adi</label><input name="db_name" class="form-control" required pattern="[A-Za-z0-9_]+" value="<?= $old('db_name', 'etic_db') ?>"></div>
                            <div class="col-md-6"><label class="form-label">DB Kullanici</label><input name="db_user" class="form-control" required value="<?= $old('db_user', '') ?>"><div class="form-text small">Uretimde root kullanmayin.</div></div>
                            <div class="col-md-6"><label class="form-label">DB Sifre</label><input type="password" name="db_pass" class="form-control" autocomplete="new-password"></div>
                            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="create_db" value="1" id="createDb" checked><label class="form-check-label" for="createDb">Veritabani yoksa olustur</label></div></div>
                            <div class="col-12"><hr><h6 class="fw-bold mt-2">Admin Hesabi</h6></div>
                            <div class="col-md-4"><label class="form-label">Adi</label><input name="admin_name" class="form-control" required maxlength="100" value="<?= $old('admin_name', 'Admin') ?>"></div>
                            <div class="col-md-4"><label class="form-label">E-posta</label><input type="email" name="admin_email" class="form-control" required value="<?= $old('admin_email', '') ?>" autocomplete="username"></div>
                            <div class="col-md-4"><label class="form-label">Sifre</label><input type="password" name="admin_pass" class="form-control" required minlength="10" autocomplete="new-password"><div class="form-text small">10+ karakter, harf+rakam.</div></div>
                            <div class="col-12"><hr><h6 class="fw-bold mt-2">Mail (opsiyonel)</h6><p class="small text-secondary mb-2">SMTP girersen parola sifirlama mailleri gonderilir.</p></div>
                            <div class="col-md-6"><label class="form-label">SMTP Host</label><input name="smtp_host" class="form-control" value="<?= $old('smtp_host', '') ?>" placeholder="orn. smtp.gmail.com"></div>
                            <div class="col-md-3"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= $old('smtp_port', '587') ?>"></div>
                            <div class="col-md-3"><label class="form-label">Sifreleme</label><select name="smtp_encryption" class="form-select"><option value="tls" <?= ($_POST['smtp_encryption'] ?? 'tls')==='tls'?'selected':'' ?>>TLS</option><option value="ssl" <?= ($_POST['smtp_encryption'] ?? '')==='ssl'?'selected':'' ?>>SSL</option></select></div>
                            <div class="col-md-6"><label class="form-label">SMTP Kullanici</label><input name="smtp_user" class="form-control" value="<?= $old('smtp_user', '') ?>" autocomplete="off"></div>
                            <div class="col-md-6"><label class="form-label">SMTP Sifre</label><input type="password" name="smtp_pass" class="form-control" autocomplete="new-password"></div>
                            <div class="col-md-6"><label class="form-label">Gonderici E-posta</label><input type="email" name="mail_from_email" class="form-control" value="<?= $old('mail_from_email', '') ?>" placeholder="admin@site.com"></div>
                            <div class="col-md-6"><label class="form-label">Gonderici Adi</label><input name="mail_from_name" class="form-control" value="<?= $old('mail_from_name', '') ?>" placeholder="Magazam"></div>
                            <div class="col-12 mt-4"><button class="btn btn-primary btn-lg w-100" type="submit">Kurulumu Baslat</button></div>
                        </form>
                        <p class="text-center text-secondary small mt-4 mb-0">Kurulum tamamlanninca guvenlik icin bu dosya tek tikla silinebilir.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
