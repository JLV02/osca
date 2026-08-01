<?php
/**
 * manage_staff.php
 * Admin-only staff account management: list, create encoder, deactivate/reactivate.
 * Called from the Settings modal (dashboard.js: openSettingsModal / createStaffAccount / toggleStaffActive).
 *
 * Every action here requires an authenticated session with role = 'admin'.
 * Encoders get a flat 403-style JSON response — they never see staff data.
 */
session_start();
require_once 'db.php';
require_once 'audit_log_helper.php';

header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────────────────────
function is_staff(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}
function is_admin(): bool {
    return is_staff() && ($_SESSION['admin_role'] ?? '') === 'admin';
}
// ─────────────────────────────────────────────────────────────────────────────

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Only administrators can manage staff accounts.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list_staff') {

    try {
        $stmt = $pdo->query("SELECT id, username, role, display_name, is_active, created_at
                              FROM staff ORDER BY role DESC, username ASC");
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'staff' => $staff]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'create_staff') {

    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    // Role is intentionally NOT taken from client input — this endpoint only
    // creates encoder accounts. Promote to admin directly in the database if needed.
    $role = 'encoder';

    if (empty($username) || empty($password) || empty($displayName)) {
        echo json_encode(['success' => false, 'message' => 'Username, password, and display name are required.']);
        exit;
    }
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username must be 3–30 characters: lowercase letters, numbers, underscore, or period only.']);
        exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    if (mb_strlen($displayName) > 100) {
        echo json_encode(['success' => false, 'message' => 'Display name is too long.']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM staff WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'That username is already taken.']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO staff (username, password_hash, role, display_name, is_active, created_by, created_at)
                                VALUES (?, ?, ?, ?, 1, ?, NOW())");
        $stmt->execute([$username, $hash, $role, $displayName, $_SESSION['admin_username'] ?? null]);

        $newId = $pdo->lastInsertId();
        osca_log_audit($pdo, 'create_staff', 'staff', (int)$newId, $displayName, "Created encoder account '$username'");

        echo json_encode([
            'success' => true,
            'message' => 'Encoder account created successfully.',
            'id'      => $newId,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'toggle_active') {

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, role, is_active FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit;
        }
        if ($target['username'] === ($_SESSION['admin_username'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
            exit;
        }
        // Never allow the last active admin to be deactivated — would lock everyone out
        if ($target['role'] === 'admin' && (int)$target['is_active'] === 1) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE role='admin' AND is_active=1");
            if ((int)$countStmt->fetchColumn() <= 1) {
                echo json_encode(['success' => false, 'message' => 'Cannot deactivate the last remaining administrator.']);
                exit;
            }
        }

        $newStatus = (int)$target['is_active'] === 1 ? 0 : 1;
        $upd = $pdo->prepare("UPDATE staff SET is_active = ? WHERE id = ?");
        $upd->execute([$newStatus, $id]);

        osca_log_audit($pdo, 'toggle_staff_active', 'staff', $id, $target['username'], $newStatus ? 'Reactivated account' : 'Deactivated account');

        echo json_encode([
            'success'   => true,
            'message'   => $newStatus ? 'Account reactivated.' : 'Account deactivated.',
            'is_active' => $newStatus,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'reset_password') {

    // Any admin can reset ANY staff account's password — including another
    // admin's, or their own — since forgetting a password shouldn't require
    // deleting/recreating the account. No "last admin" restriction needed
    // here (unlike toggle/delete) since resetting never locks anyone out.

    $id          = (int)($_POST['id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
        exit;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }
    if ($newPassword !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE staff SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $id]);

        osca_log_audit($pdo, 'reset_staff_password', 'staff', $id, $target['username']);

        echo json_encode([
            'success' => true,
            'message' => 'Password reset for "' . $target['username'] . '". Share the new password with them securely.',
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'reset_password_UNUSED_DUPLICATE') {

    // Any admin can reset ANY staff account's password — including another
    // admin's, or their own — since forgetting a password shouldn't require
    // deleting/recreating the account. No "last admin" restriction needed
    // here (unlike toggle/delete) since resetting never locks anyone out.

    $id          = (int)($_POST['id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
        exit;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }
    if ($newPassword !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE staff SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Password reset for "' . $target['username'] . '". Share the new password with them securely.',
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'delete_staff') {

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, role, is_active FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit;
        }
        if ($target['username'] === ($_SESSION['admin_username'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            exit;
        }
        if ($target['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Administrator accounts cannot be deleted from here.']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM staff WHERE id = ?");
        $del->execute([$id]);

        if ($del->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'No account was deleted. Please try again.']);
            exit;
        }

        osca_log_audit($pdo, 'delete_staff', 'staff', $id, $target['username']);

        echo json_encode([
            'success' => true,
            'message' => 'Staff account "' . $target['username'] . '" deleted permanently.',
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>