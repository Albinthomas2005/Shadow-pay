<?php
require_once __DIR__ . '/../api/bootstrap.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login/login.html');
  exit;
}

// Fetch user's plan
$userStmt = execute_with_retry($pdo, 'SELECT plan FROM users WHERE id = :id', ['id' => $_SESSION['user_id']]);
$userPlan = $userStmt->fetchColumn();

$stmt = execute_with_retry($pdo, 'SELECT * FROM cards WHERE user_id = :user_id ORDER BY created_at DESC', ['user_id' => $_SESSION['user_id']]);
$cards = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShadowPay | Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* Base */
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { height: 100%; }
body {
  font-family: 'Poppins', sans-serif;
  background-color: #0c0c0c;
  color: #fff;
  padding: 40px 0;
  overflow: hidden;                /* no page scroll */
  overscroll-behavior: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  opacity: 0;
  animation: fadeIn 0.8s ease-out forwards;
}

/* Page transition overlay */
.page-transition {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #0c0c0c 0%, #1a1a1a 50%, #0c0c0c 100%);
  z-index: 10000;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
}

.page-transition.active {
  opacity: 1;
  pointer-events: all;
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInLeft {
  from { opacity: 0; transform: translateX(-30px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes slideInRight {
  from { opacity: 0; transform: translateX(30px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

@keyframes glow {
  0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
  50% { box-shadow: 0 8px 30px rgba(255,255,255,0.1); }
}

/* Scrollbar Styles */
::-webkit-scrollbar {
  width: 8px;
}
::-webkit-scrollbar-track {
  background: #0c0c0c; /* Match body background */
}
::-webkit-scrollbar-thumb {
  background: #222;
  border-radius: 4px;
}

/* Icons, logo */
.home-icon { 
  position: absolute; 
  top: 25px; 
  left: 25px; 
  cursor: pointer; 
  transition: all 0.3s ease;
  animation: slideInLeft 0.6s ease-out 0.2s both;
}
.home-icon:hover { 
  transform: scale(1.1); 
  filter: drop-shadow(0 0 10px rgba(255,255,255,0.3));
}
.home-icon svg { 
  width: 30px; 
  height: 30px; 
  stroke: #fff; 
  transition: all 0.3s ease;
}
.logo { 
  animation: slideInUp 0.8s ease-out 0.4s both;
}
.logo img { 
  width: 200px; 
  margin-bottom: 40px; 
  transition: all 0.3s ease;
  filter: drop-shadow(0 4px 20px rgba(255,255,255,0.1));
}
.logo img:hover {
  transform: scale(1.05);
  filter: drop-shadow(0 6px 25px rgba(255,255,255,0.2));
}

/* Layout */
.dashboard {
  width: min(1200px, 90vw);
  flex: 1 1 auto;                  /* consume remaining viewport height */
  min-height: 0;                   /* allow inner scrolling */
  display: grid;
  grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr);
  gap: 25px;
  overflow: hidden;                /* scrolling happens in children */
  animation: slideInUp 1s ease-out 0.6s both;
}
.left-column {
  display: flex;
  flex-direction: column;
  gap: 25px;
  min-height: 0;
}

/* Cards/sections */
.section {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 15px;
  padding: 25px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.3);
  transition: all 0.3s ease;
  animation: slideInUp 0.8s ease-out both;
}
.section:nth-child(1) { animation-delay: 0.8s; }
.section:nth-child(2) { animation-delay: 1s; }
.section:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 30px rgba(0,0,0,0.4);
  border-color: rgba(255,255,255,0.2);
}
.section h3 {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  padding-bottom: 10px;
  transition: all 0.3s ease;
}
.section:hover h3 {
  color: #fff;
  text-shadow: 0 0 10px rgba(255,255,255,0.3);
}

/* Welcome + button */
.welcome { 
  font-size: 22px; 
  margin-bottom: 25px; 
  animation: slideInLeft 0.6s ease-out 1.2s both;
}
.welcome span { 
  font-weight: 700; 
  font-size: 28px; 
  background: linear-gradient(45deg, #fff, #4CAF50);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: pulse 2s ease-in-out infinite;
}
.new-btn {
  background: transparent;
  border: 2px dashed #fff;
  color: #fff;
  font-weight: 600;
  font-size: 18px;
  padding: 12px 35px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  animation: slideInUp 0.6s ease-out 1.4s both;
}
.new-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s;
}
.new-btn:hover::before {
  left: 100%;
}
.new-btn:hover { 
  background: #fff; 
  color: #000; 
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(255,255,255,0.3);
  border-style: solid;
}

/* Left middle panel scrolls */
.left-column .section:first-child { flex: 0 0 auto; }
.recent-transactions {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: start;
  text-align: center;
  padding: 30px;
}
.recent-transactions img { width: 250px; margin-bottom: 14px; }

/* Right panel scrolls */
.recently-used { min-height: 0; overflow: auto; }

/* Card rows */
.recently-used .card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  background: rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 15px 20px;
  margin: 12px 0;
  transition: all 0.3s ease;
  animation: slideInRight 0.6s ease-out both;
  position: relative;
  overflow: hidden;
}
.recently-used .card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
  transition: left 0.6s;
}
.recently-used .card:hover::before {
  left: 100%;
}
.recently-used .card:hover { 
  background: rgba(255,255,255,0.15); 
  transform: translateX(5px) scale(1.02);
  box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}
.card-info h4 { font-size: 18px; font-weight: 700; }
.card-info p { font-size: 14px; color: #bbb; }

/* Toggle */
.toggle {
  width: 40px;
  height: 22px;
  border-radius: 20px;
  border: 2px solid #fff;
  position: relative;
  cursor: pointer;
  transition: 0.3s;
}
.toggle.active { background: #fff; }
.toggle::after {
  content: '';
  position: absolute;
  top: 2px; left: 2px;
  width: 14px; height: 14px;
  background: #fff;
  border-radius: 50%;
  transition: 0.3s;
}
.toggle.active::after { left: 20px; background: #000; }

/* States */
.no-cards { text-align: center; color: #bbb; font-style: italic; padding: 20px; }

/* Mobile */
@media (max-width: 900px) {
  .dashboard {
    grid-template-columns: 1fr;
  }
  .recent-transactions img { width: 250px; }
}

  </style>
</head>
<body>
  <!-- Page transition overlay -->
  <div class="page-transition" id="pageTransition"></div>


  <div style="width: min(1200px, 90vw); position: relative; margin-bottom: 10px;">
    <div class="logo" style="display: flex; align-items: center; justify-content: center; min-width: 200px; width: 100%;">
      <img src="logowhite.png" alt="ShadowPay Logo">
    </div>
    <div style="position: absolute; top: 0; right: 0; display: flex; align-items: flex-start; gap: 12px; animation: slideInRight 0.6s ease-out 0.4s both;">
      <button id="plans-btn" style="background: #fff; color: #000; font-weight: 600; font-size: 16px; padding: 10px 28px; border-radius: 10px; border: none; cursor: pointer; margin-top: 2px; transition: all 0.3s ease; position: relative; overflow: hidden;">Plans</button>
      <div class="home-icon" onclick="navigateWithTransition('../logout.php')" style="position: static; cursor: pointer; margin-top: 0;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </div>
    </div>
  </div>

  <div class="dashboard">
    <div class="left-column">
      <div class="section">
        <div class="welcome">
          <span>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
          <?php if ($userPlan): ?>
            <span style="display: inline-block; margin-left: 12px; padding: 4px 12px; background: #fff; color: #000; font-size: 14px; font-weight: 700; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px; vertical-align: middle; box-shadow: 0 2px 8px rgba(255,255,255,0.2); -webkit-text-fill-color: #000; background-clip: border-box;">
              <?php echo htmlspecialchars($userPlan); ?>
            </span>
          <?php endif; ?>
          <br>
          Welcome to your Dashboard
        </div>
        <button class="new-btn" id="new-btn">NEW+</button>
      </div>

      <div class="section recent-transactions">
        <h3>Recent Transactions</h3>
        <img src="no-transactions.png" alt="No Recent Transactions">
        <p>No Recent Transactions</p>
      </div>
    </div>

    <div class="section recently-used">
      <h3>Your Cards</h3>
      <div class="cards-container">
      <?php if (empty($cards)): ?>
        <div class="no-cards" style="padding-top: 20px;">No cards created yet. Click NEW+ to create your first card!</div>
      <?php else: ?>
        <div style="display: flex; flex-wrap: wrap; gap: 18px; justify-content: flex-start;">
        <?php
        // Sanitize card color input and provide a safe fallback (copied from configure.php)
        if (!function_exists('sanitize_css_color')) {
          function sanitize_css_color($color) {
              $color = trim($color);
              if (empty($color)) return '#222';
              if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
                  return $color;
              }
              if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $color)) {
                  return $color;
              }
              if (preg_match('/^hsla?\(\s*\d{1,3}(?:deg|rad|grad|turn)?\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%?(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $color)) {
                  return $color;
              }
              return '#222';
          }
        }
        if (!function_exists('is_bright_color')) {
          function is_bright_color($hex) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
              $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            if (strlen($hex) !== 6) return false;
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            // Improved perceived brightness formula (W3C recommendation)
            $brightness = sqrt(
              $r * $r * 0.241 +
              $g * $g * 0.691 +
              $b * $b * 0.068
            );
            return $brightness > 180;
          }
        }
        $cardIndex = 0;
        foreach ($cards as $card):
          $cardColor = sanitize_css_color($card['card_color'] ?? '');
          $textColor = (strpos($cardColor, '#') === 0 && is_bright_color($cardColor)) ? '#222' : '#fff';
          $animationDelay = 1.6 + ($cardIndex * 0.1);
        ?>
          <div class="card" data-card-id="<?php echo $card['id']; ?>" style="background: <?php echo htmlspecialchars($cardColor); ?>; box-shadow: 0 4px 16px rgba(0,0,0,0.18); min-width: 260px; max-width: 320px; width: 100%; height: 170px; border-radius: 18px; display: flex; flex-direction: row; justify-content: space-between; align-items: stretch; padding: 22px 24px; position: relative; color: <?php echo $textColor; ?>; animation: slideInUp 0.6s ease-out <?php echo $animationDelay; ?>s both; transition: all 0.3s ease;"
               onmouseover="this.style.transform='translateY(-8px) scale(1.02)'; this.style.boxShadow='0 8px 30px rgba(0,0,0,0.3)'"
               onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.18)'">
            <div class="card-info" style="flex:1; display: flex; flex-direction: column; justify-content: center;">
              <h4 style="font-size: 22px; font-weight: 700; margin-bottom: 8px; text-shadow: 0 2px 8px rgba(0,0,0,0.18); color: <?php echo $textColor; ?>;">
                <?php echo htmlspecialchars($card['card_name']); ?>
              </h4>
              <p style="font-size: 14px; letter-spacing: 2px; color: <?php echo $textColor === '#fff' ? '#f3f3f3' : '#222'; ?>; text-shadow: 0 1px 4px rgba(0,0,0,0.18);">
                <?php echo htmlspecialchars($card['card_number']); ?>
              </p>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; gap: 10px;">
              <button onclick="navigateWithTransition('../configure/configure.php?card_id=<?php echo $card['id']; ?>')"
                      style="background: rgba(255,255,255,0.18); color: <?php echo $textColor; ?>; border: none; width: 44px; height: 44px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 0; transition: all 0.3s ease; position: relative; overflow: hidden;"
                      onmouseover="this.style.transform='scale(1.1)'; this.style.background='rgba(255,255,255,0.3)'"
                      onmouseout="this.style.transform='scale(1)'; this.style.background='rgba(255,255,255,0.18)'">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z" stroke="<?php echo $textColor; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" stroke="<?php echo $textColor; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button onclick="copyCardNumber('<?php echo htmlspecialchars($card['card_number']); ?>')"
                      style="background: rgba(255,255,255,0.18); color: <?php echo $textColor; ?>; border: none; width: 44px; height: 44px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; position: relative; overflow: hidden;"
                      onmouseover="this.style.transform='scale(1.1)'; this.style.background='rgba(255,255,255,0.3)'"
                      onmouseout="this.style.transform='scale(1)'; this.style.background='rgba(255,255,255,0.18)'">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="6" y="7" width="16" height="18" rx="3" stroke="<?php echo $textColor; ?>" stroke-width="2" fill="none"/>
                  <rect x="10" y="3" width="8" height="6" rx="2" stroke="<?php echo $textColor; ?>" stroke-width="2" fill="none"/>
                  <line x1="10" y1="13" x2="18" y2="13" stroke="<?php echo $textColor; ?>" stroke-width="2"/>
                  <line x1="10" y1="17" x2="18" y2="17" stroke="<?php echo $textColor; ?>" stroke-width="2"/>
                </svg>
              </button>
            </div>
            <span style="position: absolute; bottom: 12px; right: 18px; font-size: 12px; color: <?php echo $textColor === '#fff' ? '#eee' : '#222'; ?>; opacity: 0.7;">ID: <?php echo $card['id']; ?></span>
          </div>
        <?php 
          $cardIndex++;
          endforeach; 
        ?>
        </div>
      <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
  // Set plan selection status from backend
  let hasChosenPlan = <?php echo $userPlan ? 'true' : 'false'; ?>;

    // Page transition function
    function navigateWithTransition(url) {
      const transition = document.getElementById('pageTransition');
      transition.classList.add('active');
      setTimeout(() => {
        window.location.href = url;
      }, 300);
    }

    // Plans button click
    document.getElementById('plans-btn').onclick = function() {
      navigateWithTransition('../dashboard/pricing.html');
    };

    // NEW+ button click
    document.getElementById('new-btn').onclick = function() {
      if (!hasChosenPlan) {
        showPlanDialog();
      } else {
        navigateWithTransition('namethecard.html');
      }
    };

    // Dialog creation
    function showPlanDialog() {
      if (document.getElementById('plan-dialog')) return;
      const dialog = document.createElement('div');
      dialog.id = 'plan-dialog';
      dialog.style.position = 'fixed';
      dialog.style.top = '0';
      dialog.style.left = '0';
      dialog.style.width = '100vw';
      dialog.style.height = '100vh';
      dialog.style.background = 'rgba(0,0,0,0.7)';
      dialog.style.display = 'flex';
      dialog.style.alignItems = 'center';
      dialog.style.justifyContent = 'center';
      dialog.style.zIndex = '9999';
      dialog.innerHTML = `
        <div style="background: #181818; color: #fff; padding: 40px 32px; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.4); text-align: center; min-width: 320px;">
          <h2 style="margin-bottom: 18px; font-size: 24px;">Plan Required</h2>
          <p style="margin-bottom: 28px; font-size: 16px;">You haven't chosen a plan yet.<br>Please choose a plan before creating a new card.</p>
          <button id="choose-plan-btn" style="background: #fff; color: #000; font-weight: 600; font-size: 16px; padding: 10px 28px; border-radius: 10px; border: none; cursor: pointer; margin-bottom: 10px;">Choose Plan</button><br>
          <button id="close-dialog-btn" style="background: transparent; color: #fff; font-size: 14px; border: none; margin-top: 8px; cursor: pointer;">Cancel</button>
        </div>
      `;
      document.body.appendChild(dialog);
      document.getElementById('choose-plan-btn').onclick = function() {
        navigateWithTransition('../dashboard/pricing.html');
      };
      document.getElementById('close-dialog-btn').onclick = function() {
        dialog.remove();
      };
    }

    // Copy card number to clipboard with visual feedback
    function copyCardNumber(cardNumber) {
      navigator.clipboard.writeText(cardNumber).then(() => {
        // Create a temporary toast notification
        const toast = document.createElement('div');
        toast.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: #4CAF50;
          color: white;
          padding: 12px 20px;
          border-radius: 8px;
          font-weight: 600;
          z-index: 10001;
          animation: slideInRight 0.3s ease-out;
          box-shadow: 0 4px 20px rgba(76, 175, 80, 0.3);
        `;
        toast.textContent = 'Card number copied!';
        document.body.appendChild(toast);
        
        // Remove toast after 2 seconds
        setTimeout(() => {
          toast.style.animation = 'slideInRight 0.3s ease-out reverse';
          setTimeout(() => toast.remove(), 300);
        }, 2000);
      }).catch(() => {
        // Create error toast
        const toast = document.createElement('div');
        toast.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: #f44336;
          color: white;
          padding: 12px 20px;
          border-radius: 8px;
          font-weight: 600;
          z-index: 10001;
          animation: slideInRight 0.3s ease-out;
          box-shadow: 0 4px 20px rgba(244, 67, 54, 0.3);
        `;
        toast.textContent = 'Failed to copy card number';
        document.body.appendChild(toast);
        
        setTimeout(() => {
          toast.style.animation = 'slideInRight 0.3s ease-out reverse';
          setTimeout(() => toast.remove(), 300);
        }, 2000);
      });
    }

    // Add loading animation for page transitions
    document.addEventListener('DOMContentLoaded', function() {
      // Add staggered animation to cards
      const cards = document.querySelectorAll('.card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${1.6 + (index * 0.1)}s`;
      });
    });
  </script>
</body>
</html>
