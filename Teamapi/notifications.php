<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

$db->query("CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       VARCHAR(30) NOT NULL DEFAULT 'general',
    title      VARCHAR(150) NOT NULL DEFAULT '',
    body       TEXT NOT NULL DEFAULT '',
    link       VARCHAR(255) NOT NULL DEFAULT '',
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id, is_read, created_at)
)");

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'count') {
        $res = $db->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0");
        if (!$res) respond(['error' => 'Could not count notifications: ' . $db->error], 500);
        respond(['count' => (int)$res->fetch_assoc()['c']]);
    }

    $res = $db->query(
        "SELECT id, type, title, body, link, is_read, created_at
         FROM notifications
         WHERE user_id=$uid
         ORDER BY is_read ASC, created_at DESC
         LIMIT 50"
    );
    if (!$res) respond(['error' => 'Could not load notifications: ' . $db->error], 500);
    respond($res->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST') {
    $b = body();
    if (($b['action'] ?? '') === 'mark_read') {
        $db->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
        respond(['success' => true]);
    }
    respond(['error' => 'Invalid action'], 400);
}
