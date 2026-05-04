<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    require_once __DIR__ . '/config/db.php';
    audit_log('logout', 'user', current_user_id());
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(dirname($scriptName), '/');
if ($basePath === '/' || $basePath === '.') $basePath = '';
header('Location: ' . $basePath . '/login.php');
exit;
