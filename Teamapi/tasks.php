<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// GET all tasks (role-scoped)
if ($method === 'GET') {
    if ($role === 'admin') {
        $rows = $db->query(
            "SELECT t.*, m.name AS assigned_name, m.avatar_color
             FROM tasks t LEFT JOIN members m ON t.assigned_to = m.id
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        )->fetch_all(MYSQLI_ASSOC);
    } elseif ($role === 'director') {
        $rows = $db->query(
            "SELECT t.*, m.name AS assigned_name, m.avatar_color
             FROM tasks t LEFT JOIN members m ON t.assigned_to = m.id
             WHERE t.assigned_to IN (SELECT id FROM members WHERE director_id=$uid)
                OR t.assigned_to = $uid OR t.created_by = $uid
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        )->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $db->query(
            "SELECT t.*, m.name AS assigned_name
             FROM tasks t LEFT JOIN members m ON t.assigned_to = m.id
             WHERE t.assigned_to = $uid
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        )->fetch_all(MYSQLI_ASSOC);
    }
    respond($rows);
}

// POST — toggle or create
if ($method === 'POST') {
    $b = body();

    // Toggle status
    if (isset($b['toggle']) && $id) {
        $t = $db->query("SELECT status, assigned_to FROM tasks WHERE id=$id")->fetch_assoc();
        if (!$t) respond(['error' => 'Not found'], 404);
        if ($role === 'member' && $t['assigned_to'] != $uid) respond(['error' => 'Forbidden'], 403);
        $new = $t['status'] === 'completed' ? 'pending' : 'completed';
        $db->query("UPDATE tasks SET status='$new' WHERE id=$id");
        respond(['status' => $new]);
    }

    // Create task (admin / director only)
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);

    $title       = safe($db, $b['title'] ?? '');
    $assigned_to = !empty($b['assigned_to']) ? (int)$b['assigned_to'] : 'NULL';
    $due         = safe($db, $b['due_date'] ?? '');
    $priority    = in_array($b['priority'] ?? '', ['low','medium','high']) ? $b['priority'] : 'medium';
    $status      = in_array($b['status'] ?? '', ['pending','in-progress','completed']) ? $b['status'] : 'pending';
    if (!$title) respond(['error' => 'Title required'], 400);

    $due_val = $due ? "'$due'" : 'NULL';
    $db->query("INSERT INTO tasks (title,assigned_to,due_date,priority,status,created_by)
                VALUES ('$title',$assigned_to,$due_val,'$priority','$status',$uid)");
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

// DELETE
if ($method === 'DELETE' && $id) {
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);
    $db->query("DELETE FROM tasks WHERE id=$id");
    respond(['success' => true]);
}
