<?php
require_once 'config.php';
require_once 'auth_check.php';

$db   = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$wid    = $CURRENT_WORKSPACE_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function myDirectorateId($db, $uid) {
    $r = $db->query("SELECT directorate_id FROM members WHERE id = $uid")->fetch_assoc();
    return ($r && $r['directorate_id']) ? (int)$r['directorate_id'] : 0;
}

function notifyUser($db, $userId, $type, $title, $body, $link = '') {
    if (!$userId) return;
    $title = safe($db, $title);
    $body  = safe($db, $body);
    $link  = safe($db, $link);
    $db->query("INSERT INTO notifications (user_id, type, title, body, link) VALUES ($userId, '$type', '$title', '$body', '$link')");
}

function notifyDirectorateMembers($db, $directorateId, $type, $title, $body, $link = '') {
    if (!$directorateId) return;
    $res = $db->query("SELECT id FROM members WHERE directorate_id=$directorateId AND status!='inactive'");
    if (!$res) return;
    while ($row = $res->fetch_assoc()) {
        notifyUser($db, (int)$row['id'], $type, $title, $body, $link);
    }
}

// ── GET ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Workspace scope: only tasks belonging to this workspace
    $wsScope = "t.workspace_id = $wid";

    if ($id) {
        $q = "SELECT t.*,
                    m.name         AS assigned_name,
                    m.avatar_color,
                    d.name         AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             WHERE t.id = $id AND $wsScope";

        if ($role === 'director') {
            $myDir     = myDirectorateId($db, $uid);
            $dirClause = $myDir ? "OR t.assigned_directorate_id = $myDir" : '';
            $q .= " AND (t.assigned_to IN (SELECT id FROM members WHERE director_id = $uid)
                    OR t.assigned_to = $uid
                    OR t.created_by = $uid
                    $dirClause)";
        } elseif ($role === 'member') {
            $myDir     = myDirectorateId($db, $uid);
            $dirClause = $myDir ? "OR t.assigned_directorate_id = $myDir" : '';
            $q .= " AND (t.assigned_to = $uid $dirClause)";
        }

        $res = $db->query($q);
        if (!$res) respond(['error' => 'Query failed: ' . $db->error], 500);
        $row = $res->fetch_assoc();
        if (!$row) respond(['error' => 'Not found'], 404);
        respond($row);
    }

    if ($role === 'admin') {
        $res = $db->query(
            "SELECT t.*,
                    m.name         AS assigned_name,
                    m.avatar_color,
                    d.name         AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             WHERE $wsScope
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
             WHERE $wsScope
               AND (t.assigned_to IN (SELECT id FROM members WHERE director_id = $uid)
                OR t.assigned_to  = $uid
                OR t.created_by   = $uid
                $dirClause)
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        );

    } else {
        $myDir     = myDirectorateId($db, $uid);
        $dirClause = $myDir ? "OR t.assigned_directorate_id = $myDir" : '';
        $res = $db->query(
            "SELECT t.*,
                    m.name AS assigned_name,
                    d.name AS directorate_name
             FROM tasks t
             LEFT JOIN members      m ON t.assigned_to             = m.id
             LEFT JOIN directorates d ON t.assigned_directorate_id = d.id
             WHERE $wsScope
               AND (t.assigned_to = $uid $dirClause)
             ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC"
        );
    }
    if (!$res) respond(['error' => 'Query failed: ' . $db->error], 500);
    respond($res->fetch_all(MYSQLI_ASSOC));
}

// ── POST ─────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $b = body();

    if (isset($b['action']) && $b['action'] === 'update_progress' && $id) {
        $t = $db->query("SELECT status, assigned_to, assigned_directorate_id, work_plan, closing_note FROM tasks WHERE id = $id AND workspace_id = $wid")->fetch_assoc();
        if (!$t) respond(['error' => 'Not found'], 404);

        if ($role === 'member') {
            $myDir   = myDirectorateId($db, $uid);
            $allowed = ($t['assigned_to'] == $uid)
                    || ($myDir && $t['assigned_directorate_id'] == $myDir);
            if (!$allowed) respond(['error' => 'Forbidden'], 403);
        }

        $status      = in_array($b['status'] ?? '', ['pending','in-progress','completed']) ? $b['status'] : $t['status'];
        $workPlan    = safe($db, $b['work_plan']    ?? $t['work_plan']    ?? '');
        $closingNote = safe($db, $b['closing_note'] ?? $t['closing_note'] ?? '');

        if ($status === 'completed' && trim($closingNote) === '') {
            respond(['error' => 'Closing statement is required before marking this task done'], 400);
        }

        $db->query("UPDATE tasks SET status = '$status', work_plan = '$workPlan', closing_note = '$closingNote' WHERE id = $id AND workspace_id = $wid");
        respond(['success' => true, 'status' => $status]);
    }

    if (isset($b['toggle']) && $id) {
        $t = $db->query("SELECT status, assigned_to, assigned_directorate_id FROM tasks WHERE id = $id AND workspace_id = $wid")->fetch_assoc();
        if (!$t) respond(['error' => 'Not found'], 404);

        if ($role === 'member') {
            $myDir   = myDirectorateId($db, $uid);
            $allowed = ($t['assigned_to'] == $uid)
                    || ($myDir && $t['assigned_directorate_id'] == $myDir);
            if (!$allowed) respond(['error' => 'Forbidden'], 403);
        }

        $new = $t['status'] === 'completed' ? 'pending' : 'completed';
        $db->query("UPDATE tasks SET status = '$new' WHERE id = $id AND workspace_id = $wid");
        respond(['status' => $new]);
    }

    if ($role === 'member') respond(['error' => 'Forbidden'], 403);

    $title    = safe($db, $b['title'] ?? '');
    $asgn_to  = !empty($b['assigned_to'])            ? (int)$b['assigned_to']            : 'NULL';
    $asgn_dir = !empty($b['assigned_directorate_id']) ? (int)$b['assigned_directorate_id'] : 'NULL';
    $due      = safe($db, $b['due_date'] ?? '');
    $priority = in_array($b['priority'] ?? '', ['low','medium','high']) ? $b['priority'] : 'medium';
    $status   = in_array($b['status']   ?? '', ['pending','in-progress','completed']) ? $b['status'] : 'pending';

    if (!$title) respond(['error' => 'Title required'], 400);

    $due_val = $due ? "'$due'" : 'NULL';
    $ok = $db->query(
        "INSERT INTO tasks
             (title, assigned_to, assigned_directorate_id, due_date, priority, status, created_by, workspace_id)
         VALUES
             ('$title', $asgn_to, $asgn_dir, $due_val, '$priority', '$status', $uid, $wid)"
    );
    if (!$ok) respond(['error' => 'Insert failed: ' . $db->error], 500);

    $taskId = (int)$db->insert_id;
    if ($asgn_to !== 'NULL') {
        notifyUser($db, (int)$b['assigned_to'], 'task', 'New task assigned', "You were assigned: $title", "tasks.php?id=$taskId");
    }
    if ($asgn_dir !== 'NULL') {
        notifyDirectorateMembers($db, (int)$b['assigned_directorate_id'], 'task', 'New task for your directorate', "A new task was assigned to your directorate: $title", "tasks.php?id=$taskId");
    }

    respond(['success' => true, 'id' => $taskId], 201);
}

// ── DELETE ───────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    if ($role === 'member') respond(['error' => 'Forbidden'], 403);
    $db->query("DELETE FROM tasks WHERE id = $id AND workspace_id = $wid");
    respond(['success' => true]);
}
