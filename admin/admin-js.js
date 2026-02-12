// ==========================================
// 1. ORIGINAL DUMMY DATA (Preserved)
// ==========================================

const pitches = [
  {
    id: "PIT001",
    startupName: "TechVenture AI",
    founderName: "Rahul Sharma",
    founderEmail: "rahul@techventure.ai",
    category: "Artificial Intelligence",
    fundingAsk: 50000000,
    status: "pending",
    submissionDate: "2024-01-15",
    description:
      "An AI-powered platform for automating business processes using machine learning and natural language processing.",
    attachments: ["pitch_deck.pdf", "financials.xlsx"],
  },
  {
    id: "PIT002",
    startupName: "GreenEnergy Solutions",
    founderName: "Priya Patel",
    founderEmail: "priya@greenenergy.in",
    category: "Clean Energy",
    fundingAsk: 75000000,
    status: "approved",
    submissionDate: "2024-01-12",
    description:
      "Developing affordable solar energy solutions for rural India.",
    attachments: ["pitch_deck.pdf", "market_analysis.pdf"],
  },
  {
    id: "PIT003",
    startupName: "HealthFirst",
    founderName: "Dr. Amit Kumar",
    founderEmail: "amit@healthfirst.com",
    category: "Healthcare",
    fundingAsk: 100000000,
    status: "approved",
    submissionDate: "2024-01-10",
    description: "Telemedicine platform connecting patients with specialists.",
    attachments: ["pitch_deck.pdf"],
  },
  {
    id: "PIT004",
    startupName: "EduLearn Pro",
    founderName: "Sneha Reddy",
    founderEmail: "sneha@edulearn.pro",
    category: "EdTech",
    fundingAsk: 30000000,
    status: "rejected",
    submissionDate: "2024-01-08",
    description: "Personalized learning platform for K-12 students.",
    attachments: ["pitch_deck.pdf", "demo_video.mp4"],
  },
  {
    id: "PIT005",
    startupName: "FinFlow",
    founderName: "Vikram Singh",
    founderEmail: "vikram@finflow.io",
    category: "FinTech",
    fundingAsk: 80000000,
    status: "pending",
    submissionDate: "2024-01-18",
    description: "Digital payments and lending platform for small businesses.",
    attachments: ["pitch_deck.pdf", "financials.xlsx"],
  },
];

// NOTE: 'const entrepreneurs' REMOVED. It now comes from PHP.

const transactions = [
  {
    id: "TXN001",
    userType: "Investor",
    userName: "Ananya Gupta",
    type: "credit",
    amount: 500000,
    reason: "Wallet Top-up",
    status: "completed",
    date: "2024-01-20",
  },
  {
    id: "TXN002",
    userType: "Entrepreneur",
    userName: "Rahul Sharma",
    type: "debit",
    amount: 50000,
    reason: "Pitch Submission Fee",
    status: "completed",
    date: "2024-01-19",
  },
  {
    id: "TXN003",
    userType: "Investor",
    userName: "Rajesh Mehta",
    type: "debit",
    amount: 2500000,
    reason: "Investment - TechVenture AI",
    status: "completed",
    date: "2024-01-18",
  },
  {
    id: "TXN004",
    userType: "Investor",
    userName: "Kavita Iyer",
    type: "credit",
    amount: 1000000,
    reason: "Wallet Top-up",
    status: "pending",
    date: "2024-01-20",
  },
  {
    id: "TXN005",
    userType: "Platform",
    userName: "SmartPitchHub",
    type: "credit",
    amount: 125000,
    reason: "Commission - Investment",
    status: "completed",
    date: "2024-01-18",
  },
];
function renderEntrepreneursTable() {
  const tbody = document.getElementById("entrepreneursTable");
  if (!tbody) return;

  // Use the global 'entrepreneurs' variable that comes from your PHP file
  // Check if variable exists
  if (typeof entrepreneurs === "undefined" || !entrepreneurs.length) {
    tbody.innerHTML =
      '<tr><td colspan="7" style="text-align:center; padding:20px;">No entrepreneurs found.</td></tr>';
    return;
  }

  tbody.innerHTML = entrepreneurs
    .map(
      (ent) => `
    <tr>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div class="avatar avatar-orange" style="width: 2.5rem; height: 2.5rem; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#fff7ed; color:#ea580c; font-weight:bold;">
             ${ent.name ? ent.name.charAt(0).toUpperCase() : "U"}
          </div>
          <div>
            <div class="font-medium">${ent.name}</div>
            <div class="text-xs text-muted" style="color:#64748b;">${
              ent.email
            }</div>
          </div>
        </div>
      </td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; 
           background-color: ${
             ent.kycStatus === "verified"
               ? "#dcfce7"
               : ent.kycStatus === "pending"
                 ? "#fef9c3"
                 : "#fee2e2"
           };
           color: ${
             ent.kycStatus === "verified"
               ? "#166534"
               : ent.kycStatus === "pending"
                 ? "#854d0e"
                 : "#991b1b"
           };">
            ${ent.kycStatus.toUpperCase()}
        </span>
      </td>
      <td class="font-medium">₹${ent.walletBalance.toLocaleString("en-IN")}</td>
      <td style="text-align: center;">${ent.totalPitches}</td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500;
           background-color: ${
             ent.accountStatus === "active" ? "#dbeafe" : "#fee2e2"
           };
           color: ${ent.accountStatus === "active" ? "#1e40af" : "#991b1b"};">
            ${
              ent.accountStatus.charAt(0).toUpperCase() +
              ent.accountStatus.slice(1)
            }
        </span>
      </td>
      <td style="text-align: right;">
         <button class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border:1px solid #e2e8f0; border-radius:4px; background:transparent; cursor:pointer;">View</button>
      </td>
    </tr>
  `,
    )
    .join("");
}
const bidPackages = [
  {
    id: "BID001",
    name: "Starter Pack",
    price: 10000,
    bidCount: 10,
    status: "active",
    description: "Perfect for new investors",
  },
  {
    id: "BID002",
    name: "Growth Pack",
    price: 45000,
    bidCount: 50,
    status: "active",
    description: "Best value for active investors",
  },
  {
    id: "BID003",
    name: "Premium Pack",
    price: 80000,
    bidCount: 100,
    status: "active",
    description: "For serious investors",
  },
  {
    id: "BID004",
    name: "Enterprise Pack",
    price: 150000,
    bidCount: 200,
    status: "inactive",
    description: "Unlimited access",
  },
];

const investmentsList = [
  {
    id: "INVEST001",
    investorName: "Ananya Gupta",
    pitchName: "TechVenture AI",
    amount: 2500000,
    date: "2024-01-15",
    status: "completed",
  },
  {
    id: "INVEST002",
    investorName: "Rajesh Mehta",
    pitchName: "GreenEnergy Solutions",
    amount: 5000000,
    date: "2024-01-12",
    status: "completed",
  },
  {
    id: "INVEST003",
    investorName: "Suresh Nair",
    pitchName: "HealthFirst",
    amount: 7500000,
    date: "2024-01-10",
    status: "completed",
  },
  {
    id: "INVEST004",
    investorName: "Kavita Iyer",
    pitchName: "AgriTech India",
    amount: 1500000,
    date: "2024-01-08",
    status: "pending",
  },
];

const tickets = [
  {
    id: "TKT001",
    subject: "Unable to submit pitch",
    userName: "Rahul Sharma",
    userType: "Entrepreneur",
    priority: "high",
    status: "open",
    createdAt: "2024-01-20 10:30",
    messages: [
      {
        sender: "user",
        message: "I'm getting an error when trying to submit my pitch deck.",
        time: "10:30",
      },
      {
        sender: "support",
        message: "Could you please share a screenshot of the error?",
        time: "11:00",
      },
    ],
  },
  {
    id: "TKT002",
    subject: "KYC verification taking too long",
    userName: "Kavita Iyer",
    userType: "Investor",
    priority: "medium",
    status: "in_progress",
    createdAt: "2024-01-19 15:45",
    messages: [
      {
        sender: "user",
        message: "My KYC has been pending for 5 days now.",
        time: "15:45",
      },
    ],
  },
];

// ==========================================
// 2. UTILITY FUNCTIONS
// ==========================================

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 0,
  }).format(amount);
}

function getStatusBadge(status) {
  const classes = {
    pending: "badge-warning",
    approved: "badge-success",
    rejected: "badge-destructive",
    completed: "badge-success",
    active: "badge-success",
    suspended: "badge-destructive",
    verified: "badge-success",
    open: "badge-destructive",
    in_progress: "badge-warning",
    resolved: "badge-success",
    inactive: "badge-secondary",
  };
  return `<span class="badge ${
    classes[status] || "badge-secondary"
  }">${status.replace("_", " ")}</span>`;
}

function getPriorityBadge(priority) {
  const classes = {
    high: "badge-destructive",
    medium: "badge-warning",
    low: "badge-secondary",
  };
  return `<span class="badge ${
    classes[priority] || "badge-secondary"
  }">${priority}</span>`;
}

// ==========================================
// 3. NAVIGATION & UI
// ==========================================

function showPage(pageId) {
  // Hide all pages
  document.querySelectorAll(".page").forEach((p) => {
    p.style.display = "none";
    p.classList.remove("active");
  });

  // Remove active from nav
  document
    .querySelectorAll(".nav-item")
    .forEach((n) => n.classList.remove("active"));

  // Show target page
  const target = document.getElementById("page-" + pageId);
  if (target) {
    target.style.display = "block";
    target.classList.add("active");
  }

  // Highlight Nav
  const navBtn = document.querySelector(`[onclick="showPage('${pageId}')"]`);
  if (navBtn) navBtn.classList.add("active");

  // Update Title
  const titles = {
    dashboard: "Dashboard",
    pitches: "Pitch Management",
    entrepreneurs: "Entrepreneurs",
    investors: "Investors",
    wallet: "Wallet & Transactions",
    bids: "Bid Packages",
    investments: "Investments",
    analytics: "Analytics & Reports",
    support: "Support & Tickets",
    settings: "Settings",
  };
  const pageTitle = document.getElementById("pageTitle");
  if (pageTitle) pageTitle.textContent = titles[pageId] || "Dashboard";

  // Init Charts if Analytics
  if (pageId === "analytics") initAnalyticsCharts();

  // Re-render dynamic tables to ensure fresh data
  if (pageId === "entrepreneurs") renderEntrepreneursTable();
  if (pageId === "investors") renderInvestorsTable();
}

function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("collapsed");
}

function toggleMobileSidebar() {
  document.getElementById("sidebar").classList.toggle("mobile-open");
}

function openModal(modalId) {
  document.getElementById(modalId).classList.add("active");
}
function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active");
}

function showToast(title, desc) {
  const container = document.getElementById("toastContainer");
  if (!container) return;
  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerHTML = `<div><div class="toast-title">${title}</div><div class="toast-desc">${desc}</div></div>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// ==========================================
// 4. PITCH MANAGEMENT LOGIC (Preserved)
// ==========================================

function renderPitchesTable(data) {
  const tbody = document.getElementById("pitchesTable");
  if (!tbody) return;
  tbody.innerHTML = data
    .map(
      (p) => `
        <tr>
          <td style="font-family: monospace; font-size: 13px;">${p.id}</td>
          <td style="font-weight: 500;">${p.startupName}</td>
          <td>${p.founderName}</td>
          <td><span class="badge badge-outline">${p.category}</span></td>
          <td>${formatCurrency(p.fundingAsk)}</td>
          <td>
            <div style="display: flex; align-items: center; gap: 8px;">
               <div style="width: 40px; height: 4px; background: #eee; border-radius: 2px; overflow: hidden;">
                  <div style="width: ${p.warzoneScore === "N/A" ? 0 : p.warzoneScore}%; height: 100%; background: ${p.warzoneScore > 70 ? "#14b8a6" : p.warzoneScore > 40 ? "#f59e0b" : "#ef4444"};"></div>
               </div>
               <span style="font-weight: 700; font-size: 11px; color: ${p.warzoneScore === "N/A" ? "#64748b" : "#0f172a"}">
                  ${p.warzoneScore}${p.warzoneScore === "N/A" ? "" : "%"}
               </span>
            </div>
          </td>
          <td>${getStatusBadge(p.status)}</td>
          <td style="color: var(--muted-foreground);">${p.submissionDate}</td>
          <td style="text-align: right;">
            <a href="viewPitchDetail.php?id=${p.db_id}" class="btn btn-ghost btn-icon" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
          </td>
        </tr>
      `,
    )
    .join("");
  const countEl = document.getElementById("pitchCount");
  if (countEl) countEl.textContent = data.length;
}

function filterPitches() {
  const searchEl = document.getElementById("pitchSearch");
  const statusEl = document.getElementById("pitchStatusFilter");
  if (!searchEl || !statusEl) return;

  const search = searchEl.value.toLowerCase();
  const status = statusEl.value;
  const filtered = pitches.filter((p) => {
    const matchesSearch =
      p.startupName.toLowerCase().includes(search) ||
      p.founderName.toLowerCase().includes(search) ||
      p.id.toLowerCase().includes(search);
    const matchesStatus = status === "all" || p.status === status;
    return matchesSearch && matchesStatus;
  });
  renderPitchesTable(filtered);
}

function viewPitch(id) {
  const p = pitches.find((x) => x.id === id);
  if (!p) return;

  // (Your modal population logic here...)
  // For brevity in this merge, assuming modals exist in HTML
  openModal("viewPitchModal");
}

let currentConfirmAction = null;
function confirmAction(action, id, name) {
  currentConfirmAction = { action, id, name };
  openModal("confirmModal");
}

// ==========================================
// 5. DYNAMIC TABLES (UPDATED FROM PHP)
// ==========================================

function renderEntrepreneursTable() {
  const tbody = document.getElementById("entrepreneursTable");
  if (!tbody) return;

  // Use GLOBAL 'entrepreneurs' from PHP
  if (typeof entrepreneurs === "undefined") return;

  tbody.innerHTML = entrepreneurs
    .map(
      (ent) => `
    <tr>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div class="avatar avatar-orange" style="width: 2.5rem; height: 2.5rem; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#fff7ed; color:#ea580c; font-weight:bold;">
             ${ent.name ? ent.name.charAt(0).toUpperCase() : "U"}
          </div>
          <div>
            <div class="font-medium">${ent.name}</div>
            <div class="text-xs text-muted" style="color:#64748b;">${
              ent.email
            }</div>
          </div>
        </div>
      </td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; 
           background-color: ${
             ent.kycStatus === "verified"
               ? "#dcfce7"
               : ent.kycStatus === "pending"
                 ? "#fef9c3"
                 : "#fee2e2"
           };
           color: ${
             ent.kycStatus === "verified"
               ? "#166534"
               : ent.kycStatus === "pending"
                 ? "#854d0e"
                 : "#991b1b"
           };">
            ${ent.kycStatus.toUpperCase()}
        </span>
      </td>
      <td class="font-medium">₹${ent.walletBalance.toLocaleString("en-IN")}</td>
      <td style="text-align: center;">${ent.totalPitches}</td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500;
           background-color: ${
             ent.accountStatus === "active" ? "#dbeafe" : "#fee2e2"
           };
           color: ${ent.accountStatus === "active" ? "#1e40af" : "#991b1b"};">
            ${
              ent.accountStatus.charAt(0).toUpperCase() +
              ent.accountStatus.slice(1)
            }
        </span>
      </td>
      <td style="text-align: right;">
         <button class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border:1px solid #e2e8f0; border-radius:4px; background:transparent; cursor:pointer;">View</button>
      </td>
    </tr>
  `,
    )
    .join("");
}

function renderInvestorsTable() {
  const tbody = document.getElementById("investorsTable");
  if (!tbody) return;

  // Use GLOBAL 'investors' from PHP
  if (typeof investors === "undefined") return;

  tbody.innerHTML = investors
    .map(
      (inv) => `
    <tr>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div class="avatar avatar-green" style="width: 2.5rem; height: 2.5rem; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#f0fdf4; color:#16a34a; font-weight:bold;">
             ${inv.name ? inv.name.charAt(0).toUpperCase() : "U"}
          </div>
          <div>
            <div class="font-medium">${inv.name}</div>
            <div class="text-xs text-muted" style="color:#64748b;">${
              inv.email
            }</div>
          </div>
        </div>
      </td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; 
           background-color: ${
             inv.kycStatus === "verified" ? "#dcfce7" : "#fef9c3"
           };
           color: ${inv.kycStatus === "verified" ? "#166534" : "#854d0e"};">
            ${inv.kycStatus.toUpperCase()}
        </span>
      </td>
      <td class="font-medium">₹${inv.walletBalance.toLocaleString("en-IN")}</td>
      <td style="text-align: center;">${inv.totalInvestments}</td>
      <td style="text-align: center;">${inv.bidsPurchased}</td>
      <td>
        <span class="badge" style="padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500;
           background-color: ${
             inv.accountStatus === "active" ? "#dbeafe" : "#fee2e2"
           };
           color: ${inv.accountStatus === "active" ? "#1e40af" : "#991b1b"};">
            ${
              inv.accountStatus.charAt(0).toUpperCase() +
              inv.accountStatus.slice(1)
            }
        </span>
      </td>
      <td style="text-align: right;">
         <button class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border:1px solid #e2e8f0; border-radius:4px; background:transparent; cursor:pointer;">View</button>
      </td>
    </tr>
  `,
    )
    .join("");
}

// ==========================================
// 6. OTHER RENDERERS (Transactions, Packages, etc.)
// ==========================================

function renderTransactionsTable() {
  const tbody = document.getElementById("transactionsTable");
  if (!tbody) return;
  tbody.innerHTML = transactions
    .map(
      (t) => `
        <tr>
          <td style="font-family: monospace; font-size: 13px;">${t.id}</td>
          <td><span class="badge badge-outline">${t.userType}</span></td>
          <td style="font-weight: 500;">${t.userName}</td>
          <td><div style="display: flex; align-items: center; gap: 8px;"><span style="color: ${
            t.type === "credit" ? "var(--success)" : "var(--destructive)"
          };">${t.type === "credit" ? "↓" : "↑"} ${t.type}</span></div></td>
          <td style="font-weight: 500; color: ${
            t.type === "credit" ? "var(--success)" : "var(--destructive)"
          };">${t.type === "credit" ? "+" : "-"}${formatCurrency(t.amount)}</td>
          <td>${t.reason}</td>
          <td>${getStatusBadge(t.status)}</td>
          <td style="color: var(--muted-foreground);">${t.date}</td>
        </tr>
      `,
    )
    .join("");
}

function renderPackagesGrid() {
  const grid = document.getElementById("packagesGrid");
  if (!grid) return;
  grid.innerHTML = bidPackages
    .map(
      (p) => `
        <div class="card package-card ${
          p.status === "inactive" ? "inactive" : ""
        }">
          <div class="package-header">
            <div class="package-icon">...</div>
            <div class="toggle ${
              p.status === "active" ? "active" : ""
            }" onclick="this.classList.toggle('active')"></div>
          </div>
          <div class="package-title">${p.name}</div>
          <div class="package-desc">${p.description}</div>
          <div class="package-details">
            <div class="package-row"><span class="package-label">Price</span><span class="package-value">${formatCurrency(
              p.price,
            )}</span></div>
            <div class="package-row"><span class="package-label">Bids Included</span><span class="package-value">${
              p.bidCount
            }</span></div>
          </div>
          <button class="btn btn-outline" style="width: 100%; margin-top: 16px;">Edit Package</button>
        </div>
      `,
    )
    .join("");
}

function renderInvestmentsTable() {
  const tbody = document.getElementById("investmentsTable");
  if (!tbody) return;
  tbody.innerHTML = investmentsList
    .map(
      (i) => `
        <tr>
          <td style="font-family: monospace; font-size: 13px;">${i.id}</td>
          <td style="font-weight: 500;">${i.investorName}</td>
          <td>${i.pitchName}</td>
          <td style="font-weight: 500; color: var(--success);">${formatCurrency(
            i.amount,
          )}</td>
          <td style="color: var(--muted-foreground);">${i.date}</td>
          <td>${getStatusBadge(i.status)}</td>
        </tr>
      `,
    )
    .join("");
}

function renderTickets() {
  const list = document.getElementById("ticketList");
  if (!list) return;
  list.innerHTML = tickets
    .map(
      (t, i) => `
        <div class="ticket-item ${
          i === 0 ? "active" : ""
        }" onclick="selectTicket(${i})">
          <div class="ticket-header"><span class="ticket-id">${
            t.id
          }</span>${getPriorityBadge(t.priority)}</div>
          <div class="ticket-subject">${t.subject}</div>
          <div class="ticket-footer"><span class="ticket-user">${
            t.userName
          }</span>${getStatusBadge(t.status)}</div>
        </div>
      `,
    )
    .join("");
  selectTicket(0);
}

function selectTicket(index) {
  // Simple toggle logic
  document
    .querySelectorAll(".ticket-item")
    .forEach((el, i) => el.classList.toggle("active", i === index));
}

// ==========================================
// 7. CHARTS (Chart.js)
// ==========================================

function initDashboardChart() {
  const canvas = document.getElementById("investmentChart");
  if (!canvas) return;
  const ctx = canvas.getContext("2d");
  new Chart(ctx, {
    type: "line",
    data: {
      labels: [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
      ],
      datasets: [
        {
          label: "Investment (Cr)",
          data: [12.5, 18, 22, 19.5, 31, 28.5, 35, 42, 38, 45, 52, 62],
          borderColor: "#14b8a6",
          backgroundColor: "rgba(20, 184, 166, 0.1)",
          fill: true,
          tension: 0.4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
    },
  });
}

function initAnalyticsCharts() {
  // (Include your other charts here if needed, keeping it brief for the file size)
  const lineCanvas = document.getElementById("analyticsLineChart");
  if (lineCanvas) {
    new Chart(lineCanvas.getContext("2d"), {
      type: "line",
      data: {
        labels: ["Jan", "Feb", "Mar"],
        datasets: [
          {
            label: "Investment",
            data: [12, 18, 22],
            borderColor: "#14b8a6",
            fill: true,
          },
        ],
      },
      options: { responsive: true, maintainAspectRatio: false },
    });
  }
}

// ==========================================
// 8. INITIALIZATION
// ==========================================

document.addEventListener("DOMContentLoaded", () => {
  // Static
  renderPitchesTable(pitches);
  renderTransactionsTable();
  renderPackagesGrid();
  renderInvestmentsTable();
  renderTickets();

  // Dynamic
  if (typeof renderEntrepreneursTable === "function")
    renderEntrepreneursTable();
  if (typeof renderInvestorsTable === "function") renderInvestorsTable();

  // Charts
  initDashboardChart();

  // Search Filter Global Function
  window.filterTable = function (tableId, query) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.getElementsByTagName("tr");
    const lowerQuery = query.toLowerCase();

    for (let i = 0; i < rows.length; i++) {
      const nameCell = rows[i].getElementsByTagName("td")[1]; // Assume Name is in 2nd column
      if (nameCell) {
        const textValue = nameCell.textContent || nameCell.innerText;
        rows[i].style.display = textValue.toLowerCase().includes(lowerQuery)
          ? ""
          : "none";
      }
    }
  };
});

// Close modals overlay
document.querySelectorAll(".modal-overlay").forEach((overlay) => {
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) overlay.classList.remove("active");
  });
});
