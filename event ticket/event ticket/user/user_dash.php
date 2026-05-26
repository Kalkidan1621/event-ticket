<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user stats
$stats = fetchSingle("
    SELECT 
        (SELECT COUNT(*) FROM ticket_sales WHERE user_id = ? AND payment_status = 'completed') as total_bookings,
        (SELECT SUM(total_amount) FROM ticket_sales WHERE user_id = ? AND payment_status = 'completed') as total_spent,
        (SELECT COUNT(*) FROM ticket_sales WHERE user_id = ? AND payment_status = 'pending') as pending_payments
", [$user_id, $user_id, $user_id], "iii");

// Get upcoming events
$upcoming_events = fetchAll("
    SELECT e.*, 
           (SELECT MIN(price) FROM tickets WHERE event_id = e.id) as min_price
    FROM events e
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 3
");

// Get recent bookings
$recent_bookings = getUserBookings($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - G5 Event</title>
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
        
        /* Dashboard Styles */
        .welcome-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .events-grid, .bookings-list {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .event-card, .booking-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .event-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        
        .event-price {
            background: #0c18fd;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .event-details {
            color: #666;
            margin: 10px 0;
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        
        .btn-primary {
            background: #0c18fd;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .booking-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
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
                <a href="user_dash.php" class="act">🛠️ Dashboard</a>
                <a href="browse.php">🔍 Browse Events</a>
                <a href="book.php">🎟️ Book Tickets</a>
                <a href="ticket.php">💳 Payments</a>
                <a href="payment.php">📁 My Tickets</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>!</h1>
                <p>Here's your event booking dashboard</p>
            </div>
            
            <!-- Statistics -->
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
                    <div class="stat-value"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <h2>Upcoming Events</h2>
            <div class="events-grid">
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                <?php if ($event['min_price']): ?>
                                    <div class="event-price">From $<?php echo number_format($event['min_price'], 2); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="event-details">
                                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($event['event_date'])); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($event['event_location']); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($event['event_category']); ?></p>
                            </div>
                            <a href="book.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">Book Now</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No upcoming events found.</p>
                        <a href="browse.php" class="btn btn-primary">Browse Events</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Bookings -->
            <h2>Recent Bookings</h2>
            <div class="bookings-list">
                <?php if (!empty($recent_bookings)): ?>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="event-header">
                                <div class="event-title">
                                    <?php echo htmlspecialchars($booking['event_name']); ?>
                                    <span class="booking-status status-<?php echo $booking['payment_status']; ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </div>
                                <div class="event-price">$<?php echo number_format($booking['total_amount'], 2); ?></div>
                            </div>
                            <div class="event-details">
                                <p><strong>Ticket Type:</strong> <?php echo htmlspecialchars($booking['ticket_type']); ?></p>
                                <p><strong>Quantity:</strong> <?php echo $booking['quantity']; ?></p>
                                <p><strong>Booking Date:</strong> <?php echo date('M d, Y H:i', strtotime($booking['sale_date'])); ?></p>
                                <p><strong>Event Date:</strong> <?php echo date('F d, Y', strtotime($booking['event_date'])); ?> at <?php echo $booking['event_time']; ?></p>
                            </div>
                            <?php if ($booking['payment_status'] === 'pending'): ?>
                                <a href="ticket.php?pay=<?php echo $booking['sale_id']; ?>" class="btn btn-primary">Complete Payment</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You haven't made any bookings yet.</p>
                        <a href="browse.php" class="btn btn-primary">Browse Events</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>