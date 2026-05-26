<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's tickets
$user_tickets = getUserBookings($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - G5 Event</title>
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
        
        /* Tickets Styles */
        .tickets-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .tickets-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .summary-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .summary-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .tickets-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 1.5em;
            color: #333;
            margin: 0;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-tab.active,
        .filter-tab:hover {
            background: #0c18fd;
            color: white;
            border-color: #0c18fd;
        }
        
        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .ticket-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #e9ecef;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .ticket-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #0c18fd;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .ticket-event {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            line-height: 1.3;
        }
        
        .ticket-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ticket-details {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px -25px -25px;
            border-top: 1px solid #e9ecef;
        }
        
        .ticket-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .ticket-row:last-child {
            border-bottom: none;
        }
        
        .ticket-label {
            color: #666;
            font-weight: bold;
        }
        
        .ticket-value {
            color: #333;
            font-weight: 500;
        }
        
        .ticket-qr {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }
        
        .qr-placeholder {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.9em;
        }
        
        .ticket-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-primary {
            background: #0c18fd;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a14d4;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .ticket-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 5em;
            color: rgba(12, 24, 253, 0.05);
            font-weight: bold;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }
        
        @media print {
            aside, .btn, .filter-tabs {
                display: none !important;
            }
            
            section {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .ticket-card {
                page-break-inside: avoid;
                border: 2px solid #000 !important;
            }
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
                <a href="payment.php" class="act">📁 My Tickets</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <div class="tickets-container">
                <h1>My Tickets</h1>
                <hr>
                
                <!-- Summary -->
                <div class="tickets-summary">
                    <?php 
                    $total_tickets = count($user_tickets);
                    $completed = array_filter($user_tickets, fn($t) => $t['payment_status'] === 'completed');
                    $pending = array_filter($user_tickets, fn($t) => $t['payment_status'] === 'pending');
                    ?>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $total_tickets; ?></div>
                        <div class="summary-label">Total Tickets</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo count($completed); ?></div>
                        <div class="summary-label">Confirmed</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo count($pending); ?></div>
                        <div class="summary-label">Pending</div>
                    </div>
                </div>
                
                <!-- Tickets List -->
                <div class="tickets-section">
                    <div class="section-header">
                        <h2 class="section-title">Your Bookings</h2>
                        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print All</button>
                    </div>
                    
                    <div class="filter-tabs">
                        <div class="filter-tab active" onclick="filterTickets('all')">All Tickets</div>
                        <div class="filter-tab" onclick="filterTickets('completed')">Confirmed</div>
                        <div class="filter-tab" onclick="filterTickets('pending')">Pending</div>
                        <div class="filter-tab" onclick="filterTickets('upcoming')">Upcoming</div>
                    </div>
                    
                    <?php if (!empty($user_tickets)): ?>
                        <div class="tickets-grid">
                            <?php foreach ($user_tickets as $ticket): 
                                $is_upcoming = strtotime($ticket['event_date']) > time();
                                $status_class = 'status-' . $ticket['payment_status'];
                            ?>
                                <div class="ticket-card" 
                                     data-status="<?php echo $ticket['payment_status']; ?>"
                                     data-upcoming="<?php echo $is_upcoming ? 'yes' : 'no'; ?>">
                                    <div class="ticket-watermark">TICKET</div>
                                    
                                    <div class="ticket-header">
                                        <div class="ticket-event"><?php echo htmlspecialchars($ticket['event_name']); ?></div>
                                        <span class="ticket-status <?php echo $status_class; ?>">
                                            <?php echo ucfirst($ticket['payment_status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="ticket-details">
                                        <div class="ticket-row">
                                            <span class="ticket-label">Ticket Type:</span>
                                            <span class="ticket-value"><?php echo htmlspecialchars($ticket['ticket_type']); ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Quantity:</span>
                                            <span class="ticket-value"><?php echo $ticket['quantity']; ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Unit Price:</span>
                                            <span class="ticket-value">$<?php echo number_format($ticket['unit_price'], 2); ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Total Amount:</span>
                                            <span class="ticket-value" style="color: #0c18fd; font-weight: bold;">
                                                $<?php echo number_format($ticket['total_amount'], 2); ?>
                                            </span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Event Date:</span>
                                            <span class="ticket-value"><?php echo date('F d, Y', strtotime($ticket['event_date'])); ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Event Time:</span>
                                            <span class="ticket-value"><?php echo date('g:i A', strtotime($ticket['event_time'])); ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Location:</span>
                                            <span class="ticket-value"><?php echo htmlspecialchars($ticket['event_location']); ?></span>
                                        </div>
                                        <div class="ticket-row">
                                            <span class="ticket-label">Booking Date:</span>
                                            <span class="ticket-value"><?php echo date('M d, Y H:i', strtotime($ticket['sale_date'])); ?></span>
                                        </div>
                                        
                                        <!-- QR Code Placeholder -->
                                        <?php if ($ticket['payment_status'] === 'completed'): ?>
                                            <div class="ticket-qr">
                                                <div class="qr-placeholder">
                                                    QR Code<br>
                                                    Scan for entry
                                                </div>
                                                <small>Booking ID: <?php echo str_pad($ticket['sale_id'], 8, '0', STR_PAD_LEFT); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="ticket-actions">
                                            <?php if ($ticket['payment_status'] === 'pending'): ?>
                                                <a href="ticket.php?pay=<?php echo $ticket['sale_id']; ?>" class="btn btn-primary">
                                                    💳 Complete Payment
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($ticket['payment_status'] === 'completed'): ?>
                                                <button onclick="printTicket(<?php echo $ticket['sale_id']; ?>)" class="btn btn-primary">
                                                    🖨️ Print Ticket
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">🎫</div>
                            <h3>No Tickets Yet</h3>
                            <p>You haven't booked any events yet. Start exploring and book your first event!</p>
                            <a href="browse.php" class="btn btn-primary" style="margin-top: 20px;">
                                Browse Events
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    
    <script>
        function filterTickets(filter) {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter tickets
            const tickets = document.querySelectorAll('.ticket-card');
            tickets.forEach(ticket => {
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'completed':
                        show = ticket.dataset.status === 'completed';
                        break;
                    case 'pending':
                        show = ticket.dataset.status === 'pending';
                        break;
                    case 'upcoming':
                        show = ticket.dataset.upcoming === 'yes';
                        break;
                }
                
                ticket.style.display = show ? 'block' : 'none';
            });
        }
        
        function printTicket(saleId) {
            const ticket = document.querySelector(`.ticket-card[data-sale-id="${saleId}"]`);
            if (ticket) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Ticket #${saleId}</title>
                            <style>
                                body { font-family: Arial; padding: 20px; }
                                .ticket { border: 2px solid #000; padding: 20px; max-width: 400px; margin: 0 auto; }
                                .ticket-header { text-align: center; margin-bottom: 20px; }
                                .ticket-details div { margin: 10px 0; }
                                .qr-code { text-align: center; margin: 20px 0; }
                                @media print { 
                                    body { padding: 0; }
                                    .ticket { border: none; }
                                }
                            </style>
                        </head>
                        <body>
                            ${ticket.outerHTML}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
        
        // Add data-sale-id attribute to tickets
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ticket-card').forEach((card, index) => {
                const saleId = card.querySelector('.qr-placeholder + small')?.textContent?.match(/\d+/);
                if (saleId) {
                    card.setAttribute('data-sale-id', saleId[0]);
                }
            });
        });
    </script>
</body>
</html>