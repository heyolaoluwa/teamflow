<?php
require_once 'config.php';
require_once 'auth_check.php';

$db    = db();
$today = date('Y-m-d');
$role  = $CURRENT_USER['user_role'];
$uid   = $CURRENT_USER_ID;

// ── ADMIN ────────────────────────────────────────────────────────────────
if ($role === 'admin') {
    $total_members  = $db->query("SELECT COUNT(*) c FROM members WHERE status != 'inactive'")->fetch_assoc()['c'];
    $new_members    = $db->query("SELECT COUNT(*) c FROM members WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['c'];
    $tasks_due      = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status!='completed'")->fetch_assoc()['c'];
    $tasks_done     = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status='completed'")->fetch_assoc()['c'];
    $messages       = $db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
    $clocked_in     = $db->query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND clock_out IS NULL")->fetch_assoc()['c'];

    // Total combined work minutes across ALL members today (completed + active sessions)
    $work_min_done  = (int)$db->query("SELECT COALESCE(SUM(work_minutes),0) c FROM attendance WHERE date='$today' AND clock_out IS NOT NULL")->fetch_assoc()['c'];
    $active_mins    = (int)$db->query(
        "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, NOW())),0) c
         FROM attendance WHERE date='$today' AND clock_out IS NULL"
    )->fetch_assoc()['c'];
    $total_work_min = $work_min_done + $active_mins;

    respond([
        'total_members'    => $total_members,
        'new_members'      => $new_members,
        'tasks_due'        => $tasks_due,
        'tasks_done'       => $tasks_done,
        'messages'         => $messages,
        'clocked_in'       => $clocked_in,
        'total_work_hours' => round($total_work_min / 60, 1),
        'total_work_min'   => $total_work_min,
    ]);
}

// ── DIRECTOR ─────────────────────────────────────────────────────────────
if ($role === 'director') {
    // Resolve the director's directorate
    $myDirRow = $db->query("SELECT directorate_id FROM members WHERE id=$uid")->fetch_assoc();
    $myDirId  = $myDirRow ? (int)$myDirRow['directorate_id'] : 0;

    // Scope condition: prefer directorate_id, fall back to director_id link
    $memberScope = $myDirId > 0
        ? "m.directorate_id = $myDirId"
        : "(m.director_id = $uid OR m.id = $uid)";

    // How many people in their directorate are currently clocked in
    $dir_clocked = (int)$db->query(
        "SELECT COUNT(*) c FROM attendance a
         JOIN members m ON a.member_id = m.id
         WHERE a.date='$today' AND a.clock_out IS NULL AND ($memberScope)"
    )->fetch_assoc()['c'];

    // Combined work minutes for the whole directorate today
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

    $dir_total_hours = round(($dir_work_min_done + $dir_active_mins) / 60, 1);

    // Tasks due today scoped to directorate
    $dirTaskScope = "t.assigned_to IN (SELECT id FROM members WHERE $memberScope AND status!='inactive')
                  OR t.assigned_to = $uid
                  OR t.created_by  = $uid"
                  . ($myDirId > 0 ? " OR t.assigned_directorate_id = $myDirId" : "");

    $tasks_due = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks t
         WHERE t.due_date='$today' AND t.status!='completed' AND ($dirTaskScope)"
    )->fetch_assoc()['c'];

    $messages = (int)$db->query(
        "SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'"
    )->fetch_assoc()['c'];

    // Personal data so the director can also clock in / out
    $my_tasks    = (int)$db->query(
        "SELECT COUNT(*) c FROM tasks WHERE assigned_to=$uid AND status!='completed'"
    )->fetch_assoc()['c'];
    $today_shift = $db->query(
        "SELECT * FROM shifts WHERE member_id=$uid AND shift_date='$today' LIMIT 1"
    )->fetch_assoc();
    $attendance  = $db->query(
        "SELECT clock_in, clock_out, work_minutes FROM attendance
         WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();

    respond([
        'dir_clocked_in'   => $dir_clocked,
        'dir_total_hours'  => $dir_total_hours,
        'tasks_due'        => $tasks_due,
        'messages'         => $messages,
        'my_tasks'         => $my_tasks,
        'today_shift'      => $today_shift ?: null,
        'attendance'       => $attendance  ?: null,
    ]);
}

// ── MEMBER ───────────────────────────────────────────────────────────────
$my_tasks    = (int)$db->query("SELECT COUNT(*) c FROM tasks WHERE assigned_to=$uid AND status!='completed'")->fetch_assoc()['c'];
$messages    = (int)$db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
$today_shift = $db->query("SELECT * FROM shifts WHERE member_id=$uid AND shift_date='$today' LIMIT 1")->fetch_assoc();
$attendance  = $db->query("SELECT clock_in, clock_out, work_minutes FROM attendance WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1")->fetch_assoc();

respond([
    'my_tasks'    => $my_tasks,
    'messages'    => $messages,
    'today_shift' => $today_shift ?: null,
    'attendance'  => $attendance  ?: null,
]);
