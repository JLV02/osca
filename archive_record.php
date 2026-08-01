<?php
/**
 * archive_record.php
 */
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';
require_once 'audit_log_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id, lastnameApplicant, firstnameApplicant, is_archived FROM applicants WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }
} catch (PDOException $e) {
    error_log('[archive_record] check PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}

// ── Archive ───────────────────────────────────────────────────
if ($action === 'archive') {

    $reason = trim($_POST['reason'] ?? '');
    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'A reason for archiving is required.']);
        exit;
    }
    if (mb_strlen($reason) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Reason must be 1000 characters or fewer.']);
        exit;
    }
    if ($row['is_archived']) {
        echo json_encode(['success' => false, 'message' => 'This record is already archived.']);
        exit;
    }

    $archivedBy = $_SESSION['display_name'] ?? $_SESSION['admin_username'] ?? 'Staff';

    try {
        $stmt = $pdo->prepare("
            UPDATE applicants
            SET is_archived    = 1,
                status         = 'archived',
                archive_reason = ?,
                archived_at    = NOW(),
                archived_by    = ?
            WHERE id = ?
        ");
$stmt->execute([$reason, $archivedBy, $id]);
        $archName = trim($row['firstnameApplicant'].' '.$row['lastnameApplicant']);
        osca_bump_change($pdo, 'archive', 'Record Archived', $archName);
        osca_log_audit($pdo, 'archive_record', 'applicant', $id, $archName, 'Reason: ' . $reason);
        echo json_encode(['success' => true, 'message' => 'Record moved to archive.', 'id' => $id]);
        } catch (Throwable $e) {
        error_log('[archive_record] archive error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }

// ── Restore ───────────────────────────────────────────────────
} elseif ($action === 'restore') {

    if (!$row['is_archived']) {
        echo json_encode(['success' => false, 'message' => 'This record is not archived.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE applicants
            SET is_archived    = 0,
                status         = 'complete',
                archive_reason = NULL,
                archived_at    = NULL,
                archived_by    = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $restName = trim($row['firstnameApplicant'].' '.$row['lastnameApplicant']);
        osca_bump_change($pdo, 'restore', 'Record Restored', $restName);
        osca_log_audit($pdo, 'restore_record', 'applicant', $id, $restName);
        echo json_encode(['success' => true, 'message' => 'Record restored to active list.', 'id' => $id]);
    } catch (Throwable $e) {
        error_log('[archive_record] restore error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }

// ── Purge (admin only, BLOB storage) ─────────────────────────
} elseif ($action === 'purge') {

    $role = $_SESSION['admin_role'] ?? '';
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can permanently delete records.']);
        exit;
    }
    if (!$row['is_archived']) {
        echo json_encode(['success' => false, 'message' => 'Only archived records can be permanently deleted.']);
        exit;
    }

    try {
        // Images are stored as BLOBs in the DB — no files to delete from disk.
        // Just hard-delete the row.
        $stmt = $pdo->prepare("DELETE FROM applicants WHERE id = ?");
        $stmt->execute([$id]);
        $purgeName = trim($row['firstnameApplicant'].' '.$row['lastnameApplicant']);
        osca_bump_change($pdo, 'purge', 'Permanent Deletion', $purgeName);
        osca_log_audit($pdo, 'purge_record', 'applicant', $id, $purgeName, 'Permanently deleted from archive');
        echo json_encode(['success' => true, 'message' => 'Record permanently deleted.', 'id' => $id]);
    } catch (Throwable $e) {
        error_log('[archive_record] purge error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}