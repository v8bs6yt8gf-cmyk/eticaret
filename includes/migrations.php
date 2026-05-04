<?php
/**
 * Schema migrations runner.
 * Idempotent: tracks current version in settings.schema_version.
 */

const APP_SCHEMA_VERSION = 2;

function run_pending_migrations(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='schema_version' LIMIT 1");
        $stmt->execute();
        $current = (int)($stmt->fetchColumn() ?: 1);
    } catch (PDOException $e) {
        // settings table doesn't exist yet — installer hasn't run
        return;
    }

    if ($current >= APP_SCHEMA_VERSION) return;

    $migrationsDir = __DIR__ . '/migrations';
    $files = [
        2 => $migrationsDir . '/0002_security_hardening.sql',
    ];

    for ($v = $current + 1; $v <= APP_SCHEMA_VERSION; $v++) {
        if (!isset($files[$v]) || !is_file($files[$v])) continue;
        $sql = (string)file_get_contents($files[$v]);

        // Split by semicolon at line ends; ignore comment-only lines
        $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) continue;
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // ALTER TABLE on existing column fails on plain MySQL <8.0.29 — accept duplicate column / table errors
                $msg = $e->getMessage();
                if (
                    !str_contains($msg, 'Duplicate column') &&
                    !str_contains($msg, 'already exists') &&
                    !str_contains($msg, 'Duplicate key name')
                ) {
                    error_log("[migrate v{$v}] " . $msg);
                }
            }
        }
    }

    try {
        $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([(string)APP_SCHEMA_VERSION]);
    } catch (PDOException $e) {
        error_log('[migrate version write] ' . $e->getMessage());
    }
}
