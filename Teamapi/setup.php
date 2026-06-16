<?php
require_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');

$db = db();

// Safety: only run if no admin exists
$existing = $db->query("SELECT COUNT(*) c FROM members WHERE user_role='admin'")->fetch_assoc()['c'];
if ($existing > 0) {
    echo '<p style="font-family:sans-serif;color:#c0392b;padding:24px">
            <strong>Setup already completed.</strong> Admin account exists.<br>
            <a href="../app.html">Go to app</a>
          </p>';
    exit();
}

$password = 'Admin@TeamFlow1';
$hash     = password_hash($password, PASSWORD_BCRYPT);
$color    = '#5C6BC0';

$stmt = $db->prepare(
    "INSERT INTO members (name, email, role, user_role, department, employment_type, status, avatar_color, password_hash, must_change_password)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
);
$name  = 'Admin';
$email = 'admin@teamflow.com';
$role  = 'Administrator';
$urole = 'admin';
$dept  = 'Management';
$emp   = 'full-time';
$stat  = 'active';
$stmt->bind_param('sssssssss', $name, $email, $role, $urole, $dept, $emp, $stat, $color, $hash);
$stmt->execute();

echo '<!DOCTYPE html><html><head>
<meta charset="UTF-8"/>
<style>
  body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#F5F6FA;margin:0}
  .box{background:#fff;border:1px solid #E4E6EF;border-radius:10px;padding:36px;max-width:420px;width:95%}
  h2{color:#1A1D2E;margin-bottom:8px}p{color:#6B7280;font-size:14px;margin-bottom:6px}
  .cred{background:#F5F6FA;border-radius:6px;padding:16px;margin:16px 0;font-size:14px}
  .cred b{color:#1A1D2E}.warn{color:#E17055;font-size:13px}
  a{display:inline-block;margin-top:16px;padding:10px 20px;background:#5C6BC0;color:#fff;border-radius:6px;text-decoration:none;font-size:14px}
</style></head><body>
<div class="box">
  <h2>&#10003; TeamFlow Setup Complete</h2>
  <p>Initial admin account created. Share these credentials:</p>
  <div class="cred">
    <p><b>Email:</b> admin@teamflow.com</p>
    <p><b>Password:</b> Admin@TeamFlow1</p>
  </div>
  <p class="warn"><strong>Important:</strong> Log in and change your password immediately. Then delete this file (<code>Teamapi/setup.php</code>) from cPanel.</p>
  <a href="../login.html">Go to Login</a>
</div>
</body></html>';
