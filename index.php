<?php
// Start session for user authentication
session_start();
?>
<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Where Great Ideas Meet Smart Capital</h1>
        <p>Connect with investors and entrepreneurs to turn innovative ideas into successful businesses. Join the premier platform for startup funding and growth.</p>
        <div class="cta-buttons">
            <a href="login.php?role=investor" class="btn btn-primary">I'm an Investor</a>
            <a href="login.php?role=entrepreneur" class="btn btn-secondary">I'm an Entrepreneur</a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="features">
    <h2 class="section-title">Why Choose Startup Pitch Hub?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h3>Direct Connections</h3>
            <p>Connect directly with verified investors and entrepreneurs without intermediaries.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3>Smart Matching</h3>
            <p>Our algorithm matches you with the most relevant opportunities based on your preferences.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Secure Platform</h3>
            <p>Enterprise-grade security to protect your data and intellectual property.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-analytics"></i>
            </div>
            <h3>Analytics & Insights</h3>
            <p>Get detailed analytics on pitch performance and investor engagement.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-bell"></i>
            </div>
            <h3>Real-time Notifications</h3>
            <p>Stay updated with instant notifications for new pitches and investor interest.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-globe"></i>
            </div>
            <h3>Global Reach</h3>
            <p>Access a worldwide network of investors and startups from anywhere.</p>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="features" style="background: #f8fafc;">
    <h2 class="section-title">How It Works</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3>1. Sign Up</h3>
            <p>Create your account as an investor or entrepreneur and complete your profile.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-edit"></i>
            </div>
            <h3>2. Create/Explore</h3>
            <p>Entrepreneurs create pitches, investors browse and discover opportunities.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h3>3. Connect</h3>
            <p>Like pitches, express interest, and start meaningful conversations.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h3>4. Collaborate</h3>
            <p>Move conversations offline and work together to make deals happen.</p>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="features">
    <h2 class="section-title">Our Impact</h2>
    <div class="features-grid">
        <div class="stat-card">
            <div class="stat-number" data-target="500">0</div>
            <div class="stat-label">Successful Pitches</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="250">0</div>
            <div class="stat-label">Active Investors</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="1000">0</div>
            <div class="stat-label">Registered Startups</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="50">0</div>
            <div class="stat-label">Million $ Raised</div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="features" style="background: white;">
    <h2 class="section-title">Get In Touch</h2>
    <div class="features-grid">
        <div class="feature-card">
            <h3>Have Questions?</h3>
            <p>Our team is here to help you get the most out of Startup Pitch Hub.</p>
            <form style="margin-top: 1.5rem;">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
        
        <div class="feature-card">
            <h3>Ready to Get Started?</h3>
            <p>Join thousands of entrepreneurs and investors who are already building the future together.</p>
            <div style="margin-top: 2rem; text-align: center;">
                <a href="login.php?role=investor" class="btn btn-primary" style="margin: 0.5rem;">Join as Investor</a>
                <a href="login.php?role=entrepreneur" class="btn btn-secondary" style="margin: 0.5rem;">Join as Entrepreneur</a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
