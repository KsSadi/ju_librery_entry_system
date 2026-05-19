<?php
require_once '../includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid request.');

$s = $pdo->prepare('SELECT b.*, d.name dept, cu.name creator, mu.name modifier
                    FROM book_entries b
                    JOIN departments d  ON b.department_id = d.id
                    JOIN users cu       ON b.created_by    = cu.id
                    LEFT JOIN users mu  ON b.last_modified_by = mu.id
                    WHERE b.id = ?');
$s->execute([$id]);
$r = $s->fetch();
if (!$r) die('Entry not found.');

$logs = $pdo->prepare('SELECT l.*, u.name modified_by_name
                       FROM entry_modification_logs l
                       JOIN users u ON l.modified_by = u.id
                       WHERE l.entry_id = ? ORDER BY l.id DESC');
$logs->execute([$id]);
$log_rows = $logs->fetchAll();

$saved    = !empty($_GET['saved']);
$et       = $r['entry_type'] ?? 'new_entry';
$is_copy  = $et === 'copy_entry';

include '../includes/header.php';
?>
<style>
/* ── View Page ── */
.vp { width: 100%; }

/* ── Header ── */
.vp-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 14px; margin-bottom: 20px;
}
.vp-breadcrumb {
  display: flex; align-items: center; gap: 5px;
  font-size: 12.5px; color: var(--text-muted); margin-bottom: 5px;
}
.vp-breadcrumb a { color: var(--primary); text-decoration: none; font-weight: 500; }
.vp-breadcrumb a:hover { text-decoration: underline; }
.vp-breadcrumb svg { opacity: .5; }
.vp-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.vp-page-title {
  font-size: 21px; font-weight: 800; color: var(--text);
  display: flex; align-items: center; gap: 10px;
}
.vp-title-bar { width: 4px; height: 24px; background: var(--primary); border-radius: 3px; flex-shrink: 0; }

/* ── Type Badge ── */
.vp-et-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
}
.vp-et-badge svg { width: 12px; height: 12px; }
.vp-et-new  { background: #f0fdf4; color: #15803d; border: 1.5px solid #86efac; }
.vp-et-copy { background: #eff6ff; color: #1d4ed8; border: 1.5px solid #93c5fd; }

/* ── Action buttons row ── */
.vp-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
.vp-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
  text-decoration: none; border: none; cursor: pointer; font-family: inherit;
  transition: all .18s;
}
.vp-btn svg { width: 14px; height: 14px; }
.vp-btn-primary { background: var(--primary); color: white; box-shadow: 0 3px 10px rgba(26,87,54,.25); }
.vp-btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
.vp-btn-copy   { background: #eff6ff; color: #1d4ed8; border: 1.5px solid #bfdbfe; }
.vp-btn-copy:hover { background: #dbeafe; transform: translateY(-1px); }
.vp-btn-secondary { background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; }
.vp-btn-secondary:hover { background: #e2e8f0; transform: translateY(-1px); }

/* ── Saved alert ── */
.vp-saved-alert {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
  background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 13.5px; font-weight: 500;
}
.vp-saved-alert svg { width: 20px; height: 20px; flex-shrink: 0; }

/* ── Meta strip ── */
.vp-meta {
  display: flex; flex-wrap: wrap; gap: 0;
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
  margin-bottom: 18px; overflow: hidden;
}
.vp-meta-item {
  flex: 1; min-width: 180px; padding: 14px 20px;
  border-right: 1px solid var(--border);
}
.vp-meta-item:last-child { border-right: none; }
.vp-meta-label {
  font-size: 10.5px; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .8px; margin-bottom: 4px;
}
.vp-meta-value { font-size: 13.5px; font-weight: 600; color: var(--text); }
.vp-meta-value.muted { color: var(--text-muted); font-style: italic; font-weight: 400; }

/* ── Section card ── */
.vp-section {
  background: var(--white); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm); margin-bottom: 18px;
  border: 1px solid var(--border); overflow: hidden;
}
.vp-section-head {
  display: flex; align-items: center; gap: 13px;
  padding: 14px 22px 13px; background: #f8fafc;
  border-bottom: 1px solid var(--border);
}
.vp-section-icon {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(26,87,54,.1);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.vp-section-icon svg { width: 17px; height: 17px; color: var(--primary); }
.vp-section-title    { font-size: 13px; font-weight: 700; color: var(--text); }
.vp-section-subtitle { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
.vp-section-body { padding: 20px 22px; }

/* ── Field grid ── */
.vp-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 28px; }
.vp-grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px 24px; }
.vp-grid4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px 20px; }
.vp-span2 { grid-column: span 2; }
.vp-span3 { grid-column: span 3; }
.vp-full  { grid-column: 1 / -1; }

.vp-field-label {
  font-size: 11px; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .7px; margin-bottom: 4px;
}
.vp-field-value {
  font-size: 13.5px; color: var(--text); line-height: 1.55;
  padding: 8px 12px; background: #f8fafc; border-radius: 7px;
  border: 1px solid var(--border); min-height: 36px; word-break: break-word;
}
.vp-field-value.empty { color: var(--text-muted); font-style: italic; }

/* ── Sep ── */
.vp-sep { height: 1px; background: var(--border); margin: 16px 0; }

/* ── Log table ── */
.vp-log-empty {
  text-align: center; padding: 30px 20px; color: var(--text-muted);
  font-size: 13px; display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.vp-log-empty svg { width: 32px; height: 32px; opacity: .3; }

/* ── Responsive ── */
@media (max-width: 860px) {
  .vp-grid3 { grid-template-columns: 1fr 1fr; }
  .vp-grid4 { grid-template-columns: 1fr 1fr; }
  .vp-span3 { grid-column: span 2; }
}
@media (max-width: 600px) {
  .vp-grid2, .vp-grid3, .vp-grid4 { grid-template-columns: 1fr; }
  .vp-span2, .vp-span3, .vp-full { grid-column: 1; }
  .vp-section-body { padding: 14px; }
  .vp-meta-item { border-right: none; border-bottom: 1px solid var(--border); }
  .vp-meta-item:last-child { border-bottom: none; }
}
</style>

<div class="vp">

<?php if ($saved): ?>
<div class="vp-saved-alert">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  Book entry saved successfully!
</div>
<?php endif; ?>

<!-- ── Page Header ── -->
<div class="vp-header">
  <div>
    <div class="vp-breadcrumb">
      <a href="<?= BASE ?>/dashboard.php">Dashboard</a>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      <a href="<?= BASE ?>/entries/list.php">All Entries</a>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      <span>Entry #<?= $r['id'] ?></span>
    </div>
    <div class="vp-title-row">
      <div class="vp-page-title">
        <div class="vp-title-bar"></div>
        <?= e($r['title']) ?>
      </div>
      <?php if ($is_copy): ?>
      <span class="vp-et-badge vp-et-copy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copy Entry
      </span>
      <?php else: ?>
      <span class="vp-et-badge vp-et-new">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Entry
      </span>
      <?php endif; ?>
    </div>
  </div>
  <div class="vp-actions">
    <?php if (can_create_entry()): ?>
    <a href="<?= BASE ?>/entries/create.php?copy_from=<?= $r['id'] ?>" class="vp-btn vp-btn-copy">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      Copy Entry
    </a>
    <?php endif; ?>
    <?php if (can_modify_entries()): ?>
    <a href="<?= BASE ?>/entries/edit.php?id=<?= $r['id'] ?>" class="vp-btn vp-btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Edit Entry
    </a>
    <?php endif; ?>
    <a href="<?= BASE ?>/entries/list.php" class="vp-btn vp-btn-secondary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      All Entries
    </a>
  </div>
</div>

<!-- ── Meta Strip ── -->
<div class="vp-meta">
  <div class="vp-meta-item">
    <div class="vp-meta-label">Entry ID</div>
    <div class="vp-meta-value">#<?= $r['id'] ?></div>
  </div>
  <div class="vp-meta-item">
    <div class="vp-meta-label">Department</div>
    <div class="vp-meta-value"><?= e($r['dept']) ?></div>
  </div>
  <div class="vp-meta-item">
    <div class="vp-meta-label">Created By</div>
    <div class="vp-meta-value"><?= e($r['creator']) ?></div>
  </div>
  <div class="vp-meta-item">
    <div class="vp-meta-label">Created At</div>
    <div class="vp-meta-value"><?= e($r['created_at']) ?></div>
  </div>
  <div class="vp-meta-item">
    <div class="vp-meta-label">Last Modified By</div>
    <div class="vp-meta-value <?= $r['modifier'] ? '' : 'muted' ?>"><?= $r['modifier'] ? e($r['modifier']) : '—' ?></div>
  </div>
  <div class="vp-meta-item">
    <div class="vp-meta-label">Last Modified At</div>
    <div class="vp-meta-value <?= $r['last_modified_at'] ? '' : 'muted' ?>"><?= $r['last_modified_at'] ? e($r['last_modified_at']) : '—' ?></div>
  </div>
</div>

<!-- ══ 1. Book Identification ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Book Identification</div>
      <div class="vp-section-subtitle">Core identifiers — accession number, title and ISBN</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid3">
      <div>
        <div class="vp-field-label">Acc. No</div>
        <div class="vp-field-value <?= $r['acc_no'] ? '' : 'empty' ?>"><?= $r['acc_no'] ? e($r['acc_no']) : 'Not provided' ?></div>
      </div>
      <div class="vp-span2">
        <div class="vp-field-label">Title</div>
        <div class="vp-field-value <?= $r['title'] ? '' : 'empty' ?>"><?= $r['title'] ? e($r['title']) : 'Not provided' ?></div>
      </div>
      <div class="vp-span2">
        <div class="vp-field-label">Sub-Title</div>
        <div class="vp-field-value <?= $r['sub_title'] ? '' : 'empty' ?>"><?= $r['sub_title'] ? e($r['sub_title']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">ISBN</div>
        <div class="vp-field-value <?= $r['isbn'] ? '' : 'empty' ?>"><?= $r['isbn'] ? e($r['isbn']) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ 2. Authorship ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Authorship</div>
      <div class="vp-section-subtitle">Primary author, added authors and corporate body</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid2">
      <div>
        <div class="vp-field-label">Author</div>
        <div class="vp-field-value <?= $r['author'] ? '' : 'empty' ?>"><?= $r['author'] ? e($r['author']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Author Name (as it is)</div>
        <div class="vp-field-value <?= $r['author_name_as_it_is'] ? '' : 'empty' ?>"><?= $r['author_name_as_it_is'] ? e($r['author_name_as_it_is']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Added Author 1</div>
        <div class="vp-field-value <?= $r['added_author_1'] ? '' : 'empty' ?>"><?= $r['added_author_1'] ? e($r['added_author_1']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Added Author 2</div>
        <div class="vp-field-value <?= $r['added_author_2'] ? '' : 'empty' ?>"><?= $r['added_author_2'] ? e($r['added_author_2']) : '—' ?></div>
      </div>
      <div class="vp-full">
        <div class="vp-field-label">Corporate Author</div>
        <div class="vp-field-value <?= $r['corporate_author'] ? '' : 'empty' ?>"><?= $r['corporate_author'] ? e($r['corporate_author']) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ 3. Publication Details ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Publication Details</div>
      <div class="vp-section-subtitle">Publisher, place, date and edition</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid3">
      <div class="vp-span2">
        <div class="vp-field-label">Name of Publisher</div>
        <div class="vp-field-value <?= $r['publisher_name'] ? '' : 'empty' ?>"><?= $r['publisher_name'] ? e($r['publisher_name']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Place of Publication</div>
        <div class="vp-field-value <?= $r['place_of_publication'] ? '' : 'empty' ?>"><?= $r['place_of_publication'] ? e($r['place_of_publication']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Date of Publication</div>
        <div class="vp-field-value <?= $r['publication_date'] ? '' : 'empty' ?>"><?= $r['publication_date'] ? e($r['publication_date']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Edition</div>
        <div class="vp-field-value <?= $r['edition'] ? '' : 'empty' ?>"><?= $r['edition'] ? e($r['edition']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Extent (Page Number)</div>
        <div class="vp-field-value <?= $r['extent_page_number'] ? '' : 'empty' ?>"><?= $r['extent_page_number'] ? e($r['extent_page_number']) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ 4. Series & Notes ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="12" x2="3" y2="12"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Series &amp; Notes</div>
      <div class="vp-section-subtitle">Series, volume and bibliography notes</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid2">
      <div>
        <div class="vp-field-label">Series Name</div>
        <div class="vp-field-value <?= $r['series_name'] ? '' : 'empty' ?>"><?= $r['series_name'] ? e($r['series_name']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Vol.</div>
        <div class="vp-field-value <?= $r['volume'] ? '' : 'empty' ?>"><?= $r['volume'] ? e($r['volume']) : '—' ?></div>
      </div>
      <div class="vp-full">
        <div class="vp-field-label">Bibliography Note</div>
        <div class="vp-field-value <?= $r['bibliography_note'] ? '' : 'empty' ?>" style="min-height:70px;"><?= $r['bibliography_note'] ? nl2br(e($r['bibliography_note'])) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ 5. Subject & Classification ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Subject &amp; Classification</div>
      <div class="vp-section-subtitle">Subject headings, class number and copy details</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid3" style="margin-bottom:16px;">
      <div>
        <div class="vp-field-label">Subject 1</div>
        <div class="vp-field-value <?= $r['subject_1'] ? '' : 'empty' ?>"><?= $r['subject_1'] ? e($r['subject_1']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Subject 2</div>
        <div class="vp-field-value <?= $r['subject_2'] ? '' : 'empty' ?>"><?= $r['subject_2'] ? e($r['subject_2']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Subject 3</div>
        <div class="vp-field-value <?= $r['subject_3'] ? '' : 'empty' ?>"><?= $r['subject_3'] ? e($r['subject_3']) : '—' ?></div>
      </div>
    </div>
    <div class="vp-sep"></div>
    <div class="vp-grid4" style="margin-top:16px;">
      <div>
        <div class="vp-field-label">Class No.</div>
        <div class="vp-field-value <?= $r['class_no'] ? '' : 'empty' ?>"><?= $r['class_no'] ? e($r['class_no']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Item Number</div>
        <div class="vp-field-value <?= $r['item_number'] ? '' : 'empty' ?>"><?= $r['item_number'] ? e($r['item_number']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Copy</div>
        <div class="vp-field-value <?= $r['copy'] ? '' : 'empty' ?>"><?= $r['copy'] ? e($r['copy']) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ 6. Library Details ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Library Details</div>
      <div class="vp-section-subtitle">Home and current library location</div>
    </div>
  </div>
  <div class="vp-section-body">
    <div class="vp-grid2">
      <div>
        <div class="vp-field-label">Home Library</div>
        <div class="vp-field-value <?= $r['home_library'] ? '' : 'empty' ?>"><?= $r['home_library'] ? e($r['home_library']) : '—' ?></div>
      </div>
      <div>
        <div class="vp-field-label">Current Library</div>
        <div class="vp-field-value <?= $r['current_library'] ? '' : 'empty' ?>"><?= $r['current_library'] ? e($r['current_library']) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ Modification Log ══ -->
<div class="vp-section">
  <div class="vp-section-head">
    <div class="vp-section-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div>
      <div class="vp-section-title">Modification Log</div>
      <div class="vp-section-subtitle">History of all changes made to this entry</div>
    </div>
  </div>
  <div class="vp-section-body" style="padding: 0;">
    <?php if (empty($log_rows)): ?>
    <div class="vp-log-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
      No modifications recorded yet.
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Field</th>
          <th>Old Value</th>
          <th>New Value</th>
          <th>Modified By</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($log_rows as $l): ?>
        <tr>
          <td><strong><?= e($l['field_name']) ?></strong></td>
          <td style="color:var(--text-muted);"><?= e($l['old_value']) ?></td>
          <td><?= e($l['new_value']) ?></td>
          <td><?= e($l['modified_by_name']) ?></td>
          <td style="white-space:nowrap;color:var(--text-muted);"><?= e($l['modified_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</div>
<?php include '../includes/footer.php'; ?>

