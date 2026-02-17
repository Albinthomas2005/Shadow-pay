<?php
require_once __DIR__ . '/bootstrap.php';

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
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['cardId', 'amount', 'transactionId', 'paymentMethod'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Validate amount
    $amount = floatval($input['amount']);
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid amount']);
        exit();
    }

    // Check if card belongs to user
    $stmt = $pdo->prepare('SELECT id, user_id, balance FROM cards WHERE id = :card_id AND user_id = :user_id');
    $stmt->execute([
        'card_id' => $input['cardId'],
        'user_id' => $_SESSION['user_id']
    ]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        http_response_code(404);
        echo json_encode(['error' => 'Card not found or access denied']);
        exit();
    }

    // Get current balance (default to 0 if not set)
    $current_balance = isset($card['balance']) ? floatval($card['balance']) : 0.0;
    $new_balance = $current_balance + $amount;

    // Update card balance
    $stmt = $pdo->prepare('UPDATE cards SET balance = :balance WHERE id = :card_id');
    $success = $stmt->execute([
        'balance' => $new_balance,
        'card_id' => $input['cardId']
    ]);

    if (!$success) {
        throw new Exception('Failed to update card balance');
    }

    // Record the transaction
    $stmt = $pdo->prepare('
        INSERT INTO transactions (card_id, amount, merchant_name, transaction_type, description, created_at) 
        VALUES (:card_id, :amount, :merchant_name, :transaction_type, :description, :created_at)
    ');
    $stmt->execute([
        'card_id' => $input['cardId'],
        'amount' => $amount,
        'merchant_name' => 'Fund Addition',
        'transaction_type' => 'credit',
        'description' => 'Funds added via ' . $input['paymentMethod'] . ' - Transaction ID: ' . $input['transactionId'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Log the fund addition
    $log_entry = date('Y-m-d H:i:s') . " - Funds added: " . json_encode([
        'user_id' => $_SESSION['user_id'],
        'card_id' => $input['cardId'],
        'amount' => $amount,
        'old_balance' => $current_balance,
        'new_balance' => $new_balance,
        'transaction_id' => $input['transactionId'],
        'payment_method' => $input['paymentMethod']
    ]) . "\n";
    file_put_contents('add_funds_log.txt', $log_entry, FILE_APPEND | LOCK_EX);

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Funds added successfully',
        'amount' => $amount,
        'old_balance' => $current_balance,
        'new_balance' => $new_balance,
        'transaction_id' => $input['transactionId']
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    error_log("Add funds error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to add funds',
        'message' => $e->getMessage()
    ]);
}
?>
