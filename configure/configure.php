<?php
require_once __DIR__ . '/../api/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login/login.html');
    exit;
}

// Get card ID from URL parameter
$card_id = $_GET['card_id'] ?? '';

if (empty($card_id)) {
    header('Location: dashboard.php');
    exit;
}

// Get card details
$stmt = execute_with_retry($pdo, 
    'SELECT * FROM cards WHERE id = :card_id AND user_id = :user_id',
    ['card_id' => $card_id, 'user_id' => $_SESSION['user_id']]
);
$card = $stmt->fetch();

if (!$card) {
    header('Location: dashboard.php');
    exit;
}

// Sanitize card color input and provide a safe fallback
function sanitize_css_color($color) {
    $color = trim($color);
    if (empty($color)) return '#999999';

    // Allow hex colors (#fff or #ffffff)
    if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
        return $color;
    }

    // Allow rgb() and rgba()
    if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $color)) {
        return $color;
    }

    // Allow hsl() and hsla()
    if (preg_match('/^hsla?\(\s*\d{1,3}(?:deg|rad|grad|turn)?\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%?(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $color)) {
        return $color;
    }

    // If none matched, return a safe default
    return '#999999';
}

$card_color_css = sanitize_css_color($card['card_color'] ?? '');

// Get merchant locks for this card
$stmt = execute_with_retry($pdo, 
    'SELECT * FROM merchant_locks WHERE card_id = :card_id ORDER BY created_at DESC',
    ['card_id' => $card_id]
);
$merchant_locks = $stmt->fetchAll();

// Get recent transactions
$stmt = execute_with_retry($pdo, 
    'SELECT * FROM transactions WHERE card_id = :card_id ORDER BY created_at DESC LIMIT 5',
    ['card_id' => $card_id]
);
$transactions = $stmt->fetchAll();

// Get card balance (default to 0 if not set)
$balance = isset($card['balance']) ? floatval($card['balance']) : 0.0;

// Merchant categories mapping
// I've added an 'icon' key to make it easy to use either emojis or images.
$merchant_categories = [
    'transport' => ['name' => 'Transportation', 'icon' => 'merchanticons/uber.png'],
    'delivery'  => ['name' => 'Delivery', 'icon' => 'merchanticons/amazon.png'],
    'shopping'  => ['name' => 'Shopping', 'icon' => 'merchanticons/flipkart.png'],
    'food'      => ['name' => 'Food & Dining', 'icon' => 'merchanticons/mc.png'],
    'desserts'  => ['name' => 'Desserts', 'icon' => 'merchanticons/swiggy.png'],
    'music'     => ['name' => 'Music', 'icon' => 'merchanticons/spotify.png'],
    'movies'    => ['name' => 'Movies', 'icon' => 'merchanticons/ubereats.png'],
    'streaming' => ['name' => 'Streaming', 'icon' => 'merchanticons/netflix.png']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Card Management - <?php echo htmlspecialchars($card['card_name']); ?></title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,600&display=swap" rel="stylesheet">
  <style>
        *{margin:0;padding:0;box-sizing:border-box}

:root{
  --bg:#f5f7fb;
  --panel:#ffffff;
  --ink:#111827;
  --muted:#6b7280;
  --ring:#e5e7eb;
  --shadow:0 8px 20px rgba(17,24,39,.08);
  --radius:16px;
  --gap:20px;
}

html,body{min-height:100%}

body{
  font-family:'Montserrat',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;
  background:var(--bg);
  color:var(--ink);
  padding:16px;
  overflow-y:auto; /* Allow vertical scrolling */
}

.container{
  max-width:95%;
  margin:0 auto;
  display:flex;
  flex-direction:column;
  gap:16px;
  min-height: calc(100vh - 48px);
  width:100%;
}

.header{
  display:flex;
  align-items:center;
  gap:12px;
  flex-shrink:0;
}

h1{
  font-size:24px;
  font-weight:700;
  color:#1f2937;
}

.back-button{
  background:var(--panel);
  border:1px solid var(--ring);
  border-radius:999px;
  width:44px;
  height:44px;
  display:grid;
  place-items:center;
  box-shadow:var(--shadow);
  cursor:pointer;
  transition:transform .15s ease, box-shadow .15s ease;
}

.back-button:hover{
  transform:translateY(-1px);
  box-shadow:0 12px 24px rgba(17,24,39,.1);
}

.content{
  display:grid;
  grid-template-columns:30% 1fr 25%;
  gap:var(--gap);
  flex:1;
  min-height:0;
  align-items:start;
}

.card-section,.funds-section,.actions-section{
  display:flex;
  flex-direction:column;
  gap:20px;
  min-height:0;
  justify-content:flex-start;
}

.funds-section{
  gap:var(--gap);
}

.actions-section{
  gap:var(--gap);
  flex:1;
}

/* Card */
.card{
  background:var(--card-color,#999);
  border-radius:20px;
  padding:22px;
  color:#fff;
  position:relative;
  box-shadow:0 14px 30px rgba(0,0,0,.18);
  width:100%;
  aspect-ratio:16/9;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}

.card::before{
  content:"";
  position:absolute;
  inset:-40% -20% auto auto;
  width:80%;
  height:180%;
  background:radial-gradient(ellipse at top left,rgba(255,255,255,.22),transparent 60%);
  transform:rotate(18deg);
  pointer-events:none;
}

.card-name{
  font-size:20px;
  font-weight:600;
  opacity:.95;
}

.card-number{
  font-family:"SF Mono","Courier New",monospace;
  letter-spacing:2px;
  font-size:20px;
  opacity:.95;
}

.card-expiry{
  font-size:14px;
  opacity:.9;
}

/* Controls under card */
.control-item,.balance-item{
  background:var(--panel);
  border:1px solid var(--ring);
  border-radius:var(--radius);
  padding:14px 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  box-shadow:var(--shadow);
  transition:transform .12s ease, box-shadow .12s ease;
}

.balance-item {
  padding: 18px;
  background: #f9fafb;
  border: 1px solid #d1d5db;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
  min-width: 0;
  max-width: none;
  font-size: 1.05rem;
  box-shadow: 0 4px 12px rgba(17,24,39,.08);
  border-radius: 20px;
}

.control-item:hover,.balance-item:hover{
  transform:translateY(-1px);
  box-shadow:0 12px 24px rgba(17,24,39,.1);
}

.control-item span:first-child{
  font-weight:600;
  color:#111827;
}

.icon{
  font-size:18px;
  opacity:.8;
}

/* Toggle */
.toggle{
  width:56px;
  height:30px;
  background:#e5e7eb;
  border-radius:999px;
  position:relative;
  transition:background .2s ease;
  flex-shrink:0;
}

.toggle::after{
  content:"";
  position:absolute;
  top:3px;
  left:3px;
  width:24px;
  height:24px;
  border-radius:999px;
  background:#fff;
  box-shadow:0 2px 6px rgba(0,0,0,.12);
  transition:left .2s ease;
}

.toggle.active{
  background:#10b981;
}

.toggle.active::after{
  left:29px;
}

/* Middle column */
.funds-box,.merchant-box{
  background:var(--panel);
  border:1px solid var(--ring);
  border-radius:20px;
  box-shadow:var(--shadow);
  padding:18px;
}

.funds-box{
  display:flex;
  flex-direction:column;
  gap:14px;
}

.funds-box h2,.merchant-box h2{
  font-size:16px;
  font-weight:700;
  color:#111827;
}

.payment-methods{
  display:flex;
  gap:12px;
  align-items:center;
  flex-wrap:wrap;
}

.payment-logo{
  height:34px;
}

@media (min-width:2560px){
  .payment-logo{
    height:54px;
  }
}

@media (min-width:1920px) and (max-width:2559px){
  .payment-logo{
    height:46px;
  }
}

@media (min-width:1600px) and (max-width:1919px){
  .payment-logo{
    height:40px;
  }
}

.merchant-box{
  display:flex;
  flex-direction:column;
  gap:14px;
  max-height:600px;
  overflow-y:auto;
}

.verified{
  font-size:12px;
  color:var(--muted);
  font-weight:500;
}

.merchant-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:12px;
}

.merchant-icon{
  background:#f3f4f6;
  border:1px solid #e5e7eb;
  border-radius:14px;
  min-height:84px;
  display:grid;
  place-items:center;
  font-size:28px;
  cursor:pointer;
  transition:transform .12s ease, background .12s ease, border-color .12s ease;
}

.merchant-icon img{
  width:40px;
  height:40px;
  object-fit:contain;
}

.merchant-icon:hover{
  transform:translateY(-1px);
  background:#eef2ff;
  border-color:#c7d2fe;
}

.merchant-icon.locked{
  background:#fff1f2;
  border-color:#fecaca;
}

/* Right column actions */
.actions-section{
  min-height:0;
  display:flex;
  flex-direction:column;
  gap:16px;
}

.action-button,.action-spacer{
  background:var(--panel);
  border:1px solid var(--ring);
  border-radius:18px;
  padding:16px;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:8px;
  box-shadow:var(--shadow);
  flex:1;
  min-height:118px;
  cursor:pointer;
  transition:transform .12s ease, box-shadow .12s ease;
}

.action-button:hover{
  transform:translateY(-1px);
  box-shadow:0 12px 24px rgba(17,24,39,.1);
}

.action-button h3{
  font-size:15px;
  font-weight:700;
  color:#111827;
}

.action-icon{
  font-size:26px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.action-icon svg{
  width:40px;
  height:40px;
  color:#111827;
}

/* Transactions modal */
.modal{
  display:none;
  position:fixed;
  z-index:1000;
  inset:0;
  background:rgba(2,6,23,.55);
  backdrop-filter:blur(2px);
}

.modal-content{
  background:var(--panel);
  border:1px solid var(--ring);
  border-radius:20px;
  width:min(520px,92vw);
  margin:8vh auto 0;
  box-shadow:var(--shadow);
  padding:22px;
}

/* Scale modals for larger screens */
@media (min-width:2560px){
  .modal-content{
    width:min(800px,92vw);
    padding:36px;
    border-radius:28px;
  }
  .modal h2{
    font-size:28px;
    margin-bottom:20px;
  }
  .modal input{
    padding:18px 20px;
    font-size:22px;
    border-radius:16px;
  }
  .modal button{
    padding:18px 24px;
    font-size:20px;
    border-radius:16px;
  }
  .preset-amount{
    padding:20px 14px;
    font-size:20px;
    border-radius:16px;
  }
}

@media (min-width:1920px) and (max-width:2559px){
  .modal-content{
    width:min(680px,92vw);
    padding:30px;
    border-radius:24px;
  }
  .modal h2{
    font-size:24px;
    margin-bottom:16px;
  }
  .modal input{
    padding:16px 18px;
    font-size:19px;
    border-radius:14px;
  }
  .modal button{
    padding:16px 20px;
    font-size:18px;
    border-radius:14px;
  }
  .preset-amount{
    padding:18px 12px;
    font-size:18px;
    border-radius:14px;
  }
}

@media (min-width:1600px) and (max-width:1919px){
  .modal-content{
    width:min(600px,92vw);
    padding:26px;
  }
  .modal h2{
    font-size:21px;
    margin-bottom:14px;
  }
  .modal input{
    padding:14px 16px;
    font-size:17px;
  }
  .modal button{
    padding:14px 18px;
    font-size:16px;
  }
  .preset-amount{
    padding:16px 12px;
    font-size:16px;
  }
}

.modal h2{
  font-size:18px;
  font-weight:700;
  margin-bottom:12px;
}

.modal input{
  width:100%;
  padding:12px 14px;
  border:1px solid var(--ring);
  border-radius:12px;
  font-size:15px;
}

.modal button{
  background:#111827;
  color:#fff;
  border:none;
  border-radius:12px;
  padding:12px 16px;
  font-size:14px;
  cursor:pointer;
}

.modal .cancel{
  background:#e5e7eb;
  color:#111827;
}

.modal .cancel:hover{
  filter:brightness(.97);
}

/* Balance pill */
.balance-label{
  color:#111827;
  font-weight:600;
  font-size:1em;
  font-size:2.6em !important;
  font-weight:800;
  color:#111827;
  letter-spacing:1px;
}

.icon-wrapper{
  cursor:pointer;
  opacity:.8;
  display:grid;
  place-items:center;
}

.icon-wrapper svg{
  width:20px;
  height:20px;
}

@media (min-width:2560px){
  .icon-wrapper svg{
    width:32px;
    height:32px;
  }
}

@media (min-width:1920px) and (max-width:2559px){
  .icon-wrapper svg{
    width:28px;
    height:28px;
  }
}

@media (min-width:1600px) and (max-width:1919px){
  .icon-wrapper svg{
    width:24px;
    height:24px;
  }
}

.icon-wrapper:hover{
  opacity:1;
}

/* Responsive */
/* Extra large screens - scale everything up */
@media (min-width:2560px){
  .container{
    max-width:96%;
  }
  body{
    font-size:20px;
    padding:24px;
  }
  h1{
    font-size:38px;
  }
  .content{
    grid-template-columns:26% 1fr 22%;
    gap:40px;
  }
  .card{
    padding:36px;
    border-radius:28px;
  }
  .card-name{
    font-size:32px;
  }
  .card-number{
    font-size:32px;
    letter-spacing:3px;
  }
  .card-expiry{
    font-size:22px;
  }
  .control-item,.balance-item{
    padding:28px;
    border-radius:24px;
    font-size:20px;
  }
  .balance-amount{
    font-size:3.5em !important;
  }
  .balance-label{
    font-size:1.4em !important;
  }
  .toggle{
    width:76px;
    height:42px;
  }
  .toggle::after{
    width:34px;
    height:34px;
    top:4px;
    left:4px;
  }
  .toggle.active::after{
    left:38px;
  }
  .funds-box,.merchant-box{
    padding:28px;
    border-radius:28px;
  }
  .funds-box h2,.merchant-box h2{
    font-size:24px;
  }
  .merchant-icon{
    min-height:120px;
    border-radius:20px;
  }
  .merchant-icon img{
    width:64px;
    height:64px;
  }
  .action-button{
    padding:24px;
    border-radius:24px;
  }
  .action-button h3{
    font-size:22px;
  }
  .action-icon{
    font-size:40px;
  }
  .action-icon svg{
    width:60px;
    height:60px;
  }
  .back-button{
    width:64px;
    height:64px;
    font-size:32px;
  }
  #addFundsBtn{
    padding:28px 0 !important;
    font-size:32px !important;
  }
}

@media (min-width:1920px) and (max-width:2559px){
  .container{
    max-width:94%;
  }
  body{
    font-size:18px;
    padding:20px;
  }
  h1{
    font-size:32px;
  }
  .content{
    grid-template-columns:28% 1fr 24%;
    gap:32px;
  }
  .card{
    padding:30px;
    border-radius:24px;
  }
  .card-name{
    font-size:26px;
  }
  .card-number{
    font-size:26px;
  }
  .card-expiry{
    font-size:18px;
  }
  .control-item,.balance-item{
    padding:22px;
    border-radius:20px;
    font-size:18px;
  }
  .balance-amount{
    font-size:3em !important;
  }
  .balance-label{
    font-size:1.3em !important;
  }
  .toggle{
    width:66px;
    height:36px;
  }
  .toggle::after{
    width:30px;
    height:30px;
  }
  .toggle.active::after{
    left:33px;
  }
  .funds-box,.merchant-box{
    padding:24px;
    border-radius:24px;
  }
  .funds-box h2,.merchant-box h2{
    font-size:20px;
  }
  .merchant-icon{
    min-height:100px;
    border-radius:18px;
  }
  .merchant-icon img{
    width:52px;
    height:52px;
  }
  .action-button{
    padding:20px;
    border-radius:22px;
  }
  .action-button h3{
    font-size:19px;
  }
  .action-icon{
    font-size:34px;
  }
  .action-icon svg{
    width:52px;
    height:52px;
  }
  .back-button{
    width:54px;
    height:54px;
    font-size:26px;
  }
  #addFundsBtn{
    padding:24px 0 !important;
    font-size:28px !important;
  }
}

@media (min-width:1600px) and (max-width:1919px){
  .container{
    max-width:92%;
  }
  body{
    font-size:17px;
    padding:18px;
  }
  h1{
    font-size:28px;
  }
  .content{
    grid-template-columns:29% 1fr 24%;
    gap:28px;
  }
  .card{
    padding:26px;
  }
  .card-name{
    font-size:23px;
  }
  .card-number{
    font-size:23px;
  }
  .card-expiry{
    font-size:16px;
  }
  .control-item,.balance-item{
    padding:18px;
    font-size:17px;
  }
  .balance-amount{
    font-size:2.7em !important;
  }
  .balance-label{
    font-size:1.2em !important;
  }
  .toggle{
    width:62px;
    height:34px;
  }
  .toggle::after{
    width:28px;
    height:28px;
  }
  .toggle.active::after{
    left:31px;
  }
  .funds-box,.merchant-box{
    padding:22px;
  }
  .funds-box h2,.merchant-box h2{
    font-size:18px;
  }
  .merchant-icon{
    min-height:92px;
    border-radius:16px;
  }
  .merchant-icon img{
    width:48px;
    height:48px;
  }
  .action-button{
    padding:18px;
  }
  .action-button h3{
    font-size:17px;
  }
  .action-icon{
    font-size:30px;
  }
  .action-icon svg{
    width:46px;
    height:46px;
  }
  .back-button{
    width:50px;
    height:50px;
    font-size:24px;
  }
  #addFundsBtn{
    padding:22px 0 !important;
    font-size:26px !important;
  }
}

@media (min-width:1400px) and (max-width:1599px){
  .container{
    max-width:90%;
  }
  body{
    padding:16px;
  }
  .content{
    grid-template-columns:30% 1fr 25%;
    gap:24px;
  }
}

@media (min-width:1200px) and (max-width:1399px){
  .content{
    grid-template-columns:360px 1fr 280px;
  }
}

@media (min-width:900px) and (max-width:1199px){
  .content{
    grid-template-columns:1fr 1fr;
    grid-template-rows:auto 1fr;
  }
  .card-section{
    grid-column:1/2;
    grid-row:1/3;
  }
  .funds-section{
    grid-column:2/3;
    grid-row:1/2;
  }
  .actions-section{
    grid-column:2/3;
    grid-row:2/3;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    grid-template-rows:repeat(2,1fr);
    gap:16px;
  }
  .container{
    min-height:calc(100vh - 48px);
  }
}

@media (max-width:899px){
  .content{
    grid-template-columns:1fr;
    gap:16px;
  }
  .actions-section{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
  }
}

@media (max-width:640px){
  body{
    padding:16px;
  }
  .card{
    aspect-ratio:16/10;
  }
  .merchant-grid{
    grid-template-columns:repeat(3,1fr);
    gap:10px;
  }
  .actions-section{
    grid-template-columns:repeat(2,1fr);
    gap:12px;
  }
  h1{
    font-size:20px;
  }
}

@media (max-width:480px){
  .merchant-grid{
    grid-template-columns:repeat(2,1fr);
  }
  .payment-methods{
    justify-content:space-around;
  }
}

/* Add Funds Modal Styles */
.preset-amount {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 10px;
  font-size: 14px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.preset-amount:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.preset-amount.selected {
  background: #3b82f6;
  color: #fff;
  border-color: #3b82f6;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}</style>
</head>
<body>
    
    <div class="container">
        <div class="header">
            <button class="back-button" onclick="window.location.href='../dashboard/dashboard.php'" title="Home">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 24px; height: 24px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
            </button>
            <h1>Card Management</h1>
        </div>
        
        <div class="content">
            <!-- Left Column - Card Section -->
            <div class="card-section">
        <div class="card" style="--card-color: <?php echo htmlspecialchars($card_color_css); ?>;">
          <div class="card-name" id="cardNameDisplay"><?php echo htmlspecialchars($card['card_name']); ?></div>
          <div class="card-number"><?php echo htmlspecialchars($card['card_number']); ?></div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-expiry">EXP 02/28</div>
            <div class="card-expiry">CVV <?php echo htmlspecialchars($card['cvv'] ?? '***'); ?></div>
          </div>
        </div>

        <div class="control-item">
          <span>Enabled</span>
          <div class="toggle <?php echo $card['is_active'] ? 'active' : ''; ?>" onclick="toggleCard()"></div>
        </div>

        <div class="control-item" onclick="editCardName()">
          <span id="cardNameText"><?php echo htmlspecialchars($card['card_name']); ?></span>
          <span class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
          </span>
        </div>

        <div class="control-item" onclick="regenerateCardNumber()">
          <span id="cardNumberDisplay"><?php echo htmlspecialchars($card['card_number']); ?></span>
          <span class="icon-wrapper" title="Regenerate card number">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
          </span>
        </div>

        <div id="addFundsDiv">
          <button id="addFundsBtn" style="padding: 18px 0; font-size: 22px; border-radius: 12px; background: #111; color: #fff; border: 1.5px solid #111; cursor: pointer; box-shadow: 0 4px 16px rgba(17,24,39,.12); width: 100%; font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif; font-weight: 700; letter-spacing: 1px; margin-top: 0; margin-bottom: 0; text-align: center;" onclick="openAddFundsModal()">Add Funds</button>
        </div>
            </div>

            <!-- Middle Column - Funds & Merchant -->
            <div class="funds-section">
        <!-- Current Balance above Merchant Lock -->
        <div class="balance-item">
          <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 2px;">
            <span class="balance-label"><span style="font-size:1.05rem;font-weight:600;letter-spacing:0.5px;">CURRENT BALANCE</span></span>
            <button id="toggleBalance" style="background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;color:#6b7280;transition:color 0.2s;" title="Toggle balance visibility">
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;display:none;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
            </button>
          </div>
          <div style="width:100%;margin-top:6px;">
            <span id="balanceAmount" class="balance-amount" style="font-size:2.3em;font-weight:800;line-height:1.1;display:block;letter-spacing:1px;">₹<?php echo number_format($balance, 2); ?></span>
          </div>
        </div>

        <div class="merchant-box">
          <h2>Merchant Lock <span class="verified">verified</span></h2>
          <div class="merchant-grid">
            <?php foreach ($merchant_categories as $category_key => $data): 
              $is_locked = false;
              foreach ($merchant_locks as $lock) {
                if ($lock['merchant_category'] === $category_key && $lock['is_locked']) {
                  $is_locked = true;
                  break;
                }
              }
              $icon_content = $data['icon'];
            ?>
            <div class="merchant-icon <?php echo $is_locked ? 'locked' : ''; ?>" 
               onclick="toggleMerchantLock('<?php echo $category_key; ?>', '<?php echo htmlspecialchars($data['name']); ?>', this)">
              <?php 
                if (strpos($icon_content, '.png') !== false || strpos($icon_content, '.svg') !== false) {
                  echo '<img src="' . htmlspecialchars($icon_content) . '" alt="' . htmlspecialchars($data['name']) . '">';
                } else {
                  echo $icon_content; // It's an emoji
                }
              ?>
            </div>
            <?php endforeach; ?>
            <div class="merchant-icon" onclick="toggleMerchantLock('none', 'None', this)">❌</div>
          </div>
        </div>
            </div>

            <!-- Right Column - Actions -->
            <div class="actions-section">
                <div class="action-button" onclick="copyCVV()">
                    <h3>Copy CVV</h3>
                    <span class="action-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                    </span>
                </div>

                <div class="action-button" onclick="openColorChangeModal()">
                    <h3>Color Change</h3>
                    <span class="action-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" />
                        </svg>
                    </span>
                </div>

                <div class="action-button" onclick="copyCardNumber()">
                    <h3>Copy Card Number</h3>
                    <span class="action-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a2.25 2.25 0 01-2.25 2.25h-1.5a2.25 2.25 0 01-2.25-2.25v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                        </svg>
                    </span>
                </div>

                <div class="action-button" onclick="deleteCard()">
                    <h3>Delete Card</h3>
                    <span class="action-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Card Name Modal -->
    <div id="editNameModal" class="modal">
        <div class="modal-content">
            <h2>Edit Card Name</h2>
            <input type="text" id="newCardName" placeholder="Enter new card name" value="<?php echo htmlspecialchars($card['card_name']); ?>">
            <button onclick="saveCardName()">Save</button>
            <button class="cancel" onclick="closeModal('editNameModal')">Cancel</button>
        </div>
    </div>

    <!-- Transactions Modal -->
    <div id="transactionsModal" class="modal">
        <div class="modal-content">
            <h2>Recent Transactions</h2>
            <div id="transactionsList">
                <?php if (empty($transactions)): ?>
                    <p>No transactions yet</p>
                <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <div style="padding: 10px; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($transaction['merchant_name']); ?></strong><br>
                            <?php echo $transaction['transaction_type'] === 'debit' ? '-' : '+'; ?>₹<?php echo number_format($transaction['amount'], 2); ?><br>
                            <small><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="cancel" onclick="closeModal('transactionsModal')">Close</button>
        </div>
    </div>

    <!-- Color Change Modal -->
    <div id="colorChangeModal" class="modal">
        <div class="modal-content" style="max-width: 520px; text-align: center; max-height: 90vh; overflow-y: auto;">
            <h2 style="background: linear-gradient(45deg, #3b82f6, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 6px; font-size: 20px;">Get Creative</h2>
            <p style="color: #64748b; margin-bottom: 20px; font-size: 13px;">Click or drag on the color wheel</p>
            
            <div style="display: flex; flex-direction: column; align-items: center; gap: 20px; margin-bottom: 20px;">
                <!-- Card Preview -->
                <div id="cardPreview" style="
                    width: 280px;
                    height: 176px;
                    background: <?php echo htmlspecialchars($card_color_css); ?>;
                    border-radius: 14px;
                    color: white;
                    padding: 16px;
                    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    position: relative;
                    transition: all 0.3s ease;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="width: 35px; height: 24px; background: linear-gradient(135deg, #d9d9d9, #ffffff); border-radius: 4px;"></div>
                        <div style="font-size: 18px; font-weight: 800; opacity: 0.9; font-family: 'Montserrat', sans-serif;">S</div>
                    </div>
                    <div style="font-size: 15px; letter-spacing: 1.5px; opacity: 0.9;">XXXX XXXX XXXX XXXX</div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($card['card_name']); ?></div>
                        <div style="font-size: 11px; font-weight: 600; opacity: 0.8;">VIRTUAL</div>
                    </div>
                </div>
                
                <!-- Color Wheel -->
                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                    <div id="colorWheel" style="
                        width: 180px;
                        height: 180px;
                        background: url('colorwheel.png') no-repeat center/cover;
                        border-radius: 50%;
                        box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
                        cursor: crosshair;
                        position: relative;
                        transition: transform 0.1s ease;
                    "></div>
                    <div id="selectedColorDisplay" style="
                        padding: 8px 16px;
                        background: #f8fafc;
                        border-radius: 8px;
                        font-size: 12px;
                        font-weight: 600;
                        color: #334155;
                        font-family: monospace;
                        border: 1px solid #e5e7eb;
                        min-width: 160px;
                    ">Click to select</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button id="saveColorBtn" onclick="saveCardColor()" style="flex: 1; max-width: 180px; background: #3b82f6; color: #fff; border: none; border-radius: 10px; padding: 12px 16px; font-size: 14px; cursor: pointer; font-weight: 600; transition: all 0.3s;" disabled>
                    Save Color
                </button>
                <button class="cancel" onclick="closeModal('colorChangeModal')" style="flex: 1; max-width: 180px; padding: 12px 16px; font-size: 14px;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Add Funds Modal -->
    <div id="addFundsModal" class="modal">
        <div class="modal-content" style="max-width: 500px; background: #fefefe;">
            <h2 style="color: #334155;">Add Funds to Card</h2>
            <p style="color: #64748b; margin-bottom: 24px;">Select an amount to add to your card balance</p>
            
            <!-- Preset Amounts -->
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 12px;">Quick Amounts</h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <button class="preset-amount" onclick="selectAmount(1000)">₹1,000</button>
                    <button class="preset-amount" onclick="selectAmount(2000)">₹2,000</button>
                    <button class="preset-amount" onclick="selectAmount(3000)">₹3,000</button>
                    <button class="preset-amount" onclick="selectAmount(5000)">₹5,000</button>
                    <button class="preset-amount" onclick="selectAmount(10000)">₹10,000</button>
                    <button class="preset-amount" onclick="selectAmount(20000)">₹20,000</button>
                </div>
            </div>
            
            <!-- Custom Amount -->
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 12px;">Custom Amount</h3>
                <input type="number" id="customAmount" placeholder="Enter amount" min="100" max="100000" step="100" style="width: 100%; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 15px; background: #f8fafc; color: #334155;">
            </div>
            
            <!-- Selected Amount Display -->
            <div id="selectedAmountDisplay" style="background: #f1f5f9; padding: 16px; border-radius: 12px; margin-bottom: 24px; text-align: center; display: none; border: 1px solid #e2e8f0;">
                <div style="font-size: 18px; font-weight: 600; color: #334155;">
                    Selected Amount: <span id="selectedAmount" style="color: #3b82f6;">₹0</span>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button onclick="processAddFunds()" id="addFundsProcessBtn" style="flex: 1; background: #3b82f6; color: #fff; border: none; border-radius: 12px; padding: 14px 16px; font-size: 15px; cursor: pointer; font-weight: 600; transition: all 0.2s ease;" disabled>Add Funds</button>
                <button class="cancel" onclick="closeModal('addFundsModal')" style="flex: 1; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const cardId = '<?php echo $card_id; ?>';
        
        function toggleCard() {
            const toggle = document.querySelector('.toggle');
            const isActive = toggle.classList.contains('active');
            
            fetch('/ui/api/cards.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    card_id: cardId,
                    is_active: !isActive
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toggle.classList.toggle('active');
                } else {
                    alert('Error updating card status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating card status');
            });
        }

        // Toggle balance visibility
        let balanceVisible = true;
        const toggleBalanceBtn = document.getElementById('toggleBalance');
        const balanceAmount = document.getElementById('balanceAmount');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeSlashIcon = document.getElementById('eyeSlashIcon');
        const originalBalance = balanceAmount.textContent;

        toggleBalanceBtn.addEventListener('click', function() {
            balanceVisible = !balanceVisible;
            
            if (balanceVisible) {
                balanceAmount.textContent = originalBalance;
                eyeIcon.style.display = 'block';
                eyeSlashIcon.style.display = 'none';
                toggleBalanceBtn.style.color = '#6b7280';
            } else {
                balanceAmount.textContent = '₹••••••';
                eyeIcon.style.display = 'none';
                eyeSlashIcon.style.display = 'block';
                toggleBalanceBtn.style.color = '#9ca3af';
            }
        });

        // Hover effect for the button
        toggleBalanceBtn.addEventListener('mouseenter', function() {
            this.style.color = '#111827';
        });

        toggleBalanceBtn.addEventListener('mouseleave', function() {
            if (balanceVisible) {
                this.style.color = '#6b7280';
            } else {
                this.style.color = '#9ca3af';
            }
        });
        
        function editCardName() {
            document.getElementById('editNameModal').style.display = 'block';
        }
        
        function saveCardName() {
            const newName = document.getElementById('newCardName').value.trim();
            if (!newName) {
                alert('Please enter a card name');
                return;
            }
            
            fetch('/ui/api/card_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_card_name',
                    card_id: cardId,
                    card_name: newName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cardNameDisplay').textContent = newName;
                    document.getElementById('cardNameText').textContent = newName;
                    closeModal('editNameModal');
                } else {
                    alert('Error updating card name: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating card name');
            });
        }
        
        function copyCardNumber() {
            const cardNumber = '<?php echo $card['card_number']; ?>';
            navigator.clipboard.writeText(cardNumber).then(() => {
                alert('Card number copied to clipboard!');
            }).catch(() => {
                alert('Failed to copy card number');
            });
        }
        
        function regenerateCardNumber() {
            if (!confirm('⚠️ WARNING: Are you sure you want to regenerate the card number?\n\nThis will:\n• Generate a new 16-digit card number\n• Update all your transactions\n• Cannot be undone\n\nOld card number will be permanently replaced.')) {
                return;
            }
            
            // Generate new card number
            let newNumber = '';
            for (let i = 0; i < 16; i++) {
                newNumber += Math.floor(Math.random() * 10);
            }
            const formattedNumber = newNumber.substring(0, 4) + ' ' + 
                                   newNumber.substring(4, 8) + ' ' + 
                                   newNumber.substring(8, 12) + ' ' + 
                                   newNumber.substring(12, 16);
            
            // Update on server
            fetch('/ui/api/card_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_card_number',
                    card_id: cardId,
                    card_number: formattedNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update displays
                    document.getElementById('cardNumberDisplay').textContent = formattedNumber;
                    document.querySelector('.card-number').textContent = formattedNumber;
                    alert('Card number successfully regenerated!');
                } else {
                    alert('Error regenerating card number: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error regenerating card number');
            });
        }
        
        function copyCVV() {
            const cvv = '<?php echo $card['cvv'] ?? '***'; ?>';
            if (cvv === '***') {
                alert('CVV not available');
                return;
            }
            navigator.clipboard.writeText(cvv).then(() => {
                alert('CVV copied to clipboard!');
            }).catch(() => {
                alert('Failed to copy CVV');
            });
        }
        
        let selectedCardColor = null;
        let colorWheelCanvas = null;
        let colorWheelCtx = null;
        let colorWheelImg = null;
        let isMouseDownOnWheel = false;
        
        // Convert RGB to Hex
        function rgbToHex(r, g, b) {
            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
        }
        
        function openColorChangeModal() {
            document.getElementById('colorChangeModal').style.display = 'block';
            initColorWheel();
            selectedCardColor = null;
            document.getElementById('saveColorBtn').disabled = true;
        }
        
        function initColorWheel() {
            const wheel = document.getElementById('colorWheel');
            
            // Preload the image and create canvas
            if (!colorWheelImg) {
                colorWheelImg = new Image();
                colorWheelImg.src = 'colorwheel.png';
                colorWheelImg.onload = () => {
                    colorWheelCanvas = document.createElement('canvas');
                    const rect = wheel.getBoundingClientRect();
                    colorWheelCanvas.width = rect.width;
                    colorWheelCanvas.height = rect.height;
                    colorWheelCtx = colorWheelCanvas.getContext('2d');
                    colorWheelCtx.drawImage(colorWheelImg, 0, 0, rect.width, rect.height);
                };
            }
            
            // Mouse events
            wheel.addEventListener('mousedown', handleColorWheelMouseDown);
            wheel.addEventListener('mousemove', handleColorWheelMouseMove);
            wheel.addEventListener('mouseup', handleColorWheelMouseUp);
            wheel.addEventListener('mouseleave', handleColorWheelMouseLeave);
            
            // Touch events for mobile
            wheel.addEventListener('touchstart', handleColorWheelTouchStart);
            wheel.addEventListener('touchmove', handleColorWheelTouchMove);
            wheel.addEventListener('touchend', handleColorWheelTouchEnd);
        }
        
        function handleColorWheelMouseDown(e) {
            e.preventDefault();
            isMouseDownOnWheel = true;
            getColorFromPosition(e);
        }
        
        function handleColorWheelMouseMove(e) {
            if (isMouseDownOnWheel) {
                e.preventDefault();
                getColorFromPosition(e);
            }
        }
        
        function handleColorWheelMouseUp(e) {
            e.preventDefault();
            isMouseDownOnWheel = false;
        }
        
        function handleColorWheelMouseLeave() {
            isMouseDownOnWheel = false;
        }
        
        function handleColorWheelTouchStart(e) {
            e.preventDefault();
            isMouseDownOnWheel = true;
            getColorFromPosition(e.touches[0]);
        }
        
        function handleColorWheelTouchMove(e) {
            e.preventDefault();
            if (isMouseDownOnWheel) {
                getColorFromPosition(e.touches[0]);
            }
        }
        
        function handleColorWheelTouchEnd(e) {
            e.preventDefault();
            isMouseDownOnWheel = false;
        }
        
        function getColorFromPosition(e) {
            if (!colorWheelCanvas || !colorWheelCtx) {
                return;
            }
            
            const wheel = document.getElementById('colorWheel');
            const rect = wheel.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Check if click is within circular area
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const distance = Math.sqrt((x - centerX) ** 2 + (y - centerY) ** 2);
            const radius = rect.width / 2;
            
            if (distance <= radius) {
                const pixel = colorWheelCtx.getImageData(x, y, 1, 1).data;
                const hexColor = rgbToHex(pixel[0], pixel[1], pixel[2]);
                
                // Update card preview
                document.getElementById('cardPreview').style.background = hexColor;
                
                // Update selected color display
                document.getElementById('selectedColorDisplay').textContent = hexColor;
                document.getElementById('selectedColorDisplay').style.background = hexColor;
                document.getElementById('selectedColorDisplay').style.color = (pixel[0] + pixel[1] + pixel[2]) / 3 > 128 ? '#000' : '#fff';
                
                // Add scale effect
                wheel.style.transform = 'scale(0.98)';
                wheel.style.boxShadow = '0 0 30px rgba(59, 130, 246, 0.5)';
                setTimeout(() => {
                    wheel.style.transform = 'scale(1)';
                    wheel.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.3)';
                }, 100);
                
                selectedCardColor = hexColor;
                document.getElementById('saveColorBtn').disabled = false;
            }
        }
        
        function saveCardColor() {
            if (!selectedCardColor) {
                alert('Please select a color first');
                return;
            }
            
            console.log('Saving color:', selectedCardColor, 'for card:', cardId);
            
            fetch('/ui/api/card_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_card_color',
                    card_id: cardId,
                    card_color: selectedCardColor
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    closeModal('colorChangeModal');
                    alert('Card color updated successfully!');
                    // Reload page to reflect changes from database
                    window.location.reload();
                } else {
                    alert('Error updating card color: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('Error updating card color: ' + error.message);
            });
        }
        
        function toggleMerchantLock(category, name, element) {
            const isLocked = element.classList.contains('locked');
            
            fetch('/ui/api/card_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_merchant_lock',
                    card_id: cardId,
                    merchant_category: category,
                    merchant_name: name
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.toggle('locked');
                } else {
                    alert('Error updating merchant lock: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating merchant lock');
            });
        }
        
        function viewTransactions() {
            document.getElementById('transactionsModal').style.display = 'block';
        }
        
        function deleteCard() {
            if (confirm('Are you sure you want to delete this card? This action cannot be undone.')) {
                fetch('/ui/api/cards.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Card deleted successfully');
                        window.location.href = '../dashboard/dashboard.php';
                    } else {
                        alert('Error deleting card: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting card');
                });
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Add Funds functionality
        let selectedAmount = 0;

        function openAddFundsModal() {
            document.getElementById('addFundsModal').style.display = 'block';
            resetAddFundsModal();
        }

        function resetAddFundsModal() {
            selectedAmount = 0;
            
            // Reset preset buttons
            document.querySelectorAll('.preset-amount').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Reset custom amount input
            document.getElementById('customAmount').value = '';
            
            // Hide selected amount display
            document.getElementById('selectedAmountDisplay').style.display = 'none';
            document.getElementById('addFundsProcessBtn').disabled = true;
        }

        function selectAmount(amount) {
            selectedAmount = amount;
            
            // Update preset buttons
            document.querySelectorAll('.preset-amount').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            // Clear custom amount
            document.getElementById('customAmount').value = '';
            
            // Update display
            updateSelectedAmountDisplay();
        }


        function updateSelectedAmountDisplay() {
            if (selectedAmount > 0) {
                document.getElementById('selectedAmount').textContent = `₹${selectedAmount.toLocaleString()}`;
                document.getElementById('selectedAmountDisplay').style.display = 'block';
                document.getElementById('addFundsProcessBtn').disabled = false;
            } else {
                document.getElementById('selectedAmountDisplay').style.display = 'none';
                document.getElementById('addFundsProcessBtn').disabled = true;
            }
        }

        // Handle custom amount input
        document.addEventListener('DOMContentLoaded', function() {
            const customAmountInput = document.getElementById('customAmount');
            if (customAmountInput) {
                customAmountInput.addEventListener('input', function() {
                    const amount = parseFloat(this.value) || 0;
                    if (amount > 0) {
                        selectedAmount = amount;
                        
                        // Clear preset selections
                        document.querySelectorAll('.preset-amount').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                        
                        updateSelectedAmountDisplay();
                    } else {
                        selectedAmount = 0;
                        updateSelectedAmountDisplay();
                    }
                });
            }
        });

        function generateMoneySVG() {
            const svg = `
                <svg width="400" height="400" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#10b981;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#059669;stop-opacity:1" />
                        </linearGradient>
                        <linearGradient id="coinGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#fbbf24;stop-opacity:1" />
                            <stop offset="50%" style="stop-color:#f59e0b;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#d97706;stop-opacity:1" />
                        </linearGradient>
                        <filter id="shadow">
                            <feDropShadow dx="0" dy="4" stdDeviation="8" flood-opacity="0.3"/>
                        </filter>
                    </defs>
                    
                    <!-- Background circle -->
                    <circle cx="200" cy="200" r="180" fill="url(#bgGradient)" filter="url(#shadow)"/>
                    
                    <!-- Coin stack back -->
                    <ellipse cx="200" cy="240" rx="80" ry="20" fill="#d97706" opacity="0.7"/>
                    <rect x="120" y="220" width="160" height="20" fill="#f59e0b" opacity="0.8"/>
                    
                    <!-- Coin stack middle -->
                    <ellipse cx="200" cy="220" rx="80" ry="20" fill="#d97706" opacity="0.7"/>
                    <rect x="120" y="200" width="160" height="20" fill="#f59e0b" opacity="0.9"/>
                    
                    <!-- Main coin -->
                    <ellipse cx="200" cy="200" rx="80" ry="20" fill="#d97706"/>
                    <ellipse cx="200" cy="180" rx="80" ry="20" fill="url(#coinGradient)" filter="url(#shadow)"/>
                    <rect x="120" y="160" width="160" height="20" fill="url(#coinGradient)"/>
                    <ellipse cx="200" cy="160" rx="80" ry="20" fill="url(#coinGradient)"/>
                    
                    <!-- Coin shine -->
                    <ellipse cx="200" cy="160" rx="60" ry="15" fill="white" opacity="0.2"/>
                    
                    <!-- Currency symbol -->
                    <text x="200" y="175" text-anchor="middle" font-family="Arial, sans-serif" font-size="50" font-weight="bold" fill="#065f46">₹</text>
                    
                    <!-- Floating coins -->
                    <g opacity="0.8">
                        <ellipse cx="100" cy="100" rx="25" ry="6" fill="#d97706"/>
                        <ellipse cx="100" cy="94" rx="25" ry="6" fill="url(#coinGradient)"/>
                        <text x="100" y="99" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" font-weight="bold" fill="#065f46">₹</text>
                    </g>
                    
                    <g opacity="0.8">
                        <ellipse cx="300" cy="120" rx="30" ry="7" fill="#d97706"/>
                        <ellipse cx="300" cy="113" rx="30" ry="7" fill="url(#coinGradient)"/>
                        <text x="300" y="119" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="#065f46">₹</text>
                    </g>
                    
                    <g opacity="0.8">
                        <ellipse cx="280" cy="280" rx="22" ry="5" fill="#d97706"/>
                        <ellipse cx="280" cy="275" rx="22" ry="5" fill="url(#coinGradient)"/>
                        <text x="280" y="279" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#065f46">₹</text>
                    </g>
                    
                    <!-- Add Funds text -->
                    <text x="200" y="360" text-anchor="middle" font-family="Montserrat, Arial, sans-serif" font-size="24" font-weight="bold" fill="white">Add Funds</text>
                </svg>
            `;
            
            // Convert SVG to data URL
            const svgBlob = new Blob([svg], {type: 'image/svg+xml;charset=utf-8'});
            return URL.createObjectURL(svgBlob);
        }

        function processAddFunds() {
            if (selectedAmount <= 0) {
                alert('Please select an amount to add');
                return;
            }

            if (selectedAmount < 100) {
                alert('Minimum amount to add is ₹100');
                return;
            }

            if (selectedAmount > 100000) {
                alert('Maximum amount to add is ₹1,00,000');
                return;
            }

            // Generate money SVG
            const moneyImageUrl = generateMoneySVG();

            // Create payment data for Razorpay
            const paymentData = {
                name: 'Add Funds to Card',
                description: `Add ₹${selectedAmount.toLocaleString()} to your card balance`,
                price: selectedAmount,
                quantity: 1,
                image: moneyImageUrl,
                merchantName: 'ShadowPay',
                merchantEmail: 'support@shadowpay.com',
                merchantLogo: moneyImageUrl,
                action: 'add_funds',
                cardId: cardId,
                paymentMethod: 'card'
            };

            // Build URL with parameters
            const url = '../razorpay/index.html?' + new URLSearchParams(paymentData).toString();
            
            // Close modal first
            closeModal('addFundsModal');
            
            // Open payment page in popup
            const paymentWindow = window.open(url, 'addFunds', 'width=1200,height=800,scrollbars=yes,resizable=yes');
            
            // Listen for payment completion
            const checkClosed = setInterval(() => {
                if (paymentWindow.closed) {
                    clearInterval(checkClosed);
                    // Check if payment was successful
                    checkAddFundsStatus();
                }
            }, 1000);

            // Also listen for postMessage from payment page
            window.addEventListener('message', function(event) {
                if (event.data && event.data.success && event.data.action === 'add_funds') {
                    clearInterval(checkClosed);
                    handleAddFundsSuccess(event.data);
                }
            });
        }

        function checkAddFundsStatus() {
            // Check localStorage for payment completion
            const purchaseData = localStorage.getItem('purchaseCompletion');
            if (purchaseData) {
                const data = JSON.parse(purchaseData);
                if (data.success && data.action === 'add_funds') {
                    handleAddFundsSuccess(data);
                }
            }
        }

        function handleAddFundsSuccess(paymentData) {
            // Send payment data to server for processing
            fetch('../api/add_funds.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cardId: cardId,
                    amount: paymentData.amount,
                    transactionId: paymentData.transactionId,
                    paymentMethod: paymentData.paymentMethod,
                    timestamp: paymentData.timestamp
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear localStorage
                    localStorage.removeItem('purchaseCompletion');
                    
                    // Show success message
                    alert(`Successfully added ₹${paymentData.amount.toLocaleString()} to your card!`);
                    
                    // Reload page to show updated balance
                    window.location.reload();
                } else {
                    alert('Payment successful but there was an issue adding funds to your card. Please contact support.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment successful but there was an issue processing your request. Please contact support.');
            });
        }
    </script>
</body>
</html>
