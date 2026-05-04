<?php
/**
 * Core Functions
 */

// ─── Session ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS even behind a reverse proxy / CDN.
    $secure = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
    );
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/security.php';

// ─── URL Helpers ───────────────────────────────────────
function app_base_path(): string {
    static $basePath = null;
    if ($basePath !== null) return $basePath;

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptName), '/');
    if (str_ends_with($basePath, '/admin')) {
        $basePath = rtrim(dirname($basePath), '/');
    }
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }
    return $basePath;
}

function url(string $path = ''): string {
    $path = trim($path);
    if ($path === '') return app_base_path() ?: '/';
    if (preg_match('#^(https?:)?//#i', $path)) return $path;
    return (app_base_path() ?: '') . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return url($path);
}

if (!defined('APP_OUTPUT_REWRITE_STARTED')) {
    define('APP_OUTPUT_REWRITE_STARTED', true);
    ob_start(function (string $html): string {
        $base = app_base_path();
        if ($base === '') return $html;
        return strtr($html, [
            'href="/'   => 'href="' . $base . '/',
            'src="/'    => 'src="' . $base . '/',
            'action="/' => 'action="' . $base . '/',
            "fetch('/"  => "fetch('" . $base . '/',
        ]);
    });
}

// ─── Settings Cache ────────────────────────────────────
function setting(string $key, ?string $default = null): ?string {
    static $cache = [];
    global $pdo;

    if (empty($cache)) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            return $default;
        }
    }

    return $cache[$key] ?? $default;
}

// ─── Escaping ──────────────────────────────────────────
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ─── CSRF ──────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool {
    $token = $_POST['_csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function rotate_csrf(): void {
    unset($_SESSION['_csrf_token']);
    csrf_token();
}

// ─── Flash Messages ────────────────────────────────────
function flash(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

// ─── Redirect ──────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . url($url));
    exit;
}

// ─── Auth Helpers ──────────────────────────────────────
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function login_user(array $user): void {
    global $pdo;
    session_regenerate_id(true);
    rotate_csrf();
    $_SESSION['user_id']            = (int)$user['id'];
    $_SESSION['user_name']          = (string)$user['name'];
    $_SESSION['user_role']          = (string)$user['role'];
    $_SESSION['session_started_at'] = time();

    try {
        $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?")
            ->execute([client_ip(), (int)$user['id']]);
    } catch (PDOException $e) { /* schema may not yet include columns */ }
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('warning', 'Devam etmek için giriş yapın.');
        redirect('/login.php');
    }
}

function require_admin(): void {
    if (!is_logged_in() || !is_admin()) {
        http_response_code(403);
        die('Erişim reddedildi.');
    }
    // Re-validate role from DB (logout if revoked)
    ensure_active_admin_or_logout();
}

/**
 * Rehash the password if its parameters are weaker than current policy.
 * Call right after a successful password_verify().
 */
function maybe_rehash_password(int $userId, string $plainPassword, string $currentHash): void {
    global $pdo;
    if (password_needs_rehash($currentHash, PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
        } catch (PDOException $e) { /* ignore */ }
    }
}

// ─── Slug Generator ────────────────────────────────────
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'ı'=>'i','ğ'=>'g','ü'=>'u','ş'=>'s','ö'=>'o','ç'=>'c',
        'â'=>'a','î'=>'i','û'=>'u',
    ]);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// ─── Price Formatter ───────────────────────────────────
function format_price(float $amount): string {
    $symbol = setting('currency_symbol', '$');
    return $symbol . number_format($amount, 2);
}

// ─── Cart Helpers ──────────────────────────────────────
function get_cart_id(): ?int {
    global $pdo;

    $userId    = current_user_id();
    $sessionId = session_id();

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$sessionId]);
    }

    $cart = $stmt->fetch();
    return $cart ? (int)$cart['id'] : null;
}

function get_or_create_cart(): int {
    global $pdo;

    $cartId = get_cart_id();
    if ($cartId) return $cartId;

    $userId    = current_user_id();
    $sessionId = session_id();

    $stmt = $pdo->prepare("INSERT INTO carts (user_id, session_id) VALUES (?, ?)");
    $stmt->execute([$userId, $sessionId]);
    return (int)$pdo->lastInsertId();
}

function cart_count(): int {
    global $pdo;

    $cartId = get_cart_id();
    if (!$cartId) return 0;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) AS cnt FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

function cart_items(): array {
    global $pdo;

    $cartId = get_cart_id();
    if (!$cartId) return [];

    $stmt = $pdo->prepare("
        SELECT ci.*, p.name, p.slug, p.price, p.sale_price, p.image, p.stock
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
        ORDER BY ci.created_at
    ");
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
}

function cart_total(): float {
    $items = cart_items();
    $total = 0;
    foreach ($items as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        $total += $price * $item['quantity'];
    }
    return $total;
}

function merge_cart_on_login(int $userId): void {
    global $pdo;

    $sessionId = session_id();

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? AND user_id IS NULL LIMIT 1");
    $stmt->execute([$sessionId]);
    $guestCart = $stmt->fetch();

    if (!$guestCart) return;

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userCart = $stmt->fetch();

    if ($userCart) {
        $stmt = $pdo->prepare("
            INSERT INTO cart_items (cart_id, product_id, quantity)
            SELECT ?, product_id, quantity FROM cart_items WHERE cart_id = ?
            ON DUPLICATE KEY UPDATE quantity = cart_items.quantity + VALUES(quantity)
        ");
        $stmt->execute([$userCart['id'], $guestCart['id']]);
        $pdo->prepare("DELETE FROM carts WHERE id = ?")->execute([$guestCart['id']]);
    } else {
        $pdo->prepare("UPDATE carts SET user_id = ?, session_id = NULL WHERE id = ?")->execute([$userId, $guestCart['id']]);
    }
}

// ─── Order Number Generator ───────────────────────────
function generate_order_number(): string {
    return 'ORD-' . strtoupper(date('Ymd')) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

// ─── Pagination ────────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $perPage    = max(1, min(100, $perPage));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $current    = max(1, min($current, $totalPages));
    $offset     = ($current - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $current,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

// ─── Image Upload ──────────────────────────────────────
function upload_image(array $file, string $dir = 'uploads/products'): ?string {
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return null;
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    if (@getimagesize($file['tmp_name']) === false) return null;

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $path = rtrim($dir, '/') . '/' . $filename;

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        @chmod($path, 0644);
        // Defense in depth: ensure uploads/.htaccess denies PHP execution
        $uploadsRoot = dirname(rtrim($dir, '/'));
        $htaccess    = $uploadsRoot . '/.htaccess';
        if (!is_file($htaccess) && is_writable($uploadsRoot)) {
            @file_put_contents($htaccess, "Options -Indexes\n\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\n");
        }
        return $path;
    }
    return null;
}
