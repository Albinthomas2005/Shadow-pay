<?php
require_once __DIR__ . '/bootstrap.php';

// Basic server-side validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if ($username === '' || $email === '' || $password === '') {
    $_SESSION['error'] = 'Please fill all required fields.';
    header('Location: ../signup/shadowpay_signup.html');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email address.';
    header('Location: ../signup/shadowpay_signup.html');
    exit;
}

if ($password !== $password_confirm) {
    $_SESSION['error'] = 'Passwords do not match.';
    header('Location: ../signup/shadowpay_signup.html');
    exit;
}

// Check if username or email exists
$stmt = execute_with_retry($pdo, 'SELECT id FROM users WHERE username = :u OR email = :e', ['u' => $username, 'e' => $email]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'Username or email already taken.';
    header('Location: ../signup/shadowpay_signup.html');
    exit;
}

// Insert user with password_hash
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = execute_with_retry($pdo, 'INSERT INTO users (username, email, password_hash, created_at) VALUES (:u, :e, :p, NOW())', ['u' => $username, 'e' => $email, 'p' => $hash]);

$_SESSION['success'] = 'Account created. You can now log in.';
header('Location: ../login/login.html');
exit;
