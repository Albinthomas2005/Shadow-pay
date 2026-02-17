<?php
require_once __DIR__ . '/../api/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login/login.html');
    exit;
}

// Get plan from query
$plan = isset($_GET['plan']) ? $_GET['plan'] : '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $plan = $_POST['plan'];
  // Simulate payment success
  // Update user's plan in DB
  $stmt = $pdo->prepare('UPDATE users SET plan = :plan WHERE id = :id');
  $success = $stmt->execute(['plan' => $plan, 'id' => $_SESSION['user_id']]);
  if ($success && $stmt->rowCount() > 0) {
    $_SESSION['plan'] = $plan;
    header('Location: ../dashboard/dashboard.php');
    exit;
  } else {
    // Payment recorded but DB update failed
    echo '<script>alert("Payment recorded but there was an issue updating your account. Please contact support."); window.location.href = "../dashboard/pricing.html";</script>';
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShadowPay | Checkout</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background: #1a1a1a url('p4.png') center/cover no-repeat fixed;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    .container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 30px;
      width: 100%;
      max-width: 450px;
    }
    .card-display {
      width: 100%;
      max-width: 430px;
      aspect-ratio: 1.586; /* Standard card ratio */
      background: linear-gradient(135deg, #333, #111);
      border-radius: 20px;
      color: white;
      padding: 25px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      transition: transform 0.5s, box-shadow 0.5s;
    }
    .card-display.flip {
      transform: rotateY(180deg);
    }
    .card-front, .card-back {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      padding: 25px;
      backface-visibility: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .card-back {
      transform: rotateY(180deg);
    }
    .card-back .black-strip {
      height: 50px;
      background: #000;
      margin: 10px -25px;
    }
    .card-back .cvv-box {
      background: #fff;
      color: #000;
      height: 40px;
      width: 85%;
      text-align: right;
      padding: 10px;
      font-family: 'Source Code Pro', monospace;
    }
    .card-chip { width: 50px; height: 35px; background: linear-gradient(135deg, #d9d9d9, #b3b3b3); border-radius: 5px; }
    .card-top { display: flex; justify-content: space-between; align-items: center; }
    .card-number { letter-spacing: 3px; font-size: clamp(16px, 5vw, 22px); font-family: 'Source Code Pro', monospace; }
    .card-bottom { display: flex; justify-content: space-between; align-items: flex-end; }
    .cardholder, .card-expiry { font-size: clamp(14px, 4vw, 18px); text-transform: uppercase; }
    .brand { font-size: 20px; font-weight: 700; opacity: 0.8; color: #c9a876; }

    .payment-form {
      background: rgba(34, 34, 34, 0.9);
      backdrop-filter: blur(10px);
      padding: 30px;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      width: 100%;
    }
    h2 {
      text-align: center;
      margin-bottom: 25px;
      font-size: 24px;
      color: #c9a876;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    .plan-label {
      text-align: center;
      margin-bottom: 20px;
      font-size: 17px;
      font-weight: 600;
    }
    .plan-label span { color: #c9a876; text-transform: uppercase; }
    label { display: block; margin-top: 18px; font-size: 14px; color: #ccc; }
    input {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #555;
      margin-top: 6px;
      font-size: 16px;
      background: #333;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      transition: border-color 0.3s, box-shadow 0.3s;
    }
    input:focus {
      outline: none;
      border-color: #c9a876;
      box-shadow: 0 0 0 3px rgba(201, 168, 118, 0.3);
    }
    .expiry-cvv-row { display: flex; gap: 15px; }
    .expiry-cvv-row > div { flex: 1; }
    button {
      background: #c9a876;
      color: #111;
      font-weight: 700;
      font-size: 18px;
      padding: 15px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      margin-top: 24px;
      width: 100%;
      transition: 0.3s;
      text-transform: uppercase;
    }
    button:hover { filter: brightness(1.1); }
    .back-icon {
      position: absolute;
      top: 25px;
      left: 25px;
      cursor: pointer;
      z-index: 10;
    }
    .back-icon svg { width: 30px; height: 30px; stroke: white; }
  </style>
</head>
<body>
  <div class="back-icon" onclick="window.location.href='pricing.html'">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
  </div>

  <div class="container">
    <form class="payment-form" method="post">
      <h2>Checkout</h2>
      <div class="plan-label">Selected Plan: <span><?php echo htmlspecialchars($plan); ?></span></div>
      <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan); ?>">
      
      <label for="card_number">Card Number</label>
      <input type="text" id="card_number" name="card_number" maxlength="19" required placeholder="1234 5678 9012 3456">
      
      <label for="card_name">Cardholder Name</label>
      <input type="text" id="card_name" name="card_name" required placeholder="Full Name">

      <div class="expiry-cvv-row">
        <div>
          <label for="expiry">Expiry</label>
          <input type="text" id="expiry" name="expiry" maxlength="5" required placeholder="MM/YY">
        </div>
        <div>
          <label for="cvv">CVV</label>
          <input type="text" id="cvv" name="cvv" maxlength="4" required placeholder="123">
        </div>
      </div>
      
      <button type="submit">Pay Now</button>
    </form>
  </div>

  <script>
    const cardNumberInput = document.getElementById('card_number');
    const cardNameInput = document.getElementById('card_name');
    const expiryInput = document.getElementById('expiry');
    const cvvInput = document.getElementById('cvv');

    cardNumberInput.addEventListener('input', (e) => {
      let value = e.target.value.replace(/\D/g, '').substring(0, 16);
      value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
      e.target.value = value;
    });

    cardNameInput.addEventListener('input', (e) => {
      // No-op, but listener is here if needed in future
    });

    expiryInput.addEventListener('input', (e) => {
      let value = e.target.value.replace(/\D/g, '').substring(0, 4);
      if (value.length > 2) {
        value = value.slice(0, 2) + '/' + value.slice(2);
      }
      e.target.value = value;
    });
  </script>
</body>
</html>
