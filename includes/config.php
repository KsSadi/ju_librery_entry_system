<?php
$host = 'localhost';
$dbname = 'librery_db';
$username = 'librery_user';
$password = 'StrongPassword123!';
try {
    $pdo = new PDO("mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-detect base URL — works at any subfolder depth or at server root
if (!defined('BASE')) {
    $_appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $_docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    define('BASE', str_replace($_docRoot, '', $_appRoot));
    unset($_appRoot, $_docRoot);
}
?>
