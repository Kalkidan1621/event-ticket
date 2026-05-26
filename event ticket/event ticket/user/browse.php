<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle filters
$filters = [];
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($category)) {
    $filters['category'] = $category;
}
if (!empty($date_from)) {
    $filters['date_from'] = $date_from;
}

// Get events with filters
$events = getAllEvents($filters);

// Get unique categories for filter dropdown
$categories = fetchAll("SELECT DISTINCT event_category FROM events WHERE event_category IS NOT NULL AND event_category != ''");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - G5 Event</title>
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
        
        /* Browse Events Styles */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        
        .form-group label {
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
        }
        
        .btn-primary {
            background: #0c18fd;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .event-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
        }
        
        .event-image {
            height: 200px;
            background: linear-gradient(45deg, #0c18fd, #6a11cb);
            position: relative;
            overflow: hidden;
        }
        
        .event-category {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .event-content {
            padding: 20px;
        }
        
        .event-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .event-details {
            color: #666;
            margin: 10px 0;
        }
        
        .event-details p {
            margin: 5px 0;
        }
        
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .event-price {
            font-size: 1.5em;
            font-weight: bold;
            color: #0c18fd;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        
        .page-link.active {
            background: #0c18fd;
            color: white;
            border-color: #0c18fd;
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
                <a href="browse.php" class="act">🔍 Browse Events</a>
                <a href="book.php">🎟️ Book Tickets</a>
                <a href="ticket.php">💳 Payments</a>
                <a href="payment.php">📁 My Tickets</a>
                <a href="profile.php">👤 Profile</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </aside>
        <section>
            <h1>Browse Events</h1>
            <hr>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search Events</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Event name or location..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['event_category']); ?>"
                                    <?php echo $category == $cat['event_category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['event_category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="browse.php" class="btn btn-secondary" style="margin-top: 10px;">Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <!-- Events Grid -->
            <div class="events-grid">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): 
                        // Get ticket price range for this event
                        $ticket_prices = fetchAll("SELECT price FROM tickets WHERE event_id = ? AND available_quantity > 0 ORDER BY price ASC", [$event['id']], "i");
                        $min_price = $ticket_prices[0]['price'] ?? 0;
                    ?>
                        <div class="event-card">
                            <div class="event-image">
                                <div class="event-category"><?php echo htmlspecialchars($event['event_category']); ?></div>
                            </div>
                            <div class="event-content">
                                <h3 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                                <div class="event-details">
                                    <p><strong>📅 Date:</strong> <?php echo date('F d, Y', strtotime($event['event_date'])); ?></p>
                                    <p><strong>🕒 Time:</strong> <?php echo date('g:i A', strtotime($event['event_time'])); ?></p>
                                    <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($event['event_location']); ?></p>
                                    <p><strong>🎫 Available Tickets:</strong> <?php echo $event['total_tickets']; ?></p>
                                    <?php if (!empty($event['event_description'])): ?>
                                        <p><?php echo substr(htmlspecialchars($event['event_description']), 0, 100); ?>...</p>
                                    <?php endif; ?>
                                </div>
                                <div class="event-footer">
                                    <?php if ($min_price > 0): ?>
                                        <div class="event-price">From $<?php echo number_format($min_price, 2); ?></div>
                                    <?php else: ?>
                                        <div class="event-price">Sold Out</div>
                                    <?php endif; ?>
                                    <a href="book.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No events found</h3>
                        <p>Try adjusting your filters or check back later for new events.</p>
                        <a href="browse.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination (if needed) -->
            <?php if (count($events) > 12): ?>
                <div class="pagination">
                    <a href="#" class="page-link">« Previous</a>
                    <a href="#" class="page-link active">1</a>
                    <a href="#" class="page-link">2</a>
                    <a href="#" class="page-link">3</a>
                    <a href="#" class="page-link">Next »</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>