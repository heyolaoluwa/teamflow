<?php
require_once 'config.php';
require_once 'auth_check.php';

$db   = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$wid    = $CURRENT_WORKSPACE_ID;

function genTempPassword() {
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower  = 'abcdefghijkmnpqrstuvwxyz';
    $digits = '23456789';
    $all    = $upper . $lower . $digits;
    $pass   = $upper[random_int(0, strlen($upper)-1)]
            . $lower[random_int(0, strlen($lower)-1)]
            . $digits[random_int(0, strlen($digits)-1)];
    for ($i = 0; $i < 7; $i++) $pass .= $all[random_int(0, strlen($all)-1)];
    return str_shuffle($pass);
}

// GET — single member
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $r  = $db->query(
        "SELECT m.id, m.name, m.email, m.role, m.user_role, m.department, m.employment_type, m.status,
                m.phone, m.avatar_color, m.director_id, m.directorate_id, m.workspace_id,
                w.name AS workspace_name, m.last_login
         FROM members m
         LEFT JOIN workspaces w ON m.workspace_id = w.id
         WHERE m.id = $id AND m.workspace_id = $wid"
    )->fetch_assoc();
    if (!$r) respond(['error' => 'Not found'], 404);
    if ($role !== 'admin' && $r['id'] !== $uid && $r['director_id'] !== $uid)
        respond(['error' => 'Forbidden'], 403);
    respond($r);
}

// GET — list (always scoped to current workspace)
if ($method === 'GET') {
    $q           = safe($db, $_GET['q'] ?? '');
    $role_filter = safe($db, $_GET['user_role'] ?? '');

    $where = "m.status != 'inactive' AND m.workspace_id = $wid";
    if ($q)           $where .= " AND (m.name LIKE '%$q%' OR m.email LIKE '%$q%' OR m.role LIKE '%$q%' OR m.department LIKE '%$q%')";
    if ($role_filter) $where .= " AND m.user_role = '$role_filter'";

    if ($role === 'director') {
        $myDirRow = $db->query("SELECT directorate_id FROM members WHERE id=$uid")->fetch_assoc();
        $myDirId  = $myDirRow ? (int)$myDirRow['directorate_id'] : 0;
        if ($myDirId > 0) $where .= " AND m.directorate_id = $myDirId";
        else               $where .= " AND (m.director_id = $uid OR m.id = $uid)";
    } elseif ($role === 'member') {
        $where .= " AND m.id = $uid";
    }

    $rows = $db->query(
        "SELECT m.id, m.name, m.email, m.role, m.user_role, m.department, m.employment_type, m.status,
                m.phone, m.avatar_color, m.director_id, m.directorate_id, m.workspace_id,
                w.name AS workspace_name, m.last_login
         FROM members m
         LEFT JOIN workspaces w ON m.workspace_id = w.id
         WHERE $where
         ORDER BY FIELD(m.user_role,'admin','director','member'), m.name ASC"
    )->fetch_all(MYSQLI_ASSOC);
    respond($rows);
}

// POST — create member (admin only)
if ($method === 'POST') {
    if ($role !== 'admin') respond(['error' => 'Forbidden'], 403);
    $b = body();

    $name  = safe($db, trim($b['name'] ?? ''));
    $email = safe($db, trim($b['email'] ?? ''));
    if (!$name || !$email) respond(['error' => 'Name and email required'], 400);
    if ($db->query("SELECT id FROM members WHERE email = '$email'")->num_rows > 0)
        respond(['error' => 'Email already in use'], 400);

    $job_role  = safe($db, $b['role'] ?? '');
    $user_role = in_array($b['user_role'] ?? '', ['member','director','admin']) ? $b['user_role'] : 'member';
    $dept      = safe($db, $b['department'] ?? '');
    $emp       = in_array($b['employment_type'] ?? '', ['full-time','part-time','contractor']) ? $b['employment_type'] : 'full-time';
    $stat      = in_array($b['status'] ?? '', ['active','on-leave','inactive']) ? $b['status'] : 'active';
    $phone     = safe($db, $b['phone'] ?? '');
    $dir_id    = !empty($b['director_id'])    ? (int)$b['director_id']    : 'NULL';
    $dirate_id = !empty($b['directorate_id']) ? (int)$b['directorate_id'] : 'NULL';
    // New members always inherit the admin's workspace
    $new_wid   = $wid ?: 'NULL';

    $temp  = genTempPassword();
    $hash  = safe($db, password_hash($temp, PASSWORD_BCRYPT));
    $tsafe = safe($db, $temp);
    $colors = ['#6C5CE7','#00B894','#E17055','#0984E3','#FDCB6E','#E84393','#00CEC9','#A29BFE'];
    $color  = $colors[array_rand($colors)];

    $db->query(
        "INSERT INTO members
             (name, email, role, user_role, department, employment_type, status, phone,
              avatar_color, password_hash, temp_password, must_change_password,
              director_id, directorate_id, workspace_id)
         VALUES
             ('$name','$email','$job_role','$user_role','$dept','$emp','$stat','$phone',
              '$color','$hash','$tsafe', 1, $dir_id, $dirate_id, $new_wid)"
    );

    respond(['success' => true, 'id' => $db->insert_id, 'temp_password' => $temp, 'email' => $b['email']]);
}

// PUT — update member
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['error' => 'ID required'], 400);
    if ($role !== 'admin' && $id !== $uid) respond(['error' => 'Forbidden'], 403);
    $b = body();

    $name  = safe($db, $b['name'] ?? '');
    $email = safe($db, $b['email'] ?? '');
    $jrole = safe($db, $b['role'] ?? '');
    $dept  = safe($db, $b['department'] ?? '');
    $phone = safe($db, $b['phone'] ?? '');
    $emp   = in_array($b['employment_type'] ?? '', ['full-time','part-time','contractor']) ? $b['employment_type'] : 'full-time';
    $stat  = in_array($b['status'] ?? '', ['active','on-leave','inactive']) ? $b['status'] : 'active';
    $set   = "name='$name', email='$email', role='$jrole', department='$dept', phone='$phone', employment_type='$emp', status='$stat'";

    if ($role === 'admin') {
        $urole     = in_array($b['user_role'] ?? '', ['member','director','admin']) ? $b['user_role'] : null;
        $dir       = isset($b['director_id'])    ? (!empty($b['director_id'])    ? (int)$b['director_id']    : 'NULL') : null;
        $dirate_id = isset($b['directorate_id']) ? (!empty($b['directorate_id']) ? (int)$b['directorate_id'] : 'NULL') : null;
        if ($urole !== null)     $set .= ", user_role='$urole'";
        if ($dir !== null)       $set .= ", director_id=$dir";
        if ($dirate_id !== null) $set .= ", directorate_id=$dirate_id";
    }
    $db->query("UPDATE members SET $set WHERE id = $id AND workspace_id = $wid");
    respond(['success' => true]);
}

// DELETE — soft-delete (admin only)
if ($method === 'DELETE') {
    if ($role !== 'admin') respond(['error' => 'Forbidden'], 403);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['error' => 'ID required'], 400);
    if ($id === $uid) respond(['error' => 'Cannot deactivate yourself'], 400);
    $db->query("UPDATE members SET status = 'inactive' WHERE id = $id AND workspace_id = $wid");
    respond(['success' => true]);
}
