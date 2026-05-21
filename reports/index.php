<?php
require_once '../includes/functions.php';
require_login();
$u = current_user();

// ── Date filters ───────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$to_full = $to . ' 23:59:59';

// ── Dept filter (admin only) ───────────────────────────────
$depts      = is_admin() ? $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll() : [];
$filter_dept = is_admin() ? (int)($_GET['dept_id'] ?? 0) : 0;

// ── Build WHERE clause based on role ──────────────────────
$where  = "WHERE b.status='active' AND b.created_at BETWEEN ? AND ?";
$params = [$from, $to_full];

if (is_admin()) {
    if ($filter_dept) {
        $where  .= ' AND b.department_id=?';
        $params[] = $filter_dept;
    }
} elseif (is_dept_admin()) {
    $where  .= ' AND b.department_id=?';
    $params[] = $u['department_id'];
} else {
    // Departmental Student — own entries only
    $where  .= ' AND b.created_by=?';
    $params[] = $u['id'];
}

// ── Summary stats ─────────────────────────────────────────
$qSum = $pdo->prepare("SELECT
    COUNT(*)                        AS total,
    SUM(b.entry_type='new_entry')     AS new_entries,
    SUM(b.entry_type='copy_entry')    AS copy_entries,
    SUM(b.entry_type='edition_entry') AS edition_entries
    FROM book_entries b $where");
$qSum->execute($params);
$sum = $qSum->fetch();
$stat_total    = (int)$sum['total'];
$stat_new      = (int)$sum['new_entries'];
$stat_copy     = (int)$sum['copy_entries'];
$stat_edition  = (int)$sum['edition_entries'];

// ── User-wise breakdown ────────────────────────────────────
$qUser = $pdo->prepare("SELECT u.name, u.email,
    COUNT(b.id)                       total,
    SUM(b.entry_type='new_entry')     new_e,
    SUM(b.entry_type='copy_entry')    copy_e,
    SUM(b.entry_type='edition_entry') edition_e
    FROM book_entries b
    JOIN users u ON b.created_by=u.id
    $where
    GROUP BY u.id ORDER BY total DESC");
$qUser->execute($params);
$userReport = $qUser->fetchAll();

// ── Department-wise breakdown (not for students) ──────────
$deptReport = [];
if (!is_dept_student()) {
    $qDept = $pdo->prepare("SELECT d.name dept,
        COUNT(b.id)                       total,
        SUM(b.entry_type='new_entry')     new_e,
        SUM(b.entry_type='copy_entry')    copy_e,
        SUM(b.entry_type='edition_entry') edition_e
        FROM book_entries b
        JOIN departments d ON b.department_id=d.id
        $where
        GROUP BY d.id ORDER BY total DESC");
    $qDept->execute($params);
    $deptReport = $qDept->fetchAll();
}

// ── Daily trend (last 14 days within range) ────────────────
$qDaily = $pdo->prepare("SELECT
    DATE(b.created_at)              day,
    COUNT(b.id)                     total,
    SUM(b.entry_type='new_entry')   new_e,
    SUM(b.entry_type='copy_entry')  copy_e,
    SUM(b.entry_type='edition_entry') edition_e
    FROM book_entries b $where
    GROUP BY DATE(b.created_at) ORDER BY day ASC");
$qDaily->execute($params);
$dailyRows = $qDaily->fetchAll();

include '../includes/header.php';
?>
<style>
/* ── Report Page ── */
.rp { width: 100%; }

.rp-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
.rp-breadcrumb { display:flex; align-items:center; gap:5px; font-size:12.5px; color:var(--text-muted); margin-bottom:5px; }
.rp-breadcrumb a { color:var(--primary); text-decoration:none; font-weight:500; }
.rp-breadcrumb a:hover { text-decoration:underline; }
.rp-breadcrumb svg { opacity:.5; }
.rp-page-title { font-size:21px; font-weight:800; color:var(--text); display:flex; align-items:center; gap:10px; }
.rp-title-bar  { width:4px; height:24px; background:var(--primary); border-radius:3px; flex-shrink:0; }

/* ── Filter card ── */
.rp-filter {
  background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg);
  box-shadow:var(--shadow-sm); padding:18px 22px; margin-bottom:20px;
}
.rp-filter-head { font-size:12.5px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.7px; margin-bottom:12px; display:flex; align-items:center; gap:7px; }
.rp-filter-head svg { width:14px; height:14px; }
.rp-filter-row  { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; }
.rp-filter-field { display:flex; flex-direction:column; gap:5px; }
.rp-filter-field label { font-size:12px; font-weight:600; color:#374151; }
.rp-filter-field input,
.rp-filter-field select {
  padding:8px 12px; border:1.5px solid var(--border); border-radius:8px;
  font-size:13px; color:var(--text); background:#fafafa; font-family:inherit;
  transition:border-color .2s;
}
.rp-filter-field input:focus,
.rp-filter-field select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,87,54,.1); }
.rp-filter-btn {
  display:inline-flex; align-items:center; gap:7px; padding:9px 22px;
  background:var(--primary); color:white; border:none; border-radius:9px;
  font-size:13.5px; font-weight:700; cursor:pointer; font-family:inherit;
  transition:all .18s; box-shadow:0 3px 10px rgba(26,87,54,.22);
}
.rp-filter-btn:hover { background:var(--primary-hover); transform:translateY(-1px); }
.rp-filter-btn svg { width:14px; height:14px; }

/* ── Summary stats ── */
.rp-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:20px; }
.rp-stat {
  background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg);
  box-shadow:var(--shadow-sm); padding:20px 22px; display:flex; align-items:center; gap:16px;
}
.rp-stat-icon { width:46px; height:46px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.rp-stat-icon svg { width:22px; height:22px; }
.rp-stat-icon.total { background:rgba(26,87,54,.1); }
.rp-stat-icon.green { background:rgba(34,197,94,.12); }
.rp-stat-icon.blue  { background:rgba(59,130,246,.12); }
.rp-stat-icon.amber { background:rgba(245,158,11,.12); }
.rp-stat-number { font-size:32px; font-weight:900; line-height:1; color:var(--text); }
.rp-stat-label  { font-size:12.5px; color:var(--text-muted); margin-top:3px; }
.rp-stat-bar    { height:4px; border-radius:4px; margin-top:8px; }
.rp-stat-bar.green { background:#22c55e; }
.rp-stat-bar.blue  { background:#3b82f6; }

/* ── Section card ── */
.rp-section {
  background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg);
  box-shadow:var(--shadow-sm); margin-bottom:18px; overflow:hidden;
}
.rp-section-head {
  display:flex; align-items:center; gap:13px; padding:14px 22px 13px;
  background:#f8fafc; border-bottom:1px solid var(--border);
}
.rp-section-icon { width:34px; height:34px; border-radius:8px; background:rgba(26,87,54,.1); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.rp-section-icon svg { width:17px; height:17px; color:var(--primary); }
.rp-section-title    { font-size:13px; font-weight:700; color:var(--text); }
.rp-section-subtitle { font-size:11.5px; color:var(--text-muted); margin-top:1px; }

/* ── Table badges ── */
.rp-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:20px; font-size:11.5px; font-weight:600; white-space:nowrap; }
.rp-badge svg { width:10px; height:10px; }
.rp-b-new  { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.rp-b-copy { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.rp-b-edition { background:#fefce8; color:#92400e; border:1px solid #fde68a; }
.rp-b-total{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }

/* ── Progress bar in table ── */
.rp-prog { height:5px; background:#f1f5f9; border-radius:4px; margin-top:5px; overflow:hidden; }
.rp-prog-fill { height:100%; border-radius:4px; background:var(--primary); transition:width .5s; }

/* ── Daily trend ── */
.rp-trend { display:flex; align-items:flex-end; gap:5px; min-height:80px; padding:18px 22px 10px; }
.rp-trend-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
.rp-trend-bars { display:flex; align-items:flex-end; gap:2px; width:100%; justify-content:center; }
.rp-bar-new  { flex:1; background:#22c55e; border-radius:3px 3px 0 0; min-height:3px; }
.rp-bar-copy { flex:1; background:#3b82f6; border-radius:3px 3px 0 0; min-height:3px; }
.rp-bar-edition { flex:1; background:#f59e0b; border-radius:3px 3px 0 0; min-height:3px; }
.rp-trend-date { font-size:10px; color:var(--text-muted); white-space:nowrap; }
.rp-trend-legend { display:flex; gap:16px; padding:0 22px 14px; }
.rp-trend-legend span { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-muted); }
.rp-trend-legend i { display:inline-block; width:12px; height:12px; border-radius:3px; flex-shrink:0; }

/* ── Empty state ── */
.rp-empty { text-align:center; padding:36px 20px; color:var(--text-muted); display:flex; flex-direction:column; align-items:center; gap:10px; }
.rp-empty svg { width:38px; height:38px; opacity:.3; }
.rp-empty p { font-size:13px; }

@media(max-width:700px){
  .rp-stats { grid-template-columns:1fr; }
  .rp-filter-row { flex-direction:column; align-items:stretch; }
}
</style>

<div class="rp">

<!-- ── Page Header ── -->
<div class="rp-header">
  <div>
    <div class="rp-breadcrumb">
      <a href="<?= BASE ?>/dashboard.php">Dashboard</a>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      <span>Reports</span>
    </div>
    <div class="rp-page-title">
      <div class="rp-title-bar"></div>
      Entry Reports
      <?php if (is_dept_student()): ?>
        <span style="font-size:13px;font-weight:500;color:var(--text-muted);">— My Entries</span>
      <?php elseif (is_dept_admin()): ?>
        <span style="font-size:13px;font-weight:500;color:var(--text-muted);">— <?= e($u['dept_name']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Filters ── -->
<div class="rp-filter">
  <div class="rp-filter-head">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filters
  </div>
  <form method="get" class="rp-filter-row">
    <div class="rp-filter-field">
      <label>From Date</label>
      <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="rp-filter-field">
      <label>To Date</label>
      <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <?php if (is_admin()): ?>
    <div class="rp-filter-field">
      <label>Department</label>
      <select name="dept_id" style="min-width:200px;">
        <option value="0">— All Departments —</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="rp-filter-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Apply
    </button>
    <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Reset
    </a>
  </form>
</div>

<!-- ── Summary Stats ── -->
<div class="rp-stats">
  <div class="rp-stat">
    <div class="rp-stat-icon total">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a5736" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div>
      <div class="rp-stat-number"><?= $stat_total ?></div>
      <div class="rp-stat-label">Total Entries</div>
    </div>
  </div>
  <div class="rp-stat">
    <div class="rp-stat-icon green">
      <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
    </div>
    <div style="flex:1;">
      <div class="rp-stat-number" style="color:#15803d;"><?= $stat_new ?></div>
      <div class="rp-stat-label">New Entries</div>
      <?php if ($stat_total > 0): ?>
      <div class="rp-prog"><div class="rp-prog-fill" style="width:<?= round($stat_new/$stat_total*100) ?>%;background:#22c55e;"></div></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="rp-stat">
    <div class="rp-stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    </div>
    <div style="flex:1;">
      <div class="rp-stat-number" style="color:#1d4ed8;"><?= $stat_copy ?></div>
      <div class="rp-stat-label">Copy Entries</div>
      <?php if ($stat_total > 0): ?>
      <div class="rp-prog"><div class="rp-prog-fill" style="width:<?= round($stat_copy/$stat_total*100) ?>%;background:#3b82f6;"></div></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="rp-stat">
    <div class="rp-stat-icon amber">
      <svg viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div style="flex:1;">
      <div class="rp-stat-number" style="color:#92400e;"><?= $stat_edition ?></div>
      <div class="rp-stat-label">Edition Entries</div>
      <?php if ($stat_total > 0): ?>
      <div class="rp-prog"><div class="rp-prog-fill" style="width:<?= round($stat_edition/$stat_total*100) ?>%;background:#f59e0b;"></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Daily Trend ── -->
<?php if (!empty($dailyRows)): ?>
<?php
  $maxDay = max(array_column($dailyRows, 'total')) ?: 1;
  $maxBarH = 70; // px
?>
<div class="rp-section">
  <div class="rp-section-head">
    <div class="rp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
    </div>
    <div>
      <div class="rp-section-title">Daily Trend</div>
      <div class="rp-section-subtitle">Entry activity across the selected period</div>
    </div>
  </div>
  <div class="rp-trend">
    <?php foreach ($dailyRows as $day): ?>
    <?php
      $newH  = $day['total'] > 0 ? max(3, round($day['new_e']     / $maxDay * $maxBarH)) : 3;
      $copyH = $day['total'] > 0 ? max(3, round($day['copy_e']    / $maxDay * $maxBarH)) : 3;
      $edH   = $day['total'] > 0 ? max(3, round($day['edition_e'] / $maxDay * $maxBarH)) : 3;
      $label = date('d M', strtotime($day['day']));
    ?>
    <div class="rp-trend-col" title="<?= e($label) ?>: <?= $day['new_e'] ?> new, <?= $day['copy_e'] ?> copy, <?= $day['edition_e'] ?> edition">
      <div class="rp-trend-bars">
        <div class="rp-bar-new"     style="height:<?= $newH  ?>px;"></div>
        <div class="rp-bar-copy"    style="height:<?= $copyH ?>px;"></div>
        <div class="rp-bar-edition" style="height:<?= $edH   ?>px;"></div>
      </div>
      <div class="rp-trend-date"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="rp-trend-legend">
    <span><i style="background:#22c55e;"></i> New Entry</span>
    <span><i style="background:#3b82f6;"></i> Copy Entry</span>
    <span><i style="background:#f59e0b;"></i> Edition Entry</span>
  </div>
</div>
<?php endif; ?>

<!-- ── User-wise Breakdown ── -->
<div class="rp-section">
  <div class="rp-section-head">
    <div class="rp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div>
      <div class="rp-section-title">User-wise Breakdown</div>
      <div class="rp-section-subtitle">Entries grouped by creator</div>
    </div>
  </div>
  <?php if (empty($userReport)): ?>
  <div class="rp-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
    <p>No entries found for this period.</p>
  </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Total</th>
        <th>New Entries</th>
        <th>Copy Entries</th>
        <th>Edition Entries</th>
        <th>Breakdown</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($userReport as $i => $row): ?>
      <?php
        $pNew = $row['total'] > 0 ? round($row['new_e']     /$row['total']*100) : 0;
        $pCpy = $row['total'] > 0 ? round($row['copy_e']    /$row['total']*100) : 0;
        $pEd  = $row['total'] > 0 ? round($row['edition_e'] /$row['total']*100) : 0;
      ?>
      <tr>
        <td style="color:var(--text-muted);font-size:12px;"><?= $i+1 ?></td>
        <td><strong><?= e($row['name']) ?></strong></td>
        <td style="color:var(--text-muted);"><?= e($row['email']) ?></td>
        <td><span class="rp-badge rp-b-total"><?= $row['total'] ?></span></td>
        <td><span class="rp-badge rp-b-new"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><?= $row['new_e'] ?></span></td>
        <td><span class="rp-badge rp-b-copy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?= $row['copy_e'] ?></span></td>
        <td><span class="rp-badge rp-b-edition"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><?= $row['edition_e'] ?></span></td>
        <td style="min-width:120px;">
          <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;background:#f1f5f9;">
            <div style="width:<?= $pNew ?>%;background:#22c55e;"></div>
            <div style="width:<?= $pCpy ?>%;background:#3b82f6;"></div>
            <div style="width:<?= $pEd  ?>%;background:#f59e0b;"></div>
          </div>
          <div style="font-size:10.5px;color:var(--text-muted);margin-top:3px;"><?= $pNew ?>% new</div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Department-wise Breakdown (non-students only) ── -->
<?php if (!is_dept_student()): ?>
<div class="rp-section">
  <div class="rp-section-head">
    <div class="rp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    </div>
    <div>
      <div class="rp-section-title">Department-wise Breakdown</div>
      <div class="rp-section-subtitle">Entries grouped by department</div>
    </div>
  </div>
  <?php if (empty($deptReport)): ?>
  <div class="rp-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
    <p>No entries found for this period.</p>
  </div>
  <?php else: ?>
  <?php $deptMax = max(array_column($deptReport,'total')) ?: 1; ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Department</th>
        <th>Total</th>
        <th>New Entries</th>
        <th>Copy Entries</th>
        <th>Edition Entries</th>
        <th>Breakdown</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($deptReport as $i => $row): ?>
      <?php
        $pNew = $row['total'] > 0 ? round($row['new_e']     /$row['total']*100) : 0;
        $pCpy = $row['total'] > 0 ? round($row['copy_e']    /$row['total']*100) : 0;
        $pEd  = $row['total'] > 0 ? round($row['edition_e'] /$row['total']*100) : 0;
      ?>
      <tr>
        <td style="color:var(--text-muted);font-size:12px;"><?= $i+1 ?></td>
        <td><strong><?= e($row['dept']) ?></strong></td>
        <td>
          <span class="rp-badge rp-b-total"><?= $row['total'] ?></span>
          <div class="rp-prog" style="margin-top:6px;width:100px;">
            <div class="rp-prog-fill" style="width:<?= round($row['total']/$deptMax*100) ?>%;"></div>
          </div>
        </td>
        <td><span class="rp-badge rp-b-new"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><?= $row['new_e'] ?></span></td>
        <td><span class="rp-badge rp-b-copy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><?= $row['copy_e'] ?></span></td>
        <td><span class="rp-badge rp-b-edition"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><?= $row['edition_e'] ?></span></td>
        <td style="min-width:120px;">
          <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;background:#f1f5f9;">
            <div style="width:<?= $pNew ?>%;background:#22c55e;"></div>
            <div style="width:<?= $pCpy ?>%;background:#3b82f6;"></div>
            <div style="width:<?= $pEd  ?>%;background:#f59e0b;"></div>
          </div>
          <div style="font-size:10.5px;color:var(--text-muted);margin-top:3px;"><?= $pNew ?>% new</div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>
<?php include '../includes/footer.php'; ?>

