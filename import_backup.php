<?php
/**
 * import_backup.php
 * Admin-only: decrypts an .oscabak file produced by export_backup.php and
 * restores the entire database from it — used when the main office
 * computer breaks down and needs to be rebuilt from a USB backup.
 *
 * THIS IS DESTRUCTIVE: every table in the current database is dropped
 * and rebuilt from the backup. The client MUST get explicit typed
 * confirmation (e.g. type "RESTORE") before calling this, the same way
 * delete_record.php / manage_staff.php require confirmation.
 *
 * Order of operations, deliberately in this sequence:
 *   1. Read + validate the uploaded file's magic bytes
 *   2. Derive keys from the supplied password and verify the HMAC
 *   3. Decrypt and json_decode the payload
 *   4. Only AFTER all of the above succeed (i.e. we know the password is
 *      correct and the file isn't corrupted) do we touch the database.
 *
 * CAVEAT: MySQL's DROP/CREATE TABLE statements are DDL and are NOT
 * transactional — they auto-commit immediately. If the restore fails
 * partway through table N of M, tables before N have already been
 * replaced and cannot be rolled back automatically. Validating the file
 * fully before starting (step 4 above) is what keeps this safe in
 * practice: by the time we start dropping tables, we've already parsed
 * the entire backup into memory successfully.
 */
session_start();
require_once 'db.php';

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
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only administrators can restore backups.']);
    exit;
}

$password = $_POST['password'] ?? '';
$confirmText = trim($_POST['confirm_text'] ?? '');

if ($confirmText !== 'RESTORE') {
    echo json_encode(['success' => false, 'message' => 'Please type RESTORE to confirm this will overwrite all current data.']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Backup password is required.']);
    exit;
}
if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No backup file was uploaded, or the upload failed.']);
    exit;
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$tmpPath = $_FILES['backup_file']['tmp_name'];
$fileContents = file_get_contents($tmpPath);
if ($fileContents === false || strlen($fileContents) < (6 + 16 + 16 + 32)) {
    echo json_encode(['success' => false, 'message' => 'The backup file is missing or too small to be valid.']);
    exit;
}

// ── Parse the file format ───────────────────────────────────────────────
$magic = substr($fileContents, 0, 6);
if ($magic !== 'OSCAB1') {
    echo json_encode(['success' => false, 'message' => 'This does not look like an OSCA backup file.']);
    exit;
}

$salt       = substr($fileContents, 6, 16);
$iv         = substr($fileContents, 22, 16);
$hmacStored = substr($fileContents, 38, 32);
$ciphertext = substr($fileContents, 70);

// ── Derive keys and verify integrity/password BEFORE decrypting ────────
$keyMaterial = hash_pbkdf2('sha256', $password, $salt, 100000, 64, true);
$encKey = substr($keyMaterial, 0, 32);
$macKey = substr($keyMaterial, 32, 32);

$hmacCalculated = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);

if (!hash_equals($hmacCalculated, $hmacStored)) {
    // Either the password is wrong, or the file was corrupted/tampered with.
    // We can't distinguish the two without leaking timing info, so give a
    // single generic message for both cases.
    echo json_encode(['success' => false, 'message' => 'Incorrect password, or this backup file is corrupted.']);
    exit;
}

$json = openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
if ($json === false) {
    echo json_encode(['success' => false, 'message' => 'Decryption failed. The file may be corrupted.']);
    exit;
}

$backup = json_decode($json, true);
if (!is_array($backup) || ($backup['app'] ?? '') !== 'OSCA' || !isset($backup['tables']) || !is_array($backup['tables'])) {
    echo json_encode(['success' => false, 'message' => 'The backup file does not contain valid OSCA data.']);
    exit;
}

// ── At this point the file is fully validated. Proceed with restore. ──
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($backup['tables'] as $table => $tableData) {
        // Basic sanity check on the table name to avoid surprises when
        // building identifiers into SQL below.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new Exception("Unsafe table name in backup: $table");
        }

        $createSql  = $tableData['create'] ?? null;
        $binaryCols = $tableData['binary_cols'] ?? [];
        $rows       = $tableData['rows'] ?? [];

        if (!$createSql) {
            throw new Exception("Missing CREATE TABLE statement for `$table` in backup.");
        }

        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        $pdo->exec($createSql);

        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $insertStmt = $pdo->prepare("INSERT INTO `$table` ($columnList) VALUES $placeholders");

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $col) {
                    $val = $row[$col];
                    if (in_array($col, $binaryCols, true) && $val !== null) {
                        $val = base64_decode($val);
                    }
                    $values[] = $val;
                }
                $insertStmt->execute($values);
            }
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo json_encode([
        'success'      => true,
        'message'      => 'Database restored successfully from backup (created ' . ($backup['created_at'] ?? 'unknown date') . ').',
        'tables'       => array_keys($backup['tables']),
        'backup_date'  => $backup['created_at'] ?? null,
    ]);

} catch (Exception $e) {
    // Re-enable FK checks even on failure so the DB isn't left half-configured.
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $ignored) {}
    error_log('[import_backup] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Restore failed partway through: ' . $e->getMessage() .
                     ' Some tables may already have been replaced — please verify your data and consider restoring again from the same file.',
    ]);
}