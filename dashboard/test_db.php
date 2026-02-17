<?php
require_once __DIR__ . '/../api/bootstrap.php';

header('Content-Type: application/json');

try {
    // Test database connection
    $result = [
        'database_connection' => 'OK',
        'user_session' => $_SESSION['user_id'] ?? 'not_set',
        'tables' => []
    ];

    // Check if users table exists and has plan column
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['users_table_columns'] = $columns;
    
    // Check if plan column exists
    $plan_column_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'plan') {
            $plan_column_exists = true;
            break;
        }
    }
    $result['plan_column_exists'] = $plan_column_exists;

    // Check if payments table exists
    try {
        $stmt = $pdo->query("DESCRIBE payments");
        $result['payments_table_exists'] = true;
        $result['payments_table_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $result['payments_table_exists'] = false;
        $result['payments_table_error'] = $e->getMessage();
    }

    // Test user update (dry run)
    if ($_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id');
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['current_user'] = $user;
        } catch (PDOException $e) {
            $result['user_lookup_error'] = $e->getMessage();
        }
    }

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
