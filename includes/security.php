<?php
/**
 * Security helpers: rate limiting, audit logging, password reset tokens,
 * fresh-from-DB role validation, allowlist redirect.
 */

// ─── IP detection (proxy aware) ────────────────────────
function client_ip(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP']       ?? null,
        $_SERVER['REMOTE_ADDR']          ?? null,
    ];
    foreach ($candidates as $c) {
        if (!$c) continue;
        $ip = trim(explode(',', $c)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

function user_agent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

// ─── Rate limiting (DB-backed) ─────────────────────────
/**
 * Record a login attempt and return true if the identifier is currently throttled.
 * Identifier examples: "ip:1.2.3.4", "email:foo@bar.com".
 */
function login_attempts_record(string $identifier, bool $success): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO login_attempts (identifier, success) VALUES (?, ?)")
            ->execute([$identifier, $success ? 1 : 0]);
        // Garbage collect rows older than 24h occasionally
        if (random_int(1, 100) === 1) {
            $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 24 HOUR)");
        }
    } catch (PDOException $e) {
        error_log('[login_attempts_record] ' . $e->getMessage());
    }
}

/**
 * Returns number of failed attempts for identifier within the last $minutes minutes.
 */
function login_attempts_recent_failures(string $identifier, int $minutes = 15): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE identifier = ? AND success = 0
               AND attempted_at >= (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$identifier, $minutes]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function login_attempts_clear(string $identifier): void {
    global $pdo;
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$identifier]);
    } catch (PDOException $e) { /* ignore */ }
}

/**
 * Composite throttle: returns null if allowed, or seconds remaining if blocked.
 * Blocks per-IP after 10 failures in 15 min, per-email after 5 in 15 min.
 */
function login_throttle_check(string $email): ?int {
    $ipFails    = login_attempts_recent_failures('ip:' . client_ip(), 15);
    $emailFails = login_attempts_recent_failures('email:' . strtolower($email), 15);

    if ($ipFails >= 10 || $emailFails >= 5) {
        // Hard block 15 min
        return 15 * 60;
    }
    return null;
}

// ─── Audit logging ─────────────────────────────────────
function audit_log(string $action, ?string $targetType = null, ?int $targetId = null, ?array $payload = null): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, actor_email, action, target_type, target_id, payload, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $userId = $_SESSION['user_id'] ?? null;
        $email  = null;
        if ($userId) {
            try {
                $s = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                $s->execute([$userId]);
                $email = $s->fetchColumn() ?: null;
            } catch (PDOException $e) { /* ignore */ }
        }
        $stmt->execute([
            $userId,
            $email,
            $action,
            $targetType,
            $targetId,
            $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            client_ip(),
            user_agent(),
        ]);
    } catch (PDOException $e) {
        error_log('[audit_log] ' . $e->getMessage());
    }
}

// ─── Fresh role check (DB-backed) ──────────────────────
/**
 * Verify the session user still has admin role and is active.
 * Cached per-request in static var, but always re-checks on first call.
 */
function ensure_active_admin_or_logout(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (empty($_SESSION['user_id'])) return; // require_admin handles this

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        $row = null;
    }

    if (!$row || $row['role'] !== 'admin' || !$row['is_active']) {
        // Force logout
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        http_response_code(403);
        exit('Erişim reddedildi. Hesabınız artık admin değil veya devre dışı.');
    }
}

// ─── Last-admin lockout protection ─────────────────────
function active_admin_count(): int {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function is_only_remaining_admin(int $userId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || $row['role'] !== 'admin' || !$row['is_active']) return false;
        return active_admin_count() <= 1;
    } catch (PDOException $e) {
        return false;
    }
}

// ─── Password reset tokens ─────────────────────────────
function password_reset_create(int $userId): string {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expires = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

    // Invalidate older unused tokens
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
        ->execute([$userId]);

    $pdo->prepare(
        "INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
         VALUES (?, ?, ?, ?)"
    )->execute([$userId, $hash, $expires, client_ip()]);

    return $token; // raw token, share once with user
}

function password_reset_consume(string $token): ?int {
    global $pdo;
    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) return null;
    $hash = hash('sha256', $token);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return null;
        }
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
            ->execute([$row['id']]);
        $pdo->commit();
        return (int)$row['user_id'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }
}

// ─── Safe internal redirect (allowlist) ────────────────
function safe_internal_path(string $path, string $fallback = '/'): string {
    if ($path === '' ) return $fallback;
    // Reject any scheme/host/protocol-relative
    if (preg_match('#^(?:https?:)?//#i', $path)) return $fallback;
    // Reject CRLF or null bytes
    if (preg_match("/[\r\n\x00]/", $path)) return $fallback;
    if ($path[0] !== '/') $path = '/' . $path;
    return $path;
}
