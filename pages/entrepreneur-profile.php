<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrepreneur Profile - Account Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* CSS Variables - Theme System */
    :root {
      /* Light theme */
      --background: hsl(0, 0%, 98%);
      --foreground: hsl(224, 71%, 10%);
      --card: hsl(0, 0%, 100%);
      --card-foreground: hsl(224, 71%, 10%);
      --primary: hsl(263, 70%, 50%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(215, 25%, 92%);
      --secondary-foreground: hsl(215, 28%, 17%);
      --muted: hsl(215, 25%, 95%);
      --muted-foreground: hsl(215, 20%, 45%);
      --accent: hsl(250, 80%, 60%);
      --border: hsl(215, 25%, 88%);
      --destructive: hsl(0, 72%, 51%);
      --success: hsl(142, 76%, 36%);
      --warning: hsl(38, 92%, 50%);
      --info: hsl(199, 89%, 48%);
      --radius: 0.75rem;
      --glow-primary: hsla(263, 70%, 50%, 0.3);
      --glow-success: hsla(142, 76%, 36%, 0.3);
    }

    .dark {
      --background: hsl(224, 71%, 4%);
      --foreground: hsl(213, 31%, 91%);
      --card: hsl(224, 71%, 6%);
      --card-foreground: hsl(213, 31%, 91%);
      --primary: hsl(263, 70%, 58%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(215, 28%, 17%);
      --secondary-foreground: hsl(213, 31%, 91%);
      --muted: hsl(215, 28%, 12%);
      --muted-foreground: hsl(215, 20%, 55%);
      --accent: hsl(250, 80%, 65%);
      --border: hsl(215, 28%, 17%);
      --destructive: hsl(0, 72%, 51%);
      --success: hsl(142, 76%, 45%);
      --warning: hsl(38, 92%, 50%);
      --info: hsl(199, 89%, 48%);
      --glow-primary: hsla(263, 70%, 58%, 0.3);
      --glow-success: hsla(142, 76%, 45%, 0.3);
    }

    /* Reset & Base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--background);
      color: var(--foreground);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
    }

    /* Layout */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }

    .header-gradient {
      position: fixed;
      inset: 0;
      height: 18rem;
      background: linear-gradient(to bottom, var(--glow-primary), transparent);
      pointer-events: none;
      z-index: 0;
    }

    /* Page Header */
    .page-header {
      margin-bottom: 2rem;
      position: relative;
      z-index: 1;
    }

    .page-header h1 {
      font-size: 1.875rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .page-header p {
      color: var(--muted-foreground);
    }

    /* Card Component */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -2px rgba(0, 0, 0, 0.2);
    }

    .card-glow {
      box-shadow: 0 0 0 1px var(--primary), 0 0 20px -5px var(--glow-primary), 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    }

    .card-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border);
    }

    .card-header::before {
      content: '';
      width: 0.25rem;
      height: 1.25rem;
      background: var(--primary);
      border-radius: 9999px;
      box-shadow: 0 0 10px var(--glow-primary);
    }

    .card-header h2 {
      font-size: 1.125rem;
      font-weight: 600;
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: var(--radius);
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      text-decoration: none;
    }

    .btn-primary {
      background: var(--primary);
      color: var(--primary-foreground);
    }

    .btn-primary:hover {
      opacity: 0.9;
      box-shadow: 0 0 30px -5px var(--glow-primary);
    }

    .btn-ghost {
      background: transparent;
      color: var(--muted-foreground);
      border: 1px solid transparent;
    }

    .btn-ghost:hover {
      background: var(--secondary);
      color: var(--foreground);
    }

    .btn-outline {
      background: transparent;
      color: var(--foreground);
      border: 1px solid var(--border);
    }

    .btn-outline:hover {
      background: var(--secondary);
    }

    .btn-destructive {
      background: var(--destructive);
      color: white;
    }

    .btn-icon {
      width: 2.25rem;
      height: 2.25rem;
      padding: 0;
      border-radius: 50%;
    }

    /* Profile Header Card */
    .profile-header {
      position: relative;
      z-index: 1;
    }

    .profile-header .decorative-glow-1 {
      position: absolute;
      top: -5rem;
      right: -5rem;
      width: 10rem;
      height: 10rem;
      background: var(--glow-primary);
      border-radius: 50%;
      filter: blur(48px);
      pointer-events: none;
    }

    .profile-header .decorative-glow-2 {
      position: absolute;
      bottom: -2.5rem;
      left: -2.5rem;
      width: 8rem;
      height: 8rem;
      background: hsla(250, 80%, 65%, 0.1);
      border-radius: 50%;
      filter: blur(32px);
      pointer-events: none;
    }

    .profile-top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      position: relative;
    }

    .profile-content {
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      align-items: flex-start;
      position: relative;
    }

    .avatar-container {
      position: relative;
    }

    .avatar {
      width: 7rem;
      height: 7rem;
      border-radius: 50%;
      background: hsla(263, 70%, 58%, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--primary);
      box-shadow: 0 0 0 3px var(--background), 0 0 0 5px var(--glow-primary), 0 0 20px -5px var(--glow-primary);
    }

    .avatar img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }

    .avatar-edit-btn {
      position: absolute;
      bottom: -0.5rem;
      right: -0.5rem;
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 50%;
      background: var(--primary);
      color: var(--primary-foreground);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s;
      cursor: pointer;
      border: none;
    }

    .avatar-container:hover .avatar-edit-btn {
      opacity: 1;
    }

    .profile-info {
      flex: 1;
      min-width: 200px;
    }

    .profile-name-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
    }

    .profile-name-row h1 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .profile-details {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .profile-detail-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--muted-foreground);
      font-size: 0.875rem;
    }

    .profile-detail-item svg {
      width: 1rem;
      height: 1rem;
    }

    .role-badge-container {
      display: none;
    }

    @media (min-width: 768px) {
      .role-badge-container {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
      }
    }

    .role-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      background: hsla(263, 70%, 58%, 0.1);
      border: 1px solid hsla(263, 70%, 58%, 0.2);
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--primary);
    }

    /* Status Badge */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-success {
      background: hsla(142, 76%, 45%, 0.2);
      color: var(--success);
      border: 1px solid hsla(142, 76%, 45%, 0.3);
      box-shadow: 0 0 10px -2px var(--glow-success);
    }

    .badge-warning {
      background: hsla(38, 92%, 50%, 0.2);
      color: var(--warning);
      border: 1px solid hsla(38, 92%, 50%, 0.3);
    }

    .badge-destructive {
      background: hsla(0, 72%, 51%, 0.2);
      color: var(--destructive);
      border: 1px solid hsla(0, 72%, 51%, 0.3);
    }

    .badge-primary {
      background: hsla(263, 70%, 58%, 0.2);
      color: var(--primary);
      border: 1px solid hsla(263, 70%, 58%, 0.3);
    }

    .badge-info {
      background: hsla(199, 89%, 48%, 0.2);
      color: var(--info);
      border: 1px solid hsla(199, 89%, 48%, 0.3);
    }

    .pulse-dot {
      width: 0.375rem;
      height: 0.375rem;
      background: currentColor;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .verified-shimmer {
      background: linear-gradient(90deg, var(--success) 0%, hsl(142, 76%, 55%) 50%, var(--success) 100%);
      background-size: 200% auto;
      animation: shimmer 3s linear infinite;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-weight: 600;
      font-size: 0.75rem;
    }

    @keyframes shimmer {
      0% { background-position: -200% center; }
      100% { background-position: 200% center; }
    }

    /* Grid Layout */
    .grid-2 {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }

    @media (min-width: 1024px) {
      .grid-2 {
        grid-template-columns: 1fr 1fr;
      }
    }

    .space-y-6 > * + * {
      margin-top: 1.5rem;
    }

    /* Form Elements */
    .form-field {
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--muted-foreground);
      margin-bottom: 0.5rem;
    }

    .form-input {
      width: 100%;
      padding: 0.5rem 0.75rem;
      background: var(--muted);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 0.875rem;
      color: var(--foreground);
      transition: all 0.2s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px hsla(263, 70%, 58%, 0.3), 0 0 15px -5px var(--glow-primary);
    }

    .form-input[readonly] {
      background: var(--muted);
      color: var(--muted-foreground);
      cursor: not-allowed;
    }

    /* Editable Field */
    .editable-field {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem;
      border-radius: var(--radius);
      transition: background 0.2s;
    }

    .editable-field:hover {
      background: var(--muted);
    }

    .editable-field-label {
      font-size: 0.75rem;
      color: var(--muted-foreground);
      margin-bottom: 0.25rem;
    }

    .editable-field-value {
      font-size: 0.875rem;
      color: var(--foreground);
    }

    .editable-field-readonly .editable-field-value {
      color: var(--muted-foreground);
    }

    .edit-btn {
      padding: 0.25rem;
      background: transparent;
      border: none;
      color: var(--muted-foreground);
      cursor: pointer;
      border-radius: 0.25rem;
      transition: all 0.2s;
    }

    .edit-btn:hover {
      color: var(--primary);
      background: hsla(263, 70%, 58%, 0.1);
    }

    /* Pitch Cards */
    .pitch-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }

    .pitch-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      transition: all 0.2s;
    }

    .pitch-card:hover {
      border-color: var(--primary);
      box-shadow: 0 0 15px -3px var(--glow-primary);
    }

    .pitch-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .pitch-title {
      font-weight: 600;
      font-size: 1rem;
      margin-bottom: 0.25rem;
    }

    .pitch-updated {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .pitch-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .pitch-stat {
      text-align: center;
      padding: 0.5rem;
      background: var(--muted);
      border-radius: 0.5rem;
    }

    .pitch-stat-value {
      font-weight: 600;
      font-size: 1rem;
    }

    .pitch-stat-label {
      font-size: 0.625rem;
      color: var(--muted-foreground);
      text-transform: uppercase;
    }

    .pitch-actions {
      display: flex;
      gap: 0.5rem;
    }

    .pitch-actions .btn {
      flex: 1;
      font-size: 0.75rem;
      padding: 0.375rem 0.75rem;
    }

    /* Score Circle */
    .score-circle-container {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .score-circle {
      position: relative;
      width: 80px;
      height: 80px;
    }

    .score-circle svg {
      transform: rotate(-90deg);
    }

    .score-circle-bg {
      fill: none;
      stroke: var(--muted);
      stroke-width: 8;
    }

    .score-circle-progress {
      fill: none;
      stroke: var(--primary);
      stroke-width: 8;
      stroke-linecap: round;
      transition: stroke-dashoffset 1s ease;
    }

    .score-circle-text {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--foreground);
    }

    /* Session Item */
    .session-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem;
      border-radius: var(--radius);
      background: var(--muted);
      margin-bottom: 0.5rem;
    }

    .session-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .session-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.5rem;
      background: var(--secondary);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .session-device {
      font-size: 0.875rem;
      font-weight: 500;
    }

    .session-meta {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    /* Toggle Switch */
    .toggle {
      position: relative;
      width: 44px;
      height: 24px;
      background: var(--muted);
      border-radius: 9999px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .toggle.active {
      background: var(--primary);
    }

    .toggle::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      transition: transform 0.2s;
    }

    .toggle.active::after {
      transform: translateX(20px);
    }

    /* Notification Preference */
    .notification-pref {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      border-radius: var(--radius);
      background: var(--muted);
      margin-bottom: 0.75rem;
    }

    .notification-pref-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .notification-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.5rem;
      background: hsla(263, 70%, 58%, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
    }

    .notification-title {
      font-weight: 500;
      font-size: 0.875rem;
    }

    .notification-desc {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .notification-toggles {
      display: flex;
      gap: 1rem;
    }

    .toggle-group {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.25rem;
    }

    .toggle-label {
      font-size: 0.625rem;
      color: var(--muted-foreground);
      text-transform: uppercase;
    }

    /* Timeline */
    .timeline {
      position: relative;
      padding-left: 2rem;
    }

    .timeline::before {
      content: '';
      position: absolute;
      left: 1rem;
      top: 0.5rem;
      bottom: 0;
      width: 1px;
      background: linear-gradient(to bottom, var(--primary), var(--border), transparent);
    }

    .timeline-item {
      position: relative;
      padding-bottom: 1.5rem;
    }

    .timeline-dot {
      position: absolute;
      left: -1.5rem;
      top: 0.25rem;
      width: 0.75rem;
      height: 0.75rem;
      border-radius: 50%;
      background: var(--primary);
      border: 2px solid var(--background);
    }

    .timeline-content {
      background: var(--muted);
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
    }

    .timeline-title {
      font-weight: 500;
      font-size: 0.875rem;
      margin-bottom: 0.25rem;
    }

    .timeline-desc {
      font-size: 0.75rem;
      color: var(--muted-foreground);
      margin-bottom: 0.25rem;
    }

    .timeline-time {
      font-size: 0.625rem;
      color: var(--muted-foreground);
    }

    /* Danger Zone */
    .danger-zone {
      border: 1px solid hsla(0, 72%, 51%, 0.3);
      background: hsla(0, 72%, 51%, 0.05);
      box-shadow: inset 0 0 30px -10px hsla(0, 72%, 51%, 0.1);
    }

    .danger-zone .card-header::before {
      background: var(--destructive);
      box-shadow: 0 0 10px hsla(0, 72%, 51%, 0.5);
    }

    .danger-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      background: hsla(0, 72%, 51%, 0.05);
      border-radius: var(--radius);
      margin-bottom: 0.75rem;
    }

    .danger-item:last-child {
      margin-bottom: 0;
    }

    .danger-title {
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .danger-desc {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    /* AI Insights */
    .insight-item {
      display: flex;
      gap: 0.75rem;
      padding: 0.75rem;
      border-radius: var(--radius);
      margin-bottom: 0.5rem;
    }

    .insight-success {
      background: hsla(142, 76%, 45%, 0.1);
      border-left: 3px solid var(--success);
    }

    .insight-tip {
      background: hsla(199, 89%, 48%, 0.1);
      border-left: 3px solid var(--info);
    }

    .insight-warning {
      background: hsla(38, 92%, 50%, 0.1);
      border-left: 3px solid var(--warning);
    }

    .insight-icon {
      flex-shrink: 0;
    }

    .insight-title {
      font-weight: 500;
      font-size: 0.875rem;
      margin-bottom: 0.25rem;
    }

    .insight-desc {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    /* KYC Documents */
    .kyc-doc {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem;
      background: var(--muted);
      border-radius: var(--radius);
      margin-bottom: 0.5rem;
    }

    .kyc-doc-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .kyc-doc-icon {
      width: 2rem;
      height: 2rem;
      border-radius: 0.5rem;
      background: var(--secondary);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .kyc-doc-name {
      font-size: 0.875rem;
      font-weight: 500;
    }

    .kyc-doc-date {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    /* Score Cards */
    .score-cards {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .score-card {
      text-align: center;
      padding: 1rem;
      background: var(--muted);
      border-radius: var(--radius);
    }

    .score-card-label {
      font-size: 0.75rem;
      color: var(--muted-foreground);
      margin-bottom: 0.5rem;
    }

    /* Icons (SVG inline) */
    .icon {
      width: 1rem;
      height: 1rem;
      display: inline-block;
      vertical-align: middle;
    }

    .icon-lg {
      width: 1.25rem;
      height: 1.25rem;
    }

    /* Utilities */
    .flex {
      display: flex;
    }

    .items-center {
      align-items: center;
    }

    .gap-2 {
      gap: 0.5rem;
    }

    .gap-4 {
      gap: 1rem;
    }

    .mt-4 {
      margin-top: 1rem;
    }

    .text-sm {
      font-size: 0.875rem;
    }

    .text-xs {
      font-size: 0.75rem;
    }
  </style>
</head>
<body>
  <div class="header-gradient"></div>
  
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <h1>Account Settings</h1>
      <p>Manage your profile, startup, and security settings</p>
    </div>

    <!-- Profile Header Card -->
    <div class="card card-glow profile-header" style="margin-bottom: 1.5rem;">
      <div class="decorative-glow-1"></div>
      <div class="decorative-glow-2"></div>
      
      <div class="profile-top-bar">
        <button class="btn btn-ghost" onclick="goBack()">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 19-7-7 7-7"/>
            <path d="M19 12H5"/>
          </svg>
          Back
        </button>
        
        <button class="btn btn-outline btn-icon" id="themeToggle" onclick="toggleTheme()">
          <svg class="icon sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
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
          <svg class="icon moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
          </svg>
        </button>
      </div>
      
      <div class="profile-content">
        <div class="avatar-container">
          <div class="avatar">AC</div>
          <button class="avatar-edit-btn" onclick="showToast('Photo upload coming soon')">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
              <circle cx="12" cy="13" r="3"/>
            </svg>
          </button>
        </div>
        
        <div class="profile-info">
          <div class="profile-name-row">
            <h1>Alexandra Chen</h1>
            <span class="badge badge-success">
              <span class="pulse-dot"></span>
              Active
            </span>
          </div>
          
          <div class="profile-details">
            <div class="profile-detail-item">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
              </svg>
              <span>alex.chen@nexusfin.io</span>
              <span class="verified-shimmer">Verified</span>
            </div>
            
            <div class="profile-detail-item">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
              <span>+1 (555) 123-4567</span>
            </div>
            
            <div class="profile-detail-item">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
              <span class="text-sm">Last login: Today at 2:34 PM</span>
            </div>
          </div>
        </div>
        
        <div class="role-badge-container">
          <div class="role-badge">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <span>Entrepreneur</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Basic Profile & Startup Info -->
    <div class="grid-2">
      <!-- Basic Profile -->
      <div class="card">
        <div class="card-header">
          <h2>Basic Profile</h2>
        </div>
        
        <div class="editable-field">
          <div>
            <div class="editable-field-label">Display Name</div>
            <div class="editable-field-value">Alexandra Chen</div>
          </div>
          <button class="edit-btn" onclick="showToast('Edit display name')">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
          </button>
        </div>
        
        <div class="editable-field">
          <div>
            <div class="editable-field-label">Phone Number</div>
            <div class="editable-field-value">+1 (555) 123-4567</div>
          </div>
          <button class="edit-btn" onclick="showToast('Edit phone number')">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
          </button>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Email Address</div>
            <div class="editable-field-value">alex.chen@nexusfin.io</div>
          </div>
          <span class="badge badge-success" style="font-size: 0.625rem;">Verified</span>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Role</div>
            <div class="editable-field-value">Entrepreneur</div>
          </div>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Registration Date</div>
            <div class="editable-field-value">January 15, 2024</div>
          </div>
        </div>
      </div>
      
      <!-- Startup Information -->
      <div class="card card-glow">
        <div class="card-header">
          <h2>Startup Information</h2>
        </div>
        
        <div class="editable-field">
          <div>
            <div class="editable-field-label">Startup Name</div>
            <div class="editable-field-value">NexusFin</div>
          </div>
          <button class="edit-btn" onclick="showToast('Edit startup name')">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
          </button>
        </div>
        
        <div class="editable-field">
          <div>
            <div class="editable-field-label">Industry</div>
            <div class="editable-field-value">FinTech</div>
          </div>
          <button class="edit-btn" onclick="showToast('Edit industry')">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
          </button>
        </div>
        
        <div class="editable-field">
          <div>
            <div class="editable-field-label">Business Stage</div>
            <div class="editable-field-value">MVP</div>
          </div>
          <span class="badge badge-primary">MVP</span>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Legal Entity</div>
            <div class="editable-field-value">Delaware C-Corp</div>
          </div>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Registration Number</div>
            <div class="editable-field-value">DE-2024-88432</div>
          </div>
        </div>
        
        <div class="editable-field editable-field-readonly">
          <div>
            <div class="editable-field-label">Verification Status</div>
            <div class="editable-field-value">Verified</div>
          </div>
          <span class="badge badge-success">Verified</span>
        </div>
      </div>
    </div>

    <!-- Pitch Management -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <div class="card-header">
        <h2>Pitch Management</h2>
      </div>
      
      <div class="pitch-grid">
        <div class="pitch-card">
          <div class="pitch-header">
            <div>
              <div class="pitch-title">NexusFin Series A</div>
              <div class="pitch-updated">Updated 2 days ago</div>
            </div>
            <span class="badge badge-success">Approved</span>
          </div>
          
          <div class="pitch-stats">
            <div class="pitch-stat">
              <div class="pitch-stat-value">1,247</div>
              <div class="pitch-stat-label">Views</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">34</div>
              <div class="pitch-stat-label">Investors</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">$2.5M</div>
              <div class="pitch-stat-label">Goal</div>
            </div>
          </div>
          
          <div class="pitch-actions">
            <button class="btn btn-outline" onclick="showToast('Viewing pitch')">View</button>
          </div>
        </div>
        
        <div class="pitch-card">
          <div class="pitch-header">
            <div>
              <div class="pitch-title">NexusFin Seed Extension</div>
              <div class="pitch-updated">Updated 5 hours ago</div>
            </div>
            <span class="badge badge-warning">Under Review</span>
          </div>
          
          <div class="pitch-stats">
            <div class="pitch-stat">
              <div class="pitch-stat-value">89</div>
              <div class="pitch-stat-label">Views</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">5</div>
              <div class="pitch-stat-label">Investors</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">$500K</div>
              <div class="pitch-stat-label">Goal</div>
            </div>
          </div>
          
          <div class="pitch-actions">
            <button class="btn btn-outline" onclick="showToast('Viewing pitch')">View</button>
          </div>
        </div>
        
        <div class="pitch-card">
          <div class="pitch-header">
            <div>
              <div class="pitch-title">Product Demo Pitch</div>
              <div class="pitch-updated">Updated 1 week ago</div>
            </div>
            <span class="badge badge-info">Draft</span>
          </div>
          
          <div class="pitch-stats">
            <div class="pitch-stat">
              <div class="pitch-stat-value">0</div>
              <div class="pitch-stat-label">Views</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">0</div>
              <div class="pitch-stat-label">Investors</div>
            </div>
            <div class="pitch-stat">
              <div class="pitch-stat-value">$1M</div>
              <div class="pitch-stat-label">Goal</div>
            </div>
          </div>
          
          <div class="pitch-actions">
            <button class="btn btn-primary" onclick="showToast('Editing pitch')">Edit</button>
            <button class="btn btn-outline" onclick="showToast('Viewing pitch')">View</button>
          </div>
        </div>
      </div>
    </div>

    <!-- KYC & AI Insights -->
    <div class="grid-2">
      <!-- KYC Section -->
      <div class="card">
        <div class="card-header">
          <h2>KYC & Verification</h2>
        </div>
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
          <div>
            <div class="text-sm" style="color: var(--muted-foreground);">Status</div>
            <span class="badge badge-success" style="margin-top: 0.25rem;">Verified</span>
          </div>
          <div class="score-circle-container">
            <div class="score-circle">
              <svg width="80" height="80" viewBox="0 0 80 80">
                <circle class="score-circle-bg" cx="40" cy="40" r="32"/>
                <circle class="score-circle-progress" cx="40" cy="40" r="32" 
                  stroke-dasharray="201" 
                  stroke-dashoffset="12"/>
              </svg>
              <div class="score-circle-text">94</div>
            </div>
          </div>
        </div>
        
        <div class="text-sm" style="margin-bottom: 0.5rem; font-weight: 500;">Submitted Documents</div>
        
        <div class="kyc-doc">
          <div class="kyc-doc-info">
            <div class="kyc-doc-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div>
              <div class="kyc-doc-name">Government ID</div>
              <div class="kyc-doc-date">Jan 15, 2024</div>
            </div>
          </div>
          <span class="badge badge-success">Verified</span>
        </div>
        
        <div class="kyc-doc">
          <div class="kyc-doc-info">
            <div class="kyc-doc-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div>
              <div class="kyc-doc-name">Proof of Address</div>
              <div class="kyc-doc-date">Jan 15, 2024</div>
            </div>
          </div>
          <span class="badge badge-success">Verified</span>
        </div>
        
        <div class="kyc-doc">
          <div class="kyc-doc-info">
            <div class="kyc-doc-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div>
              <div class="kyc-doc-name">Business Registration</div>
              <div class="kyc-doc-date">Jan 16, 2024</div>
            </div>
          </div>
          <span class="badge badge-success">Verified</span>
        </div>
      </div>
      
      <!-- AI Insights -->
      <div class="card">
        <div class="card-header">
          <h2>AI-Powered Insights</h2>
        </div>
        
        <div class="score-cards">
          <div class="score-card">
            <div class="score-card-label">Profile Score</div>
            <div class="score-circle-container">
              <div class="score-circle">
                <svg width="80" height="80" viewBox="0 0 80 80">
                  <circle class="score-circle-bg" cx="40" cy="40" r="32"/>
                  <circle class="score-circle-progress" cx="40" cy="40" r="32" 
                    stroke-dasharray="201" 
                    stroke-dashoffset="26"/>
                </svg>
                <div class="score-circle-text">87</div>
              </div>
            </div>
          </div>
          <div class="score-card">
            <div class="score-card-label">Pitch Readiness</div>
            <div class="score-circle-container">
              <div class="score-circle">
                <svg width="80" height="80" viewBox="0 0 80 80">
                  <circle class="score-circle-bg" cx="40" cy="40" r="32"/>
                  <circle class="score-circle-progress" cx="40" cy="40" r="32" 
                    stroke-dasharray="201" 
                    stroke-dashoffset="56"/>
                </svg>
                <div class="score-circle-text">72</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="insight-item insight-success">
          <div class="insight-icon">
            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--success);">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>
          <div>
            <div class="insight-title">Strong Profile Verification</div>
            <div class="insight-desc">Your KYC verification is complete and your AI trust score is above average.</div>
          </div>
        </div>
        
        <div class="insight-item insight-tip">
          <div class="insight-icon">
            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--info);">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 16v-4"/>
              <path d="M12 8h.01"/>
            </svg>
          </div>
          <div>
            <div class="insight-title">Add Team Members</div>
            <div class="insight-desc">Investors prefer pitches that showcase the founding team. Consider adding your co-founders.</div>
          </div>
        </div>
        
        <div class="insight-item insight-warning">
          <div class="insight-icon">
            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--warning);">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
              <path d="M12 9v4"/>
              <path d="M12 17h.01"/>
            </svg>
          </div>
          <div>
            <div class="insight-title">Financial Projections Missing</div>
            <div class="insight-desc">Your Series A pitch doesn't include 3-year financial projections. This is highly recommended.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Security & Notifications -->
    <div class="grid-2">
      <!-- Security Settings -->
      <div class="card">
        <div class="card-header">
          <h2>Security & Account</h2>
        </div>
        
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
          <button class="btn btn-primary" onclick="showToast('Change password flow coming soon')">Change Password</button>
          <button class="btn btn-outline" onclick="showToast('Password reset email sent')">Forgot Password</button>
        </div>
        
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--muted); border-radius: var(--radius); margin-bottom: 1rem;">
          <div>
            <div class="text-sm" style="font-weight: 500;">Two-Factor Authentication</div>
            <div class="text-xs" style="color: var(--muted-foreground);">Add an extra layer of security</div>
          </div>
          <div class="toggle" id="2faToggle" onclick="toggle2FA(this)"></div>
        </div>
        
        <div class="text-sm" style="font-weight: 500; margin-bottom: 0.5rem;">Active Sessions</div>
        
        <div class="session-item">
          <div class="session-info">
            <div class="session-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
              </svg>
            </div>
            <div>
              <div class="session-device">MacBook Pro - Chrome</div>
              <div class="session-meta">San Francisco, CA • Now</div>
            </div>
          </div>
          <span class="badge badge-success">Current</span>
        </div>
        
        <div class="session-item">
          <div class="session-info">
            <div class="session-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <line x1="12" y1="18" x2="12.01" y2="18"/>
              </svg>
            </div>
            <div>
              <div class="session-device">iPhone 15 Pro - Safari</div>
              <div class="session-meta">San Francisco, CA • 2 hours ago</div>
            </div>
          </div>
          <button class="btn btn-ghost" style="font-size: 0.75rem;" onclick="showToast('Logged out session')">Logout</button>
        </div>
        
        <div class="session-item">
          <div class="session-info">
            <div class="session-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
              </svg>
            </div>
            <div>
              <div class="session-device">Windows PC - Firefox</div>
              <div class="session-meta">New York, NY • 3 days ago</div>
            </div>
          </div>
          <button class="btn btn-ghost" style="font-size: 0.75rem;" onclick="showToast('Logged out session')">Logout</button>
        </div>
        
        <button class="btn btn-destructive" style="width: 100%; margin-top: 1rem;" onclick="showToast('Logged out of all devices')">Logout All Devices</button>
      </div>
      
      <!-- Notification Settings -->
      <div class="card">
        <div class="card-header">
          <h2>Notification Preferences</h2>
        </div>
        
        <div class="notification-pref">
          <div class="notification-pref-info">
            <div class="notification-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
                <path d="m9 15 2 2 4-4"/>
              </svg>
            </div>
            <div>
              <div class="notification-title">Pitch Status Updates</div>
              <div class="notification-desc">Get notified when your pitch status changes</div>
            </div>
          </div>
          <div class="notification-toggles">
            <div class="toggle-group">
              <div class="toggle active" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">Email</div>
            </div>
            <div class="toggle-group">
              <div class="toggle active" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">In-App</div>
            </div>
          </div>
        </div>
        
        <div class="notification-pref">
          <div class="notification-pref-info">
            <div class="notification-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
            </div>
            <div>
              <div class="notification-title">Investor Messages</div>
              <div class="notification-desc">Receive notifications for new investor inquiries</div>
            </div>
          </div>
          <div class="notification-toggles">
            <div class="toggle-group">
              <div class="toggle active" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">Email</div>
            </div>
            <div class="toggle-group">
              <div class="toggle active" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">In-App</div>
            </div>
          </div>
        </div>
        
        <div class="notification-pref">
          <div class="notification-pref-info">
            <div class="notification-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m3 11 18-5v12L3 14v-3z"/>
                <path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>
              </svg>
            </div>
            <div>
              <div class="notification-title">Platform Announcements</div>
              <div class="notification-desc">Important updates and new features</div>
            </div>
          </div>
          <div class="notification-toggles">
            <div class="toggle-group">
              <div class="toggle" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">Email</div>
            </div>
            <div class="toggle-group">
              <div class="toggle active" onclick="this.classList.toggle('active')"></div>
              <div class="toggle-label">In-App</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity History -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <div class="card-header">
        <h2>Activity & Audit History</h2>
      </div>
      
      <div class="timeline">
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">Logged in from new device</div>
            <div class="timeline-desc">MacBook Pro - Chrome browser detected</div>
            <div class="timeline-time">Today, 2:34 PM • IP: 192.168.1.***</div>
          </div>
        </div>
        
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">Pitch status updated</div>
            <div class="timeline-desc">NexusFin Series A was approved by the review team</div>
            <div class="timeline-time">2 days ago</div>
          </div>
        </div>
        
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">Profile photo updated</div>
            <div class="timeline-desc">You changed your profile picture</div>
            <div class="timeline-time">1 week ago</div>
          </div>
        </div>
        
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">Password changed</div>
            <div class="timeline-desc">Your account password was successfully updated</div>
            <div class="timeline-time">2 weeks ago</div>
          </div>
        </div>
        
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">KYC verification completed</div>
            <div class="timeline-desc">All documents verified successfully</div>
            <div class="timeline-time">Jan 16, 2024</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="card danger-zone">
      <div class="card-header">
        <h2>Danger Zone</h2>
      </div>
      
      <div class="danger-item">
        <div>
          <div class="danger-title">Deactivate Account</div>
          <div class="danger-desc">Temporarily disable your account. You can reactivate anytime.</div>
        </div>
        <button class="btn btn-outline" onclick="showToast('Account deactivation initiated')">Deactivate</button>
      </div>
      
      <div class="danger-item">
        <div>
          <div class="danger-title">Delete Account</div>
          <div class="danger-desc">Permanently delete your account and all associated data. This action cannot be undone.</div>
        </div>
        <button class="btn btn-destructive" onclick="showToast('Account deletion flow started')">Delete Account</button>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toastContainer" style="position: fixed; bottom: 1rem; right: 1rem; z-index: 9999;"></div>

  <script>
    // Theme Toggle
    function toggleTheme() {
      const html = document.documentElement;
      const sunIcon = document.querySelector('.sun-icon');
      const moonIcon = document.querySelector('.moon-icon');
      
      if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'block';
      } else {
        html.classList.add('dark');
        sunIcon.style.display = 'block';
        moonIcon.style.display = 'none';
      }
    }

    // Initialize theme icon
    document.addEventListener('DOMContentLoaded', function() {
      const isDark = document.documentElement.classList.contains('dark');
      const sunIcon = document.querySelector('.sun-icon');
      const moonIcon = document.querySelector('.moon-icon');
      
      if (isDark) {
        sunIcon.style.display = 'block';
        moonIcon.style.display = 'none';
      } else {
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'block';
      }
    });

    // Back Button
    function goBack() {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        showToast('No previous page');
      }
    }

    // 2FA Toggle
    function toggle2FA(element) {
      element.classList.toggle('active');
      const isEnabled = element.classList.contains('active');
      showToast(isEnabled ? '2FA Enabled' : '2FA Disabled');
    }

    // Toast Notification
    function showToast(message) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.style.cssText = `
        background: var(--card);
        border: 1px solid var(--border);
        color: var(--foreground);
        padding: 0.75rem 1rem;
        border-radius: var(--radius);
        margin-top: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
        font-size: 0.875rem;
      `;
      toast.textContent = message;
      container.appendChild(toast);
      
      setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>