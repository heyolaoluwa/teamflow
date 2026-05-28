<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

function extractMentions($text) {
    preg_match_all('/@([A-Za-z][A-Za-z0-9_\- ]{1,40})/u', $text, $matches);
    return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
}

function notifyMention($db, $userId, $senderName, $messageId) {
    if (!$userId) return;
    $sender = safe($db, $senderName);
    $db->query("INSERT INTO notifications (user_id, type, title, body, link) VALUES ($userId, 'mention', 'You were mentioned', '$sender mentioned you in chat.', 'messages.php?id=$messageId')");
}

// PHP 8.1+ throws mysqli exceptions by default — restore legacy behaviour
mysqli_report(MYSQLI_REPORT_OFF);

$db->query("CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       VARCHAR(30) NOT NULL DEFAULT 'general',
    title      VARCHAR(150) NOT NULL DEFAULT '',
    body       TEXT NOT NULL,
    link       VARCHAR(255) NOT NULL DEFAULT '',
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id, is_read, created_at)
)");

// Ensure table exists
$db->query("CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NULL,
    sender_name VARCHAR(100) NOT NULL DEFAULT '',
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Migrate: add member_id if the table was created without it
$col = $db->query("SHOW COLUMNS FROM messages LIKE 'member_id'");
if ($col && $col->num_rows === 0) {
    $db->query("ALTER TABLE messages ADD COLUMN member_id INT NULL AFTER id");
}

// Migrate: add sender_name if missing
$col2 = $db->query("SHOW COLUMNS FROM messages LIKE 'sender_name'");
if ($col2 && $col2->num_rows === 0) {
    $db->query("ALTER TABLE messages ADD COLUMN sender_name VARCHAR(100) NOT NULL DEFAULT '' AFTER member_id");
}

if ($method === 'GET') {
    $result = $db->query(
        "SELECT id, member_id, sender_name, content, created_at
         FROM messages ORDER BY created_at ASC LIMIT 100"
    );
    if (!$result) respond(['error' => 'Could not load messages: ' . $db->error], 500);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond($rows);
}

if ($method === 'POST') {
    $b       = body();
    $content = safe($db, trim($b['content'] ?? ''));
    if (!$content) respond(['error' => 'Content required'], 400);

    $sender = safe($db, $CURRENT_USER['name']);
    $ok = $db->query(
        "INSERT INTO messages (member_id, sender_name, content)
         VALUES ($uid, '$sender', '$content')"
    );
    if (!$ok) respond(['error' => 'Could not send message: ' . $db->error], 500);

    $messageId = (int)$db->insert_id;
    $mentions = extractMentions($content);
    foreach ($mentions as $name) {
        $res = $db->query("SELECT id FROM members WHERE LOWER(name) LIKE LOWER('%$name%') AND status!='inactive'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ((int)$row['id'] !== $uid) {
                    notifyMention($db, (int)$row['id'], $CURRENT_USER['name'], $messageId);
                }
            }
        }
    }

    respond(['success' => true, 'id' => $messageId], 201);
}
