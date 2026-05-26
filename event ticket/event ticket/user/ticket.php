<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle payment processing
$success_msg = '';
$error_msg = '';

if (isset($_GET['pay'])) {
    $sale_id = intval($_GET['pay']);
    
    // Update payment status
    $sql = "UPDATE ticket_sales SET payment_status = 'completed' WHERE sale_id = ? AND user_id = ?";
    $stmt = executeQuery($sql, [$sale_id, $user_id], "ii");
    
    if ($stmt->affected_rows > 0) {
        $success_msg = "Payment completed successfully!";
    } else {
        $error_msg = "Payment failed or booking not found.";
    }
}

// Get pending payments
$pending_payments = fetchAll("
    SELECT 
        ts.sale_id,
        ts.quantity,
        ts.total_amount,
        ts.sale_date,
        t.ticket_type,
        e.event_name,
        e.event_date,
        e.event_time,
        e.event_location
    FROM ticket_sales ts
    JOIN tickets t ON ts.ticket_id = t.ticket_id
    JOIN events e ON t.event_id = e.id
    WHERE ts.user_id = ? AND ts.payment_status = 'pending'
    ORDER BY ts.sale_date DESC
", [$user_id], "i");

// Get payment history
$payment_history = fetchAll("
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
    WHERE ts.user_id = ? AND ts.payment_status IN ('completed', 'failed')
    ORDER BY ts.sale_date DESC
    LIMIT 20
", [$user_id], "i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - G5 Event</title>
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
        
        /* Payments Styles */
        .payments-container {
            max-width: 1000px;
            margin: 0 auto;
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
        
        .payment-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .payment-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .payment-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .payment-card:hover {
            border-color: #0c18fd;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .payment-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }
        
        .payment-amount {
            font-size: 1.3em;
            font-weight: bold;
            color: #0c18fd;
        }
        
        .payment-details {
            color: #666;
            margin-bottom: 15px;
        }
        
        .payment-details p {
            margin: 5px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 8px 20px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .payment-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .summary-value {
            font-size: 2em;
            font-weight: bold;
            color: #0c18fd;
            margin: 10px 0;
        }
        
        .summary-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .due-date {
            color: #ff6b6b;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .payment-methods {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .payment-method {
            display: inline-block;
            margin-right: 15px;
            padding: 10px 20px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover,
        .payment-method.active {
            border-color: #0c18fd;
            background: #f0f2ff;
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
                <a href="ticket.php" class="act">💳 Payments</a>
                <a href="payment.php">📁 My Tickets</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <div class="payments-container">
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
                
                <h1>Payment Management</h1>
                <hr>
                
                <!-- Payment Summary -->
                <div class="payment-summary">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo count($pending_payments); ?></div>
                        <div class="summary-label">Pending Payments</div>
                    </div>
                    <div class="summary-card">
                        <?php 
                        $total_pending = array_sum(array_column($pending_payments, 'total_amount'));
                        ?>
                        <div class="summary-value">$<?php echo number_format($total_pending, 2); ?></div>
                        <div class="summary-label">Total Due</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo count($payment_history); ?></div>
                        <div class="summary-label">Payment History</div>
                    </div>
                </div>
                
                <!-- Pending Payments -->
                <div class="payment-section">
                    <h2 class="section-title">Pending Payments</h2>
                    
                    <?php if (!empty($pending_payments)): ?>
                        <div class="payment-cards">
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="payment-card">
                                    <div class="payment-header">
                                        <div class="payment-title"><?php echo htmlspecialchars($payment['event_name']); ?></div>
                                        <div class="payment-amount">$<?php echo number_format($payment['total_amount'], 2); ?></div>
                                    </div>
                                    <div class="payment-details">
                                        <p><strong>Ticket Type:</strong> <?php echo htmlspecialchars($payment['ticket_type']); ?></p>
                                        <p><strong>Quantity:</strong> <?php echo $payment['quantity']; ?></p>
                                        <p><strong>Event Date:</strong> <?php echo date('M d, Y', strtotime($payment['event_date'])); ?></p>
                                        <p><strong>Location:</strong> <?php echo htmlspecialchars($payment['event_location']); ?></p>
                                        <p><strong>Booking Date:</strong> <?php echo date('M d, Y H:i', strtotime($payment['sale_date'])); ?></p>
                                    </div>
                                    <span class="status-badge status-pending">Payment Pending</span>
                                    <div class="due-date">Due: Within 24 hours</div>
                                    <a href="ticket.php?pay=<?php echo $payment['sale_id']; ?>" class="btn btn-primary" style="margin-top: 15px; display: block; text-align: center;">
                                        Pay Now
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No Pending Payments</h3>
                            <p>You don't have any pending payments at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment History -->
                <div class="payment-section">
                    <h2 class="section-title">Payment History</h2>
                    
                    <?php if (!empty($payment_history)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Ticket Type</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_history as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['ticket_type']); ?></td>
                                        <td><?php echo $payment['quantity']; ?></td>
                                        <td><strong>$<?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No Payment History</h3>
                            <p>You haven't made any payments yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>