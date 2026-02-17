<?php
require_once __DIR__ . '/db.php';
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

// Helper function for database operations with retry
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

// Get merchant categories mapping
function getMerchantCategories() {
    return [
        '🚗' => ['name' => 'Transportation', 'category' => 'transport'],
        '📦' => ['name' => 'Delivery', 'category' => 'delivery'],
        '🛒' => ['name' => 'Shopping', 'category' => 'shopping'],
        '🍔' => ['name' => 'Food & Dining', 'category' => 'food'],
        '🍦' => ['name' => 'Desserts', 'category' => 'food'],
        '🎵' => ['name' => 'Music', 'category' => 'entertainment'],
        '🎬' => ['name' => 'Movies', 'category' => 'entertainment'],
        '📺' => ['name' => 'Streaming', 'category' => 'entertainment']
    ];
}

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'card_details':
                $card_id = $_GET['card_id'] ?? '';
                if (empty($card_id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID required']);
                    break;
                }
                
                // Get card details with merchant locks
                $stmt = execute_with_retry($pdo, 
                    'SELECT c.*, 
                     GROUP_CONCAT(ml.merchant_name) as locked_merchants,
                     GROUP_CONCAT(ml.merchant_category) as locked_categories
                     FROM cards c 
                     LEFT JOIN merchant_locks ml ON c.id = ml.card_id AND ml.is_locked = 1
                     WHERE c.id = :card_id AND c.user_id = :user_id 
                     GROUP BY c.id',
                    ['card_id' => $card_id, 'user_id' => $user_id]
                );
                $card = $stmt->fetch();
                
                if (!$card) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Card not found']);
                    break;
                }
                
                // Get recent transactions
                $stmt = execute_with_retry($pdo, 
                    'SELECT * FROM transactions WHERE card_id = :card_id ORDER BY created_at DESC LIMIT 5',
                    ['card_id' => $card_id]
                );
                $transactions = $stmt->fetchAll();
                
                header('Content-Type: application/json');
                echo json_encode([
                    'card' => $card,
                    'transactions' => $transactions,
                    'merchant_categories' => getMerchantCategories()
                ]);
                break;
                
            case 'merchant_locks':
                $card_id = $_GET['card_id'] ?? '';
                if (empty($card_id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID required']);
                    break;
                }
                
                $stmt = execute_with_retry($pdo, 
                    'SELECT * FROM merchant_locks WHERE card_id = :card_id ORDER BY created_at DESC',
                    ['card_id' => $card_id]
                );
                $locks = $stmt->fetchAll();
                
                header('Content-Type: application/json');
                echo json_encode(['locks' => $locks]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'update_card_name':
                $card_id = $input['card_id'] ?? '';
                $new_name = trim($input['card_name'] ?? '');
                
                if (empty($card_id) || empty($new_name)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID and name required']);
                    break;
                }
                
                // Verify card belongs to user
                $stmt = execute_with_retry($pdo, 'SELECT id FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Card not found or access denied']);
                    break;
                }
                
                // Update card name
                $stmt = execute_with_retry($pdo, 'UPDATE cards SET card_name = :name WHERE id = :card_id AND user_id = :user_id', 
                    ['name' => $new_name, 'card_id' => $card_id, 'user_id' => $user_id]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                break;
                
            case 'update_card_number':
                $card_id = $input['card_id'] ?? '';
                $new_number = trim($input['card_number'] ?? '');
                
                if (empty($card_id) || empty($new_number)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID and number required']);
                    break;
                }
                
                // Verify card belongs to user
                $stmt = execute_with_retry($pdo, 'SELECT id FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Card not found or access denied']);
                    break;
                }
                
                // Update card number
                $stmt = execute_with_retry($pdo, 'UPDATE cards SET card_number = :number WHERE id = :card_id AND user_id = :user_id', 
                    ['number' => $new_number, 'card_id' => $card_id, 'user_id' => $user_id]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                break;
                
            case 'update_card_color':
                $card_id = $input['card_id'] ?? '';
                $new_color = trim($input['card_color'] ?? '');
                
                // Log for debugging
                error_log("Update card color - Card ID: $card_id, Color: $new_color, User ID: $user_id");
                
                if (empty($card_id) || empty($new_color)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID and color required']);
                    break;
                }
                
                // Verify card belongs to user
                $stmt = execute_with_retry($pdo, 'SELECT id, card_color FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
                $card = $stmt->fetch();
                
                if (!$card) {
                    http_response_code(403);
                    error_log("Card not found - Card ID: $card_id, User ID: $user_id");
                    echo json_encode(['error' => 'Card not found or access denied']);
                    break;
                }
                
                error_log("Old color: " . $card['card_color'] . ", New color: $new_color");
                
                // Update card color
                $stmt = execute_with_retry($pdo, 'UPDATE cards SET card_color = :color WHERE id = :card_id AND user_id = :user_id', 
                    ['color' => $new_color, 'card_id' => $card_id, 'user_id' => $user_id]);
                
                $rows_affected = $stmt->rowCount();
                error_log("Rows affected by UPDATE: $rows_affected");
                
                if ($rows_affected > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'color' => $new_color, 'rows_affected' => $rows_affected]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'No changes made', 'color' => $new_color]);
                }
                break;
                
            case 'toggle_merchant_lock':
                $card_id = $input['card_id'] ?? '';
                $merchant_category = $input['merchant_category'] ?? '';
                $merchant_name = $input['merchant_name'] ?? '';
                
                if (empty($card_id) || empty($merchant_category)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID and merchant category required']);
                    break;
                }
                
                // Verify card belongs to user
                $stmt = execute_with_retry($pdo, 'SELECT id FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Card not found or access denied']);
                    break;
                }
                
                // Check if lock already exists
                $stmt = execute_with_retry($pdo, 'SELECT id, is_locked FROM merchant_locks WHERE card_id = :card_id AND merchant_category = :category', 
                    ['card_id' => $card_id, 'category' => $merchant_category]);
                $existing_lock = $stmt->fetch();
                
                if ($existing_lock) {
                    // Toggle existing lock
                    $new_status = $existing_lock['is_locked'] ? 0 : 1;
                    $stmt = execute_with_retry($pdo, 'UPDATE merchant_locks SET is_locked = :status WHERE id = :lock_id', 
                        ['status' => $new_status, 'lock_id' => $existing_lock['id']]);
                } else {
                    // Create new lock
                    $stmt = execute_with_retry($pdo, 
                        'INSERT INTO merchant_locks (card_id, merchant_name, merchant_category, created_at) VALUES (:card_id, :name, :category, NOW())',
                        ['card_id' => $card_id, 'name' => $merchant_name, 'category' => $merchant_category]
                    );
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                break;
                
            case 'add_transaction':
                $card_id = $input['card_id'] ?? '';
                $amount = $input['amount'] ?? 0;
                $merchant_name = $input['merchant_name'] ?? '';
                $transaction_type = $input['transaction_type'] ?? 'debit';
                $description = $input['description'] ?? '';
                
                if (empty($card_id) || empty($amount) || empty($merchant_name)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Card ID, amount, and merchant name required']);
                    break;
                }
                
                // Verify card belongs to user
                $stmt = execute_with_retry($pdo, 'SELECT id FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Card not found or access denied']);
                    break;
                }
                
                // Insert transaction
                $stmt = execute_with_retry($pdo, 
                    'INSERT INTO transactions (card_id, amount, merchant_name, transaction_type, description, created_at) VALUES (:card_id, :amount, :merchant, :type, :desc, NOW())',
                    ['card_id' => $card_id, 'amount' => $amount, 'merchant' => $merchant_name, 'type' => $transaction_type, 'desc' => $description]
                );
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        $card_id = $input['card_id'] ?? '';
        
        if (empty($card_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Card ID required']);
            break;
        }
        
        // Verify card belongs to user and delete
        $stmt = execute_with_retry($pdo, 'DELETE FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Card not found or access denied']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
