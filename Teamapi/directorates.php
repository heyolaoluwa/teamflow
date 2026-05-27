<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// GET — list all directorates (any authenticated user may read)
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT d.*,
                m.name         AS director_name,
                m.avatar_color AS director_color,
                (SELECT COUNT(*)
                 FROM members
                 WHERE directorate_id = d.id
                   AND status != 'inactive') AS member_count
         FROM directorates d
         LEFT JOIN members m ON d.director_id = m.id
         ORDER BY d.name ASC"
    )->fetch_all(MYSQLI_ASSOC);
    respond($rows);
}

// POST — create directorate (admin only)
if ($method === 'POST') {
    if ($role !== 'admin') respond(['error' => 'Forbidden'], 403);
    $b    = body();
    $name = safe($db, trim($b['name'] ?? ''));
    if (!$name) respond(['error' => 'Name required'], 400);

    $desc   = safe($db, $b['description'] ?? '');
    $dir_id = !empty($b['director_id']) ? (int)$b['director_id'] : 'NULL';

    $db->query(
        "INSERT INTO directorates (name, description, director_id, created_by)
         VALUES ('$name', '$desc', $dir_id, $uid)"
    );
    $new_id = $db->insert_id;

    // Sync the appointed director's directorate_id so they belong to it too
    if ($dir_id !== 'NULL') {
        $db->query("UPDATE members SET directorate_id = $new_id WHERE id = $dir_id");
    }

    respond(['success' => true, 'id' => $new_id], 201);
}

// PUT — update directorate (admin only)
if ($method === 'PUT' && $id) {
    if ($role !== 'admin') respond(['error' => 'Forbidden'], 403);
    $b    = body();
    $name = safe($db, trim($b['name'] ?? ''));
    if (!$name) respond(['error' => 'Name required'], 400);

    $desc   = safe($db, $b['description'] ?? '');
    $dir_id = isset($b['director_id'])
              ? (!empty($b['director_id']) ? (int)$b['director_id'] : 'NULL')
              : null;

    $set = "name='$name', description='$desc'";
    if ($dir_id !== null) {
        $set .= ", director_id=$dir_id";
        if ($dir_id !== 'NULL') {
            $db->query("UPDATE members SET directorate_id = $id WHERE id = $dir_id");
        }
    }
    $db->query("UPDATE directorates SET $set WHERE id = $id");
    respond(['success' => true]);
}

// DELETE — admin only
if ($method === 'DELETE' && $id) {
    if ($role !== 'admin') respond(['error' => 'Forbidden'], 403);
    // Unlink members first (ON DELETE SET NULL handles it, but be explicit)
    $db->query("UPDATE members SET directorate_id = NULL WHERE directorate_id = $id");
    $db->query("DELETE FROM directorates WHERE id = $id");
    respond(['success' => true]);
}
