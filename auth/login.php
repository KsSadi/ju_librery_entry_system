<?php
require_once '../includes/functions.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        if ($user['status'] === 'pending') {
            $err = 'Your account is <strong>pending approval</strong>. Please wait for an administrator to review your registration.';
        } elseif ($user['status'] === 'rejected') {
            $err = 'Your registration was <strong>not approved</strong>. Please contact the library office.';
        } elseif ($user['status'] === 'inactive') {
            $err = 'Your account has been <strong>deactivated</strong>. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            redirect('/library_entry_system/dashboard.php');
        }
    } else {
        $err = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign In — JU Central Library</title>
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

    /* ═══════════════════════════════════════
       LEFT DECORATIVE PANEL
    ═══════════════════════════════════════ */
    .auth-panel {
      width: 44%;
      min-height: 100vh;
      background: linear-gradient(160deg, #071a1f 0%, #0d3d3d 35%, #0f766e 70%, #0a4d4d 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      padding: 48px 40px 0;
      position: relative;
      overflow: hidden;
    }

    /* Background decorative circles */
    .auth-panel::before {
      content: '';
      position: absolute;
      width: 480px; height: 480px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(6,182,212,.12) 0%, transparent 70%);
      top: -80px; right: -120px;
      pointer-events: none;
    }
    .auth-panel::after {
      content: '';
      position: absolute;
      width: 340px; height: 340px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,.05) 0%, transparent 70%);
      bottom: 120px; left: -80px;
      pointer-events: none;
    }

    /* Dot grid pattern */
    .panel-dots {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
      background-size: 28px 28px;
      pointer-events: none;
    }

    .panel-inner {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      width: 100%;
    }

    /* Full logo */
    .panel-logo {
      width: 110px;
      height: 110px;
      object-fit: contain;
      margin-bottom: 20px;
      background: #ffffff;
      border-radius: 50%;
      padding: 10px;
      box-shadow: 0 6px 28px rgba(0,0,0,.22), 0 0 0 3px rgba(6,182,212,.35), 0 0 0 6px rgba(6,182,212,.1);
    }
    .logo-fallback {
      position: absolute;
      inset: 9px;
      border-radius: 50%;
      background: white;
      z-index: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
    }

    .panel-univ {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 22px;
      font-weight: 800;
      color: white;
      line-height: 1.35;
      letter-spacing: -.2px;
    }
    .panel-library {
      font-size: 13px;
      font-weight: 600;
      color: #06b6d4;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      margin-top: 8px;
    }

    .panel-divider {
      width: 50px; height: 2px;
      background: linear-gradient(90deg, transparent, #06b6d4, transparent);
      margin: 20px auto;
    }

    .panel-quote {
      max-width: 280px;
      font-size: 13.5px;
      color: rgba(255,255,255,.65);
      font-style: italic;
      line-height: 1.7;
    }
    .panel-quote::before { content: '\201C'; font-size: 22px; color: #06b6d4; line-height: 0; vertical-align: -6px; margin-right: 3px; }
    .panel-quote::after  { content: '\201D'; font-size: 22px; color: #06b6d4; line-height: 0; vertical-align: -6px; margin-left: 3px; }

    /* Floating book icons */
    .float-books {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 0;
    }
    .fb {
      position: absolute;
      opacity: .12;
      animation: floatUp 6s ease-in-out infinite;
    }
    .fb:nth-child(1) { top: 15%;  left: 8%;  animation-delay: 0s;   animation-duration: 7s; }
    .fb:nth-child(2) { top: 40%;  right: 7%; animation-delay: 2s;   animation-duration: 8s; }
    .fb:nth-child(3) { top: 65%;  left: 12%; animation-delay: 1s;   animation-duration: 6s; }
    .fb:nth-child(4) { top: 25%;  right: 14%;animation-delay: 3.5s; animation-duration: 9s; }
    @keyframes floatUp {
      0%,100% { transform: translateY(0) rotate(-5deg); }
      50%      { transform: translateY(-16px) rotate(5deg); }
    }

    /* Bookshelf illustration */
    .panel-shelf {
      width: 100%;
      position: relative;
      z-index: 1;
      margin-top: 30px;
    }
    .panel-shelf svg { width: 100%; }

    /* ═══════════════════════════════════════
       RIGHT FORM PANEL
    ═══════════════════════════════════════ */
    .auth-form-side {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
    }

    .auth-form-box {
      width: 100%;
      max-width: 410px;
      background: white;
      border-radius: 20px;
      padding: 42px 40px 36px;
      box-shadow: 0 8px 48px rgba(0,0,0,.1);
    }

    .form-heading h2 {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 26px;
      font-weight: 800;
      color: #0d3d3d;
      margin-bottom: 4px;
    }
    .form-heading p {
      color: #64748b;
      font-size: 13.5px;
      margin-bottom: 28px;
    }

    .form-label {
      display: block;
      font-size: 12.5px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
      letter-spacing: .2px;
    }

    .input-wrap {
      position: relative;
      margin-bottom: 18px;
    }
    .input-wrap .i-icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
    }
    .input-wrap .i-icon svg { width: 16px; height: 16px; color: #94a3b8; }
    .auth-input {
      width: 100%;
      padding: 11px 12px 11px 40px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      color: #1e293b;
      font-family: inherit;
      transition: border-color .2s, box-shadow .2s;
      background: #fafafa;
    }
    .auth-input:focus {
      outline: none;
      border-color: #0f766e;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(15,118,110,.1);
    }
    .pw-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      padding: 2px;
      color: #94a3b8;
      line-height: 0;
    }
    .pw-toggle:hover { color: #475569; }
    .pw-toggle svg { width: 16px; height: 16px; }

    .btn-signin {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #0f766e, #0d3d3d);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      letter-spacing: .3px;
      transition: all .2s;
      margin-top: 4px;
      box-shadow: 0 4px 16px rgba(13,61,61,.35);
    }
    .btn-signin:hover {
      background: linear-gradient(135deg, #14b8a6, #0f766e);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(13,61,61,.4);
    }
    .btn-signin:active { transform: translateY(0); }

    .alert-box {
      padding: 12px 15px;
      border-radius: 10px;
      font-size: 13.5px;
      margin-bottom: 18px;
      border-left: 4px solid;
      line-height: 1.55;
    }
    .alert-error   { background: #fef2f2; color: #dc2626; border-color: #ef4444; }
    .alert-pending { background: #fffbeb; color: #92400e; border-color: #f59e0b; }

    .form-footer {
      text-align: center;
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px solid #f1f5f9;
      font-size: 13px;
      color: #64748b;
    }
    .form-footer a {
      color: #0f766e;
      font-weight: 700;
      text-decoration: none;
    }
    .form-footer a:hover { text-decoration: underline; }

    .copyright {
      margin-top: 14px;
      font-size: 11.5px;
      color: #cbd5e1;
      text-align: center;
    }

    /* ═══════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════ */
    @media (max-width: 860px) {
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
      .panel-divider, .panel-quote, .panel-icon-bar { display: none; }
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
    @media (max-width: 480px) {
      .auth-panel { padding: 36px 18px 64px; }
      .auth-form-side { padding: 0 12px 40px; margin-top: -22px; }
      .auth-form-box { padding: 26px 18px 22px; border-radius: 18px; }
      .panel-logo { width: 70px; height: 70px; padding: 8px; }
      .panel-univ { font-size: 16px; }
      .form-heading h2 { font-size: 22px; }
      .auth-input { padding: 12px 12px 12px 40px; font-size: 15px; }
      .btn-signin { padding: 14px; font-size: 15px; }
    }
  </style>
</head>
<body>

<!-- ═══════ LEFT PANEL ═══════ -->
<div class="auth-panel">
  <div class="panel-dots"></div>

  <!-- Floating books background decoration -->
  <div class="float-books">
    <div class="fb">
      <svg width="55" height="70" viewBox="0 0 55 70" fill="none"><rect x="2" y="2" width="51" height="66" rx="4" fill="white"/><rect x="2" y="2" width="10" height="66" rx="4" fill="#06b6d4"/><line x1="18" y1="20" x2="45" y2="20" stroke="#ccc" stroke-width="2"/><line x1="18" y1="28" x2="45" y2="28" stroke="#ccc" stroke-width="2"/><line x1="18" y1="36" x2="38" y2="36" stroke="#ccc" stroke-width="2"/></svg>
    </div>
    <div class="fb">
      <svg width="45" height="60" viewBox="0 0 45 60" fill="none"><rect x="2" y="2" width="41" height="56" rx="4" fill="white"/><rect x="2" y="2" width="8" height="56" rx="4" fill="#3b82f6"/><line x1="16" y1="16" x2="36" y2="16" stroke="#ccc" stroke-width="2"/><line x1="16" y1="24" x2="36" y2="24" stroke="#ccc" stroke-width="2"/></svg>
    </div>
    <div class="fb">
      <svg width="50" height="65" viewBox="0 0 50 65" fill="none"><rect x="2" y="2" width="46" height="61" rx="4" fill="white"/><rect x="2" y="2" width="9" height="61" rx="4" fill="#22c55e"/><line x1="17" y1="18" x2="42" y2="18" stroke="#ccc" stroke-width="2"/><line x1="17" y1="26" x2="42" y2="26" stroke="#ccc" stroke-width="2"/><line x1="17" y1="34" x2="35" y2="34" stroke="#ccc" stroke-width="2"/></svg>
    </div>
    <div class="fb">
      <svg width="42" height="58" viewBox="0 0 42 58" fill="none"><rect x="2" y="2" width="38" height="54" rx="4" fill="white"/><rect x="2" y="2" width="8" height="54" rx="4" fill="#a855f7"/><line x1="15" y1="16" x2="34" y2="16" stroke="#ccc" stroke-width="2"/><line x1="15" y1="24" x2="34" y2="24" stroke="#ccc" stroke-width="2"/></svg>
    </div>
  </div>

  <div class="panel-inner">
    <img src="https://upload.wikimedia.org/wikipedia/en/thumb/a/a9/Jahangirnagar_University_Logo.svg/1280px-Jahangirnagar_University_Logo.svg.png" alt="JU Logo" class="panel-logo">

    <div class="panel-univ">Jahangirnagar University</div>
    <div class="panel-library">Central Library</div>

    <div class="panel-divider"></div>

    <p class="panel-quote">
      Knowledge is the light that guides humanity through the darkness of ignorance.
    </p>

    <div style="margin-top:24px;display:flex;gap:20px;align-items:center;opacity:.55;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
  </div>

  <!-- Bookshelf SVG at the bottom -->
  <div class="panel-shelf">
    <svg viewBox="0 0 420 150" xmlns="http://www.w3.org/2000/svg">
      <!-- Shelf board -->
      <rect x="0" y="120" width="420" height="10" fill="#8B6E42" rx="2"/>
      <rect x="0" y="130" width="420" height="4" fill="rgba(0,0,0,0.3)" rx="1"/>

      <!-- Books -->
      <rect x="6"   y="68"  width="24" height="52" fill="#c0392b" rx="2 2 0 0"/>
      <rect x="6"   y="68"  width="24" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="12" y1="90" x2="24" y2="90" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>
      <line x1="12" y1="96" x2="24" y2="96" stroke="rgba(255,255,255,.3)" stroke-width="1"/>

      <rect x="32"  y="80"  width="18" height="40" fill="#2980b9" rx="2 2 0 0"/>
      <line x1="37" y1="98" x2="44" y2="98" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="52"  y="60"  width="30" height="60" fill="#27ae60" rx="2 2 0 0"/>
      <rect x="52"  y="60"  width="30" height="8"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="58" y1="85" x2="76" y2="85" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>
      <line x1="58" y1="91" x2="76" y2="91" stroke="rgba(255,255,255,.3)" stroke-width="1"/>

      <rect x="84"  y="82"  width="16" height="38" fill="#8e44ad" rx="2 2 0 0"/>

      <rect x="102" y="72"  width="22" height="48" fill="#e67e22" rx="2 2 0 0"/>
      <line x1="108" y1="93" x2="118" y2="93" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="126" y="55"  width="32" height="65" fill="#1a5276" rx="2 2 0 0"/>
      <rect x="126" y="55"  width="32" height="9"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="132" y1="80" x2="152" y2="80" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>
      <line x1="132" y1="86" x2="152" y2="86" stroke="rgba(255,255,255,.3)" stroke-width="1"/>
      <line x1="132" y1="92" x2="144" y2="92" stroke="rgba(255,255,255,.25)" stroke-width="1"/>

      <rect x="160" y="75"  width="20" height="45" fill="#16a085" rx="2 2 0 0"/>
      <line x1="165" y1="94" x2="174" y2="94" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="182" y="64"  width="26" height="56" fill="#7b241c" rx="2 2 0 0"/>
      <rect x="182" y="64"  width="26" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="188" y1="87" x2="202" y2="87" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="210" y="78"  width="18" height="42" fill="#d4ac0d" rx="2 2 0 0"/>
      <line x1="215" y1="96" x2="222" y2="96" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="230" y="58"  width="34" height="62" fill="#1b2a6b" rx="2 2 0 0"/>
      <rect x="230" y="58"  width="34" height="9"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="236" y1="82" x2="258" y2="82" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>
      <line x1="236" y1="88" x2="258" y2="88" stroke="rgba(255,255,255,.3)" stroke-width="1"/>

      <rect x="266" y="74"  width="22" height="46" fill="#cb4335" rx="2 2 0 0"/>
      <line x1="271" y1="93" x2="282" y2="93" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="290" y="65"  width="24" height="55" fill="#6e7b3e" rx="2 2 0 0"/>
      <rect x="290" y="65"  width="24" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="296" y1="88" x2="308" y2="88" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="316" y="79"  width="17" height="41" fill="#5b2c6f" rx="2 2 0 0"/>

      <rect x="335" y="62"  width="28" height="58" fill="#1a6b5a" rx="2 2 0 0"/>
      <rect x="335" y="62"  width="28" height="8"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="341" y1="86" x2="357" y2="86" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="365" y="76"  width="20" height="44" fill="#c0392b" rx="2 2 0 0"/>
      <line x1="370" y1="95" x2="379" y2="95" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <rect x="387" y="69"  width="25" height="51" fill="#2471a3" rx="2 2 0 0"/>
      <rect x="387" y="69"  width="25" height="7"  fill="rgba(255,255,255,.2)" rx="2 2 0 0"/>
      <line x1="392" y1="92" x2="406" y2="92" stroke="rgba(255,255,255,.4)" stroke-width="1.5"/>

      <!-- Floor shadow -->
      <rect x="0" y="134" width="420" height="16" fill="url(#shelfFade)"/>
      <defs>
        <linearGradient id="shelfFade" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgba(0,0,0,0.15)"/>
          <stop offset="100%" stop-color="rgba(0,0,0,0)"/>
        </linearGradient>
      </defs>
    </svg>
  </div>
</div>

<!-- ═══════ RIGHT FORM PANEL ═══════ -->
<div class="auth-form-side">
  <div class="auth-form-box">

    <div class="form-heading">
      <h2>Welcome Back</h2>
      <p>Sign in to your library account to continue</p>
    </div>

    <?php if ($err): ?>
      <?php $alertClass = (str_contains($err,'pending') || str_contains($err,'deactivated')) ? 'alert-pending' : 'alert-error'; ?>
      <div class="alert-box <?= $alertClass ?>"><?= $err ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>

      <label class="form-label" for="email">Email Address</label>
      <div class="input-wrap">
        <span class="i-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
        </span>
        <input id="email" name="email" type="email" class="auth-input" required
               placeholder="your@email.com"
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <label class="form-label" for="password">Password</label>
      <div class="input-wrap">
        <span class="i-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </span>
        <input id="password" name="password" type="password" class="auth-input" required placeholder="••••••••">
        <button type="button" class="pw-toggle" onclick="togglePw('password',this)" title="Show/hide password">
          <svg id="eye-password" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>

      <button type="submit" class="btn-signin">Sign In</button>
    </form>

    <div class="form-footer">
      New student?
      <a href="/library_entry_system/auth/register.php">Create an account</a>
    </div>

    <p class="copyright">&copy; <?= date('Y') ?> Jahangirnagar University Central Library. All rights reserved.</p>
  </div>
</div>

<script>
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  var isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<svg id="eye-'+id+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>
</body>
</html>
