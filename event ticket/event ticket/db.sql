-- Create database
CREATE DATABASE IF NOT EXISTS `user_auth_system`;
USE `user_auth_system`;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','organizer','user') DEFAULT 'user',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events table
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) NOT NULL,
  `event_name` varchar(200) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `event_location` varchar(255) NOT NULL,
  `event_category` varchar(50) DEFAULT NULL,
  `ticket_price` decimal(10,2) NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `event_description` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tickets table
CREATE TABLE IF NOT EXISTS `tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `ticket_type` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_quantity` int(11) NOT NULL,
  `available_quantity` int(11) NOT NULL,
  `sold_quantity` int(11) DEFAULT 0,
  `seat_layout` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`),
  KEY `event_id` (`event_id`),
  KEY `ticket_type` (`ticket_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket sales table
CREATE TABLE IF NOT EXISTS `ticket_sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  PRIMARY KEY (`sale_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  KEY `sale_date` (`sale_date`),
  KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `created_at`) VALUES
('admin', 'admin@example.com', 'admin123', 'Administrator', 'admin', NOW()),
('organizer', 'organizer@example.com', 'organizer123', 'Event Organizer', 'organizer', NOW()),
('user1', 'user1@example.com', 'user123', 'Regular User', 'user', NOW());

-- Insert sample event
INSERT INTO `events` (`organizer_id`, `event_name`, `event_date`, `event_time`, `event_location`, `event_category`, `ticket_price`, `total_tickets`, `event_description`, `created_at`) VALUES
(2, 'Summer Music Festival 2024', '2024-07-15', '18:00:00', 'Central Park, New York', 'Music', 50.00, 500, 'Annual summer music festival featuring top artists from around the world. Food stalls and merchandise available.', NOW()),
(2, 'Tech Conference 2024', '2024-08-20', '09:00:00', 'Convention Center, San Francisco', 'Conference', 200.00, 300, 'Technology conference covering AI, Blockchain, and Web Development trends.', NOW());

-- Insert sample tickets
INSERT INTO `tickets` (`event_id`, `ticket_type`, `price`, `total_quantity`, `available_quantity`, `sold_quantity`, `seat_layout`, `description`, `created_at`) VALUES
(1, 'General Admission', 50.00, 300, 250, 50, 'GA Section A, B, C', 'Standing room only, includes festival access', NOW()),
(1, 'VIP', 150.00, 100, 80, 20, 'VIP Section, Front Row', 'VIP access, free drinks, backstage pass', NOW()),
(1, 'VVIP', 300.00, 50, 45, 5, 'VVIP Lounge, Premium Seating', 'All VIP benefits plus meet and greet with artists', NOW()),
(2, 'Early Bird', 150.00, 100, 80, 20, 'Main Hall, Rows 1-10', 'Early bird special price', NOW()),
(2, 'Regular', 200.00, 150, 140, 10, 'Main Hall, Rows 11-20', 'Standard conference access', NOW()),
(2, 'Premium', 350.00, 50, 45, 5, 'Premium Section, Front Center', 'Premium seating with lunch included', NOW());

-- Insert sample ticket sales
INSERT INTO `ticket_sales` (`ticket_id`, `user_id`, `quantity`, `total_amount`, `sale_date`, `payment_status`) VALUES
(1, 3, 2, 100.00, '2024-01-10 14:30:00', 'completed'),
(2, 3, 1, 150.00, '2024-01-11 10:15:00', 'completed'),
(4, 3, 1, 150.00, '2024-01-12 09:45:00', 'completed'),
(1, 3, 1, 50.00, '2024-01-13 16:20:00', 'pending'),
(3, 3, 1, 300.00, '2024-01-14 11:30:00', 'completed');

-- Add foreign key constraints
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

ALTER TABLE `ticket_sales`
  ADD CONSTRAINT `ticket_sales_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX idx_events_organizer ON events(organizer_id, event_date);
CREATE INDEX idx_tickets_event ON tickets(event_id, available_quantity);
CREATE INDEX idx_sales_user_date ON ticket_sales(user_id, sale_date);
CREATE INDEX idx_sales_status ON ticket_sales(payment_status, sale_date);

-- Create views for reporting
CREATE OR REPLACE VIEW `event_sales_summary` AS
SELECT 
    e.id AS event_id,
    e.event_name,
    e.event_date,
    COUNT(DISTINCT ts.sale_id) AS total_sales,
    SUM(ts.quantity) AS tickets_sold,
    SUM(ts.total_amount) AS total_revenue,
    AVG(ts.total_amount) AS avg_sale_amount
FROM events e
LEFT JOIN tickets t ON e.id = t.event_id
LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
GROUP BY e.id;

CREATE OR REPLACE VIEW `user_purchase_history` AS
SELECT 
    u.id AS user_id,
    u.username,
    u.email,
    u.role,
    COUNT(ts.sale_id) AS purchase_count,
    SUM(ts.quantity) AS tickets_purchased,
    SUM(ts.total_amount) AS total_spent,
    MAX(ts.sale_date) AS last_purchase_date
FROM users u
LEFT JOIN ticket_sales ts ON u.id = ts.user_id AND ts.payment_status = 'completed'
GROUP BY u.id;

CREATE OR REPLACE VIEW `ticket_availability` AS
SELECT 
    t.ticket_id,
    t.event_id,
    e.event_name,
    t.ticket_type,
    t.price,
    t.total_quantity,
    t.available_quantity,
    t.sold_quantity,
    ROUND((t.sold_quantity * 100.0 / t.total_quantity), 2) AS sold_percentage,
    CASE 
        WHEN t.available_quantity = 0 THEN 'Sold Out'
        WHEN t.available_quantity < 10 THEN 'Limited'
        ELSE 'Available'
    END AS availability_status
FROM tickets t
JOIN events e ON t.event_id = e.id;

-- Create stored procedures
DELIMITER //

CREATE PROCEDURE `GetDailyRevenueReport`(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        DATE(ts.sale_date) AS sale_day,
        COUNT(*) AS sales_count,
        SUM(ts.quantity) AS tickets_sold,
        SUM(ts.total_amount) AS daily_revenue,
        AVG(ts.total_amount) AS avg_sale_amount
    FROM ticket_sales ts
    WHERE ts.payment_status = 'completed'
        AND DATE(ts.sale_date) BETWEEN start_date AND end_date
    GROUP BY DATE(ts.sale_date)
    ORDER BY sale_day;
END //

CREATE PROCEDURE `GetEventPerformance`(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        e.event_name,
        e.event_category,
        e.event_date,
        COUNT(ts.sale_id) AS sales_count,
        SUM(ts.quantity) AS tickets_sold,
        SUM(ts.total_amount) AS total_revenue,
        AVG(ts.total_amount) AS avg_sale_amount,
        ROUND((SUM(ts.quantity) * 100.0 / e.total_tickets), 2) AS occupancy_rate
    FROM events e
    LEFT JOIN tickets t ON e.id = t.event_id
    LEFT JOIN ticket_sales ts ON t.ticket_id = ts.ticket_id AND ts.payment_status = 'completed'
    WHERE e.event_date BETWEEN start_date AND end_date
    GROUP BY e.id
    ORDER BY total_revenue DESC;
END //

CREATE PROCEDURE `ProcessRefund`(IN sale_id_param INT)
BEGIN
    DECLARE ticket_id_var INT;
    DECLARE quantity_var INT;
    DECLARE current_status VARCHAR(20);
    
    START TRANSACTION;
    
    -- Get sale details
    SELECT ticket_id, quantity, payment_status 
    INTO ticket_id_var, quantity_var, current_status
    FROM ticket_sales 
    WHERE sale_id = sale_id_param FOR UPDATE;
    
    -- Check if refund is possible
    IF current_status != 'completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only completed payments can be refunded';
    END IF;
    
    -- Update ticket availability
    UPDATE tickets 
    SET available_quantity = available_quantity + quantity_var,
        sold_quantity = sold_quantity - quantity_var
    WHERE ticket_id = ticket_id_var;
    
    -- Update payment status to failed (refunded)
    UPDATE ticket_sales 
    SET payment_status = 'failed'
    WHERE sale_id = sale_id_param;
    
    COMMIT;
END //

DELIMITER ;

-- Create triggers
DELIMITER //

CREATE TRIGGER `before_ticket_insert`
BEFORE INSERT ON `tickets`
FOR EACH ROW
BEGIN
    -- Set available_quantity equal to total_quantity for new tickets
    SET NEW.available_quantity = NEW.total_quantity;
END //

CREATE TRIGGER `before_ticket_update`
BEFORE UPDATE ON `tickets`
FOR EACH ROW
BEGIN
    -- Prevent available_quantity from going negative
    IF NEW.available_quantity < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Available quantity cannot be negative';
    END IF;
    
    -- Calculate sold_quantity
    SET NEW.sold_quantity = NEW.total_quantity - NEW.available_quantity;
END //

CREATE TRIGGER `after_ticket_sale_insert`
AFTER INSERT ON `ticket_sales`
FOR EACH ROW
BEGIN
    -- Update ticket availability when a sale is made
    IF NEW.payment_status = 'completed' THEN
        UPDATE tickets 
        SET available_quantity = available_quantity - NEW.quantity,
            sold_quantity = sold_quantity + NEW.quantity
        WHERE ticket_id = NEW.ticket_id;
    END IF;
END //

CREATE TRIGGER `after_ticket_sale_update`
AFTER UPDATE ON `ticket_sales`
FOR EACH ROW
BEGIN
    -- Handle status changes (e.g., from pending to completed)
    IF NEW.payment_status = 'completed' AND OLD.payment_status != 'completed' THEN
        UPDATE tickets 
        SET available_quantity = available_quantity - NEW.quantity,
            sold_quantity = sold_quantity + NEW.quantity
        WHERE ticket_id = NEW.ticket_id;
    END IF;
    
    -- Handle refunds
    IF NEW.payment_status = 'failed' AND OLD.payment_status = 'completed' THEN
        UPDATE tickets 
        SET available_quantity = available_quantity + NEW.quantity,
            sold_quantity = sold_quantity - NEW.quantity
        WHERE ticket_id = NEW.ticket_id;
    END IF;
END //

DELIMITER ;

-- Grant privileges (adjust based on your MySQL user)
GRANT ALL PRIVILEGES ON `user_auth_system`.* TO 'root'@'localhost';
FLUSH PRIVILEGES;

-- Show database status
SELECT 'Database setup completed successfully!' AS message;
SELECT COUNT(*) AS users_count FROM users;
SELECT COUNT(*) AS events_count FROM events;
SELECT COUNT(*) AS tickets_count FROM tickets;
SELECT COUNT(*) AS sales_count FROM ticket_sales;