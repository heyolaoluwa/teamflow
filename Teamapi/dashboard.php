<?php
require_once 'config.php';
require_once 'auth_check.php';

$db    = db();
$today = date('Y-m-d');
$role  = $CURRENT_USER['user_role'];
$uid   = $CURRENT_USER_ID;
$wid   = $CURRENT_WORKSPACE_ID;

// ── ADMIN ────────────────────────────────────────────────────────────────
if ($role === 'admin') {
    $total_members = (int)$db->query(
        "SELECT COUNT(*) c FROM members WHERE status != 'inactive' AND workspace_id = $wid"
    )->fetch_assoc()['c'];

    $new_members = (int)$db->query(
        "SELECT COUNT(*) c FROM members
         WHERE workspace_id = $wid
           AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
    )->fetch_assoc()['c'];

    $tasks_due = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks WHERE workspace_id=$wid AND due_date='$today' AND status!='completed'"
    )->fetch_assoc()['c'];

    $tasks_done = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks WHERE workspace_id=$wid AND due_date='$today' AND status='completed'"
    )->fetch_assoc()['c'];

    $messages = (int)$db->query(
        "SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'"
    )->fetch_assoc()['c'];

    $clocked_in = (int)$db->query(
        "SELECT COUNT(*) c FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NULL AND m.workspace_id = $wid"
    )->fetch_assoc()['c'];

    $work_min_done = (int)$db->query(
        "SELECT COALESCE(SUM(a.work_minutes),0) c FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NOT NULL AND m.workspace_id = $wid"
    )->fetch_assoc()['c'];

    $active_mins = (int)$db->query(
        "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.clock_in, NOW())),0) c
         FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NULL AND m.workspace_id = $wid"
    )->fetch_assoc()['c'];

    $pending_req = (int)$db->query(
        "SELECT COUNT(*) c FROM leave_requests lr
         JOIN members m ON lr.member_id = m.id
         WHERE lr.status='pending' AND m.workspace_id = $wid"
    )->fetch_assoc()['c'];

    respond([
        'total_members'    => $total_members,
        'new_members'      => $new_members,
        'tasks_due'        => $tasks_due,
        'tasks_done'       => $tasks_done,
        'messages'         => $messages,
        'clocked_in'       => $clocked_in,
        'total_work_hours' => round(($work_min_done + $active_mins) / 60, 1),
        'total_work_min'   => $work_min_done + $active_mins,
        'pending_requests' => $pending_req,
    ]);
}

// ── DIRECTOR ─────────────────────────────────────────────────────────────
if ($role === 'director') {
    $myDirRow = $db->query("SELECT directorate_id FROM members WHERE id=$uid")->fetch_assoc();
    $myDirId  = $myDirRow ? (int)$myDirRow['directorate_id'] : 0;

    $memberScope = $myDirId > 0
        ? "m.directorate_id = $myDirId AND m.workspace_id = $wid"
        : "(m.director_id = $uid OR m.id = $uid) AND m.workspace_id = $wid";

    $dir_clocked = (int)$db->query(
        "SELECT COUNT(*) c FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NULL AND ($memberScope)"
    )->fetch_assoc()['c'];

    $dir_work_min_done = (int)$db->query(
        "SELECT COALESCE(SUM(a.work_minutes),0) c FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NOT NULL AND ($memberScope)"
    )->fetch_assoc()['c'];

    $dir_active_mins = (int)$db->query(
        "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.clock_in, NOW())),0) c
         FROM attendance a JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NULL AND ($memberScope)"
    )->fetch_assoc()['c'];

    $dirTaskScope = "t.workspace_id = $wid AND (
        t.assigned_to IN (SELECT id FROM members WHERE $memberScope AND status!='inactive')
        OR t.assigned_to = $uid
        OR t.created_by  = $uid"
        . ($myDirId > 0 ? " OR t.assigned_directorate_id = $myDirId" : "") . ")";

    $tasks_due = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks t
         WHERE t.due_date='$today' AND t.status!='completed' AND ($dirTaskScope)"
    )->fetch_assoc()['c'];

    $messages = (int)$db->query(
        "SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'"
    )->fetch_assoc()['c'];

    $my_tasks = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks WHERE assigned_to=$uid AND status!='completed' AND workspace_id=$wid"
    )->fetch_assoc()['c'];

    $today_shift = $db->query(
        "SELECT * FROM shifts WHERE member_id=$uid AND shift_date='$today' LIMIT 1"
    )->fetch_assoc();

    $attendance = $db->query(
        "SELECT clock_in, clock_out, work_minutes FROM attendance
         WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();

    $dirPendReqScope = $myDirId > 0
        ? "member_id IN (SELECT id FROM members WHERE directorate_id=$myDirId AND workspace_id=$wid AND status!='inactive') OR member_id=$uid"
        : "member_id IN (SELECT id FROM members WHERE director_id=$uid AND workspace_id=$wid) OR member_id=$uid";

    $dir_pending_req = (int)$db->query(
        "SELECT COUNT(*) c FROM leave_requests WHERE status='pending' AND ($dirPendReqScope)"
    )->fetch_assoc()['c'];

    respond([
        'dir_clocked_in'   => $dir_clocked,
        'dir_total_hours'  => round(($dir_work_min_done + $dir_active_mins) / 60, 1),
        'tasks_due'        => $tasks_due,
        'messages'         => $messages,
        'my_tasks'         => $my_tasks,
        'today_shift'      => $today_shift ?: null,
        'attendance'       => $attendance  ?: null,
        'pending_requests' => $dir_pending_req,
    ]);
}

// ── MEMBER ───────────────────────────────────────────────────────────────
$my_tasks = (int)$db->query(
    "SELECT COUNT(*) c FROM tasks WHERE assigned_to=$uid AND status!='completed' AND workspace_id=$wid"
)->fetch_assoc()['c'];

$messages = (int)$db->query(
    "SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'"
)->fetch_assoc()['c'];

$today_shift = $db->query(
    "SELECT * FROM shifts WHERE member_id=$uid AND shift_date='$today' LIMIT 1"
)->fetch_assoc();

$attendance = $db->query(
    "SELECT clock_in, clock_out, work_minutes FROM attendance
     WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

$my_pending_req = (int)$db->query(
    "SELECT COUNT(*) c FROM leave_requests WHERE member_id=$uid AND status='pending'"
)->fetch_assoc()['c'];

respond([
    'my_tasks'         => $my_tasks,
    'messages'         => $messages,
    'today_shift'      => $today_shift ?: null,
    'attendance'       => $attendance  ?: null,
    'pending_requests' => $my_pending_req,
]);
