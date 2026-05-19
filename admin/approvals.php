<?php
require_once '../includes/functions.php';
require_login();
if (!can_approve()) { http_response_code(403); die('Access denied.'); }

$u   = current_user();
$msg = '';
$msgType = 'success';

// ── Handle approve / reject (POST for CSRF safety) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $action   = $_POST['action'];

    // Dept Admin may only act on their own department
    $check = $pdo->prepare("SELECT id, department_id FROM users WHERE id=? AND status='pending' LIMIT 1");
    $check->execute([$targetId]);
    $target = $check->fetch();

    if (!$target) {
        $msg = 'Account not found or already processed.'; $msgType = 'error';
    } elseif (is_dept_admin() && (int)$target['department_id'] !== (int)$u['department_id']) {
        $msg = 'Access denied: this account belongs to a different department.'; $msgType = 'error';
    } elseif ($action === 'approve') {
        $pdo->prepare("UPDATE users SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")
            ->execute([$u['id'], $targetId]);
        $msg = 'Account approved successfully. The student can now log in.';
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE users SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")
            ->execute([$u['id'], $targetId]);
        $msg = 'Account has been rejected.'; $msgType = 'error';
    }
}

// ── Fetch pending accounts ───────────────────────────────────────
$where  = "WHERE u.status='pending'";
$params = [];
if (is_dept_admin()) {
    $where  .= " AND u.department_id=?";
    $params[] = $u['department_id'];
}

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.student_id, u.email, u.mobile, u.created_at,
           d.name dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    $where
    ORDER BY u.created_at ASC
");
$stmt->execute($params);
$pending = $stmt->fetchAll();

// ── Fetch recently processed (last 20) ──────────────────────────
$pWhere  = is_dept_admin() ? "AND u.department_id=?" : "";
$pParams = is_dept_admin() ? [$u['department_id']] : [];
$proc = $pdo->prepare("
    SELECT u.id, u.name, u.student_id, u.email, u.status, u.approved_at,
           d.name dept_name, a.name approved_by_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users a ON u.approved_by = a.id
    WHERE u.status IN ('active','rejected') AND u.student_id IS NOT NULL $pWhere
    ORDER BY u.approved_at DESC
    LIMIT 20
");
$proc->execute($pParams);
$processed = $proc->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?>
  <div class="alert <?= $msgType ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- ── Pending Approvals ── -->
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:18px;">
    <h2 style="margin:0;">
      Pending Approvals
      <?php if (count($pending)): ?>
        <span style="background:#ef4444;color:#fff;font-size:12px;font-weight:700;padding:3px 9px;border-radius:20px;margin-left:8px;vertical-align:middle;">
          <?= count($pending) ?>
        </span>
      <?php endif; ?>
    </h2>
    <?php if (is_dept_admin()): ?>
      <span class="muted">Showing requests for: <strong><?= e($u['dept_name']) ?></strong></span>
    <?php endif; ?>
  </div>

  <?php if (empty($pending)): ?>
    <div style="text-align:center;padding:40px 0;color:#94a3b8;">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:10px;">
        <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
      </svg>
      <p>No pending account requests.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Student ID</th>
            <th>Department</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $i => $p): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td><?= e($p['student_id']) ?></td>
            <td><?= e($p['dept_name']) ?></td>
            <td><?= e($p['email']) ?></td>
            <td><?= e($p['mobile']) ?></td>
            <td style="white-space:nowrap;"><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
            <td>
              <div class="actions">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="action"  value="approve">
                  <button type="submit" class="btn" style="background:#22c55e;padding:6px 13px;font-size:12px;"
                          onclick="return confirm('Approve account for <?= e(addslashes($p['name'])) ?>?')">
                    ✓ Approve
                  </button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="action"  value="reject">
                  <button type="submit" class="btn danger" style="padding:6px 13px;font-size:12px;"
                          onclick="return confirm('Reject account for <?= e(addslashes($p['name'])) ?>?')">
                    ✗ Reject
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ── Recently Processed ── -->
<?php if (!empty($processed)): ?>
<div class="card">
  <h3>Recently Processed (last 20)</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Student ID</th>
          <th>Department</th>
          <th>Status</th>
          <th>Processed By</th>
          <th>Processed At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($processed as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td><?= e($p['student_id']) ?></td>
          <td><?= e($p['dept_name']) ?></td>
          <td>
            <?php if ($p['status'] === 'active'): ?>
              <span class="badge badge-success">Approved</span>
            <?php else: ?>
              <span class="badge badge-danger">Rejected</span>
            <?php endif; ?>
          </td>
          <td><?= e($p['approved_by_name']) ?></td>
          <td><?= $p['approved_at'] ? e(date('d M Y H:i', strtotime($p['approved_at']))) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
