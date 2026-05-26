<?php
// fix_database.php
$conn = new mysqli('localhost', 'root', '', 'user_auth_system');

// Add full_name column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email");
    echo "Added full_name column to users table.<br>";
}

// Update existing users with full_name from username
$conn->query("UPDATE users SET full_name = username WHERE full_name IS NULL OR full_name = ''");
echo "Updated existing users with full_name.<br>";

echo "Database fix completed successfully!";
?>