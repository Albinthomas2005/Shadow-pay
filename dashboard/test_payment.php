<?php
require_once __DIR__ . '/../api/bootstrap.php';

header('Content-Type: application/json');

// Test payment completion with mock data
$test_data = [
    'plan' => 'basic',
    'transactionId' => 'TEST_' . time(),
    'amount' => 200,
    'paymentMethod' => 'card',
    'timestamp' => date('Y-m-d H:i:s')
];

echo "<h2>Testing Payment Completion</h2>";
echo "<h3>Session Info:</h3>";
echo "<pre>" . json_encode($_SESSION, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

if (empty($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user session found!</p>";
    echo "<p>Please <a href='../login/login.html'>login first</a></p>";
    exit;
}

echo "<p style='color: green;'>✅ User session found: " . $_SESSION['user_id'] . "</p>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test user exists
try {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p style='color: green;'>✅ User found: " . json_encode($user) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ User not found in database</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ User lookup failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test plan column
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_plan_column = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'plan') {
            $has_plan_column = true;
            break;
        }
    }
    
    if ($has_plan_column) {
        echo "<p style='color: green;'>✅ Plan column exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Plan column missing - will be created</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Column check failed: " . $e->getMessage() . "</p>";
}

// Test actual payment completion
echo "<h3>Testing Payment Completion:</h3>";

// Simulate the payment completion
$input = $test_data;

try {
    $pdo->beginTransaction();
    
    // Try to update user plan
    try {
        $stmt = $pdo->prepare('UPDATE users SET plan = :plan WHERE id = :id');
        $stmt->execute([
            'plan' => $input['plan'],
            'id' => $_SESSION['user_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Plan update successful</p>";
        } else {
            echo "<p style='color: red;'>❌ Plan update failed - no rows affected</p>";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            echo "<p style='color: orange;'>⚠️ Plan column missing, creating it...</p>";
            $pdo->exec('ALTER TABLE users ADD COLUMN plan VARCHAR(20) DEFAULT "free"');
            
            // Retry
            $stmt = $pdo->prepare('UPDATE users SET plan = :plan WHERE id = :id');
            $stmt->execute([
                'plan' => $input['plan'],
                'id' => $_SESSION['user_id']
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Plan update successful after creating column</p>";
            } else {
                echo "<p style='color: red;'>❌ Plan update still failed</p>";
            }
        } else {
            throw $e;
        }
    }
    
    $pdo->commit();
    echo "<p style='color: green;'>✅ Payment completion test successful!</p>";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "<p style='color: red;'>❌ Payment completion test failed: " . $e->getMessage() . "</p>";
}
?>
