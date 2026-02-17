// Global state
let selectedBank = null;
let selectedWallet = null;
let currentPaymentMethod = 'card';

// Product data with defaults
let productData = {
    name: 'Premium Wireless Headphones',
    description: 'Noise-cancelling bluetooth headphones with 30-hour battery',
    price: 1299.00,
    quantity: 1,
    image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&h=400&fit=crop',
    merchantName: 'TechStore India',
    merchantEmail: 'support@techstore.com',
    merchantLogo: 'https://images.unsplash.com/photo-1599305445671-ac291c95aaa9?w=100&h=100&fit=crop'
};

// Initialize dynamic data from URL parameters
function initializeProductData() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update product data from URL parameters
    if (urlParams.get('name')) productData.name = decodeURIComponent(urlParams.get('name'));
    if (urlParams.get('description')) productData.description = decodeURIComponent(urlParams.get('description'));
    if (urlParams.get('price')) productData.price = parseFloat(urlParams.get('price'));
    if (urlParams.get('quantity')) productData.quantity = parseInt(urlParams.get('quantity'));
    if (urlParams.get('image')) productData.image = decodeURIComponent(urlParams.get('image'));
    if (urlParams.get('merchantName')) productData.merchantName = decodeURIComponent(urlParams.get('merchantName'));
    if (urlParams.get('merchantEmail')) productData.merchantEmail = decodeURIComponent(urlParams.get('merchantEmail'));
    if (urlParams.get('merchantLogo')) productData.merchantLogo = decodeURIComponent(urlParams.get('merchantLogo'));
    if (urlParams.get('planType')) productData.planType = decodeURIComponent(urlParams.get('planType'));
    if (urlParams.get('action')) productData.action = decodeURIComponent(urlParams.get('action'));
    if (urlParams.get('cardId')) productData.cardId = decodeURIComponent(urlParams.get('cardId'));
    
    // Calculate totals
    const subtotal = productData.price * productData.quantity;
    const taxes = subtotal * 0.18; // 18% GST
    const total = subtotal + taxes;
    
    // Update DOM elements
    document.getElementById('productName').textContent = productData.name;
    document.getElementById('productDescription').textContent = productData.description;
    document.getElementById('productPrice').textContent = `₹${productData.price.toFixed(2)}`;
    document.getElementById('productQuantity').textContent = productData.quantity;
    document.getElementById('productImage').src = productData.image;
    document.getElementById('merchantName').textContent = productData.merchantName;
    document.getElementById('merchantEmail').textContent = productData.merchantEmail;
    document.getElementById('merchantLogo').src = productData.merchantLogo;
    
    // Update pricing
    document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
    document.getElementById('taxes').textContent = `₹${taxes.toFixed(2)}`;
    document.getElementById('totalAmount').textContent = `₹${total.toFixed(2)}`;
    
    // Update payment buttons
    document.getElementById('cardPayBtn').textContent = `Pay ₹${total.toFixed(2)}`;
    document.getElementById('upiPayBtn').textContent = `Pay ₹${total.toFixed(2)}`;
    document.getElementById('upiQrPayBtn').textContent = `Pay ₹${total.toFixed(2)}`;
    document.getElementById('netbankingPayBtn').textContent = `Pay ₹${total.toFixed(2)}`;
    document.getElementById('walletPayBtn').textContent = `Pay ₹${total.toFixed(2)}`;
    
    // Generate order ID
    const orderId = 'ORDER_' + Math.random().toString(36).substr(2, 9).toUpperCase();
    document.getElementById('orderId').textContent = orderId;
    
    // Store totals for later use
    productData.subtotal = subtotal;
    productData.taxes = taxes;
    productData.total = total;
    productData.orderId = orderId;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initializeProductData);

// Tab Switching
function switchTab(tabName) {
    // Update active tab button
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active');
        }
    });

    // Update active tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    currentPaymentMethod = tabName;
}

// UPI Tab Switching
function switchUpiTab(tabName) {
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    document.querySelectorAll('.upi-content').forEach(content => {
        content.classList.remove('active');
    });
    
    if (tabName === 'id') {
        document.getElementById('upiIdContent').classList.add('active');
    } else {
        document.getElementById('upiQrContent').classList.add('active');
    }
}

// Bank Selection
function selectBank(element, bankId) {
    document.querySelectorAll('.bank-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    element.classList.add('selected');
    selectedBank = bankId;
    document.getElementById('netbankingPayBtn').disabled = false;
}

// Wallet Selection
function selectWallet(element, walletId) {
    document.querySelectorAll('.wallet-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    element.classList.add('selected');
    selectedWallet = walletId;
    document.getElementById('walletPayBtn').disabled = false;
}

// Card Number Formatting
document.getElementById('cardNumber')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Expiry Date Formatting
document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    if (value.length >= 2) {
        e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
    } else {
        e.target.value = value;
    }
});

// Process Payment
async function processPayment(method) {
    // Show processing modal
    document.getElementById('processingModal').classList.remove('hidden');
    document.getElementById('processingView').classList.remove('hidden');
    document.getElementById('successView').classList.add('hidden');
    
    const stages = ['initiating', 'verifying', 'processing', 'completing'];
    const progressFill = document.getElementById('progressFill');
    
    // Animate through stages
    for (let i = 0; i < stages.length; i++) {
        const stage = stages[i];
        const stageElement = document.querySelector(`.stage[data-stage="${stage}"]`);
        
        // Mark current stage as active
        stageElement.classList.add('active');
        
        // Update progress bar
        progressFill.style.width = `${((i + 1) / stages.length) * 100}%`;
        
        // Wait based on stage
        if (i === 0) await sleep(800);
        else if (i === 1) await sleep(1000);
        else if (i === 2) await sleep(1200);
        else await sleep(800);
        
        // Mark as completed
        stageElement.classList.remove('active');
        stageElement.classList.add('completed');
    }
    
    // Show success in modal
    document.getElementById('processingView').classList.add('hidden');
    document.getElementById('successView').classList.remove('hidden');
    
    await sleep(1500);
    
    // Hide processing modal
    document.getElementById('processingModal').classList.add('hidden');
    
    // Show success screen with confetti
    showSuccessScreen(method);
    
    // Handle purchase completion
    handlePurchaseCompletion(method);
}

// Handle purchase completion and return values
async function handlePurchaseCompletion(method) {
    // Create purchase completion data
    const purchaseData = {
        success: true,
        transactionId: productData.orderId,
        paymentMethod: method,
        amount: productData.total,
        productName: productData.name,
        merchantName: productData.merchantName,
        planType: productData.planType,
        action: productData.action,
        cardId: productData.cardId,
        timestamp: new Date().toISOString(),
        status: 'completed'
    };
    
    // Store in localStorage for parent window access
    localStorage.setItem('purchaseCompletion', JSON.stringify(purchaseData));
    
    // Send to API endpoint
    try {
        const response = await fetch('../api/purchase_complete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(purchaseData)
        });
        
        if (response.ok) {
            const result = await response.json();
            console.log('Purchase recorded successfully:', result);
        } else {
            console.error('Failed to record purchase:', response.statusText);
        }
    } catch (error) {
        console.error('Error sending purchase data:', error);
    }
    
    // If opened in popup/iframe, send message to parent
    if (window.opener) {
        window.opener.postMessage(purchaseData, '*');
        // Close popup after a delay
        setTimeout(() => {
            window.close();
        }, 3000);
    }
    
    // If in iframe, send message to parent
    if (window.parent !== window) {
        window.parent.postMessage(purchaseData, '*');
    }
    
    // Add URL parameter to indicate completion
    const url = new URL(window.location);
    url.searchParams.set('payment_status', 'success');
    url.searchParams.set('transaction_id', productData.orderId);
    url.searchParams.set('amount', productData.total);
    url.searchParams.set('method', method);
    
    // Update URL without reload
    window.history.replaceState({}, '', url);
    
    // Log completion for debugging
    console.log('Purchase completed:', purchaseData);
}

function showSuccessScreen(method) {
    document.getElementById('checkoutPage').classList.add('hidden');
    document.getElementById('successScreen').classList.remove('hidden');
    
    // Update transaction details with dynamic data
    document.getElementById('transactionId').textContent = productData.orderId;
    document.getElementById('paymentMethod').textContent = method.charAt(0).toUpperCase() + method.slice(1);
    document.getElementById('amountPaid').textContent = `₹${productData.total.toFixed(2)}`;
    
    // Trigger confetti
    const duration = 3000;
    const animationEnd = Date.now() + duration;
    const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 999 };

    function randomInRange(min, max) {
        return Math.random() * (max - min) + min;
    }

    const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now();

        if (timeLeft <= 0) {
            return clearInterval(interval);
        }

        const particleCount = 50 * (timeLeft / duration);
        
        confetti(Object.assign({}, defaults, { 
            particleCount, 
            origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } 
        }));
        confetti(Object.assign({}, defaults, { 
            particleCount, 
            origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } 
        }));
    }, 250);
}

function resetCheckout() {
    document.getElementById('successScreen').classList.add('hidden');
    document.getElementById('checkoutPage').classList.remove('hidden');
    
    // Reset form
    document.getElementById('cardForm')?.reset();
    document.querySelectorAll('.bank-btn, .wallet-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    selectedBank = null;
    selectedWallet = null;
    
    // Reset processing stages
    document.querySelectorAll('.stage').forEach(stage => {
        stage.classList.remove('active', 'completed');
    });
    document.getElementById('progressFill').style.width = '0';
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}