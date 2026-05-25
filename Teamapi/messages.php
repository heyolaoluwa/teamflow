<?php
require_once 'config.php';
$db = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $r = $db->query("SELECT * FROM messages ORDER BY created_at ASC LIMIT 100");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    respond($rows);
}

if ($method === 'POST') {
    $b = body();
    $sender = safe($db, $b['sender_name'] ?? 'You');
    $content = safe($db, $b['content'] ?? '');
    if (!$content) respond(['error' => 'Content required'], 400);
    $db->query("INSERT INTO messages (sender_name,content) VALUES ('$sender','$content')");
    $r = $db->query("SELECT * FROM messages WHERE id=" . $db->insert_id);
    respond($r->fetch_assoc(), 201);
}
