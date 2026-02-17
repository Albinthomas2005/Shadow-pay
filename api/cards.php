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

// Generate a random card number
function generateCardNumber() {
    // Generate a realistic-looking card number (16 digits)
    $number = '';
    for ($i = 0; $i < 16; $i++) {
        $number .= rand(0, 9);
    }
    // Format as XXXX XXXX XXXX XXXX
    return substr($number, 0, 4) . ' ' . substr($number, 4, 4) . ' ' . substr($number, 8, 4) . ' ' . substr($number, 12, 4);
}

// Generate a random CVV
function generateCVV() {
    // Generate a 3-digit CVV
    return str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

switch ($method) {
    case 'GET':
        // Get all cards for the user
        $stmt = execute_with_retry($pdo, 'SELECT * FROM cards WHERE user_id = :user_id ORDER BY created_at DESC', ['user_id' => $user_id]);
        $cards = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode(['cards' => $cards]);
        break;
        
    case 'POST':
        // Create a new card
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['card_name']) || !isset($input['card_color'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }
        
        $card_name = trim($input['card_name']);
        $card_color = $input['card_color'];
        
        if (empty($card_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Card name cannot be empty']);
            break;
        }
        
        // Generate unique card number and CVV
        $card_number = generateCardNumber();
        $cvv = generateCVV();
        
        // Insert new card
        $stmt = execute_with_retry($pdo, 
            'INSERT INTO cards (user_id, card_name, card_number, cvv, card_color, created_at) VALUES (:user_id, :card_name, :card_number, :cvv, :card_color, NOW())',
            [
                'user_id' => $user_id,
                'card_name' => $card_name,
                'card_number' => $card_number,
                'cvv' => $cvv,
                'card_color' => $card_color
            ]
        );
        
        $card_id = $pdo->lastInsertId();
        
        // Return the created card
        $stmt = execute_with_retry($pdo, 'SELECT * FROM cards WHERE id = :card_id', ['card_id' => $card_id]);
        $card = $stmt->fetch();
        
        header('Content-Type: application/json');
        echo json_encode(['card' => $card]);
        break;
        
    case 'PUT':
        // Update card (toggle active status)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['card_id']) || !isset($input['is_active'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }
        
        $card_id = $input['card_id'];
        $is_active = $input['is_active'] ? 1 : 0;
        
        // Verify card belongs to user
        $stmt = execute_with_retry($pdo, 'SELECT id FROM cards WHERE id = :card_id AND user_id = :user_id', ['card_id' => $card_id, 'user_id' => $user_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Card not found or access denied']);
            break;
        }
        
        // Update card status
        $stmt = execute_with_retry($pdo, 'UPDATE cards SET is_active = :is_active WHERE id = :card_id AND user_id = :user_id', 
            ['is_active' => $is_active, 'card_id' => $card_id, 'user_id' => $user_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        break;
        
    case 'DELETE':
        // Delete a card
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['card_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing card_id']);
            break;
        }
        
        $card_id = $input['card_id'];
        
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
