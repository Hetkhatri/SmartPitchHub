<?php
session_start();
require_once '../../../db.php';

// Only investors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'investor') {
    header("Location: ../../index.php");
    exit;
}

// --- BACK BUTTON LOGIC ---
$back_url = '../../investor-dashboard.php'; 

if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    
    // If coming from view-pitch.php, go back there
    if (strpos($referer, 'view-pitch.php') !== false) {
        $back_url = $referer; 
    } 
    // If coming from dashboard, ensure we use the correct relative path
    elseif (strpos($referer, 'investor-dashboard.php') !== false) {
        $back_url = '../../investor-dashboard.php'; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buy Bid Tokens | SmartPitchHub</title>
  <meta name="description" content="Purchase bid packs to invest in startup pitches. Get bonus bids and exclusive access to promising startups.">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ... (KEEP ALL YOUR EXISTING CSS HERE) ... */
    
    /* ADD THIS CSS FOR THE BACK BUTTON */
    .back-btn-container {
        position: absolute;
        top: 24px;
        left: 24px;
        z-index: 101;
    }

    .back-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--card);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        color: var(--muted);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        font-size: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .back-btn:hover {
        color: var(--foreground);
        border-color: var(--primary);
        transform: translateX(-3px);
    }

    .back-btn svg {
        width: 18px;
        height: 18px;
    }

    /* Adjust for mobile */
    @media (max-width: 768px) {
        .back-btn-container {
            top: 16px;
            left: 16px;
        }
        .back-btn span {
            display: none; /* Hide text on small screens */
        }
        .back-btn {
            padding: 10px;
        }
    }
    
    /* ... (REST OF YOUR CSS) ... */
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --background: #0D0F1A;
      --card: #141827;
      --card-elevated: #1a1f35;
      --foreground: #ffffff;
      --primary: #A78BFA;
      --primary-dark: #8B5CF6;
      --muted: #6B7280;
      --border: #3B3F5C;
      --border-subtle: #2D3348;
      --green: #10B981;
      --gold: #F59E0B;
      --shadow-glow: rgba(167, 139, 250, 0.4);
    }

    [data-theme="light"] {
      --background: #F8FAFC;
      --card: #FFFFFF;
      --card-elevated: #F1F5F9;
      --foreground: #0F172A;
      --primary: #7C3AED;
      --primary-dark: #6D28D9;
      --muted: #64748B;
      --border: #CBD5E1;
      --border-subtle: #E2E8F0;
      --green: #059669;
      --gold: #D97706;
      --shadow-glow: rgba(124, 58, 237, 0.25);
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Background Effects */
    .bg-effects {
      position: fixed;
      inset: 0;
      overflow: hidden;
      pointer-events: none;
      z-index: 0;
    }

    .bg-glow-1 {
      position: absolute;
      top: 0;
      left: 25%;
      width: 400px;
      height: 400px;
      background: rgba(167, 139, 250, 0.1);
      border-radius: 50%;
      filter: blur(128px);
    }

    [data-theme="light"] .bg-glow-1 {
      background: rgba(124, 58, 237, 0.08);
    }

    .bg-glow-2 {
      position: absolute;
      bottom: 0;
      right: 25%;
      width: 400px;
      height: 400px;
      background: rgba(167, 139, 250, 0.05);
      border-radius: 50%;
      filter: blur(128px);
    }

    [data-theme="light"] .bg-glow-2 {
      background: rgba(124, 58, 237, 0.05);
    }

    /* Theme Toggle */
    .theme-toggle {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 100;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: var(--card);
      border: 1px solid var(--border-subtle);
      border-radius: 9999px;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 20px var(--shadow-glow);
    }

    .theme-toggle:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px var(--shadow-glow);
    }

    .theme-toggle svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
      transition: transform 0.3s;
    }

    .theme-toggle:hover svg {
      transform: rotate(15deg);
    }

    .theme-toggle span {
      font-size: 14px;
      font-weight: 500;
      color: var(--foreground);
    }

    .sun-icon, .moon-icon {
      display: none;
    }

    [data-theme="light"] .sun-icon {
      display: block;
    }

    [data-theme="dark"] .moon-icon,
    :root:not([data-theme]) .moon-icon {
      display: block;
    }

    [data-theme="light"] .light-text {
      display: none;
    }

    [data-theme="light"] .dark-text {
      display: inline;
    }

    .dark-text {
      display: none;
    }

    :root:not([data-theme="light"]) .light-text {
      display: inline;
    }

    /* Container */
    .container {
      position: relative;
      z-index: 10;
      max-width: 1200px;
      margin: 0 auto;
      padding: 48px 16px;
    }

    /* Header */
    header {
      text-align: center;
      margin-bottom: 64px;
    }

    .wallet-badge {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 10px 20px;
      background: rgba(20, 24, 39, 0.5);
      border: 1px solid rgba(59, 63, 92, 0.3);
      border-radius: 9999px;
      margin-bottom: 32px;
    }

    .wallet-badge svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
    }

    .wallet-badge span {
      font-weight: 500;
    }

    .wallet-badge .balance {
      color: var(--primary);
      font-weight: 700;
      font-size: 18px;
    }

    .title-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 16px;
    }

    .title-wrapper svg {
      width: 40px;
      height: 40px;
      color: var(--primary);
    }

    h1 {
      font-size: clamp(32px, 5vw, 48px);
      font-weight: 800;
      color: var(--foreground);
    }

    .subtitle {
      font-size: 18px;
      color: var(--muted);
      margin-bottom: 24px;
      max-width: 560px;
      margin-left: auto;
      margin-right: auto;
    }

    /* Info Tooltip */
    .info-tooltip {
      position: relative;
      display: inline-block;
    }

    .info-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      background: none;
      border: none;
      color: var(--muted);
      font-size: 14px;
      cursor: pointer;
      transition: color 0.2s;
    }

    .info-btn:hover {
      color: var(--primary);
    }

    .info-btn svg {
      width: 20px;
      height: 20px;
    }

    .tooltip-content {
      display: none;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      top: 100%;
      margin-top: 12px;
      width: 320px;
      padding: 20px;
      background: var(--card);
      border: 1px solid var(--border-subtle);
      border-radius: 12px;
      box-shadow: 0 4px 30px rgba(167, 139, 250, 0.15);
      z-index: 100;
      text-align: left;
    }

    .tooltip-content.active {
      display: block;
      animation: fadeIn 0.3s ease-out;
    }

    .tooltip-content h4 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .tooltip-content ul {
      list-style: none;
    }

    .tooltip-content li {
      display: flex;
      gap: 12px;
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 12px;
    }

    .tooltip-content li span:first-child {
      color: var(--primary);
      font-weight: 700;
    }

    .tooltip-close {
      position: absolute;
      top: 12px;
      right: 12px;
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 4px;
    }

    .tooltip-close:hover {
      color: var(--foreground);
    }

    /* Cards Grid */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 32px;
      margin-bottom: 64px;
    }

    @media (max-width: 900px) {
      .cards-grid {
        grid-template-columns: 1fr;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
      }
    }

    /* Card */
    .card {
      position: relative;
      background: var(--card);
      border: 1px solid var(--border-subtle);
      border-radius: 16px;
      padding: 24px;
      text-align: center;
      transition: all 0.3s ease-out;
      box-shadow: 0 4px 30px rgba(167, 139, 250, 0.1);
    }

    .card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 8px 40px rgba(167, 139, 250, 0.25);
    }

    .card.popular {
      border-color: rgba(167, 139, 250, 0.6);
      transform: scale(1.05);
      z-index: 10;
    }

    .card.popular:hover {
      transform: translateY(-8px) scale(1.07);
    }

    .card.premium {
      border-color: rgba(245, 158, 11, 0.4);
    }

    /* Popular Badge */
    .popular-badge {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 16px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      border-radius: 9999px;
      box-shadow: 0 0 20px var(--shadow-glow);
    }

    .popular-badge svg {
      width: 16px;
      height: 16px;
    }

    /* Crown Badge */
    .crown-badge {
      position: absolute;
      top: -16px;
      left: 50%;
      transform: translateX(-50%);
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #F59E0B, #D97706);
      border-radius: 50%;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
    }

    .crown-badge svg {
      width: 24px;
      height: 24px;
      color: var(--background);
    }

    /* Savings Badge */
    .savings-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      padding: 4px 12px;
      background: rgba(16, 185, 129, 0.2);
      color: var(--green);
      font-size: 12px;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .card-content {
      margin-top: 24px;
    }

    .card.popular .card-content,
    .card.premium .card-content {
      margin-top: 32px;
    }

    .card h3 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .card .description {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 24px;
    }

    /* Bids Display */
    .bids-display {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 8px;
    }

    .bids-display svg {
      width: 24px;
      height: 24px;
      color: var(--primary);
    }

    .bids-count {
      font-size: 40px;
      font-weight: 800;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .bids-label {
      font-size: 20px;
      font-weight: 500;
    }

    /* Bonus */
    .bonus {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      color: var(--green);
      font-weight: 600;
      margin-bottom: 24px;
    }

    .bonus svg {
      width: 20px;
      height: 20px;
    }

    /* Divider */
    .divider {
      width: 100%;
      height: 1px;
      background: var(--border-subtle);
      margin-bottom: 24px;
    }

    /* Price */
    .price {
      font-size: 48px;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .price-per-bid {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 24px;
    }

    .price-per-bid span {
      color: var(--primary);
      font-weight: 600;
    }

    /* Buy Button */
    .buy-btn {
      position: relative;
      width: 100%;
      padding: 16px 32px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--background);
      font-size: 18px;
      font-weight: 600;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      overflow: hidden;
      transition: all 0.3s;
      box-shadow: 0 0 20px var(--shadow-glow);
    }

    .buy-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px var(--shadow-glow);
    }

    [data-theme="light"] .buy-btn {
      color: #ffffff;
      font-size: 18px;
      font-weight: 600;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      overflow: hidden;
      transition: all 0.3s;
      box-shadow: 0 0 20px rgba(167, 139, 250, 0.4);
    }

    .buy-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(167, 139, 250, 0.6);
    }

    .buy-btn::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, #C4B5FD, #A78BFA);
      opacity: 0;
      transition: opacity 0.3s;
    }

    .buy-btn:hover::before {
      opacity: 1;
    }

    .buy-btn span {
      position: relative;
      z-index: 1;
    }

    /* Security Note */
    .security-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      font-size: 12px;
      color: var(--muted);
    }

    .security-note svg {
      width: 16px;
      height: 16px;
      color: var(--green);
    }

    /* Footer */
    footer {
      text-align: center;
    }

    footer p {
      font-size: 14px;
      color: var(--muted);
    }

    /* Modal Overlay */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(13, 15, 26, 0.8);
      backdrop-filter: blur(4px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .modal-overlay.active {
      display: flex;
    }

    /* Modal */
    .modal {
      position: relative;
      width: 100%;
      max-width: 400px;
      background: var(--card);
      border: 1px solid var(--border-subtle);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 30px rgba(167, 139, 250, 0.2);
      animation: scaleIn 0.2s ease-out;
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 4px;
    }

    .modal-close:hover {
      color: var(--foreground);
    }

    .modal-icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(167, 139, 250, 0.2);
      border-radius: 50%;
    }

    .modal-icon svg {
      width: 32px;
      height: 32px;
      color: var(--primary);
    }

    .modal-icon.success {
      background: rgba(16, 185, 129, 0.2);
    }

    .modal-icon.success svg {
      color: var(--green);
    }

    .modal h3 {
      font-size: 24px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 4px;
    }

    .modal .modal-subtitle {
      font-size: 14px;
      color: var(--muted);
      text-align: center;
      margin-bottom: 24px;
    }

    .modal-summary {
      background: rgba(20, 24, 39, 0.5);
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 24px;
    }

    .modal-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .modal-summary-row:last-child {
      margin-bottom: 0;
    }

    .modal-summary-row .label {
      color: var(--muted);
    }

    .modal-summary-row .value {
      font-weight: 600;
    }

    .modal-summary-divider {
      height: 1px;
      background: var(--border-subtle);
      margin: 12px 0;
    }

    .modal-summary-total .value {
      font-size: 24px;
      font-weight: 800;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .modal-pay-btn {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: #ffffff;
      font-size: 16px;
      font-weight: 600;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 0 20px var(--shadow-glow);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .modal-pay-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px var(--shadow-glow);
    }

    .modal-pay-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .modal-pay-btn .spinner {
      width: 20px;
      height: 20px;
      border: 2px solid transparent;
      border-top-color: var(--background);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .modal-disclaimer {
      font-size: 12px;
      color: var(--muted);
      text-align: center;
      margin-top: 16px;
    }

    .modal-success {
      text-align: center;
      padding: 32px 0;
    }

    .modal-success p {
      color: var(--muted);
    }

    /* Hide states */
    .hidden {
      display: none !important;
    }
  </style>
</head>
<script>
  // ... (KEEP YOUR EXISTING JAVASCRIPT LOGIC HERE FOR BUY/VERIFY) ...
  // Function to initiate the order
  function buyBidPack(packId) {
    // Show a loading state if you want, or just start the fetch
    fetch('create_bid_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pack_id=' + packId
    })
    .then(response => {
        // This check handles PHP crashes/errors (non-JSON responses)
        if (!response.ok) return response.text().then(t => { throw new Error(t) });
        return response.json();
    })
    .then(data => {
        if (!data.status) {
            alert(data.message);
            return;
        }

        var options = {
            "key": data.razorpay_key,
            "amount": data.amount,
            "currency": "INR",
            "name": "SmartPitchHub",
            "description": data.pack_name,
            "order_id": data.order_id,
            // ✅ This handler runs when payment is successful on Razorpay
            "handler": function (response) {
                verifyPayment(response);
            },
            "theme": {
                "color": "#A78BFA"
            },
            "modal": {
                "ondismiss": function() {
                   console.log('Payment Cancelled');
                }
            }
        };

        var rzp = new Razorpay(options);
        rzp.open();
    })
    .catch(error => {
        console.error("Error:", error);
        alert('Something went wrong. Check console for details.');
    });
}

// Function to verify payment and add bids to database
function verifyPayment(paymentResponse) {
    console.log("Verifying payment...", paymentResponse);

    fetch('verify_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            razorpay_order_id: paymentResponse.razorpay_order_id,
            razorpay_payment_id: paymentResponse.razorpay_payment_id,
            razorpay_signature: paymentResponse.razorpay_signature
        })
    })
    .then(response => {
        // IF the server returns an error (500 or 404), get the text
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.text(); // Get raw text first to check for HTML/PHP errors
    })
    .then(text => {
        try {
            return JSON.parse(text); // Try to parse as JSON
        } catch (e) {
            // If parsing fails, it's likely a PHP Fatal Error (HTML)
            throw new Error("Server Error: " + text);
        }
    })
    .then(data => {
        if (data.status) {
            alert("Success! " + data.new_bids + " bids added.");
            location.reload(); 
        } else {
            alert("Verification Failed: " + data.message);
        }
    })
    .catch(error => {
        console.error('Full Error:', error);
        // This will alert the ACTUAL PHP error on your screen
        alert(error.message);
    });
}
</script>
<body>
  
  <div class="back-btn-container">
      <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-btn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          <span>Back</span>
      </a>
  </div>

  <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
    </svg>
    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="4"/>
      <path d="M12 2v2"/>
      <path d="M12 20v2"/>
      <path d="m4.93 4.93 1.41 1.41"/>
      <path d="m17.66 17.66 1.41 1.41"/>
      <path d="M2 12h2"/>
      <path d="M20 12h2"/>
      <path d="m6.34 17.66-1.41 1.41"/>
      <path d="m19.07 4.93-1.41 1.41"/>
    </svg>
    <span class="light-text"></span>
    <span class="dark-text"></span>
  </button>

  <div class="bg-effects">
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>
  </div>

  <div class="container">
    <header>
      <?php
      // 1. Get User ID from Session
      $investor_id = $_SESSION['user_id'] ?? 0; // Default to 0 if not logged in
      $current_bids = 0;

      if ($investor_id > 0) {
          // 2. Fetch Bids from Database
          $stmt_bids = $conn->prepare("SELECT total_bids FROM investor_bids WHERE investor_id = ?");
          $stmt_bids->bind_param("i", $investor_id);
          $stmt_bids->execute();
          $res_bids = $stmt_bids->get_result();
          
          if ($row_bids = $res_bids->fetch_assoc()) {
              $current_bids = $row_bids['total_bids'];
          }
      }
      ?>

      <div class="wallet-badge">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/>
            <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
            <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
        </svg>
        <span>Bids Balance:</span>
        <span class="balance" id="walletBalance"><?php echo number_format($current_bids); ?></span>
      </div>

      <div class="title-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="8" cy="8" r="6"/>
          <path d="M18.09 10.37A6 6 0 1 1 10.34 18"/>
          <path d="M7 6h1v4"/>
          <path d="m16.71 13.88.7.71-2.82 2.82"/>
        </svg>
        <h1>Buy Bid Tokens</h1>
      </div>

      <p class="subtitle">Purchase bid packs to invest in startup pitches and unlock exclusive opportunities.</p>

      <div class="info-tooltip">
        <button class="info-btn" onclick="toggleTooltip()">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <path d="M12 17h.01"/>
          </svg>
          How bid tokens work
        </button>
        <div class="tooltip-content" id="tooltipContent">
          <button class="tooltip-close" onclick="toggleTooltip()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
          </button>
          <h4>How Bid Tokens Work</h4>
          <ul>
            <li><span>1.</span><span>Purchase bid tokens to participate in startup pitch auctions</span></li>
            <li><span>2.</span><span>Use tokens to place bids on promising startups</span></li>
            <li><span>3.</span><span>Bonus bids are added instantly to your wallet</span></li>
            <li><span>4.</span><span>Tokens never expire and can be used anytime</span></li>
          </ul>
        </div>
      </div>
    </header>

    <?php
    $stmt = $conn->prepare("SELECT * FROM bid_packs WHERE status = 'active' ORDER BY price ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    ?>

    <section class="cards-grid">
      <?php while($row = $result->fetch_assoc()): 
          // 1. Calculate Data
          $total_bids = $row['bid_count'] + $row['bonus_bids'];
          $effective_price = ($total_bids > 0) ? ($row['price'] / $total_bids) : 0;
          
          // 2. Determine Styling based on ID
          $card_class = "card";
          $description = "Great value"; // Default description
          $badge_html = ""; 

          if ($row['id'] == 1) {
              $description = "Best for beginners";
          }
          elseif ($row['id'] == 2) { 
              $card_class .= " popular";
              $description = "Most value for active investors";
              $badge_html = '
              <div class="popular-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
                  <path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>
                </svg>
                Most Popular
              </div>
              <div class="savings-badge">Save 21%</div>';
          } 
          elseif ($row['id'] == 3) {
              $card_class .= " premium";
              $description = "Perfect for serious investors";
              $badge_html = '
              <div class="crown-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/>
                  <path d="M5 21h14"/>
                </svg>
              </div>
              <div class="savings-badge">Save 37%</div>';
          }
      ?>

      <div class="<?php echo $card_class; ?>" data-pack="<?php echo $row['id']; ?>">
        <?php echo $badge_html; ?>
        <div class="card-content">
          <h3><?php echo htmlspecialchars($row['pack_name']); ?></h3>
          <p class="description"><?php echo $description; ?></p>
          
          <div class="bids-display">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
            <span class="bids-count"><?php echo $row['bid_count']; ?></span>
            <span class="bids-label">Bids</span>
          </div>

          <div class="bonus">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/>
              <path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/>
              <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
            </svg>
            +<?php echo $row['bonus_bids']; ?> Bonus Bids
          </div>

          <div class="divider"></div>

          <div class="price">₹<?php echo number_format($row['price'], 0); ?></div>
          
          <p class="price-per-bid">Effective: <span>₹<?php echo number_format($effective_price, 2); ?>/bid</span></p>

          <button class="buy-btn" onclick="buyBidPack(<?php echo $row['id']; ?>)">
            Buy <?php echo htmlspecialchars($row['pack_name']); ?>
          </button>

          <div class="security-note">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
              <path d="m9 12 2 2 4-4"/>
            </svg>
            Payments secured by Razorpay 🔒
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </section>
    
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <footer>
      <p>All transactions are securely processed. Bid tokens never expire.</p>
    </footer>
  </div>

  <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
      <button class="modal-close" onclick="closeModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </button>

      <div id="paymentForm">
        <div class="modal-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <rect width="20" height="14" x="2" y="5" rx="2"/>
            <line x1="2" x2="22" y1="10" y2="10"/>
          </svg>
        </div>
        <h3>Complete Purchase</h3>
        <p class="modal-subtitle">Razorpay Secure Checkout</p>

        <div class="modal-summary">
          <div class="modal-summary-row">
            <span class="label">Pack</span>
            <span class="value" id="modalPackName">Pro Pack</span>
          </div>
          <div class="modal-summary-row">
            <span class="label">Bids</span>
            <span class="value" id="modalBids">250 + 30 bonus</span>
          </div>
          <div class="modal-summary-divider"></div>
          <div class="modal-summary-row modal-summary-total">
            <span class="label">Total</span>
            <span class="value" id="modalPrice">₹199</span>
          </div>
        </div>

        <button class="modal-pay-btn" id="payBtn" onclick="processPayment()">
          <span id="payBtnText">Pay ₹199</span>
          <div class="spinner hidden" id="paySpinner"></div>
        </button>

        <p class="modal-disclaimer">This is a demo. No actual payment will be processed.</p>
      </div>

      <div id="successState" class="modal-success hidden">
        <div class="modal-icon success">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <path d="m9 11 3 3L22 4"/>
          </svg>
        </div>
        <h3>Payment Successful!</h3>
        <p id="successMessage">280 bids have been added to your wallet</p>
      </div>
    </div>
  </div>
<script>
    // Theme handling
    function toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
    }

    // Load saved theme on page load
    (function() {
      const savedTheme = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();

    // Wallet balance (simulated)
    let walletBalance = 1200;

    // Current selected pack
    let currentPack = null;

    // Toggle info tooltip
    function toggleTooltip() {
      const tooltip = document.getElementById('tooltipContent');
      tooltip.classList.toggle('active');
    }

    // Close tooltip when clicking outside
    document.addEventListener('click', function(e) {
      const tooltip = document.getElementById('tooltipContent');
      const infoBtn = document.querySelector('.info-btn');
      if (!tooltip.contains(e.target) && !infoBtn.contains(e.target)) {
        tooltip.classList.remove('active');
      }
    });

    // Open payment modal
    function openModal(packName, price, bids, bonus) {
      currentPack = { packName, price, bids, bonus };
      
      document.getElementById('modalPackName').textContent = packName;
      document.getElementById('modalBids').textContent = `${bids} + ${bonus} bonus`;
      document.getElementById('modalPrice').textContent = `₹${price}`;
      document.getElementById('payBtnText').textContent = `Pay ₹${price}`;
      
      // Reset modal state
      document.getElementById('paymentForm').classList.remove('hidden');
      document.getElementById('successState').classList.add('hidden');
      document.getElementById('payBtn').disabled = false;
      document.getElementById('paySpinner').classList.add('hidden');
      
      document.getElementById('modalOverlay').classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    // Close payment modal
    function closeModal(e) {
      if (e && e.target !== e.currentTarget) return;
      
      document.getElementById('modalOverlay').classList.remove('active');
      document.body.style.overflow = '';
    }

    // Process payment (simulated)
    function processPayment() {
      const payBtn = document.getElementById('payBtn');
      const payBtnText = document.getElementById('payBtnText');
      const paySpinner = document.getElementById('paySpinner');
      
      // Show processing state
      payBtn.disabled = true;
      payBtnText.textContent = 'Processing...';
      paySpinner.classList.remove('hidden');
      
      // Simulate payment processing
      setTimeout(() => {
        // Show success state
        document.getElementById('paymentForm').classList.add('hidden');
        document.getElementById('successState').classList.remove('hidden');
        
        const totalBids = currentPack.bids + currentPack.bonus;
        document.getElementById('successMessage').textContent = `${totalBids} bids have been added to your wallet`;
        
        // Update wallet balance
        walletBalance += totalBids;
        document.getElementById('walletBalance').textContent = `₹${walletBalance.toLocaleString()}`;
        
        // Auto close after 2 seconds
        setTimeout(() => {
          closeModal();
        }, 2000);
      }, 2000);
    }

    // Keyboard support
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
        document.getElementById('tooltipContent').classList.remove('active');
      }
    });
  </script>
</body>
</html>