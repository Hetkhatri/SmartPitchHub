<?php
session_start();
// If user is not logged in, close the window or redirect
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please log in first.'); window.close();</script>";
    exit();
}

// 2. Role Check (Only entrepreneurs should build valuations)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'entrepreneur') {
    echo "<script>alert('Access Denied: Only entrepreneurs can access this tool.'); window.close();</script>";
    exit();
}

// 3. Pending Request Check
require_once '../db.php';
$user_id = $_SESSION['user_id'];
$checkValSql = "SELECT status FROM valuation_requests WHERE entrepreneur_id = ? AND status = 'pending' LIMIT 1";
if ($stmt = $conn->prepare($checkValSql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Your valuation request is already under review.'); window.location.href='../dashboards/Entrepreneur-dashboard.php';</script>";
        exit();
    }
    $stmt->close();
}

// 4. Auto-Stage Logic: Detect if this is a first-time or follow-on round
$pitchCount = 0;
$countSql = "SELECT COUNT(*) as total FROM pitches WHERE entrepreneur_id = ?";
if ($countStmt = $conn->prepare($countSql)) {
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $res = $countStmt->get_result()->fetch_assoc();
    $pitchCount = $res['total'];
    $countStmt->close();
}

// Logic: 0 pitches = Seed, 1 pitch = Series A, etc.
$autoStage = "seed";
if ($pitchCount == 1) $autoStage = "series-a";
elseif ($pitchCount == 2) $autoStage = "series-b";
elseif ($pitchCount >= 3) $autoStage = "series-c";

$stageLabels = [
    'pre-seed' => 'Pre-Seed',
    'seed' => 'Seed Stage',
    'series-a' => 'Series A',
    'series-b' => 'Series B',
    'series-c' => 'Series C+'
];
$displayStage = $stageLabels[$autoStage] ?? 'Seed Stage';
?>
<!DOCTYPE html>
<html lang="en">
...
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Valuation Builder & Verification</title>
  <link rel="stylesheet" href="../css/valuationVerifyStyles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Animated Background -->
  <div class="animated-bg">
    <div class="gradient-orb orb-1"></div>
    <div class="gradient-orb orb-2"></div>
    <div class="gradient-orb orb-3"></div>
    <div class="light-streak streak-1"></div>
    <div class="light-streak streak-2"></div>
    <div class="light-streak streak-3"></div>
  </div>

  <div class="container">
    <!-- Header -->
    <header class="header">
      <div class="step-badge">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>
          <path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
        </svg>
        <span>Step: Valuation Setup</span>
      </div>
      <h1 class="title">
        <span class="text-gradient">Valuation Builder</span>
        <span> & Verification</span>
      </h1>
      <p class="subtitle">
        Provide the key inputs to establish and justify your company's valuation for investor review.
      </p>
    </header>

    <!-- Progress Indicator -->
    <div class="progress-indicator">
      <div class="progress-step" data-step="1">
        <div class="step-circle">1</div>
        <span class="step-label">Company</span>
      </div>
      <div class="progress-line"></div>
      <div class="progress-step" data-step="2">
        <div class="step-circle">2</div>
        <span class="step-label">Financials</span>
      </div>
      <div class="progress-line"></div>
      <div class="progress-step" data-step="3">
        <div class="step-circle">3</div>
        <span class="step-label">Market</span>
      </div>
      <div class="progress-line"></div>
      <div class="progress-step" data-step="4">
        <div class="step-circle">4</div>
        <span class="step-label">Valuation</span>
      </div>
      <div class="progress-line"></div>
      <div class="progress-step" data-step="5">
        <div class="step-circle">5</div>
        <span class="step-label">Documents</span>
      </div>
      <div class="progress-line"></div>
      <div class="progress-step" data-step="6">
        <div class="step-circle">6</div>
        <span class="step-label">Review</span>
      </div>
    </div>

    <!-- Form Sections -->
    <form id="valuationForm" class="form-sections">
      
      <!-- Section 1: Company Profile -->
      <section class="glass-card">
        <div class="section-header">
          <div class="section-icon purple">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
            </svg>
          </div>
          <div class="section-title-group">
            <h3 class="section-title">Company Profile</h3>
            <p class="section-description">Context signals about your registered entity</p>
          </div>
        </div>
        <div class="form-grid grid-2">
          <div class="form-group">
            <label class="form-label">Registered Entity Name</label>
            <input type="text" class="glow-input" placeholder="Enter company name" name="companyName">
          </div>
          <div class="form-group">
            <label class="form-label">Industry Sector</label>
            <div class="select-wrapper">
              <select class="glow-select" name="industry">
                <option value="">Select industry</option>
                <option value="fintech">Fintech</option>
                <option value="healthtech">HealthTech</option>
                <option value="edtech">EdTech</option>
                <option value="saas">SaaS / Enterprise</option>
                <option value="ecommerce">E-Commerce</option>
                <option value="ai-ml">AI / Machine Learning</option>
                <option value="cleantech">CleanTech / Sustainability</option>
                <option value="deeptech">DeepTech / Hardware</option>
                <option value="consumer">Consumer</option>
                <option value="other">Other</option>
              </select>
              <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Business Stage</label>
            <div class="input-with-icon">
              <input type="text" class="glow-input" value="<?php echo $displayStage; ?>" readonly style="background: rgba(255,255,255,0.05); cursor: not-allowed; color: #a5b4fc; border-color: rgba(99, 102, 241, 0.2);">
              <input type="hidden" name="stage" value="<?php echo $autoStage; ?>">
              <div style="font-size: 0.75rem; color: #6366f1; margin-top: 6px; display: flex; align-items: center; gap: 4px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Automatically assigned based on growth
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Date of Incorporation</label>
            <input type="date" class="glow-input" name="incorporationDate">
          </div>
        </div>
      </section>

      <!-- Section 2: Financial & Traction Signals -->
      <section class="glass-card">
        <div class="section-header">
          <div class="section-icon blue">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
            </svg>
          </div>
          <div class="section-title-group">
            <h3 class="section-title">Financial & Traction Signals</h3>
            <p class="section-description">Key metrics that inform valuation calculations</p>
          </div>
        </div>
        <div class="form-grid grid-2">
          <div class="form-group">
            <label class="form-label">
              Trailing 12-Month Revenue
              <button type="button" class="tooltip-trigger" data-tooltip="Total revenue generated in the last 12 months">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
                </svg>
              </button>
            </label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="0" name="ttmRevenue">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Projected Revenue (Next 12 Months)</label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="0" name="projectedRevenue">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">
              Monthly Cash Burn
              <button type="button" class="tooltip-trigger" data-tooltip="Average monthly operating expenses minus revenue">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
                </svg>
              </button>
            </label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="0" name="cashBurn">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Total Active Users / Customers</label>
            <input type="text" class="glow-input" placeholder="0" name="activeUsers">
          </div>
          <div class="form-group full-width">
            <label class="form-label">
              Growth Rate
              <button type="button" class="tooltip-trigger" data-tooltip="Month-over-month or year-over-year growth rate for your primary metric">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
                </svg>
              </button>
            </label>
            <div class="input-with-suffix">
              <input type="number" class="glow-input has-suffix" placeholder="0" name="growthRate">
              <span class="input-suffix">%</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 3: Market & Team Signals -->
      <section class="glass-card">
        <div class="section-header">
          <div class="section-icon cyan">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div class="section-title-group">
            <h3 class="section-title">Market & Team Signals</h3>
            <p class="section-description">Qualitative indicators for valuation context</p>
          </div>
        </div>
        <div class="form-grid grid-2">
          <div class="form-group">
            <label class="form-label">Market Size Indicator</label>
            <div class="select-wrapper">
              <select class="glow-select" name="marketSize">
                <option value="">Select market size</option>
                <option value="niche">Niche (₹0 - ₹10 Cr)</option>
                <option value="medium">Medium (₹10 Cr - ₹100 Cr)</option>
                <option value="large">Large (₹100 Cr - ₹1,000 Cr)</option>
                <option value="global">Global (₹1,000 Cr+)</option>
              </select>
              <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Founder / Team Strength</label>
            <div class="select-wrapper">
              <select class="glow-select" name="teamStrength">
                <option value="">Select experience level</option>
                <option value="first-time">First-time Founders</option>
                <option value="experienced">Experienced Founders (1-2 startups)</option>
                <option value="serial">Serial Entrepreneurs (3+ startups)</option>
                <option value="exited">Previously Exited Founders</option>
                <option value="industry">Deep Industry Expertise</option>
              </select>
              <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 4: Valuation & Fundraise Intent -->
      <section class="glass-card">
        <div class="section-header">
          <div class="section-icon purple">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <div class="section-title-group">
            <h3 class="section-title">Valuation & Fundraise Intent</h3>
            <p class="section-description">Your proposed valuation and funding requirements</p>
          </div>
        </div>
        <div class="form-grid grid-3">
          <div class="form-group">
            <label class="form-label">Pre-Money Valuation Ask</label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="e.g. 7,50,00,000" name="valuationAsk" id="valuationAsk">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Target Fundraise Amount</label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="0" name="fundraiseTarget">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Previous Capital Raised</label>
            <div class="input-with-prefix">
              <span class="input-prefix">₹</span>
              <input type="text" class="glow-input has-prefix" placeholder="0" name="previousRaised">
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
          <label class="form-label">Valuation Basis (select all that apply)</label>
          <div class="multi-select-grid">
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="market-comparables">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Market comparables</span>
            </label>
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="traction-users">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Traction & users</span>
            </label>
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="revenue">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Revenue</span>
            </label>
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="technology-ip">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Technology / IP</span>
            </label>
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="team-experience">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Team experience</span>
            </label>
            <label class="multi-select-item">
              <input type="checkbox" name="valuationBasis[]" value="market-opportunity">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">Market opportunity</span>
            </label>
          </div>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
          <label class="form-label">
            Valuation Justification
            <button type="button" class="tooltip-trigger" data-tooltip="Required: Provide context for why you believe this valuation is appropriate">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
              </svg>
            </button>
          </label>
          <textarea class="glow-textarea" placeholder="Explain the rationale behind your valuation ask. Reference comparable companies, market multiples, or unique value drivers..." name="justification" id="justificationTextarea" maxlength="1000"></textarea>
          <div style="display: flex; justify-content: space-between; margin-top: 6px; font-size: 0.75rem; color: #94a3b8;">
            <span>Min 50 characters required</span>
            <span id="justificationCharCount">0 / 50</span>
          </div>
        </div>
      </section>

      <!-- Section 5: Document Verification -->
      <section class="glass-card">
        <div class="section-header">
          <div class="section-icon blue">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>
            </svg>
          </div>
          <div class="section-title-group">
            <h3 class="section-title">Document Verification</h3>
            <p class="section-description">Upload required documents for due diligence</p>
          </div>
        </div>
        <div class="form-grid grid-2">
          <div class="file-upload" id="pitchDeckUpload">
            <div class="file-upload-content">
              <div class="file-upload-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
              </div>
              <p class="file-upload-title">Drop file here or click to upload</p>
              <p class="file-upload-hint">PDF only, max 25MB</p>
            </div>
            <div class="file-upload-preview" style="display: none;">
              <div class="file-info">
                <div class="file-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                  </svg>
                </div>
                <div class="file-details">
                  <p class="file-name"></p>
                  <p class="file-size"></p>
                </div>
              </div>
              <div class="file-actions">
                <svg class="file-check" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <button type="button" class="file-remove">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                  </svg>
                </button>
              </div>
            </div>
            <input type="file" accept=".pdf" class="file-input" name="pitchDeck">
            <label class="form-label" style="position: absolute; top: -28px; left: 0;">Pitch Deck</label>
          </div>

          <div class="file-upload" id="financialDocsUpload">
            <div class="file-upload-content">
              <div class="file-upload-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
              </div>
              <p class="file-upload-title">Drop file here or click to upload</p>
              <p class="file-upload-hint">PDF or Excel, max 25MB</p>
            </div>
            <div class="file-upload-preview" style="display: none;">
              <div class="file-info">
                <div class="file-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                  </svg>
                </div>
                <div class="file-details">
                  <p class="file-name"></p>
                  <p class="file-size"></p>
                </div>
              </div>
              <div class="file-actions">
                <svg class="file-check" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <button type="button" class="file-remove">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                  </svg>
                </button>
              </div>
            </div>
            <input type="file" accept=".pdf,.xlsx,.xls" class="file-input" name="financialDocs">
            <label class="form-label" style="position: absolute; top: -28px; left: 0;">Financial Statements / Ownership Proof</label>
          </div>
        </div>
      </section>

      <!-- Section 6: Valuation Awareness Card -->
      <section class="awareness-card">
        <div class="awareness-content">
          <div class="awareness-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
            </svg>
          </div>
          <div class="awareness-text">
            <h4 class="awareness-title">
              Valuation Context
            </h4>
            <div id="aiAdviceOutput">
              <p class="awareness-description">
                Based on your inputs, startups at this stage usually fall within a certain valuation range. 
                Your final valuation will be reviewed by our investment team and may be subject to adjustment 
                based on market conditions and due diligence.
              </p>
            </div>
          </div>
        </div>
        <div class="awareness-indicator">
          <div class="indicator-bar">
            <div class="indicator-fill" id="aiConfidenceBar"></div>
          </div>
          <span class="indicator-label" id="aiIndicatorLabel">Advisory only</span>
        </div>
      </section>

      <!-- Action Buttons -->
      <div class="action-area">
        <div class="action-buttons">
          <button type="button" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>
            </svg>
            Edit Details
          </button>
          <button type="submit" class="btn btn-primary">
            Confirm & Continue
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
            </svg>
          </button>
        </div>
        <p class="disclaimer">
          Valuation does not guarantee investor acceptance or returns. All submissions are subject to 
          verification and due diligence by our investment team.
        </p>
      </div>
    </form>
  </div>

  <!-- Tooltip -->
  <div class="tooltip" id="tooltip"></div>

  <script src="../js/valuationVerifyScript.js"></script>
</body>
</html>