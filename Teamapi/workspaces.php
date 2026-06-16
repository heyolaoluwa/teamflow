<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT w.*,
                m.name AS creator_name,
                (SELECT COUNT(*) FROM members WHERE workspace_id = w.id AND status != 'inactive') AS member_count
         FROM workspaces w
         LEFT JOIN members m ON w.created_by = m.id
         ORDER BY w.name ASC"
    )->fetch_all(MYSQLI_ASSOC);
    respond($rows);
}

if ($method === 'POST') {
    $b    = body();
    $name = safe($db, trim($b['name'] ?? ''));
    if (!$name) respond(['error' => 'Name required'], 400);

    $desc = safe($db, $b['description'] ?? '');
    $db->query(
        "INSERT INTO workspaces (name, description, created_by)
         VALUES ('$name', '$desc', $uid)"
    );

    respond(['success' => true, 'id' => $db->insert_id], 201);
}

if ($method === 'PUT' && $id) {
    $existing = $db->query("SELECT created_by FROM workspaces WHERE id = $id")->fetch_assoc();
    if (!$existing) respond(['error' => 'Workspace not found'], 404);
    if ($role !== 'admin' && $existing['created_by'] != $uid) respond(['error' => 'Forbidden'], 403);

    $b    = body();
    $name = safe($db, trim($b['name'] ?? ''));
    if (!$name) respond(['error' => 'Name required'], 400);
    $desc = safe($db, $b['description'] ?? '');

    $db->query("UPDATE workspaces SET name='$name', description='$desc' WHERE id=$id");
    respond(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $existing = $db->query("SELECT created_by FROM workspaces WHERE id = $id")->fetch_assoc();
    if (!$existing) respond(['error' => 'Workspace not found'], 404);
    if ($role !== 'admin' && $existing['created_by'] != $uid) respond(['error' => 'Forbidden'], 403);

    $db->query("UPDATE members SET workspace_id = NULL WHERE workspace_id = $id");
    $db->query("DELETE FROM workspaces WHERE id = $id");
    respond(['success' => true]);
}

respond(['error' => 'Unknown action'], 400);
