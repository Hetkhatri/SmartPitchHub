<?php
// Start session and check if user is logged in as investor
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'investor') {
    // For demo purposes, we'll set the session if not set
    $_SESSION['user_role'] = 'investor';
    $_SESSION['user_name'] = 'John Investor';
    $_SESSION['user_id'] = 1;
}
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Investor Dashboard</h1>
        <p>Welcome back, <?php echo $_SESSION['user_name']; ?>! Here's your investment overview.</p>
    </div>

    <!-- Statistics -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number" data-target="42">0</div>
            <div class="stat-label">Pitches Viewed</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="15">0</div>
            <div class="stat-label">Pitches Liked</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="8">0</div>
            <div class="stat-label">Expressions of Interest</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="3">0</div>
            <div class="stat-label">Active Conversations</div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Recent Pitches Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent Pitches</h3>
                <a href="browse-pitches.php" class="btn btn-primary">Browse All Pitches</a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Startup Name</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>TechFlow Solutions</td>
                            <td>SaaS</td>
                            <td>$500,000</td>
                            <td><span style="color: #10b981;">Active</span></td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-success btn-sm">Like</button>
                            </td>
                        </tr>
                        <tr>
                            <td>EcoGrow Farms</td>
                            <td>Agriculture</td>
                            <td>$250,000</td>
                            <td><span style="color: #10b981;">Active</span></td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-success btn-sm">Like</button>
                            </td>
                        </tr>
                        <tr>
                            <td>HealthTech AI</td>
                            <td>Healthcare</td>
                            <td>$1,200,000</td>
                            <td><span style="color: #f59e0b;">Reviewing</span></td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-success btn-sm">Like</button>
                            </td>
                        </tr>
                        <tr>
                            <td>FinTech Pro</td>
                            <td>Finance</td>
                            <td>$750,000</td>
                            <td><span style="color: #10b981;">Active</span></td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-success btn-sm">Like</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Saved Pitches Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Saved Pitches</h3>
                <span class="badge">15 pitches</span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Startup Name</th>
                            <th>Saved Date</th>
                            <th>Interest Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>GreenEnergy Tech</td>
                            <td>2024-01-15</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 85%; background: #10b981;"></div>
                                </div>
                                <small>85% Match</small>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm">Contact</button>
                                <button class="btn btn-danger btn-sm">Remove</button>
                            </td>
                        </tr>
                        <tr>
                            <td>EdTech Solutions</td>
                            <td>2024-01-10</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 72%; background: #3b82f6;"></div>
                                </div>
                                <small>72% Match</small>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm">Contact</button>
                                <button class="btn btn-danger btn-sm">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Investment Preferences -->
        <div class="content-section">
            <div class="section-header">
                <h3>Investment Preferences</h3>
                <button class="btn btn-secondary" onclick="openModal('preferencesModal')">Edit Preferences</button>
            </div>
            
            <div class="preferences-grid">
                <div class="preference-item">
                    <strong>Investment Range:</strong> $50,000 - $500,000
                </div>
                <div class="preference-item">
                    <strong>Industries:</strong> SaaS, Healthcare, FinTech, Green Energy
                </div>
                <div class="preference-item">
                    <strong>Stages:</strong> Seed, Series A
                </div>
                <div class="preference-item">
                    <strong>Location:</strong> Global (Preferred: North America, Europe)
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent Activity</h3>
            </div>
            
            <div class="activity-feed">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="activity-content">
                        <p>Liked <strong>TechFlow Solutions</strong> pitch</p>
                        <small>2 hours ago</small>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="activity-content">
                        <p>Sent message to <strong>EcoGrow Farms</strong></p>
                        <small>1 day ago</small>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="activity-content">
                        <p>Viewed <strong>HealthTech AI</strong> profile</p>
                        <small>2 days ago</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preferences Modal -->
<div id="preferencesModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('preferencesModal')">&times;</span>
        <h2>Edit Investment Preferences</h2>
        <form>
            <div class="form-group">
                <label>Investment Range ($)</label>
                <div class="range-inputs">
                    <input type="number" class="form-control" placeholder="Min" value="50000">
                    <span>to</span>
                    <input type="number" class="form-control" placeholder="Max" value="500000">
                </div>
            </div>
            
            <div class="form-group">
                <label>Preferred Industries</label>
                <select multiple class="form-control">
                    <option selected>SaaS</option>
                    <option selected>Healthcare</option>
                    <option selected>FinTech</option>
                    <option selected>Green Energy</option>
                    <option>E-commerce</option>
                    <option>Education</option>
                    <option>Manufacturing</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Funding Stages</label>
                <div class="checkbox-group">
                    <label><input type="checkbox" checked> Pre-seed</label>
                    <label><input type="checkbox" checked> Seed</label>
                    <label><input type="checkbox" checked> Series A</label>
                    <label><input type="checkbox"> Series B+</label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Preferences</button>
        </form>
    </div>
</div>

<style>
.progress-bar {
    width: 100px;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    margin-bottom: 4px;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
}

.preferences-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.preference-item {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.activity-feed {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.range-inputs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.close {
    float: right;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
}
</style>

<?php include 'includes/footer.php'; ?>
