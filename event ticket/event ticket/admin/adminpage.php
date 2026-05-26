<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get statistics
$total_users = fetchSingle("SELECT COUNT(*) as count FROM users")['count'];
$total_events = fetchSingle("SELECT COUNT(*) as count FROM events")['count'];
$total_tickets_sold = fetchSingle("SELECT COALESCE(SUM(ts.quantity), 0) as count FROM ticket_sales ts WHERE ts.payment_status='completed'")['count'];
$total_revenue = fetchSingle("SELECT COALESCE(SUM(ts.total_amount), 0) as amount FROM ticket_sales ts WHERE ts.payment_status='completed'")['amount'];

// Get recent activities
$recent_activities = fetchAll("
    SELECT 
        'user_registered' as type,
        username as title,
        'New user registered' as description,
        created_at as date,
        CONCAT('userman.php?edit=', id) as link
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 
        'event_created' as type,
        event_name as title,
        'New event created' as description,
        created_at as date,
        CONCAT('event_management.php?view=', id) as link
    FROM events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 
        'ticket_sold' as type,
        CONCAT('Sale #', ts.sale_id) as title,
        CONCAT('Ticket sold for $', ts.total_amount) as description,
        ts.sale_date as date,
        CONCAT('payment_manage.php?view=', ts.sale_id) as link
    FROM ticket_sales ts
    WHERE ts.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND ts.payment_status = 'completed'
    
    ORDER BY date DESC 
    LIMIT 10
");

// Get top events
$top_events = fetchAll("
    SELECT 
        e.event_name,
        e.event_category,
        COUNT(ts.sale_id) as tickets_sold,
        COALESCE(SUM(ts.total_amount), 0) as revenue
    FROM events e
    LEFT JOIN tickets t ON e.id = t.event_id
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    GROUP BY e.id
    ORDER BY revenue DESC
    LIMIT 5
");

// Get recent payments
$recent_payments = fetchAll("
    SELECT 
        ts.sale_id,
        ts.total_amount,
        ts.sale_date,
        ts.payment_status,
        u.username,
        e.event_name
    FROM ticket_sales ts
    JOIN tickets t ON ts.ticket_id = t.ticket_id
    JOIN events e ON t.event_id = e.id
    JOIN users u ON ts.user_id = u.id
    ORDER BY ts.sale_date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        
        /* Dashboard Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.3em;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th, .table td {
            padding: 12px;
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
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
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
        
        .btn {
            padding: 8px 16px;
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
                <a href="adminpage.php" class="act">🛠️ Dashboard</a>
                <a href="userman.php">👥 User Management</a>
                <a href="event_management.php">🎭 Event Management</a>
                <a href="ticket_management.php">🎟️ Ticket Management</a>
                <a href="payment_manage.php">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Admin Dashboard</h1>
            <hr>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <a href="userman.php" class="btn btn-primary">View Users</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-value"><?php echo number_format($total_events); ?></div>
                    <a href="event_management.php" class="btn btn-primary">View Events</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Tickets Sold</div>
                    <div class="stat-value"><?php echo number_format($total_tickets_sold); ?></div>
                    <a href="ticket_management.php" class="btn btn-primary">View Tickets</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <a href="payment_manage.php" class="btn btn-primary">View Payments</a>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="dashboard-section">
                <h2 class="section-title">Recent Activities</h2>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    switch($activity['type']) {
                                        case 'user_registered': echo '👤'; break;
                                        case 'event_created': echo '🎭'; break;
                                        case 'ticket_sold': echo '🎫'; break;
                                        default: echo '📝';
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></div>
                                </div>
                                <?php if ($activity['link']): ?>
                                    <a href="<?php echo $activity['link']; ?>" class="btn btn-primary">View</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No recent activities</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Events -->
            <div class="dashboard-section">
                <h2 class="section-title">Top Performing Events</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Category</th>
                            <th>Tickets Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_events)): ?>
                            <?php foreach ($top_events as $event): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_category']); ?></td>
                                    <td><?php echo number_format($event['tickets_sold']); ?></td>
                                    <td><strong>$<?php echo number_format($event['revenue'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #666;">No event data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Payments -->
            <div class="dashboard-section">
                <h2 class="section-title">Recent Payments</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>User</th>
                            <th>Event</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_payments)): ?>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['sale_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['username']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['event_name']); ?></td>
                                    <td><strong>$<?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['sale_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['payment_status'] === 'completed' ? 'success' : ($payment['payment_status'] === 'pending' ? 'pending' : 'danger'); ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666;">No payment data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>