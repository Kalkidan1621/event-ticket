<?php
require_once 'config.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    redirectBasedOnRole();
}

$errors = [];
$success = "";
// Handle registration form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Validation
    if(empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required";
    }
    
    if(strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Password validation - minimum 8 characters with complexity
    if(strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check for at least one uppercase letter
    if(!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    // Check for at least one lowercase letter
    if(!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    // Check for at least one number
    if(!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    // Check for at least one special character
    if(!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*()-_=+{};:,<.>)";
    }
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if(!in_array($role, ['user', 'organizer'])) {
        $errors[] = "Invalid role selected";
    }
    
    // Check if username or email already exists
    if(empty($errors)) {
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if($check_stmt->num_rows > 0) {
            $errors[] = "Username or email already exists";
        }
        $check_stmt->close();
    }
    
    // If no errors, insert into database
    if(empty($errors)) {
        // Store password directly without hashing
        $plain_password = $password;
        $insert_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssss", $username, $email, $plain_password, $role);
        
        if($insert_stmt->execute()) {
            $success = "Registration successful! You can now <a href='login.php'>login</a>.";
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $insert_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>home page</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="styles.css">
    
</head>
<body>
     <header>
    <div class="logo">
        <h2>G5 Event</h2>
    </div>
    
    <nav>
        <a href="index.php#home" >Home</a>
        <a href="index.php#about" >About Us</a>
        <a href="index.php#contact" >Contact Us</a>
        <a href="./login.php" >Login</a>
    </nav>
   
    </header>

    <main>
        
       
        <!-- registration form -->
        <section id="register">
           <div class="register_form">
            <?php if(!empty($errors)): ?>
            <div class="error">
                <?php foreach($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>

            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
            
            <form action="register.php" id="registerForm" method="POST">
        <div class="form-tittle">
          <h3 style="text-align: center;">Register</h3><br>
        </div>
        <label for="names">Name <span style="color: red;"> *</span></label>
        <input type="text" name="username" id="rnames"  placeholder="enter your name"/><br>
       
        <label for="emials">Email <span style="color: red;"> *</span></label>
        <input type="email" name="email" id="emails" value="" placeholder="enter your email"/><br>
        
        <label for="pass">Password <span style="color: red;"> *</span></label>
        <input type="password" name="password" id="pass" value="" placeholder="enter your Password "/><br>
        
        <label for="pass">Confirm password <span style="color: red;"> *</span></label>
        <input type="password" name="confirm_password" id="cpass" value="" placeholder="Confirm Password "/><br>
           <label>Account Type:</label>
                <select name="role" required>
                    <option value="user">User</option>
                    <option value="organizer">Organizer</option>
                </select>
                <small>Note: Admin accounts can only be created by existing admins</small>
        
        <div class="sub">
        
        <input type="reset" value="Clear" class="log ii">
        <input type="submit" name="submit" value="Register" class="log">
        </div>
        <br>
        <p class="swicher">I've Already an Account <a href="login.php" id="lo">Login</a></p>
      </form>
      

           </div>
        </section>

     </main>
</body>
</html>