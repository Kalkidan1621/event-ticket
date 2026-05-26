<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$event_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_event'])) {
        $event_id = $_POST['event_id'];
        $event_name = trim($_POST['event_name']);
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'];
        $event_location = trim($_POST['event_location']);
        $event_category = $_POST['event_category'];
        $ticket_price = $_POST['ticket_price'];
        $total_tickets = $_POST['total_tickets'];
        $event_description = trim($_POST['event_description']);
        
        $sql = "UPDATE events SET 
                event_name = ?, 
                event_date = ?, 
                event_time = ?, 
                event_location = ?, 
                event_category = ?, 
                ticket_price = ?, 
                total_tickets = ?, 
                event_description = ? 
                WHERE id = ?";
        
        $stmt = executeQuery($sql, [
            $event_name, $event_date, $event_time, $event_location, 
            $event_category, $ticket_price, $total_tickets, $event_description, $event_id
        ], "sssssdiss");
        
        $message = "Event updated successfully!";
    }
    
    if (isset($_POST['delete_event'])) {
        $event_id = $_POST['event_id'];
        
        // Check if event has tickets
        $tickets = fetchSingle("SELECT COUNT(*) as count FROM tickets WHERE event_id = ?", [$event_id], "i");
        
        if ($tickets['count'] > 0) {
            $error = "Cannot delete event with existing tickets!";
        } else {
            $sql = "DELETE FROM events WHERE id = ?";
            $stmt = executeQuery($sql, [$event_id], "i");
            $message = "Event deleted successfully!";
        }
    }
}

// Get all events with organizer info
$events = fetchAll("
    SELECT e.*, u.username as organizer_name, u.email as organizer_email
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.event_date DESC
");

// Get event for editing/viewing
$current_event = null;
if ($event_id) {
    $current_event = fetchSingle("
        SELECT e.*, u.username as organizer_name, u.email as organizer_email
        FROM events e
        JOIN users u ON e.organizer_id = u.id
        WHERE e.id = ?
    ", [$event_id], "i");
    
    if ($current_event) {
        // Get event statistics
        $event_stats = fetchSingle("
            SELECT 
                COUNT(DISTINCT ts.sale_id) as total_sales,
                SUM(ts.quantity) as tickets_sold,
                SUM(ts.total_amount) as total_revenue,
                AVG(ts.total_amount) as avg_sale_amount
            FROM ticket_sales ts
            JOIN tickets t ON ts.ticket_id = t.ticket_id
            WHERE t.event_id = ? AND ts.payment_status = 'completed'
        ", [$event_id], "i");
        
        // Get ticket types for this event
        $ticket_types = fetchAll("
            SELECT * FROM tickets WHERE event_id = ? ORDER BY ticket_type
        ", [$event_id], "i");
        
        // Get recent sales
        $recent_sales = fetchAll("
            SELECT 
                ts.sale_id,
                ts.quantity,
                ts.total_amount,
                ts.sale_date,
                ts.payment_status,
                t.ticket_type,
                u.username
            FROM ticket_sales ts
            JOIN tickets t ON ts.ticket_id = t.ticket_id
            JOIN users u ON ts.user_id = u.id
            WHERE t.event_id = ?
            ORDER BY ts.sale_date DESC
            LIMIT 10
        ", [$event_id], "i");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management</title>
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
        
        /* Event Management Styles */
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
        
        .event-detail-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .event-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .event-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .meta-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .meta-value {
            color: #666;
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
        
        .ticket-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .ticket-type-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .ticket-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .ticket-type-name {
            font-weight: bold;
            color: #333;
        }
        
        .ticket-price {
            color: #0c18fd;
            font-weight: bold;
        }
        
        .availability-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .availability-fill {
            height: 100%;
            background: #28a745;
            border-radius: 4px;
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
                <a href="event_management.php" class="act">🎭 Event Management</a>
                <a href="ticket_management.php">🎟️ Ticket Management</a>
                <a href="payment_manage.php">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Event Management</h1>
            <hr>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($event_id && $current_event && $action == 'edit'): ?>
                <!-- Edit Event Form -->
                <div class="form-container">
                    <h2>Edit Event: <?php echo htmlspecialchars($current_event['event_name']); ?></h2>
                    
                    <form method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $current_event['id']; ?>">
                        <input type="hidden" name="update_event" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Event Name *</label>
                            <input type="text" name="event_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_event['event_name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Event Date *</label>
                                <input type="date" name="event_date" class="form-control" 
                                       value="<?php echo $current_event['event_date']; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Event Time *</label>
                                <input type="time" name="event_time" class="form-control" 
                                       value="<?php echo $current_event['event_time']; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Location *</label>
                            <input type="text" name="event_location" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_event['event_location']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="event_category" class="form-control" required>
                                <option value="Music" <?php echo $current_event['event_category'] == 'Music' ? 'selected' : ''; ?>>Music</option>
                                <option value="Sport" <?php echo $current_event['event_category'] == 'Sport' ? 'selected' : ''; ?>>Sport</option>
                                <option value="Conference" <?php echo $current_event['event_category'] == 'Conference' ? 'selected' : ''; ?>>Conference</option>
                                <option value="Workshop" <?php echo $current_event['event_category'] == 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Festival" <?php echo $current_event['event_category'] == 'Festival' ? 'selected' : ''; ?>>Festival</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Ticket Price ($) *</label>
                                <input type="number" step="0.01" name="ticket_price" class="form-control" 
                                       value="<?php echo $current_event['ticket_price']; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Total Tickets *</label>
                                <input type="number" name="total_tickets" class="form-control" 
                                       value="<?php echo $current_event['total_tickets']; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="event_description" class="form-control" rows="4"><?php echo htmlspecialchars($current_event['event_description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Organizer</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_event['organizer_name'] . ' (' . $current_event['organizer_email'] . ')'); ?>" 
                                   disabled>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Event</button>
                        <a href="event_management.php?action=view&id=<?php echo $event_id; ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                
            <?php elseif ($event_id && $current_event && $action == 'view'): ?>
                <!-- Event Details View -->
                <div class="event-detail-section">
                    <div class="event-header">
                        <h2><?php echo htmlspecialchars($current_event['event_name']); ?></h2>
                        <p>Event ID: #<?php echo $current_event['id']; ?></p>
                        
                        <div style="margin-top: 20px;">
                            <a href="event_management.php" class="btn btn-secondary">Back to Events</a>
                            <a href="event_management.php?action=edit&id=<?php echo $event_id; ?>" class="btn btn-primary">Edit Event</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
                                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                <input type="hidden" name="delete_event" value="1">
                                <button type="submit" class="btn btn-danger">Delete Event</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Event Information -->
                    <div class="event-meta">
                        <div class="meta-item">
                            <div class="meta-label">Date & Time</div>
                            <div class="meta-value">
                                <?php echo date('F d, Y', strtotime($current_event['event_date'])); ?> 
                                at <?php echo date('g:i A', strtotime($current_event['event_time'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Location</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_event['event_location']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Category</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_event['event_category']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Organizer</div>
                            <div class="meta-value">
                                <?php echo htmlspecialchars($current_event['organizer_name']); ?><br>
                                <?php echo htmlspecialchars($current_event['organizer_email']); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Ticket Price</div>
                            <div class="meta-value">$<?php echo number_format($current_event['ticket_price'], 2); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Total Tickets</div>
                            <div class="meta-value"><?php echo number_format($current_event['total_tickets']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Created</div>
                            <div class="meta-value"><?php echo date('M d, Y', strtotime($current_event['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Event Description -->
                    <?php if ($current_event['event_description']): ?>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <?php echo nl2br(htmlspecialchars($current_event['event_description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Event Statistics -->
                    <?php if ($event_stats): ?>
                        <h3 style="margin-top: 30px;">Event Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $event_stats['total_sales'] ?? 0; ?></div>
                                <div class="stat-label">Total Sales</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $event_stats['tickets_sold'] ?? 0; ?></div>
                                <div class="stat-label">Tickets Sold</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($event_stats['total_revenue'] ?? 0, 2); ?></div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($event_stats['avg_sale_amount'] ?? 0, 2); ?></div>
                                <div class="stat-label">Average Sale</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ticket Types -->
                    <?php if (!empty($ticket_types)): ?>
                        <h3 style="margin-top: 30px;">Ticket Types</h3>
                        <div class="ticket-type-grid">
                            <?php foreach ($ticket_types as $ticket): 
                                $percentage = ($ticket['sold_quantity'] / $ticket['total_quantity']) * 100;
                            ?>
                                <div class="ticket-type-card">
                                    <div class="ticket-type-header">
                                        <div class="ticket-type-name"><?php echo htmlspecialchars($ticket['ticket_type']); ?></div>
                                        <div class="ticket-price">$<?php echo number_format($ticket['price'], 2); ?></div>
                                    </div>
                                    
                                    <div>
                                        <div>Available: <?php echo $ticket['available_quantity']; ?> / <?php echo $ticket['total_quantity']; ?></div>
                                        <div>Sold: <?php echo $ticket['sold_quantity']; ?></div>
                                        
                                        <div class="availability-bar">
                                            <div class="availability-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        </div>
                                        
                                        <?php if (!empty($ticket['description'])): ?>
                                            <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                                <?php echo htmlspecialchars($ticket['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Recent Sales -->
                    <?php if (!empty($recent_sales)): ?>
                        <h3 style="margin-top: 30px;">Recent Sales</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>User</th>
                                    <th>Ticket Type</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['sale_id']; ?></td>
                                        <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['ticket_type']); ?></td>
                                        <td><?php echo $sale['quantity']; ?></td>
                                        <td><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                        <td>
                                            <span class="badge" style="background: <?php 
                                                echo $sale['payment_status'] == 'completed' ? '#d4edda' : 
                                                    ($sale['payment_status'] == 'pending' ? '#fff3cd' : '#f8d7da');
                                                ?>; color: <?php 
                                                echo $sale['payment_status'] == 'completed' ? '#155724' : 
                                                    ($sale['payment_status'] == 'pending' ? '#856404' : '#721c24');
                                                ?>;">
                                                <?php echo ucfirst($sale['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Events List -->
                <div class="header-actions">
                    <div>
                        <h2>All Events (<?php echo count($events); ?>)</h2>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event Name</th>
                            <th>Organizer</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Tickets</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($events)): ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>#<?php echo $event['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['event_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo substr(htmlspecialchars($event['event_description'] ?? ''), 0, 50); ?>...</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_location']); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_category']); ?></td>
                                    <td>$<?php echo number_format($event['ticket_price'], 2); ?></td>
                                    <td><?php echo number_format($event['total_tickets']); ?></td>
                                    <td>
                                        <a href="event_management.php?action=view&id=<?php echo $event['id']; ?>" class="btn btn-primary">View</a>
                                        <a href="event_management.php?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-success">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <input type="hidden" name="delete_event" value="1">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #666;">No events found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>