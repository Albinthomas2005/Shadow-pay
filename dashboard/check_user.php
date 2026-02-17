<?php
require_once __DIR__ . '/../api/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Get current user info
    $stmt = $pdo->prepare('SELECT id, username, email, plan FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found in database', 'user_id' => $_SESSION['user_id']]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'session_user_id' => $_SESSION['user_id'],
        'message' => 'User found successfully'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
