<?php
// Start session and check if user is logged in as entrepreneur
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'entrepreneur') {
    // Redirect to login if not authenticated as entrepreneur
    header('Location: login.php?role=entrepreneur');
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Entrepreneur Dashboard</h1>
        <p>Welcome back, <?php echo $_SESSION['user_name']; ?>! Manage your startup <?php echo $_SESSION['startup_name']; ?> and track investor interest.</p>
    </div>

    <!-- Statistics -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number" data-target="3">0</div>
            <div class="stat-label">Active Pitches</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="42">0</div>
            <div class="stat-label">Total Views</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="15">0</div>
            <div class="stat-label">Investor Likes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="8">0</div>
            <div class="stat-label">Expressions of Interest</div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- My Pitches Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>My Pitches</h3>
                <button class="btn btn-primary" onclick="openModal('createPitchModal')">Create New Pitch</button>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pitch Title</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>AI-Powered Customer Support</td>
                            <td>SaaS</td>
                            <td>$500,000</td>
                            <td><span style="color: #10b981;">Active</span></td>
                            <td>156</td>
                            <td>23</td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-warning btn-sm">Edit</button>
                                <button class="btn btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Eco-Friendly Packaging</td>
                            <td>Sustainability</td>
                            <td>$250,000</td>
                            <td><span style="color: #10b981;">Active</span></td>
                            <td>89</td>
                            <td>12</td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-warning btn-sm">Edit</button>
                                <button class="btn btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Health Monitoring Wearable</td>
                            <td>Healthcare</td>
                            <td>$750,000</td>
                            <td><span style="color: #f59e0b;">Draft</span></td>
                            <td>0</td>
                            <td>0</td>
                            <td>
                                <button class="btn btn-info btn-sm">View</button>
                                <button class="btn btn-warning btn-sm">Edit</button>
                                <button class="btn btn-primary btn-sm">Publish</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Investor Interest Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Investor Interest</h3>
                <span class="badge">8 expressions of interest</span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Pitch</th>
                            <th>Interest Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Venture Capital</td>
                            <td>AI-Powered Customer Support</td>
                            <td>2024-01-15</td>
                            <td><span style="color: #10b981;">New</span></td>
                            <td>
                                <button class="btn btn-primary btn-sm">Contact</button>
                                <button class="btn btn-info btn-sm">View Profile</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Tech Growth Partners</td>
                            <td>Eco-Friendly Packaging</td>
                            <td>2024-01-12</td>
                            <td><span style="color: #3b82f6;">Contacted</span></td>
                            <td>
                                <button class="btn btn-primary btn-sm">Follow Up</button>
                                <button class="btn btn-info btn-sm">View Profile</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Green Energy Fund</td>
                            <td>Eco-Friendly Packaging</td>
                            <td>2024-01-10</td>
                            <td><span style="color: #f59e0b;">Scheduled Meeting</span></td>
                            <td>
                                <button class="btn btn-primary btn-sm">Meeting Details</button>
                                <button class="btn btn-info btn-sm">View Profile</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analytics Overview -->
        <div class="content-section">
            <div class="section-header">
                <h3>Pitch Performance Analytics</h3>
                <select class="form-control" style="width: auto;">
                    <option>Last 7 days</option>
                    <option selected>Last 30 days</option>
                    <option>Last 90 days</option>
                </select>
            </div>
            
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h4>Total Views</h4>
                    <div class="stat-number">245</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 75%; background: #3b82f6;"></div>
                    </div>
                    <small>+15% from last month</small>
                </div>
                
                <div class="analytics-card">
                    <h4>Engagement Rate</h4>
                    <div class="stat-number">18.5%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 65%; background: #10b981;"></div>
                    </div>
                    <small>+8% from last month</small>
                </div>
                
                <div class="analytics-card">
                    <h4>Conversion Rate</h4>
                    <div class="stat-number">6.2%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 40%; background: #f59e0b;"></div>
                    </div>
                    <small>+3% from last month</small>
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
                    <div class="activity-icon" style="background: #10b981;">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="activity-content">
                        <p><strong>25 investors</strong> viewed your AI-Powered Customer Support pitch</p>
                        <small>Today</small>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: #3b82f6;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="activity-content">
                        <p><strong>Tech Growth Partners</strong> liked your Eco-Friendly Packaging pitch</p>
                        <small>Yesterday</small>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: #f59e0b;">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="activity-content">
                        <p>New message from <strong>Green Energy Fund</strong></p>
                        <small>2 days ago</small>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: #ef4444;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="activity-content">
                        <p>Pitch <strong>Health Monitoring Wearable</strong> needs completion</p>
                        <small>3 days ago</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Pitch Modal -->
<div id="createPitchModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('createPitchModal')">&times;</span>
        <h2>Create New Pitch</h2>
        <form>
            <div class="form-group">
                <label>Pitch Title *</label>
                <input type="text" class="form-control" placeholder="Enter your pitch title" required>
            </div>
            
            <div class="form-group">
                <label>Category *</label>
                <select class="form-control" required>
                    <option value="">Select category</option>
                    <option>SaaS</option>
                    <option>Healthcare</option>
                    <option>FinTech</option>
                    <option>E-commerce</option>
                    <option>Sustainability</option>
                    <option>Education</option>
                    <option>Manufacturing</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Funding Goal ($) *</label>
                <input type="number" class="form-control" placeholder="Enter funding amount" required>
            </div>
            
            <div class="form-group">
                <label>Short Description *</label>
                <textarea class="form-control" rows="3" placeholder="Brief description of your startup" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Detailed Pitch</label>
                <textarea class="form-control" rows="6" placeholder="Detailed business plan, market analysis, team information..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Upload Images (Max 5)</label>
                <input type="file" class="form-control" multiple accept="image/*">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPitchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save as Draft</button>
                <button type="submit" class="btn btn-success">Publish Now</button>
            </div>
        </form>
    </div>
</div>

<style>
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.analytics-card {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    border-left: 4px solid #667eea;
}

.analytics-card h4 {
    margin-bottom: 1rem;
    color: #374151;
    font-size: 0.9rem;
    font-weight: 600;
}

.analytics-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.5rem;
}

.analytics-card .progress-bar {
    margin: 0.5rem auto;
    max-width: 100px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.badge {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 0.5rem;
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
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-content p {
    margin: 0;
    font-weight: 500;
}

.activity-content small {
    color: #6b7280;
    font-size: 0.875rem;
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
    max-width: 600px;
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
