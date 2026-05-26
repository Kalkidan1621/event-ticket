<?php
require_once '../config.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header('Location: ../login.php');
    exit();
}

$organizer_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'view';
$event_id = $_GET['event_id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_ticket'])) {
        // Create new ticket type
        $event_id = $_POST['event_id'];
        $ticket_type = $_POST['ticket_type'];
        $price = $_POST['price'];
        $total_quantity = $_POST['total_quantity'];
        $seat_layout = $_POST['seat_layout'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $sql = "INSERT INTO tickets (event_id, ticket_type, price, total_quantity, available_quantity, seat_layout, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = executeQuery($sql, [$event_id, $ticket_type, $price, $total_quantity, $total_quantity, $seat_layout, $description], "issdiss");
        
        $_SESSION['success_message'] = "Ticket type created successfully!";
        header("Location: tick.php?event_id=$event_id");
        exit();
        
    } elseif (isset($_POST['update_ticket'])) {
        // Update ticket price/quantity
        $ticket_id = $_POST['ticket_id'];
        $price = $_POST['price'];
        $total_quantity = $_POST['total_quantity'];
        
        // Get current sold count
        $current = fetchSingle("SELECT sold_quantity FROM tickets WHERE ticket_id = ?", [$ticket_id], "i");
        
        $available_quantity = $total_quantity - ($current['sold_quantity'] ?? 0);
        
        $sql = "UPDATE tickets SET price = ?, total_quantity = ?, available_quantity = ? WHERE ticket_id = ?";
        $stmt = executeQuery($sql, [$price, $total_quantity, $available_quantity, $ticket_id], "diii");
        
        $_SESSION['success_message'] = "Ticket updated successfully!";
        header("Location: tick.php");
        exit();
    }
}

// Get organizer's events for dropdown
$events = fetchAll("SELECT id as event_id, event_name FROM events WHERE organizer_id = ? ORDER BY event_date DESC", [$organizer_id], "i");
// If event is selected, get its tickets
$tickets = [];
if ($event_id) {
    $tickets = fetchAll("
        SELECT t.*, e.event_name 
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE t.event_id = ? AND e.organizer_id = ?
        ORDER BY t.ticket_type
    ", [$event_id, $organizer_id], "ii");
}

// Get all tickets for monitoring
$all_tickets = fetchAll("
    SELECT t.*, e.event_name, e.event_date 
    FROM tickets t
    JOIN events e ON t.event_id = e.id
    WHERE e.organizer_id = ?
    ORDER BY e.event_date DESC, t.ticket_type
", [$organizer_id], "i");
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
      
        .ticket-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #ecf0f1;
            margin-bottom: 30px;
        }
        
        .tab {
            padding: 15px 30px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1em;
            color: #7f8c8d;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
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
            color: #2c3e50;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .ticket-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .ticket-type {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .ticket-price {
            font-size: 1.5em;
            font-weight: bold;
            color: #27ae60;
        }
        
        .ticket-info {
            margin: 10px 0;
            color: #7f8c8d;
        }
        
        .availability-bar {
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .availability-fill {
            height: 100%;
            background: #27ae60;
            transition: width 0.3s;
        }
        
        .availability-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .event-selector {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-available {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-low {
            color: #f39c12;
            font-weight: bold;
        }
        
        .status-soldout {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .seat-layout-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
            white-space: pre-wrap;
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
               <div class="img"><img src="../img/pro.jpg" alt=""></div>
               <div class="ro">ORGANIZER</div>
        </div>
        <div class="roles">
            <a href="org_dash.php">📊 Organizer Dashboard</a>
            <a href="eve_ma.php">🎭 Event Management</a>
            <a href="tick.php" class="act">🎟️ Ticket & Seat Management</a>
            <a href="../logout.php">🚪 Logout</a>
        </div>
    </aside>
   
    
    <div class="ticket-container">
        <h1>Ticket Management</h1>
        <hr>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('create')">Create Tickets</button>
            <button class="tab" onclick="switchTab('manage')">Manage Tickets</button>
            <button class="tab" onclick="switchTab('monitor')">Monitor Availability</button>
            <button class="tab" onclick="switchTab('layout')">Seat Layout</button>
        </div>
        
        <!-- Create Ticket Tab -->
        <div id="create-tab" class="tab-content active">
            <div class="event-selector">
                <h3>Select Event</h3>
                <select id="eventSelect" class="form-select" onchange="loadEventTickets(this.value)">
                    <option value="">Select an event...</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event['event_id']; ?>" <?php echo ($event_id == $event['event_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['event_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($event_id): ?>
                <div class="form-container">
                    <h2>Create New Ticket Type</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Ticket Type</label>
                            <select name="ticket_type" class="form-select" required>
                                <option value="VIP">VIP</option>
                                <option value="Regular">Regular</option>
                                <option value="Student">Student</option>
                                <option value="Early Bird">Early Bird</option>
                                <option value="Group">Group</option>
                                <option value="Premium">Premium</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" name="price" class="form-input" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Total Quantity</label>
                            <input type="number" name="total_quantity" class="form-input" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Seat Layout (Optional)</label>
                            <textarea name="seat_layout" class="form-textarea" placeholder="Enter seat layout information, e.g., 'A1-A50, B1-B50' or 'VIP: Rows 1-3, Regular: Rows 4-10'"></textarea>
                            <small>Describe the seating arrangement for this ticket type</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description (Optional)</label>
                            <textarea name="description" class="form-textarea" placeholder="Enter ticket description..."></textarea>
                        </div>
                        
                        <button type="submit" name="create_ticket" class="btn btn-success">Create Ticket Type</button>
                    </form>
                </div>
                
                <!-- Display existing tickets for this event -->
                <?php if (count($tickets) > 0): ?>
                    <div class="form-container">
                        <h3>Existing Ticket Types for This Event</h3>
                        <div class="ticket-grid">
                            <?php foreach ($tickets as $ticket): 
                                $percentage = ($ticket['sold_quantity'] / $ticket['total_quantity']) * 100;
                            ?>
                                <div class="ticket-card">
                                    <div class="ticket-header">
                                        <div class="ticket-type"><?php echo htmlspecialchars($ticket['ticket_type']); ?></div>
                                        <div class="ticket-price">$<?php echo number_format($ticket['price'], 2); ?></div>
                                    </div>
                                    <div class="ticket-info">
                                        <div>Available: <?php echo $ticket['available_quantity']; ?> / <?php echo $ticket['total_quantity']; ?></div>
                                        <div>Sold: <?php echo $ticket['sold_quantity']; ?></div>
                                    </div>
                                    <div class="availability-bar">
                                        <div class="availability-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <?php if (!empty($ticket['seat_layout'])): ?>
                                        <div class="seat-layout-preview">
                                            <?php echo htmlspecialchars($ticket['seat_layout']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Manage Tickets Tab -->
        <div id="manage-tab" class="tab-content">
            <div class="form-container">
                <h2>Manage Ticket Types</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Ticket Type</th>
                            <th>Price</th>
                            <th>Available</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['event_name']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                <td>$<?php echo number_format($ticket['price'], 2); ?></td>
                                <td>
                                    <span class="<?php 
                                        if ($ticket['available_quantity'] == 0) echo 'status-soldout';
                                        elseif ($ticket['available_quantity'] < 10) echo 'status-low';
                                        else echo 'status-available';
                                    ?>">
                                        <?php echo $ticket['available_quantity']; ?>
                                    </span>
                                </td>
                                <td><?php echo $ticket['total_quantity']; ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="editTicket(<?php echo $ticket['ticket_id']; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Monitor Availability Tab -->
        <div id="monitor-tab" class="tab-content">
            <div class="form-container">
                <h2>Ticket Availability Monitor</h2>
                
                <div class="ticket-grid">
                    <?php foreach ($all_tickets as $ticket): 
                        $percentage = ($ticket['sold_quantity'] / $ticket['total_quantity']) * 100;
                    ?>
                        <div class="ticket-card">
                            <div class="ticket-header">
                                <div class="ticket-type"><?php echo htmlspecialchars($ticket['ticket_type']); ?></div>
                                <div class="ticket-price">$<?php echo number_format($ticket['price'], 2); ?></div>
                            </div>
                            <div class="ticket-info">
                                <div><strong><?php echo htmlspecialchars($ticket['event_name']); ?></strong></div>
                                <div>Date: <?php echo date('M d, Y', strtotime($ticket['event_date'])); ?></div>
                            </div>
                            <div class="availability-text">
                                <span>Available: <?php echo $ticket['available_quantity']; ?></span>
                                <span>Sold: <?php echo $ticket['sold_quantity']; ?></span>
                                <span>Total: <?php echo $ticket['total_quantity']; ?></span>
                            </div>
                            <div class="availability-bar">
                                <div class="availability-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="availability-text">
                                <span><?php echo number_format($percentage, 1); ?>% Sold</span>
                                <span>
                                    <?php if ($ticket['available_quantity'] == 0): ?>
                                        <span class="status-soldout">SOLD OUT</span>
                                    <?php elseif ($ticket['available_quantity'] < 10): ?>
                                        <span class="status-low">LOW STOCK</span>
                                    <?php else: ?>
                                        <span class="status-available">AVAILABLE</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Seat Layout Tab -->
        <div id="layout-tab" class="tab-content">
            <div class="form-container">
                <h2>Seat Layout Management</h2>
                
                <div class="event-selector">
                    <h3>Select Event to View/Edit Seat Layout</h3>
                    <select id="layoutEventSelect" class="form-select" onchange="loadSeatLayout(this.value)">
                        <option value="">Select an event...</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['event_id']; ?>">
                                <?php echo htmlspecialchars($event['event_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="seatLayoutDisplay" style="margin-top: 30px;">
                    <p>Select an event to view seat layout.</p>
                </div>
            </div>
        </div>
    </div>
    </main>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        function loadEventTickets(eventId) {
            window.location.href = 'tick.php?event_id=' + eventId;
        }
        
        function editTicket(ticketId) {
            // Create a simple form for editing
            const newPrice = prompt("Enter new price:");
            const newQuantity = prompt("Enter new total quantity:");
            
            if (newPrice && newQuantity) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const ticketIdInput = document.createElement('input');
                ticketIdInput.type = 'hidden';
                ticketIdInput.name = 'ticket_id';
                ticketIdInput.value = ticketId;
                
                const priceInput = document.createElement('input');
                priceInput.type = 'hidden';
                priceInput.name = 'price';
                priceInput.value = newPrice;
                
                const quantityInput = document.createElement('input');
                quantityInput.type = 'hidden';
                quantityInput.name = 'total_quantity';
                quantityInput.value = newQuantity;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_ticket';
                submitInput.value = '1';
                
                form.appendChild(ticketIdInput);
                form.appendChild(priceInput);
                form.appendChild(quantityInput);
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function loadSeatLayout(eventId) {
            if (!eventId) {
                document.getElementById('seatLayoutDisplay').innerHTML = '<p>Select an event to view seat layout.</p>';
                return;
            }
            
            // Fetch tickets for the selected event
            const tickets = <?= json_encode($tickets) ?>;
            const eventTickets = tickets.filter(ticket => ticket.event_id == eventId);
            
            if (eventTickets.length === 0) {
                document.getElementById('seatLayoutDisplay').innerHTML = '<p>No tickets found for this event.</p>';
                return;
            }
            
            let html = '<h4>Seat Layout Information</h4>';
            eventTickets.forEach(ticket => {
                html += `
                    <div style="margin-bottom:20px; padding:15px; background:#f8f9fa; border-radius:5px;">
                        <strong>Ticket Type: ${ticket.ticket_type}</strong><br>
                        Available: ${ticket.available_quantity}/${ticket.total_quantity}<br>
                `;
                
                if (ticket.seat_layout) {
                    html += `Layout: <pre style="background:white; padding:10px;">${ticket.seat_layout}</pre>`;
                } else {
                    html += 'No specific seat layout defined.';
                }
                
                html += '</div>';
            });
            
            document.getElementById('seatLayoutDisplay').innerHTML = html;
        }
    </script>
</body>
</html>