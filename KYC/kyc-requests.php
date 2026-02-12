<?php
session_start();
require_once '../db.php'; 

// 1. Security Check (Admin Only)
if (!isset($_SESSION['admin_id'])) {
    // header("Location: ../admin/index.php");
    // exit;
}

$requests = [];

// --- FETCH ENTREPRENEURS ---
$sql_ent = "SELECT 
            k.id, 
            k.full_name, 
            e.email, 
            k.submission_date as date_added, 
            k.status, 
            'Entrepreneur' as user_role 
        FROM entrepreneur_kyc_details k
        JOIN entrepreneurs e ON k.entrepreneur_id = e.id";

if ($result = $conn->query($sql_ent)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

// --- FETCH INVESTORS (FIXED) ---
// UPDATED: Using 'submission_date' instead of 'created_at' to match your schema
$sql_inv = "SELECT 
            k.id, 
            i.name as full_name, 
            i.email, 
            k.submission_date as date_added, 
            k.status, 
            'Investor' as user_role 
        FROM investor_kyc_details k
        JOIN investors i ON k.investor_id = i.id";

if ($result = $conn->query($sql_inv)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

// --- SORT BY DATE (Newest First) ---
usort($requests, function($a, $b) {
    return strtotime($b['date_added']) - strtotime($a['date_added']);
});

// --- FORMAT DATA FOR DISPLAY ---
foreach ($requests as &$r) {
    // Determine prefix based on role
    $prefix = ($r['user_role'] == 'Investor') ? 'INV-' : 'ENT-';
    $r['formatted_id'] = $prefix . str_pad($r['id'], 5, '0', STR_PAD_LEFT);
    
    // Format Date
    $r['formatted_date'] = date("d M Y", strtotime($r['date_added']));
}
unset($r); 

// 3. Calculate Stats
$pending = 0; $approved = 0; $rejected = 0;
foreach($requests as $r) {
    if($r['status'] == 'pending' || $r['status'] == 'under_review') $pending++;
    elseif($r['status'] == 'approved') $approved++;
    elseif($r['status'] == 'rejected') $rejected++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KYC Requests - SmartPitchHub Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --background: hsl(210, 20%, 98%); --foreground: hsl(222, 47%, 11%); --card: hsl(0, 0%, 100%); --card-foreground: hsl(222, 47%, 11%); --primary: hsl(221, 83%, 53%); --primary-foreground: hsl(210, 40%, 98%); --secondary: hsl(210, 40%, 96%); --muted: hsl(210, 40%, 96%); --muted-foreground: hsl(215, 16%, 47%); --accent: hsl(210, 40%, 96%); --accent-foreground: hsl(222, 47%, 11%); --border: hsl(214, 32%, 91%); --success: hsl(142, 71%, 45%); --warning: hsl(38, 92%, 50%); --destructive: hsl(0, 84%, 60%); }
    body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--foreground); line-height: 1.5; min-height: 100vh; }
    .header { position: sticky; top: 0; z-index: 40; background-color: var(--card); border-bottom: 1px solid var(--border); }
    .header-content { max-width: 1280px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
    .logo-section { display: flex; align-items: center; gap: 0.75rem; }
    .logo-icon { width: 40px; height: 40px; border-radius: 12px; background-color: hsla(221, 83%, 53%, 0.1); display: flex; align-items: center; justify-content: center; }
    .logo-icon svg { width: 20px; height: 20px; color: var(--primary); }
    .logo-text h1 { font-size: 1.125rem; font-weight: 700; color: var(--foreground); }
    .logo-text p { font-size: 0.75rem; color: var(--muted-foreground); }
    .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); background-color: var(--card); font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); text-decoration: none; transition: all 0.2s; }
    .back-btn:hover { background-color: var(--secondary); color: var(--foreground); }
    .main-content { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; }
    .page-header { margin-bottom: 2rem; }
    .page-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--foreground); }
    .page-header p { color: var(--muted-foreground); margin-top: 0.25rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background-color: var(--card); border-radius: 16px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid var(--border); }
    .stat-card-content { display: flex; align-items: center; justify-content: space-between; }
    .stat-label { font-size: 0.875rem; color: var(--muted-foreground); }
    .stat-value { font-size: 1.875rem; font-weight: 700; margin-top: 0.25rem; }
    .stat-value.pending { color: var(--warning); }
    .stat-value.approved { color: var(--success); }
    .stat-value.rejected { color: var(--destructive); }
    .stat-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .stat-icon.pending { background-color: hsla(38, 92%, 50%, 0.1); }
    .stat-icon.approved { background-color: hsla(142, 71%, 45%, 0.1); }
    .stat-icon.rejected { background-color: hsla(0, 84%, 60%, 0.1); }
    .stat-dot { width: 12px; height: 12px; border-radius: 50%; }
    .stat-dot.pending { background-color: var(--warning); }
    .stat-dot.approved { background-color: var(--success); }
    .stat-dot.rejected { background-color: var(--destructive); }
    .filters { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .filters-left { display: flex; align-items: center; gap: 0.75rem; }
    .search-input { position: relative; }
    .search-input svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted-foreground); }
    .search-input input { padding: 0.5rem 1rem 0.5rem 2.5rem; border-radius: 8px; border: 1px solid var(--border); background-color: var(--card); font-size: 0.875rem; width: 288px; outline: none; font-family: inherit; }
    .search-input input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px hsla(221, 83%, 53%, 0.15); }
    .filter-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); background-color: var(--card); font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); cursor: pointer; font-family: inherit; transition: background-color 0.2s; }
    .filter-btn:hover { background-color: var(--secondary); }
    .filter-btn svg { width: 16px; height: 16px; }
    .results-count { font-size: 0.875rem; color: var(--muted-foreground); }
    .table-container { background-color: var(--card); border-radius: 16px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid var(--border); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    thead tr { background-color: hsla(210, 40%, 96%, 0.5); border-bottom: 1px solid var(--border); }
    th { text-align: left; padding: 1rem 1.5rem; font-size: 0.75rem; font-weight: 600; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.05em; }
    th:last-child { text-align: right; }
    tbody tr { border-bottom: 1px solid var(--border); transition: background-color 0.2s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background-color: hsla(210, 40%, 96%, 0.3); }
    td { padding: 1rem 1.5rem; }
    td:last-child { text-align: right; }
    .kyc-id { font-size: 0.875rem; font-weight: 500; color: var(--foreground); }
    .user-name { font-size: 0.875rem; font-weight: 500; color: var(--foreground); }
    .user-email { font-size: 0.75rem; color: var(--muted-foreground); }
    .role-badge { display: inline-flex; align-items: center; padding: 0.25rem 0.625rem; border-radius: 9999px; background-color: var(--accent); color: var(--accent-foreground); font-size: 0.75rem; font-weight: 500; }
    .date { font-size: 0.875rem; color: var(--muted-foreground); }
    .status-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; text-transform: capitalize; }
    .status-badge.pending, .status-badge.under_review { background-color: hsla(38, 92%, 50%, 0.1); color: hsl(38, 92%, 40%); }
    .status-badge.approved { background-color: hsla(142, 71%, 45%, 0.1); color: hsl(142, 71%, 35%); }
    .status-badge.rejected { background-color: hsla(0, 84%, 60%, 0.1); color: hsl(0, 84%, 50%); }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }
    .status-badge.pending .status-dot, .status-badge.under_review .status-dot { background-color: var(--warning); }
    .status-badge.approved .status-dot { background-color: var(--success); }
    .status-badge.rejected .status-dot { background-color: var(--destructive); }
    .view-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background-color: var(--primary); color: var(--primary-foreground); border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; font-family: inherit; transition: background-color 0.2s; }
    .view-btn:hover { background-color: hsla(221, 83%, 53%, 0.9); }
    .view-btn svg { width: 16px; height: 16px; }
    .footer { text-align: center; margin-top: 2rem; font-size: 0.75rem; color: var(--muted-foreground); }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .filters { flex-direction: column; align-items: stretch; gap: 1rem; } .filters-left { flex-direction: column; } .search-input input { width: 100%; } .table-container { overflow-x: auto; } table { min-width: 800px; } }
  </style>
</head>
<body>
  <header class="header">
    <div class="header-content">
      <div class="logo-section">
        <div class="logo-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
          </svg>
        </div>
        <div class="logo-text">
          <h1>SmartPitchHub</h1>
          <p>Admin Portal</p>
        </div>
      </div>
      <a href="../admin/admin-dashboard.php" class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </a>
    </div>
  </header>

  <main class="main-content">
    <div class="page-header">
      <h2>KYC Requests</h2>
      <p>Review and manage user verification requests</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-content">
          <div><p class="stat-label">Pending Review</p><p class="stat-value pending" id="pending-count"><?php echo $pending; ?></p></div>
          <div class="stat-icon pending"><div class="stat-dot pending"></div></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-content">
          <div><p class="stat-label">Approved</p><p class="stat-value approved" id="approved-count"><?php echo $approved; ?></p></div>
          <div class="stat-icon approved"><div class="stat-dot approved"></div></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-content">
          <div><p class="stat-label">Rejected</p><p class="stat-value rejected" id="rejected-count"><?php echo $rejected; ?></p></div>
          <div class="stat-icon rejected"><div class="stat-dot rejected"></div></div>
        </div>
      </div>
    </div>

    <div class="filters">
      <div class="filters-left">
        <div class="search-input">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="text" placeholder="Search by name, email or ID..." id="search-input">
        </div>
        <button class="filter-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
      </div>
      <p class="results-count">Showing <span id="results-count"><?php echo count($requests); ?></span> requests</p>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>KYC ID</th>
            <th>User</th>
            <th>Role</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="requests-table"></tbody>
      </table>
    </div>

    <p class="footer">SmartPitchHub Admin Portal • KYC Verification System</p>
  </main>

  <script>
    // 1. INJECT REAL DATA FROM PHP
    const kycRequests = <?php echo json_encode($requests); ?>;

    const tableBody = document.getElementById('requests-table');
    const searchInput = document.getElementById('search-input');
    const resultsCountEl = document.getElementById('results-count');

    // Render table rows
    function renderTable(requests) {
      if(requests.length === 0) {
          tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem;">No KYC requests found.</td></tr>`;
          return;
      }

      tableBody.innerHTML = requests.map(request => `
        <tr>
          <td><span class="kyc-id">${request.formatted_id}</span></td>
          <td>
            <div>
              <p class="user-name">${request.full_name}</p>
              <p class="user-email">${request.email}</p>
            </div>
          </td>
          <td><span class="role-badge">${request.user_role}</span></td>
          <td><span class="date">${request.formatted_date}</span></td>
          <td>
            <span class="status-badge ${request.status.replace(' ', '_')}">
              <span class="status-dot"></span>
              ${request.status.replace('_', ' ')}
            </span>
          </td>
          <td>
            <button class="view-btn" onclick="viewKYC(${request.id}, '${request.user_role}')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              View KYC
            </button>
          </td>
        </tr>
      `).join('');
    }

    // Filter requests
    function filterRequests(searchTerm) {
      const term = searchTerm.toLowerCase();
      return kycRequests.filter(request => 
        request.formatted_id.toLowerCase().includes(term) ||
        request.full_name.toLowerCase().includes(term) ||
        request.email.toLowerCase().includes(term)
      );
    }

    // UPDATED: View KYC handler (Handles different pages for Ent/Inv)
    function viewKYC(id, role) {
      if (role === 'Entrepreneur') {
          // Go to your existing page
          window.location.href = 'view-kyc-detail.php?id=' + id;
      } else {
          // Go to the NEW investor page we will create next
          window.location.href = 'view-investor-kyc-detail.php?id=' + id;
      }
    }

    // Search event
    searchInput.addEventListener('input', (e) => {
      const filtered = filterRequests(e.target.value);
      renderTable(filtered);
      resultsCountEl.textContent = filtered.length;
    });

    // Initial render
    renderTable(kycRequests);
  </script>
</body>
</html>