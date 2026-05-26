<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get date range parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'revenue';

// Get revenue report
$revenue_report = fetchAll("
    SELECT 
        DATE(ts.sale_date) as sale_day,
        COUNT(*) as sales_count,
        SUM(ts.quantity) as tickets_sold,
        SUM(ts.total_amount) as daily_revenue,
        AVG(ts.total_amount) as avg_sale_amount
    FROM ticket_sales ts
    WHERE ts.payment_status = 'completed'
        AND DATE(ts.sale_date) BETWEEN ? AND ?
    GROUP BY DATE(ts.sale_date)
    ORDER BY sale_day
", [$date_from, $date_to], "ss");

// Get event performance report
$event_performance = fetchAll("
    SELECT 
        e.event_name,
        e.event_category,
        e.event_date,
        COUNT(ts.sale_id) as sales_count,
        SUM(ts.quantity) as tickets_sold,
        SUM(ts.total_amount) as total_revenue,
        AVG(ts.total_amount) as avg_sale_amount,
        (SUM(ts.quantity) * 100.0 / e.total_tickets) as occupancy_rate
    FROM events e
    LEFT JOIN tickets t ON e.id = t.event_id
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    WHERE e.event_date BETWEEN ? AND ?
    GROUP BY e.id
    ORDER BY total_revenue DESC
", [$date_from, $date_to], "ss");

// Get ticket type performance
$ticket_performance = fetchAll("
    SELECT 
        t.ticket_type,
        COUNT(ts.sale_id) as sales_count,
        SUM(ts.quantity) as tickets_sold,
        SUM(ts.total_amount) as total_revenue,
        AVG(ts.total_amount) as avg_sale_amount,
        AVG(t.price) as avg_ticket_price
    FROM tickets t
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    GROUP BY t.ticket_type
    ORDER BY tickets_sold DESC
");

// Get user activity report
$user_activity = fetchAll("
    SELECT 
        u.username,
        u.email,
        u.role,
        COUNT(ts.sale_id) as purchase_count,
        SUM(ts.quantity) as tickets_purchased,
        SUM(ts.total_amount) as total_spent,
        MAX(ts.sale_date) as last_purchase
    FROM users u
    LEFT JOIN ticket_sales ts ON u.id = ts.user_id AND ts.payment_status = 'completed'
    WHERE u.role IN ('user', 'organizer')
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 20
");

// Get summary statistics
$summary_stats = fetchSingle("
    SELECT 
        COUNT(DISTINCT ts.sale_id) as total_sales,
        SUM(ts.quantity) as total_tickets_sold,
        SUM(ts.total_amount) as total_revenue,
        AVG(ts.total_amount) as avg_sale_amount,
        COUNT(DISTINCT e.id) as active_events,
        COUNT(DISTINCT u.id) as active_users
    FROM ticket_sales ts
    JOIN tickets t ON ts.ticket_id = t.ticket_id
    JOIN events e ON t.event_id = e.id
    JOIN users u ON ts.user_id = u.id
    WHERE ts.payment_status = 'completed'
        AND ts.sale_date BETWEEN ? AND ?
", [$date_from, $date_to], "ss");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
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
        
        /* Reports & Analytics Styles */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .report-tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 30px;
        }
        
        .report-tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1em;
            color: #6c757d;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .report-tab.active {
            color: #0c18fd;
            border-bottom: 2px solid #0c18fd;
            font-weight: bold;
        }
        
        .report-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.5em;
            margin-bottom: 25px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
            position: relative;
        }
        
        .chart-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #f8f9fa 25%, #e9ecef 25%, #e9ecef 50%, #f8f9fa 50%, #f8f9fa 75%, #e9ecef 75%, #e9ecef);
            background-size: 20px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.2em;
        }
        
        .export-options {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .date-range {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .date-range-text {
            font-weight: bold;
            color: #333;
        }
        
        .metric-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .metric-up {
            background: #d4edda;
            color: #155724;
        }
        
        .metric-down {
            background: #f8d7da;
            color: #721c24;
        }
        
        .metric-neutral {
            background: #fff3cd;
            color: #856404;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .comparison-grid {
                grid-template-columns: 1fr;
            }
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
                <a href="report.php" class="act">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Reports & Analytics</h1>
            <hr>
            
            <!-- Date Range Filter -->
            <div class="filter-section">
                <h3>Report Period</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-control">
                            <option value="revenue" <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                            <option value="events" <?php echo $report_type == 'events' ? 'selected' : ''; ?>>Event Performance</option>
                            <option value="tickets" <?php echo $report_type == 'tickets' ? 'selected' : ''; ?>>Ticket Analysis</option>
                            <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Activity</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <button type="button" onclick="printReport()" class="btn btn-secondary" style="margin-top: 10px;">🖨️ Print Report</button>
                    </div>
                </form>
            </div>
            
            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="report-tab <?php echo $report_type == 'revenue' ? 'active' : ''; ?>" onclick="showReport('revenue')">📈 Revenue</button>
                <button class="report-tab <?php echo $report_type == 'events' ? 'active' : ''; ?>" onclick="showReport('events')">🎭 Events</button>
                <button class="report-tab <?php echo $report_type == 'tickets' ? 'active' : ''; ?>" onclick="showReport('tickets')">🎟️ Tickets</button>
                <button class="report-tab <?php echo $report_type == 'users' ? 'active' : ''; ?>" onclick="showReport('users')">👥 Users</button>
            </div>
            
            <!-- Summary Statistics -->
            <?php if ($summary_stats): ?>
                <div class="report-section">
                    <h2 class="section-title">Summary Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($summary_stats['total_revenue'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($summary_stats['total_sales'] ?? 0); ?></div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($summary_stats['total_tickets_sold'] ?? 0); ?></div>
                            <div class="stat-label">Tickets Sold</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($summary_stats['avg_sale_amount'] ?? 0, 2); ?></div>
                            <div class="stat-label">Avg. Sale</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($summary_stats['active_events'] ?? 0); ?></div>
                            <div class="stat-label">Active Events</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($summary_stats['active_users'] ?? 0); ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                    </div>
                    
                    <div class="date-range">
                        <span class="date-range-text">
                            Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Revenue Report -->
            <?php if ($report_type == 'revenue'): ?>
                <div class="report-section">
                    <h2 class="section-title">Daily Revenue Report</h2>
                    
                    <!-- Chart Placeholder -->
                    <div class="chart-container">
                        <div class="chart-placeholder">
                            📊 Revenue Chart Visualization<br>
                            <small>(Would show daily revenue trends with interactive chart library)</small>
                        </div>
                    </div>
                    
                    <?php if (!empty($revenue_report)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sales Count</th>
                                    <th>Tickets Sold</th>
                                    <th>Daily Revenue</th>
                                    <th>Avg. Sale Amount</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $previous_revenue = null;
                                foreach ($revenue_report as $report): 
                                    $trend = '';
                                    if ($previous_revenue !== null) {
                                        if ($report['daily_revenue'] > $previous_revenue) {
                                            $trend = '<span class="metric-badge metric-up">↑ ' . number_format((($report['daily_revenue'] - $previous_revenue) / $previous_revenue) * 100, 1) . '%</span>';
                                        } elseif ($report['daily_revenue'] < $previous_revenue) {
                                            $trend = '<span class="metric-badge metric-down">↓ ' . number_format((($previous_revenue - $report['daily_revenue']) / $previous_revenue) * 100, 1) . '%</span>';
                                        } else {
                                            $trend = '<span class="metric-badge metric-neutral">→ 0%</span>';
                                        }
                                    }
                                    $previous_revenue = $report['daily_revenue'];
                                ?>
                                    <tr>
                                        <td><?php echo date('F d, Y', strtotime($report['sale_day'])); ?></td>
                                        <td><?php echo number_format($report['sales_count']); ?></td>
                                        <td><?php echo number_format($report['tickets_sold']); ?></td>
                                        <td><strong>$<?php echo number_format($report['daily_revenue'], 2); ?></strong></td>
                                        <td>$<?php echo number_format($report['avg_sale_amount'], 2); ?></td>
                                        <td><?php echo $trend; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Export Options -->
                        <div class="export-options">
                            <button onclick="exportRevenueCSV()" class="btn btn-success">📥 Export to CSV</button>
                            <button onclick="exportRevenuePDF()" class="btn btn-secondary">📄 Export to PDF</button>
                        </div>
                        
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 40px;">No revenue data for the selected period.</p>
                    <?php endif; ?>
                </div>
                
            <!-- Event Performance Report -->
            <?php elseif ($report_type == 'events'): ?>
                <div class="report-section">
                    <h2 class="section-title">Event Performance Report</h2>
                    
                    <div class="comparison-grid">
                        <!-- Top Performing Events -->
                        <div>
                            <h3>Top Performing Events</h3>
                            <?php if (!empty($event_performance)): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Revenue</th>
                                            <th>Tickets Sold</th>
                                            <th>Occupancy</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($event_performance, 0, 5) as $event): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                                <td><strong>$<?php echo number_format($event['total_revenue'] ?? 0, 2); ?></strong></td>
                                                <td><?php echo number_format($event['tickets_sold'] ?? 0); ?></td>
                                                <td><?php echo number_format($event['occupancy_rate'] ?? 0, 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 20px;">No event data available.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Category Performance -->
                        <div>
                            <h3>Category Performance</h3>
                            <?php
                            $category_stats = [];
                            foreach ($event_performance as $event) {
                                $category = $event['event_category'] ?? 'Uncategorized';
                                if (!isset($category_stats[$category])) {
                                    $category_stats[$category] = [
                                        'revenue' => 0,
                                        'tickets_sold' => 0,
                                        'event_count' => 0
                                    ];
                                }
                                $category_stats[$category]['revenue'] += $event['total_revenue'] ?? 0;
                                $category_stats[$category]['tickets_sold'] += $event['tickets_sold'] ?? 0;
                                $category_stats[$category]['event_count']++;
                            }
                            ?>
                            <?php if (!empty($category_stats)): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Events</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_stats as $category => $stats): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category); ?></td>
                                                <td><?php echo $stats['event_count']; ?></td>
                                                <td><strong>$<?php echo number_format($stats['revenue'], 2); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 20px;">No category data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Full Event Performance Table -->
                    <?php if (!empty($event_performance)): ?>
                        <h3 style="margin-top: 30px;">Complete Event Performance</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Category</th>
                                    <th>Event Date</th>
                                    <th>Sales Count</th>
                                    <th>Tickets Sold</th>
                                    <th>Revenue</th>
                                    <th>Avg. Sale</th>
                                    <th>Occupancy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($event_performance as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_category']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo number_format($event['sales_count'] ?? 0); ?></td>
                                        <td><?php echo number_format($event['tickets_sold'] ?? 0); ?></td>
                                        <td><strong>$<?php echo number_format($event['total_revenue'] ?? 0, 2); ?></strong></td>
                                        <td>$<?php echo number_format($event['avg_sale_amount'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($event['occupancy_rate'] ?? 0, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Export Options -->
                        <div class="export-options">
                            <button onclick="exportEventCSV()" class="btn btn-success">📥 Export to CSV</button>
                        </div>
                        
                    <?php endif; ?>
                </div>
                
            <!-- Ticket Analysis Report -->
            <?php elseif ($report_type == 'tickets'): ?>
                <div class="report-section">
                    <h2 class="section-title">Ticket Type Analysis</h2>
                    
                    <?php if (!empty($ticket_performance)): ?>
                        <div class="chart-container">
                            <div class="chart-placeholder">
                                📊 Ticket Sales Distribution<br>
                                <small>(Would show pie chart of ticket type distribution)</small>
                            </div>
                        </div>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket Type</th>
                                    <th>Sales Count</th>
                                    <th>Tickets Sold</th>
                                    <th>Total Revenue</th>
                                    <th>Avg. Sale Amount</th>
                                    <th>Avg. Ticket Price</th>
                                    <th>Market Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_tickets_sold = array_sum(array_column($ticket_performance, 'tickets_sold'));
                                $total_revenue = array_sum(array_column($ticket_performance, 'total_revenue'));
                                ?>
                                <?php foreach ($ticket_performance as $ticket): 
                                    $market_share = $total_tickets_sold > 0 ? ($ticket['tickets_sold'] / $total_tickets_sold) * 100 : 0;
                                    $revenue_share = $total_revenue > 0 ? ($ticket['total_revenue'] / $total_revenue) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                        <td><?php echo number_format($ticket['sales_count'] ?? 0); ?></td>
                                        <td><?php echo number_format($ticket['tickets_sold'] ?? 0); ?></td>
                                        <td><strong>$<?php echo number_format($ticket['total_revenue'] ?? 0, 2); ?></strong></td>
                                        <td>$<?php echo number_format($ticket['avg_sale_amount'] ?? 0, 2); ?></td>
                                        <td>$<?php echo number_format($ticket['avg_ticket_price'] ?? 0, 2); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px;">
                                                    <div style="height: 100%; width: <?php echo $market_share; ?>%; background: #0c18fd; border-radius: 4px;"></div>
                                                </div>
                                                <span><?php echo number_format($market_share, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Summary Stats -->
                        <div class="stats-grid" style="margin-top: 30px;">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($total_tickets_sold); ?></div>
                                <div class="stat-label">Total Tickets Sold</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($ticket_performance); ?></div>
                                <div class="stat-label">Ticket Types</div>
                            </div>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="export-options">
                            <button onclick="exportTicketCSV()" class="btn btn-success">📥 Export to CSV</button>
                        </div>
                        
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 40px;">No ticket data available.</p>
                    <?php endif; ?>
                </div>
                
            <!-- User Activity Report -->
            <?php elseif ($report_type == 'users'): ?>
                <div class="report-section">
                    <h2 class="section-title">User Activity Report</h2>
                    
                    <?php if (!empty($user_activity)): ?>
                        <div class="comparison-grid">
                            <!-- Top Spenders -->
                            <div>
                                <h3>Top 10 Spenders</h3>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Total Spent</th>
                                            <th>Purchases</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($user_activity, 0, 10) as $user): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($user['username']); ?><br>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                </td>
                                                <td>
                                                    <span style="padding: 2px 8px; border-radius: 10px; font-size: 0.8em; background: <?php 
                                                        echo $user['role'] == 'admin' ? '#0c18fd' : 
                                                            ($user['role'] == 'organizer' ? '#17a2b8' : '#28a745');
                                                        ?>; color: white;">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><strong>$<?php echo number_format($user['total_spent'] ?? 0, 2); ?></strong></td>
                                                <td><?php echo number_format($user['purchase_count'] ?? 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- User Stats -->
                            <div>
                                <h3>User Statistics</h3>
                                <?php
                                $role_stats = ['user' => 0, 'organizer' => 0, 'admin' => 0];
                                $total_spent = 0;
                                $active_users = 0;
                                
                                foreach ($user_activity as $user) {
                                    if (isset($role_stats[$user['role']])) {
                                        $role_stats[$user['role']]++;
                                    }
                                    $total_spent += $user['total_spent'] ?? 0;
                                    if ($user['purchase_count'] > 0) {
                                        $active_users++;
                                    }
                                }
                                ?>
                                <div class="stats-grid" style="margin-top: 20px;">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo count($user_activity); ?></div>
                                        <div class="stat-label">Total Users</div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo $active_users; ?></div>
                                        <div class="stat-label">Active Users</div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-value">$<?php echo number_format($total_spent, 2); ?></div>
                                        <div class="stat-label">Total Spent</div>
                                    </div>
                                </div>
                                
                                <!-- Role Distribution -->
                                <h4 style="margin-top: 20px;">Role Distribution</h4>
                                <div style="margin-top: 15px;">
                                    <?php foreach ($role_stats as $role => $count): ?>
                                        <div style="margin-bottom: 10px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                                <span><?php echo ucfirst($role); ?></span>
                                                <span><?php echo $count; ?> users</span>
                                            </div>
                                            <div style="height: 8px; background: #e9ecef; border-radius: 4px;">
                                                <div style="height: 100%; width: <?php echo (count($user_activity) > 0) ? ($count / count($user_activity)) * 100 : 0; ?>%; background: <?php 
                                                    echo $role == 'admin' ? '#0c18fd' : 
                                                        ($role == 'organizer' ? '#17a2b8' : '#28a745');
                                                    ?>; border-radius: 4px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Full User Activity Table -->
                        <h3 style="margin-top: 30px;">Complete User Activity</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Purchases</th>
                                    <th>Tickets</th>
                                    <th>Total Spent</th>
                                    <th>Last Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_activity as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span style="padding: 2px 8px; border-radius: 10px; font-size: 0.8em; background: <?php 
                                                echo $user['role'] == 'admin' ? '#0c18fd' : 
                                                    ($user['role'] == 'organizer' ? '#17a2b8' : '#28a745');
                                                ?>; color: white;">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($user['purchase_count'] ?? 0); ?></td>
                                        <td><?php echo number_format($user['tickets_purchased'] ?? 0); ?></td>
                                        <td><strong>$<?php echo number_format($user['total_spent'] ?? 0, 2); ?></strong></td>
                                        <td>
                                            <?php if ($user['last_purchase']): ?>
                                                <?php echo date('M d, Y', strtotime($user['last_purchase'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Never</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Export Options -->
                        <div class="export-options">
                            <button onclick="exportUserCSV()" class="btn btn-success">📥 Export to CSV</button>
                        </div>
                        
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 40px;">No user activity data available.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <script>
        // Show report based on tab click
        function showReport(reportType) {
            const url = new URL(window.location);
            url.searchParams.set('report_type', reportType);
            window.location.href = url.toString();
        }
        
        // Print report
        function printReport() {
            window.print();
        }
        
        // Export functions
        function exportRevenueCSV() {
            alert('Revenue CSV export functionality would be implemented here.');
        }
        
        function exportRevenuePDF() {
            alert('Revenue PDF export functionality would be implemented here.');
        }
        
        function exportEventCSV() {
            alert('Event performance CSV export functionality would be implemented here.');
        }
        
        function exportTicketCSV() {
            alert('Ticket analysis CSV export functionality would be implemented here.');
        }
        
        function exportUserCSV() {
            alert('User activity CSV export functionality would be implemented here.');
        }
        
        // Set default date range to current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            // Set default values if not already set
            const dateFromInput = document.querySelector('input[name="date_from"]');
            const dateToInput = document.querySelector('input[name="date_to"]');
            
            if (dateFromInput && !dateFromInput.value) {
                dateFromInput.value = formatDate(firstDay);
            }
            if (dateToInput && !dateToInput.value) {
                dateToInput.value = formatDate(lastDay);
            }
        });
    </script>
</body>
</html>