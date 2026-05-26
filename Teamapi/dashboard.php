<?php
require_once 'config.php';
require_once 'auth_check.php';

$db    = db();
$today = date('Y-m-d');
$role  = $CURRENT_USER['user_role'];
$uid   = $CURRENT_USER_ID;

if ($role === 'admin') {
    $total_members  = $db->query("SELECT COUNT(*) c FROM members WHERE status != 'inactive'")->fetch_assoc()['c'];
    $new_members    = $db->query("SELECT COUNT(*) c FROM members WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['c'];
    $on_shift       = $db->query("SELECT COUNT(*) c FROM shifts WHERE shift_date='$today' AND status='active'")->fetch_assoc()['c'];
    $tasks_due      = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status!='completed'")->fetch_assoc()['c'];
    $tasks_done     = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status='completed'")->fetch_assoc()['c'];
    $messages       = $db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
    $clocked_in     = $db->query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND clock_out IS NULL")->fetch_assoc()['c'];
    $total_work_min = $db->query("SELECT COALESCE(SUM(work_minutes),0) c FROM attendance WHERE date='$today'")->fetch_assoc()['c'];

    respond([
        'total_members'   => $total_members,
        'new_members'     => $new_members,
        'on_shift'        => $on_shift,
        'tasks_due'       => $tasks_due,
        'tasks_done'      => $tasks_done,
        'messages'        => $messages,
        'clocked_in'      => $clocked_in,
        'total_work_hours'=> round($total_work_min / 60, 1),
    ]);
}

if ($role === 'director') {
    $team       = $db->query("SELECT COUNT(*) c FROM members WHERE director_id=$uid AND status='active'")->fetch_assoc()['c'];
    $on_shift   = $db->query("SELECT COUNT(*) c FROM shifts s JOIN members m ON s.member_id=m.id WHERE s.shift_date='$today' AND s.status='active' AND (m.director_id=$uid OR m.id=$uid)")->fetch_assoc()['c'];
    $tasks_due  = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status!='completed' AND (assigned_to IN (SELECT id FROM members WHERE director_id=$uid) OR assigned_to=$uid)")->fetch_assoc()['c'];
    $messages   = $db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
    $clocked_in = $db->query("SELECT COUNT(*) c FROM attendance a JOIN members m ON a.member_id=m.id WHERE a.date='$today' AND a.clock_out IS NULL AND (m.director_id=$uid OR a.member_id=$uid)")->fetch_assoc()['c'];

    respond([
        'team_members' => $team,
        'on_shift'     => $on_shift,
        'tasks_due'    => $tasks_due,
        'messages'     => $messages,
        'clocked_in'   => $clocked_in,
    ]);
}

// member
$my_tasks   = $db->query("SELECT COUNT(*) c FROM tasks WHERE assigned_to=$uid AND status!='completed'")->fetch_assoc()['c'];
$messages   = $db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
$today_shift = $db->query("SELECT * FROM shifts WHERE member_id=$uid AND shift_date='$today' LIMIT 1")->fetch_assoc();
$attendance  = $db->query("SELECT clock_in, clock_out, work_minutes FROM attendance WHERE member_id=$uid AND date='$today' ORDER BY id DESC LIMIT 1")->fetch_assoc();

respond([
    'my_tasks'    => $my_tasks,
    'messages'    => $messages,
    'today_shift' => $today_shift,
    'attendance'  => $attendance ?: null,
]);
