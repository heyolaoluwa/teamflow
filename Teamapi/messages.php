<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT id, member_id, sender_name, content, created_at
         FROM messages ORDER BY created_at ASC LIMIT 100"
    )->fetch_all(MYSQLI_ASSOC);
    respond($rows);
}

if ($method === 'POST') {
    $b       = body();
    $content = safe($db, trim($b['content'] ?? ''));
    if (!$content) respond(['error' => 'Content required'], 400);

    $sender = safe($db, $CURRENT_USER['name']);
    $db->query("INSERT INTO messages (member_id, sender_name, content) VALUES ($uid, '$sender', '$content')");
    respond(['success' => true, 'id' => $db->insert_id], 201);
}
