<?php
require_once 'config.php';

$action = $_GET['action'] ?? 'check';

// CHECK session
if ($action === 'check') {
    if (!isset($_SESSION['member_id'])) respond(['logged_in' => false]);
    $id = (int)$_SESSION['member_id'];
    $r = db()->query("SELECT id, name, email, user_role, avatar_color, must_change_password FROM members WHERE id=$id AND status != 'inactive'")->fetch_assoc();
    if ($r) respond(['logged_in' => true, 'user' => $r]);
    session_destroy();
    respond(['logged_in' => false]);
}

// LOGIN
if ($action === 'login') {
    $b = body();
    $email = trim($b['email'] ?? '');
    $password = $b['password'] ?? '';
    if (!$email || !$password) respond(['error' => 'Email and password required'], 400);

    $db = db();
    $e = safe($db, $email);
    $r = $db->query("SELECT * FROM members WHERE email='$e' AND status != 'inactive'")->fetch_assoc();
    if (!$r || !password_verify($password, $r['password_hash'])) {
        respond(['error' => 'Invalid email or password'], 401);
    }

    $_SESSION['member_id'] = $r['id'];
    $db->query("UPDATE members SET last_login=NOW() WHERE id={$r['id']}");

    respond([
        'success' => true,
        'must_change_password' => (bool)$r['must_change_password'],
        'user' => [
            'id'         => $r['id'],
            'name'       => $r['name'],
            'user_role'  => $r['user_role'],
            'avatar_color' => $r['avatar_color'],
        ]
    ]);
}

// CHANGE PASSWORD (requires active session)
if ($action === 'change_password') {
    if (!isset($_SESSION['member_id'])) respond(['error' => 'Not authenticated'], 401);
    $b = body();
    $new     = $b['new_password'] ?? '';
    $confirm = $b['confirm_password'] ?? '';

    if (strlen($new) < 8)
        respond(['error' => 'Password must be at least 8 characters'], 400);
    if ($new !== $confirm)
        respond(['error' => 'Passwords do not match'], 400);
    if (!preg_match('/[A-Z]/', $new))
        respond(['error' => 'Password must contain at least one uppercase letter'], 400);
    if (!preg_match('/[0-9]/', $new))
        respond(['error' => 'Password must contain at least one number'], 400);

    $id   = (int)$_SESSION['member_id'];
    $db   = db();
    $hash = safe($db, password_hash($new, PASSWORD_BCRYPT));
    $db->query("UPDATE members SET password_hash='$hash', must_change_password=0, temp_password=NULL WHERE id=$id");
    respond(['success' => true]);
}

// LOGOUT
if ($action === 'logout') {
    session_destroy();
    respond(['success' => true]);
}

respond(['error' => 'Unknown action'], 400);
