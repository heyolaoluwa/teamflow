<?php
require_once 'config.php';
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT w.*, w.logo_url,
                m.name AS creator_name,
                (SELECT COUNT(*) FROM members WHERE workspace_id = w.id AND status != 'inactive') AS member_count
         FROM workspaces w
         LEFT JOIN members m ON w.created_by = m.id
         ORDER BY w.name ASC"
    )->fetch_all(MYSQLI_ASSOC);
    respond($rows);
}

if ($method === 'POST') {
    $b          = body();
    $name       = safe($db, trim($b['name'] ?? ''));
    $admin_name = safe($db, trim($b['admin_name'] ?? ''));
    $admin_email= safe($db, trim($b['admin_email'] ?? ''));
    if (!$name) respond(['error' => 'Workspace name required'], 400);
    if (!$admin_name || !$admin_email) respond(['error' => 'Admin name and email required'], 400);
    if ($db->query("SELECT id FROM members WHERE email = '$admin_email'")->num_rows > 0)
        respond(['error' => 'Email already in use'], 400);

    $desc      = safe($db, $b['description'] ?? '');
    $logo_url  = safe($db, $b['logo_url'] ?? '');
    $created_by = isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : 'NULL';

    $db->query(
        "INSERT INTO workspaces (name, description, logo_url, created_by)
         VALUES ('$name', '$desc', '$logo_url', $created_by)"
    );
    $workspace_id = $db->insert_id;

    $temp_pass = genTempPassword();
    $hash      = safe($db, password_hash($temp_pass, PASSWORD_BCRYPT));
    $colors    = ['#6C5CE7','#00B894','#E17055','#0984E3','#FDCB6E','#E84393','#00CEC9','#A29BFE'];
    $color     = $colors[array_rand($colors)];

    $db->query(
        "INSERT INTO members
             (name, email, role, user_role, department, employment_type, status, phone,
              avatar_color, password_hash, temp_password, must_change_password,
              workspace_id)
         VALUES
             ('$admin_name','$admin_email','Administrator','admin','','full-time','active','',
              '$color','$hash','$temp_pass', 1, $workspace_id)"
    );

    respond([
        'success' => true,
        'id' => $workspace_id,
        'admin_email' => $admin_email,
        'admin_temp_password' => $temp_pass
    ], 201);
}

require_once 'auth_check.php';
$role = $CURRENT_USER['user_role'];
$uid  = $CURRENT_USER_ID;

if ($method === 'PUT' && $id) {
    $existing = $db->query("SELECT created_by FROM workspaces WHERE id = $id")->fetch_assoc();
    if (!$existing) respond(['error' => 'Workspace not found'], 404);
    if ($role !== 'admin' && $existing['created_by'] != $uid) respond(['error' => 'Forbidden'], 403);

    $b    = body();
    $name = safe($db, trim($b['name'] ?? ''));
    if (!$name) respond(['error' => 'Name required'], 400);
    $desc = safe($db, $b['description'] ?? '');
    $logo = safe($db, $b['logo_url'] ?? '');

    $db->query("UPDATE workspaces SET name='$name', description='$desc', logo_url='$logo' WHERE id=$id");
    respond(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $existing = $db->query("SELECT created_by FROM workspaces WHERE id = $id")->fetch_assoc();
    if (!$existing) respond(['error' => 'Workspace not found'], 404);
    if ($role !== 'admin' && $existing['created_by'] != $uid) respond(['error' => 'Forbidden'], 403);

    $db->query("UPDATE members SET workspace_id = NULL WHERE workspace_id = $id");
    $db->query("DELETE FROM workspaces WHERE id = $id");
    respond(['success' => true]);
}

respond(['error' => 'Unknown action'], 400);
