<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name'] ?? '');
        
        // Check if user exists
        $existing = fetchSingle("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email], "ss");
        
        if ($existing) {
            $error = "Username or email already exists!";
        } else {
            $sql = "INSERT INTO users (username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?)";
            $stmt = executeQuery($sql, [$username, $email, $password, $role, $full_name], "sssss");
            $message = "User created successfully!";
        }
    }
    
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Check if username/email exists for another user
        $existing = fetchSingle("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?", 
                               [$username, $email, $user_id], "ssi");
        
        if ($existing) {
            $error = "Username or email already exists!";
        } else {
            if (!empty($password)) {
                $sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, full_name = ? WHERE id = ?";
                $stmt = executeQuery($sql, [$username, $email, $password, $role, $full_name, $user_id], "sssssi");
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, role = ?, full_name = ? WHERE id = ?";
                $stmt = executeQuery($sql, [$username, $email, $role, $full_name, $user_id], "ssssi");
            }
            $message = "User updated successfully!";
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        // Don't delete if user has bookings
        $bookings = fetchSingle("SELECT COUNT(*) as count FROM ticket_sales WHERE user_id = ?", [$user_id], "i");
        
        if ($bookings['count'] > 0) {
            $error = "Cannot delete user with existing bookings!";
        } else {
            $sql = "DELETE FROM users WHERE id = ? AND role != 'admin'";
            $stmt = executeQuery($sql, [$user_id], "i");
            $message = "User deleted successfully!";
        }
    }
}

// Get all users
$users = fetchAll("SELECT * FROM users ORDER BY created_at DESC");

// Get user for editing
$edit_user = null;
if ($action == 'edit' && $user_id) {
    $edit_user = fetchSingle("SELECT * FROM users WHERE id = ?", [$user_id], "i");
}

// Get user statistics
if ($action == 'view' && $user_id) {
    $user_stats = fetchSingle("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM ticket_sales WHERE user_id = u.id) as total_bookings,
            (SELECT SUM(total_amount) FROM ticket_sales WHERE user_id = u.id AND payment_status = 'completed') as total_spent,
            (SELECT COUNT(DISTINCT e.id) FROM ticket_sales ts 
             JOIN tickets t ON ts.ticket_id = t.ticket_id 
             JOIN events e ON t.event_id = e.id 
             WHERE ts.user_id = u.id) as events_attended
        FROM users u 
        WHERE u.id = ?
    ", [$user_id], "i");
    
    $user_bookings = fetchAll("
        SELECT 
            ts.sale_id,
            ts.quantity,
            ts.total_amount,
            ts.sale_date,
            ts.payment_status,
            t.ticket_type,
            e.event_name,
            e.event_date
        FROM ticket_sales ts
        JOIN tickets t ON ts.ticket_id = t.ticket_id
        JOIN events e ON t.event_id = e.id
        WHERE ts.user_id = ?
        ORDER BY ts.sale_date DESC
        LIMIT 10
    ", [$user_id], "i");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
        
        /* User Management Styles */
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
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table th, .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
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
        
        .badge-organizer {
            background: #17a2b8;
            color: white;
        }
        
        .badge-user {
            background: #28a745;
            color: white;
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
        
        .user-detail-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #0c18fd, #6a11cb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2em;
            margin-right: 20px;
        }
        
        .user-info h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .user-info p {
            color: #666;
            margin: 5px 0;
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
                <a href="userman.php" class="act">👥 User Management</a>
                <a href="event_management.php">🎭 Event Management</a>
                <a href="ticket_management.php">🎟️ Ticket Management</a>
                <a href="payment_manage.php">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>User Management</h1>
            <hr>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($action == 'create' || ($action == 'edit' && $edit_user)): ?>
                <!-- Create/Edit User Form -->
                <div class="form-container">
                    <h2><?php echo $action == 'edit' ? 'Edit User' : 'Create New User'; ?></h2>
                    
                    <form method="POST">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                            <input type="hidden" name="update_user" value="1">
                        <?php else: ?>
                            <input type="hidden" name="create_user" value="1">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['full_name'] ?? '') : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-control" required>
                                <option value="user" <?php echo ($edit_user && $edit_user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                <option value="organizer" <?php echo ($edit_user && $edit_user['role'] == 'organizer') ? 'selected' : ''; ?>>Organizer</option>
                                <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password <?php echo $action == 'edit' ? '(Leave blank to keep current)' : '*'; ?></label>
                            <input type="password" name="password" class="form-control" 
                                   <?php echo $action == 'create' ? 'required' : ''; ?>>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action == 'edit' ? 'Update User' : 'Create User'; ?>
                        </button>
                        <a href="userman.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                
            <?php elseif ($action == 'view' && $user_id && $user_stats): ?>
                <!-- User Details View -->
                <div class="user-detail-section">
                    <div class="user-header">
                        <div class="user-avatar">
                            <?php 
                            $initials = strtoupper(substr($user_stats['full_name'] ?? $user_stats['username'], 0, 2));
                            echo $initials;
                            ?>
                        </div>
                        <div class="user-info">
                            <h2><?php echo htmlspecialchars($user_stats['full_name'] ?? $user_stats['username']); ?></h2>
                            <p>📧 <?php echo htmlspecialchars($user_stats['email']); ?></p>
                            <p>👤 @<?php echo htmlspecialchars($user_stats['username']); ?></p>
                            <p>🎭 
                                <span class="badge badge-<?php echo $user_stats['role']; ?>">
                                    <?php echo ucfirst($user_stats['role']); ?>
                                </span>
                            </p>
                            <p>📅 Member since: <?php echo date('F d, Y', strtotime($user_stats['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <!-- User Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $user_stats['total_bookings'] ?? 0; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($user_stats['total_spent'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $user_stats['events_attended'] ?? 0; ?></div>
                            <div class="stat-label">Events Attended</div>
                        </div>
                    </div>
                    
                    <!-- User Bookings -->
                    <?php if (!empty($user_bookings)): ?>
                        <h3>Recent Bookings</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Event</th>
                                    <th>Ticket Type</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_bookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking['sale_id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['ticket_type']); ?></td>
                                        <td><?php echo $booking['quantity']; ?></td>
                                        <td><strong>$<?php echo number_format($booking['total_amount'], 2); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($booking['sale_date'])); ?></td>
                                        <td>
                                            <span class="badge" style="background: <?php 
                                                echo $booking['payment_status'] == 'completed' ? '#d4edda' : 
                                                    ($booking['payment_status'] == 'pending' ? '#fff3cd' : '#f8d7da');
                                                ?>; color: <?php 
                                                echo $booking['payment_status'] == 'completed' ? '#155724' : 
                                                    ($booking['payment_status'] == 'pending' ? '#856404' : '#721c24');
                                                ?>;">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No bookings found for this user.</p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px;">
                        <a href="userman.php" class="btn btn-secondary">Back to Users</a>
                        <a href="userman.php?action=edit&id=<?php echo $user_id; ?>" class="btn btn-primary">Edit User</a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Users List -->
                <div class="header-actions">
                    <div>
                        <h2>All Users (<?php echo count($users); ?>)</h2>
                    </div>
                    <div>
                        <a href="userman.php?action=create" class="btn btn-success">+ Create New User</a>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="userman.php?action=view&id=<?php echo $user['id']; ?>" class="btn btn-primary">View</a>
                                        <a href="userman.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-success">Edit</a>
                                        <?php if ($user['role'] != 'admin'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="delete_user" value="1">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666;">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>