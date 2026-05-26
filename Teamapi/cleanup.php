<?php
/**
 * ONE-TIME cleanup script — wipes all data except the admin account.
 * Run once, then DELETE this file from the server immediately.
 *
 * Usage: visit  /teamflow/Teamapi/cleanup.php?confirm=yes
 */
require_once 'config.php';

if (($_GET['confirm'] ?? '') !== 'yes') {
    http_response_code(400);
    echo json_encode([
        'error'   => 'Add ?confirm=yes to the URL to proceed.',
        'warning' => 'This permanently deletes ALL messages, tasks, shifts, attendance, and non-admin members.'
    ]);
    exit;
}

$db = db();
mysqli_report(MYSQLI_REPORT_OFF);

$steps  = [];
$errors = [];

function run($db, $sql, $label, &$steps, &$errors) {
    $ok = $db->query($sql);
    if ($ok === false) {
        $errors[] = "$label: " . $db->error;
    } else {
        $affected = $db->affected_rows;
        $steps[]  = "$label — $affected row(s) affected";
    }
}

// Delete in dependency order to avoid FK constraint issues
run($db, "DELETE FROM messages",                           "Clear messages",    $steps, $errors);
run($db, "DELETE FROM attendance",                         "Clear attendance",  $steps, $errors);
run($db, "DELETE FROM shifts",                             "Clear shifts",      $steps, $errors);
run($db, "DELETE FROM tasks",                              "Clear tasks",       $steps, $errors);
// Nullify director references so the self-FK doesn't block deletion
run($db, "UPDATE members SET director_id = NULL WHERE user_role != 'admin'", "Nullify director links", $steps, $errors);
run($db, "DELETE FROM members WHERE user_role != 'admin'", "Remove non-admin members", $steps, $errors);

// Reset auto-increment counters
foreach (['messages','attendance','shifts','tasks'] as $tbl) {
    $db->query("ALTER TABLE $tbl AUTO_INCREMENT = 1");
}

header('Content-Type: application/json');
echo json_encode([
    'status'  => empty($errors) ? 'ok' : 'partial',
    'steps'   => $steps,
    'errors'  => $errors,
    'note'    => 'Done. DELETE this file from the server now — it should not stay accessible.'
], JSON_PRETTY_PRINT);
