<?php
require 'vendor/autoload.php'; // Make sure Stripe PHP SDK is installed
header('Content-Type: application/json');

// Set your Stripe secret key (test key)
\Stripe\Stripe::setApiKey('sk_test_51N...your_test_key_here');

$input = json_decode(file_get_contents('php://input'), true);
$plan = $input['plan'] ?? 'basic';

// Define price IDs from Stripe Dashboard (replace with your test price IDs)
$prices = [
    'basic' => 'price_1N...basic',
    'pro' => 'price_1N...pro',
];

if (!isset($prices[$plan])) {
    echo json_encode(['error' => 'Invalid plan']);
    exit;
}

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $prices[$plan],
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => 'http://localhost/ui/dashboard/pricing.html?success=true',
        'cancel_url' => 'http://localhost/ui/dashboard/pricing.html?canceled=true',
    ]);
    echo json_encode([
        'sessionId' => $session->id,
        'publicKey' => 'pk_test_51N...your_test_public_key_here', // Replace with your test public key
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
