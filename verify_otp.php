<?php
session_start();
include 'db.php'; // your DB connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enteredOtp = $_POST['otp'];
    $email = $_SESSION['email']; // we stored this during register.php

    // Fetch OTP from otp_verifications
    $stmt = $conn->prepare("SELECT * FROM otp_verifications WHERE email = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['otp'] == $enteredOtp) {
            // OTP matched → Insert into the correct table based on role
            $role = $row['role'];
            
            if ($role === 'entrepreneur') {
                // Insert into entrepreneurs table
                $insert = $conn->prepare("INSERT INTO entrepreneurs (name, email, contact, password, startup_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("sssss", $row['name'], $row['email'], $row['contact'], $row['password'], $row['startup_name']);
            } else {
                // Insert into investors table (role is 'investor')
                $insert = $conn->prepare("INSERT INTO investors (name, email, contact, password, created_at) VALUES (?, ?, ?, ?, NOW())");
                $insert->bind_param("ssss", $row['name'], $row['email'], $row['contact'], $row['password']);
            }
            
            if ($insert->execute()) {
                // Update status in otp_verifications
                $update = $conn->prepare("UPDATE otp_verifications SET status = 'verified' WHERE id = ?");
                $update->bind_param("i", $row['id']);
                $update->execute();

                echo "<script>alert('Registration successful! You can now log in.'); window.location='login.php';</script>";
            } else {
                echo "<script>alert('Error inserting into database.'); window.location='register.php';</script>";
            }
        } else {
            echo "<script>alert('Invalid OTP. Please try again.'); window.location='verify_otp.php';</script>";
        }
    } else {
        echo "<script>alert('No pending OTP found for this email.'); window.location='register.php';</script>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard" style="padding-top: 8rem; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="content-section" style="max-width: 450px; margin: 2rem auto; background: white; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); padding: 2.5rem;">
        <div class="text-center mb-4">
            <div class="otp-icon" style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                <i class="fas fa-shield-alt" style="font-size: 2.5rem; color: white;"></i>
            </div>
            <h2 style="font-size: 1.8rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Verify Your Email</h2>
            <p style="color: #6b7280; margin-bottom: 2rem;">Enter the 6-digit code sent to your email</p>
        </div>

        <form method="POST" action="verify_otp.php" id="otpForm">
            <div class="otp-container" style="margin-bottom: 2rem;">
                <div class="otp-inputs" style="display: flex; gap: 0.75rem; justify-content: center;">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input 
                        type="text" 
                        name="otp_digit_<?php echo $i; ?>" 
                        class="otp-digit" 
                        maxlength="1" 
                        data-index="<?php echo $i; ?>"
                        style="
                            width: 50px;
                            height: 60px;
                            text-align: center;
                            font-size: 1.5rem;
                            font-weight: 600;
                            border: 2px solid #e5e7eb;
                            border-radius: 12px;
                            background: #f8fafc;
                            transition: all 0.3s ease;
                        "
                        oninput="moveToNext(this)"
                        onkeydown="handleOtpKeydown(event, this)"
                    >
                    <?php endfor; ?>
                    <input type="hidden" name="otp" id="fullOtp">
                </div>
            </div>

            <button 
                type="submit" 
                class="btn btn-primary" 
                style="
                    width: 100%;
                    padding: 1rem;
                    font-size: 1.1rem;
                    font-weight: 600;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    color: white;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    margin-bottom: 1.5rem;
                "
                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.4)';"
                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';"
            >
                Verify OTP
            </button>
        </form>

        <div class="text-center">
            <p style="color: #6b7280; margin-bottom: 1rem;">
                Didn't receive the code? 
                <a href="#" style="color: #667eea; text-decoration: none; font-weight: 500;" onclick="resendOtp()">Resend OTP</a>
            </p>
            <p>
                <a href="register.php" style="color: #6b7280; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Registration
                </a>
            </p>
        </div>

        <div class="otp-timer" style="text-align: center; margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
            <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">
                OTP expires in: <span id="otpTimer" style="color: #ef4444; font-weight: 600;">05:00</span>
            </p>
        </div>
    </div>
</div>

<style>
.otp-digit:focus {
    outline: none;
    border-color: #667eea !important;
    background: white !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.otp-digit.filled {
    border-color: #10b981 !important;
    background: #f0fdf4 !important;
}

.otp-digit.error {
    border-color: #ef4444 !important;
    background: #fef2f2 !important;
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.success-animation {
    animation: pulse 0.5s ease-in-out;
}
</style>

<script>
// OTP Input Handling
function moveToNext(input) {
    const value = input.value;
    const index = parseInt(input.getAttribute('data-index'));
    
    if (value.length === 1) {
        // Move to next input if available
        if (index < 6) {
            const nextInput = document.querySelector(`[data-index="${index + 1}"]`);
            if (nextInput) nextInput.focus();
        }
        
        // Update filled class
        input.classList.add('filled');
        input.classList.remove('error');
        
        // Update hidden OTP field
        updateFullOtp();
    } else if (value.length === 0) {
        // Move to previous input if backspace pressed
        if (index > 1) {
            const prevInput = document.querySelector(`[data-index="${index - 1}"]`);
            if (prevInput) prevInput.focus();
        }
        
        input.classList.remove('filled');
        updateFullOtp();
    }
}

function handleOtpKeydown(event, input) {
    const index = parseInt(input.getAttribute('data-index'));
    
    if (event.key === 'Backspace' && input.value === '' && index > 1) {
        event.preventDefault();
        const prevInput = document.querySelector(`[data-index="${index - 1}"]`);
        if (prevInput) {
            prevInput.focus();
            prevInput.value = '';
            prevInput.classList.remove('filled');
            updateFullOtp();
        }
    }
    
    // Allow only numbers
    if (!/^\d$/.test(event.key) && event.key !== 'Backspace' && event.key !== 'Tab' && event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        event.preventDefault();
    }
}

function updateFullOtp() {
    let fullOtp = '';
    for (let i = 1; i <= 6; i++) {
        const digitInput = document.querySelector(`[data-index="${i}"]`);
        if (digitInput) {
            fullOtp += digitInput.value;
        }
    }
    document.getElementById('fullOtp').value = fullOtp;
}

// Form validation
document.getElementById('otpForm').addEventListener('submit', function(e) {
    const fullOtp = document.getElementById('fullOtp').value;
    
    if (fullOtp.length !== 6) {
        e.preventDefault();
        
        // Highlight empty inputs
        const inputs = document.querySelectorAll('.otp-digit');
        inputs.forEach(input => {
            if (!input.value) {
                input.classList.add('error');
            }
        });
        
        // Show error message
        showNotification('Please enter all 6 digits of the OTP', 'error');
    }
});

// OTP Timer
function startOtpTimer() {
    let timeLeft = 300; // 5 minutes in seconds
    const timerElement = document.getElementById('otpTimer');
    
    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerElement.textContent = '00:00';
            timerElement.style.color = '#ef4444';
            
            // Disable form submission
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
            
            showNotification('OTP has expired. Please request a new one.', 'error');
        } else {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            timeLeft--;
        }
    }, 1000);
}

// Resend OTP function
function resendOtp() {
    // This would typically make an AJAX call to resend OTP
    showNotification('New OTP has been sent to your email', 'success');
    
    // Reset timer
    startOtpTimer();
    
    // Clear inputs
    const inputs = document.querySelectorAll('.otp-digit');
    inputs.forEach(input => {
        input.value = '';
        input.classList.remove('filled', 'error');
    });
    document.getElementById('fullOtp').value = '';
    
    // Focus first input
    inputs[0].focus();
}

// Show notification (using existing function from script.js)
function showNotification(message, type) {
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback notification
        alert(message);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Focus first OTP input
    const firstInput = document.querySelector('[data-index="1"]');
    if (firstInput) firstInput.focus();
    
    // Start timer
    startOtpTimer();
    
    // Add paste functionality
    document.addEventListener('paste', function(e) {
        const activeElement = document.activeElement;
        if (activeElement.classList.contains('otp-digit')) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text');
            if (/^\d{6}$/.test(pastedData)) {
                const inputs = document.querySelectorAll('.otp-digit');
                inputs.forEach((input, index) => {
                    input.value = pastedData[index] || '';
                    input.classList.add('filled');
                    input.classList.remove('error');
                });
                updateFullOtp();
                
                // Focus last input
                inputs[5].focus();
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
