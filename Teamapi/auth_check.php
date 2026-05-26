<?php
// Include AFTER config.php. Sets $CURRENT_USER and $CURRENT_USER_ID or exits 401/403.

if (!isset($_SESSION['member_id'])) {
    respond(['error' => 'Not authenticated', 'redirect' => 'login'], 401);
}

$CURRENT_USER_ID = (int)$_SESSION['member_id'];
$_db = db();
$CURRENT_USER = $_db->query(
    "SELECT id, name, email, user_role, avatar_color, must_change_password, director_id
     FROM members WHERE id=$CURRENT_USER_ID AND status != 'inactive'"
)->fetch_assoc();

if (!$CURRENT_USER) {
    session_destroy();
    respond(['error' => 'Not authenticated', 'redirect' => 'login'], 401);
}

if ($CURRENT_USER['must_change_password']) {
    respond(['error' => 'Password change required', 'redirect' => 'change_password'], 403);
}
