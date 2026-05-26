<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$uid    = $CURRENT_USER_ID;

if ($method === 'GET') {
    $result = $db->query(
        "SELECT m.id, m.member_id, m.sender_name, m.content, m.created_at,
                mb.avatar_color
         FROM messages m
         LEFT JOIN members mb ON mb.id = m.member_id
         ORDER BY m.created_at ASC LIMIT 100"
    );
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
    $db->query("INSERT INTO messages (member_id, sender_name, content) VALUES ($uid, '$sender', '$content')");
    respond(['success' => true, 'id' => $db->insert_id], 201);
}
