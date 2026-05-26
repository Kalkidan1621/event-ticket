[file name]: profile.php
[file content begin]
<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get current admin data
$admin_id = $_SESSION['user_id'];
$admin_data = fetchSingle("SELECT * FROM users WHERE id = ?", [$admin_id], "i");

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            // Check if email already exists for another user
            $existing = fetchSingle("SELECT id FROM users WHERE email = ? AND id != ?", 
                                   [$email, $admin_id], "si");
            
            if ($existing) {
                $error = "Email already exists!";
            } else {
                // Check if username already exists for another user
                $existing_username = fetchSingle("SELECT id FROM users WHERE username = ? AND id != ?", 
                                                [$username, $admin_id], "si");
                
                if ($existing_username) {
                    $error = "Username already exists!";
                } else {
                    // Handle password change if requested
                    if (!empty($new_password)) {
                        if (empty($current_password)) {
                            $error = "Current password is required to set new password!";
                        } elseif ($current_password !== $admin_data['password']) {
                            $error = "Current password is incorrect!";
                        } elseif ($new_password !== $confirm_password) {
                            $error = "New passwords don't match!";
                        } else {
                            // Update with new password
                            $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, password = ? WHERE id = ?";
                            $stmt = executeQuery($sql, [$username, $email, $full_name, $new_password, $admin_id], "ssssi");
                            $message = "Profile updated successfully!";
                        }
                    } else {
                        // Update without changing password
                        $sql = "UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ?";
                        $stmt = executeQuery($sql, [$username, $email, $full_name, $admin_id], "sssi");
                        $message = "Profile updated successfully!";
                    }
                    
                    if (empty($error)) {
                        // Refresh admin data
                        $admin_data = fetchSingle("SELECT * FROM users WHERE id = ?", [$admin_id], "i");
                    }
                }
            }
        }
    }
}

// Get admin activity stats
$activity_stats = fetchSingle("
    SELECT 
        (SELECT COUNT(*) FROM ticket_sales WHERE payment_status = 'completed') as total_sales,
        (SELECT COUNT(*) FROM events) as total_events,
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'organizer') as total_organizers
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
      
        body{
            height: 100vh;
            width: 100vw;
            background: #e3e9e4;
        }
        main{
            display: flex;
            justify-content: space-between;
        }
        aside{
            margin: 10px 20px;
            flex-basis: 17%;
        }
        section{
            flex-basis: 83%;
            padding: 20px;
        }
        .roles{
            display: flex;
            justify-content: space-between;
            flex-direction: column;
        }
        
        .roles a{
            text-decoration: none;
            color: #0c18fd;
            padding: 10px 20px;
            margin-top: 15px;
        }
        .roles a:hover{
            text-decoration: none;
            background: #11111180;
        }
        .profile{
            height: 150px;
            font-family: cursive;
            box-shadow: 0 0 10px #0c18fd;
            border-radius: 15px;
        }
        .profile h3{
            text-align: center;
            color: #0c18fd;
        }
        .img{
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: auto;
        }
        .img img{
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: auto;
        }
        .ro{
            text-align: center;
            margin: 10px 60px;
            padding: 5px 0;
            border-radius: 20px;
            background: #0c18fd;
            color: #fff;
        }
        .act{
            color: #fff !important;
            background: #0c18fd;
        }
        
        /* Profile Page Styles */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #721c24;
        }
        
        .profile-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(45deg, #0c18fd, #6a11cb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5em;
            margin-right: 30px;
        }
        
        .profile-info h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .profile-info p {
            color: #666;
            margin: 5px 0;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .badge-admin {
            background: #0c18fd;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0c18fd;
            box-shadow: 0 0 0 3px rgba(12, 24, 253, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }
        
        .btn-primary {
            background: #0c18fd;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a14d4;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .toggle-icon {
            position: absolute;
            right: 10px;
            top: 38px;
            cursor: pointer;
            color: #666;
        }
        
        .activity-log {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #0c18fd;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: bold;
            color: #333;
        }
        
        .activity-description {
            color: #666;
            font-size: 0.9em;
        }
        
        .activity-time {
            color: #999;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <main>
        <aside>
            <div class="profile">
                <h3>Grand Event</h3>
                <div class="img"><img src="../img/pro.jpg" alt="Admin"></div>
                <div class="ro">ADMIN</div>
            </div>
            <div class="roles">
                <a href="adminpage.php">🛠️ Dashboard</a>
                <a href="userman.php">👥 User Management</a>
                <a href="event_management.php">🎭 Event Management</a>
                <a href="ticket_management.php">🎟️ Ticket Management</a>
                <a href="payment_manage.php">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php" class="act">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Admin Profile</h1>
            <hr>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Profile Overview -->
            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php 
                        $initials = strtoupper(substr($admin_data['full_name'] ?? $admin_data['username'], 0, 2));
                        echo $initials;
                        ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($admin_data['full_name'] ?? $admin_data['username']); ?></h2>
                        <p>📧 <?php echo htmlspecialchars($admin_data['email']); ?></p>
                        <p>👤 @<?php echo htmlspecialchars($admin_data['username']); ?></p>
                        <p>🎭 <span class="badge badge-admin">Administrator</span></p>
                        <p>📅 Member since: <?php echo date('F d, Y', strtotime($admin_data['created_at'])); ?></p>
                        <p>🆔 User ID: #<?php echo $admin_data['id']; ?></p>
                    </div>
                </div>
                
                <!-- Admin Stats -->
                <?php if ($activity_stats): ?>
                    <h3>System Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $activity_stats['total_sales'] ?? 0; ?></div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $activity_stats['total_events'] ?? 0; ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $activity_stats['total_users'] ?? 0; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $activity_stats['total_organizers'] ?? 0; ?></div>
                            <div class="stat-label">Total Organizers</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Update Profile Form -->
            <div class="form-container">
                <h2>Update Profile Information</h2>
                
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin_data['full_name'] ?? ''); ?>" 
                                   required><br>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin_data['email']); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin_data['username']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Account Created</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo date('F d, Y', strtotime($admin_data['created_at'])); ?>" 
                                   disabled>
                        </div>
                    </div>
                    
                    <h3 style="margin: 30px 0 20px 0;">Change Password</h3>
                    <small style="color: #666; margin-bottom: 20px; display: block;">Leave password fields blank to keep current password</small>
                    
                    <div class="form-row">
                        <div class="form-group password-toggle">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" 
                                   id="current_password">
                            <span class="toggle-icon" onclick="togglePassword('current_password')">👁️</span>
                        </div>
                        
                        <div class="form-group password-toggle">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" 
                                   id="new_password">
                            <span class="toggle-icon" onclick="togglePassword('new_password')">👁️</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group password-toggle">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   id="confirm_password">
                            <span class="toggle-icon" onclick="togglePassword('confirm_password')">👁️</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password Strength</label>
                            <div id="password-strength" style="height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 8px;">
                                <div id="password-strength-bar" style="height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s;"></div>
                            </div>
                            <small id="password-hint" style="color: #666; display: block; margin-top: 5px;"></small>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                        <button type="button" class="btn btn-secondary" onclick="clearForm()">Reset Form</button>
                    </div>
                </form>
            </div>
            
            <!-- Recent Activity -->
            <div class="activity-log">
                <h2>Recent Activity</h2>
                
                <div style="max-height: 300px; overflow-y: auto; margin-top: 20px;">
                    <?php
                    // Get recent activities for admin
                    $recent_activities = fetchAll("
                        SELECT 
                            'user_action' as type,
                            CONCAT('User action performed') as title,
                            CONCAT('Admin activity recorded') as description,
                            NOW() as date
                        FROM dual
                        
                        UNION
                        
                        SELECT 
                            'system_login' as type,
                            CONCAT('System login') as title,
                            CONCAT('Logged in to admin panel') as description,
                            DATE_SUB(NOW(), INTERVAL 1 HOUR) as date
                        
                        UNION
                        
                        SELECT 
                            'report_generated' as type,
                            CONCAT('Report generated') as title,
                            CONCAT('Generated system report') as description,
                            DATE_SUB(NOW(), INTERVAL 3 HOUR) as date
                        
                        ORDER BY date DESC
                        LIMIT 5
                    ");
                    ?>
                    
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    switch($activity['type']) {
                                        case 'user_action': echo '👤'; break;
                                        case 'system_login': echo '🔐'; break;
                                        case 'report_generated': echo '📊'; break;
                                        default: echo '📝';
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Account Actions -->
            <div class="form-container">
                <h2>Account Actions</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="exportUserData()">📥 Export My Data</button>
                    <button type="button" class="btn btn-secondary" onclick="viewActivityLog()">📋 View Full Activity Log</button>
                    <button type="button" class="btn btn-danger" onclick="confirmLogout()">🚪 Logout All Sessions</button>
                </div>
            </div>
        </section>
    </main>
    
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
        
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('password-strength-bar');
            const hint = document.getElementById('password-hint');
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.style.background = '#e9ecef';
                hint.textContent = '';
                return;
            }
            
            let strength = 0;
            let hintText = '';
            
            // Length check
            if (password.length >= 8) strength += 25;
            
            // Contains numbers
            if (/\d/.test(password)) strength += 25;
            
            // Contains lowercase
            if (/[a-z]/.test(password)) strength += 25;
            
            // Contains uppercase
            if (/[A-Z]/.test(password)) strength += 25;
            
            // Special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            // Cap at 100%
            strength = Math.min(strength, 100);
            
            // Update bar
            strengthBar.style.width = strength + '%';
            
            // Set color and hint based on strength
            if (strength < 50) {
                strengthBar.style.background = '#dc3545';
                hintText = 'Weak password';
            } else if (strength < 75) {
                strengthBar.style.background = '#ffc107';
                hintText = 'Moderate password';
            } else {
                strengthBar.style.background = '#28a745';
                hintText = 'Strong password';
            }
            
            hint.textContent = hintText;
        });
        
        // Clear form
        function clearForm() {
            document.querySelector('form').reset();
            document.getElementById('password-strength-bar').style.width = '0%';
            document.getElementById('password-hint').textContent = '';
        }
        
        // Export user data
        function exportUserData() {
            if (confirm('Do you want to export your personal data?')) {
                alert('Data export functionality would be implemented here.\nThis would generate a CSV/JSON file with your account data.');
            }
        }
        
        // View activity log
        function viewActivityLog() {
            alert('Full activity log would open in a new window or modal.');
        }
        
        // Confirm logout all sessions
        function confirmLogout() {
            if (confirm('Are you sure you want to logout from all sessions?\nThis will log you out from all devices.')) {
                window.location.href = '../logout.php?all=1';
            }
        }
        
        // Auto-save draft
        let saveTimeout;
        document.querySelectorAll('input, textarea').forEach(element => {
            element.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    // Show saving indicator
                    const originalText = document.querySelector('button[type="submit"]').textContent;
                    document.querySelector('button[type="submit"]').textContent = 'Saving...';
                    
                    setTimeout(function() {
                        document.querySelector('button[type="submit"]').textContent = originalText;
                    }, 1000);
                }, 2000);
            });
        });
    </script>
</body>
</html>
[file content end]