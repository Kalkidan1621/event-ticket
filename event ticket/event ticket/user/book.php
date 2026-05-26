<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'] ?? null;
$success_msg = '';
$error_msg = '';

// Get event details if event_id is provided
$event = null;
$tickets = [];

if ($event_id) {
    $event = getEventById($event_id);
    if ($event) {
        $tickets = getEventTickets($event_id);
    }
}

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_tickets'])) {
    $ticket_id = $_POST['ticket_id'];
    $quantity = intval($_POST['quantity']);
    
    // Validate quantity
    if ($quantity < 1) {
        $error_msg = "Quantity must be at least 1";
    } else {
        // Check ticket availability
        $ticket = fetchSingle("SELECT * FROM tickets WHERE ticket_id = ?", [$ticket_id], "i");
        
        if (!$ticket) {
            $error_msg = "Ticket not found";
        } elseif ($ticket['available_quantity'] < $quantity) {
            $error_msg = "Only " . $ticket['available_quantity'] . " tickets available";
        } else {
            // Calculate total amount
            $total_amount = $ticket['price'] * $quantity;
            
            try {
                // Process booking
                $sale_id = bookTickets($user_id, $ticket_id, $quantity, $total_amount);
                
                if ($sale_id) {
                    $success_msg = "Successfully booked " . $quantity . " ticket(s)! Total: $" . number_format($total_amount, 2);
                    // Refresh tickets list
                    $tickets = getEventTickets($event_id);
                }
            } catch (Exception $e) {
                $error_msg = "Booking failed: " . $e->getMessage();
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
    <title>Book Tickets - G5 Event</title>
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
        
        /* Booking Styles */
        .booking-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .event-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .event-title {
            font-size: 2em;
            margin-bottom: 15px;
            color: #333;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #666;
        }
        
        .meta-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #0c18fd;
        }
        
        .tickets-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .ticket-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #f0f0f0;
            transition: all 0.3s;
        }
        
        .ticket-card:hover {
            border-color: #0c18fd;
            transform: translateY(-5px);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .ticket-type {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }
        
        .ticket-price {
            font-size: 1.5em;
            font-weight: bold;
            color: #0c18fd;
        }
        
        .ticket-details {
            color: #666;
            margin-bottom: 20px;
        }
        
        .ticket-availability {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 15px 0;
        }
        
        .availability-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .available {
            background: #d4edda;
            color: #155724;
        }
        
        .low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .sold-out {
            background: #f8d7da;
            color: #721c24;
        }
        
        .booking-form {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .qty-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-input {
            width: 60px;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            font-size: 1.1em;
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
        }
        
        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0c18fd;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
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
                <a href="book.php" class="act">🎟️ Book Tickets</a>
                <a href="ticket.php">💳 Payments</a>
                <a href="payment.php">📁 My Tickets</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <div class="booking-container">
                <?php if ($event_id && !$event): ?>
                    <div class="empty-state">
                        <h2>Event Not Found</h2>
                        <p>The event you're looking for doesn't exist or has been removed.</p>
                        <a href="browse.php" class="btn btn-primary">Browse Events</a>
                    </div>
                <?php elseif (!$event_id): ?>
                    <div class="empty-state">
                        <h2>Select an Event</h2>
                        <p>Please select an event from the browse page to book tickets.</p>
                        <a href="browse.php" class="btn btn-primary">Browse Events</a>
                    </div>
                <?php else: ?>
                    <a href="browse.php" class="back-link">← Back to Events</a>
                    
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
                    
                    <!-- Event Header -->
                    <div class="event-header">
                        <h1 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h1>
                        <p><?php echo htmlspecialchars($event['event_description']); ?></p>
                        
                        <div class="event-meta">
                            <div class="meta-item">
                                <div class="meta-icon">📅</div>
                                <div>
                                    <strong>Date</strong><br>
                                    <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-icon">🕒</div>
                                <div>
                                    <strong>Time</strong><br>
                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-icon">📍</div>
                                <div>
                                    <strong>Location</strong><br>
                                    <?php echo htmlspecialchars($event['event_location']); ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-icon">🏷️</div>
                                <div>
                                    <strong>Category</strong><br>
                                    <?php echo htmlspecialchars($event['event_category']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tickets Section -->
                    <h2>Available Tickets</h2>
                    <div class="tickets-grid">
                        <?php if (!empty($tickets)): ?>
                            <?php foreach ($tickets as $ticket): 
                                $availability_class = '';
                                if ($ticket['available_quantity'] == 0) {
                                    $availability_class = 'sold-out';
                                } elseif ($ticket['available_quantity'] < 10) {
                                    $availability_class = 'low-stock';
                                } else {
                                    $availability_class = 'available';
                                }
                            ?>
                                <div class="ticket-card">
                                    <div class="ticket-header">
                                        <div class="ticket-type"><?php echo htmlspecialchars($ticket['ticket_type']); ?></div>
                                        <div class="ticket-price">$<?php echo number_format($ticket['price'], 2); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($ticket['description'])): ?>
                                        <div class="ticket-details">
                                            <?php echo htmlspecialchars($ticket['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($ticket['seat_layout'])): ?>
                                        <div class="ticket-details">
                                            <strong>Seat Layout:</strong><br>
                                            <?php echo htmlspecialchars($ticket['seat_layout']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ticket-availability">
                                        <div>
                                            <strong>Available:</strong> <?php echo $ticket['available_quantity']; ?> / <?php echo $ticket['total_quantity']; ?>
                                        </div>
                                        <span class="availability-badge <?php echo $availability_class; ?>">
                                            <?php 
                                            if ($ticket['available_quantity'] == 0) {
                                                echo 'Sold Out';
                                            } elseif ($ticket['available_quantity'] < 10) {
                                                echo 'Low Stock';
                                            } else {
                                                echo 'Available';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($ticket['available_quantity'] > 0): ?>
                                        <form method="POST" class="booking-form">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                            
                                            <div class="quantity-selector">
                                                <button type="button" class="qty-btn" onclick="decrementQuantity(<?php echo $ticket['ticket_id']; ?>)">-</button>
                                                <input type="number" 
                                                       name="quantity" 
                                                       id="quantity_<?php echo $ticket['ticket_id']; ?>" 
                                                       class="qty-input" 
                                                       value="1" 
                                                       min="1" 
                                                       max="<?php echo min($ticket['available_quantity'], 10); ?>"
                                                       onchange="updateTotal(<?php echo $ticket['ticket_id']; ?>, <?php echo $ticket['price']; ?>)">
                                                <button type="button" class="qty-btn" onclick="incrementQuantity(<?php echo $ticket['ticket_id']; ?>, <?php echo min($ticket['available_quantity'], 10); ?>)">+</button>
                                            </div>
                                            
                                            <div id="total_<?php echo $ticket['ticket_id']; ?>" style="font-weight: bold;">
                                                Total: $<?php echo number_format($ticket['price'], 2); ?>
                                            </div>
                                            
                                            <button type="submit" name="book_tickets" class="btn btn-primary">
                                                Book Now
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-disabled" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No tickets available</h3>
                                <p>All tickets for this event have been sold out.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <script>
        function decrementQuantity(ticketId) {
            const input = document.getElementById('quantity_' + ticketId);
            if (parseInt(input.value) > parseInt(input.min)) {
                input.value = parseInt(input.value) - 1;
                updateTotal(ticketId, parseFloat(input.dataset.price));
            }
        }
        
        function incrementQuantity(ticketId, max) {
            const input = document.getElementById('quantity_' + ticketId);
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
                updateTotal(ticketId, parseFloat(input.dataset.price));
            }
        }
        
        function updateTotal(ticketId, unitPrice) {
            const input = document.getElementById('quantity_' + ticketId);
            const quantity = parseInt(input.value) || 0;
            const total = unitPrice * quantity;
            document.getElementById('total_' + ticketId).textContent = 'Total: $' + total.toFixed(2);
        }
        
        // Initialize data-price attribute on inputs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.qty-input').forEach(input => {
                const ticketId = input.id.replace('quantity_', '');
                const priceElement = input.closest('.ticket-card').querySelector('.ticket-price');
                const price = parseFloat(priceElement.textContent.replace('$', ''));
                input.dataset.price = price;
            });
        });
    </script>
</body>
</html>