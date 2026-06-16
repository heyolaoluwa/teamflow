<?php
/**
 * One-time migration: add all missing columns introduced after initial setup.
 * Run once via browser, then DELETE this file from the server.
 */
require_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');

$db = db();
$migrations = [];

function addCol($db, &$migrations, $table, $col, $definition, $after = '') {
    $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->num_rows > 0;
    if ($exists) {
        $migrations[] = ['skip', "<code>$table.$col</code> already exists — skipped."];
        return;
    }
    $afterSql = $after ? "AFTER `$after`" : '';
    if ($db->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition $afterSql")) {
        $migrations[] = ['ok', "Added <code>$col</code> to <code>$table</code>."];
    } else {
        $migrations[] = ['err', "Failed to add <code>$table.$col</code>: " . htmlspecialchars($db->error)];
    }
}

// ── workspaces table ──────────────────────────────────────────────────────
addCol($db, $migrations, 'workspaces', 'logo_url',    "VARCHAR(500) NOT NULL DEFAULT ''", 'description');

// ── members table ─────────────────────────────────────────────────────────
addCol($db, $migrations, 'members', 'workspace_id',        'INT DEFAULT NULL',                          'status');
addCol($db, $migrations, 'members', 'temp_password',       "VARCHAR(255) NOT NULL DEFAULT ''",          'password_hash');
addCol($db, $migrations, 'members', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0',            'temp_password');

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>TeamFlow — Migration</title>
<style>
  body{font-family:sans-serif;background:#0e0e1a;color:#f3f3f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:36px;max-width:560px;width:95%}
  h2{margin-bottom:20px;font-size:1.25rem}
  .item{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:14px;line-height:1.6}
  .item:last-child{border-bottom:none}
  .ic{flex-shrink:0;margin-top:1px;font-style:normal}
  .ok   .ic::before{content:'✓ ';color:#34d399}
  .err  .ic::before{content:'✗ ';color:#f87171}
  .skip .ic::before{content:'– ';color:#9ca3af}
  .warn{margin-top:24px;padding:14px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);border-radius:10px;color:#fca5a5;font-size:13px;line-height:1.7}
</style>
</head>
<body>
<div class="box">
  <h2>TeamFlow — Database Migration</h2>
  <?php foreach ($migrations as [$type, $msg]): ?>
    <div class="item <?= $type ?>"><i class="ic"></i><span><?= $msg ?></span></div>
  <?php endforeach; ?>
  <div class="warn">
    <strong>Done?</strong> Once all rows show ✓ or –, delete this file from your server.<br>
    Path: <code>teamflow/Teamapi/migrate.php</code>
  </div>
</div>
</body>
</html>
