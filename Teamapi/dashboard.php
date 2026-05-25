<?php
require_once 'config.php';
$db = db();
$today = date('Y-m-d');

$members = $db->query("SELECT COUNT(*) c FROM members WHERE status != 'inactive'")->fetch_assoc()['c'];
$on_shift = $db->query("SELECT COUNT(*) c FROM shifts WHERE shift_date='$today' AND status='active'")->fetch_assoc()['c'];
$tasks_due = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status!='completed'")->fetch_assoc()['c'];
$tasks_done = $db->query("SELECT COUNT(*) c FROM tasks WHERE due_date='$today' AND status='completed'")->fetch_assoc()['c'];
$messages = $db->query("SELECT COUNT(*) c FROM messages WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
$new_members = $db->query("SELECT COUNT(*) c FROM members WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['c'];

respond([
    'total_members' => $members,
    'on_shift' => $on_shift,
    'tasks_due' => $tasks_due,
    'tasks_done' => $tasks_done,
    'messages' => $messages,
    'new_members' => $new_members
]);
