<?php
/**
 * notifications_helper.php
 * Shared helpers for the Change Notifications feature.
 */

function osca_bump_change(PDO $pdo, string $type, string $title, ?string $message = null): void {
    try {
        $pdo->prepare(
            "UPDATE system_meta SET pending_changes = pending_changes + 1,
             last_change_at = NOW(), last_change_type = ? WHERE id = 1"
        )->execute([$type]);
    } catch (PDOException $e) {
        error_log('[osca_bump_change:meta] ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications_log (type, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$type, $title, $message]);
    } catch (PDOException $e) {
        error_log('[osca_bump_change:log] ' . $e->getMessage());
    }
}

function osca_get_notification_state(PDO $pdo): array {
    try {
        $stmt = $pdo->query(
            "SELECT pending_changes, last_change_at, last_change_type, last_backup_at
             FROM system_meta WHERE id = 1 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['pending_changes' => 0, 'last_change_at' => null, 'last_change_type' => null, 'last_backup_at' => null];
        }
        $row['pending_changes'] = (int)$row['pending_changes'];
        return $row;
    } catch (PDOException $e) {
        error_log('[osca_get_notification_state] ' . $e->getMessage());
        return ['pending_changes' => 0, 'last_change_at' => null, 'last_change_type' => null, 'last_backup_at' => null];
    }
}

function osca_get_recent_notifications(PDO $pdo, int $limit = 8): array {
    try {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->query(
            "SELECT id, type, title, message, created_at
             FROM notifications_log ORDER BY created_at DESC, id DESC LIMIT $limit"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[osca_get_recent_notifications] ' . $e->getMessage());
        return [];
    }
}
function osca_delete_notification(PDO $pdo, int $id): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications_log WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('[osca_delete_notification] ' . $e->getMessage());
        return false;
    }
}
function osca_delete_all_notifications(PDO $pdo): bool {
    try {
        $pdo->exec("DELETE FROM notifications_log");
        return true;
    } catch (PDOException $e) {
        error_log('[osca_delete_all_notifications] ' . $e->getMessage());
        return false;
    }
}