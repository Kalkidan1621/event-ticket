<?php
require_once '../config.php';

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$sale_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_payment'])) {
        $sale_id = $_POST['sale_id'];
        $payment_status = $_POST['payment_status'];
        
        $sql = "UPDATE ticket_sales SET payment_status = ? WHERE sale_id = ?";
        $stmt = executeQuery($sql, [$payment_status, $sale_id], "si");
        $message = "Payment status updated successfully!";
    }
    
    if (isset($_POST['refund_payment'])) {
        $sale_id = $_POST['sale_id'];
        
        // Get sale details
        $sale = fetchSingle("
            SELECT ts.ticket_id, ts.quantity 
            FROM ticket_sales ts 
            WHERE ts.sale_id = ?
        ", [$sale_id], "i");
        
        if ($sale) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update ticket availability
                $sql1 = "UPDATE tickets SET 
                        available_quantity = available_quantity + ?, 
                        sold_quantity = sold_quantity - ? 
                        WHERE ticket_id = ?";
                $stmt1 = executeQuery($sql1, [$sale['quantity'], $sale['quantity'], $sale['ticket_id']], "iii");
                
                // Update payment status to failed (refunded)
                $sql2 = "UPDATE ticket_sales SET payment_status = 'failed' WHERE sale_id = ?";
                $stmt2 = executeQuery($sql2, [$sale_id], "i");
                
                $conn->commit();
                $message = "Payment refunded successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Refund failed: " . $e->getMessage();
            }
        } else {
            $error = "Sale not found!";
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = "";

if ($filter_status) {
    $where_conditions[] = "ts.payment_status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_date_from) {
    $where_conditions[] = "DATE(ts.sale_date) >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(ts.sale_date) <= ?";
    $params[] = $filter_date_to;
    $param_types .= "s";
}

if ($search) {
    $where_conditions[] = "(e.event_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all payments with filters - FIXED: Using COALESCE for full_name
$payments = fetchAll("
    SELECT 
        ts.sale_id,
        ts.quantity,
        ts.total_amount,
        ts.sale_date,
        ts.payment_status,
        t.ticket_type,
        t.price as unit_price,
        e.event_name,
        e.event_date,
        e.event_location,
        u.username,
        u.email,
        COALESCE(u.full_name, u.username) as full_name
    FROM ticket_sales ts
    JOIN tickets t ON ts.ticket_id = t.ticket_id
    JOIN events e ON t.event_id = e.id
    JOIN users u ON ts.user_id = u.id
    $where_clause
    ORDER BY ts.sale_date DESC
", $params, $param_types);

// Get payment statistics
$payment_stats = fetchSingle("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
        AVG(total_amount) as avg_payment_amount,
        COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_payments,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments
    FROM ticket_sales
");

// Get payment for viewing - FIXED: Using COALESCE for full_name
$current_payment = null;
if ($sale_id) {
    $current_payment = fetchSingle("
        SELECT 
            ts.*,
            t.ticket_type,
            t.price as unit_price,
            t.description as ticket_description,
            e.event_name,
            e.event_date,
            e.event_time,
            e.event_location,
            e.event_description,
            u.username,
            u.email,
            COALESCE(u.full_name, u.username) as user_name,
            u2.username as organizer_name
        FROM ticket_sales ts
        JOIN tickets t ON ts.ticket_id = t.ticket_id
        JOIN events e ON t.event_id = e.id
        JOIN users u ON ts.user_id = u.id
        JOIN users u2 ON e.organizer_id = u2.id
        WHERE ts.sale_id = ?
    ", [$sale_id], "i");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
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
        
        /* Payment Management Styles */
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
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .stat-revenue {
            color: #28a745;
        }
        
        .stat-pending {
            color: #ffc107;
        }
        
        .stat-failed {
            color: #dc3545;
        }
        
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
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
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-detail-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .payment-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .payment-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .payment-status-select {
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            font-weight: bold;
        }
        
        .payment-status-select.completed {
            border-color: #28a745;
            color: #28a745;
        }
        
        .payment-status-select.pending {
            border-color: #ffc107;
            color: #ffc107;
        }
        
        .payment-status-select.failed {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
                <a href="adminpage.php" >🛠️ Dashboard</a>
                <a href="userman.php">👥 User Management</a>
                <a href="event_management.php">🎭 Event Management</a>
                <a href="ticket_management.php">🎟️ Ticket Management</a>
                <a href="payment_manage.php" class="act">💳 Payment Management</a>
                <a href="report.php">📊 Reports & Analytics</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Payment Management</h1>
            <hr>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Payment Statistics -->
            <?php if ($payment_stats && !$sale_id): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value stat-revenue">$<?php echo number_format($payment_stats['total_revenue'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $payment_stats['total_payments'] ?? 0; ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-pending">$<?php echo number_format($payment_stats['pending_amount'] ?? 0, 2); ?></div>
                        <div class="stat-label">Pending Amount</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($payment_stats['avg_payment_amount'] ?? 0, 2); ?></div>
                        <div class="stat-label">Average Payment</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #28a745;"><?php echo $payment_stats['completed_payments'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #ffc107;"><?php echo $payment_stats['pending_payments'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #dc3545;"><?php echo $payment_stats['failed_payments'] ?? 0; ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($sale_id && $current_payment): ?>
                <!-- Payment Details View -->
                <div class="payment-detail-section">
                    <div class="payment-header">
                        <h2>Payment Details</h2>
                        <p>Sale ID: #<?php echo $current_payment['sale_id']; ?></p>
                        
                        <div style="margin-top: 20px;">
                            <a href="payment_manage.php" class="btn btn-secondary">Back to Payments</a>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="payment-meta">
                        <div class="meta-item">
                            <div class="meta-label">Payment Status</div>
                            <div class="meta-value">
                                <span class="badge badge-<?php echo $current_payment['payment_status']; ?>">
                                    <?php echo ucfirst($current_payment['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Amount</div>
                            <div class="meta-value">
                                <strong style="font-size: 1.2em; color: #0c18fd;">
                                    $<?php echo number_format($current_payment['total_amount'], 2); ?>
                                </strong>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Sale Date</div>
                            <div class="meta-value"><?php echo date('F d, Y H:i:s', strtotime($current_payment['sale_date'])); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Quantity</div>
                            <div class="meta-value"><?php echo $current_payment['quantity']; ?> ticket(s)</div>
                        </div>
                    </div>
                    
                    <!-- User Information -->
                    <h3 style="margin-top: 30px;">Customer Information</h3>
                    <div class="payment-meta">
                        <div class="meta-item">
                            <div class="meta-label">Customer Name</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['user_name']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Username</div>
                            <div class="meta-value">@<?php echo htmlspecialchars($current_payment['username']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Email</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['email']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Event Information -->
                    <h3 style="margin-top: 30px;">Event Information</h3>
                    <div class="payment-meta">
                        <div class="meta-item">
                            <div class="meta-label">Event Name</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['event_name']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Event Date & Time</div>
                            <div class="meta-value">
                                <?php echo date('F d, Y', strtotime($current_payment['event_date'])); ?> 
                                at <?php echo date('g:i A', strtotime($current_payment['event_time'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Event Location</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['event_location']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Organizer</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['organizer_name']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Ticket Information -->
                    <h3 style="margin-top: 30px;">Ticket Information</h3>
                    <div class="payment-meta">
                        <div class="meta-item">
                            <div class="meta-label">Ticket Type</div>
                            <div class="meta-value"><?php echo htmlspecialchars($current_payment['ticket_type']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Unit Price</div>
                            <div class="meta-value">$<?php echo number_format($current_payment['unit_price'], 2); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Quantity</div>
                            <div class="meta-value"><?php echo $current_payment['quantity']; ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Total Amount</div>
                            <div class="meta-value">
                                <strong style="font-size: 1.2em; color: #0c18fd;">
                                    $<?php echo number_format($current_payment['total_amount'], 2); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($current_payment['ticket_description']): ?>
                        <div style="margin-top: 20px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px;">Ticket Description</label>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <?php echo nl2br(htmlspecialchars($current_payment['ticket_description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Update Payment Status -->
                    <div class="form-container" style="margin-top: 30px;">
                        <h3>Update Payment Status</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="sale_id" value="<?php echo $current_payment['sale_id']; ?>">
                            
                            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="font-weight: bold; display: block; margin-bottom: 8px;">Current Status</label>
                                    <span class="badge badge-<?php echo $current_payment['payment_status']; ?>">
                                        <?php echo ucfirst($current_payment['payment_status']); ?>
                                    </span>
                                </div>
                                
                                <div>
                                    <label style="font-weight: bold; display: block; margin-bottom: 8px;">New Status</label>
                                    <select name="payment_status" class="payment-status-select <?php echo $current_payment['payment_status']; ?>" required>
                                        <option value="completed" <?php echo $current_payment['payment_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="pending" <?php echo $current_payment['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="failed" <?php echo $current_payment['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="payment-actions">
                                <button type="submit" name="update_payment" class="btn btn-primary">Update Status</button>
                                
                                <?php if ($current_payment['payment_status'] == 'completed'): ?>
                                    <button type="submit" name="refund_payment" class="btn btn-warning" 
                                            onclick="return confirm('Are you sure you want to refund this payment? This will make the ticket available again.');">
                                        Process Refund
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Filters -->
                <div class="filter-section">
                    <h3>Filter Payments</h3>
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">Payment Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Event, user, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="payment_manage.php" class="btn btn-secondary" style="margin-top: 10px;">Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <!-- Payments List -->
                <div class="header-actions">
                    <div>
                        <h2>All Payments (<?php echo count($payments); ?>)</h2>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Event</th>
                            <th>Customer</th>
                            <th>Ticket Type</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['sale_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['event_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['full_name']); ?><br>
                                        <small><?php echo htmlspecialchars($payment['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['ticket_type']); ?></td>
                                    <td><?php echo $payment['quantity']; ?></td>
                                    <td><strong>$<?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['sale_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="payment_manage.php?action=view&id=<?php echo $payment['sale_id']; ?>" class="btn btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #666;">No payments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Export Option -->
                <?php if (!empty($payments)): ?>
                    <div style="margin-top: 20px; text-align: right;">
                        <button onclick="exportPayments()" class="btn btn-success">📊 Export to CSV</button>
                    </div>
                    
                    <script>
                        function exportPayments() {
                            // Create CSV content
                            let csv = 'Sale ID,Event,Customer,Email,Ticket Type,Quantity,Amount,Date,Status\n';
                            
                            <?php foreach ($payments as $payment): ?>
                                csv += '"#<?php echo $payment['sale_id']; ?>",';
                                csv += '"<?php echo addslashes($payment['event_name']); ?>",';
                                csv += '"<?php echo addslashes($payment['full_name']); ?>",';
                                csv += '"<?php echo addslashes($payment['email']); ?>",';
                                csv += '"<?php echo addslashes($payment['ticket_type']); ?>",';
                                csv += '"<?php echo $payment['quantity']; ?>",';
                                csv += '"$<?php echo number_format($payment['total_amount'], 2); ?>",';
                                csv += '"<?php echo date('M d, Y', strtotime($payment['sale_date'])); ?>",';
                                csv += '"<?php echo ucfirst($payment['payment_status']); ?>"\n';
                            <?php endforeach; ?>
                            
                            // Create download link
                            const blob = new Blob([csv], { type: 'text/csv' });
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'payments_<?php echo date('Y-m-d'); ?>.csv';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        }
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>