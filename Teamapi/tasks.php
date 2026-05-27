<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper: get a member's directorate_id (0 if none)
function myDirectorateId($db, $uid) {
    $r = $db->query("SELECT directorate_id FROM members WHERE id = $uid")->fetch_assoc();
    return ($r && $r['directorate_id']) ? (int)$r['directorate_id'] : 0;
}

// ── GET — fetch tasks (role-scoped) ──────────────────────────────────────
if ($method === 'GET') {

    if ($role === 'admin') {
        $res = $db->query(
            "SELECT t.*,
                    m.name         AS assigned_name,
                    m.avatar_color,
                    d.name         AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        );

    } elseif ($role === 'director') {
        $myDir     = myDirectorateId($db, $uid);
        $dirClause = $myDir ? "OR t.assigned_directorate_id = $myDir" : '';
        $res = $db->query(
            "SELECT t.*,
                    m.name         AS assigned_name,
                    m.avatar_color,
                    d.name         AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             WHERE t.assigned_to IN (SELECT id FROM members WHERE director_id = $uid)
                OR t.assigned_to  = $uid
                OR t.created_by   = $uid
                $dirClause
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        );

    } else {
        // Member: personal tasks + tasks assigned to their directorate
        $myDir     = myDirectorateId($db, $uid);
        $dirClause = $myDir ? "OR t.assigned_directorate_id = $myDir" : '';
        $res = $db->query(
            "SELECT t.*,
                    m.name AS assigned_name,
                    d.name AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             WHERE t.assigned_to = $uid
             $dirClause
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        );
    }
    if (!$res) respond(['error' => 'Query failed: ' . $db->error], 500);
    respond($res->fetch_all(MYSQLI_ASSOC));
}

// ── POST — toggle status or create new task ──────────────────────────────
if ($method === 'POST') {
    $b = body();

    // Toggle status
    if (isset($b['toggle']) && $id) {
        $t = $db->query(
            "SELECT status, assigned_to, assigned_directorate_id FROM tasks WHERE id = $id"
        )->fetch_assoc();
        if (!$t) respond(['error' => 'Not found'], 404);

        // Members may toggle tasks assigned to them personally OR to their directorate
        if ($role === 'member') {
            $myDir   = myDirectorateId($db, $uid);
            $allowed = ($t['assigned_to'] == $uid)
                    || ($myDir && $t['assigned_directorate_id'] == $myDir);
            if (!$allowed) respond(['error' => 'Forbidden'], 403);
        }

        $new = $t['status'] === 'completed' ? 'pending' : 'completed';
        $db->query("UPDATE tasks SET status = '$new' WHERE id = $id");
        respond(['status' => $new]);
    }

    // Create task — admin / director only
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);

    $title    = safe($db, $b['title'] ?? '');
    $asgn_to  = !empty($b['assigned_to'])             ? (int)$b['assigned_to']             : 'NULL';
    $asgn_dir = !empty($b['assigned_directorate_id'])  ? (int)$b['assigned_directorate_id']  : 'NULL';
    $due      = safe($db, $b['due_date'] ?? '');
    $priority = in_array($b['priority'] ?? '', ['low','medium','high']) ? $b['priority'] : 'medium';
    $status   = in_array($b['status']   ?? '', ['pending','in-progress','completed']) ? $b['status'] : 'pending';

    if (!$title) respond(['error' => 'Title required'], 400);

    $due_val = $due ? "'$due'" : 'NULL';
    $ok = $db->query(
        "INSERT INTO tasks
             (title, assigned_to, assigned_directorate_id, due_date, priority, status, created_by)
         VALUES
             ('$title', $asgn_to, $asgn_dir, $due_val, '$priority', '$status', $uid)"
    );
    if (!$ok) respond(['error' => 'Insert failed: ' . $db->error], 500);
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

// ── DELETE ───────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);
    $db->query("DELETE FROM tasks WHERE id = $id");
    respond(['success' => true]);
}
