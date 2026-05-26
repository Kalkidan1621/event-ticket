<?php
// login.php
require_once 'config.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'admin') {
        header("Location: admin/adminpage.php");
    } elseif($_SESSION['role'] == 'user') {
        header("Location: user/user_dash.php");
    } elseif($_SESSION['role'] == 'organizer') {
        header("Location: organizers/org_dash.php");
    }
    exit();
}

$error = "";

// Handle login form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if(empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check user in database
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        
        if($stmt) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password (plain text comparison for now)
                if($password === $user['password']) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    
                    // Redirect based on role
                    if($user['role'] == 'admin') {
                        header("Location: admin/adminpage.php");
                        exit();
                    } elseif($user['role'] == 'user') {
                        header("Location: user/user_dash.php");
                        exit();
                    } elseif($user['role'] == 'organizer') {
                        header("Location: organizers/org_dash.php");
                        exit();
                    } 
                } else {
                    $error = "Invalid password!";
                }
            } else {
                $error = "User not found!";
            }
            $stmt->close();
        } else {
            $error = "Database error!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - G5 Event</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="logo">
            <h2>G5 Event</h2>
        </div>
        
        <nav>
            <a href="index.php#home">Home</a>
            <a href="index.php#about">About Us</a>
            <a href="index.php#contact">Contact Us</a>
            <a href="login.php">Login</a>
        </nav>
    </header>

    <main>
        <section id="login">
            <div class="login_form">
                <?php if(!empty($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" id="loginForm" method="POST">
                    <div class="form-tittle">
                        <h2>Login</h2>
                    </div>
                    
                    <label for="email">Username or Email</label>
                    <input type="text" name="username" id="email" placeholder="Enter your username or email" required>
                    
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    
                    <div class="sub">
                        <input type="reset" value="Clear" class="log ii">
                        <input type="submit" name="submit" value="Login" id="conf" class="log">
                    </div>
                    
                    <p class="swicher">I don't have an Account <a href="register.php">Register</a></p>
                    
                    <div class="links">
                        <p><a href="index.php">← Back to Home</a></p>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>