<?php
/**
 * delete_record.php
 * Permanently deletes a single applicant record (and any associated blobs).
 * Called by: confirmDeleteBtn click handler in dashboard.js
 *
 * Requires: admin session AND admin role (encoders cannot delete)
 * Method: POST
 * Body: id=<int>
 */
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';
require_once 'audit_log_helper.php';

header('Content-Type: application/json');

// ── Auth guard — must be logged in ───────────────────────────
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Role guard — admin only ───────────────────────────────────
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only administrators can permanently delete records.',
    ]);
    exit;
}

// ── Only allow POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Validate ID ───────────────────────────────────────────────
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

// ── Delete ────────────────────────────────────────────────────
try {
    // Verify the record exists before attempting deletion
    $check = $pdo->prepare("SELECT id, lastnameApplicant, firstnameApplicant FROM applicants WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found or already deleted.']);
        exit;
    }

    // Hard delete — all blob columns are stored in the same row,
    // so a single DELETE removes everything (photos included).
    $del = $pdo->prepare("DELETE FROM applicants WHERE id = ?");
    $del->execute([$id]);

    if ($del->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No rows were deleted. Please try again.']);
        exit;
    }

    // Audit log (uncomment if you have an audit_logs table)
    // $log = $pdo->prepare("INSERT INTO audit_logs (admin, action, target_id, target_name, logged_at)
    //                        VALUES (?, 'DELETE', ?, ?, NOW())");
    // $log->execute([$_SESSION['admin_username'] ?? 'unknown', $id, $row['lastnameApplicant']]);

    $deletedName = trim($row['firstnameApplicant'].' '.$row['lastnameApplicant']);
    osca_bump_change($pdo, 'delete', 'Record Deleted', $deletedName);
    osca_log_audit($pdo, 'delete_record', 'applicant', $id, $deletedName);
    echo json_encode([
        'success'    => true,
        'message'    => 'Record deleted successfully.',
        'deleted_id' => $id,
    ]);
} catch (Throwable $e) {
    error_log('[delete_record] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}