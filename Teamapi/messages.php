<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

// PHP 8.1+ throws mysqli exceptions by default; keep errors as return values
mysqli_report(MYSQLI_REPORT_OFF);

// Auto-create the messages table in case it wasn't in the initial DB setup
$db->query("CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NULL,
    sender_name VARCHAR(100) NOT NULL DEFAULT '',
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

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
    $ok = $db->query("INSERT INTO messages (member_id, sender_name, content) VALUES ($uid, '$sender', '$content')");
    if (!$ok) respond(['error' => 'Could not send message: ' . $db->error], 500);
    respond(['success' => true, 'id' => $db->insert_id], 201);
}
