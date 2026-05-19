<?php
require_once '../includes/functions.php';
require_login();

$u   = current_user();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $err = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $err = 'New password must be at least 8 characters long.';
    } elseif ($new !== $confirm) {
        $err = 'New passwords do not match.';
    } elseif ($current === $new) {
        $err = 'New password must be different from your current password.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?")
            ->execute([$hash, $_SESSION['user_id']]);
        $msg = 'Password updated successfully.';
    }
}

include '../includes/header.php';
?>
<style>
  .cp-wrap {
    max-width: 520px;
    margin: 0 auto;
  }

  /* Breadcrumb */
  .cp-breadcrumb {
    display: flex; align-items: center; gap: 5px;
    font-size: 12.5px; color: var(--text-muted); margin-bottom: 6px;
  }
  .cp-breadcrumb a { color: var(--primary); text-decoration: none; font-weight: 500; }
  .cp-breadcrumb a:hover { text-decoration: underline; }
  .cp-breadcrumb svg { opacity: .45; }

  .cp-page-title {
    font-size: 21px; font-weight: 800; color: var(--text);
    display: flex; align-items: center; gap: 10px; margin-bottom: 22px;
  }
  .cp-page-title-bar { width: 4px; height: 24px; background: var(--primary); border-radius: 3px; }

  /* Card */
  .cp-card {
    background: var(--white); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm); border: 1px solid var(--border); overflow: hidden;
  }

  /* Card header */
  .cp-card-head {
    padding: 20px 26px 18px;
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
    display: flex; align-items: center; gap: 14px;
  }
  .cp-card-head-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: rgba(255,255,255,.15);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .cp-card-head-icon svg { width: 22px; height: 22px; color: white; }
  .cp-card-head-title { font-size: 16px; font-weight: 700; color: white; }
  .cp-card-head-sub   { font-size: 12.5px; color: rgba(255,255,255,.65); margin-top: 2px; }

  /* Card body */
  .cp-card-body { padding: 26px; }

  /* User info strip */
  .cp-user-strip {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 15px; background: #f8fafc; border-radius: 10px;
    border: 1px solid var(--border); margin-bottom: 24px;
  }
  .cp-user-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--primary); color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 15px; flex-shrink: 0;
  }
  .cp-user-name  { font-size: 13.5px; font-weight: 700; color: var(--text); }
  .cp-user-role  { font-size: 12px; color: var(--text-muted); }

  /* Alerts */
  .cp-alert {
    display: flex; align-items: center; gap: 11px;
    padding: 13px 16px; border-radius: 9px; font-size: 13.5px;
    margin-bottom: 20px; border: 1px solid;
  }
  .cp-alert svg { width: 18px; height: 18px; flex-shrink: 0; }
  .cp-alert.success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
  .cp-alert.error   { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

  /* Form fields */
  .cp-field { margin-bottom: 18px; }
  .cp-field label {
    display: flex; align-items: center; gap: 5px;
    font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;
  }
  .cp-field label .req { color: #ef4444; }
  .cp-input-wrap { position: relative; }
  .cp-input-wrap input {
    width: 100%; padding: 10px 42px 10px 13px;
    border: 1.5px solid var(--border); border-radius: 9px;
    font-size: 14px; color: var(--text); background: #fafafa;
    font-family: inherit; transition: border-color .2s, box-shadow .2s, background .2s;
  }
  .cp-input-wrap input:focus {
    outline: none; border-color: var(--primary);
    background: #fff; box-shadow: 0 0 0 3px rgba(26,87,54,.1);
  }
  .cp-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; padding: 2px;
    color: #94a3b8; line-height: 0;
  }
  .cp-toggle:hover { color: #475569; }
  .cp-toggle svg { width: 16px; height: 16px; }

  /* Divider */
  .cp-divider {
    display: flex; align-items: center; gap: 10px;
    margin: 22px 0; color: var(--text-muted); font-size: 12px;
  }
  .cp-divider::before, .cp-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
  }

  /* Submit button */
  .cp-submit {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 700; font-family: inherit;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 4px 14px rgba(26,87,54,.28);
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .cp-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,87,54,.35); }
  .cp-submit svg { width: 17px; height: 17px; }

  /* Success done state */
  .cp-done {
    text-align: center; padding: 10px 0 4px;
  }
  .cp-done-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #f0fdf4; border: 2px solid #bbf7d0;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
  }
  .cp-done-icon svg { width: 34px; height: 34px; color: #22c55e; }
  .cp-done h3 { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
  .cp-done p  { font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; }
</style>

<div class="cp-wrap">

  <!-- Breadcrumb + Title -->
  <div class="cp-breadcrumb">
    <a href="<?= BASE ?>/dashboard.php">Dashboard</a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    <span>Change Password</span>
  </div>
  <div class="cp-page-title">
    <div class="cp-page-title-bar"></div>
    Change Password
  </div>

  <div class="cp-card">

    <!-- Card header -->
    <div class="cp-card-head">
      <div class="cp-card-head-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <div>
        <div class="cp-card-head-title">Account Security</div>
        <div class="cp-card-head-sub">Update your login password</div>
      </div>
    </div>

    <div class="cp-card-body">

      <?php if ($msg): ?>
      <!-- ── Success State ── -->
      <div class="cp-done">
        <div class="cp-done-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3>Password Updated!</h3>
        <p>Your password has been changed successfully.<br>Use your new password the next time you sign in.</p>
        <a href="<?= BASE ?>/dashboard.php" class="btn" style="display:inline-flex;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Back to Dashboard
        </a>
      </div>

      <?php else: ?>

      <!-- User info -->
      <div class="cp-user-strip">
        <div class="cp-user-avatar"><?= strtoupper(mb_substr($u['name'], 0, 1)) ?></div>
        <div>
          <div class="cp-user-name"><?= e($u['name']) ?></div>
          <div class="cp-user-role"><?= e($u['role_name']) ?><?= !empty($u['dept_name']) ? ' · ' . e($u['dept_name']) : '' ?></div>
        </div>
      </div>

      <?php if ($err): ?>
      <div class="cp-alert error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= e($err) ?>
      </div>
      <?php endif; ?>

      <form method="post" id="cpForm" autocomplete="off">

        <!-- Current password -->
        <div class="cp-field">
          <label>Current Password <span class="req">*</span></label>
          <div class="cp-input-wrap">
            <input type="password" name="current_password" id="cp_current" required placeholder="Enter your current password">
            <button type="button" class="cp-toggle" onclick="togglePw('cp_current',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="cp-divider">New Password</div>

        <!-- New password -->
        <div class="cp-field">
          <label>New Password <span class="req">*</span></label>
          <div class="cp-input-wrap">
            <input type="password" name="new_password" id="cp_new" required minlength="8"
                   placeholder="Choose a new password (min. 8 characters)">
            <button type="button" class="cp-toggle" onclick="togglePw('cp_new',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <!-- Confirm password -->
        <div class="cp-field">
          <label>Confirm New Password <span class="req">*</span></label>
          <div class="cp-input-wrap">
            <input type="password" name="confirm_password" id="cp_confirm" required minlength="8"
                   placeholder="Re-enter your new password">
            <button type="button" class="cp-toggle" onclick="togglePw('cp_confirm',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="cp-submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Update Password
        </button>

      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}


</script>

<?php include '../includes/footer.php'; ?>

