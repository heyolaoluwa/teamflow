<?php
require_once 'config.php';
$db = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($method === 'GET') {
    $date = isset($_GET['date']) ? safe($db, $_GET['date']) : date('Y-m-d');
    $r = $db->query("SELECT s.*, m.name AS member_name, m.avatar_color, m.role AS member_role FROM shifts s JOIN members m ON s.member_id=m.id WHERE s.shift_date='$date' ORDER BY s.start_time");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    respond($rows);
}

if ($method === 'POST') {
    $b = body();
    $mid = (int)($b['member_id'] ?? 0);
    $date = safe($db, $b['shift_date'] ?? date('Y-m-d'));
    $start = safe($db, $b['start_time'] ?? '09:00');
    $end = safe($db, $b['end_time'] ?? '17:00');
    $status = safe($db, $b['status'] ?? 'upcoming');
    if (!$mid) respond(['error' => 'Member required'], 400);
    $db->query("INSERT INTO shifts (member_id,shift_date,start_time,end_time,status) VALUES ($mid,'$date','$start','$end','$status')");
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

if ($method === 'DELETE' && $id) {
    $db->query("DELETE FROM shifts WHERE id=$id");
    respond(['success' => true]);
}
