<?php
require_once '../includes/functions.php';
require_role(['Admin', 'Departmental Admin']);

$u       = current_user();
$isDeptAdmin = is_dept_admin();

// Roles available to create based on who is logged in
if ($isDeptAdmin) {
    $roles = $pdo->query("SELECT * FROM roles WHERE role_name IN ('Departmental Admin','Departmental Student') ORDER BY id")->fetchAll();
} else {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
}
$depts = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$msg     = '';
$msgType = 'success';

// ── Create new user (any role) ────────────────────────────────
if (isset($_POST['create_user'])) {
    $name      = trim($_POST['name']          ?? '');
    $email     = strtolower(trim($_POST['email']   ?? ''));
    $mobile    = trim($_POST['mobile']        ?? '');
    $roleId    = (int)($_POST['role_id']       ?? 0);
    // Dept Admin: force their own department
    $deptId    = $isDeptAdmin ? (int)$u['department_id'] : ((int)($_POST['department_id'] ?? 0) ?: null);
    $studentId = trim($_POST['student_id']    ?? '') ?: null;

    // Determine role name for validation
    $roleRow = $pdo->prepare("SELECT role_name FROM roles WHERE id=?");
    $roleRow->execute([$roleId]);
    $roleName = $roleRow->fetchColumn();

    // Dept Admin must not create Admin role
    if ($isDeptAdmin && $roleName === 'Admin') {
        $msg = 'You are not allowed to create Admin accounts.'; $msgType = 'error';
    } elseif (!$name || !$email || !$mobile || !$roleId) {
        $msg = 'All required fields must be filled.'; $msgType = 'error';
    } elseif ($roleName === 'Departmental Student' && !$studentId) {
        $msg = 'Student ID is required for Departmental Student accounts.'; $msgType = 'error';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $msg = 'Email already exists.'; $msgType = 'error';
        } else {
            $pwd  = random_password();
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (name, student_id, email, mobile, password_hash, role_id, department_id, must_change_password, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active')")
                ->execute([$name, $studentId, $email, $mobile, $hash, $roleId, $deptId]);
            $msg = "User created. Temporary password: <strong>" . e($pwd) . "</strong>";
        }
    }
}

// ── Reset password ─────────────────────────────────────────────
if (isset($_GET['reset'])) {
    $uid = (int)$_GET['reset'];
    // Dept Admin may only reset users in their department
    if ($isDeptAdmin) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND department_id=?");
        $chk->execute([$uid, $u['department_id']]);
        if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
    }
    $pwd  = random_password();
    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?")->execute([$hash, $uid]);
    $pdo->prepare("INSERT INTO password_reset_logs (user_id, reset_by) VALUES (?, ?)")->execute([$uid, $_SESSION['user_id']]);
    $msg = "Password reset. New temporary password: <strong>" . e($pwd) . "</strong>";
}

// ── Toggle active / inactive ───────────────────────────────────
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];    // Dept Admin may only toggle users in their department
    if ($isDeptAdmin) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND department_id=?");
        $chk->execute([$uid, $u['department_id']]);
        if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
    }    $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND id != ?")
        ->execute([$uid, $_SESSION['user_id']]);
    redirect(BASE . '/admin/users.php');
}

// ── Load users (active/inactive only – pending/rejected handled in Approvals) ──
if ($isDeptAdmin) {
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name, d.name dept_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status IN ('active','inactive') AND u.department_id = ?
        ORDER BY r.id, u.name
    ");
    $stmt->execute([$u['department_id']]);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query("
        SELECT u.*, r.role_name, d.name dept_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status IN ('active','inactive')
        ORDER BY r.id, u.name
    ")->fetchAll();
}

// Fetch the Departmental Student role id for JS
$studentRoleId = $pdo->query("SELECT id FROM roles WHERE role_name='Departmental Student' LIMIT 1")->fetchColumn();

include '../includes/header.php';
?>

<?php if ($msg): ?>
  <div class="alert <?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ── Create New User ── -->
<div class="card">
  <h2>Create User Account</h2>
  <p class="muted" style="margin-bottom:16px;">
    <?php if ($isDeptAdmin): ?>
      Create Departmental Admin or Departmental Student accounts for <strong><?= e($u['dept_name']) ?></strong>.
    <?php else: ?>
      Create Admin, Departmental Admin, or Departmental Student accounts directly.
      Students may also <a href="<?= BASE ?>/auth/register.php" target="_blank">self-register</a>
      and be approved via <a href="<?= BASE ?>/admin/approvals.php">Approvals</a>.
    <?php endif; ?>
  </p>
  <form method="post">
    <div class="form-grid">
      <div>
        <label>Full Name <span style="color:#ef4444">*</span></label>
        <input name="name" required>
      </div>
      <div>
        <label>Email <span style="color:#ef4444">*</span></label>
        <input type="email" name="email" required>
      </div>
      <div>
        <label>Mobile <span style="color:#ef4444">*</span></label>
        <input name="mobile" required>
      </div>
      <div>
        <label>Role <span style="color:#ef4444">*</span></label>
        <select name="role_id" id="roleSelect" required onchange="toggleStudentFields()">
          <option value="">— Select Role —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>"><?= e($r['role_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!$isDeptAdmin): ?>
      <div>
        <label>Department <small class="muted">(for Dept. Admin &amp; Student)</small></label>
        <select name="department_id">
          <option value="">— Not Applicable —</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div>
        <label>Department</label>
        <input type="text" value="<?= e($u['dept_name']) ?>" disabled style="background:#f1f5f9;color:#64748b;">
      </div>
      <?php endif; ?>
      <div id="studentIdField" style="display:none;">
        <label>Student ID <span style="color:#ef4444">*</span></label>
        <input name="student_id" id="studentIdInput" placeholder="e.g. 2021-1-19-001">
      </div>
      <div style="align-self:end;">
        <button class="btn" name="create_user">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Create User
        </button>
      </div>
    </div>
  </form>
</div>
<script>
const STUDENT_ROLE_ID = '<?= (int)$studentRoleId ?>';
function toggleStudentFields() {
  const sel = document.getElementById('roleSelect');
  const field = document.getElementById('studentIdField');
  const input = document.getElementById('studentIdInput');
  const isStudent = sel.value === STUDENT_ROLE_ID;
  field.style.display = isStudent ? '' : 'none';
  input.required = isStudent;
}
</script>

<!-- ── User List ── -->
<div class="card">
  <h2><?= $isDeptAdmin ? e($u['dept_name']).' — ' : '' ?>User Accounts</h2>
  <div class="searchbar">
    <input id="tableSearch" placeholder="Search users…">
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Student ID</th><th>Mobile</th><th>Role</th>
          <th>Department</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $x): ?>
        <tr>
          <td><strong><?= e($x['name']) ?></strong></td>
          <td><?= e($x['email']) ?></td>
          <td><?= e($x['student_id'] ?? '—') ?></td>
          <td><?= e($x['mobile']) ?></td>
          <td>
            <?php $bc = match($x['role_name']) {
                'Admin'              => 'badge-danger',
                'Departmental Admin' => 'badge-info',
                default              => 'badge-success',
            }; ?>
            <span class="badge <?= $bc ?>"><?= e($x['role_name']) ?></span>
          </td>
          <td><?= e($x['dept_name'] ?? '—') ?></td>
          <td>
            <span class="badge <?= $x['status']==='active' ? 'badge-success' : 'badge-danger' ?>">
              <?= ucfirst($x['status']) ?>
            </span>
          </td>
          <td>
            <div class="actions">
              <a class="btn secondary" style="padding:5px 10px;font-size:12px;"
                 href="?reset=<?= $x['id'] ?>"
                 data-confirm="Reset password for <?= e(addslashes($x['name'])) ?>?">Reset PW</a>
              <?php if ((int)$x['id'] !== (int)$_SESSION['user_id']): ?>
              <a class="btn <?= $x['status']==='active' ? 'danger' : '' ?>"
                 style="padding:5px 10px;font-size:12px;<?= $x['status']!=='active' ? 'background:#22c55e;' : '' ?>"
                 href="?toggle=<?= $x['id'] ?>"
                 data-confirm="<?= $x['status']==='active' ? 'Deactivate' : 'Activate' ?> this account?">
                <?= $x['status']==='active' ? 'Deactivate' : 'Activate' ?>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
