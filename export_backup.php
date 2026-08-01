<?php
/**
 * export_backup.php
 * Admin-only: exports the entire database (schema + data, including
 * photo BLOBs) into a single AES-256-CBC encrypted, HMAC-authenticated
 * file the staff can drop onto a USB flash drive.
 *
 * File format (all binary, concatenated in this order):
 *   MAGIC   (6 bytes)  "OSCAB1"
 *   SALT    (16 bytes) random, used to derive the key from the password
 *   IV      (16 bytes) random, AES-CBC initialization vector
 *   HMAC    (32 bytes) HMAC-SHA256(IV || CIPHERTEXT), verified before decrypting
 *   CIPHERTEXT (rest)  AES-256-CBC(JSON payload)
 *
 * The JSON payload (before encryption) looks like:
 *   {
 *     "app": "OSCA", "db": "...", "created_at": "...",
 *     "tables": {
 *       "applicants": { "create": "CREATE TABLE ...", "binary_cols": [...], "rows": [...] },
 *       ...
 *     }
 *   }
 *
 * Binary columns (BLOB/BINARY/etc.) are base64-encoded inside the JSON
 * since JSON can't hold raw binary safely.
 *
 * SECURITY NOTE: the password is never stored anywhere. If the staff
 * forgets it, the backup is permanently unreadable by design — that's
 * the point of "encrypted and password protected."
 */
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

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
    echo json_encode(['success' => false, 'message' => 'Only administrators can export backups.']);
    exit;
}

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Backup password must be at least 8 characters.']);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

// Backups can be large (photo BLOBs), give the script room to work.
set_time_limit(0);
ini_set('memory_limit', '512M');

try {
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        throw new Exception('No tables found in the database.');
    }

    $backup = [
        'app'        => 'OSCA',
        'db'         => $dbName,
        'created_at' => date('c'),
        'created_by' => $_SESSION['admin_username'] ?? 'unknown',
        'tables'     => [],
    ];

    $colTypeStmt = $pdo->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
    );

    foreach ($tables as $table) {
        // Exact CREATE TABLE statement (preserves engine, charset, keys, etc.)
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? null;
        if ($createSql === null) {
            throw new Exception("Could not read schema for table `$table`.");
        }

        // Identify binary columns so we know what to base64-encode/decode
        $colTypeStmt->execute([$dbName, $table]);
        $binaryCols = [];
        foreach ($colTypeStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (in_array($col['DATA_TYPE'], ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary'], true)) {
                $binaryCols[] = $col['COLUMN_NAME'];
            }
        }

        $rows = [];
        $dataStmt = $pdo->query("SELECT * FROM `$table`");
        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($binaryCols as $bc) {
                if ($row[$bc] !== null) {
                    $row[$bc] = base64_encode($row[$bc]);
                }
            }
            $rows[] = $row;
        }

        $backup['tables'][$table] = [
            'create'      => $createSql,
            'binary_cols' => $binaryCols,
            'rows'        => $rows,
        ];
    }

    $json = json_encode($backup, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception('Failed to encode backup data: ' . json_last_error_msg());
    }

    // ── Encrypt ──────────────────────────────────────────────────────────
    $salt = random_bytes(16);
    $iv   = random_bytes(16);

    // Derive 64 bytes: first 32 = encryption key, last 32 = HMAC key
    $keyMaterial = hash_pbkdf2('sha256', $password, $salt, 100000, 64, true);
    $encKey = substr($keyMaterial, 0, 32);
    $macKey = substr($keyMaterial, 32, 32);

    $ciphertext = openssl_encrypt($json, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new Exception('Encryption failed.');
    }

    $hmac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);

    $magic = 'OSCAB1';
    $fileContents = $magic . $salt . $iv . $hmac . $ciphertext;
    $filename = 'osca_backup_' . date('Ymd_His') . '.oscabak';

    // Backup succeeded — clear the pending-changes counter and record the time.
    try {
        $pdo->prepare("UPDATE system_meta SET pending_changes = 0, last_backup_at = NOW() WHERE id = 1")->execute();
    } catch (PDOException $e) {
        error_log('[export_backup] failed to reset notification state: ' . $e->getMessage());
    }

    // Stream the encrypted file directly — the client should fetch() this
    // with a POST body and handle the response as a Blob, NOT as JSON.
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($fileContents));
    header('X-OSCA-Filename: ' . $filename); // convenience for JS to read the suggested name
    echo $fileContents;
    exit;

} catch (Exception $e) {
    error_log('[export_backup] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Backup export failed: ' . $e->getMessage()]);
    exit;
}