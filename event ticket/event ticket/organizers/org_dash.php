<?php
require_once '../config.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header('Location: ../login.php');
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Get total events count - FIXED: using 'id' instead of 'event_id'
$total_events_result = fetchSingle(
    "SELECT COUNT(*) as total_events FROM events WHERE organizer_id = ?",
    [$organizer_id],
    "i"
);
$total_events = $total_events_result['total_events'] ?? 0;

// Get total ticket sales and revenue - FIXED: using correct column names
$sales_data = fetchSingle("
    SELECT 
        COALESCE(SUM(ts.quantity), 0) as total_tickets_sold,
        COALESCE(SUM(ts.total_amount), 0) as total_revenue
    FROM ticket_sales ts
    JOIN tickets t ON ts.ticket_id = t.ticket_id
    JOIN events e ON t.event_id = e.id
    WHERE e.organizer_id = ? AND ts.payment_status = 'completed'
", [$organizer_id], "i");

// Get recent events - FIXED: using correct column names
$recent_events = fetchAll("
    SELECT * FROM events 
    WHERE organizer_id = ? 
    ORDER BY event_date DESC 
    LIMIT 5
", [$organizer_id], "i");

// Get ticket sales by type - FIXED: using correct column names
$ticket_stats = fetchAll("
    SELECT 
        t.ticket_type,
        COALESCE(SUM(ts.quantity), 0) as sold_count,
        COALESCE(SUM(ts.total_amount), 0) as revenue
    FROM tickets t
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    JOIN events e ON t.event_id = e.id
    WHERE e.organizer_id = ?
    GROUP BY t.ticket_type
", [$organizer_id], "i");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard</title>
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        
        .stats-grid {
            display: flex;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .revenue {
            color: #27ae60;
        }
        
        .tickets {
            color: #3498db;
        }
        
        .events {
            color: #9b59b6;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .event-list {
            list-style: none;
            padding: 0;
        }
        
        .event-item {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-title {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .event-date {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .ticket-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .ticket-type-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .ticket-type {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .ticket-count {
            font-size: 1.5em;
            color: #27ae60;
            margin-bottom: 5px;
        }
        
        .ticket-revenue {
            color: #7f8c8d;
            font-size: 0.9em;
        }
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
            width: 20%;
            padding: 20px;
            background: #fff;
            min-height: 100vh;
        }
        section{
            width: 83%;
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
        }
        .roles a:hover{
            background: rgba(12, 24, 253, 0.1);
        }
        .profile{
            text-align: center;
            margin-bottom: 20px;
        }
        .profile h3{
            color: #0c18fd;
            margin: 10px 0;
        }
        .img{
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: auto;
            overflow: hidden;
        }
        .img img{
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ro{
            background: #0c18fd;
            color: #fff;
            padding: 5px;
            border-radius: 20px;
            margin: 10px 30px;
        }
        .act{
            background: #0c18fd !important;
            color: #fff !important;
        }
        h1 {
            margin-bottom: 20px;
        }
        hr {
            margin: 10px 0 20px 0;
            border: none;
            border-top: 2px solid #0c18fd;
        }
    </style>
</head>
<body>
    <main>
     <aside>
        <div class="profile">
               <h3>Grand Event</h3>
               <div class="img"><img src="../img/pro.jpg" alt="Profile"></div>
               <div class="ro">ORGANIZER</div>
        </div>
                <div class="roles">
                <a href="org_dash.php" class="act">📊  Dashboard</a>
                <a href="eve_ma.php">🎭 Event Management</a>
                <a href="tick.php">🎟️ Ticket & Seat Management</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
       </div>
    </aside>
   
     
    <div class="dashboard-container">
        <div>
        <h1>Organizer Dashboard</h1>
        <hr>
    </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Events</div>
                <div class="stat-value events"><?php echo $total_events; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Tickets Sold</div>
                <div class="stat-value tickets"><?php echo $sales_data['total_tickets_sold'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value revenue">$<?php echo number_format($sales_data['total_revenue'] ?? 0, 2); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Quick Actions</div>
                <div style="margin-top: 15px;">
                    <a href="tick.php" class="btn-primary">Manage Tickets</a>
                    <a href="eve_ma.php" class="btn-success" style="margin-left: 10px;">Create Event</a>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2 class="section-title">Recent Events</h2>
            <?php if (count($recent_events) > 0): ?>
                <ul class="event-list">
                    <?php foreach ($recent_events as $event): ?>
                        <li class="event-item">
                            <div>
                                <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                <div class="event-date"><?php echo date('F d, Y', strtotime($event['event_date'])); ?></div>
                            </div>
                            <a href="eve_ma.php?edit=<?php echo $event['id']; ?>" class="btn-primary">View Details</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No events created yet. <a href="eve_ma.php">Create your first event</a></p>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-section">
            <h2 class="section-title">Ticket Sales by Type</h2>
            <?php if (count($ticket_stats) > 0): ?>
                <div class="ticket-type-grid">
                    <?php foreach ($ticket_stats as $stat): ?>
                        <div class="ticket-type-card">
                            <div class="ticket-type"><?php echo htmlspecialchars($stat['ticket_type']); ?></div>
                            <div class="ticket-count"><?php echo $stat['sold_count'] ?? 0; ?> sold</div>
                            <div class="ticket-revenue">$<?php echo number_format($stat['revenue'] ?? 0, 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No ticket sales data available.</p>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-section">
            <h2 class="section-title">Quick Links</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="tick.php?action=create" class="btn-primary">Create Ticket Types</a>
                <a href="eve_ma.php" class="btn-primary">Manage Events</a>
                <a href="../logout.php" class="btn-primary">Logout</a>
            </div>
        </div>
    </div>
    </main>
</body>
</html>