<?php
require_once __DIR__ . '/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Please fill all required fields.';
    header('Location: login/login.html');
    exit;
}

// Use the same execute_with_retry helper available in signup_process. Declare it here if missing.
if (!function_exists('execute_with_retry')) {
    function execute_with_retry($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if ($e->getCode() === 'HY000' || strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
                require_once __DIR__ . '/db.php';
                $newPdo = create_pdo();
                $stmt = $newPdo->prepare($sql);
                $stmt->execute($params);
                $GLOBALS['pdo'] = $newPdo;
                return $stmt;
            }
            throw $e;
        }
    }
}

$stmt = execute_with_retry($pdo, 'SELECT id, username, password_hash FROM users WHERE username = :u OR email = :e', ['u' => $username, 'e' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['error'] = 'Invalid credentials.';
    header('Location: login/login.html');
    exit;
}

// Regenerate session id to prevent fixation
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

header('Location: dashboard/dashboard.php');
exit;
