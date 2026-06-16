<?php
/**
 * One-time migration: add logo_url column to workspaces table.
 * Run this once via browser: https://yourdomain.com/teamflow/Teamapi/migrate.php
 * DELETE this file from the server after running.
 */
require_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');

$db = db();

$migrations = [];

// 1. Add logo_url to workspaces if missing
$cols = $db->query("SHOW COLUMNS FROM workspaces LIKE 'logo_url'")->num_rows;
if ($cols === 0) {
    if ($db->query("ALTER TABLE workspaces ADD COLUMN logo_url VARCHAR(500) NOT NULL DEFAULT '' AFTER description")) {
        $migrations[] = ['ok', 'Added <code>logo_url</code> column to <code>workspaces</code> table.'];
    } else {
        $migrations[] = ['err', 'Failed to add <code>logo_url</code>: ' . htmlspecialchars($db->error)];
    }
} else {
    $migrations[] = ['skip', '<code>logo_url</code> column already exists — no change needed.'];
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>TeamFlow — Database Migration</title>
<style>
  body{font-family:sans-serif;background:#0e0e1a;color:#f3f3f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:36px;max-width:540px;width:95%}
  h2{margin-bottom:20px;font-size:1.3rem}
  .item{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:14px;line-height:1.6}
  .item:last-child{border-bottom:none}
  .ic{font-size:16px;flex-shrink:0;margin-top:1px}
  .ok .ic::before{content:'✓';color:#34d399}
  .err .ic::before{content:'✗';color:#f87171}
  .skip .ic::before{content:'–';color:#9ca3af}
  .warn{margin-top:24px;padding:14px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);border-radius:10px;color:#fca5a5;font-size:13px}
</style>
</head>
<body>
<div class="box">
  <h2>TeamFlow — Database Migration</h2>
  <?php foreach ($migrations as [$type, $msg]): ?>
    <div class="item <?= $type ?>"><span class="ic"></span><span><?= $msg ?></span></div>
  <?php endforeach; ?>
  <div class="warn">
    <strong>Important:</strong> Delete this file from your server once all migrations show ✓ or –.<br>
    Path: <code>teamflow/Teamapi/migrate.php</code>
  </div>
</div>
</body>
</html>
