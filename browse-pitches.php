<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_role'])) {
    // Redirect to login if not authenticated
    header('Location: login.php?role=investor');
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard">
    <!-- Page Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Browse Startup Pitches</h1>
        <p>Discover innovative startups looking for funding and partnership opportunities.</p>
        
        <!-- Search and Filter -->
        <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 300px;">
                <input type="text" class="form-control" placeholder="Search pitches..." id="searchInput" data-table="pitchesTable">
            </div>
            
            <div class="form-group" style="min-width: 200px;">
                <select class="form-control" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="SaaS">SaaS</option>
                    <option value="Healthcare">Healthcare</option>
                    <option value="FinTech">FinTech</option>
                    <option value="E-commerce">E-commerce</option>
                    <option value="Sustainability">Sustainability</option>
                    <option value="Education">Education</option>
                </select>
            </div>
            
            <div class="form-group" style="min-width: 200px;">
                <select class="form-control" id="fundingFilter">
                    <option value="">All Funding Ranges</option>
                    <option value="0-100000">Under ₹100k</option>
                    <option value="100000-500000">₹100k - ₹500k</option>
                    <option value="500000-1000000">₹500k - ₹1M</option>
                    <option value="1000000+">₹1M+</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Pitches Grid -->
    <div class="features-grid" style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
        <!-- Pitch Card 1 -->
        <div class="feature-card pitch-card" data-category="SaaS" data-funding="500000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-cloud" style="font-size: 3rem;"></i>
            </div>
            <h3>TechFlow Solutions</h3>
            <p class="pitch-category" style="color: #667eea; font-weight: 500;">SaaS • Seed Stage</p>
            <p class="pitch-description">AI-powered customer support platform that reduces response time by 80% and increases customer satisfaction.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹500K</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">156</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>

        <!-- Pitch Card 2 -->
        <div class="feature-card pitch-card" data-category="Sustainability" data-funding="250000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-leaf" style="font-size: 3rem;"></i>
            </div>
            <h3>EcoGrow Farms</h3>
            <p class="pitch-category" style="color: #10b981; font-weight: 500;">Sustainability • Pre-seed</p>
            <p class="pitch-description">Sustainable vertical farming technology that uses 90% less water and produces food locally year-round.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹250K</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">89</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>

        <!-- Pitch Card 3 -->
        <div class="feature-card pitch-card" data-category="Healthcare" data-funding="1200000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-heartbeat" style="font-size: 3rem;"></i>
            </div>
            <h3>HealthTech AI</h3>
            <p class="pitch-category" style="color: #ef4444; font-weight: 500;">Healthcare • Series A</p>
            <p class="pitch-description">AI-powered diagnostic tool that detects early signs of chronic diseases with 95% accuracy.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹1.2M</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">203</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>

        <!-- Pitch Card 4 -->
        <div class="feature-card pitch-card" data-category="FinTech" data-funding="750000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-chart-line" style="font-size: 3rem;"></i>
            </div>
            <h3>FinTech Pro</h3>
            <p class="pitch-category" style="color: #f59e0b; font-weight: 500;">FinTech • Seed Stage</p>
            <p class="pitch-description">Blockchain-based payment solution that reduces transaction fees by 70% and increases security.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹750K</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">127</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>

        <!-- Pitch Card 5 -->
        <div class="feature-card pitch-card" data-category="Education" data-funding="300000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-graduation-cap" style="font-size: 3rem;"></i>
            </div>
            <h3>EdTech Solutions</h3>
            <p class="pitch-category" style="color: #8b5cf6; font-weight: 500;">Education • Pre-seed</p>
            <p class="pitch-description">Interactive learning platform that personalizes education using AI and adaptive learning algorithms.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹300K</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">94</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>

        <!-- Pitch Card 6 -->
        <div class="feature-card pitch-card" data-category="E-commerce" data-funding="600000">
            <div class="pitch-image" style="height: 200px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-shopping-cart" style="font-size: 3rem;"></i>
            </div>
            <h3>ShopSmart AI</h3>
            <p class="pitch-category" style="color: #ec4899; font-weight: 500;">E-commerce • Seed Stage</p>
            <p class="pitch-description">AI-powered shopping assistant that helps users find the best deals and make informed purchasing decisions.</p>
            
            <div class="pitch-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #667eea;">₹600K</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Funding Goal</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 600; color: #10b981;">178</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">Views</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button class="btn btn-info btn-sm">View Details</button>
                <button class="btn btn-success btn-sm">Like ♥</button>
            </div>
        </div>
    </div>
</div>

<script>
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const fundingFilter = document.getElementById('fundingFilter');
    const pitchCards = document.querySelectorAll('.pitch-card');

    function filterPitches() {
        const searchTerm = searchInput.value.toLowerCase();
        const categoryValue = categoryFilter.value;
        const fundingValue = fundingFilter.value;

        pitchCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const category = card.getAttribute('data-category');
            const funding = parseInt(card.getAttribute('data-funding'));
            const description = card.querySelector('.pitch-description').textContent.toLowerCase();

            // Check search term
            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            
            // Check category
            const matchesCategory = !categoryValue || category === categoryValue;
            
            // Check funding range
            let matchesFunding = true;
            if (fundingValue) {
                if (fundingValue === '0-100000') {
                    matchesFunding = funding <= 100000;
                } else if (fundingValue === '100000-500000') {
                    matchesFunding = funding > 100000 && funding <= 500000;
                } else if (fundingValue === '500000-1000000') {
                    matchesFunding = funding > 500000 && funding <= 1000000;
                } else if (fundingValue === '1000000+') {
                    matchesFunding = funding > 1000000;
                }
            }

            // Show or hide card based on filters
            if (matchesSearch && matchesCategory && matchesFunding) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Add event listeners
    searchInput.addEventListener('input', filterPitches);
    categoryFilter.addEventListener('change', filterPitches);
    fundingFilter.addEventListener('change', filterPitches);

    // Initialize filters
    filterPitches();
});
</script>

<style>
.pitch-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.pitch-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.pitch-stats {
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 0;
}

.pitch-description {
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 1rem;
}
</style>

<?php include 'includes/footer.php'; ?>
