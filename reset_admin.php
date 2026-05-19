<?php
require_once __DIR__ . '/includes/config.php';

$adminEmail = 'admin@library.local';
$adminPassword = 'Admin@12345';
$hash = password_hash($adminPassword, PASSWORD_BCRYPT);

try {
    // Ensure roles exist
    $roles = ['Department User', 'Library Staff', 'Expert', 'Library Admin'];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name) VALUES (?)");
        $stmt->execute([$role]);
    }

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name='Library Admin' LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch();

    if (!$role) {
        die('Library Admin role not found. Import database.sql first.');
    }

    $roleId = $role['id'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$adminEmail]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET password_hash=?, role_id=?, status='active', must_change_password=0 WHERE email=?");
        $stmt->execute([$hash, $roleId, $adminEmail]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name,email,mobile,password_hash,role_id,department_id,must_change_password,status) VALUES (?,?,?,?,?,NULL,0,'active')");
        $stmt->execute(['Library Admin', $adminEmail, '0000000000', $hash, $roleId]);
    }

    echo "Admin reset successful.<br>";
    echo "Email: <b>$adminEmail</b><br>";
    echo "Password: <b>$adminPassword</b><br><br>";
    echo "IMPORTANT: Delete reset_admin.php after login.";
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?>
