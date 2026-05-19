<?php require_once '../includes/functions.php'; require_login(); $u=current_user();
$params=[]; $where="WHERE b.status='active'";
if($u['role_name']==='Departmental Admin'){
    $where.=' AND b.department_id=?'; $params[]=$u['department_id'];
} elseif($u['role_name']==='Departmental Student'){
    $where.=' AND b.created_by=?'; $params[]=$u['id'];
}
$s=$pdo->prepare("SELECT b.*, d.name dept, cu.name creator, mu.name modifier FROM book_entries b JOIN departments d ON b.department_id=d.id JOIN users cu ON b.created_by=cu.id LEFT JOIN users mu ON b.last_modified_by=mu.id $where ORDER BY b.id DESC"); $s->execute($params); $rows=$s->fetchAll(); include '../includes/header.php'; ?>
<style>
.et-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap;}
.et-new{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.et-copy{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.et-badge svg{width:11px;height:11px;}
</style>
<div class="card"><h2>Entries</h2><div class="searchbar"><input id="tableSearch" placeholder="Search entries..."><a class="btn" href="create.php">New Entry</a></div><table><thead><tr><th>ID</th><th>Acc No</th><th>Title</th><th>Type</th><th>Department</th><th>Created By</th><th>Created At</th><th>Modified By</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=$r['id']?></td><td><?=e($r['acc_no'])?></td><td><?=e($r['title'])?></td><td><?php $et=$r['entry_type']??'new_entry'; if($et==='copy_entry'): ?><span class="et-badge et-copy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy Entry</span><?php else: ?><span class="et-badge et-new"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>New Entry</span><?php endif; ?></td><td><?=e($r['dept'])?></td><td><?=e($r['creator'])?></td><td><?=e($r['created_at'])?></td><td><?=e($r['modifier'])?></td><td class="actions"><a class="btn secondary" href="view.php?id=<?=$r['id']?>">View</a><?php if(can_modify_entries()): ?><a class="btn" href="edit.php?id=<?=$r['id']?>">Edit</a><?php endif; ?><?php if(can_create_entry()): ?><a class="btn secondary" href="create.php?copy_from=<?=$r['id']?>" title="Duplicate this entry" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;">Copy</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include '../includes/footer.php'; ?>
