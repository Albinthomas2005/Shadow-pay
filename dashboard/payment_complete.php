<?php
require_once __DIR__ . '/../api/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated', 'session_data' => $_SESSION]);
    exit();
}

// Debug: Log session info
error_log("Payment attempt - User ID: " . $_SESSION['user_id'] . ", Session: " . json_encode($_SESSION));

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['plan', 'transactionId', 'amount', 'paymentMethod'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Validate plan type
    $valid_plans = ['basic', 'pro'];
    if (!in_array($input['plan'], $valid_plans)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid plan type']);
        exit();
    }

    // Exact same approach as working payment.php
    $stmt = $pdo->prepare('UPDATE users SET plan = :plan WHERE id = :id');
    $success = $stmt->execute([
        'plan' => $input['plan'],
        'id' => $_SESSION['user_id']
    ]);
    
    if ($success && $stmt->rowCount() > 0) {
        $_SESSION['plan'] = $input['plan'];
    } else {
        // This is the exact same error handling as payment.php
        throw new Exception('Payment recorded but there was an issue updating your account. Please contact support.');
    }

    // Optional: Insert payment record into payments table (skip if table doesn't exist)
    try {
        $stmt_payment = $pdo->prepare('
            INSERT INTO payments (user_id, plan, transaction_id, amount, payment_method, status, created_at) 
            VALUES (:user_id, :plan, :transaction_id, :amount, :payment_method, :status, :created_at)
        ');
        $stmt_payment->execute([
            'user_id' => $_SESSION['user_id'],
            'plan' => $input['plan'],
            'transaction_id' => $input['transactionId'],
            'amount' => floatval($input['amount']),
            'payment_method' => $input['paymentMethod'],
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        // If payments table doesn't exist, just log it (don't fail the payment)
        error_log("Payments table doesn't exist, skipping payment record: " . $e->getMessage());
    }

    // Also log payment record to file for backup
    $payment_record = [
        'user_id' => $_SESSION['user_id'],
        'plan' => $input['plan'],
        'transaction_id' => $input['transactionId'],
        'amount' => floatval($input['amount']),
        'payment_method' => $input['paymentMethod'],
        'timestamp' => $input['timestamp'] ?? date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ];

    $log_entry = date('Y-m-d H:i:s') . " - Payment completed: " . json_encode($payment_record) . "\n";
    file_put_contents('payment_log.txt', $log_entry, FILE_APPEND | LOCK_EX);

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Payment processed successfully',
        'plan' => $input['plan'],
        'transaction_id' => $input['transactionId'],
        'user_id' => $_SESSION['user_id']
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {

    // Log error with more details
    $error_details = [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? 'not_set',
        'plan' => $input['plan'] ?? 'not_set',
        'transaction_id' => $input['transactionId'] ?? 'not_set',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    error_log("Payment completion error: " . json_encode($error_details));

    http_response_code(500);
    echo json_encode([
        'error' => 'Payment processing failed',
        'message' => $e->getMessage(),
        'debug' => $error_details
    ]);
}
?>
