<?php
require_once 'config.php';
$db = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($method === 'GET') {
    if ($id) {
        $r = $db->query("SELECT * FROM members WHERE id=$id");
        respond($r->fetch_assoc());
    } else {
        $q = isset($_GET['q']) ? '%' . safe($db, $_GET['q']) . '%' : '%';
        $r = $db->query("SELECT * FROM members WHERE name LIKE '$q' OR email LIKE '$q' OR role LIKE '$q' ORDER BY name");
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        respond($rows);
    }
}

if ($method === 'POST') {
    $b = body();
    $name = safe($db, $b['name'] ?? '');
    $email = safe($db, $b['email'] ?? '');
    $role = safe($db, $b['role'] ?? '');
    $dept = safe($db, $b['department'] ?? '');
    $type = safe($db, $b['employment_type'] ?? 'full-time');
    $status = safe($db, $b['status'] ?? 'active');
    $phone = safe($db, $b['phone'] ?? '');
    $colors = ['#6C5CE7','#00B894','#E17055','#0984E3','#FDCB6E','#D63031','#A29BFE'];
    $color = $colors[array_rand($colors)];
    if (!$name || !$email) respond(['error' => 'Name and email required'], 400);
    $db->query("INSERT INTO members (name,email,role,department,employment_type,status,phone,avatar_color) VALUES ('$name','$email','$role','$dept','$type','$status','$phone','$color')");
    $r = $db->query("SELECT * FROM members WHERE id=" . $db->insert_id);
    respond($r->fetch_assoc(), 201);
}

if ($method === 'PUT' && $id) {
    $b = body();
    $name = safe($db, $b['name'] ?? '');
    $email = safe($db, $b['email'] ?? '');
    $role = safe($db, $b['role'] ?? '');
    $dept = safe($db, $b['department'] ?? '');
    $type = safe($db, $b['employment_type'] ?? 'full-time');
    $status = safe($db, $b['status'] ?? 'active');
    $phone = safe($db, $b['phone'] ?? '');
    $db->query("UPDATE members SET name='$name',email='$email',role='$role',department='$dept',employment_type='$type',status='$status',phone='$phone' WHERE id=$id");
    respond(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $db->query("DELETE FROM members WHERE id=$id");
    respond(['success' => true]);
}
