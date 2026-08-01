<?php
/**
 * toggle_ncsc.php
 * Toggles the NCSC portal encoding status for a single applicant.
 */
session_start();
require_once 'db.php';
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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT ncsc_encoded FROM applicants WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    $newValue = ($row['ncsc_encoded'] === 'Yes') ? 'No' : 'Yes';

    $stmt = $pdo->prepare("UPDATE applicants SET ncsc_encoded = ? WHERE id = ?");
    $stmt->execute([$newValue, $id]);

    $nameStmt = $pdo->prepare("SELECT lastnameApplicant, firstnameApplicant FROM applicants WHERE id = ?");
    $nameStmt->execute([$id]);
    $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
    osca_log_audit($pdo, 'toggle_ncsc', 'applicant', $id, $nameRow ? trim($nameRow['firstnameApplicant'].' '.$nameRow['lastnameApplicant']) : null, "NCSC status set to $newValue");

    echo json_encode(['success' => true, 'id' => $id, 'ncsc_encoded' => $newValue]);
} catch (PDOException $e) {
    error_log('[toggle_ncsc] PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}