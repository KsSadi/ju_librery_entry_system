<?php
// Temporary hash generator — DELETE this file after use!
$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Hash Generator</title>
  <style>
    body { font-family: sans-serif; max-width: 520px; margin: 60px auto; padding: 0 20px; }
    input[type=password] { width: 100%; padding: 10px; font-size: 15px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
    button { margin-top: 12px; padding: 10px 24px; background: #1a5736; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
    .hash-box { margin-top: 20px; background: #f1f5f9; padding: 14px; border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 13px; }
    .warn { color: #b91c1c; font-size: 12px; margin-top: 20px; }
  </style>
</head>
<body>
  <h2>BCrypt Hash Generator</h2>
  <form method="post">
    <input type="password" name="password" placeholder="Type your password" autofocus required>
    <button type="submit">Generate Hash</button>
  </form>

  <?php if ($hash): ?>
  <div class="hash-box">
    <strong>Hash:</strong><br><br>
    <?= htmlspecialchars($hash) ?>
  </div>
  <p>Run this SQL for all 3 users (replace <code>?</code> with each user id):</p>
  <div class="hash-box">
    UPDATE users SET password = '<?= htmlspecialchars($hash) ?>' WHERE id IN (1, 2, 3);
  </div>
  <?php endif; ?>

  <p class="warn">&#9888; Delete this file (gen_hash.php) after you're done!</p>
</body>
</html>
