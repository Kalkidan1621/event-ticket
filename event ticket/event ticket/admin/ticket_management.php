<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$ticket_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_ticket'])) {
        $ticket_id = $_POST['ticket_id'];
        $ticket_type = trim($_POST['ticket_type']);
        $price = $_POST['price'];
        $total_quantity = $_POST['total_quantity'];
        $seat_layout = trim($_POST['seat_layout'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Calculate available quantity
        $current = fetchSingle("SELECT sold_quantity FROM tickets WHERE ticket_id = ?", [$ticket_id], "i");
        $available_quantity = $total_quantity - ($current['sold_quantity'] ?? 0);
        
        if ($available_quantity < 0) {
            $error = "Total quantity cannot be less than sold quantity!";
        } else {
            $sql = "UPDATE tickets SET 
                    ticket_type = ?, 
                    price = ?, 
                    total_quantity = ?, 
                    available_quantity = ?, 
                    seat_layout = ?, 
                    description = ? 
                    WHERE ticket_id = ?";
            
            $stmt = executeQuery($sql, [
                $ticket_type, $price, $total_quantity, $available_quantity, 
                $seat_layout, $description, $ticket_id
            ], "sddisssi");
            
            $message = "Ticket updated successfully!";
        }
    }
    
    if (isset($_POST['delete_ticket'])) {
        $ticket_id = $_POST['ticket_id'];
        
        // Check if ticket has sales
        $sales = fetchSingle("SELECT COUNT(*) as count FROM ticket_sales WHERE ticket_id = ?", [$ticket_id], "i");
        
        if ($sales['count'] > 0) {
            $error = "Cannot delete ticket with existing sales!";
        } else {
            $sql = "DELETE FROM tickets WHERE ticket_id = ?";
            $stmt = executeQuery($sql, [$ticket_id], "i");
            $message = "Ticket deleted successfully!";
        }
    }
}

// Get all tickets with event info
$tickets = fetchAll("
    SELECT t.*, e.event_name, e.event_date, e.event_location, u.username as organizer_name
    FROM tickets t
    JOIN events e ON t.event_id = e.id
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.event_date DESC, t.ticket_type
");

// Get ticket for editing/viewing
$current_ticket = null;
if ($ticket_id) {
    $current_ticket = fetchSingle("
        SELECT t.*, e.event_name, e.event_date, e.event_location, u.username as organizer_name
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON e.organizer_id = u.id
        WHERE t.ticket_id = ?
    ", [$ticket_id], "i");
    
    if ($current_ticket) {
        // Get ticket sales
        $ticket_sales = fetchAll("
            SELECT 
                ts.sale_id,
                ts.quantity,
                ts.total_amount,
                ts.sale_date,
                ts.payment_status,
                u.username,
                u.email
            FROM ticket_sales ts
            JOIN users u ON ts.user_id = u.id
            WHERE ts.ticket_id = ?
            ORDER BY ts.sale_date DESC
        ", [$ticket_id], "i");
    }
}

// Get statistics
$ticket_stats = fetchAll("
    SELECT 
        COUNT(*) as total_tickets,
        SUM(total_quantity) as total_capacity,
        SUM(sold_quantity) as total_sold,
        SUM(available_quantity) as total_available,
        AVG(price) as avg_price
    FROM tickets
");

$popular_tickets = fetchAll("
    SELECT 
        t.ticket_type,
        e.event_name,
        COUNT(ts.sale_id) as sales_count,
        SUM(ts.quantity) as tickets_sold,
        SUM(ts.total_amount) as revenue
    FROM tickets t
    JOIN events e ON t.event_id = e.id
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    GROUP BY t.ticket_id
    ORDER BY tickets_sold DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Management</title>
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
        
        /* Ticket Management Styles */
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
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
        
        .ticket-detail-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .ticket-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .ticket-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .ticket-meta {
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
        
        .availability-bar {
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .availability-fill {
            height: 100%;
            background: #28a745;
            border-radius: 5px;
        }
        
        .availability-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            color: #666;
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
        
        .text-danger {
            color: #dc3545;
            font-weight: bold;
        }
        
        .text-success {
            color: #28a745;
            font-weight: bold;
        }
        
        .text-warning {
            color: #ffc107;
            font-weight: bold;
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
                <a href="ticket_management.php" class="act">🎟️ Ticket Management</a>
                <a href="payment_manage.php">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Ticket Management</h1>
            <hr>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Ticket Statistics -->
            <?php if ($ticket_stats && !$ticket_id): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $ticket_stats[0]['total_tickets'] ?? 0; ?></div>
                        <div class="stat-label">Total Ticket Types</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $ticket_stats[0]['total_capacity'] ?? 0; ?></div>
                        <div class="stat-label">Total Capacity</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $ticket_stats[0]['total_sold'] ?? 0; ?></div>
                        <div class="stat-label">Tickets Sold</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($ticket_stats[0]['avg_price'] ?? 0, 2); ?></div>
                        <div class="stat-label">Average Price</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($ticket_id && $current_ticket && $action == 'edit'): ?>
                <!-- Edit Ticket Form -->
                <div class="form-container">
                    <h2>Edit Ticket: <?php echo htmlspecialchars($current_ticket['ticket_type']); ?></h2>
                    
                    <form method="POST">
                        <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['ticket_id']; ?>">
                        <input type="hidden" name="update_ticket" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Event</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_ticket['event_name']); ?>" 
                                   disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ticket Type *</label>
                            <input type="text" name="ticket_type" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_ticket['ticket_type']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" step="0.01" name="price" class="form-control" 
                                       value="<?php echo $current_ticket['price']; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Total Quantity *</label>
                                <input type="number" name="total_quantity" class="form-control" 
                                       value="<?php echo $current_ticket['total_quantity']; ?>" 
                                       required>
                                <small style="color: #666;">Currently sold: <?php echo $current_ticket['sold_quantity']; ?></small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Seat Layout</label>
                            <textarea name="seat_layout" class="form-control" rows="3"><?php echo htmlspecialchars($current_ticket['seat_layout'] ?? ''); ?></textarea>
                            <small style="color: #666;">Describe seating arrangement (e.g., "VIP: Rows 1-3")</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($current_ticket['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Ticket</button>
                        <a href="ticket_management.php?action=view&id=<?php echo $ticket_id; ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                
            <?php elseif ($ticket_id && $current_ticket && $action == 'view'): ?>
                <!-- Ticket Details View -->
                <div class="ticket-detail-section">
                    <div class="ticket-header">
                        <h2><?php echo htmlspecialchars($current_ticket['ticket_type']); ?> Ticket</h2>
                        <p>Ticket ID: #<?php echo $current_ticket['ticket_id']; ?></p>
                        
                        <div style="margin-top: 20px;">
                            <a href="ticket_management.php" class="btn btn-secondary">Back to Tickets</a>
                            <a href="ticket_management.php?action=edit&id=<?php echo $ticket_id; ?>" class="btn btn-primary">Edit Ticket</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ticket? This action cannot be undone.');">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                <input type="hidden" name="delete_ticket" value="1">
                                <button type="submit" class="btn btn-danger">Delete Ticket</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Ticket Information -->
                    <div class="ticket-meta">
                        <div class="meta-item">
                            <div class="meta-label">Event</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_ticket['event_name']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Organizer</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_ticket['organizer_name']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Event Date</div>
                            <div class="meta-value"><?php echo date('F d, Y', strtotime($current_ticket['event_date'])); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Event Location</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_ticket['event_location']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Price</div>
                            <div class="meta-value"><strong>$<?php echo number_format($current_ticket['price'], 2); ?></strong></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Created</div>
                            <div class="meta-value"><?php echo date('M d, Y', strtotime($current_ticket['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Ticket Availability -->
                    <div style="margin: 30px 0;">
                        <h3>Availability</h3>
                        <div class="availability-bar">
                            <?php 
                            $percentage = ($current_ticket['sold_quantity'] / $current_ticket['total_quantity']) * 100;
                            $availability_class = '';
                            if ($current_ticket['available_quantity'] == 0) {
                                $availability_class = 'text-danger';
                            } elseif ($current_ticket['available_quantity'] < 10) {
                                $availability_class = 'text-warning';
                            } else {
                                $availability_class = 'text-success';
                            }
                            ?>
                            <div class="availability-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                        </div>
                        <div class="availability-text">
                            <span>Available: <span class="<?php echo $availability_class; ?>"><?php echo $current_ticket['available_quantity']; ?></span></span>
                            <span>Sold: <?php echo $current_ticket['sold_quantity']; ?></span>
                            <span>Total: <?php echo $current_ticket['total_quantity']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Ticket Description -->
                    <?php if ($current_ticket['description']): ?>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <?php echo nl2br(htmlspecialchars($current_ticket['description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Seat Layout -->
                    <?php if ($current_ticket['seat_layout']): ?>
                        <div class="form-group">
                            <label class="form-label">Seat Layout</label>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;">
                                <?php echo nl2br(htmlspecialchars($current_ticket['seat_layout'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ticket Sales -->
                    <?php if (!empty($ticket_sales)): ?>
                        <h3 style="margin-top: 30px;">Ticket Sales</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>User</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ticket_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['sale_id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['username']); ?><br>
                                            <small><?php echo htmlspecialchars($sale['email']); ?></small>
                                        </td>
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
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No sales for this ticket yet.</p>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Tickets List -->
                <div class="header-actions">
                    <div>
                        <h2>All Tickets (<?php echo count($tickets); ?>)</h2>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Event</th>
                            <th>Ticket Type</th>
                            <th>Price</th>
                            <th>Availability</th>
                            <th>Organizer</th>
                            <th>Event Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tickets)): ?>
                            <?php foreach ($tickets as $ticket): 
                                $percentage = ($ticket['sold_quantity'] / $ticket['total_quantity']) * 100;
                                $availability_class = '';
                                if ($ticket['available_quantity'] == 0) {
                                    $availability_class = 'text-danger';
                                } elseif ($ticket['available_quantity'] < 10) {
                                    $availability_class = 'text-warning';
                                } else {
                                    $availability_class = 'text-success';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $ticket['ticket_id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                    <td>$<?php echo number_format($ticket['price'], 2); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="flex: 1;">
                                                <div style="height: 6px; background: #e9ecef; border-radius: 3px;">
                                                    <div style="height: 100%; width: <?php echo min($percentage, 100); ?>%; background: #28a745; border-radius: 3px;"></div>
                                                </div>
                                            </div>
                                            <span class="<?php echo $availability_class; ?>">
                                                <?php echo $ticket['available_quantity']; ?>/<?php echo $ticket['total_quantity']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['organizer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($ticket['event_date'])); ?></td>
                                    <td>
                                        <a href="ticket_management.php?action=view&id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-primary">View</a>
                                        <a href="ticket_management.php?action=edit&id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-success">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ticket? This action cannot be undone.');">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                            <input type="hidden" name="delete_ticket" value="1">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #666;">No tickets found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Popular Tickets -->
                <?php if (!empty($popular_tickets)): ?>
                    <h2 style="margin-top: 40px;">Popular Tickets</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Ticket Type</th>
                                <th>Event</th>
                                <th>Tickets Sold</th>
                                <th>Revenue</th>
                                <th>Sales Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popular_tickets as $index => $ticket): ?>
                                <tr>
                                    <td>#<?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['event_name']); ?></td>
                                    <td><?php echo $ticket['tickets_sold'] ?? 0; ?></td>
                                    <td><strong>$<?php echo number_format($ticket['revenue'] ?? 0, 2); ?></strong></td>
                                    <td><?php echo $ticket['sales_count'] ?? 0; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>