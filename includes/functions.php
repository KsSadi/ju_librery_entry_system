<?php
require_once __DIR__ . '/config.php';

function redirect($url){ header("Location: $url"); exit; }
function is_logged_in(){ return isset($_SESSION['user_id']); }

function current_user(){
    static $cached = null;
    global $pdo;
    if (!is_logged_in()) return null;
    if ($cached !== null) return $cached;
    $s = $pdo->prepare("SELECT u.*, r.role_name, d.name dept_name
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        LEFT JOIN departments d ON u.department_id = d.id
                        WHERE u.id = ?");
    $s->execute([$_SESSION['user_id']]);
    $cached = $s->fetch();
    return $cached;
}

function require_login(){ if (!is_logged_in()) redirect('/library_entry_system/auth/login.php'); }

function has_role($roles){
    $u = current_user();
    return $u && in_array($u['role_name'], (array)$roles);
}

function require_role($roles){
    require_login();
    if (!has_role($roles)) { http_response_code(403); die('Access denied.'); }
}

/* ── Role helpers ─────────────────────────────────── */
function is_admin()        { return has_role('Admin'); }
function is_dept_admin()   { return has_role('Departmental Admin'); }
function is_dept_student() { return has_role('Departmental Student'); }

// Can choose any department when creating an entry
function can_enter_all()       { return is_admin(); }

// Can create new entries
function can_create_entry()    { return has_role(['Admin','Departmental Admin','Departmental Student']); }

// Can edit / delete entries
function can_modify_entries()  { return has_role(['Admin','Departmental Admin']); }

// Can approve / reject pending student accounts
function can_approve()         { return has_role(['Admin','Departmental Admin']); }

/* ── Pending approvals count (for nav badge) ─────── */
function pending_count(){
    global $pdo;
    $u = current_user();
    if (!$u || !can_approve()) return 0;
    if (is_admin()) {
        return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
    }
    $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status='pending' AND department_id=?");
    $s->execute([$u['department_id']]);
    return (int)$s->fetchColumn();
}

/* ── Utilities ───────────────────────────────────── */
function random_password($length = 12){
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$!%*?&';
    $p = '';
    for ($i = 0; $i < $length; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function field_map(){ return [
    'acc_no'               => 'Acc. No (including Dept. Code)',
    'author'               => 'Author',
    'added_author_1'       => 'Added Author 1',
    'added_author_2'       => 'Added Author 2',
    'corporate_author'     => 'Corporate Author',
    'title'                => 'Title',
    'sub_title'            => 'Sub-Title',
    'author_name_as_it_is' => 'Author Name as it is',
    'place_of_publication' => 'Place of publication',
    'publisher_name'       => 'Name of publisher',
    'publication_date'     => 'Date of publication',
    'extent_page_number'   => 'Extent (Page Number)',
    'series_name'          => 'Series Name',
    'bibliography_note'    => 'Bibliography note',
    'subject_1'            => 'Subject 1',
    'subject_2'            => 'Subject 2',
    'subject_3'            => 'Subject 3',
    'home_library'         => 'Home library',
    'current_library'      => 'Current library',
    'isbn'                 => 'ISBN',
    'edition'              => 'Edition',
    'class_no'             => 'Class No.',
    'item_number'          => 'Item Number',
    'copy'                 => 'Copy',
    'volume'               => 'Vol.',
]; }

