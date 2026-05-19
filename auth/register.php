<?php
require_once '../includes/functions.php';
if (is_logged_in()) redirect('/library_entry_system/dashboard.php');

$err  = '';
$done = false;
$depts = $pdo->query("SELECT id, name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$studentRoleId = (int)$pdo->query("SELECT id FROM roles WHERE role_name='Departmental Student' LIMIT 1")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']        ?? '');
    $sid     = trim($_POST['student_id']  ?? '');
    $deptId  = (int)($_POST['department_id'] ?? 0);
    $email   = strtolower(trim($_POST['email']   ?? ''));
    $pass    = $_POST['password']          ?? '';
    $confirm = $_POST['confirm_password']  ?? '';
    $mobile  = trim($_POST['mobile']       ?? '');

    if (!$name || !$sid || !$deptId || !$email || !$pass || !$mobile) {
        $err = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters long.';
    } elseif ($pass !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $err = 'This email is already registered.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users
                (name, student_id, email, mobile, password_hash, role_id, department_id, must_change_password, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pending')")
                ->execute([$name, $sid, $email, $mobile, $hash, $studentRoleId, $deptId]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Registration — JU Central Library</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      background: #f0f4f7;
    }

    /* ═══════════════ LEFT PANEL ═══════════════ */
    .auth-panel {
      width: 40%;
      min-height: 100vh;
      background: linear-gradient(160deg, #071a1f 0%, #0d3d3d 35%, #0f766e 70%, #0a4d4d 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      padding: 50px 36px 0;
      position: relative;
      overflow: hidden;
      flex-shrink: 0;
    }
    .auth-panel::before {
      content: '';
      position: absolute;
      width: 450px; height: 450px; border-radius: 50%;
      background: radial-gradient(circle, rgba(6,182,212,.12) 0%, transparent 70%);
      top: -60px; right: -110px; pointer-events: none;
    }
    .auth-panel::after {
      content: '';
      position: absolute;
      width: 300px; height: 300px; border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,.05) 0%, transparent 70%);
      bottom: 140px; left: -60px; pointer-events: none;
    }
    .panel-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
      background-size: 28px 28px; pointer-events: none;
    }
    .panel-inner {
      position: relative; z-index: 1;
      display: flex; flex-direction: column; align-items: center; text-align: center; width: 100%;
    }

    .panel-logo {
      width: 110px; height: 110px; object-fit: contain; margin-bottom: 20px;
      background: #ffffff; border-radius: 50%; padding: 10px;
      box-shadow: 0 6px 28px rgba(0,0,0,.22), 0 0 0 3px rgba(6,182,212,.35), 0 0 0 6px rgba(6,182,212,.1);
    }

    .panel-univ {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 20px; font-weight: 800; color: white; line-height: 1.35;
    }
    .panel-library {
      font-size: 12px; font-weight: 600; color: #06b6d4;
      letter-spacing: 2.5px; text-transform: uppercase; margin-top: 7px;
    }
    .panel-divider {
      width: 46px; height: 2px;
      background: linear-gradient(90deg, transparent, #06b6d4, transparent);
      margin: 18px auto;
    }
    .panel-steps {
      text-align: left; width: 100%; max-width: 240px;
    }
    .panel-steps h4 {
      font-size: 11px; font-weight: 700; color: rgba(255,255,255,.5);
      text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px;
    }
    .step-item {
      display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;
    }
    .step-num {
      width: 26px; height: 26px; border-radius: 50%;
      background: rgba(6,182,212,.25); border: 1.5px solid rgba(6,182,212,.5);
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; color: #06b6d4; flex-shrink: 0; margin-top: 1px;
    }
    .step-text strong { display: block; font-size: 12.5px; color: white; margin-bottom: 1px; }
    .step-text span   { font-size: 11.5px; color: rgba(255,255,255,.5); line-height: 1.5; }

    /* Floating books */
    .float-books { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
    .fb { position: absolute; opacity: .1; animation: floatUp 6s ease-in-out infinite; }
    .fb:nth-child(1) { top: 12%; left: 6%;   animation-delay: 0s;   animation-duration: 7s; }
    .fb:nth-child(2) { top: 45%; right: 6%;  animation-delay: 2s;   animation-duration: 8s; }
    .fb:nth-child(3) { top: 68%; left: 10%;  animation-delay: 1s;   animation-duration: 6.5s; }
    @keyframes floatUp {
      0%,100% { transform: translateY(0) rotate(-4deg); }
      50%      { transform: translateY(-14px) rotate(4deg); }
    }

    .panel-shelf { width: 100%; position: relative; z-index: 1; margin-top: 24px; }
    .panel-shelf svg { width: 100%; }

    /* ═══════════════ RIGHT FORM PANEL ═══════════════ */
    .auth-form-side {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 40px 24px; overflow-y: auto;
    }
    .auth-form-box {
      width: 100%; max-width: 480px;
      background: white; border-radius: 20px;
      padding: 40px 40px 32px;
      box-shadow: 0 8px 48px rgba(0,0,0,.1);
      my-8: auto;
    }
    .form-heading h2 {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 24px; font-weight: 800; color: #0d3d3d; margin-bottom: 4px;
    }
    .form-heading p { color: #64748b; font-size: 13px; margin-bottom: 24px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-grid .full { grid-column: 1 / -1; }

    .form-label {
      display: block; font-size: 12.5px; font-weight: 600; color: #374151;
      margin-bottom: 5px; letter-spacing: .2px;
    }
    .req { color: #ef4444; }

    .input-wrap { position: relative; margin-bottom: 0; }
    .input-wrap .i-icon {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;
    }
    .input-wrap .i-icon svg { width: 15px; height: 15px; color: #94a3b8; }
    .auth-input {
      width: 100%; padding: 10px 12px 10px 36px;
      border: 1.5px solid #e2e8f0; border-radius: 9px;
      font-size: 13.5px; color: #1e293b; font-family: inherit;
      transition: border-color .2s, box-shadow .2s; background: #fafafa;
    }
    .auth-input:focus {
      outline: none; border-color: #0f766e; background: #fff;
      box-shadow: 0 0 0 3px rgba(15,118,110,.1);
    }
    .pw-toggle {
      position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; padding: 2px;
      color: #94a3b8; line-height: 0;
    }
    .pw-toggle:hover { color: #475569; }
    .pw-toggle svg { width: 15px; height: 15px; }

    .btn-register {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #0f766e, #0d3d3d);
      color: white; border: none; border-radius: 10px;
      font-size: 15px; font-weight: 700; font-family: inherit;
      cursor: pointer; letter-spacing: .3px; transition: all .2s;
      box-shadow: 0 4px 16px rgba(13,61,61,.35);
    }
    .btn-register:hover {
      background: linear-gradient(135deg, #14b8a6, #0f766e);
      transform: translateY(-1px); box-shadow: 0 6px 20px rgba(13,61,61,.4);
    }

    .alert-box {
      padding: 12px 15px; border-radius: 9px; font-size: 13px;
      margin-bottom: 18px; border-left: 4px solid; line-height: 1.55;
    }
    .alert-error { background: #fef2f2; color: #dc2626; border-color: #ef4444; }

    .form-footer {
      text-align: center; margin-top: 18px; padding-top: 16px;
      border-top: 1px solid #f1f5f9; font-size: 13px; color: #64748b;
    }
    .form-footer a { color: #0f766e; font-weight: 700; text-decoration: none; }
    .form-footer a:hover { text-decoration: underline; }
    .copyright { margin-top: 12px; font-size: 11.5px; color: #cbd5e1; text-align: center; }

    /* Success state */
    .done-card { text-align: center; padding: 16px 0; }
    .done-icon {
      width: 80px; height: 80px; border-radius: 50%;
      background: #f0fdfd; border: 2px solid #a5f3fc;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
    }
    .done-icon svg { width: 38px; height: 38px; color: #06b6d4; }
    .done-card h3 { font-family: 'Playfair Display', serif; font-size: 22px; color: #0d3d3d; margin-bottom: 10px; }
    .done-card p  { color: #64748b; font-size: 13.5px; line-height: 1.7; max-width: 320px; margin: 0 auto; }
    .done-steps { margin: 20px auto; max-width: 300px; text-align: left; }
    .done-step { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #475569; }
    .done-step:last-child { border-bottom: none; }
    .done-step svg { width: 16px; height: 16px; color: #22c55e; flex-shrink: 0; }

    /* Responsive */
    @media (max-width: 900px) {
      body {
        flex-direction: column;
        background: #f0fdfd;
        min-height: 100vh;
      }
      .auth-panel {
        width: 100%;
        min-height: unset;
        padding: 44px 24px 76px;
        background: linear-gradient(150deg, #071a1f 0%, #0d3d3d 45%, #0f766e 100%);
        justify-content: center;
        position: relative;
        overflow: hidden;
      }
      .auth-panel::before {
        width: 280px; height: 280px;
        top: -50px; right: -70px;
        background: radial-gradient(circle, rgba(6,182,212,.22) 0%, transparent 70%);
      }
      /* Wave separator at the bottom of the header */
      .auth-panel::after {
        content: '';
        position: absolute;
        bottom: -1px; left: -5%;
        width: 110%; height: 60px;
        background: #f0fdfd;
        border-radius: 50% 50% 0 0 / 60px 60px 0 0;
        pointer-events: none;
        z-index: 2;
      }
      .panel-dots { display: block; }
      .float-books, .panel-shelf { display: none; }
      .panel-inner { width: 100%; position: relative; z-index: 1; }
      .panel-logo { width: 84px; height: 84px; margin-bottom: 14px; padding: 9px; }
      .panel-univ { font-size: 18px; }
      .panel-library { font-size: 11.5px; margin-top: 5px; }
      .panel-divider, .panel-steps { display: none; }
      .form-grid { grid-template-columns: 1fr; }
      .auth-form-side {
        flex: unset;
        width: 100%;
        padding: 4px 18px 52px;
        justify-content: center;
        background: transparent;
        position: relative;
        z-index: 3;
        margin-top: -28px;
      }
      .auth-form-box {
        box-shadow: 0 12px 48px rgba(15,118,110,.2), 0 2px 12px rgba(6,182,212,.1);
        border-radius: 22px;
        border: 1px solid rgba(6,182,212,.13);
      }
    }
    @media (max-width: 520px) {
      .auth-panel { padding: 36px 18px 64px; }
      .auth-form-side { padding: 0 12px 40px; margin-top: -22px; }
      .auth-form-box { padding: 26px 18px 22px; border-radius: 18px; }
      .panel-logo { width: 70px; height: 70px; padding: 8px; }
      .panel-univ { font-size: 16px; }
      .form-heading h2 { font-size: 22px; }
      .auth-input { padding: 11px 12px 11px 36px; font-size: 15px; }
      .btn-register { padding: 14px; font-size: 15px; }
    }
  </style>
</head>
<body>

<!-- ═══════ LEFT PANEL ═══════ -->
<div class="auth-panel">
  <div class="panel-dots"></div>
  <div class="float-books">
    <div class="fb"><svg width="50" height="64" viewBox="0 0 50 64" fill="none"><rect x="2" y="2" width="46" height="60" rx="4" fill="white"/><rect x="2" y="2" width="9" height="60" rx="4" fill="#06b6d4"/><line x1="17" y1="18" x2="42" y2="18" stroke="#ccc" stroke-width="2"/><line x1="17" y1="26" x2="42" y2="26" stroke="#ccc" stroke-width="2"/></svg></div>
    <div class="fb"><svg width="42" height="56" viewBox="0 0 42 56" fill="none"><rect x="2" y="2" width="38" height="52" rx="4" fill="white"/><rect x="2" y="2" width="8" height="52" rx="4" fill="#22c55e"/><line x1="15" y1="16" x2="34" y2="16" stroke="#ccc" stroke-width="2"/><line x1="15" y1="24" x2="34" y2="24" stroke="#ccc" stroke-width="2"/></svg></div>
    <div class="fb"><svg width="48" height="62" viewBox="0 0 48 62" fill="none"><rect x="2" y="2" width="44" height="58" rx="4" fill="white"/><rect x="2" y="2" width="9" height="58" rx="4" fill="#3b82f6"/><line x1="17" y1="18" x2="40" y2="18" stroke="#ccc" stroke-width="2"/><line x1="17" y1="26" x2="40" y2="26" stroke="#ccc" stroke-width="2"/><line x1="17" y1="34" x2="32" y2="34" stroke="#ccc" stroke-width="2"/></svg></div>
  </div>

  <div class="panel-inner">
    <img src="https://upload.wikimedia.org/wikipedia/en/thumb/a/a9/Jahangirnagar_University_Logo.svg/1280px-Jahangirnagar_University_Logo.svg.png" alt="JU Logo" class="panel-logo">
    <div class="panel-univ">Jahangirnagar University</div>
    <div class="panel-library">Central Library</div>
    <div class="panel-divider"></div>

    <div class="panel-steps">
      <h4>How it works</h4>
      <div class="step-item">
        <div class="step-num">1</div>
        <div class="step-text">
          <strong>Fill the form</strong>
          <span>Enter your student details and credentials</span>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <div class="step-text">
          <strong>Await approval</strong>
          <span>Your department admin will review your request</span>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <div class="step-text">
          <strong>Start entering</strong>
          <span>Log in and begin cataloguing library books</span>
        </div>
      </div>
    </div>
  </div>

  <div class="panel-shelf">
    <svg viewBox="0 0 380 130" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="105" width="380" height="10" fill="#8B6E42" rx="2"/>
      <rect x="0" y="115" width="380" height="4" fill="rgba(0,0,0,0.3)" rx="1"/>
      <rect x="6"   y="60"  width="22" height="45" fill="#c0392b" rx="2 2 0 0"/>
      <rect x="6"   y="60"  width="22" height="6"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="30"  y="70"  width="17" height="35" fill="#2980b9" rx="2 2 0 0"/>
      <rect x="49"  y="55"  width="27" height="50" fill="#27ae60" rx="2 2 0 0"/>
      <rect x="49"  y="55"  width="27" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="78"  y="73"  width="15" height="32" fill="#8e44ad" rx="2 2 0 0"/>
      <rect x="95"  y="63"  width="20" height="42" fill="#e67e22" rx="2 2 0 0"/>
      <rect x="117" y="50"  width="30" height="55" fill="#1a5276" rx="2 2 0 0"/>
      <rect x="117" y="50"  width="30" height="8"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="149" y="66"  width="18" height="39" fill="#16a085" rx="2 2 0 0"/>
      <rect x="169" y="56"  width="24" height="49" fill="#7b241c" rx="2 2 0 0"/>
      <rect x="169" y="56"  width="24" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="195" y="68"  width="16" height="37" fill="#d4ac0d" rx="2 2 0 0"/>
      <rect x="213" y="52"  width="30" height="53" fill="#1b2a6b" rx="2 2 0 0"/>
      <rect x="213" y="52"  width="30" height="8"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="245" y="65"  width="20" height="40" fill="#cb4335" rx="2 2 0 0"/>
      <rect x="267" y="57"  width="22" height="48" fill="#6e7b3e" rx="2 2 0 0"/>
      <rect x="267" y="57"  width="22" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="291" y="70"  width="15" height="35" fill="#5b2c6f" rx="2 2 0 0"/>
      <rect x="308" y="58"  width="26" height="47" fill="#1a6b5a" rx="2 2 0 0"/>
      <rect x="308" y="58"  width="26" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="336" y="67"  width="18" height="38" fill="#c0392b" rx="2 2 0 0"/>
      <rect x="356" y="60"  width="18" height="45" fill="#2471a3" rx="2 2 0 0"/>
      <rect x="356" y="60"  width="18" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <rect x="0" y="119" width="380" height="11" fill="url(#sf2)"/>
      <defs><linearGradient id="sf2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="rgba(0,0,0,0.15)"/><stop offset="100%" stop-color="rgba(0,0,0,0)"/></linearGradient></defs>
    </svg>
  </div>
</div>

<!-- ═══════ RIGHT FORM PANEL ═══════ -->
<div class="auth-form-side">
  <div class="auth-form-box">

    <?php if ($done): ?>
    <!-- ── Success State ── -->
    <div class="done-card">
      <div class="done-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <h3>Registration Submitted!</h3>
      <p>Your account request has been received and is now <strong>pending review</strong> by your department administrator.</p>
      <div class="done-steps">
        <div class="done-step">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Registration form submitted
        </div>
        <div class="done-step">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Waiting for department approval
        </div>
        <div class="done-step">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="17 21 12 16 7 21"/></svg>
          Login access will be granted
        </div>
      </div>
      <a href="/library_entry_system/auth/login.php" class="btn-register" style="display:block;text-decoration:none;line-height:normal;padding:12px;">
        Go to Login Page
      </a>
    </div>

    <?php else: ?>
    <!-- ── Registration Form ── -->
    <div class="form-heading">
      <h2>Student Registration</h2>
      <p>Create your library account — approval required before login</p>
    </div>

    <?php if ($err): ?>
      <div class="alert-box alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <div class="form-grid">

        <div>
          <label class="form-label">Full Name <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input name="name" type="text" class="auth-input" required placeholder="Your full name" value="<?= e($_POST['name'] ?? '') ?>">
          </div>
        </div>

        <div>
          <label class="form-label">Student ID <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="13" y2="13"/></svg></span>
            <input name="student_id" type="text" class="auth-input" required placeholder="e.g. 2021-1-60-001" value="<?= e($_POST['student_id'] ?? '') ?>">
          </div>
        </div>

        <div class="full">
          <label class="form-label">Department <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
            <select name="department_id" class="auth-input" required style="padding-left:36px;appearance:none;cursor:pointer;">
              <option value="">— Select your department —</option>
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>>
                  <?= e($d['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="form-label">Email Address <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
            <input name="email" type="email" class="auth-input" required placeholder="your@email.com" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div>
          <label class="form-label">Mobile Number <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
            <input name="mobile" type="text" class="auth-input" required placeholder="01XXXXXXXXX" value="<?= e($_POST['mobile'] ?? '') ?>">
          </div>
        </div>

        <div>
          <label class="form-label">Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <input id="pw1" name="password" type="password" class="auth-input" required placeholder="Min. 8 characters">
            <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div>
          <label class="form-label">Confirm Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <input id="pw2" name="confirm_password" type="password" class="auth-input" required placeholder="Repeat password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="full" style="margin-top:4px;">
          <button type="submit" class="btn-register">Submit Registration</button>
        </div>

      </div>
    </form>

    <div class="form-footer">
      Already have an account?
      <a href="/library_entry_system/auth/login.php">Sign in here</a>
    </div>
    <p class="copyright">&copy; <?= date('Y') ?> Jahangirnagar University Central Library</p>

    <?php endif; ?>
  </div>
</div>

<script>
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  var isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>
</body>
</html>
