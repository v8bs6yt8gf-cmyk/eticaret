<?php
/**
 * Database Configuration
 * Loads credentials from config/local.php (created by installer)
 */

if (!file_exists(__DIR__ . '/local.php')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptName), '/');
    if (str_ends_with($basePath, '/admin')) {
        $basePath = rtrim(dirname($basePath), '/');
    }
    if ($basePath === '/' || $basePath === '.') $basePath = '';
    header('Location: ' . $basePath . '/kurulum.php');
    exit;
}

$config = require __DIR__ . '/local.php';
$config['db_host'] = getenv('DB_HOST') ?: ($config['db_host'] ?? 'localhost');
$config['db_name'] = getenv('DB_NAME') ?: ($config['db_name'] ?? '');
$config['db_user'] = getenv('DB_USER') ?: ($config['db_user'] ?? '');
$config['db_pass'] = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($config['db_pass'] ?? '');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_name']
    );
    
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('[db connect] ' . $e->getMessage());
    if (($config['app_env'] ?? 'production') === 'development') {
        die('Database Error: ' . $e->getMessage());
    }
    die('A database error occurred. Please try again later.');
}

// Run pending schema migrations once per request
require_once __DIR__ . '/../includes/migrations.php';
run_pending_migrations($pdo);
