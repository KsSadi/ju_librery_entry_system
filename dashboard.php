<?php
require_once 'includes/functions.php';
require_login();
$u = current_user();

$notice = '';
if ($u['must_change_password']) $notice = 'Please change your system-generated password.';

// ── Role-based WHERE clause ──────────────────────────────
$where  = "WHERE b.status='active'";
$params = [];
if ($u['role_name'] === 'Departmental Admin') {
    $where  .= ' AND b.department_id=?';
    $params[] = $u['department_id'];
} elseif ($u['role_name'] === 'Departmental Student') {
    $where  .= ' AND b.created_by=?';
    $params[] = $u['id'];
}

// ── Stats ────────────────────────────────────────────────
$qSummary = $pdo->prepare("SELECT
    COUNT(*) total,
    SUM(b.entry_type='new_entry')  new_entries,
    SUM(b.entry_type='copy_entry') copy_entries
    FROM book_entries b $where");
$qSummary->execute($params);
$summary = $qSummary->fetch();
$total       = (int)$summary['total'];
$newEntries  = (int)$summary['new_entries'];
$copyEntries = (int)$summary['copy_entries'];

$todayParams = array_merge($params);
$andOr  = $where ? 'AND' : 'WHERE';
$qToday = $pdo->prepare("SELECT COUNT(*) c FROM book_entries b $where $andOr DATE(b.created_at)=CURDATE()");
$qToday->execute($todayParams);
$today  = (int)$qToday->fetch()['c'];

include 'includes/header.php';
?>

<?php if ($notice): ?>
<div class="alert warning">
  <?= e($notice) ?> <a href="auth/change_password.php"><strong>Change now &rarr;</strong></a>
</div>
<?php endif; ?>

<div class="welcome-card">
  <h2>Welcome back, <?= e($u['name']) ?>!</h2>
  <p>Here's an overview of your library entries.</p>
  <span class="role-badge"><?= e($u['role_name']) ?></span>
  <?php if ($u['dept_name']): ?>
    <span class="dept-badge"><?= e($u['dept_name']) ?></span>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <!-- Total Entries -->
  <div class="stat-card">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a5736" stroke-width="2">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
      </svg>
    </div>
    <div class="stat-info">
      <h2><?= $total ?></h2>
      <p>Total Entries</p>
    </div>
  </div>

  <!-- New Entries -->
  <div class="stat-card green">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="16"/>
        <line x1="8"  y1="12" x2="16" y2="12"/>
      </svg>
    </div>
    <div class="stat-info">
      <h2><?= $newEntries ?></h2>
      <p>New Entries</p>
    </div>
  </div>

  <!-- Copy Entries -->
  <div class="stat-card blue">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2"/>
        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
      </svg>
    </div>
    <div class="stat-info">
      <h2><?= $copyEntries ?></h2>
      <p>Copy Entries</p>
    </div>
  </div>

  <!-- Today -->
  <div class="stat-card gold">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#c9a227" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
        <line x1="12" y1="14" x2="12" y2="18"/>
        <line x1="10" y1="16" x2="14" y2="16"/>
      </svg>
    </div>
    <div class="stat-info">
      <h2><?= $today ?></h2>
      <p>Today's Entries</p>
    </div>
  </div>
</div>

<div class="card">
  <h3>Quick Actions</h3>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a href="entries/create.php" class="btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      New Entry
    </a>
    <a href="entries/list.php" class="btn secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
        <circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/>
      </svg>
      View All Entries
    </a>
    <a href="reports/index.php" class="btn secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6"  y1="20" x2="6"  y2="14"/><line x1="2"  y1="20" x2="22" y2="20"/>
      </svg>
      Reports
    </a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
