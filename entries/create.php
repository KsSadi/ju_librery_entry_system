<?php
require_once '../includes/functions.php';
require_login();
if (!can_create_entry()) { http_response_code(403); die('Access denied.'); }

$u      = current_user();
$depts  = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$fields = field_map();
$msg    = '';

// ── Copy / duplicate mode ──────────────────────────────
$copy_from_id = (int)($_GET['copy_from'] ?? 0);
$src          = [];
$is_copy      = false;
if ($copy_from_id) {
    $cs = $pdo->prepare("SELECT * FROM book_entries WHERE id=? AND status='active'");
    $cs->execute([$copy_from_id]);
    $src     = $cs->fetch() ?: [];
    $is_copy = !empty($src);
}

// ── Auto-detect next copy number ─────────────────────
$next_copy = 1;
if ($is_copy && !empty($src['acc_no'])) {
    $mc = $pdo->prepare("SELECT MAX(CAST(copy AS UNSIGNED)) FROM book_entries WHERE acc_no = ? AND status = 'active'");
    $mc->execute([$src['acc_no']]);
    $next_copy = (int)$mc->fetchColumn() + 1;
}
$copy_readonly = $is_copy && is_dept_student();

// ── Edition / new-edition mode ───────────────────────────────
$edition_from_id = (int)($_GET['edition_from'] ?? 0);
$is_edition      = false;
if (!$is_copy && $edition_from_id) {
    $es = $pdo->prepare("SELECT * FROM book_entries WHERE id=? AND status='active'");
    $es->execute([$edition_from_id]);
    $esrc = $es->fetch() ?: [];
    if (!empty($esrc)) { $src = $esrc; $is_edition = true; }
}
// Editable fields in edition mode: acc_no, edition, copy
$edition_readonly = $is_edition && is_dept_student();
$edition_editable = ['acc_no', 'edition', 'copy'];

$error_msg = '';

// helper – get prefill value for a field
function prefill($field, $src) { return htmlspecialchars((string)($src[$field] ?? ''), ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept = can_enter_all() ? (int)($_POST['department_id'] ?? 0) : $u['department_id'];
    if      ($is_copy)    $entry_type = 'copy_entry';
    elseif  ($is_edition) $entry_type = 'edition_entry';
    else                  $entry_type = ($_POST['entry_type'] ?? 'new_entry') === 'copy_entry' ? 'copy_entry' : 'new_entry';
    $cols = array_keys($fields);
    $sql  = 'INSERT INTO book_entries(' . implode(',', $cols) . ',department_id,created_by,entry_type) VALUES(' . str_repeat('?,', count($cols) + 2) . '?)';
    $vals = [];
    if ($copy_readonly) {
        // Use source values server-side; only override copy with next available number
        foreach ($cols as $c) $vals[] = ($c === 'copy') ? (string)$next_copy : ($src[$c] ?? null);
        $dept = (int)($src['department_id'] ?? $u['department_id']);
    } elseif ($is_edition) {
        // Editable: acc_no, edition, copy (from POST); rest from source
        $new_vals = [];
        foreach ($cols as $c) {
            $new_vals[$c] = in_array($c, $edition_editable)
                ? (trim($_POST[$c] ?? '') ?: null)
                : ($src[$c] ?? null);
        }
        // Validate: at least one editable field must differ from source
        $has_diff = false;
        foreach ($edition_editable as $c) {
            if ((string)($new_vals[$c] ?? '') !== (string)($src[$c] ?? '')) {
                $has_diff = true; break;
            }
        }
        if (!$has_diff) {
            $error_msg = 'At least one field (Acc. No, Edition, or Copy) must be different from the source entry.';
        } else {
            foreach ($cols as $c) $vals[] = $new_vals[$c];
            if ($edition_readonly) $dept = (int)($src['department_id'] ?? $u['department_id']);
        }
    } else {
        foreach ($cols as $c) $vals[] = trim($_POST[$c] ?? '') ?: null;
    }
    if (!$error_msg && !empty($vals)) {
        $vals[] = $dept;
        $vals[] = $u['id'];
        $vals[] = $entry_type;
        $pdo->prepare($sql)->execute($vals);
        $new_id = (int)$pdo->lastInsertId();
        redirect(BASE . '/entries/view.php?id=' . $new_id . '&saved=1');
    }
}

include '../includes/header.php';
?>
<style>
  .ef-page { width: 100%; }

  /* ── Page Header ── */
  .ef-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 14px; margin-bottom: 6px;
  }
  .ef-breadcrumb {
    display: flex; align-items: center; gap: 5px;
    font-size: 12.5px; color: var(--text-muted); margin-bottom: 5px;
  }
  .ef-breadcrumb a { color: var(--primary); text-decoration: none; font-weight: 500; }
  .ef-breadcrumb a:hover { text-decoration: underline; }
  .ef-breadcrumb svg { opacity: .5; }
  .ef-page-title {
    font-size: 21px; font-weight: 800; color: var(--text);
    display: flex; align-items: center; gap: 10px;
  }
  .ef-page-title-bar { width: 4px; height: 24px; background: var(--primary); border-radius: 3px; flex-shrink: 0; }

  /* ── Required note ── */
  .ef-req-note {
    font-size: 12.5px; color: var(--text-muted);
    display: flex; align-items: center; gap: 4px; margin-bottom: 20px;
  }
  .ef-req-note .dot { color: #ef4444; font-size: 15px; line-height: 1; }

  /* ── Alert ── */
  .ef-alert {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
    background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 13.5px;
  }
  .ef-alert svg { width: 20px; height: 20px; flex-shrink: 0; }
  .ef-alert a { color: var(--primary); font-weight: 700; margin-left: 6px; }

  /* ── Section card ── */
  .ef-section {
    background: var(--white); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm); margin-bottom: 18px;
    border: 1px solid var(--border); overflow: hidden;
  }
  .ef-section-head {
    display: flex; align-items: center; gap: 13px;
    padding: 15px 22px 14px; background: #f8fafc;
    border-bottom: 1px solid var(--border);
  }
  .ef-section-icon {
    width: 36px; height: 36px; border-radius: 9px;
    background: rgba(26,87,54,.1);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .ef-section-icon svg { width: 18px; height: 18px; color: var(--primary); }
  .ef-section-title    { font-size: 13.5px; font-weight: 700; color: var(--text); }
  .ef-section-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
  .ef-section-body { padding: 22px; }

  /* ── Field ── */
  .ef-field label {
    display: flex; align-items: center; gap: 4px;
    font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;
  }
  .ef-field label .req { color: #ef4444; font-size: 14px; line-height: 1; }
  .ef-field input,
  .ef-field select,
  .ef-field textarea {
    width: 100%; padding: 9px 12px; border: 1.5px solid var(--border);
    border-radius: 8px; font-size: 13.5px; color: var(--text);
    background: #fafafa; transition: border-color .2s, box-shadow .2s, background .2s;
    font-family: inherit;
  }
  .ef-field input:focus,
  .ef-field select:focus,
  .ef-field textarea:focus {
    outline: none; border-color: var(--primary);
    background: #fff; box-shadow: 0 0 0 3px rgba(26,87,54,.1);
  }
  .ef-field textarea { min-height: 90px; resize: vertical; }
  .ef-field .ef-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }

  /* ── Grids ── */
  .ef-g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .ef-g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
  .ef-g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; }
  .ef-span2 { grid-column: span 2; }
  .ef-span3 { grid-column: span 3; }
  .ef-full  { grid-column: 1 / -1; }

  /* ── Divider ── */
  .ef-sep { height: 1px; background: var(--border); margin: 18px 0; }

  /* ── Sticky Action Bar ── */
  .ef-actions {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 16px 22px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; box-shadow: var(--shadow-sm);
    position: sticky; bottom: 16px; z-index: 50; margin-top: 6px;
  }
  .ef-actions-info { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
  .ef-actions-info svg { width: 15px; height: 15px; opacity: .5; }
  .ef-actions-info strong { color: var(--text); }
  .ef-actions-btns { display: flex; gap: 10px; flex-wrap: wrap; }

  .ef-btn-save {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 26px; background: var(--primary); color: white;
    border: none; border-radius: 9px; font-size: 14px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: all .2s;
    box-shadow: 0 4px 14px rgba(26,87,54,.28);
  }
  .ef-btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,87,54,.35); }
  .ef-btn-save svg { width: 16px; height: 16px; }

  .ef-btn-reset {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 18px; background: #f1f5f9; color: #475569;
    border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: all .2s;
  }
  .ef-btn-reset:hover { background: #e2e8f0; }
  .ef-btn-reset svg { width: 15px; height: 15px; }

  /* ── Responsive ── */
  @media (max-width: 860px) {
    .ef-g3 { grid-template-columns: 1fr 1fr; }
    .ef-g4 { grid-template-columns: 1fr 1fr; }
    .ef-span3 { grid-column: span 2; }
  }
  @media (max-width: 600px) {
    .ef-g2, .ef-g3, .ef-g4 { grid-template-columns: 1fr; }
    .ef-span2, .ef-span3, .ef-full { grid-column: 1; }
    .ef-section-body { padding: 16px; }
    .ef-actions { position: static; }
  }

  /* ── Copy-readonly mode ── */
  .copy-readonly-form input,
  .copy-readonly-form textarea {
    background: #f1f5f9 !important; color: #64748b !important;
    cursor: default !important; border-color: #e2e8f0 !important;
    pointer-events: none;
  }
  .copy-readonly-form select {
    background: #f1f5f9 !important; color: #64748b !important;
    cursor: default !important; border-color: #e2e8f0 !important;
    pointer-events: none; opacity: 1;
  }
  .copy-readonly-form .ef-hint { display: none; }

  /* ── Edition mode ── */
  .edition-mode-form input[readonly],
  .edition-mode-form textarea[readonly] {
    background: #f1f5f9 !important; color: #64748b !important;
    cursor: default !important; border-color: #e2e8f0 !important;
  }
  .edition-mode-form select:disabled {
    background: #f1f5f9 !important; color: #64748b !important;
    cursor: default !important; border-color: #e2e8f0 !important; opacity: 1;
  }
  /* Highlight the 3 editable fields */
  .edition-mode-form input[name="acc_no"],
  .edition-mode-form input[name="edition"],
  .edition-mode-form input[name="copy"] {
    background: #f0fdf4 !important; color: var(--text) !important;
    border-color: var(--primary) !important; cursor: text !important;
    pointer-events: auto;
  }
  .edition-mode-form .ef-hint { display: none; }
  /* Error alert */
  .ef-alert-error {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
    background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-size: 13.5px;
  }
  .ef-alert-error svg { width: 20px; height: 20px; flex-shrink: 0; }
</style>

<div class="ef-page">

<?php if ($msg): ?>
<div class="ef-alert">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <?= e($msg) ?>
  <a href="<?= BASE ?>/entries/list.php">View All Entries →</a>
</div>
<?php endif; ?><?php if ($error_msg): ?>
<div class="ef-alert-error">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= e($error_msg) ?>
</div>
<?php endif; ?>
<form method="post" id="entryForm" autocomplete="off"<?= $copy_readonly ? ' class="copy-readonly-form"' : ($is_edition ? ' class="edition-mode-form"' : '') ?>>
<input type="hidden" name="entry_type" value="<?= $is_copy ? 'copy_entry' : ($is_edition ? 'edition_entry' : 'new_entry') ?>">
<?php if ($is_copy): ?><input type="hidden" name="copy_from" value="<?= (int)$copy_from_id ?>"><?php endif; ?>
<?php if ($is_edition): ?><input type="hidden" name="edition_from" value="<?= (int)$edition_from_id ?>"><?php endif; ?>

  <!-- Page Header -->
  <div class="ef-header">
    <div>
      <div class="ef-breadcrumb">
        <a href="<?= BASE ?>/dashboard.php">Dashboard</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        <span><?= $is_copy ? 'Copy Entry' : ($is_edition ? 'Edition Entry' : 'New Entry') ?></span>
      </div>
      <div class="ef-page-title">
        <div class="ef-page-title-bar"></div>
        <?= $is_copy ? 'Copy Book / Data Entry' : ($is_edition ? 'New Edition Entry' : 'New Book / Data Entry') ?>
      </div>
      <?php if ($is_copy): ?>
      <div style="margin-top:6px;font-size:12.5px;color:#6b7280;display:flex;align-items:center;gap:6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copying from entry <strong>#<?= (int)$copy_from_id ?></strong> &mdash; <em><?= e($src['acc_no'] ?? '') ?> <?= e($src['title'] ?? '') ?></em>
        <?php if ($copy_readonly): ?>&nbsp;&nbsp;<span style="background:#dbeafe;color:#1d4ed8;padding:2px 9px;border-radius:99px;font-size:11.5px;font-weight:700;">Copy <?= (int)$next_copy ?> will be created</span><?php endif; ?>
      </div>
      <?php elseif ($is_edition): ?>
      <div style="margin-top:6px;font-size:12.5px;color:#6b7280;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        New edition from entry <strong>#<?= (int)$edition_from_id ?></strong> &mdash; <em><?= e($src['acc_no'] ?? '') ?> <?= e($src['title'] ?? '') ?></em>
        &nbsp;<span style="background:#fef3c7;color:#92400e;padding:2px 9px;border-radius:99px;font-size:11.5px;font-weight:700;">Acc. No / Edition / Copy editable</span>
      </div>
      <?php endif; ?>
    </div>
    <a href="<?= BASE ?>/entries/list.php" class="btn secondary" style="margin-top:6px;font-size:13px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      All Entries
    </a>
  </div>

  <div class="ef-req-note"><span class="dot">*</span> Marked fields are required</div>

  <!-- ══ 1. Book Identification ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Book Identification</div>
        <div class="ef-section-subtitle">Core identifiers — accession number, title and ISBN</div>
      </div>
    </div>
    <div class="ef-section-body">

      <?php if (can_enter_all()): ?>
      <div class="ef-g3" style="margin-bottom:20px;">
        <div class="ef-field">
          <label>Department <span class="req">*</span></label>
          <select name="department_id" required>
            <option value="">— Select Department —</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($is_copy && isset($src['department_id']) && $src['department_id']==$d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="ef-sep"></div>
      <?php endif; ?>

      <div class="ef-g3">
        <div class="ef-field">
          <label>Acc. No <span class="req">*</span></label>
          <input name="acc_no" placeholder="e.g. CSE-2024-001" value="<?= prefill('acc_no',$src) ?>" required>
          <div class="ef-hint">Include department code prefix</div>
        </div>
        <div class="ef-field ef-span2">
          <label>Title <span class="req">*</span></label>
          <input name="title" placeholder="Full title of the book" value="<?= prefill('title',$src) ?>" required>
        </div>
        <div class="ef-field ef-span2">
          <label>Sub-Title</label>
          <input name="sub_title" placeholder="Sub-title if applicable" value="<?= prefill('sub_title',$src) ?>">
        </div>
        <div class="ef-field">
          <label>ISBN</label>
          <input name="isbn" placeholder="e.g. 978-0-06-112008-4" value="<?= prefill('isbn',$src) ?>">
        </div>
      </div>

    </div>
  </div>

  <!-- ══ 2. Authorship ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Authorship</div>
        <div class="ef-section-subtitle">Primary author, added authors and corporate body</div>
      </div>
    </div>
    <div class="ef-section-body">
      <div class="ef-g2">
        <div class="ef-field">
          <label>Author</label>
          <input name="author" placeholder="Primary author — Last, First" value="<?= prefill('author',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Author Name (as it is)</label>
          <input name="author_name_as_it_is" placeholder="Name exactly as printed on cover" value="<?= prefill('author_name_as_it_is',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Added Author 1</label>
          <input name="added_author_1" placeholder="Second author or editor" value="<?= prefill('added_author_1',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Added Author 2</label>
          <input name="added_author_2" placeholder="Third author or contributor" value="<?= prefill('added_author_2',$src) ?>">
        </div>
        <div class="ef-field ef-full">
          <label>Corporate Author</label>
          <input name="corporate_author" placeholder="Organisation or institution as author" value="<?= prefill('corporate_author',$src) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ══ 3. Publication Details ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Publication Details</div>
        <div class="ef-section-subtitle">Publisher, place, date and edition</div>
      </div>
    </div>
    <div class="ef-section-body">
      <div class="ef-g3">
        <div class="ef-field ef-span2">
          <label>Name of Publisher</label>
          <input name="publisher_name" placeholder="Publisher or press name" value="<?= prefill('publisher_name',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Place of Publication</label>
          <input name="place_of_publication" placeholder="City, Country" value="<?= prefill('place_of_publication',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Date of Publication</label>
          <input name="publication_date" placeholder="e.g. 2023" value="<?= prefill('publication_date',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Edition</label>
          <input name="edition" placeholder="e.g. 3rd, Revised" value="<?= prefill('edition',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Extent (Page Number)</label>
          <input name="extent_page_number" placeholder="e.g. xii, 450p" value="<?= prefill('extent_page_number',$src) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ══ 4. Series & Notes ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="12" x2="3" y2="12"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Series &amp; Notes</div>
        <div class="ef-section-subtitle">Series, volume and bibliography notes</div>
      </div>
    </div>
    <div class="ef-section-body">
      <div class="ef-g2">
        <div class="ef-field">
          <label>Series Name</label>
          <input name="series_name" placeholder="Series or collection name" value="<?= prefill('series_name',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Vol.</label>
          <input name="volume" placeholder="e.g. Vol. 2" value="<?= prefill('volume',$src) ?>">
        </div>
        <div class="ef-field ef-full">
          <label>Bibliography Note</label>
          <textarea name="bibliography_note" placeholder="Indexes, appendices, additional bibliographic notes…"><?= prefill('bibliography_note',$src) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ 5. Subject & Classification ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Subject &amp; Classification</div>
        <div class="ef-section-subtitle">Subject headings, class number and copy details</div>
      </div>
    </div>
    <div class="ef-section-body">
      <div class="ef-g3" style="margin-bottom:20px;">
        <div class="ef-field">
          <label>Subject 1</label>
          <input name="subject_1" placeholder="Primary subject heading" value="<?= prefill('subject_1',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Subject 2</label>
          <input name="subject_2" placeholder="Secondary subject heading" value="<?= prefill('subject_2',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Subject 3</label>
          <input name="subject_3" placeholder="Tertiary subject heading" value="<?= prefill('subject_3',$src) ?>">
        </div>
      </div>
      <div class="ef-sep"></div>
      <div class="ef-g4" style="margin-top:18px;">
        <div class="ef-field">
          <label>Class No.</label>
          <input name="class_no" placeholder="e.g. 510.1" value="<?= prefill('class_no',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Item Number</label>
          <input name="item_number" placeholder="e.g. MAT-001" value="<?= prefill('item_number',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Copy</label>
          <input name="copy" placeholder="e.g. 1" value="<?= $is_copy ? (int)$next_copy : ($is_edition ? '1' : prefill('copy',$src)) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ══ 6. Library Details ══ -->
  <div class="ef-section">
    <div class="ef-section-head">
      <div class="ef-section-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div>
        <div class="ef-section-title">Library Details</div>
        <div class="ef-section-subtitle">Home and current library location</div>
      </div>
    </div>
    <div class="ef-section-body">
      <div class="ef-g2">
        <div class="ef-field">
          <label>Home Library</label>
          <input name="home_library" placeholder="e.g. Central Library, JU" value="<?= prefill('home_library',$src) ?>">
        </div>
        <div class="ef-field">
          <label>Current Library</label>
          <input name="current_library" placeholder="e.g. Departmental Library" value="<?= prefill('current_library',$src) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ══ Sticky Action Bar ══ -->
  <div class="ef-actions">
    <div class="ef-actions-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Submitting as <strong><?= e($u['name']) ?></strong>
      <?php if (!can_enter_all() && !empty($u['dept_name'])): ?>
        &nbsp;·&nbsp; <strong><?= e($u['dept_name']) ?></strong>
      <?php endif; ?>
    </div>
    <div class="ef-actions-btns">
      <?php if (!$copy_readonly && !$edition_readonly): ?>
      <button type="reset" class="ef-btn-reset">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Reset Form
      </button>
      <?php endif; ?>
      <button type="submit" class="ef-btn-save">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Entry
      </button>
    </div>
  </div>

<?php if ($copy_readonly): ?>
<script>
document.querySelectorAll('#entryForm input:not([type=hidden]),#entryForm textarea').forEach(function(el){el.setAttribute('readonly','');});
document.querySelectorAll('#entryForm select').forEach(function(s){
  s.setAttribute('disabled','');
  var h=document.createElement('input');h.type='hidden';h.name=s.name;h.value=s.value;
  s.parentNode.appendChild(h);
});
</script>
<?php endif; ?>
<?php if ($edition_readonly): ?>
<script>
var editionSkip=['acc_no','edition','copy'];
document.querySelectorAll('#entryForm input:not([type=hidden])').forEach(function(el){
  if(editionSkip.indexOf(el.name)===-1) el.setAttribute('readonly','');
});
document.querySelectorAll('#entryForm textarea').forEach(function(el){el.setAttribute('readonly','');});
document.querySelectorAll('#entryForm select').forEach(function(s){
  s.setAttribute('disabled','');
  var h=document.createElement('input');h.type='hidden';h.name=s.name;h.value=s.value;
  s.parentNode.appendChild(h);
});
</script>
<?php endif; ?>
</form>
</div>

<?php include '../includes/footer.php'; ?>

