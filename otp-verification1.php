<?php
session_start();
include 'db.php';


// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && false) {


// Ensure pending registration exists
if (!isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Registration session missing.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email or OTP missing.']);
    exit;
}

// Fetch latest pending OTP
$stmt = $conn->prepare("SELECT otp, status FROM otp_verifications WHERE email=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No OTP found for this email.']);
    exit;
}

$row = $result->fetch_assoc();

// Check OTP status
if ($row['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'OTP already used or expired.']);
    exit;
}

// Validate OTP
if ($row['otp'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP.']);
    exit;
}

// ✅ OTP correct → mark verified
$stmt = $conn->prepare("UPDATE otp_verifications SET status='verified' WHERE email=? AND otp=?");
$stmt->bind_param("ss", $email, $otp); // both strings!
$stmt->execute();

// Insert user into correct table
$reg = $_SESSION['pending_registration'];

if ($reg['role'] === 'entrepreneur') {
    $stmt = $conn->prepare("INSERT INTO entrepreneurs (name,email,contact,password,startup_name) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $reg['name'],$reg['email'],$reg['contact'],$reg['password'],$reg['startup_name']);
} else {
    $stmt = $conn->prepare("INSERT INTO investors (name,email,contact,password) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $reg['name'],$reg['email'],$reg['contact'],$reg['password']);
}

if ($stmt->execute()) {
    unset($_SESSION['pending_registration']);
    echo json_encode(['success'=>true,'message'=>'Email verified successfully! Please login.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$stmt->error]);
}

exit;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OTP Verification - SmartPitchHub</title>
    <meta
      name="description"
      content="Enter the 6-digit verification code sent to your email to complete account verification."
    />

    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        /* Color System - HSL Format */
        --primary: 262 83% 58%;
        --primary-light: 266 85% 78%;
        --primary-glow: 262 83% 68%;
        --secondary: 220 14% 96%;
        --accent: 262 83% 58%;

        /* Background Colors */
        --bg-primary: 222 47% 11%;
        --bg-secondary: 217 33% 17%;
        --bg-surface: 215 28% 17%;

        /* Text Colors */
        --text-primary: 210 40% 98%;
        --text-secondary: 215 20% 65%;
        --text-muted: 215 16% 47%;

        /* Border Colors */
        --border-primary: 215 28% 17%;
        --border-error: 0 84% 60%;

        /* Semantic Colors */
        --error: 0 84% 60%;
        --success: 142 76% 36%;

        /* Gradients */
        --gradient-brand: linear-gradient(
          135deg,
          hsl(var(--primary)),
          hsl(316 73% 52%)
        );
        --gradient-glow: radial-gradient(
          600px circle at 50% 300px,
          hsl(var(--primary) / 0.15),
          transparent 40%
        );

        /* Shadows */
        --shadow-glow: 0 0 40px hsl(var(--primary-glow) / 0.4);
        --shadow-elevated: 0 20px 25px -5px hsl(0 0% 0% / 0.3),
          0 10px 10px -5px hsl(0 0% 0% / 0.1);

        /* Transitions */
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          sans-serif;
        background: hsl(var(--bg-primary));
        color: hsl(var(--text-primary));
        min-height: 100vh;
        overflow-x: hidden;
      }

      .floating-element {
        position: absolute;
        border-radius: 50%;
        background: hsl(var(--primary) / 0.2);
        filter: blur(40px);
        animation: float 6s ease-in-out infinite;
        pointer-events: none;
      }

      .floating-1 {
        width: 128px;
        height: 128px;
        top: 40px;
        right: 40px;
      }

      .floating-2 {
        width: 192px;
        height: 192px;
        bottom: 80px;
        left: 80px;
        animation-delay: 2s;
      }

      .floating-3 {
        width: 96px;
        height: 96px;
        top: 33%;
        right: 25%;
        animation-delay: 4s;
      }

      .gradient-overlay {
        position: absolute;
        inset: 0;
        background: var(--gradient-glow);
        opacity: 0.3;
        pointer-events: none;
      }

      .header {
        position: relative;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px;
      }

      .back-button {
        display: flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        border: none;
        color: hsl(var(--text-secondary));
        cursor: pointer;
        transition: var(--transition-smooth);
        text-decoration: none;
        font-size: 14px;
      }

      .back-button:hover {
        color: hsl(var(--text-primary));
      }

      .brand {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .brand-icon {
        width: 32px;
        height: 32px;
        background: var(--gradient-brand);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-glow);
      }

      .brand-name {
        font-size: 20px;
        font-weight: bold;
        background: var(--gradient-brand);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .main-content {
        position: relative;
        z-index: 10;
        max-width: 512px;
        margin: 0 auto;
        padding: 48px 24px;
      }

      .header-section {
        text-align: center;
        margin-bottom: 32px;
        animation: fadeIn 0.6s ease-out;
      }

      .hero-icon {
        width: 80px;
        height: 80px;
        background: var(--gradient-brand);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        box-shadow: var(--shadow-glow);
        animation: glowPulse 2s ease-in-out infinite;
      }

      .hero-title {
        font-size: 32px;
        font-weight: bold;
        color: hsl(var(--text-primary));
        margin-bottom: 16px;
      }

      .hero-subtitle {
        color: hsl(var(--text-secondary));
        margin-bottom: 8px;
      }

      .email-display {
        color: hsl(var(--primary));
        font-weight: 600;
      }

      .otp-card {
        background: hsl(var(--bg-secondary) / 0.5);
        backdrop-filter: blur(12px);
        border: 1px solid hsl(var(--border-primary));
        border-radius: 16px;
        padding: 32px;
        box-shadow: var(--shadow-elevated);
        transition: var(--transition-smooth);
        animation: slideUp 0.6s ease-out;
        margin-bottom: 32px;
      }

      .otp-card:hover {
        box-shadow: var(--shadow-glow);
      }

      .otp-inputs {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 24px;
      }

      .otp-input {
        width: 48px;
        height: 56px;
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        background: hsl(var(--bg-primary));
        border: 2px solid hsl(var(--border-primary));
        border-radius: 8px;
        color: hsl(var(--text-primary));
        transition: var(--transition-smooth);
      }

      .otp-input:focus {
        outline: none;
        border-color: hsl(var(--primary));
        box-shadow: 0 0 0 2px hsl(var(--primary) / 0.2);
        transform: scale(1.05);
      }

      .otp-input:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      .timer-section {
        text-align: center;
        margin-bottom: 16px;
      }

      .timer-text {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: hsl(var(--text-secondary));
        font-size: 14px;
        margin-bottom: 8px;
      }

      .timer-expired {
        color: hsl(var(--error));
      }

      .attempts-text {
        font-size: 12px;
        color: hsl(var(--text-muted));
      }

      .verify-button {
        width: 100%;
        height: 48px;
        background: var(--gradient-brand);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        box-shadow: var(--shadow-glow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
      }

      .verify-button:hover:not(:disabled) {
        opacity: 0.9;
        transform: scale(1.02);
        box-shadow: var(--shadow-elevated);
      }

      .verify-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
      }

      .loading-spinner {
        display: none;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 8px;
      }

      .verify-button.loading .loading-spinner {
        display: inline-block;
      }

      .resend-section {
        text-align: center;
      }

      .resend-text {
        font-size: 14px;
        color: hsl(var(--text-muted));
        margin-bottom: 12px;
      }

      .resend-button {
        background: transparent;
        border: none;
        color: hsl(var(--primary));
        font-size: 14px;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }

      .resend-button:hover:not(:disabled) {
        color: hsl(var(--primary-light));
      }

      .resend-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      .resend-spinner {
        display: none;
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      .resend-button.loading .resend-spinner {
        display: inline-block;
      }

      .help-section {
        background: hsl(var(--bg-secondary) / 0.3);
        backdrop-filter: blur(12px);
        border: 1px solid hsl(var(--border-primary) / 0.5);
        border-radius: 12px;
        padding: 24px;
        animation: scaleIn 0.4s ease-out 0.4s both;
        margin-bottom: 24px;
      }

      .help-header {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
      }

      .help-title {
        font-size: 16px;
        font-weight: 600;
        color: hsl(var(--text-primary));
      }

      .help-list {
        font-size: 14px;
        color: hsl(var(--text-secondary));
        list-style: none;
        padding: 0;
      }

      .help-list li {
        margin-bottom: 4px;
      }

      .demo-info {
        text-align: center;
        padding: 16px;
        background: hsl(var(--primary) / 0.1);
        border: 1px solid hsl(var(--primary) / 0.2);
        border-radius: 8px;
      }

      .demo-text {
        font-size: 14px;
        color: hsl(var(--text-secondary));
      }

      .demo-code {
        background: hsl(var(--bg-primary));
        padding: 4px 8px;
        border-radius: 4px;
        color: hsl(var(--primary));
        font-family: monospace;
        margin: 0 4px;
      }

      .toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background: hsl(var(--bg-secondary));
        border: 1px solid hsl(var(--border-primary));
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: var(--shadow-elevated);
        color: hsl(var(--text-primary));
        font-size: 14px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 1000;
        max-width: 400px;
      }

      .toast.show {
        transform: translateX(0);
      }

      .toast.success {
        border-color: hsl(var(--success));
        background: hsl(var(--success) / 0.1);
      }

      .toast.error {
        border-color: hsl(var(--error));
        background: hsl(var(--error) / 0.1);
      }

      /* Animations */
      @keyframes float {
        0%,
        100% {
          transform: translateY(0px);
        }
        50% {
          transform: translateY(-10px);
        }
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
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

      @keyframes glowPulse {
        0%,
        100% {
          box-shadow: var(--shadow-glow);
        }
        50% {
          box-shadow: 0 0 60px hsl(var(--primary-glow) / 0.6);
        }
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }

      /* Responsive */
      @media (max-width: 640px) {
        .main-content {
          padding: 24px 16px;
        }

        .otp-card {
          padding: 24px;
        }

        .hero-title {
          font-size: 28px;
        }

        .otp-inputs {
          gap: 8px;
        }

        .otp-input {
          width: 40px;
          height: 48px;
          font-size: 18px;
        }

        .floating-2,
        .floating-3 {
          display: none;
        }
      }
    </style>
  </head>
  <body>
    <!-- Animated background elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <!-- Background gradient overlay -->
    <div class="gradient-overlay"></div>

    <!-- Header -->
    <header class="header">
      <a href="register.php" class="back-button">
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="m12 19-7-7 7-7"></path>
          <path d="M19 12H5"></path>
        </svg>
        Back
      </a>

      <div class="brand">
        <div class="brand-icon">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="white"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M9 12l2 2 4-4"></path>
            <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
            <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
          </svg>
        </div>
        <span class="brand-name">SmartPitchHub</span>
      </div>
    </header>

    <!-- Main content -->
    <main class="main-content">
      <!-- Header section -->
      <div class="header-section">
        <div class="hero-icon">
          <svg
            width="40"
            height="40"
            viewBox="0 0 24 24"
            fill="none"
            stroke="white"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M9 12l2 2 4-4"></path>
            <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
            <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
          </svg>
        </div>

        <h1 class="hero-title">Enter Verification Code</h1>
        <p class="hero-subtitle">We've sent a 6-digit verification code to</p>
        <p class="email-display" id="emailDisplay">your email</p>
      </div>

      <!-- OTP Input Card -->
      <div class="otp-card">
        <!-- OTP Input Fields -->
        <div class="otp-inputs">
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
          <input
            type="text"
            class="otp-input"
            maxlength="1"
            inputmode="numeric"
            pattern="[0-9]*"
          />
        </div>

        <!-- Timer and attempts info -->
        <div class="timer-section">
          <div class="timer-text" id="timerText">
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12,6 12,12 16,14"></polyline>
            </svg>
            <span id="timeLeft">Code expires in 5:00</span>
          </div>

          <div class="attempts-text" id="attemptsText"></div>
        </div>

        <!-- Verify Button -->
        <button class="verify-button" id="verifyButton">
          <div class="loading-spinner"></div>
          <span id="verifyButtonText">Verify Code</span>
        </button>

        <!-- Resend Section -->
        <div class="resend-section">
          <p class="resend-text">Didn't receive the code?</p>

          <button class="resend-button" id="resendButton">
            <div class="resend-spinner"></div>
            <span id="resendButtonText">Resend Code</span>
          </button>
        </div>
      </div>

      <!-- Help Section -->
      <div class="help-section">
        <div class="help-header">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="hsl(var(--primary))"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22,4 12,14.01 9,11.01"></polyline>
          </svg>
          <div>
            <h3 class="help-title">Having trouble?</h3>
            <ul class="help-list">
              <li>• Check your spam/junk folder</li>
              <li>• Make sure you entered the correct email</li>
              <li>• Wait a moment and try resending the code</li>
              <li>• Contact support if problems persist</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Demo info -->
      <div class="demo-info">
        <p class="demo-text">
          <strong>Demo:</strong> Use code
          <code class="demo-code">123456</code> to proceed
        </p>
      </div>
    </main>
 <script>
let timeLeft = 300; // 5 minutes
let canResend = false;
let attempts = 0;
const maxAttempts = 3;
let timer;

// DOM elements
const otpInputs = document.querySelectorAll(".otp-input");
const emailDisplay = document.getElementById("emailDisplay");
const timerText = document.getElementById("timerText");
const timeLeftSpan = document.getElementById("timeLeft");
const attemptsText = document.getElementById("attemptsText");
const verifyButton = document.getElementById("verifyButton");
const verifyButtonText = document.getElementById("verifyButtonText");
const resendButton = document.getElementById("resendButton");
const resendButtonText = document.getElementById("resendButtonText");

// Initialize page
function init() {
    const email = localStorage.getItem("verificationEmail");
    if (!email) {
        alert("Registration session missing. Please register first.");
        window.location.href = "register.php";
        return;
    }
    emailDisplay.textContent = email;

    startTimer();
    setupOtpInputs();
    verifyButton.addEventListener("click", handleVerify);
    resendButton.addEventListener("click", handleResend);

    otpInputs[0].focus();
}

// Timer functions
function startTimer() {
    timer = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timer);
            canResend = true;
            updateResendButton();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    const timeString = `${mins}:${secs.toString().padStart(2, "0")}`;

    if (timeLeft > 0) {
        timeLeftSpan.textContent = `Code expires in ${timeString}`;
        timerText.classList.remove("timer-expired");
    } else {
        timeLeftSpan.textContent = "Verification code has expired";
        timerText.classList.add("timer-expired");
    }
}

function updateResendButton() {
    resendButton.disabled = !canResend;
}

function updateAttemptsDisplay() {
    if (attempts > 0) {
        attemptsText.textContent = `${maxAttempts - attempts} attempts remaining`;
    } else {
        attemptsText.textContent = "";
    }
}

// OTP input handling
function setupOtpInputs() {
    otpInputs.forEach((input, index) => {
        input.addEventListener("input", (e) => handleOtpInput(e, index));
        input.addEventListener("keydown", (e) => handleKeyDown(e, index));
        input.addEventListener("paste", handlePaste);
    });
}

function handleOtpInput(e, index) {
    const value = e.target.value;
    if (value.length > 1) e.target.value = value.slice(0, 1);
    if (!/^\d$/.test(value) && value !== "") e.target.value = "";

    if (value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
    if (index === otpInputs.length - 1 && getOtpValue().length === 6) {
        setTimeout(handleVerify, 100);
    }
}

function handleKeyDown(e, index) {
    if (e.key === "Backspace" && !e.target.value && index > 0) {
        otpInputs[index - 1].focus();
    }
}

function handlePaste(e) {
    e.preventDefault();
    const pasted = e.clipboardData.getData("text").slice(0, 6);
    for (let i = 0; i < pasted.length && i < otpInputs.length; i++) {
        if (/^\d$/.test(pasted[i])) otpInputs[i].value = pasted[i];
    }
    otpInputs[Math.min(pasted.length, otpInputs.length - 1)].focus();
    if (pasted.length === 6) setTimeout(handleVerify, 100);
}

function getOtpValue() {
    return Array.from(otpInputs).map(input => input.value).join("");
}

function clearOtpInputs() {
    otpInputs.forEach(input => input.value = "");
    otpInputs[0].focus();
}

// Verification
async function handleVerify() {
    const otp = getOtpValue();
    const email = localStorage.getItem("verificationEmail");

    if (!email) {
        alert("Registration session missing. Please register first.");
        window.location.href = "register.php";
        return;
    }

    if (otp.length !== 6) {
        showToast("Please enter the complete 6-digit verification code.", "error");
        return;
    }

    if (attempts >= maxAttempts) {
        showToast("Too many attempts. Please register again.", "error");
        return;
    }

    setVerifyLoading(true);

    try {
      console.log("hgerhulrjbhrejipghrjgi[jr")
        const formData = new FormData();
        formData.append("email", email);
        formData.append("otp", otp);

        const res = await fetch("otpVerify.php", {
            method: "POST",
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            localStorage.removeItem("verificationEmail");
            setTimeout(() => window.location.href = "login.php", 1000);
        } else {
            attempts++;
            updateAttemptsDisplay();
            clearOtpInputs();
            showToast(data.message, "error");
        }
    } catch (error) {
        console.error(error);
        showToast("Verification failed. Please try again.", "error");
    } finally {
        setVerifyLoading(false);
    }
}

// Resend OTP
async function handleResend() {
    if (!canResend) return;
    setResendLoading(true);
    const email = localStorage.getItem("verificationEmail");
    if (!email) {
        alert("Registration session missing. Please register first.");
        window.location.href = "register.php";
        return;
    }

    try {
        const formData = new FormData();
        formData.append("email", email);
        formData.append("resend", "true");

        const res = await fetch("otpVerify.php", {
            method: "POST",
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            timeLeft = 300;
            canResend = false;
            attempts = 0;
            clearOtpInputs();
            updateAttemptsDisplay();
            updateResendButton();
            clearInterval(timer);
            startTimer();
            showToast(data.message, "success");
        } else {
            showToast(data.message, "error");
        }
    } catch (error) {
        console.error(error);
        showToast("Failed to resend OTP. Please try again.", "error");
    } finally {
        setResendLoading(false);
    }
}

// Loading states
function setVerifyLoading(loading) {
    verifyButton.disabled = loading;
    verifyButton.classList.toggle("loading", loading);
    verifyButtonText.textContent = loading ? "Verifying..." : "Verify Code";
    otpInputs.forEach(input => input.disabled = loading);
}

function setResendLoading(loading) {
    resendButton.disabled = loading || !canResend;
    resendButton.classList.toggle("loading", loading);
    resendButtonText.textContent = loading ? "Resending..." : "Resend Code";
}

// Toast messages
function showToast(msg, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 100);
    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 5000);
}

document.addEventListener("DOMContentLoaded", init);
</script>
  </body>
</html>
