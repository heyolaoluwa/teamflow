<?php
require_once 'config.php';
$db = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($method === 'GET') {
    $r = $db->query("SELECT * FROM tasks ORDER BY FIELD(priority,'high','medium','low'), due_date ASC");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    respond($rows);
}

if ($method === 'POST') {
    $b = body();
    // toggle action
    if (isset($b['toggle']) && $id) {
        $r = $db->query("SELECT status FROM tasks WHERE id=$id");
        $cur = $r->fetch_assoc()['status'];
        $new = $cur === 'completed' ? 'pending' : 'completed';
        $db->query("UPDATE tasks SET status='$new' WHERE id=$id");
        respond(['status' => $new]);
    }
    $title = safe($db, $b['title'] ?? '');
    $assigned = safe($db, $b['assigned_to'] ?? '');
    $due = safe($db, $b['due_date'] ?? '');
    $priority = safe($db, $b['priority'] ?? 'medium');
    $status = safe($db, $b['status'] ?? 'pending');
    if (!$title) respond(['error' => 'Title required'], 400);
    $due_val = $due ? "'$due'" : 'NULL';
    $db->query("INSERT INTO tasks (title,assigned_to,due_date,priority,status) VALUES ('$title','$assigned',$due_val,'$priority','$status')");
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

if ($method === 'DELETE' && $id) {
    $db->query("DELETE FROM tasks WHERE id=$id");
    respond(['success' => true]);
}
