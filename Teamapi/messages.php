<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

// PHP 8.1+ throws mysqli exceptions by default — restore legacy behaviour
mysqli_report(MYSQLI_REPORT_OFF);

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
    respond(['success' => true, 'id' => $db->insert_id], 201);
}
