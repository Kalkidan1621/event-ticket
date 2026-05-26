<?php
require_once '../config.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header('Location: ../login.php');
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Get organizer details - FIXED: using 'id' instead of 'user_id'
$organizer = fetchSingle("SELECT * FROM users WHERE id = ?", [$organizer_id], "i");

// Handle profile update
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    
    // Check if email already exists (excluding current user)
    $email_check = fetchSingle("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $organizer_id], "si");
    
    if ($email_check) {
        $msg = "<div class='msg error'>Email already exists!</div>";
    } else {
        // Update profile
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = executeQuery($sql, [$name, $email, $phone, $organizer_id], "sssi");
        
        // Update session name
        $_SESSION['user_name'] = $name;
        
        $msg = "<div class='msg success'>Profile updated successfully!</div>";
        
        // Refresh organizer data
        $organizer = fetchSingle("SELECT * FROM users WHERE id = ?", [$organizer_id], "i");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organizer Profile</title>
<style>
* { 
    margin:0; 
    padding:0; 
    box-sizing:border-box; 
}
body { 
    background:#e3e9e4; 
    font-family:Arial; 
}
main { 
    display:flex; 
}
aside { 
    width:17%; 
    padding:20px; 
    background:#fff; 
    min-height:100vh; 
}
section { 
    width:83%; 
    padding:20px; 
}
.profile { 
    text-align:center; 
    margin-bottom:20px; 
}
.profile h3 {
     color:#0c18fd; 
     margin:10px 0; 
}
.img { 
    width:60px; 
    height:60px; 
    border-radius:50%; 
    margin:auto; 
    overflow:hidden; 
}
.img img { 
    width:100%; 
    height:100%; 
    object-fit:cover; 
}
.ro { 
    background:#0c18fd; 
    color:#fff; 
    padding:5px; 
    border-radius:20px; 
    margin:10px 30px; 
}
.roles a { 
    display:block; 
    padding:10px; 
    color:#0c18fd; 
    text-decoration:none; 
    margin:5px 0; 
    border-radius:5px; 
}
.roles a:hover { 
    background:#0c18fd20; 
}
.act { 
    background:#0c18fd !important; 
    color:#fff !important; 
}
.form-container {
    max-width:600px;
    margin:20px auto;
    padding:30px;
    background:#fff;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}
.form-container h2 {
    color:#0c18fd;
    margin-bottom:20px;
    text-align:center;
}
.form-group {
    margin-bottom:20px;
}
.form-group label {
    display:block;
    margin-bottom:5px;
    font-weight:bold;
    color:#333;
}
.form-group input {
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:5px;
    font-size:16px;
}
.form-group input:disabled {
    background:#f5f5f5;
    cursor:not-allowed;
}
.btn {
    padding:12px 25px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:16px;
    font-weight:bold;
}
.btn-primary {
    background:#0c18fd;
    color:#fff;
}
.btn-primary:hover {
    background:#0a14d4;
}
.btn-secondary {
    background:#666;
    color:#fff;
    margin-left:10px;
}
.btn-secondary:hover {
    background:#555;
}
.profile-header {
    text-align:center;
    margin-bottom:30px;
}
.profile-pic {
    width:120px;
    height:120px;
    border-radius:50%;
    margin:0 auto 20px;
    overflow:hidden;
    border:3px solid #0c18fd;
}
.profile-pic img {
    width:100%;
    height:100%;
    object-fit:cover;
}
.msg {
    padding:10px;
    margin:10px 0;
    border-radius:5px;
    text-align:center;
}
.success { 
    background:#d4edda; 
    color:#155724; 
    border:1px solid #c3e6cb;
}
.error { 
    background:#f8d7da; 
    color:#721c24; 
    border:1px solid #f5c6cb;
}
.info-box {
    background:#f8f9fa;
    padding:20px;
    border-radius:5px;
    margin-top:30px;
    border-left:4px solid #0c18fd;
}
.info-box h4 {
    color:#0c18fd;
    margin-bottom:10px;
}
hr {
    border:none;
    border-top:1px solid #eee;
    margin:20px 0;
}
</style>
</head>
<body>
<main>
<aside>
<div class="profile">
<h3>Grand Event</h3>
<div class="img"><img src="../img/pro.jpg" alt="Profile"></div>
<div class="ro">ORGANIZER</div>
</div>
<div class="roles">
    <a href="org_dash.php">📊 Dashboard</a>
    <a href="eve_ma.php">🎭 Event Management</a>
    <a href="tick.php">🎟️ Ticket & Seat Management</a>
    <a href="profile.php" class="act">👤 Profile</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
</aside>
<section>
<h1>Organizer Profile</h1>
<hr>

<?php echo $msg; ?>

<div class="profile-header">
    <div class="profile-pic">
        <img src="../img/pro.jpg" alt="Profile Picture">
    </div>
    <h2><?php echo htmlspecialchars($organizer['full_name'] ?? $organizer['username']); ?></h2>
    <p>Organizer ID: #<?php echo $organizer_id; ?></p>
</div>

<div class="form-container">
    <h2>Edit Profile Information</h2>
    <form method="post">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($organizer['full_name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($organizer['email']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($organizer['phone'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?php echo htmlspecialchars($organizer['username']); ?>" disabled>
        </div>
        
        <div class="form-group">
            <label>User Role</label>
            <input type="text" value="Organizer" disabled>
        </div>
        
        <div class="form-group">
            <label>Account Created</label>
            <input type="text" value="<?php echo date('F d, Y', strtotime($organizer['created_at'])); ?>" disabled>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Update Profile</button>
            <button type="button" class="btn btn-secondary" onclick="location.href='org_dash.php'">Back to Dashboard</button>
        </div>
    </form>
</div>

<div class="info-box">
    <h4>Profile Information</h4>
    <p><strong>Note:</strong> Your profile picture is shared across all organizer pages. To change your profile picture, please replace the "img/pro.jpg" file in the root directory.</p>
    <p>For security reasons, you cannot change your user role or username. If you need to change your password or other security settings, please contact the system administrator.</p>
</div>

</section>
</main>
</body>
</html>