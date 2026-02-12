<?php
session_start();
// Adjust the path to your db.php file if necessary
if (file_exists('../db.php')) {
    require_once '../db.php';
} else {
    require_once '../../db.php'; // Fallback path
}

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];
$investor_email = "";
$investor_contact = "";

// 2. Fetch Data from Database
// We select 'email' and 'contact' from the 'investors' table
$sql = "SELECT email, contact FROM investors WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($investor_email, $investor_contact);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Investor KYC Verification | SmartPitchHub</title>
  <meta name="description" content="Complete your KYC verification to start investing securely on SmartPitchHub.">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
  <style>
    /* ========================================
       CSS RESET & BASE STYLES
    ======================================== */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --background: #0D0F1A;
      --card: #111827;
      --card-hover: #1a2234;
      --foreground: #ffffff;
      --primary: #A78BFA;
      --primary-dark: #8B5CF6;
      --primary-glow: rgba(167, 139, 250, 0.3);
      --secondary: #1F2937;
      --muted: #9CA3AF;
      --border: #374151;
      --destructive: #EF4444;
      --success: #22C55E;
      --warning: #F59E0B;
      --radius: 16px;
      --radius-sm: 12px;
      --shadow-card: 0 4px 24px rgba(0, 0, 0, 0.3);
      --shadow-glow: 0 0 30px rgba(167, 139, 250, 0.15);
      --shadow-glow-hover: 0 0 40px rgba(167, 139, 250, 0.25);
      --gradient-primary: linear-gradient(135deg, #A78BFA 0%, #8B5CF6 100%);
      --transition: all 0.3s ease;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.6;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    /* ========================================
       LAYOUT
    ======================================== */
    .container {
      max-width: 768px;
      margin: 0 auto;
      padding: 32px 16px;
    }

    @media (min-width: 640px) {
      .container {
        padding: 48px 24px;
      }
    }

    /* ========================================
       HEADER
    ======================================== */
    .header {
      text-align: center;
      margin-bottom: 48px;
      animation: fadeIn 0.5s ease-out;
    }

    .header-icon {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .icon-box {
      width: 48px;
      height: 48px;
      border-radius: var(--radius-sm);
      background: rgba(167, 139, 250, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-box svg {
      width: 24px;
      height: 24px;
      color: var(--primary);
    }

    .header-text {
      text-align: left;
    }

    .header h1 {
      font-size: 1.875rem;
      font-weight: 700;
      color: var(--foreground);
      margin-bottom: 4px;
    }

    @media (min-width: 768px) {
      .header h1 {
        font-size: 2.25rem;
      }
    }

    .header p {
      color: var(--muted);
      font-size: 0.95rem;
    }

    .status-row {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      margin-bottom: 32px;
    }

    @media (min-width: 640px) {
      .status-row {
        flex-direction: row;
        justify-content: center;
      }
    }

    /* ========================================
       STATUS BADGES
    ======================================== */
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 6px 16px;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .status-badge .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-right: 8px;
    }

    .status-not-started {
      background: var(--secondary);
      color: var(--muted);
    }
    .status-not-started .dot { background: var(--muted); }

    .status-in-progress {
      background: rgba(245, 158, 11, 0.2);
      color: var(--warning);
    }
    .status-in-progress .dot { background: var(--warning); }

    .status-under-review {
      background: rgba(167, 139, 250, 0.2);
      color: var(--primary);
    }
    .status-under-review .dot { background: var(--primary); }

    .status-approved {
      background: rgba(34, 197, 94, 0.2);
      color: var(--success);
    }
    .status-approved .dot { background: var(--success); }

    .status-rejected {
      background: rgba(239, 68, 68, 0.2);
      color: var(--destructive);
    }
    .status-rejected .dot { background: var(--destructive); }

    .progress-text {
      color: var(--muted);
      font-size: 0.875rem;
    }

    /* ========================================
       PROGRESS BAR
    ======================================== */
    .progress-container {
      max-width: 576px;
      margin: 0 auto;
    }

    .progress-bar {
      height: 8px;
      background: var(--secondary);
      border-radius: 9999px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: var(--gradient-primary);
      border-radius: 9999px;
      transition: width 0.5s ease-out;
      box-shadow: 0 0 20px rgba(167, 139, 250, 0.4);
    }

    /* ========================================
       CARDS
    ======================================== */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 24px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow-card);
      transition: var(--transition);
      animation: slideUp 0.5s ease-out forwards;
      opacity: 0;
    }

    @media (min-width: 768px) {
      .card {
        padding: 32px;
      }
    }

    .card:hover {
      border-color: rgba(167, 139, 250, 0.3);
      box-shadow: var(--shadow-glow-hover);
    }

    .card.disabled {
      opacity: 0.6;
      pointer-events: none;
    }

    .card-header {
      display: flex;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 24px;
    }

    .section-number {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(167, 139, 250, 0.2);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .section-number.complete {
      background: rgba(34, 197, 94, 0.2);
      color: var(--success);
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--foreground);
      margin-bottom: 4px;
    }

    .card-description {
      font-size: 0.875rem;
      color: var(--muted);
    }

    /* ========================================
       FORM ELEMENTS
    ======================================== */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    @media (min-width: 768px) {
      .form-grid.two-cols {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--muted);
    }

    .form-label .required {
      color: var(--destructive);
      margin-left: 4px;
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      background: var(--secondary);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      color: var(--foreground);
      font-family: inherit;
      font-size: 0.95rem;
      transition: var(--transition);
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.2);
    }

    .form-input:disabled,
    .form-select:disabled,
    .form-textarea:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      background: rgba(31, 41, 55, 0.5);
    }

    .form-input::placeholder,
    .form-textarea::placeholder {
      color: var(--muted);
    }

    .form-select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239CA3AF'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 16px center;
      background-size: 20px;
      padding-right: 48px;
    }

    .form-textarea {
      resize: none;
      min-height: 100px;
    }

    .form-error {
      font-size: 0.8rem;
      color: var(--destructive);
    }

    .form-helper {
      font-size: 0.75rem;
      color: var(--muted);
    }

    /* ========================================
       CHECKBOXES
    ======================================== */
    .checkbox-group {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .checkbox-label {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      cursor: pointer;
    }

    .checkbox-label:hover .checkbox-text {
      color: var(--foreground);
    }

    .checkbox-input {
      width: 20px;
      height: 20px;
      border: 2px solid var(--border);
      border-radius: 4px;
      background: var(--secondary);
      cursor: pointer;
      appearance: none;
      position: relative;
      flex-shrink: 0;
      margin-top: 2px;
      transition: var(--transition);
    }

    .checkbox-input:checked {
      background: var(--primary);
      border-color: var(--primary);
    }

    .checkbox-input:checked::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 6px;
      width: 5px;
      height: 10px;
      border: solid var(--background);
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    .checkbox-text {
      font-size: 0.875rem;
      color: var(--muted);
      transition: var(--transition);
      line-height: 1.5;
    }

    /* ========================================
       RADIO BUTTONS
    ======================================== */
    .radio-group {
      display: flex;
      gap: 24px;
    }

    .radio-label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }

    .radio-input {
      width: 16px;
      height: 16px;
      border: 2px solid var(--border);
      border-radius: 50%;
      background: var(--secondary);
      cursor: pointer;
      appearance: none;
      position: relative;
      transition: var(--transition);
    }

    .radio-input:checked {
      border-color: var(--primary);
    }

    .radio-input:checked::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--primary);
    }

    .radio-text {
      font-size: 0.875rem;
      color: var(--foreground);
    }

    /* ========================================
       FILE UPLOAD
    ======================================== */
    .file-upload {
      border: 2px dashed var(--border);
      border-radius: var(--radius-sm);
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: var(--transition);
    }

    .file-upload:hover {
      border-color: rgba(167, 139, 250, 0.5);
      background: rgba(167, 139, 250, 0.05);
    }

    .file-upload.drag-over {
      border-color: var(--primary);
      background: rgba(167, 139, 250, 0.1);
    }

    .file-upload-icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: rgba(167, 139, 250, 0.1);
      margin: 0 auto 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .file-upload-icon svg {
      width: 24px;
      height: 24px;
      color: var(--primary);
    }

    .file-upload-text {
      font-size: 0.875rem;
      color: var(--foreground);
      font-weight: 500;
    }

    .file-upload-text span {
      color: var(--primary);
    }

    .file-upload-hint {
      font-size: 0.75rem;
      color: var(--muted);
      margin-top: 4px;
    }

    .file-upload input[type="file"] {
      display: none;
    }

    .file-preview {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px;
    }

    .file-preview-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .file-preview-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: rgba(167, 139, 250, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .file-preview-icon svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
    }

    .file-preview-name {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground);
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .file-preview-size {
      font-size: 0.75rem;
      color: var(--muted);
    }

    .file-remove {
      background: none;
      border: none;
      padding: 8px;
      cursor: pointer;
      border-radius: 8px;
      transition: var(--transition);
    }

    .file-remove:hover {
      background: rgba(239, 68, 68, 0.2);
    }

    .file-remove svg {
      width: 20px;
      height: 20px;
      color: var(--muted);
    }

    .file-remove:hover svg {
      color: var(--destructive);
    }

    .file-quality {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
      margin-top: 8px;
    }

    .file-quality.good {
      color: var(--success);
    }

    .file-quality.blurry {
      color: var(--warning);
    }

    .file-quality svg {
      width: 16px;
      height: 16px;
    }

    /* ========================================
       INFO BOXES
    ======================================== */
    .info-box {
      display: flex;
      gap: 12px;
      padding: 16px;
      border-radius: var(--radius-sm);
      margin-bottom: 16px;
    }

    .info-box.primary {
      background: rgba(167, 139, 250, 0.1);
      border: 1px solid rgba(167, 139, 250, 0.2);
    }

    .info-box.primary svg {
      color: var(--primary);
    }

    .info-box.primary p {
      color: var(--primary);
    }

    .info-box.warning {
      background: rgba(245, 158, 11, 0.1);
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .info-box.warning svg {
      color: var(--warning);
    }

    .info-box.warning p {
      color: var(--warning);
    }

    .info-box svg {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .info-box p {
      font-size: 0.875rem;
      line-height: 1.5;
    }

    /* ========================================
       REGULATORY CARDS
    ======================================== */
    .regulatory-card {
      background: var(--secondary);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 16px;
    }

    .regulatory-card h4 {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--foreground);
      margin-bottom: 8px;
    }

    .regulatory-card p {
      font-size: 0.875rem;
      color: var(--muted);
      margin-bottom: 12px;
    }

    /* ========================================
       DOCUMENT SUMMARY
    ======================================== */
    .document-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: var(--secondary);
      margin-bottom: 12px;
      transition: var(--transition);
    }

    .document-item.uploaded {
      background: rgba(34, 197, 94, 0.05);
      border-color: rgba(34, 197, 94, 0.2);
    }

    .document-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .document-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .document-item.uploaded .document-icon {
      background: rgba(34, 197, 94, 0.2);
    }

    .document-item:not(.uploaded) .document-icon {
      background: var(--card);
    }

    .document-icon svg {
      width: 20px;
      height: 20px;
    }

    .document-item.uploaded .document-icon svg {
      color: var(--success);
    }

    .document-item:not(.uploaded) .document-icon svg {
      color: var(--muted);
    }

    .document-name {
      font-weight: 500;
      color: var(--foreground);
      font-size: 0.95rem;
    }

    .document-status {
      font-size: 0.8rem;
    }

    .document-item.uploaded .document-status {
      color: var(--success);
    }

    .document-item:not(.uploaded) .document-status {
      color: var(--muted);
    }

    .document-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .document-badge {
      padding: 4px 8px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      background: rgba(167, 139, 250, 0.2);
      color: var(--primary);
      border-radius: 4px;
    }

    .document-view-btn {
      background: none;
      border: none;
      padding: 8px 16px;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--primary);
      cursor: pointer;
      border-radius: 8px;
      transition: var(--transition);
    }

    .document-view-btn:hover {
      background: rgba(167, 139, 250, 0.1);
    }

    /* ========================================
       BUTTONS
    ======================================== */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 32px;
      border-radius: 9999px;
      font-family: inherit;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      border: none;
    }

    .btn svg {
      width: 20px;
      height: 20px;
    }

    .btn-primary {
      background: var(--gradient-primary);
      color: var(--background);
      box-shadow: 0 4px 15px var(--primary-glow);
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px rgba(167, 139, 250, 0.4);
    }

    .btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    .btn-outline {
      background: transparent;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-outline:hover:not(:disabled) {
      background: rgba(167, 139, 250, 0.1);
    }

    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-top: 32px;
    }

    @media (min-width: 640px) {
      .btn-group {
        flex-direction: row;
      }

      .btn-group .btn {
        flex: 1;
      }
    }

    /* ========================================
       TIMELINE
    ======================================== */
    .timeline {
      position: relative;
    }

    .timeline-item {
      display: flex;
      gap: 16px;
      padding-bottom: 32px;
      position: relative;
    }

    .timeline-item:last-child {
      padding-bottom: 0;
    }

    .timeline-item:not(:last-child)::after {
      content: '';
      position: absolute;
      left: 5px;
      top: 12px;
      width: 2px;
      height: calc(100% - 12px);
      background: var(--border);
    }

    .timeline-item.complete:not(:last-child)::after {
      background: var(--success);
    }

    .timeline-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--border);
      flex-shrink: 0;
      margin-top: 6px;
      position: relative;
      z-index: 1;
    }

    .timeline-item.complete .timeline-dot {
      background: var(--success);
    }

    .timeline-item.active .timeline-dot {
      background: var(--primary);
      box-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
      animation: pulse 2s infinite;
    }

    .timeline-item.rejected .timeline-dot {
      background: var(--destructive);
    }

    .timeline-content h4 {
      font-weight: 500;
      margin-bottom: 4px;
    }

    .timeline-item.complete .timeline-content h4,
    .timeline-item.active .timeline-content h4 {
      color: var(--foreground);
    }

    .timeline-item:not(.complete):not(.active) .timeline-content h4 {
      color: var(--muted);
    }

    .timeline-item.rejected .timeline-content h4 {
      color: var(--destructive);
    }

    .timeline-date {
      font-size: 0.8rem;
      color: var(--muted);
    }

    .timeline-active-text {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
      color: var(--primary);
      margin-top: 4px;
    }

    .timeline-active-text .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--primary);
      animation: pulse 2s infinite;
    }

    .rejection-box {
      margin-top: 24px;
      padding: 16px;
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.2);
      border-radius: var(--radius-sm);
    }

    .rejection-box h4 {
      color: var(--destructive);
      font-weight: 600;
      margin-bottom: 8px;
    }

    .rejection-box p {
      font-size: 0.875rem;
      color: var(--muted);
    }

    .success-box {
      margin-top: 24px;
      padding: 16px;
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.2);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .success-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(34, 197, 94, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .success-icon svg {
      width: 20px;
      height: 20px;
      color: var(--success);
    }

    .success-box h4 {
      color: var(--success);
      font-weight: 600;
      margin-bottom: 2px;
    }

    .success-box p {
      font-size: 0.875rem;
      color: var(--muted);
    }

    /* ========================================
       FOOTER
    ======================================== */
    .footer {
      margin-top: 48px;
      padding-top: 32px;
      border-top: 1px solid var(--border);
      text-align: center;
    }

    .footer p {
      font-size: 0.875rem;
      color: var(--muted);
    }

    .footer-links {
      display: flex;
      justify-content: center;
      gap: 24px;
      margin-top: 16px;
    }

    .footer-links a {
      font-size: 0.875rem;
      color: var(--muted);
      text-decoration: none;
      transition: var(--transition);
    }

    .footer-links a:hover {
      color: var(--primary);
    }

    .footer-copyright {
      font-size: 0.75rem;
      color: var(--muted);
      margin-top: 16px;
    }

    /* ========================================
       DOCUMENT VIEWER (FULL PAGE)
    ======================================== */
    .document-viewer {
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: var(--background);
      display: none;
      flex-direction: column;
      animation: fadeIn 0.3s ease-out;
    }

    .document-viewer.active {
      display: flex;
    }

    .document-viewer-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      background: var(--card);
      border-bottom: 1px solid var(--border);
    }

    .document-viewer-header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .document-viewer-back {
      background: none;
      border: none;
      padding: 8px;
      cursor: pointer;
      border-radius: 8px;
      transition: var(--transition);
    }

    .document-viewer-back:hover {
      background: var(--secondary);
    }

    .document-viewer-back svg {
      width: 24px;
      height: 24px;
      color: var(--foreground);
    }

    .document-viewer-info h2 {
      font-size: 1rem;
      font-weight: 600;
      color: var(--foreground);
    }

    .document-viewer-info p {
      font-size: 0.8rem;
      color: var(--muted);
    }

    .document-viewer-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .document-viewer-actions button {
      background: none;
      border: none;
      padding: 8px;
      cursor: pointer;
      border-radius: 8px;
      transition: var(--transition);
    }

    .document-viewer-actions button:hover {
      background: var(--secondary);
    }

    .document-viewer-actions svg {
      width: 20px;
      height: 20px;
      color: var(--foreground);
    }

    .document-viewer-content {
      flex: 1;
      overflow: auto;
      padding: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(31, 41, 55, 0.3);
    }

    .document-viewer-content iframe {
      width: 100%;
      height: 100%;
      max-width: 900px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    }

    .document-viewer-content img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      border-radius: var(--radius-sm);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    }

    /* ========================================
       TOAST NOTIFICATIONS
    ======================================== */
    .toast-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 2000;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .toast {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 16px 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: flex-start;
      gap: 12px;
      min-width: 300px;
      max-width: 400px;
      animation: slideIn 0.3s ease-out;
    }

    .toast.success {
      border-color: rgba(34, 197, 94, 0.3);
    }

    .toast.error {
      border-color: rgba(239, 68, 68, 0.3);
    }

    .toast-icon {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .toast.success .toast-icon {
      color: var(--success);
    }

    .toast.error .toast-icon {
      color: var(--destructive);
    }

    .toast-content h4 {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--foreground);
      margin-bottom: 2px;
    }

    .toast-content p {
      font-size: 0.8rem;
      color: var(--muted);
    }

    /* ========================================
       ANIMATIONS
    ======================================== */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(100px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes pulse {
      0%, 100% {
        box-shadow: 0 0 10px rgba(167, 139, 250, 0.3);
      }
      50% {
        box-shadow: 0 0 20px rgba(167, 139, 250, 0.6);
      }
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .animate-spin {
      animation: spin 1s linear infinite;
    }

    /* Animation delays for cards */
    .card:nth-child(1) { animation-delay: 0.05s; }
    .card:nth-child(2) { animation-delay: 0.1s; }
    .card:nth-child(3) { animation-delay: 0.15s; }
    .card:nth-child(4) { animation-delay: 0.2s; }
    .card:nth-child(5) { animation-delay: 0.25s; }
    .card:nth-child(6) { animation-delay: 0.3s; }
    .card:nth-child(7) { animation-delay: 0.35s; }
    .card:nth-child(8) { animation-delay: 0.4s; }
    .card:nth-child(9) { animation-delay: 0.45s; }

    /* Back Button Styles */
    .back-btn-container {
      margin-bottom: 24px;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 500;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
      transition: var(--transition);
    }

    .back-btn:hover {
      color: var(--foreground);
      background: rgba(255, 255, 255, 0.08);
      border-color: var(--muted);
      transform: translateX(-4px);
    }
  </style>
</head>
<body>
  <div class="container" id="kycFormSection">
    
    <div class="back-btn-container">
      <a href="../dashboards/investor-dashboard.php" class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Dashboard
      </a>
    </div>

    <header class="header">
      <div class="header-icon">
        <div class="icon-box">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="header-text">
          <h1>Investor KYC Verification</h1>
          <p>Complete verification to start investing securely</p>
        </div>
      </div>

      <div class="status-row">
        <span id="statusBadge" class="status-badge status-not-started">
          <span class="dot"></span>
          <span id="statusText">Not Started</span>
        </span>
        <span class="progress-text"><span id="progressPercent">0</span>% Complete</span>
      </div>

      <div class="progress-container">
        <div class="progress-bar">
          <div id="progressFill" class="progress-fill" style="width: 0%"></div>
        </div>
      </div>
    </header>

    <div id="timelineSection" class="card" style="display: none;">
      <div class="card-header">
        <div class="section-number complete">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div>
          <h2 class="card-title">Verification Timeline</h2>
        </div>
      </div>
      <div id="timeline" class="timeline"></div>
    </div>

    <section class="card" id="section1">
      <div class="card-header">
        <div class="section-number" id="section1Number">1</div>
        <div>
          <h2 class="card-title">Basic Personal Information</h2>
          <p class="card-description">Your legal name and contact details</p>
        </div>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">Full Legal Name<span class="required">*</span></label>
          <input type="text" class="form-input" id="fullName" placeholder="Enter your full legal name">
          <span class="form-error" id="fullNameError"></span>
        </div>
        
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" id="email" value="<?php echo htmlspecialchars($investor_email); ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <input type="tel" class="form-input" id="mobile" value="<?php echo htmlspecialchars($investor_contact); ?>" disabled>
        </div>

        <div class="form-group">
          <label class="form-label">Date of Birth<span class="required">*</span></label>
          <input type="date" class="form-input" id="dateOfBirth">
          <span class="form-helper">You must be at least 18 years old</span>
          <span class="form-error" id="dateOfBirthError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Nationality<span class="required">*</span></label>
          <select class="form-select" id="nationality">
            <option value="">Select nationality</option>
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="GB">United Kingdom</option>
            <option value="AE">United Arab Emirates</option>
            <option value="SG">Singapore</option>
            <option value="AU">Australia</option>
            <option value="CA">Canada</option>
          </select>
          <span class="form-error" id="nationalityError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Country of Residence<span class="required">*</span></label>
          <select class="form-select" id="countryOfResidence">
            <option value="">Select country</option>
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="GB">United Kingdom</option>
            <option value="AE">United Arab Emirates</option>
            <option value="SG">Singapore</option>
            <option value="AU">Australia</option>
            <option value="CA">Canada</option>
          </select>
          <span class="form-error" id="countryOfResidenceError"></span>
        </div>
      </div>
    </section>

    <section class="card" id="section2">
      <div class="card-header">
        <div class="section-number" id="section2Number">2</div>
        <div>
          <h2 class="card-title">Identity Verification</h2>
          <p class="card-description">Upload valid government-issued identification</p>
        </div>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">Document Type<span class="required">*</span></label>
          <select class="form-select" id="documentType">
            <option value="">Select document type</option>
            <option value="pan">PAN Card</option>
            <option value="aadhaar">Aadhaar (Masked)</option>
            <option value="passport">Passport</option>
          </select>
          <span class="form-error" id="documentTypeError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Document Number<span class="required">*</span></label>
          <input type="text" class="form-input" id="documentNumber" placeholder="Enter document number" maxlength="20">
          <span class="form-helper" id="documentNumberHelper"></span>
          <span class="form-error" id="documentNumberError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Issuing Country<span class="required">*</span></label>
          <select class="form-select" id="issuingCountry">
            <option value="">Select country</option>
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="GB">United Kingdom</option>
            <option value="AE">United Arab Emirates</option>
            <option value="SG">Singapore</option>
          </select>
          <span class="form-error" id="issuingCountryError"></span>
        </div>
        <div class="form-group" id="expiryDateGroup" style="display: none;">
          <label class="form-label">Expiry Date</label>
          <input type="date" class="form-input" id="expiryDate">
        </div>
      </div>
      <div class="form-grid two-cols" style="margin-top: 20px;">
        <div class="form-group">
          <label class="form-label">Identity Proof (Document)<span class="required">*</span></label>
          <div class="file-upload" id="identityProofUpload">
            <input type="file" id="identityProof" accept=".pdf,.jpg,.jpeg,.png">
            <div class="file-upload-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
            </div>
            <p class="file-upload-text">Drop your file here, or <span>browse</span></p>
            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
          </div>
          <div id="identityProofQuality" class="file-quality" style="display: none;"></div>
          <span class="form-error" id="identityProofError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Live Selfie / Photo<span class="required">*</span></label>
          <div class="file-upload" id="selfieUpload">
            <input type="file" id="selfie" accept=".jpg,.jpeg,.png">
            <div class="file-upload-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
            </div>
            <p class="file-upload-text">Drop your file here, or <span>browse</span></p>
            <p class="file-upload-hint">JPG, PNG up to 5MB</p>
          </div>
          <div id="selfieQuality" class="file-quality" style="display: none;"></div>
          <span class="form-error" id="selfieError"></span>
        </div>
      </div>
    </section>

    <section class="card" id="section3">
      <div class="card-header">
        <div class="section-number" id="section3Number">3</div>
        <div>
          <h2 class="card-title">Address Verification</h2>
          <p class="card-description">Your current residential address</p>
        </div>
      </div>
      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label">Address Line<span class="required">*</span></label>
        <input type="text" class="form-input" id="addressLine" placeholder="House/Flat No., Building Name, Street">
        <span class="form-error" id="addressLineError"></span>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">City<span class="required">*</span></label>
          <input type="text" class="form-input" id="city" placeholder="Enter city">
          <span class="form-error" id="cityError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">State / Province<span class="required">*</span></label>
          <input type="text" class="form-input" id="state" placeholder="Enter state">
          <span class="form-error" id="stateError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Country<span class="required">*</span></label>
          <select class="form-select" id="addressCountry">
            <option value="">Select country</option>
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="GB">United Kingdom</option>
            <option value="AE">United Arab Emirates</option>
            <option value="SG">Singapore</option>
          </select>
          <span class="form-error" id="addressCountryError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Pincode / ZIP Code<span class="required">*</span></label>
          <input type="text" class="form-input" id="pincode" placeholder="Enter pincode">
          <span class="form-error" id="pincodeError"></span>
        </div>
      </div>
      <div class="form-group" style="margin-top: 20px;">
        <label class="form-label">Address Proof Document<span class="required">*</span></label>
        <div class="file-upload" id="addressProofUpload">
          <input type="file" id="addressProof" accept=".pdf">
          <div class="file-upload-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </div>
          <p class="file-upload-text">Drop your file here, or <span>browse</span></p>
          <p class="file-upload-hint">PDF only, up to 5MB</p>
        </div>
        <span class="form-helper">Utility bill, bank statement, or rental agreement</span>
        <span class="form-error" id="addressProofError"></span>
      </div>
    </section>

    <section class="card" id="section4">
      <div class="card-header">
        <div class="section-number" id="section4Number">4</div>
        <div>
          <h2 class="card-title">Bank Account Verification</h2>
          <p class="card-description">Bank details for secure transactions and refunds</p>
        </div>
      </div>
      <div class="info-box primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>Bank details are required for secure transactions and refunds. Your information is encrypted and secure.</p>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">Account Holder Name<span class="required">*</span></label>
          <input type="text" class="form-input" id="accountHolderName" placeholder="As per bank records">
          <span class="form-error" id="accountHolderNameError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name<span class="required">*</span></label>
          <input type="text" class="form-input" id="bankName" placeholder="Enter bank name">
          <span class="form-error" id="bankNameError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Account Number<span class="required">*</span></label>
          <input type="text" class="form-input" id="accountNumber" placeholder="Enter account number">
          <span class="form-error" id="accountNumberError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">IFSC / SWIFT Code<span class="required">*</span></label>
          <input type="text" class="form-input" id="ifscCode" placeholder="SBIN0001234" maxlength="11">
          <span class="form-helper">Format: 4 letters + 0 + 6 alphanumeric</span>
          <span class="form-error" id="ifscCodeError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Account Type<span class="required">*</span></label>
          <select class="form-select" id="accountType">
            <option value="">Select account type</option>
            <option value="savings">Savings Account</option>
            <option value="current">Current Account</option>
          </select>
          <span class="form-error" id="accountTypeError"></span>
        </div>
      </div>
      <div class="form-group" style="margin-top: 20px;">
        <label class="form-label">Cancelled Cheque / Bank Statement<span class="required">*</span></label>
        <div class="file-upload" id="bankProofUpload">
          <input type="file" id="bankProof" accept=".pdf,.jpg,.jpeg,.png">
          <div class="file-upload-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </div>
          <p class="file-upload-text">Drop your file here, or <span>browse</span></p>
          <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
        </div>
        <span class="form-error" id="bankProofError"></span>
      </div>
    </section>

    <section class="card" id="section5">
      <div class="card-header">
        <div class="section-number" id="section5Number">5</div>
        <div>
          <h2 class="card-title">Investment Profile</h2>
          <p class="card-description">Tell us about your investment preferences</p>
        </div>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">Investor Type<span class="required">*</span></label>
          <select class="form-select" id="investorType">
            <option value="">Select investor type</option>
            <option value="individual">Individual Investor</option>
            <option value="angel">Angel Investor</option>
            <option value="institutional">Institutional Investor</option>
          </select>
          <span class="form-error" id="investorTypeError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Annual Income Range<span class="required">*</span></label>
          <select class="form-select" id="annualIncome">
            <option value="">Select income range</option>
            <option value="below_5l">Below ₹5 Lakhs</option>
            <option value="5l_10l">₹5 - 10 Lakhs</option>
            <option value="10l_25l">₹10 - 25 Lakhs</option>
            <option value="25l_50l">₹25 - 50 Lakhs</option>
            <option value="50l_1cr">₹50 Lakhs - 1 Crore</option>
            <option value="above_1cr">Above ₹1 Crore</option>
          </select>
          <span class="form-error" id="annualIncomeError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Net Worth Range<span class="required">*</span></label>
          <select class="form-select" id="netWorth">
            <option value="">Select net worth range</option>
            <option value="below_25l">Below ₹25 Lakhs</option>
            <option value="25l_50l">₹25 - 50 Lakhs</option>
            <option value="50l_1cr">₹50 Lakhs - 1 Crore</option>
            <option value="1cr_5cr">₹1 - 5 Crores</option>
            <option value="5cr_10cr">₹5 - 10 Crores</option>
            <option value="above_10cr">Above ₹10 Crores</option>
          </select>
          <span class="form-error" id="netWorthError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Investment Experience<span class="required">*</span></label>
          <select class="form-select" id="investmentExperience">
            <option value="">Select experience level</option>
            <option value="beginner">Beginner (0-2 years)</option>
            <option value="intermediate">Intermediate (2-5 years)</option>
            <option value="professional">Professional (5+ years)</option>
          </select>
          <span class="form-error" id="investmentExperienceError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Typical Investment Amount<span class="required">*</span></label>
          <select class="form-select" id="typicalInvestmentAmount">
            <option value="">Select investment amount</option>
            <option value="below_1l">Below ₹1 Lakh</option>
            <option value="1l_5l">₹1 - 5 Lakhs</option>
            <option value="5l_10l">₹5 - 10 Lakhs</option>
            <option value="10l_25l">₹10 - 25 Lakhs</option>
            <option value="25l_50l">₹25 - 50 Lakhs</option>
            <option value="above_50l">Above ₹50 Lakhs</option>
          </select>
          <span class="form-error" id="typicalInvestmentAmountError"></span>
        </div>
      </div>
    </section>

    <section class="card" id="section6">
      <div class="card-header">
        <div class="section-number" id="section6Number">6</div>
        <div>
          <h2 class="card-title">Risk & Suitability Declaration</h2>
          <p class="card-description">Acknowledge the risks associated with startup investments</p>
        </div>
      </div>
      <div class="info-box warning">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p>Startup investments carry significant risks. Please read and acknowledge each statement carefully.</p>
      </div>
      <div class="checkbox-group">
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="understandsHighRisk">
          <span class="checkbox-text">I understand that startup investments are high risk and speculative in nature</span>
        </label>
        <span class="form-error" id="understandsHighRiskError"></span>
        
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="acceptsCapitalLoss">
          <span class="checkbox-text">I acknowledge that I may lose 100% of my invested capital</span>
        </label>
        <span class="form-error" id="acceptsCapitalLossError"></span>
        
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="understandsNoGuarantee">
          <span class="checkbox-text">I understand that returns are not guaranteed and past performance is not indicative of future results</span>
        </label>
        <span class="form-error" id="understandsNoGuaranteeError"></span>
        
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="understandsIlliquidity">
          <span class="checkbox-text">I acknowledge that startup investments are illiquid and I may not be able to sell my shares for an extended period</span>
        </label>
        <span class="form-error" id="understandsIlliquidityError"></span>
      </div>
      <div class="form-group" style="margin-top: 24px;">
        <label class="form-label">Why do you want to invest in startups? (Optional)</label>
        <textarea class="form-textarea" id="investmentReason" placeholder="Share your motivation for investing in startups..."></textarea>
      </div>
    </section>

    <section class="card" id="section7">
      <div class="card-header">
        <div class="section-number" id="section7Number">7</div>
        <div>
          <h2 class="card-title">Tax & Regulatory Details</h2>
          <p class="card-description">Tax identification and regulatory compliance</p>
        </div>
      </div>
      <div class="form-grid two-cols">
        <div class="form-group">
          <label class="form-label">PAN Number<span class="required">*</span></label>
          <input type="text" class="form-input" id="panNumber" placeholder="ABCDE1234F" maxlength="10">
          <span class="form-helper">Your Permanent Account Number</span>
          <span class="form-error" id="panNumberError"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Tax Residency Country<span class="required">*</span></label>
          <select class="form-select" id="taxResidencyCountry">
            <option value="">Select country</option>
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="GB">United Kingdom</option>
            <option value="AE">United Arab Emirates</option>
            <option value="SG">Singapore</option>
          </select>
          <span class="form-error" id="taxResidencyCountryError"></span>
        </div>
      </div>
      <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 24px;">
        <div class="regulatory-card">
          <h4>FATCA Declaration</h4>
          <p>Are you a U.S. citizen or a U.S. tax resident for FATCA purposes?</p>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" class="radio-input" name="fatca" value="yes">
              <span class="radio-text">Yes</span>
            </label>
            <label class="radio-label">
              <input type="radio" class="radio-input" name="fatca" value="no" checked>
              <span class="radio-text">No</span>
            </label>
          </div>
        </div>
        <div class="regulatory-card">
          <h4>Politically Exposed Person (PEP)</h4>
          <p>Are you or any of your close relatives a politically exposed person?</p>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" class="radio-input" name="pep" value="yes">
              <span class="radio-text">Yes</span>
            </label>
            <label class="radio-label">
              <input type="radio" class="radio-input" name="pep" value="no" checked>
              <span class="radio-text">No</span>
            </label>
          </div>
        </div>
      </div>
    </section>

    <section class="card" id="section8">
      <div class="card-header">
        <div class="section-number" id="section8Number">8</div>
        <div>
          <h2 class="card-title">Document Summary</h2>
          <p class="card-description" id="documentCount">0 of 4 documents uploaded</p>
        </div>
      </div>
      <div id="documentList">
        <div class="document-item" id="docIdentity">
          <div class="document-info">
            <div class="document-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <p class="document-name">Identity Proof</p>
              <p class="document-status">Not uploaded</p>
            </div>
          </div>
          <div class="document-actions"></div>
        </div>
        <div class="document-item" id="docSelfie">
          <div class="document-info">
            <div class="document-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <p class="document-name">Live Selfie</p>
              <p class="document-status">Not uploaded</p>
            </div>
          </div>
          <div class="document-actions"></div>
        </div>
        <div class="document-item" id="docAddress">
          <div class="document-info">
            <div class="document-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <p class="document-name">Address Proof</p>
              <p class="document-status">Not uploaded</p>
            </div>
          </div>
          <div class="document-actions"></div>
        </div>
        <div class="document-item" id="docBank">
          <div class="document-info">
            <div class="document-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <p class="document-name">Bank Proof</p>
              <p class="document-status">Not uploaded</p>
            </div>
          </div>
          <div class="document-actions"></div>
        </div>
      </div>
    </section>

    <section class="card" id="section9">
      <div class="card-header">
        <div class="section-number" id="section9Number">9</div>
        <div>
          <h2 class="card-title">Consent & Final Submission</h2>
          <p class="card-description">Review and submit your KYC application</p>
        </div>
      </div>
      <div class="checkbox-group" style="margin-bottom: 32px;">
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="confirmAccuracy">
          <span class="checkbox-text">I confirm that all information provided is accurate and complete to the best of my knowledge</span>
        </label>
        <span class="form-error" id="confirmAccuracyError"></span>
        
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="consentKYC">
          <span class="checkbox-text">I consent to the verification of my identity and documents for KYC compliance purposes</span>
        </label>
        <span class="form-error" id="consentKYCError"></span>
        
        <label class="checkbox-label">
          <input type="checkbox" class="checkbox-input" id="agreeTerms">
          <span class="checkbox-text">I agree to the Terms of Service and Privacy Policy</span>
        </label>
        <span class="form-error" id="agreeTermsError"></span>
      </div>
      <div class="btn-group">
        <button type="button" class="btn btn-outline" id="saveDraftBtn">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Save & Complete Later
        </button>
        <button type="button" class="btn btn-primary" id="submitBtn" disabled>
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Submit KYC
        </button>
      </div>
    </section>

    <footer class="footer">
      <p>Your information is encrypted and securely stored. We take your privacy seriously.</p>
      <div class="footer-links">
        <a href="#">Help Center</a>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
      <p class="footer-copyright">© 2026 SmartPitchHub. All rights reserved.</p>
    </footer>
  </div> <div class="document-viewer" id="documentViewer">
    <header class="document-viewer-header">
      <div class="document-viewer-header-left">
        <button class="document-viewer-back" id="closeViewer">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>
        <div class="document-viewer-info">
          <h2 id="viewerDocName">Document</h2>
          <p id="viewerDocInfo">-</p>
        </div>
      </div>
      <div class="document-viewer-actions">
        <button id="downloadDoc">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
        </button>
        <button id="closeViewerX">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </header>
    <main class="document-viewer-content" id="viewerContent"></main>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <div id="successScreen" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: #0f172a; z-index: 9999; align-items: center; justify-content: center;">
      <div style="background: #1e293b; padding: 40px; border-radius: 16px; border: 1px solid #334155; max-width: 500px; text-align: center; box-shadow: 0 10px 50px rgba(0,0,0,0.5);">
          
          <div style="width: 80px; height: 80px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 40px; height: 40px;">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
          </div>

          <h1 style="color: #fff; margin-bottom: 10px;">Submission Successful!</h1>
          <p style="color: #94a3b8; line-height: 1.6; margin-bottom: 30px;">
              Your KYC documents have been securely uploaded.<br>
              Our team will review your details shortly.
          </p>

          <div style="background: #0f172a; padding: 15px; border-radius: 8px; display: inline-block; margin-bottom: 30px;">
              <span style="color: #94a3b8; font-size: 0.9em;">Current Status:</span>
              <span style="color: #3b82f6; font-weight: bold; margin-left: 10px;">Under Review</span>
          </div>
          
          <br>
          <a href="../dashboards/investor-dashboard.php" class="btn btn-primary" style="padding: 12px 30px; text-decoration: none; display: inline-block; background-color: #2563eb; color: white; border-radius: 8px;">
              Return to Dashboard
          </a>
      </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
  <script>
    // ========================================
    // 0. AI CONFIGURATION
    // ========================================
    const AI_STATE = {
        modelsLoaded: false,
        idDescriptor: null,
        selfieDescriptor: null,
        ocrText: "" // Store the text read from the address proof
    };

    // Load Face AI
    async function loadAIModels() {
        const MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
        try {
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            AI_STATE.modelsLoaded = true;
            console.log("Face AI Models Loaded");
        } catch (err) {
            console.error("AI Load Error:", err);
        }
    }

    // ========================================
    // 1. STATE MANAGEMENT
    // ========================================
    const state = {
        status: 'not_started',
        isSubmitted: false,
        files: { identityProof: null, selfie: null, addressProof: null, bankProof: null },
        timeline: { submitted: null, underReview: null, approved: null, rejected: null }
    };

    // ========================================
    // 2. FACE MATCHING LOGIC
    // ========================================
    async function processFace(file, type) {
        if (!AI_STATE.modelsLoaded) { showToast('Please Wait', 'AI loading...', 'warning'); return; }

        const img = await faceapi.bufferToImage(file);
        const detection = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();

        if (!detection) {
            showError(type === 'id' ? 'identityProof' : 'selfie', 'No face detected! Use a clear photo.');
            if (type === 'id') AI_STATE.idDescriptor = null;
            if (type === 'selfie') AI_STATE.selfieDescriptor = null;
            return;
        }

        clearError(type === 'id' ? 'identityProof' : 'selfie');

        if (type === 'id') {
            AI_STATE.idDescriptor = detection.descriptor;
            showToast('ID Scanned', 'Face detected on ID.', 'success');
        } else {
            AI_STATE.selfieDescriptor = detection.descriptor;
            showToast('Selfie Scanned', 'Face detected.', 'success');
        }

        if (AI_STATE.idDescriptor && AI_STATE.selfieDescriptor) compareFaces();
    }

    function compareFaces() {
        const distance = faceapi.euclideanDistance(AI_STATE.idDescriptor, AI_STATE.selfieDescriptor);
        const matchScore = Math.max(0, (1 - distance) * 100);
        if (distance < 0.6) {
            showToast('Verified', `Face Match: ${matchScore.toFixed(0)}%`, 'success');
            document.getElementById('docIdentity').style.border = "2px solid #22c55e";
            document.getElementById('docSelfie').style.border = "2px solid #22c55e";
        } else {
            showToast('Mismatch', 'Faces do not match.', 'error');
            document.getElementById('docIdentity').style.border = "2px solid #ef4444";
            document.getElementById('docSelfie').style.border = "2px solid #ef4444";
        }
    }

    // ========================================
    // 3. ADDRESS OCR LOGIC (NEW FEATURE)
    // ========================================
   async function analyzeAddressDocument(file, zoneElement) {
        const userCity = document.getElementById('city').value.trim().toLowerCase();
        const userPincode = document.getElementById('pincode').value.trim();

        if (!userCity && !userPincode) {
            showToast('Tip', 'Enter City or Pincode first for verification.', 'warning');
            return;
        }

        showToast('Scanning', 'Reading address text...', 'info');
        zoneElement.style.borderColor = "#a78bfa"; // Purple (Scanning)

        try {
            const { data: { text } } = await Tesseract.recognize(file, 'eng');
            const cleanText = text.toLowerCase();
            console.log("OCR Text:", cleanText);

            let matchFound = false;
            let matches = [];

            if (userPincode && cleanText.includes(userPincode)) { matchFound = true; matches.push("Pincode"); }
            if (userCity && cleanText.includes(userCity)) { matchFound = true; matches.push("City"); }

            if (matchFound) {
                showToast('Verified', `Matched: ${matches.join(", ")}`, 'success');
                zoneElement.style.borderColor = "#22c55e"; // Green Border (Verified)
                zoneElement.style.backgroundColor = "rgba(34, 197, 94, 0.1)";
            } else {
                showToast('Manual Review', 'Address text not clear. Admin will verify.', 'warning');
                zoneElement.style.borderColor = "#f59e0b"; // Yellow (Manual Review)
                zoneElement.style.backgroundColor = "rgba(245, 158, 11, 0.1)";
            }
        } catch (error) {
            console.error("OCR Error", error);
            showToast('Scan Failed', 'Could not read image.', 'error');
            zoneElement.style.borderColor = "#f59e0b"; 
        }
    }

    // ========================================
    // 4. UTILITY & VALIDATION
    // ========================================
    function validatePAN(pan) { return /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.toUpperCase()); }
    function validateIFSC(ifsc) { return /^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.toUpperCase()); }
    function validateAge(dob) {
        const d = new Date(dob); const t = new Date();
        let age = t.getFullYear() - d.getFullYear();
        if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) age--;
        return age >= 18;
    }
    function formatDate(date) { return new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }
    function formatFileSize(bytes) { return (bytes / 1024).toFixed(1) + ' KB'; }
    function showError(id, msg) { const el = document.getElementById(id + 'Error'); if(el) el.textContent = msg; }
    function clearError(id) { const el = document.getElementById(id + 'Error'); if(el) el.textContent = ''; }
    
    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
        <svg class="toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}" />
        </svg>
        <div class="toast-content"><h4>${title}</h4><p>${message}</p></div>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // ========================================
    // 5. PROGRESS & UI
    // ========================================
    function calculateProgress() {
        let valid = 0;
        // 1. Personal
        if(document.getElementById('fullName').value && validateAge(document.getElementById('dateOfBirth').value)) valid++;
        // 2. Identity
        if(document.getElementById('documentNumber').value && state.files.identityProof && state.files.selfie) valid++;
        // 3. Address
        if(document.getElementById('pincode').value && state.files.addressProof) valid++;
        // 4. Bank
        if(document.getElementById('accountNumber').value && state.files.bankProof) valid++;
        // 5. Investment
        if(document.getElementById('investorType').value) valid++;
        // 6. Risk
        if(document.getElementById('understandsHighRisk').checked) valid++;
        // 7. Tax
        if(validatePAN(document.getElementById('panNumber').value)) valid++;
        // 8. Consent
        if(document.getElementById('consentKYC').checked) valid++;
        
        return Math.round((valid / 8) * 100);
    }

    function updateProgress() {
        const p = calculateProgress();
        document.getElementById('progressFill').style.width = p + '%';
        document.getElementById('progressPercent').textContent = p;
        document.getElementById('submitBtn').disabled = p < 100;
        
        // Update Section Numbers UI
        for(let i=1; i<=9; i++) {
            const el = document.getElementById('section'+i+'Number');
            if(el && p >= (i/9)*100) el.classList.add('complete');
        }
    }

    // ========================================
    // 6. FILE UPLOAD HANDLING (UPDATED)
    // ========================================
    function setupFileUpload(inputId, zoneId, key, qualityId) {
        const input = document.getElementById(inputId);
        const zone = document.getElementById(zoneId);
        const quality = qualityId ? document.getElementById(qualityId) : null;

        zone.addEventListener('click', () => input.click());
        input.addEventListener('change', (e) => {
            if(e.target.files[0]) handleFile(e.target.files[0], input, zone, key, quality);
        });
        // Drag & Drop listeners (simplified)
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', e => { e.preventDefault(); zone.classList.remove('drag-over'); });
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('drag-over');
            if(e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0], input, zone, key, quality);
        });
    }

    // ========================================
    // ROBUST HANDLE FILE FUNCTION
    // ========================================
   // ========================================
    // UNIVERSAL HANDLE FILE (Works for ALL boxes)
    // ========================================
    function handleFile(file, input, zone, key, qualityEl) {
        // 1. Validation
        if (file.size > 5 * 1024 * 1024) { 
            showToast('Error', 'File too large (Max 5MB)', 'error'); 
            return; 
        }
        
        state.files[key] = file;
        console.log(`File uploaded for ${key}:`, file.type);

        // 2. Identify Type
        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        const isImage = file.type.startsWith('image/');

        // 3. APPLY STYLES IMMEDIATELY
        // Reset first
        zone.style.cssText = "border: 2px dashed #374151; background-color: transparent;";

        if (isPdf) {
            // *** PDF CASE: YELLOW FOR EVERYONE ***
            console.log("PDF Detected -> Applying Yellow Style");
            zone.style.cssText = "border: 2px solid #f59e0b !important; background-color: rgba(245, 158, 11, 0.1) !important;";
            showToast('Manual Review', 'PDF accepted. Admin will verify manually.', 'info');
        }

        // 4. RUN AI (Only if Image)
        if (isImage) {
            if (key === 'identityProof' || key === 'selfie') {
                processFace(file, key === 'identityProof' ? 'id' : 'selfie');
            }
            if (key === 'addressProof') {
                analyzeAddressDocument(file, zone);
            }
        }

        // 5. Update UI HTML
        zone.innerHTML = `
        <div class="file-preview">
            <div class="file-preview-info">
                <div class="file-preview-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="file-preview-name">${file.name}</p>
                    <p class="file-preview-size">${formatFileSize(file.size)}</p>
                </div>
            </div>
            <button class="file-remove" type="button" style="background:none; border:none; cursor:pointer; color:inherit;">x</button>
        </div>`;

        // 6. Re-attach Remove Listener
        const removeBtn = zone.querySelector('.file-remove');
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            state.files[key] = null;
            
            // Clear AI Data
            if(key === 'identityProof') { AI_STATE.idDescriptor = null; }
            if(key === 'selfie') { AI_STATE.selfieDescriptor = null; }

            // Reset Styles
            zone.style.cssText = "border: 2px dashed #374151; background-color: transparent;";

            zone.innerHTML = `
                <div class="file-upload-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <p class="file-upload-text">Drop your file here, or <span>browse</span></p>
            `;
            
            setupFileUpload(input.id, zone.id, key, qualityEl ? qualityEl.id : null);
        });

        updateDocumentSummary();
        updateProgress();
    }
    function updateDocumentSummary() {
        const map = { identityProof: 'docIdentity', selfie: 'docSelfie', addressProof: 'docAddress', bankProof: 'docBank' };
        let count = 0;
        for (const [key, id] of Object.entries(map)) {
            const el = document.getElementById(id);
            if (state.files[key]) {
                el.classList.add('uploaded'); el.querySelector('.document-status').textContent = 'Uploaded'; count++;
            } else {
                el.classList.remove('uploaded'); el.querySelector('.document-status').textContent = 'Not uploaded';
            }
        }
        document.getElementById('documentCount').textContent = `${count} of 4 documents uploaded`;
    }

    // ========================================
    // 7. SUBMISSION
    // ========================================
   // ========================================
    // UPDATED: SUBMIT KYC FUNCTION
    // ========================================
    async function submitKYC() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true; 
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';

        try {
            const formData = new FormData();
            
            // 1. Gather Text Inputs
            ['fullName','dateOfBirth','nationality','countryOfResidence','documentType','documentNumber','issuingCountry','addressLine','city','state','addressCountry','pincode','accountHolderName','bankName','accountNumber','ifscCode','accountType','investorType','annualIncome','netWorth','investmentExperience','typicalInvestmentAmount','investmentReason','panNumber','taxResidencyCountry'].forEach(id => {
                const el = document.getElementById(id);
                if(el) formData.append(id, el.value);
            });
            
            // Optional Fields
            const expiryDate = document.getElementById('expiryDate');
            if(expiryDate) formData.append('expiryDate', expiryDate.value);

            // Radio Buttons
            formData.append('fatca', document.querySelector('input[name="fatca"]:checked')?.value || 'no');
            formData.append('pep', document.querySelector('input[name="pep"]:checked')?.value || 'no');

            // 2. Gather Files
            if(state.files.identityProof) formData.append('identityProof', state.files.identityProof);
            if(state.files.selfie) formData.append('selfie', state.files.selfie);
            if(state.files.addressProof) formData.append('addressProof', state.files.addressProof);
            if(state.files.bankProof) formData.append('bankProof', state.files.bankProof);

            // 3. Send to Backend
            const res = await fetch('submit_investor_kyc.php', { method: 'POST', body: formData });
            const result = await res.json();

            if (result.status === 'success') {
                showToast('Success', 'KYC Submitted Successfully!', 'success');

                // --- NEW LOGIC: SWITCH SCREENS ---
                
                // 1. Hide the Main Form
                const formSection = document.getElementById('kycFormSection');
                if(formSection) formSection.style.display = 'none';

                // 2. Show the Success Screen
                const successScreen = document.getElementById('successScreen');
                if(successScreen) successScreen.style.display = 'flex'; // Flex makes it center perfectly

                // 3. Scroll to Top
                window.scrollTo({ top: 0, behavior: 'smooth' });

                // 4. Update Status Badge (Visual)
                updateStatus('under_review');
                
            } else { 
                throw new Error(result.message || 'Submission failed'); 
            }

        } catch (err) {
            console.error(err);
            showToast('Error', err.message, 'error');
            btn.disabled = false; 
            btn.innerHTML = 'Submit KYC';
        }
    }

    function updateStatus(s) { 
        state.status = s; 
        document.getElementById('statusBadge').className = 'status-badge status-'+s.replace('_','-');
        document.getElementById('statusText').textContent = s.replace('_', ' ').toUpperCase();
    }

    // ========================================
    // 8. INIT
    // ========================================
    document.addEventListener('DOMContentLoaded', () => {
        loadAIModels();
        setupFileUpload('identityProof', 'identityProofUpload', 'identityProof', 'identityProofQuality');
        setupFileUpload('selfie', 'selfieUpload', 'selfie', 'selfieQuality');
        setupFileUpload('addressProof', 'addressProofUpload', 'addressProof', null);
        setupFileUpload('bankProof', 'bankProofUpload', 'bankProof', null);

        document.querySelectorAll('input, select').forEach(el => el.addEventListener('input', updateProgress));
        document.getElementById('submitBtn').addEventListener('click', submitKYC);
        
        // Document Viewer Listeners
        document.getElementById('closeViewer').addEventListener('click', () => document.getElementById('documentViewer').classList.remove('active'));
        document.getElementById('documentList').addEventListener('click', (e) => {
            if(e.target.classList.contains('document-view-btn')) {
                // ... view logic ...
            }
        });
        
        updateProgress();
    });
</script> 
</body>
</html>
