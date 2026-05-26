<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Get current user data
$user_data = fetchSingle("SELECT * FROM users WHERE id = ?", [$user_id], "i");

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Invalid email format";
        } else {
            // Check if email is already taken by another user
            $check_email = fetchSingle("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id], "si");
            
            if ($check_email) {
                $error_msg = "Email is already taken";
            } else {
                // Handle password change if requested
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        $error_msg = "Current password is required to change password";
                    } elseif ($new_password !== $confirm_password) {
                        $error_msg = "New passwords do not match";
                    } elseif (strlen($new_password) < 6) {
                        $error_msg = "New password must be at least 6 characters";
                    } elseif ($current_password !== $user_data['password']) {
                        $error_msg = "Current password is incorrect";
                    } else {
                        // Update password
                        $sql = "UPDATE users SET password = ?, full_name = ?, email = ? WHERE id = ?";
                        $stmt = executeQuery($sql, [$new_password, $full_name, $email, $user_id], "sssi");
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['email'] = $email;
                        $success_msg = "Profile and password updated successfully!";
                    }
                } else {
                    // Update without password change
                    $sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
                    $stmt = executeQuery($sql, [$full_name, $email, $user_id], "ssi");
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $success_msg = "Profile updated successfully!";
                }
                
                // Refresh user data
                $user_data = fetchSingle("SELECT * FROM users WHERE id = ?", [$user_id], "i");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - G5 Event</title>
    <style>
        /* Keep existing sidebar styles from user_dash.php */
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
      
        body{
            height: 100vh;
            width: 100vw;
            background: #e3e9e4;
            font-family: Arial, sans-serif;
        }
        main{
            display: flex;
        }
        aside{
            width: 17%;
            padding: 20px;
            background: #fff;
            min-height: 100vh;
            position: fixed;
            height: 100%;
        }
        section{
            width: 83%;
            margin-left: 17%;
            padding: 20px;
        }
        .roles{
            display: flex;
            flex-direction: column;
        }
        
        .roles a{
            text-decoration: none;
            color: #0c18fd;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .roles a:hover{
            background: rgba(12, 24, 253, 0.1);
        }
        .profile{
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .profile h3{
            color: #0c18fd;
            margin: 10px 0;
        }
        .img{
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 10px;
            overflow: hidden;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .img img{
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ro{
            background: #0c18fd;
            color: #fff;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
            margin-top: 10px;
        }
        .act{
            background: #0c18fd !important;
            color: #fff !important;
        }
        
        /* Profile Styles */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .alert {
            padding: 15px 20px;
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
        
        .profile-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(45deg, #0c18fd, #6a11cb);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: white;
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .profile-info p {
            color: #666;
            margin: 5px 0;
        }
        
        .member-since {
            color: #999;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.3em;
            margin-bottom: 25px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0c18fd;
            box-shadow: 0 0 0 3px rgba(12, 24, 253, 0.1);
        }
        
        .form-help {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #0c18fd;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a14d4;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(12, 24, 253, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
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
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        
        .danger-zone {
            border: 2px solid #f8d7da;
            background: #fff5f5;
        }
        
        .danger-zone .section-title {
            color: #721c24;
            border-bottom-color: #f8d7da;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            margin-top: 10px;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: inline-block;
            padding: 10px 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <main>
        <aside>
            <div class="profile">
                <div class="img">
                    <img src="../img/pro.jpg" alt="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>">
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></h3>
                <div class="ro">USER</div>
            </div>
            <div class="roles">
                <a href="user_dash.php">🛠️ Dashboard</a>
                <a href="browse.php">🔍 Browse Events</a>
                <a href="book.php">🎟️ Book Tickets</a>
                <a href="ticket.php">💳 Payments</a>
                <a href="payment.php">📁 My Tickets</a>
                <a href="profile.php" class="act">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <div class="profile-container">
                <!-- Success/Error Messages -->
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>
                
                <h1>Profile Management</h1>
                <hr>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php 
                        $initials = strtoupper(substr($user_data['full_name'] ?? $user_data['username'], 0, 2));
                        echo $initials;
                        ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user_data['full_name'] ?? $user_data['username']); ?></h2>
                        <p>📧 <?php echo htmlspecialchars($user_data['email']); ?></p>
                        <p>👤 @<?php echo htmlspecialchars($user_data['username']); ?></p>
                        <p class="member-since">Member since <?php echo date('F Y', strtotime($user_data['created_at'])); ?></p>
                    </div>
                </div>
                
                <!-- Profile Stats -->
                <div class="profile-section">
                    <h3 class="section-title">Your Statistics</h3>
                    <?php 
                    $stats = fetchSingle("
                        SELECT 
                            (SELECT COUNT(*) FROM ticket_sales WHERE user_id = ?) as total_bookings,
                            (SELECT SUM(total_amount) FROM ticket_sales WHERE user_id = ? AND payment_status = 'completed') as total_spent,
                            (SELECT COUNT(DISTINCT event_id) FROM ticket_sales ts 
                             JOIN tickets t ON ts.ticket_id = t.ticket_id 
                             WHERE ts.user_id = ?) as events_attended
                    ", [$user_id, $user_id, $user_id], "iii");
                    ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['events_attended'] ?? 0; ?></div>
                            <div class="stat-label">Events Attended</div>
                        </div>
                    </div>
                </div>
                
                <!-- Update Profile Form -->
                <div class="profile-section">
                    <h3 class="section-title">Update Profile Information</h3>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>"
                                   placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['username']); ?>"
                                   disabled>
                            <div class="form-help">Username cannot be changed</div>
                        </div>
                        
                        <h4 style="margin: 30px 0 20px 0; color: #333;">Change Password (Optional)</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <div class="password-toggle">
                                <input type="password" name="current_password" class="form-control" 
                                       placeholder="Enter current password">
                                <span class="password-toggle-icon" onclick="togglePassword(this)">👁️</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div class="password-toggle">
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="Enter new password (min. 6 characters)">
                                <span class="password-toggle-icon" onclick="togglePassword(this)">👁️</span>
                            </div>
                            <div class="form-help">Leave blank if you don't want to change password</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <div class="password-toggle">
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm new password">
                                <span class="password-toggle-icon" onclick="togglePassword(this)">👁️</span>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Update Profile
                        </button>
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                    </form>
                </div>
                
                <!-- Danger Zone -->
                <div class="profile-section danger-zone">
                    <h3 class="section-title">Danger Zone</h3>
                    <p style="color: #721c24; margin-bottom: 20px;">
                        ⚠️ These actions are irreversible. Please proceed with caution.
                    </p>
                    
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <button class="btn btn-danger" onclick="confirmDelete()">
                            Delete Account
                        </button>
                        <button class="btn btn-secondary" onclick="exportData()">
                            Export My Data
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <script>
        function togglePassword(icon) {
            const input = icon.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }
        
        function confirmDelete() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone and all your data will be permanently lost.')) {
                alert('Account deletion feature would be implemented here. For now, please contact support.');
            }
        }
        
        function exportData() {
            alert('Data export feature would be implemented here. You would receive an email with all your data.');
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const newPassword = document.querySelector('input[name="new_password"]');
                const confirmPassword = document.querySelector('input[name="confirm_password"]');
                
                if (newPassword.value && newPassword.value.length < 6) {
                    alert('New password must be at least 6 characters');
                    e.preventDefault();
                    return;
                }
                
                if (newPassword.value !== confirmPassword.value) {
                    alert('New passwords do not match');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>