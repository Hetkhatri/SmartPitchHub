<?php
// ==========================================
// 1. BACKEND: FETCH DATA (DO NOT ECHO HERE)
// ==========================================
session_start();
// Adjust path if your db.php is in a different folder
require_once '../db.php'; 

// Check Login Status
$user_logged_in = isset($_SESSION['user_id']) ? 'true' : 'false';

// Fetch Pitches from Database
$sql = "
    SELECT 
        p.id, 
        p.startup_name as name, 
        p.industry as category, 
        p.description, 
        p.funding_goal, 
        p.stage, 
        p.pitch_logo as logo, 
        p.likes, 
        p.views, 
        p.interested_investors as interestedInvestors, 
        p.is_approved as isAdminApproved,
        CASE WHEN ov.status = 'verified' THEN 1 ELSE 0 END as isFounderVerified
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    LEFT JOIN otp_verifications ov ON e.email = ov.email
    WHERE p.is_approved = 1
    ORDER BY p.created_at DESC
";

$result = $conn->query($sql);
$pitches_data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Format fields to match your Frontend expectations exactly
        $row['fundingRequired'] = '₹' . number_format($row['funding_goal']);
        $row['id'] = (int)$row['id'];
        $row['likes'] = (int)$row['likes'];
        $row['views'] = (int)$row['views'];
        $row['interestedInvestors'] = (int)$row['interestedInvestors'];
        $row['isAdminApproved'] = (bool)$row['isAdminApproved'];
        $row['isFounderVerified'] = (bool)$row['isFounderVerified'];
        
        $pitches_data[] = $row;
    }
}

// Store JSON in a variable (DO NOT ECHO YET)
$json_data = json_encode($pitches_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore verified startup pitches and invest with confidence on SmartPitchHub">
    <title>Explore Startup Pitches | SmartPitchHub</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    
    <style>
        /* ========================================
           SmartPitchHub - Explore Pitches Styles
           (Your Original CSS Preserved)
           ======================================== */
        :root {
            --background: hsl(230, 36%, 8%);
            --foreground: hsl(0, 0%, 100%);
            --card: hsl(230, 31%, 12%);
            --card-hover: hsl(230, 28%, 15%);
            --primary: hsl(259, 97%, 77%);
            --primary-foreground: hsl(230, 36%, 8%);
            --secondary: hsl(230, 25%, 18%);
            --muted: hsl(230, 20%, 22%);
            --muted-foreground: hsl(230, 10%, 60%);
            --border: hsl(230, 20%, 20%);
            --input: hsl(230, 25%, 15%);
            --ring: hsl(259, 97%, 77%);
            --radius: 0.75rem;
            --container-max: 1280px;
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            
            /* Badge Colors */
            --badge-blue-bg: hsl(210, 50%, 20%);
            --badge-blue-text: hsl(210, 90%, 70%);
            --badge-amber-bg: hsl(40, 50%, 20%);
            --badge-amber-text: hsl(40, 90%, 60%);
            --badge-emerald-bg: hsl(150, 50%, 15%);
            --badge-emerald-text: hsl(150, 70%, 50%);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--foreground); line-height: 1.6; }
        .container { max-width: var(--container-max); margin: 0 auto; padding: 0 1.5rem; }
        .text-primary { color: var(--primary); }
        .text-gradient { background: linear-gradient(135deg, var(--primary), hsl(280, 90%, 70%)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease forwards; opacity: 0; }

        /* Navbar */
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(13, 15, 26, 0.8); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
        .navbar-content { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 1.25rem; font-weight: 600; color: var(--foreground); }
        .logo-icon { width: 24px; height: 24px; color: var(--primary); }
        .nav-links { display: none; align-items: center; gap: 2rem; }
        .nav-links a { font-size: 0.875rem; color: var(--muted-foreground); text-decoration: none; transition: color var(--transition-fast); }
        .nav-links a:hover { color: var(--foreground); }
        .nav-actions { display: none; align-items: center; gap: 0.75rem; }
        .mobile-menu-btn { display: flex; width: 40px; height: 40px; background: transparent; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted-foreground); cursor: pointer; align-items: center; justify-content: center; }
        @media (min-width: 768px) { .nav-links, .nav-actions { display: flex; } .mobile-menu-btn { display: none; } }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 500; border-radius: var(--radius); border: none; cursor: pointer; transition: all var(--transition-fast); text-decoration: none; }
        .btn-gradient { background: linear-gradient(135deg, var(--primary), hsl(280, 80%, 60%)); color: var(--primary-foreground); box-shadow: 0 4px 20px rgba(167, 139, 250, 0.25); }
        .btn-gradient:hover { transform: scale(1.02); box-shadow: 0 6px 30px rgba(167, 139, 250, 0.4); }
        .btn-ghost { background: transparent; color: var(--foreground); }
        .btn-ghost:hover { background: var(--secondary); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--foreground); }
        .btn-outline:hover { border-color: var(--primary); background: rgba(167, 139, 250, 0.1); }
        .btn-icon { width: 40px; height: 40px; padding: 0; background: transparent; border: 1px solid var(--border); color: var(--muted-foreground); border-radius: 50%; }
        .btn-icon:hover, .btn-icon.active { border-color: var(--primary); color: var(--primary); background: rgba(167, 139, 250, 0.1); }
        .btn-icon.active svg { fill: var(--primary); }

        /* Page Header */
        .page-header { padding: 8rem 0 3rem; text-align: center; }
        .page-title { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; }
        .page-subtitle { font-size: 1.125rem; color: var(--muted-foreground); max-width: 600px; margin: 0 auto 2.5rem; }
        .search-container { position: relative; max-width: 700px; margin: 0 auto; }
        .search-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--muted-foreground); pointer-events: none; }
        .search-input { width: 100%; padding: 1rem 1.25rem 1rem 3.5rem; font-size: 1rem; background: var(--input); border: 1px solid var(--border); border-radius: var(--radius); color: var(--foreground); outline: none; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 20px rgba(167, 139, 250, 0.15); }

        /* Filter Bar */
        .filter-section { padding-bottom: 2rem; }
        .filter-bar { display: flex; flex-wrap: wrap; gap: 1rem; padding: 1rem; background: rgba(30, 34, 53, 0.5); border: 1px solid var(--border); border-radius: var(--radius); }
        .filter-group { display: flex; flex-direction: column; gap: 0.375rem; flex: 1; min-width: 140px; }
        .filter-label { font-size: 0.75rem; font-weight: 500; color: var(--muted-foreground); }
        .select-wrapper { position: relative; }
        .filter-select { width: 100%; padding: 0.625rem 2.5rem 0.625rem 1rem; font-size: 0.875rem; background: var(--input); border: 1px solid var(--border); border-radius: var(--radius); color: var(--foreground); cursor: pointer; appearance: none; outline: none; }
        .select-arrow { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--muted-foreground); pointer-events: none; }

        /* Cards Grid */
        .cards-section { padding-bottom: 5rem; }
        .cards-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .cards-grid { grid-template-columns: repeat(3, 1fr); } }

        /* Pitch Card */
        .pitch-card { display: flex; flex-direction: column; padding: 1.5rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); transition: all var(--transition-normal); }
        .pitch-card:hover { border-color: rgba(167, 139, 250, 0.4); box-shadow: 0 0 40px rgba(167, 139, 250, 0.12); transform: translateY(-4px); }
        .card-header { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
        .card-logo { width: 48px; height: 48px; border-radius: 0.75rem; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; color: var(--primary); overflow: hidden; flex-shrink: 0; }
        .card-logo img { width: 100%; height: 100%; object-fit: cover; }
        .card-info { flex: 1; min-width: 0; }
        .card-name { font-size: 1rem; font-weight: 600; margin-bottom: 0.375rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .category-badge { display: inline-flex; padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 500; background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.4); border-radius: 9999px; color: var(--primary); }
        .card-description { font-size: 0.875rem; color: var(--muted-foreground); line-height: 1.5; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
        .card-funding-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .funding-info { display: flex; flex-direction: column; }
        .funding-label { font-size: 0.75rem; color: var(--muted-foreground); }
        .funding-amount { font-size: 1.125rem; font-weight: 600; color: var(--primary); }
        
        .stage-badge { padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 9999px; border: 1px solid; }
        .stage-badge.idea { background: var(--badge-blue-bg); color: var(--badge-blue-text); border-color: rgba(96, 165, 250, 0.3); }
        .stage-badge.mvp { background: var(--badge-amber-bg); color: var(--badge-amber-text); border-color: rgba(251, 191, 36, 0.3); }
        .stage-badge.revenue { background: var(--badge-emerald-bg); color: var(--badge-emerald-text); border-color: rgba(52, 211, 153, 0.3); }

        .card-stats { display: flex; align-items: center; gap: 1rem; padding-bottom: 1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--muted-foreground); }
        .stat-item { display: flex; align-items: center; gap: 0.375rem; }
        .trust-badges { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .trust-badge { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; }
        .trust-badge.approved { color: hsl(150, 70%, 50%); }
        .trust-badge.verified { color: hsl(210, 90%, 70%); }
        
        .card-actions { display: flex; align-items: center; gap: 0.75rem; margin-top: auto; }
        .card-actions .btn-gradient { flex: 1; }

        /* Empty State & Pagination */
        .empty-state { display: none; flex-direction: column; align-items: center; justify-content: center; padding: 5rem 1rem; text-align: center; }
        .empty-icon { width: 80px; height: 80px; border-radius: 50%; background: var(--secondary); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; color: var(--muted-foreground); }
        .pagination { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 3rem; }
        .pagination .btn-icon { width: 40px; height: 40px; border-radius: var(--radius); }
        .pagination .btn-icon.active { background: var(--primary); border-color: var(--primary); color: var(--primary-foreground); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container navbar-content">
            <a href="/" class="logo">
                <svg class="logo-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>
                </svg>
                <span><span class="text-primary">Smart</span>PitchHub</span>
            </a>
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="../index.php#features">Features</a>
                <a href="../index.php#how-it-works">How It Works</a>
                <a href="../about.php">About</a>
            </div>
            <div class="nav-actions">
                <button class="btn btn-ghost">Sign In</button>
                <button class="btn btn-gradient">Get Started</button>
            </div>
        </div>
    </nav>

    <section class="page-header">
        <div class="container">
            <h1 class="page-title animate-fade-in">Explore Startup <span class="text-gradient">Pitches</span></h1>
            <p class="page-subtitle animate-fade-in" style="animation-delay: 0.1s;">Discover verified startup ideas and invest with confidence</p>
            <div class="search-container animate-fade-in" style="animation-delay: 0.2s;">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Search startups, categories, keywords…">
            </div>
        </div>
    </section>

    <section class="filter-section">
        <div class="container">
            <div class="filter-bar animate-fade-in" style="animation-delay: 0.3s;">
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <div class="select-wrapper">
                        <select id="categoryFilter" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="Fintech">Fintech</option>
                            <option value="Healthtech">Healthtech</option>
                            <option value="EdTech">Edtech</option>
                            <option value="E-commerce">E-commerce</option>
                            <option value="SaaS">SaaS</option>
                            <option value="AI/ML">AI/ML</option>
                            <option value="AgriTech">AgriTech</option>
                            <option value="Logistics">Logistics</option>
                        </select>
                        <svg class="select-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </div>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Stage</label>
                    <div class="select-wrapper">
                        <select id="stageFilter" class="filter-select">
                            <option value="">All Stages</option>
                            <option value="Idea">Idea</option>
                            <option value="MVP">MVP</option>
                            <option value="Revenue">Revenue</option>
                        </select>
                        <svg class="select-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </div>
                </div>
                <div class="filter-group" style="margin-left: auto;">
                    <label class="filter-label">Sort By</label>
                    <div class="select-wrapper">
                        <select id="sortFilter" class="filter-select">
                            <option value="recent">Most Recent</option>
                            <option value="liked">Most Liked</option>
                        </select>
                        <svg class="select-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cards-section">
        <div class="container">
            <div class="cards-grid" id="cardsGrid">
                </div>
            
            <div class="empty-state" id="emptyState">
                <div class="empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="m8 8 6 6"/></svg>
                </div>
                <h3>No pitches found</h3>
                <p>We couldn't find any startups matching your criteria. Try adjusting your filters.</p>
                <button class="btn btn-outline" id="resetFiltersBtn">Reset Filters</button>
            </div>
            
            <div class="pagination" id="pagination"></div>
        </div>
    </section>

    <script>
        // ========================================
        // 1. INITIALIZE DATA FROM PHP (Backend Integration)
        // ========================================
        
        // This injects the database rows directly into a JS variable safely
        const mockStartups = <?php echo $json_data; ?>;
        
        // Session status passed from PHP
        const isLoggedIn = <?php echo $user_logged_in; ?>;

        // ========================================
        // 2. APP STATE & LOGIC
        // ========================================
        const ITEMS_PER_PAGE = 6;
        let currentPage = 1;
        let likedCards = new Set();

        const icons = {
            heart: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>`,
            heartFilled: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>`,
            eye: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>`,
            users: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
            shieldCheck: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>`,
            badgeCheck: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/></svg>`
        };

        // DOM Elements
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const stageFilter = document.getElementById('stageFilter');
        const sortFilter = document.getElementById('sortFilter');
        const cardsGrid = document.getElementById('cardsGrid');
        const emptyState = document.getElementById('emptyState');
        const pagination = document.getElementById('pagination');
        const resetFiltersBtn = document.getElementById('resetFiltersBtn');

        // Filter Logic
        function getFilteredStartups() {
            let results = [...mockStartups];
            const query = searchInput.value.toLowerCase().trim();
            const category = categoryFilter.value.toLowerCase();
            const stage = stageFilter.value.toLowerCase();
            const sortBy = sortFilter.value;

            if (query) {
                results = results.filter(s => 
                    s.name.toLowerCase().includes(query) || 
                    s.description.toLowerCase().includes(query) || 
                    s.category.toLowerCase().includes(query)
                );
            }
            if (category) {
                results = results.filter(s => s.category.toLowerCase() === category);
            }
            if (stage) {
                results = results.filter(s => s.stage.toLowerCase() === stage);
            }
            
            if (sortBy === 'liked') {
                results.sort((a, b) => b.likes - a.likes);
            } else {
                results.sort((a, b) => b.id - a.id); // Default recent
            }
            return results;
        }

        // Render Card HTML
        function createPitchCard(startup, index) {
            const isLiked = likedCards.has(startup.id);
            const likeCount = isLiked ? startup.likes + 1 : startup.likes;
            const stageClass = startup.stage ? startup.stage.toLowerCase() : 'idea';
            const logoHtml = startup.logo 
                ? `<img src="${startup.logo}" alt="${startup.name}">` 
                : startup.name.charAt(0);

            return `
                <div class="pitch-card animate-fade-in" style="animation-delay: ${0.1 * index}s">
                    <div class="card-header">
                        <div class="card-logo">${logoHtml}</div>
                        <div class="card-info">
                            <h3 class="card-name">${startup.name}</h3>
                            <span class="category-badge">${startup.category}</span>
                        </div>
                    </div>
                    <p class="card-description">${startup.description}</p>
                    <div class="card-funding-row">
                        <div class="funding-info">
                            <span class="funding-label">Funding Required</span>
                            <span class="funding-amount">${startup.fundingRequired}</span>
                        </div>
                        <span class="stage-badge ${stageClass}">${startup.stage}</span>
                    </div>
                    <div class="card-stats">
                        <span class="stat-item">${icons.heart} <span class="like-count">${likeCount}</span></span>
                        <span class="stat-item">${icons.eye} ${startup.views}</span>
                        <span class="stat-item">${icons.users} ${startup.interestedInvestors} interested</span>
                    </div>
                    <div class="trust-badges">
                        ${startup.isAdminApproved ? `<span class="trust-badge approved">${icons.shieldCheck} Admin Approved</span>` : ''}
                        ${startup.isFounderVerified ? `<span class="trust-badge verified">${icons.badgeCheck} Verified</span>` : ''}
                    </div>
                    <div class="card-actions">
                        <a href="view-pitch.php?id=${startup.id}" class="btn btn-gradient" style="text-decoration:none;">View Pitch</a>
                        <button class="btn btn-icon like-btn ${isLiked ? 'active' : ''}" onclick="handleLike(this, ${startup.id})">
                            ${isLiked ? icons.heartFilled : icons.heart}
                        </button>
                    </div>
                </div>
            `;
        }

        // --- GLOBAL FUNCTIONS (Accessible by HTML) ---

        window.handleLike = function(btn, id) {
            // 1. SESSION CHECK
            if (!isLoggedIn) {
                alert("You must be logged in to like a pitch!");
                // window.location.href = 'login.php'; // Uncomment to redirect
                return;
            }

            // 2. LIKE LOGIC
            const card = btn.closest('.pitch-card');
            const likeCountEl = card.querySelector('.like-count');
            const startup = mockStartups.find(s => s.id === id);

            if (likedCards.has(id)) {
                likedCards.delete(id);
                btn.classList.remove('active');
                btn.innerHTML = icons.heart;
                if(startup) likeCountEl.textContent = startup.likes;
            } else {
                likedCards.add(id);
                btn.classList.add('active');
                btn.innerHTML = icons.heartFilled;
                if(startup) likeCountEl.textContent = startup.likes + 1;
            }
        };

        window.changePage = function(page) {
            currentPage = page;
            render();
            window.scrollTo({ top: 400, behavior: 'smooth' });
        };

        window.resetFilters = function() {
            searchInput.value = '';
            categoryFilter.value = '';
            stageFilter.value = '';
            sortFilter.value = 'recent';
            currentPage = 1;
            render();
        };

        // Render Function
        function render() {
            const filtered = getFilteredStartups();
            const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
            if (currentPage > totalPages) currentPage = 1;

            const start = (currentPage - 1) * ITEMS_PER_PAGE;
            const paginated = filtered.slice(start, start + ITEMS_PER_PAGE);

            if (paginated.length === 0) {
                cardsGrid.style.display = 'none';
                pagination.style.display = 'none';
                emptyState.style.display = 'flex';
            } else {
                cardsGrid.style.display = 'grid';
                emptyState.style.display = 'none';
                pagination.style.display = 'flex';
                cardsGrid.innerHTML = paginated.map((s, i) => createPitchCard(s, i)).join('');
                
                // Render Pagination
                let html = '';
                for(let i=1; i<=totalPages; i++) {
                    html += `<button class="btn btn-icon ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})" style="border:1px solid var(--border); width:40px; height:40px; border-radius:8px; cursor:pointer; background: ${i===currentPage ? 'var(--primary)' : 'transparent'}; color: ${i===currentPage ? 'black' : 'white'}">${i}</button>`;
                }
                pagination.innerHTML = html;
            }
        }

        // Initialize Listeners
        searchInput.addEventListener('input', () => { currentPage=1; render(); });
        categoryFilter.addEventListener('change', () => { currentPage=1; render(); });
        stageFilter.addEventListener('change', () => { currentPage=1; render(); });
        sortFilter.addEventListener('change', () => { currentPage=1; render(); });
        resetFiltersBtn.addEventListener('click', resetFilters);

        // Run on Load
        document.addEventListener('DOMContentLoaded', render);
    </script>
</body>
</html>