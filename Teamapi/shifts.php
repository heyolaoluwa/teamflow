<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper: directorate scope for current director
function directorMemberScope($db, $uid) {
    $r = $db->query("SELECT directorate_id FROM members WHERE id=$uid")->fetch_assoc();
    $myDirId = $r ? (int)$r['directorate_id'] : 0;
    return $myDirId > 0
        ? "m.directorate_id = $myDirId"
        : "(m.director_id = $uid OR s.member_id = $uid)";
}

// ── GET ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $date = isset($_GET['date']) ? safe($db, $_GET['date']) : date('Y-m-d');

    if ($role === 'admin') {
        $r = $db->query(
            "SELECT s.*, m.name AS member_name, m.avatar_color, m.role AS member_role
             FROM shifts s JOIN members m ON s.member_id = m.id
             WHERE s.shift_date = '$date'
             ORDER BY s.start_time"
        );
    } elseif ($role === 'director') {
        $scope = directorMemberScope($db, $uid);
        $r = $db->query(
            "SELECT s.*, m.name AS member_name, m.avatar_color, m.role AS member_role
             FROM shifts s JOIN members m ON s.member_id = m.id
             WHERE s.shift_date = '$date' AND ($scope)
             ORDER BY s.start_time"
        );
    } else {
        // Member sees only their own shifts
        $r = $db->query(
            "SELECT s.*, m.name AS member_name, m.avatar_color, m.role AS member_role
             FROM shifts s JOIN members m ON s.member_id = m.id
             WHERE s.shift_date = '$date' AND s.member_id = $uid
             ORDER BY s.start_time"
        );
    }

    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    respond($rows);
}

// ── POST ─────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);
    $b      = body();
    $mid    = (int)($b['member_id'] ?? 0);
    $date   = safe($db, $b['shift_date']  ?? date('Y-m-d'));
    $start  = safe($db, $b['start_time']  ?? '09:00');
    $end    = safe($db, $b['end_time']    ?? '17:00');
    $status = safe($db, $b['status']      ?? 'upcoming');
    if (!$mid) respond(['error' => 'Member required'], 400);
    $db->query(
        "INSERT INTO shifts (member_id, shift_date, start_time, end_time, status)
         VALUES ($mid, '$date', '$start', '$end', '$status')"
    );
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

// ── DELETE ───────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);
    $db->query("DELETE FROM shifts WHERE id = $id");
    respond(['success' => true]);
}
