<?php
require_once __DIR__.'/functions.php';
$u = current_user();

$currentFile = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['PHP_SELF'];
$pageTitles  = [
    'dashboard'       => 'Dashboard',
    'create'          => 'New Book Entry',
    'list'            => 'All Entries',
    'edit'            => 'Edit Entry',
    'view'            => 'View Entry',
    'users'           => 'User Management',
    'approvals'       => 'Account Approvals',
    'index'           => 'Reports',
    'change_password' => 'Change Password',
];
$pageTitle = $pageTitles[$currentFile] ?? 'Library Management System';

function navActive($path, $needle) {
    return str_contains($path, $needle) ? 'active' : '';
}
$isEntries    = str_contains($currentPath, '/entries/list');
$pendingCount = $u ? pending_count() : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — JU Library</title>
  <link rel="icon" type="image/png" href="https://upload.wikimedia.org/wikipedia/en/thumb/a/a9/Jahangirnagar_University_Logo.svg/1280px-Jahangirnagar_University_Logo.svg.png">
  <link rel="stylesheet" href="/library_entry_system/assets/css/style.css">
</head>
<body>
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="page-wrapper">

  <!-- ════ SIDEBAR ════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="https://upload.wikimedia.org/wikipedia/en/thumb/a/a9/Jahangirnagar_University_Logo.svg/1280px-Jahangirnagar_University_Logo.svg.png" alt="JU Logo">
      <div class="sidebar-brand-text">
        <strong>Jahangirnagar University</strong>
        <span>Library Management</span>
      </div>
    </div>

    <div class="sidebar-body">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Main Menu</div>
        <nav class="sidebar-nav">

          <a href="/library_entry_system/dashboard.php" class="<?= navActive($currentPath,'dashboard') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Dashboard
          </a>

          <a href="/library_entry_system/entries/create.php" class="<?= navActive($currentPath,'/entries/create') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
              <line x1="12" y1="8" x2="12" y2="14"/><line x1="9" y1="11" x2="15" y2="11"/>
            </svg>
            New Entry
          </a>

          <a href="/library_entry_system/entries/list.php" class="<?= $isEntries ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
              <line x1="8" y1="18" x2="21" y2="18"/>
              <circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/>
            </svg>
            All Entries
          </a>

          <?php if ($u): ?>
          <a href="/library_entry_system/reports/index.php" class="<?= navActive($currentPath,'reports') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"/>
              <line x1="12" y1="20" x2="12" y2="4"/>
              <line x1="6"  y1="20" x2="6"  y2="14"/>
              <line x1="2"  y1="20" x2="22" y2="20"/>
            </svg>
            Reports
          </a>
          <?php endif; ?>

          <?php if ($u && can_approve()): ?>
          <a href="/library_entry_system/admin/approvals.php" class="<?= navActive($currentPath,'approvals') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <polyline points="16 11 18 13 22 9"/>
            </svg>
            Approvals
            <?php if ($pendingCount > 0): ?>
              <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;min-width:18px;text-align:center;">
                <?= $pendingCount ?>
              </span>
            <?php endif; ?>
          </a>
          <?php endif; ?>

          <?php if ($u && in_array($u['role_name'], ['Admin', 'Departmental Admin'])): ?>
          <a href="/library_entry_system/admin/users.php" class="<?= navActive($currentPath,'users') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            User Management
          </a>
          <?php endif; ?>
        </nav>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <nav class="sidebar-nav">
          <a href="/library_entry_system/auth/change_password.php" class="<?= navActive($currentPath,'change_password') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="7.5" cy="15.5" r="5.5"/>
              <path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>
            </svg>
            Change Password
          </a>
          <a href="/library_entry_system/auth/logout.php" class="logout-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Logout
          </a>
        </nav>
      </div>
    </div>

    <?php if($u): ?>
    <div class="sidebar-foot">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(mb_substr($u['name'], 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <strong><?= e($u['name']) ?></strong>
          <span><?= e($u['role_name']) ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ════ MAIN AREA ════ -->
  <div class="main-area">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="3" y1="6"  x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <span class="topbar-title"><?= e($pageTitle) ?></span>
      </div>
      <div class="topbar-right">
        <?php if($u): ?>
        <span class="topbar-badge"><?= e($u['role_name']) ?></span>
        <a href="/library_entry_system/auth/logout.php" class="topbar-logout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Logout
        </a>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">

