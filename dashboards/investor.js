class InvestorDashboard {
    constructor() {
        this.activeTab = 'dashboard';
        this.sidebarOpen = false;
        this.profileMenuOpen = false;
        this.theme = this.getInitialTheme();
        this.shortlistedStartups = new Set();
        this.bookmarkedStartups = new Set();
        this.portfolio = new Map();
        this.deals = new Map();
        this.startupData = this.getDemoStartupData();

        this.loadPersistedData();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.applyTheme(this.theme);
        this.initializeLucideIcons();
        this.setActiveTab(this.activeTab);
        this.addAnimations();
        this.loadDemoData();
        this.startRealTimeUpdates();
    }

    getInitialTheme() {
        const saved = localStorage.getItem('investor-theme');
        if (saved) return saved;
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    setupEventListeners() {
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }

        // Mobile menu
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => this.toggleSidebar());
        }

        // Sidebar close
        const sidebarClose = document.getElementById('sidebar-close');
        if (sidebarClose) {
            sidebarClose.addEventListener('click', () => this.closeSidebar());
        }

        // Sidebar overlay
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => this.closeSidebar());
        }

        // Navigation items
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                const tab = e.currentTarget.getAttribute('data-tab');
                if (tab) {
                    this.setActiveTab(tab);
                    this.closeSidebar();
                }
            });
        });

        // Profile dropdown
        const profileBtn = document.getElementById('profile-btn');
        const profileMenu = document.getElementById('profile-menu');
        if (profileBtn && profileMenu) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleProfileMenu();
            });

            // Close profile menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) {
                    this.closeProfileMenu();
                }
            });
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                this.closeSidebar();
            }
        });

        // Quick actions
        this.setupQuickActions();
        
        // Startup interactions
        this.setupStartupInteractions();
        
        // Form handlers
        this.setupFormHandlers();

        // Search functionality
        this.setupSearchHandlers();

        // Modal handlers
        this.setupModalHandlers();
    }

    setupQuickActions() {
        const actionCards = document.querySelectorAll('.action-card');
        actionCards.forEach(card => {
            card.addEventListener('click', (e) => {
                const action = e.currentTarget.getAttribute('data-action');
                switch (action) {
                    case 'browse':
                        this.setActiveTab('browse-startups');
                        break;
                    case 'review':
                        this.setActiveTab('pitches');
                        break;
                    case 'connect':
                        this.setActiveTab('networking');
                        break;
                    case 'analyze':
                        this.setActiveTab('analyst');
                        break;
                }
            });
        });
    }

    setupStartupInteractions() {
        // Bookmark buttons
        const bookmarkBtns = document.querySelectorAll('.bookmark-btn');
        bookmarkBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const startupId = e.currentTarget.getAttribute('data-startup');
                this.toggleBookmark(startupId, e.currentTarget);
            });
        });

        // View pitch buttons
        const viewPitchBtns = document.querySelectorAll('[data-action="view-pitch"]');
        viewPitchBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const startupId = e.currentTarget.getAttribute('data-startup');
                this.viewPitch(startupId);
            });
        });

        // Express interest buttons
        const interestBtns = document.querySelectorAll('[data-action="express-interest"]');
        interestBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const startupId = e.currentTarget.getAttribute('data-startup');
                this.expressInterest(startupId);
            });
        });
    }

    setupFormHandlers() {
        // Profile form
        const profileForm = document.querySelector('.profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleProfileSubmit(e);
            });
        }

        // Preferences form
        const preferencesForm = document.querySelector('.preferences-form');
        if (preferencesForm) {
            preferencesForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePreferencesSubmit(e);
            });
        }
    }

    setupSearchHandlers() {
        // Global search
        const globalSearch = document.getElementById('global-search');
        if (globalSearch) {
            globalSearch.addEventListener('input', (e) => {
                this.handleGlobalSearch(e.target.value);
            });
        }

        // Main search
        const searchBtn = document.querySelector('.search-btn');
        const searchInput = document.querySelector('.search-input-main');
        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', () => {
                this.handleStartupSearch(searchInput.value);
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.handleStartupSearch(e.target.value);
                }
            });
        }

        // Filter selects
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.handleFilterChange();
            });
        });
    }

    setupModalHandlers() {
        // Modal close buttons
        const modalCloses = document.querySelectorAll('.modal-close');
        modalCloses.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = e.currentTarget.closest('.modal');
                this.closeModal(modal);
            });
        });

        // Close modal on backdrop click
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        });

        // Analyst modal handlers
        this.setupAnalystModalHandlers();
    }

    setupAnalystModalHandlers() {
        // Quick analysis buttons
        const quickAnalysisBtns = document.querySelectorAll('[data-action="quick-analysis"]');
        quickAnalysisBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const analysisType = e.currentTarget.getAttribute('data-analysis-type');
                this.performQuickAnalysis(analysisType);
            });
        });

        // Generate report button
        const generateReportBtn = document.getElementById('generate-report-btn');
        if (generateReportBtn) {
            generateReportBtn.addEventListener('click', () => {
                this.generateAnalysisReport();
            });
        }

        // Export analysis button
        const exportAnalysisBtn = document.getElementById('export-analysis-btn');
        if (exportAnalysisBtn) {
            exportAnalysisBtn.addEventListener('click', () => {
                this.exportAnalysis();
            });
        }

        // Market intelligence refresh
        const refreshIntelligenceBtn = document.getElementById('refresh-intelligence');
        if (refreshIntelligenceBtn) {
            refreshIntelligenceBtn.addEventListener('click', () => {
                this.refreshMarketIntelligence();
            });
        }
    }

    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        this.applyTheme(this.theme);
        localStorage.setItem('investor-theme', this.theme);
    }

    applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (this.sidebarOpen) {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        } else {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    }

    closeSidebar() {
        this.sidebarOpen = false;
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    toggleProfileMenu() {
        this.profileMenuOpen = !this.profileMenuOpen;
        const profileMenu = document.getElementById('profile-menu');
        
        if (this.profileMenuOpen) {
            profileMenu.classList.add('active');
        } else {
            profileMenu.classList.remove('active');
        }
    }

    closeProfileMenu() {
        this.profileMenuOpen = false;
        const profileMenu = document.getElementById('profile-menu');
        profileMenu.classList.remove('active');
    }

    setActiveTab(tabName) {
        this.activeTab = tabName;
        
        // Remove active class from all nav items
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to current nav item
        const activeNavItem = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }
        
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // Show current tab content
        const activeContent = document.getElementById(`${tabName}-content`);
        if (activeContent) {
            activeContent.classList.add('active');
        }
    }

    toggleBookmark(startupId, buttonElement) {
        try {
            if (!startupId || !buttonElement) {
                console.error('Invalid parameters for toggleBookmark');
                return;
            }

            if (this.bookmarkedStartups.has(startupId)) {
                this.bookmarkedStartups.delete(startupId);
                buttonElement.classList.remove('bookmarked');
                this.showNotification('Startup removed from bookmarks', 'info');
            } else {
                this.bookmarkedStartups.add(startupId);
                buttonElement.classList.add('bookmarked');
                this.showNotification('Startup bookmarked successfully', 'success');
            }

            this.updateBookmarkCounts();
        } catch (error) {
            console.error('Error toggling bookmark:', error);
            this.showNotification('Error updating bookmark', 'error');
        }
    }

   updateBookmarkCounts() {
  const bookmarkBadge = document.querySelector('[data-tab="shortlisted"] .nav-badge');
  if (bookmarkBadge) {
    bookmarkBadge.textContent = this.bookmarkedStartups.size;
  }

  const shortlistContainer = document.querySelector('#shortlisted-content .coming-soon');
  if (shortlistContainer) {
    if (this.bookmarkedStartups.size > 0) {
      shortlistContainer.innerHTML = '';
      this.bookmarkedStartups.forEach(id => {
        const pitchData = this.getDemoPitchData(id);
        shortlistContainer.innerHTML += `
          <div class="startup-card">
            <div class="startup-header">
              <div class="startup-info">
                <h3>${pitchData.name}</h3>
                <p>${pitchData.tagline}</p>
               </div>
            </div>
          </div>
        `;
      });
    } else {
      shortlistContainer.innerHTML = '<p>No startups shortlisted yet.</p>';
    }
  }
}


    viewPitch(startupId) {
        try {
            if (!startupId) {
                console.error('No startup ID provided for viewPitch');
                this.showNotification('Error: No startup selected', 'error');
                return;
            }

            const modal = document.getElementById('pitch-modal');
            if (!modal) {
                console.error('Pitch modal not found');
                this.showNotification('Error: Modal not available', 'error');
                return;
            }

            const modalBody = modal.querySelector('.modal-body');
            if (!modalBody) {
                console.error('Modal body not found');
                this.showNotification('Error: Modal structure invalid', 'error');
                return;
            }

            // Demo content - in real app, this would fetch actual pitch data
            const pitchData = this.getDemoPitchData(startupId);

            modalBody.innerHTML = `
                <div class="pitch-content">
                    <div class="pitch-header">
                        <h4>${pitchData.name}</h4>
                        <p class="pitch-tagline">${pitchData.tagline}</p>
                    </div>
                    <div class="pitch-details">
                        <h5>Business Overview</h5>
                        <p>${pitchData.overview}</p>

                        <h5>Key Metrics</h5>
                        <div class="metrics-grid">
                            <div class="metric">
                                <span class="metric-label">Revenue (ARR)</span>
                                <span class="metric-value">${pitchData.revenue}</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Growth Rate</span>
                                <span class="metric-value">${pitchData.growth}</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Customers</span>
                                <span class="metric-value">${pitchData.customers}</span>
                            </div>
                        </div>

                        <h5>Funding Details</h5>
                        <p>Seeking ${pitchData.seeking} at ${pitchData.valuation} valuation for ${pitchData.useOfFunds}</p>
                    </div>
                    <div class="pitch-actions">
                        <button class="btn secondary" onclick="dashboard.downloadPitchDeck('${startupId}')">
                            <i data-lucide="download"></i>
                            Download Pitch Deck
                        </button>
                        <button class="btn primary" onclick="dashboard.scheduleCall('${startupId}')">
                            <i data-lucide="calendar"></i>
                            Schedule Call
                        </button>
                    </div>
                </div>
            `;

            this.openModal(modal);
            this.initializeLucideIcons(); // Re-initialize icons for new content
        } catch (error) {
            console.error('Error displaying pitch:', error);
            this.showNotification('Error loading pitch details', 'error');
        }
    }

    getDemoPitchData(startupId) {
        const pitchData = {
            'tech-innovate': {
                name: 'TechInnovate AI',
                tagline: 'Next-gen AI solutions for enterprise',
                overview: 'TechInnovate AI is revolutionizing enterprise data analysis with our proprietary AI engine that provides real-time insights and automated decision-making capabilities. Our platform has been adopted by Fortune 500 companies and has shown to increase operational efficiency by 40%.',
                revenue: '$1.2M',
                growth: '150% YoY',
                customers: '25 Enterprise',
                seeking: '$2.5M',
                valuation: '$10M',
                useOfFunds: 'expanding the engineering team and international market penetration'
            },
            'greentech': {
                name: 'GreenTech Solutions',
                tagline: 'Sustainable energy management platform',
                overview: 'GreenTech Solutions provides smart energy management systems that help businesses reduce their carbon footprint while optimizing energy costs. Our IoT-enabled platform has helped clients achieve an average of 30% reduction in energy consumption.',
                revenue: '$800K',
                growth: '200% YoY',
                customers: '150 SMEs',
                seeking: '$1.2M',
                valuation: '$5M',
                useOfFunds: 'product development and market expansion across Europe'
            },
            'financeflow': {
                name: 'FinanceFlow',
                tagline: 'Next-generation fintech for small businesses',
                overview: 'FinanceFlow offers a comprehensive financial management platform designed specifically for small and medium enterprises. Our solution integrates accounting, invoicing, cash flow management, and financial analytics in one seamless platform.',
                revenue: '$400K',
                growth: '300% YoY',
                customers: '500 SMBs',
                seeking: '$800K',
                valuation: '$3.2M',
                useOfFunds: 'scaling the platform and expanding into new verticals'
            }
        };
        
        return pitchData[startupId] || pitchData['tech-innovate'];
    }

    expressInterest(startupId) {
        try {
            if (!startupId) {
                console.error('No startup ID provided for expressInterest');
                this.showNotification('Error: No startup selected', 'error');
                return;
            }

            // In a real application, this would send a request to the backend
            this.showNotification('Interest expressed successfully! The startup will be notified.', 'success');

            // Add to shortlisted
            this.shortlistedStartups.add(startupId);

            // Update UI to reflect the change
            this.updateShortlistedUI(startupId);

            // Also add to bookmarks if not already there
            if (!this.bookmarkedStartups.has(startupId)) {
                this.bookmarkedStartups.add(startupId);
                const bookmarkBtn = document.querySelector(`[data-startup="${startupId}"]`);
                if (bookmarkBtn) {
                    bookmarkBtn.classList.add('bookmarked');
                }
            }

            this.updateBookmarkCounts();
        } catch (error) {
            console.error('Error expressing interest:', error);
            this.showNotification('Error expressing interest', 'error');
        }
    }

    updateShortlistedUI(startupId) {
        // Update the shortlisted tab content
        const shortlistContainer = document.querySelector('#shortlisted-content .startup-grid');
        if (shortlistContainer) {
            // Check if startup is already in shortlisted
            const existingCard = shortlistContainer.querySelector(`[data-startup="${startupId}"]`);
            if (!existingCard) {
                // Add startup to shortlisted tab
                const pitchData = this.getDemoPitchData(startupId);
                const shortlistedCard = `
                    <div class="startup-card" data-startup="${startupId}">
                        <div class="startup-header">
                            <div class="startup-logo">
                                <img src="https://via.placeholder.com/60x60/8B5CF6/FFFFFF?text=${pitchData.name.charAt(0)}" alt="${pitchData.name}">
                            </div>
                            <div class="startup-info">
                                <h3>${pitchData.name}</h3>
                                <p class="startup-tagline">${pitchData.tagline}</p>
                                <div class="startup-meta">
                                    <span class="badge primary">AI/ML</span>
                                    <span class="badge outline">Series A</span>
                                    <span class="badge success">Active</span>
                                </div>
                            </div>
                            <button class="bookmark-btn bookmarked" data-startup="${startupId}">
                                <i data-lucide="bookmark"></i>
                            </button>
                        </div>
                        <div class="startup-details">
                            <p>${pitchData.overview}</p>
                            <div class="startup-stats">
                                <div class="stat">
                                    <span class="stat-label">Seeking</span>
                                    <span class="stat-value">${pitchData.seeking}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Valuation</span>
                                    <span class="stat-value">${pitchData.valuation}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Founded</span>
                                    <span class="stat-value">2023</span>
                                </div>
                            </div>
                        </div>
                        <div class="startup-actions">
                            <button class="btn secondary" data-action="view-pitch" data-startup="${startupId}">
                                <i data-lucide="presentation"></i>
                                View Pitch
                            </button>
                            <button class="btn primary" data-action="express-interest" data-startup="${startupId}">
                                <i data-lucide="heart"></i>
                                Express Interest
                            </button>
                        </div>
                    </div>
                `;
                shortlistContainer.insertAdjacentHTML('afterbegin', shortlistedCard);
            }
        }
    }

    downloadPitchDeck(startupId) {
        // In a real application, this would trigger a download
        this.showNotification('Pitch deck download initiated', 'info');
    }

    scheduleCall(startupId) {
        // In a real application, this would open a calendar scheduling interface
        this.showNotification('Redirecting to calendar scheduling...', 'info');
    }

    openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    handleGlobalSearch(query) {
        if (query.length > 2) {
            // In a real application, this would search across all content
            console.log('Global search:', query);
        }
    }

    handleStartupSearch(query) {
        // In a real application, this would filter the startup grid
        this.showNotification(`Searching for: "${query}"`, 'info');
    }

    handleFilterChange() {
        // In a real application, this would apply filters to the startup grid
        this.showNotification('Filters applied', 'info');
    }

    handleProfileSubmit(e) {
        const formData = new FormData(e.target);
        // In a real application, this would send data to the backend
        this.showNotification('Profile updated successfully', 'success');
    }

    handlePreferencesSubmit(e) {
        const formData = new FormData(e.target);
        // In a real application, this would save preferences to the backend
        this.showNotification('Investment preferences saved', 'success');
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Add styles if not already present
        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    z-index: 3000;
                    background: hsl(var(--card));
                    border: 1px solid hsl(var(--border));
                    border-radius: var(--radius);
                    box-shadow: var(--shadow-elegant);
                    max-width: 400px;
                    animation: slide-in-right 0.3s ease-out;
                }
                
                .notification.success {
                    border-left: 4px solid hsl(var(--success));
                }
                
                .notification.info {
                    border-left: 4px solid hsl(var(--primary));
                }
                
                .notification.error {
                    border-left: 4px solid hsl(var(--destructive));
                }
                
                .notification-content {
                    padding: 1rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 1rem;
                }
                
                .notification-close {
                    background: none;
                    border: none;
                    font-size: 1.25rem;
                    cursor: pointer;
                    color: hsl(var(--muted-foreground));
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                @keyframes slide-in-right {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    loadPersistedData() {
        try {
            // Load bookmarks
            const savedBookmarks = localStorage.getItem('investor-bookmarks');
            if (savedBookmarks) {
                const bookmarks = JSON.parse(savedBookmarks);
                this.bookmarkedStartups = new Set(bookmarks);
            }

            // Load portfolio
            const savedPortfolio = localStorage.getItem('investor-portfolio');
            if (savedPortfolio) {
                const portfolio = JSON.parse(savedPortfolio);
                this.portfolio = new Map(portfolio);
            }

            // Load deals
            const savedDeals = localStorage.getItem('investor-deals');
            if (savedDeals) {
                const deals = JSON.parse(savedDeals);
                this.deals = new Map(deals);
            }

            // Load user preferences
            const savedPreferences = localStorage.getItem('investor-preferences');
            if (savedPreferences) {
                this.preferences = JSON.parse(savedPreferences);
            }
        } catch (error) {
            console.error('Error loading persisted data:', error);
        }
    }

    savePersistedData() {
        try {
            localStorage.setItem('investor-bookmarks', JSON.stringify([...this.bookmarkedStartups]));
            localStorage.setItem('investor-portfolio', JSON.stringify([...this.portfolio]));
            localStorage.setItem('investor-deals', JSON.stringify([...this.deals]));
            if (this.preferences) {
                localStorage.setItem('investor-preferences', JSON.stringify(this.preferences));
            }
        } catch (error) {
            console.error('Error saving persisted data:', error);
        }
    }

    getDemoStartupData() {
        return {
            'tech-innovate': {
                id: 'tech-innovate',
                name: 'TechInnovate AI',
                tagline: 'Next-gen AI solutions for enterprise',
                industry: 'AI/ML',
                stage: 'Series A',
                seeking: '$2.5M',
                valuation: '$10M',
                revenue: '$1.2M',
                growth: '150% YoY',
                customers: '25 Enterprise',
                founded: '2023',
                location: 'San Francisco, CA',
                description: 'TechInnovate AI is revolutionizing enterprise data analysis with our proprietary AI engine that provides real-time insights and automated decision-making capabilities.',
                team: ['John Smith (CEO)', 'Sarah Johnson (CTO)', 'Mike Chen (Head of Product)'],
                traction: '40% operational efficiency improvement for Fortune 500 clients'
            },
            'greentech': {
                id: 'greentech',
                name: 'GreenTech Solutions',
                tagline: 'Sustainable energy management platform',
                industry: 'Clean Energy',
                stage: 'Seed',
                seeking: '$1.2M',
                valuation: '$5M',
                revenue: '$800K',
                growth: '200% YoY',
                customers: '150 SMEs',
                founded: '2022',
                location: 'Berlin, Germany',
                description: 'GreenTech Solutions provides smart energy management systems that help businesses reduce their carbon footprint while optimizing energy costs.',
                team: ['Anna Mueller (CEO)', 'David Brown (COO)', 'Lisa Wang (Head of Engineering)'],
                traction: '30% average energy consumption reduction for clients'
            },
            'financeflow': {
                id: 'financeflow',
                name: 'FinanceFlow',
                tagline: 'Next-generation fintech for small businesses',
                industry: 'Fintech',
                stage: 'Pre-seed',
                seeking: '$800K',
                valuation: '$3.2M',
                revenue: '$400K',
                growth: '300% YoY',
                customers: '500 SMBs',
                founded: '2023',
                location: 'London, UK',
                description: 'FinanceFlow offers a comprehensive financial management platform designed specifically for small and medium enterprises.',
                team: ['Robert Taylor (CEO)', 'Emma Davis (CFO)', 'James Wilson (Head of Sales)'],
                traction: 'Integrated accounting, invoicing, and cash flow management'
            },
            'healthtech': {
                id: 'healthtech',
                name: 'MediConnect',
                tagline: 'AI-powered telemedicine platform',
                industry: 'Healthcare',
                stage: 'Series B',
                seeking: '$5M',
                valuation: '$25M',
                revenue: '$3.5M',
                growth: '180% YoY',
                customers: '1000+ Healthcare Providers',
                founded: '2021',
                location: 'Boston, MA',
                description: 'MediConnect revolutionizes healthcare delivery with AI-powered telemedicine solutions connecting patients with healthcare providers globally.',
                team: ['Dr. Maria Rodriguez (CEO)', 'Dr. Kevin Park (CMO)', 'Rachel Green (Head of Technology)'],
                traction: 'Reduced patient wait times by 60%, improved diagnostic accuracy by 35%'
            },
            'edtech': {
                id: 'edtech',
                name: 'LearnSphere',
                tagline: 'Personalized learning platform for K-12',
                industry: 'Education',
                stage: 'Series A',
                seeking: '$3M',
                valuation: '$12M',
                revenue: '$1.8M',
                growth: '220% YoY',
                customers: '50 Schools',
                founded: '2022',
                location: 'Austin, TX',
                description: 'LearnSphere provides adaptive learning technology that personalizes education for each student based on their learning style and pace.',
                team: ['Dr. Jennifer Adams (CEO)', 'Mark Thompson (CTO)', 'Susan Lee (Head of Education)'],
                traction: '25% improvement in student test scores, 40% increase in engagement'
            }
        };
    }

    // Enhanced Search Functionality
    handleStartupSearch(query) {
        if (!query || query.trim().length === 0) {
            this.renderStartupGrid();
            return;
        }

        const searchTerm = query.toLowerCase().trim();
        const filteredStartups = Object.values(this.startupData).filter(startup => {
            return startup.name.toLowerCase().includes(searchTerm) ||
                   startup.tagline.toLowerCase().includes(searchTerm) ||
                   startup.industry.toLowerCase().includes(searchTerm) ||
                   startup.description.toLowerCase().includes(searchTerm) ||
                   startup.location.toLowerCase().includes(searchTerm);
        });

        this.renderStartupGrid(filteredStartups);
        this.showNotification(`Found ${filteredStartups.length} startup(s) matching "${query}"`, 'info');
    }

    handleFilterChange() {
        const industryFilter = document.querySelector('.filter-select[data-filter="industry"]')?.value;
        const stageFilter = document.querySelector('.filter-select[data-filter="stage"]')?.value;
        const locationFilter = document.querySelector('.filter-select[data-filter="location"]')?.value;

        let filteredStartups = Object.values(this.startupData);

        if (industryFilter && industryFilter !== 'all') {
            filteredStartups = filteredStartups.filter(startup => startup.industry === industryFilter);
        }

        if (stageFilter && stageFilter !== 'all') {
            filteredStartups = filteredStartups.filter(startup => startup.stage === stageFilter);
        }

        if (locationFilter && locationFilter !== 'all') {
            filteredStartups = filteredStartups.filter(startup => startup.location.includes(locationFilter));
        }

        this.renderStartupGrid(filteredStartups);
        this.showNotification(`Showing ${filteredStartups.length} filtered startup(s)`, 'info');
    }

    renderStartupGrid(startups = null) {
        const gridContainer = document.querySelector('#browse-startups-content .startup-grid');
        if (!gridContainer) return;

        const startupsToRender = startups || Object.values(this.startupData);

        gridContainer.innerHTML = startupsToRender.map(startup => `
            <div class="startup-card" data-startup="${startup.id}">
                <div class="startup-header">
                    <div class="startup-logo">
                        <img src="https://via.placeholder.com/60x60/8B5CF6/FFFFFF?text=${startup.name.charAt(0)}" alt="${startup.name}">
                    </div>
                    <div class="startup-info">
                        <h3>${startup.name}</h3>
                        <p class="startup-tagline">${startup.tagline}</p>
                        <div class="startup-meta">
                            <span class="badge primary">${startup.industry}</span>
                            <span class="badge outline">${startup.stage}</span>
                            <span class="badge success">Active</span>
                        </div>
                    </div>
                    <button class="bookmark-btn ${this.bookmarkedStartups.has(startup.id) ? 'bookmarked' : ''}" data-startup="${startup.id}">
                        <i data-lucide="bookmark"></i>
                    </button>
                </div>
                <div class="startup-details">
                    <p>${startup.description}</p>
                    <div class="startup-stats">
                        <div class="stat">
                            <span class="stat-label">Seeking</span>
                            <span class="stat-value">${startup.seeking}</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Valuation</span>
                            <span class="stat-value">${startup.valuation}</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Revenue</span>
                            <span class="stat-value">${startup.revenue}</span>
                        </div>
                    </div>
                </div>
                <div class="startup-actions">
                    <button class="btn secondary" data-action="view-pitch" data-startup="${startup.id}">
                        <i data-lucide="presentation"></i>
                        View Pitch
                    </button>
                    <button class="btn primary" data-action="express-interest" data-startup="${startup.id}">
                        <i data-lucide="heart"></i>
                        Express Interest
                    </button>
                </div>
            </div>
        `).join('');

        this.initializeLucideIcons();
    }

    // Portfolio Management Methods
    addToPortfolio(startupId, investmentAmount, equityPercentage) {
        try {
            const startup = this.startupData[startupId];
            if (!startup) {
                this.showNotification('Startup not found', 'error');
                return false;
            }

            const portfolioEntry = {
                startupId,
                startupName: startup.name,
                investmentAmount: parseFloat(investmentAmount),
                equityPercentage: parseFloat(equityPercentage),
                investmentDate: new Date().toISOString(),
                currentValue: parseFloat(investmentAmount), // Initial value
                status: 'active'
            };

            this.portfolio.set(startupId, portfolioEntry);
            this.savePersistedData();
            this.updatePortfolioUI();
            this.showNotification(`Added ${startup.name} to portfolio`, 'success');
            return true;
        } catch (error) {
            console.error('Error adding to portfolio:', error);
            this.showNotification('Error adding to portfolio', 'error');
            return false;
        }
    }

    removeFromPortfolio(startupId) {
        try {
            if (this.portfolio.has(startupId)) {
                const startup = this.portfolio.get(startupId);
                this.portfolio.delete(startupId);
                this.savePersistedData();
                this.updatePortfolioUI();
                this.showNotification(`Removed ${startup.startupName} from portfolio`, 'info');
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error removing from portfolio:', error);
            this.showNotification('Error removing from portfolio', 'error');
            return false;
        }
    }

    updatePortfolioUI() {
        const portfolioContainer = document.querySelector('#portfolio-content .portfolio-grid');
        if (!portfolioContainer) return;

        if (this.portfolio.size === 0) {
            portfolioContainer.innerHTML = '<p class="empty-state">No investments in portfolio yet.</p>';
            return;
        }

        portfolioContainer.innerHTML = Array.from(this.portfolio.values()).map(investment => `
            <div class="portfolio-card" data-startup="${investment.startupId}">
                <div class="portfolio-header">
                    <h4>${investment.startupName}</h4>
                    <span class="investment-status ${investment.status}">${investment.status}</span>
                </div>
                <div class="portfolio-details">
                    <div class="portfolio-metric">
                        <span class="metric-label">Investment</span>
                        <span class="metric-value">$${investment.investmentAmount.toLocaleString()}</span>
                    </div>
                    <div class="portfolio-metric">
                        <span class="metric-label">Equity</span>
                        <span class="metric-value">${investment.equityPercentage}%</span>
                    </div>
                    <div class="portfolio-metric">
                        <span class="metric-label">Current Value</span>
                        <span class="metric-value">$${investment.currentValue.toLocaleString()}</span>
                    </div>
                    <div class="portfolio-metric">
                        <span class="metric-label">Return</span>
                        <span class="metric-value ${investment.currentValue >= investment.investmentAmount ? 'positive' : 'negative'}">
                            ${((investment.currentValue - investment.investmentAmount) / investment.investmentAmount * 100).toFixed(1)}%
                        </span>
                    </div>
                </div>
                <div class="portfolio-actions">
                    <button class="btn secondary" onclick="dashboard.viewPortfolioDetails('${investment.startupId}')">
                        <i data-lucide="eye"></i>
                        View Details
                    </button>
                    <button class="btn outline" onclick="dashboard.removeFromPortfolio('${investment.startupId}')">
                        <i data-lucide="trash-2"></i>
                        Remove
                    </button>
                </div>
            </div>
        `).join('');

        this.initializeLucideIcons();
    }

    viewPortfolioDetails(startupId) {
        const investment = this.portfolio.get(startupId);
        if (!investment) return;

        const modal = document.getElementById('portfolio-modal');
        if (!modal) return;

        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="portfolio-detail">
                <div class="detail-header">
                    <h3>${investment.startupName}</h3>
                    <span class="status-badge ${investment.status}">${investment.status}</span>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Investment Amount</span>
                        <span class="detail-value">$${investment.investmentAmount.toLocaleString()}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Equity Stake</span>
                        <span class="detail-value">${investment.equityPercentage}%</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Investment Date</span>
                        <span class="detail-value">${new Date(investment.investmentDate).toLocaleDateString()}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Current Value</span>
                        <span class="detail-value">$${investment.currentValue.toLocaleString()}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Return</span>
                        <span class="detail-value ${investment.currentValue >= investment.investmentAmount ? 'positive' : 'negative'}">
                            $${(investment.currentValue - investment.investmentAmount).toLocaleString()}
                            (${((investment.currentValue - investment.investmentAmount) / investment.investmentAmount * 100).toFixed(1)}%)
                        </span>
                    </div>
                </div>
            </div>
        `;

        this.openModal(modal);
        this.initializeLucideIcons();
    }

    // Deal Management Methods
    createDeal(startupId, dealType, terms) {
        try {
            const startup = this.startupData[startupId];
            if (!startup) {
                this.showNotification('Startup not found', 'error');
                return false;
            }

            const deal = {
                id: `deal_${Date.now()}`,
                startupId,
                startupName: startup.name,
                dealType,
                terms,
                status: 'pending',
                createdDate: new Date().toISOString(),
                lastUpdated: new Date().toISOString()
            };

            this.deals.set(deal.id, deal);
            this.savePersistedData();
            this.updateDealsUI();
            this.showNotification(`Deal created for ${startup.name}`, 'success');
            return deal.id;
        } catch (error) {
            console.error('Error creating deal:', error);
            this.showNotification('Error creating deal', 'error');
            return false;
        }
    }

    updateDealStatus(dealId, newStatus) {
        try {
            if (this.deals.has(dealId)) {
                const deal = this.deals.get(dealId);
                deal.status = newStatus;
                deal.lastUpdated = new Date().toISOString();
                this.savePersistedData();
                this.updateDealsUI();
                this.showNotification(`Deal status updated to ${newStatus}`, 'info');
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error updating deal status:', error);
            this.showNotification('Error updating deal status', 'error');
            return false;
        }
    }

    updateDealsUI() {
        const dealsContainer = document.querySelector('#deals-content .deals-list');
        if (!dealsContainer) return;

        if (this.deals.size === 0) {
            dealsContainer.innerHTML = '<p class="empty-state">No deals in progress.</p>';
            return;
        }

        dealsContainer.innerHTML = Array.from(this.deals.values()).map(deal => `
            <div class="deal-card" data-deal="${deal.id}">
                <div class="deal-header">
                    <h4>${deal.startupName}</h4>
                    <span class="deal-type">${deal.dealType}</span>
                    <span class="deal-status ${deal.status}">${deal.status}</span>
                </div>
                <div class="deal-details">
                    <p><strong>Terms:</strong> ${deal.terms}</p>
                    <p><strong>Created:</strong> ${new Date(deal.createdDate).toLocaleDateString()}</p>
                    <p><strong>Last Updated:</strong> ${new Date(deal.lastUpdated).toLocaleDateString()}</p>
                </div>
                <div class="deal-actions">
                    <button class="btn secondary" onclick="dashboard.viewDealDetails('${deal.id}')">
                        <i data-lucide="eye"></i>
                        View Details
                    </button>
                    ${deal.status === 'pending' ? `
                        <button class="btn success" onclick="dashboard.updateDealStatus('${deal.id}', 'approved')">
                            <i data-lucide="check"></i>
                            Approve
                        </button>
                        <button class="btn danger" onclick="dashboard.updateDealStatus('${deal.id}', 'rejected')">
                            <i data-lucide="x"></i>
                            Reject
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');

        this.initializeLucideIcons();
    }

    viewDealDetails(dealId) {
        const deal = this.deals.get(dealId);
        if (!deal) return;

        const modal = document.getElementById('deal-modal');
        if (!modal) return;

        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="deal-detail">
                <div class="detail-header">
                    <h3>Deal: ${deal.startupName}</h3>
                    <span class="status-badge ${deal.status}">${deal.status}</span>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Deal Type</span>
                        <span class="detail-value">${deal.dealType}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Terms</span>
                        <span class="detail-value">${deal.terms}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created Date</span>
                        <span class="detail-value">${new Date(deal.createdDate).toLocaleDateString()}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Updated</span>
                        <span class="detail-value">${new Date(deal.lastUpdated).toLocaleDateString()}</span>
                    </div>
                </div>
                <div class="deal-timeline">
                    <h4>Deal Timeline</h4>
                    <div class="timeline-item">
                        <div class="timeline-marker ${deal.status === 'approved' ? 'completed' : deal.status === 'rejected' ? 'rejected' : 'pending'}"></div>
                        <div class="timeline-content">
                            <h5>Deal ${deal.status.charAt(0).toUpperCase() + deal.status.slice(1)}</h5>
                            <p>${new Date(deal.lastUpdated).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.openModal(modal);
        this.initializeLucideIcons();
    }

    loadDemoData() {
        // Update bookmark states based on demo data
        this.bookmarkedStartups.forEach(startupId => {
            const bookmarkBtn = document.querySelector(`[data-startup="${startupId}"]`);
            if (bookmarkBtn) {
                bookmarkBtn.classList.add('bookmarked');
            }
        });

        this.updateBookmarkCounts();
        this.renderStartupGrid();
        this.updatePortfolioUI();
        this.updateDealsUI();
    }

    addAnimations() {
        // Add animation classes to elements as they become visible
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);

        // Observe stat cards, startup cards, and other animated elements
        const animatedElements = document.querySelectorAll('.stat-card, .startup-card, .action-card, .activity-item');
        animatedElements.forEach(el => observer.observe(el));
    }

    initializeLucideIcons() {
        // Initialize Lucide icons with error handling
        try {
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            } else {
                console.warn('Lucide icons library not loaded. Icons may not render properly.');
                // Fallback: try to initialize after a short delay
                setTimeout(() => {
                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                        lucide.createIcons();
                    }
                }, 100);
            }
        } catch (error) {
            console.error('Error initializing Lucide icons:', error);
        }
    }

    // Analyst-specific methods
    performQuickAnalysis(analysisType) {
        const analysisContainer = document.getElementById('analysis-results');
        if (!analysisContainer) {
            this.showNotification('Analysis container not found', 'error');
            return;
        }

        // Show loading state
        analysisContainer.innerHTML = `
            <div class="analysis-loading">
                <div class="loading-spinner"></div>
                <p>Performing ${analysisType} analysis...</p>
            </div>
        `;

        // Simulate analysis delay
        setTimeout(() => {
            const analysisResult = this.generateAnalysisResult(analysisType);
            analysisContainer.innerHTML = analysisResult;
            this.initializeLucideIcons(); // Re-initialize icons for new content
            this.showNotification(`${analysisType} analysis completed`, 'success');
        }, 2000);
    }

    generateAnalysisResult(analysisType) {
        const analysisData = {
            'market': {
                title: 'Market Analysis',
                icon: 'trending-up',
                content: `
                    <div class="analysis-result">
                        <div class="analysis-header">
                            <h4>Market Opportunity Assessment</h4>
                            <span class="analysis-badge positive">High Potential</span>
                        </div>
                        <div class="analysis-metrics">
                            <div class="metric-item">
                                <span class="metric-label">Market Size</span>
                                <span class="metric-value">$2.4B</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Growth Rate</span>
                                <span class="metric-value">18.5%</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Competition Level</span>
                                <span class="metric-value">Medium</span>
                            </div>
                        </div>
                        <div class="analysis-insights">
                            <h5>Key Insights</h5>
                            <ul>
                                <li>Strong market demand with increasing adoption</li>
                                <li>Emerging competitive landscape with room for innovation</li>
                                <li>Favorable regulatory environment</li>
                            </ul>
                        </div>
                    </div>
                `
            },
            'financial': {
                title: 'Financial Analysis',
                icon: 'dollar-sign',
                content: `
                    <div class="analysis-result">
                        <div class="analysis-header">
                            <h4>Financial Health Assessment</h4>
                            <span class="analysis-badge positive">Strong</span>
                        </div>
                        <div class="analysis-metrics">
                            <div class="metric-item">
                                <span class="metric-label">Revenue Growth</span>
                                <span class="metric-value">45%</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Profit Margin</span>
                                <span class="metric-value">23%</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Burn Rate</span>
                                <span class="metric-value">Low</span>
                            </div>
                        </div>
                        <div class="analysis-insights">
                            <h5>Financial Insights</h5>
                            <ul>
                                <li>Healthy revenue trajectory with strong unit economics</li>
                                <li>Efficient capital utilization</li>
                                <li>Strong runway with current funding</li>
                            </ul>
                        </div>
                    </div>
                `
            },
            'risk': {
                title: 'Risk Analysis',
                icon: 'shield',
                content: `
                    <div class="analysis-result">
                        <div class="analysis-header">
                            <h4>Risk Assessment</h4>
                            <span class="analysis-badge neutral">Moderate Risk</span>
                        </div>
                        <div class="analysis-metrics">
                            <div class="metric-item">
                                <span class="metric-label">Market Risk</span>
                                <span class="metric-value">Medium</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Execution Risk</span>
                                <span class="metric-value">Low</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Regulatory Risk</span>
                                <span class="metric-value">Low</span>
                            </div>
                        </div>
                        <div class="analysis-insights">
                            <h5>Risk Factors</h5>
                            <ul>
                                <li>Market adoption timing uncertainty</li>
                                <li>Technology execution appears solid</li>
                                <li>Regulatory environment is favorable</li>
                            </ul>
                        </div>
                    </div>
                `
            }
        };

        const data = analysisData[analysisType] || analysisData['market'];
        return `
            <div class="analysis-result-card">
                <div class="analysis-result-header">
                    <i data-lucide="${data.icon}"></i>
                    <h3>${data.title}</h3>
                </div>
                ${data.content}
            </div>
        `;
    }

    generateAnalysisReport() {
        const reportModal = document.getElementById('analysis-report-modal');
        if (!reportModal) {
            this.showNotification('Report modal not found', 'error');
            return;
        }

        const modalBody = reportModal.querySelector('.modal-body');
        if (!modalBody) {
            this.showNotification('Modal body not found', 'error');
            return;
        }

        // Show loading state
        modalBody.innerHTML = `
            <div class="report-loading">
                <div class="loading-spinner"></div>
                <p>Generating comprehensive analysis report...</p>
            </div>
        `;

        this.openModal(reportModal);

        // Simulate report generation
        setTimeout(() => {
            modalBody.innerHTML = `
                <div class="analysis-report">
                    <div class="report-header">
                        <h2>Investment Analysis Report</h2>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>

                    <div class="report-section">
                        <h3>Executive Summary</h3>
                        <p>This comprehensive analysis evaluates the investment opportunity based on market potential, financial health, competitive landscape, and risk factors. The overall recommendation is positive with moderate risk tolerance required.</p>
                    </div>

                    <div class="report-section">
                        <h3>Market Analysis</h3>
                        <div class="report-metrics">
                            <div class="metric-row">
                                <span>Market Size:</span>
                                <span class="metric-value">$2.4B</span>
                            </div>
                            <div class="metric-row">
                                <span>Growth Rate:</span>
                                <span class="metric-value">18.5%</span>
                            </div>
                            <div class="metric-row">
                                <span>Competition Level:</span>
                                <span class="metric-value">Medium</span>
                            </div>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3>Financial Assessment</h3>
                        <div class="report-metrics">
                            <div class="metric-row">
                                <span>Revenue Growth:</span>
                                <span class="metric-value positive">45%</span>
                            </div>
                            <div class="metric-row">
                                <span>Profit Margin:</span>
                                <span class="metric-value positive">23%</span>
                            </div>
                            <div class="metric-row">
                                <span>Burn Rate:</span>
                                <span class="metric-value neutral">Low</span>
                            </div>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3>Risk Assessment</h3>
                        <div class="risk-matrix">
                            <div class="risk-item">
                                <span class="risk-label">Market Risk:</span>
                                <span class="risk-level medium">Medium</span>
                            </div>
                            <div class="risk-item">
                                <span class="risk-label">Execution Risk:</span>
                                <span class="risk-level low">Low</span>
                            </div>
                            <div class="risk-item">
                                <span class="risk-label">Regulatory Risk:</span>
                                <span class="risk-level low">Low</span>
                            </div>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3>Investment Recommendation</h3>
                        <div class="recommendation">
                            <span class="recommendation-badge positive">Recommended</span>
                            <p>Based on the comprehensive analysis, this investment opportunity shows strong potential with manageable risks. The company demonstrates solid fundamentals and operates in a growing market with favorable competitive dynamics.</p>
                        </div>
                    </div>

                    <div class="report-actions">
                        <button class="btn primary" onclick="dashboard.exportAnalysis()">
                            <i data-lucide="download"></i>
                            Export Report
                        </button>
                        <button class="btn secondary" onclick="dashboard.closeModal(document.getElementById('analysis-report-modal'))">
                            <i data-lucide="x"></i>
                            Close
                        </button>
                    </div>
                </div>
            `;

            this.initializeLucideIcons();
            this.showNotification('Analysis report generated successfully', 'success');
        }, 3000);
    }

    exportAnalysis() {
        // In a real application, this would generate and download a PDF or Excel file
        this.showNotification('Analysis report exported successfully', 'success');

        // Simulate file download
        const link = document.createElement('a');
        link.href = '#';
        link.download = `investment-analysis-${new Date().toISOString().split('T')[0]}.pdf`;
        link.click();
    }

    refreshMarketIntelligence() {
        const intelligenceContainer = document.getElementById('market-intelligence-content');
        if (!intelligenceContainer) {
            this.showNotification('Market intelligence container not found', 'error');
            return;
        }

        // Show loading state
        const refreshBtn = document.getElementById('refresh-intelligence');
        const originalText = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<div class="loading-spinner"></div> Refreshing...';
        refreshBtn.disabled = true;

        // Simulate refresh delay
        setTimeout(() => {
            // Update market intelligence content with fresh data
            intelligenceContainer.innerHTML = `
                <div class="intelligence-grid">
                    <div class="intelligence-card">
                        <div class="intelligence-header">
                            <i data-lucide="trending-up"></i>
                            <h4>Market Trends</h4>
                        </div>
                        <div class="intelligence-content">
                            <p>AI adoption in enterprise continues to accelerate with 40% YoY growth in implementation.</p>
                            <div class="trend-indicator positive">↗ +12% this month</div>
                        </div>
                    </div>

                    <div class="intelligence-card">
                        <div class="intelligence-header">
                            <i data-lucide="users"></i>
                            <h4>Investor Sentiment</h4>
                        </div>
                        <div class="intelligence-content">
                            <p>Tech sector investments remain strong with focus on AI and sustainability solutions.</p>
                            <div class="sentiment-indicator positive">Positive Outlook</div>
                        </div>
                    </div>

                    <div class="intelligence-card">
                        <div class="intelligence-header">
                            <i data-lucide="dollar-sign"></i>
                            <h4>Funding Climate</h4>
                        </div>
                        <div class="intelligence-content">
                            <p>Series A funding availability increased by 25% compared to last quarter.</p>
                            <div class="funding-indicator positive">$2.1B available</div>
                        </div>
                    </div>

                    <div class="intelligence-card">
                        <div class="intelligence-header">
                            <i data-lucide="target"></i>
                            <h4>Competition Watch</h4>
                        </div>
                        <div class="intelligence-content">
                            <p>Three new competitors entered the market this month, indicating growing opportunity.</p>
                            <div class="competition-indicator neutral">Moderate Activity</div>
                        </div>
                    </div>
                </div>

                <div class="intelligence-update">
                    <p><strong>Last updated:</strong> ${new Date().toLocaleString()}</p>
                </div>
            `;

            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            this.initializeLucideIcons();
            this.showNotification('Market intelligence refreshed successfully', 'success');
        }, 2500);
    }

    // Real-time updates implementation
    startRealTimeUpdates() {
        // Poll for updates every 30 seconds
        this.realTimeInterval = setInterval(() => {
            this.pollForUpdates();
        }, 30000);
    }

    async pollForUpdates() {
        try {
            // Poll for messages, connections, and deals updates
            const [messagesResponse, connectionsResponse, dealsResponse] = await Promise.all([
                fetch('/api/messages'),
                fetch('/api/connections'),
                fetch('/api/deals')
            ]);

            if (messagesResponse.ok) {
                const messages = await messagesResponse.json();
                this.updateMessagesUI(messages);
            }

            if (connectionsResponse.ok) {
                const connections = await connectionsResponse.json();
                this.updateConnectionsUI(connections);
            }

            if (dealsResponse.ok) {
                const deals = await dealsResponse.json();
                this.updateDealsUIFromAPI(deals);
            }
        } catch (error) {
            console.error('Error polling for updates:', error);
            // Don't show notification for polling errors to avoid spam
        }
    }

    updateMessagesUI(messages) {
        // Update messages in networking tab if visible
        if (this.activeTab === 'networking') {
            const messagesContainer = document.querySelector('#networking-content .messages-list');
            if (messagesContainer && messages.length > 0) {
                // Simple update - in real app, compare with current state
                this.showNotification(`New messages: ${messages.length}`, 'info');
            }
        }
    }

    updateConnectionsUI(connections) {
        // Update connections count and UI
        if (this.activeTab === 'networking') {
            const connectionsContainer = document.querySelector('#networking-content .connections-list');
            if (connectionsContainer) {
                // Update connection badges/counts
                this.showNotification(`Connections updated: ${connections.length}`, 'info');
            }
        }
    }

    updateDealsUIFromAPI(deals) {
        // Update deals if there are changes
        if (this.activeTab === 'deals') {
            // Compare with current deals and update if necessary
            const currentDealIds = Array.from(this.deals.keys());
            const newDealIds = deals.map(d => d.id);

            if (JSON.stringify(currentDealIds.sort()) !== JSON.stringify(newDealIds.sort())) {
                // Update deals data and UI
                this.deals.clear();
                deals.forEach(deal => this.deals.set(deal.id, deal));
                this.updateDealsUI();
                this.showNotification('Deals updated', 'info');
            }
        }
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new InvestorDashboard();
});

// Handle browser back/forward navigation
window.addEventListener('popstate', (e) => {
    if (e.state && e.state.tab) {
        window.dashboard.setActiveTab(e.state.tab);
    }
});

// Export for global access
window.InvestorDashboard = InvestorDashboard;