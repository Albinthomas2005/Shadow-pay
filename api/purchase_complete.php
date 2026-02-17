<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests for purchase completion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['transactionId', 'paymentMethod', 'amount', 'productName', 'merchantName'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Create purchase record
$purchase_data = [
    'transaction_id' => $input['transactionId'],
    'payment_method' => $input['paymentMethod'],
    'amount' => floatval($input['amount']),
    'product_name' => $input['productName'],
    'merchant_name' => $input['merchantName'],
    'status' => 'completed',
    'timestamp' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s')
];

// In a real application, you would save this to a database
// For now, we'll just log it and return success

// Log the purchase (you can replace this with database insertion)
$log_entry = date('Y-m-d H:i:s') . " - Purchase completed: " . json_encode($purchase_data) . "\n";
file_put_contents('purchase_log.txt', $log_entry, FILE_APPEND | LOCK_EX);

// Return success response
$response = [
    'success' => true,
    'message' => 'Purchase completed successfully',
    'transaction_id' => $purchase_data['transaction_id'],
    'status' => 'completed',
    'timestamp' => $purchase_data['timestamp']
];

http_response_code(200);
echo json_encode($response);
?>
