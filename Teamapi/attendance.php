<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$uid    = $CURRENT_USER_ID;
$role   = $CURRENT_USER['user_role'];

// CLOCK IN
if ($method === 'POST' && $action === 'clock_in') {
    $today = date('Y-m-d');
    $open  = $db->query("SELECT id FROM attendance WHERE member_id=$uid AND date='$today' AND clock_out IS NULL")->fetch_assoc();
    if ($open) respond(['error' => 'Already clocked in'], 400);
    $db->query("INSERT INTO attendance (member_id, clock_in, date) VALUES ($uid, NOW(), '$today')");
    respond(['success' => true, 'clock_in' => date('Y-m-d H:i:s')]);
}

// CLOCK OUT
if ($method === 'POST' && $action === 'clock_out') {
    $today = date('Y-m-d');
    $rec   = $db->query("SELECT id, clock_in FROM attendance WHERE member_id=$uid AND date='$today' AND clock_out IS NULL")->fetch_assoc();
    if (!$rec) respond(['error' => 'Not clocked in'], 400);
    $mins = max(1, (int)round((time() - strtotime($rec['clock_in'])) / 60));
    $db->query("UPDATE attendance SET clock_out=NOW(), work_minutes=$mins WHERE id={$rec['id']}");
    respond(['success' => true, 'work_minutes' => $mins]);
}

// TODAY STATUS for current user
if ($method === 'GET' && $action === 'status') {
    $today = date('Y-m-d');
    $rec   = $db->query("SELECT clock_in, clock_out, work_minutes FROM attendance WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    respond($rec ?: ['clock_in' => null, 'clock_out' => null, 'work_minutes' => null]);
}

// GET RECORDS (date filter, role-scoped)
if ($method === 'GET') {
    $date = safe($db, $_GET['date'] ?? date('Y-m-d'));

    if ($role === 'admin') {
        $rows = $db->query(
            "SELECT a.*, m.name AS member_name, m.avatar_color, m.role AS job_role
             FROM attendance a JOIN members m ON a.member_id = m.id
             WHERE a.date='$date' ORDER BY a.clock_in DESC"
        )->fetch_all(MYSQLI_ASSOC);
    } elseif ($role === 'director') {
        $rows = $db->query(
            "SELECT a.*, m.name AS member_name, m.avatar_color, m.role AS job_role
             FROM attendance a JOIN members m ON a.member_id = m.id
             WHERE a.date='$date' AND (m.director_id=$uid OR a.member_id=$uid)
             ORDER BY a.clock_in DESC"
        )->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $db->query(
            "SELECT a.*, m.name AS member_name, m.avatar_color
             FROM attendance a JOIN members m ON a.member_id = m.id
             WHERE a.member_id=$uid AND a.date='$date' ORDER BY a.clock_in DESC"
        )->fetch_all(MYSQLI_ASSOC);
    }
    respond($rows);
}
