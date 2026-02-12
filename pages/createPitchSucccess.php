<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success | SmartPitchHub</title>
    <meta name="description" content="Your pitch has been successfully submitted to SmartPitchHub.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --background: #0D0F1A;
            --background-light: #141829;
            --card-bg: #161A2C;
            --card-border: rgba(167, 139, 250, 0.15);
            --primary: #A78BFA;
            --primary-glow: rgba(167, 139, 250, 0.3);
            --primary-dark: #8B5CF6;
            --text-primary: #FFFFFF;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --success: #34D399;
            --warning: #FBBF24;
            --border: rgba(255, 255, 255, 0.08);
            --radius: 16px;
            --radius-sm: 12px;
            --shadow-card: 0 4px 24px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
            --shadow-glow: 0 0 40px rgba(167, 139, 250, 0.25);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
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

        .bg-effects::before {
            content: '';
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 900px;
            height: 600px;
            background: radial-gradient(ellipse, rgba(167, 139, 250, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .bg-effects::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        /* Main Container */
        .container {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 32px 16px 60px;
            }
        }

        /* Success Hero */
        .hero {
            text-align: center;
            margin-bottom: 48px;
            opacity: 0;
            animation: fadeInUp 0.8s ease forwards;
        }

        .success-icon-wrapper {
            display: inline-flex;
            position: relative;
            margin-bottom: 32px;
        }

        .success-icon {
            width: 120px;
            height: 120px;
            position: relative;
            animation: float 4s ease-in-out infinite;
        }

        .success-icon svg {
            width: 100%;
            height: 100%;
        }

        .success-icon .circle-bg {
            fill: rgba(167, 139, 250, 0.15);
            transform-origin: center;
            animation: circleGrow 0.6s ease forwards;
        }

        .success-icon .circle-border {
            fill: none;
            stroke: var(--primary);
            stroke-width: 2;
            transform-origin: center;
            animation: circleGrow 0.6s ease forwards;
        }

        .success-icon .checkmark {
            fill: none;
            stroke: var(--primary);
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: drawCheck 0.8s ease 0.4s forwards;
        }

        .success-icon-glow {
            position: absolute;
            inset: -20px;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulseGlow 2.5s ease-in-out infinite;
        }

        .hero h1 {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .hero p {
            font-size: clamp(16px, 2.5vw, 18px);
            color: var(--text-secondary);
            max-width: 500px;
            margin: 0 auto;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card Base */
        .card {
            background: linear-gradient(180deg, var(--card-bg) 0%, rgba(22, 26, 44, 0.8) 100%);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow-card);
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }

        .card:nth-child(1) { animation-delay: 0.2s; }
        .card:nth-child(2) { animation-delay: 0.3s; }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }

        /* Status Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.25);
            color: var(--warning);
        }

        .badge-pending svg {
            width: 14px;
            height: 14px;
        }

        /* Detail Rows */
        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .detail-label svg {
            width: 16px;
            height: 16px;
            opacity: 0.6;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .detail-value.success {
            color: var(--success);
        }

        /* Card Footer */
        .card-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .card-footer p {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .card-footer svg {
            width: 14px;
            height: 14px;
            opacity: 0.6;
        }

        /* Timeline Card */
        .timeline-card {
            grid-column: 1 / -1;
            animation-delay: 0.4s;
        }

        .timeline {
            position: relative;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            padding-bottom: 28px;
            position: relative;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .timeline-icon svg {
            width: 20px;
            height: 20px;
        }

        .timeline-icon.current {
            background: rgba(167, 139, 250, 0.15);
            border: 2px solid var(--primary);
            color: var(--primary);
            box-shadow: 0 0 20px rgba(167, 139, 250, 0.3);
            animation: pulseGlow 2s ease-in-out infinite;
        }

        .timeline-icon.upcoming {
            background: var(--background-light);
            border: 1px solid var(--border);
            color: var(--text-muted);
        }

        .timeline-line {
            position: absolute;
            left: 21px;
            top: 48px;
            bottom: 4px;
            width: 2px;
            background: var(--border);
        }

        .timeline-item:last-child .timeline-line {
            display: none;
        }

        .timeline-content {
            padding-top: 10px;
        }

        .timeline-title {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .timeline-title.current {
            color: var(--primary);
        }

        .timeline-title.upcoming {
            color: var(--text-muted);
        }

        .timeline-desc {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            opacity: 0;
            animation: fadeInUp 0.6s ease 0.5s forwards;
        }

        @media (max-width: 480px) {
            .actions {
                flex-direction: column;
                padding: 0 20px;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.25s ease;
        }

        .btn svg {
            width: 18px;
            height: 18px;
            transition: transform 0.25s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #0D0F1A;
            box-shadow: 0 4px 20px rgba(167, 139, 250, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(167, 139, 250, 0.5);
        }

        .btn-primary:hover svg {
            transform: translateX(4px);
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--background-light);
            border-color: rgba(167, 139, 250, 0.3);
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 56px;
            opacity: 0;
            animation: fadeInUp 0.6s ease 0.6s forwards;
        }

        .footer p {
            font-size: 13px;
            color: var(--text-muted);
        }

        .footer .brand {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        @keyframes circleGrow {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes drawCheck {
            to {
                stroke-dashoffset: 0;
            }
        }
    </style>
</head>
<body>
    <div class="bg-effects"></div>
    
    <main class="container">
        <section class="hero">
            <div class="success-icon-wrapper">
                <div class="success-icon-glow"></div>
                <div class="success-icon">
                    <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle class="circle-bg" cx="50" cy="50" r="45"/>
                        <circle class="circle-border" cx="50" cy="50" r="45"/>
                        <path class="checkmark" d="M30 52 L45 67 L72 35"/>
                    </svg>
                </div>
            </div>
            <h1>Pitch Submitted Successfully!</h1>
            <p>Your payment was successful and your pitch has been submitted for admin review.</p>
        </section>

        <div class="cards-grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Payment Details
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Payment Status
                    </span>
                    <span class="detail-value success">Success</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Amount Paid
                    </span>
                    <span class="detail-value" id="amountPaid">—</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                        Payment ID
                    </span>
                    <span class="detail-value" id="paymentId" style="cursor: pointer; color: var(--primary);" title="Click to copy">Loading...</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Date & Time
                    </span>
                    <span class="detail-value" id="paymentDate">Just now</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Payment Method
                    </span>
                    <span class="detail-value">Razorpay</span>
                </div>
                
                <div class="card-footer">
                    <p>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Payments are securely processed by Razorpay
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Pitch Summary
                    </div>
                    <span class="badge badge-pending">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Pending Approval
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Startup Name
                    </span>
                    <span class="detail-value" id="startupName">—</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Funding Goal
                    </span>
                    <span class="detail-value" id="fundingGoal">—</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Valuation
                    </span>
                    <span class="detail-value" id="valuation">—</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Share Price
                    </span>
                    <span class="detail-value" id="sharePrice">—</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Shares Issued
                    </span>
                    <span class="detail-value" id="sharesIssued">—</span>
                </div>
            </div>

            <div class="card timeline-card">
                <div class="card-header">
                    <div class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        What Happens Next?
                    </div>
                </div>
                
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon current">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <div class="timeline-line"></div>
                        <div class="timeline-content">
                            <div class="timeline-title current">Admin Review</div>
                            <div class="timeline-desc">Your pitch is being reviewed by our team</div>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-icon upcoming">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="timeline-line"></div>
                        <div class="timeline-content">
                            <div class="timeline-title upcoming">Document Verification</div>
                            <div class="timeline-desc">We verify your startup documents</div>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-icon upcoming">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="timeline-line"></div>
                        <div class="timeline-content">
                            <div class="timeline-title upcoming">Approval or Feedback</div>
                            <div class="timeline-desc">Get approved or receive improvement suggestions</div>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-icon upcoming">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title upcoming">Pitch Goes Live</div>
                            <div class="timeline-desc">Your pitch becomes visible to investors</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <p>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        You will receive email notifications for status updates
                    </p>
                </div>
            </div>
        </div>

        <div class="actions">
            <a id="warzoneBtn" href="../Pitches/warzone.php" class="btn btn-primary" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); display: none;">
                ENTER AI WARZONE
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </a>
            <a href="../dashboards/Entrepreneur-dashboard.php" class="btn btn-secondary">
                Go to Dashboard
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>
        </div>

        <footer class="footer">
            <p>Powered by <span class="brand">SmartPitchHub</span></p>
        </footer>
    </main>

    <script>
        // --- Populate Data from URL ---
        document.addEventListener('DOMContentLoaded', function() {
            // Get URL parameters
            const params = new URLSearchParams(window.location.search);
            
            // Helper function to set text content safely
            const setText = (id, param) => {
                const el = document.getElementById(id);
                const val = params.get(param);
                if(el && val) el.textContent = val;
            };

            // Map URL params to HTML elements
            setText('paymentId', 'payment_id');
            setText('startupName', 'startup');
            setText('fundingGoal', 'funding');
            setText('valuation', 'valuation');
            setText('sharePrice', 'share_price');
            setText('sharesIssued', 'shares_issued');
            
            // Special case for Amount Paid (2% of funding)
            const fundingStr = params.get('funding');
            if (fundingStr) {
                // Remove non-numeric chars except dot
                const fundingAmount = parseFloat(fundingStr.replace(/[^0-9.]/g, ''));
                if (!isNaN(fundingAmount)) {
                    const fee = fundingAmount * 0.02;
                    const feeStr = "₹" + fee.toLocaleString('en-IN');
                    const amountEl = document.getElementById('amountPaid');
                    if (amountEl) amountEl.textContent = feeStr;
                }
            }

            // Set current Date/Time
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            document.getElementById('paymentDate').textContent = now.toLocaleDateString('en-IN', options);

            // --- Warzone Integration ---
            const pitchId = params.get('pitch_id');
            const warzoneBtn = document.getElementById('warzoneBtn');
            if (pitchId && warzoneBtn) {
                warzoneBtn.href = `../Pitches/warzone.php?pitch_id=${pitchId}`;
                warzoneBtn.style.display = 'inline-flex';
            }
        });

        // Copy Payment ID functionality
        document.getElementById('paymentId').addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                this.style.color = '#34D399';
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.color = ''; // Reset to default
                }, 1500);
            });
        });
        
        document.getElementById('paymentId').style.cursor = 'pointer';
        document.getElementById('paymentId').title = 'Click to copy';
    </script>
</body>
</html>