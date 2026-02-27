<?php
session_start();
require_once '../db.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pitch_id = $_GET['pitch_id'] ?? null;
$valuation_id = $_GET['valuation_id'] ?? null;

if (!$pitch_id) {
    die("Error: No Pitch ID provided for Warzone entry.");
}

// Fetch Pitch Data to "Initialize" the AI
$sql = "SELECT startup_name, industry, funding_goal FROM pitches WHERE id = ? AND entrepreneur_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $pitch_id, $user_id);
$stmt->execute();
$pitch = $stmt->get_result()->fetch_assoc();

if (!$pitch) {
    die("Error: Pitch not found or access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI WARZONE | SmartPitchHub</title>
    <link rel="stylesheet" href="../css/warzone.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="warzone-bg"></div>
    <div class="grid-overlay"></div>
    <div class="scanner-line"></div>

    <div class="warzone-container">
        <!-- HUD HEADER -->
        <header class="hud-header">
            <div class="logo-area">
                <div style="font-size: 24px; font-weight: 800; letter-spacing: -1px;">
                    <span style="color: var(--glow-purple);">AI</span> WARZONE
                </div>
                <div style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; margin-top: 4px;">Combat Audit v2.0.4</div>
            </div>

            <div class="status-module">
                <div class="stat-item">
                    <div class="stat-label">Subject</div>
                    <div class="stat-value"><?php echo htmlspecialchars($pitch['startup_name']); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Threat Level</div>
                    <div class="stat-value" id="threatLevel" style="color: #f59e0b;">ANALYZING...</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Survival Probability</div>
                    <div class="stat-value" id="survivalChance">100%</div>
                    <div class="survival-bar-container">
                        <div class="survival-fill" id="survivalBar"></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- COMBAT AREA -->
        <main class="combat-area">
            <div class="terminal-header">
                <div class="terminal-dot dot-red"></div>
                <div class="terminal-dot dot-yellow"></div>
                <div class="terminal-dot dot-green"></div>
                <span style="margin-left: 10px;">ROOT@WARZONE:~# session_init --subject=<?php echo urlencode($pitch['startup_name']); ?></span>
            </div>

            <div class="chat-log" id="chatLog">
                <!-- Messages will appear here -->
            </div>

            <div class="typing" id="typingIndicator">AUDIT COMMANDER IS CALCULATING...</div>

            <div class="input-area">
                <span class="command-prefix">DEFENSE></span>
                <input type="text" id="userInput" class="command-input" placeholder="Enter your response..." autocomplete="off">
                <button id="sendBtn" class="btn-send">Execute</button>
            </div>
        </main>
    </div>

    <script>
        const pitchId = <?php echo (int)$pitch_id; ?>;
        const chatLog = document.getElementById('chatLog');
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const survivalBar = document.getElementById('survivalBar');
        const survivalChance = document.getElementById('survivalChance');
        const threatLevel = document.getElementById('threatLevel');

        let isLocked = false;

        async function addMessage(text, role) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${role}-message`;
            
            if (role === 'ai') {
                const nameSpan = document.createElement('span');
                nameSpan.className = 'ai-name';
                nameSpan.textContent = 'Audit Commander [V-100]';
                msgDiv.appendChild(nameSpan);
            }

            const contentDiv = document.createElement('div');
            contentDiv.textContent = text;
            msgDiv.appendChild(contentDiv);
            
            chatLog.appendChild(msgDiv);
            chatLog.scrollTop = chatLog.scrollHeight;
        }

        async function processAIResponse(input = '') {
            if (isLocked) return;
            isLocked = true;
            
            typingIndicator.style.display = 'block';
            
            try {
                const response = await fetch('api_warzone_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pitch_id: pitchId,
                        message: input
                    })
                });

                const data = await response.json();
                
                typingIndicator.style.display = 'none';
                
                    if (data.success) {
                        if (data.ai_detected) {
                            addMessage("🚨 [COUNTER-AI SCAN]: Automated response detected. Penalties applied. Defense efficacy reduced.", 'ai');
                        }
                        await addMessage(data.reply, 'ai');
                    
                    // Update HUD
                    if (data.score !== undefined) {
                        survivalBar.style.width = data.score + '%';
                        survivalChance.textContent = data.score + '%';
                        
                        // Update Threat level color
                        if (data.score > 80) {
                            threatLevel.textContent = 'LOW';
                            threatLevel.style.color = '#10b981';
                        } else if (data.score > 50) {
                            threatLevel.textContent = 'MEDIUM';
                            threatLevel.style.color = '#f59e0b';
                        } else {
                            threatLevel.textContent = 'CRITICAL';
                            threatLevel.style.color = '#ef4444';
                        }
                    }

                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 3000);
                    }
                } else {
                    addMessage("SIGNAL LOST: " + (data.error || "Unknown interference"), 'ai');
                }
            } catch (err) {
                console.error(err);
                typingIndicator.style.display = 'none';
                addMessage("CONNECTION TERMINATED: AI Core unreachable.", 'ai');
            } finally {
                isLocked = false;
            }
        }

        sendBtn.addEventListener('click', () => {
            const text = userInput.value.trim();
            if (text && !isLocked) {
                addMessage(text, 'user');
                userInput.value = '';
                processAIResponse(text);
            }
        });

        userInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendBtn.click();
        });

        // Initial Greeting
        window.onload = () => {
            setTimeout(() => {
                processAIResponse(); // Initialize first question
            }, 1000);
        };
    </script>
</body>
</html>
