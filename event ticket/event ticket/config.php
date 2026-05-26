<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'user_auth_system');

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch(Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to redirect based on role
function redirectBasedOnRole() {
    if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
        $dashboard_file = "";
        
        switch($role) {
            case 'admin':
                $dashboard_file = "admin/adminpage.php";
                break;
            case 'user':
                $dashboard_file = "user/user_dash.php";
                break;
            case 'organizer':
                $dashboard_file = "organizers/org_dash.php";
                break;
        }
        
        if($dashboard_file && file_exists($dashboard_file)) {
            header("Location: $dashboard_file");
            exit();
        }
    }
}

// Helper function for executing prepared statements
function executeQuery($sql, $params = [], $types = "") {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat("s", count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

// Function to fetch single row
function fetchSingle($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to fetch all rows
function fetchAll($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// Initialize database tables if they don't exist
function initOrganizerTables() {
    global $conn;
    
    // First check if users table exists
    $users_table = $conn->query("SHOW TABLES LIKE 'users'");
    if ($users_table->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            role ENUM('admin', 'organizer', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert a test organizer (password: organizer123)
        $conn->query("INSERT INTO users (username, email, password, full_name, role) VALUES 
            ('organizer', 'organizer@example.com', 'organizer123', 'Event Organizer', 'organizer'),
            ('user1', 'user1@example.com', 'user123', 'Regular User', 'user')");
    }
    
    // Create events table
    if (!$conn->query("SHOW TABLES LIKE 'events'")) {
        $conn->query("CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT NOT NULL,
            event_name VARCHAR(200) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            event_location VARCHAR(255) NOT NULL,
            event_category VARCHAR(50),
            ticket_price DECIMAL(10,2) NOT NULL,
            total_tickets INT NOT NULL,
            event_description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Create tickets table
    if (!$conn->query("SHOW TABLES LIKE 'tickets'")) {
        $conn->query("CREATE TABLE IF NOT EXISTS tickets (
            ticket_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            ticket_type VARCHAR(50) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            total_quantity INT NOT NULL,
            available_quantity INT NOT NULL,
            sold_quantity INT DEFAULT 0,
            seat_layout TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Create ticket_sales table
    if (!$conn->query("SHOW TABLES LIKE 'ticket_sales'")) {
        $conn->query("CREATE TABLE IF NOT EXISTS ticket_sales (
            sale_id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT,
            quantity INT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_status ENUM('pending','completed','failed') DEFAULT 'pending'
        )");
    }
}

// Call table initialization
initOrganizerTables();

function checkAndAddColumns() {
    global $conn;
    
    // Check if full_name column exists in users table
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email");
    }
    
    // Check if phone column exists in users table
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER full_name");
    }
    
    // Check for other missing columns in other tables if needed
}

// Call this function after table initialization
checkAndAddColumns();

// Function to get user bookings
function getUserBookings($user_id) {
    return fetchAll("
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
            e.event_time,
            e.event_location
        FROM ticket_sales ts
        JOIN tickets t ON ts.ticket_id = t.ticket_id
        JOIN events e ON t.event_id = e.id
        WHERE ts.user_id = ?
        ORDER BY ts.sale_date DESC
    ", [$user_id], "i");
}

// Function to get all events for browsing
function getAllEvents($filters = []) {
    $sql = "SELECT * FROM events WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filters['category'])) {
        $sql .= " AND event_category = ?";
        $params[] = $filters['category'];
        $types .= "s";
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (event_name LIKE ? OR event_location LIKE ?)";
        $search_term = "%" . $filters['search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND event_date >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY event_date ASC";
    
    return fetchAll($sql, $params, $types);
}

// Function to get event by ID
function getEventById($event_id) {
    return fetchSingle("
        SELECT e.*, u.username as organizer_name 
        FROM events e 
        JOIN users u ON e.organizer_id = u.id 
        WHERE e.id = ?
    ", [$event_id], "i");
}

// Function to get tickets for an event
function getEventTickets($event_id) {
    return fetchAll("
        SELECT * FROM tickets 
        WHERE event_id = ? AND available_quantity > 0 
        ORDER BY price ASC
    ", [$event_id], "i");
}

// Function to update user profile
function updateUserProfile($user_id, $data) {
    $sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
    return executeQuery($sql, [$data['full_name'], $data['email'], $user_id], "ssi");
}

// Function to book tickets
function bookTickets($user_id, $ticket_id, $quantity, $total_amount) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update ticket availability
        $sql1 = "UPDATE tickets SET 
                 available_quantity = available_quantity - ?, 
                 sold_quantity = sold_quantity + ? 
                 WHERE ticket_id = ? AND available_quantity >= ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("iiii", $quantity, $quantity, $ticket_id, $quantity);
        $stmt1->execute();
        
        if ($stmt1->affected_rows === 0) {
            throw new Exception("Not enough tickets available");
        }
        
        // Create sale record
        $sql2 = "INSERT INTO ticket_sales (ticket_id, user_id, quantity, total_amount, payment_status) 
                 VALUES (?, ?, ?, ?, 'completed')";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("iiid", $ticket_id, $user_id, $quantity, $total_amount);
        $stmt2->execute();
        
        $sale_id = $conn->insert_id;
        
        $conn->commit();
        return $sale_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>