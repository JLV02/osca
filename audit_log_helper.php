<?php
/**
 * audit_log_helper.php
 * Records every transaction in the system for the Audit Log (Activity History) feature.
 * Include this alongside notifications_helper.php wherever a transaction happens.
 */

function osca_log_audit(PDO $pdo, string $action, ?string $targetType = null, ?int $targetId = null, ?string $targetName = null, ?string $details = null): void {
    try {
        $staffId     = $_SESSION['staff_id']       ?? null;
        $username    = $_SESSION['admin_username'] ?? null;
        $displayName = $_SESSION['display_name']   ?? null;
        $role        = $_SESSION['admin_role']     ?? null;
        $ip          = $_SERVER['REMOTE_ADDR']     ?? null;

        $stmt = $pdo->prepare("INSERT INTO audit_log
            (staff_id, username, display_name, role, action, target_type, target_id, target_name, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$staffId, $username, $displayName, $role, $action, $targetType, $targetId, $targetName, $details, $ip]);
    } catch (Throwable $e) {
        // Never let a logging failure break the real transaction
        error_log('[audit_log] ' . $e->getMessage());
    }
}

/**
 * Special-case logger for login attempts — there's no session yet on a
 * failed attempt, so we pass the looked-up staff row (or null) directly
 * instead of reading from $_SESSION.
 */
function osca_log_login(PDO $pdo, bool $success, string $attemptedUsername, ?array $staffRow = null, ?string $details = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO audit_log
            (staff_id, username, display_name, role, action, target_type, target_id, target_name, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $staffRow['id']           ?? null,
            $staffRow['username']     ?? $attemptedUsername,
            $staffRow['display_name'] ?? null,
            $staffRow['role']         ?? null,
            $success ? 'login_success' : 'login_failed',
            'staff',
            $staffRow['id'] ?? null,
            $staffRow['display_name'] ?? $attemptedUsername,
            $details,
            $ip
        ]);
    } catch (Throwable $e) {
        error_log('[audit_log] ' . $e->getMessage());
    }
}