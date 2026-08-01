<?php
session_start();
require_once 'db.php';
require_once 'audit_log_helper.php';

header('Content-Type: application/json');
// ─────────────────────────────────────────────────────────────────────────────
// LOGIN — authenticates against the `staff` table (see staff_schema.sql).
//
// This replaces the old hardcoded $STAFF array. New encoder accounts created
// from Settings → Manage Staff appear here automatically since they're just
// rows in the same table.
//
// Roles:
//   admin   — Full access: view, register, edit, archive, DELETE, manage staff
//   encoder — Limited access: view, register, edit, archive (no delete, no staff mgmt)
// ─────────────────────────────────────────────────────────────────────────────

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE username COLLATE utf8mb4_bin = ? LIMIT 1");
    $stmt->execute([$username]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff && (int)$staff['is_active'] === 1 && password_verify($password, $staff['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;          // kept for backward-compat guards throughout the app
        $_SESSION['admin_username']  = $staff['username'];
        $_SESSION['admin_role']      = $staff['role'];
        $_SESSION['display_name']    = $staff['display_name'];
        $_SESSION['staff_id']        = $staff['id'];
        $_SESSION['login_time']      = time();

        osca_log_login($pdo, true, $username, $staff);

        echo json_encode([
            'success'      => true,
            'message'      => 'Login successful.',
            'role'         => $staff['role'],
            'display_name' => $staff['display_name'],
        ]);
    } elseif ($staff && (int)$staff['is_active'] === 0) {
        sleep(1);
        osca_log_login($pdo, false, $username, $staff, 'Attempted login on deactivated account');
        echo json_encode(['success' => false, 'message' => 'This account has been deactivated. Contact an administrator.']);
    } else {
        // Constant-time delay to deter brute-force
        sleep(1);
        osca_log_login($pdo, false, $username, null, 'Invalid username or password');
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
} catch (PDOException $e) {
    error_log('[login] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Login error. Please try again.']);
}
?>