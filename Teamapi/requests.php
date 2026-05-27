<?php
require_once 'config.php';
require_once 'auth_check.php';

$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$role   = $CURRENT_USER['user_role'];
$uid    = $CURRENT_USER_ID;
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Scope: which requests can this user see / act on? ────────────────────
function reqScope($db, $uid, $role) {
    if ($role === 'admin') return '1=1';
    if ($role === 'director') {
        $r     = $db->query("SELECT directorate_id FROM members WHERE id=$uid")->fetch_assoc();
        $myDir = $r ? (int)$r['directorate_id'] : 0;
        if ($myDir > 0)
            return "(lr.member_id IN (SELECT id FROM members WHERE directorate_id=$myDir AND status!='inactive') OR lr.member_id=$uid)";
        return "(lr.member_id IN (SELECT id FROM members WHERE director_id=$uid) OR lr.member_id=$uid)";
    }
    return "lr.member_id = $uid";
}

// ── GET — list requests ──────────────────────────────────────────────────
if ($method === 'GET') {
    $sf    = isset($_GET['status']) ? safe($db, $_GET['status']) : '';
    $scope = reqScope($db, $uid, $role);
    $where = "($scope)";
    if ($sf && in_array($sf, ['pending','approved','rejected']))
        $where .= " AND lr.status = '$sf'";

    $rows = $db->query(
        "SELECT lr.*,
                m.name         AS member_name,
                m.avatar_color,
                rv.name        AS reviewer_name
         FROM   leave_requests lr
         JOIN   members m  ON lr.member_id   = m.id
         LEFT JOIN members rv ON lr.reviewed_by = rv.id
         WHERE  $where
         ORDER  BY FIELD(lr.status,'pending','approved','rejected'), lr.created_at DESC"
    );
    if (!$rows) respond(['error' => $db->error], 500);
    respond($rows->fetch_all(MYSQLI_ASSOC));
}

// ── POST — create OR review ──────────────────────────────────────────────
if ($method === 'POST') {
    $b = body();

    // ── Review action (approve / reject) ──────────────────────────────
    if (isset($b['action']) && in_array($b['action'], ['approve','reject'])) {
        if ($role === 'member') respond(['error' => 'Forbidden'], 403);
        if (!$id)               respond(['error' => 'ID required'], 400);

        // Directors may only review requests within their scope
        if ($role === 'director') {
            $scope = reqScope($db, $uid, $role);
            $ok    = $db->query("SELECT id FROM leave_requests lr WHERE lr.id=$id AND ($scope)")->fetch_assoc();
            if (!$ok) respond(['error' => 'Forbidden'], 403);
        }

        $newStatus = $b['action'] === 'approve' ? 'approved' : 'rejected';
        $notes     = safe($db, trim($b['notes'] ?? ''));
        $db->query(
            "UPDATE leave_requests
             SET status='$newStatus', reviewed_by=$uid, reviewer_notes='$notes'
             WHERE id=$id"
        );
        respond(['success' => true, 'status' => $newStatus]);
    }

    // ── Create new request ─────────────────────────────────────────────
    $valid_types = ['annual_leave','sick_leave','emergency_leave',
                    'maternity_leave','paternity_leave','short_break','other'];
    $type = in_array($b['type'] ?? '', $valid_types) ? $b['type'] : null;
    if (!$type) respond(['error' => 'Invalid request type'], 400);

    $start  = safe($db, trim($b['start_date'] ?? ''));
    $end    = !empty($b['end_date']) ? "'" . safe($db, trim($b['end_date'])) . "'" : 'NULL';
    $reason = safe($db, trim($b['reason'] ?? ''));

    if (!$start) respond(['error' => 'Start date is required'], 400);

    $db->query(
        "INSERT INTO leave_requests (member_id, type, start_date, end_date, reason)
         VALUES ($uid, '$type', '$start', $end, '$reason')"
    );
    respond(['success' => true, 'id' => $db->insert_id], 201);
}

// ── DELETE — member cancels pending; admin/director can delete in scope ──
if ($method === 'DELETE' && $id) {
    $req = $db->query(
        "SELECT member_id, status FROM leave_requests WHERE id=$id"
    )->fetch_assoc();
    if (!$req) respond(['error' => 'Not found'], 404);

    if ($role === 'member') {
        if ($req['member_id'] != $uid)      respond(['error' => 'Forbidden'], 403);
        if ($req['status'] !== 'pending')   respond(['error' => 'Only pending requests can be cancelled'], 400);
    } elseif ($role === 'director') {
        $scope = reqScope($db, $uid, $role);
        $ok    = $db->query("SELECT id FROM leave_requests lr WHERE lr.id=$id AND ($scope)")->fetch_assoc();
        if (!$ok) respond(['error' => 'Forbidden'], 403);
    }

    $db->query("DELETE FROM leave_requests WHERE id=$id");
    respond(['success' => true]);
}
