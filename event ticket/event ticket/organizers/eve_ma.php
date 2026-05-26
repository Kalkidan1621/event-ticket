<?php
require_once '../config.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header('Location: ../login.php');
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Handle form actions
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create'])) {
        // Create new event
        $sql = "INSERT INTO events (organizer_id, event_name, event_date, event_time, event_location, event_category, ticket_price, total_tickets, event_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = executeQuery($sql, [
            $organizer_id,
            $_POST['event_name'],
            $_POST['event_date'],
            $_POST['event_time'],
            $_POST['event_location'],
            $_POST['event_category'],
            $_POST['ticket_price'],
            $_POST['total_tickets'],
            $_POST['event_description']
        ], "isssssdis");
        
        $msg = "Event created successfully!";
        header("Location: eve_ma.php?msg=" . urlencode($msg));
        exit();
        
    } elseif (isset($_POST['update'])) {
        // Update event
        $sql = "UPDATE events SET event_name=?, event_date=?, event_time=?, event_location=?, event_category=?, ticket_price=?, total_tickets=?, event_description=? WHERE id=? AND organizer_id=?";
        $stmt = executeQuery($sql, [
            $_POST['event_name'],
            $_POST['event_date'],
            $_POST['event_time'],
            $_POST['event_location'],
            $_POST['event_category'],
            $_POST['ticket_price'],
            $_POST['total_tickets'],
            $_POST['event_description'],
            $_POST['event_id'],
            $organizer_id
        ], "sssssdisii");
        
        $msg = "Event updated successfully!";
        header("Location: eve_ma.php?msg=" . urlencode($msg));
        exit();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $sql = "DELETE FROM events WHERE id=? AND organizer_id=?";
    $stmt = executeQuery($sql, [$_GET['delete'], $organizer_id], "ii");
    $msg = "Event deleted successfully!";
    header("Location: eve_ma.php?msg=" . urlencode($msg));
    exit();
}

// Get message from URL
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Get all events for this organizer
$events = fetchAll("SELECT * FROM events WHERE organizer_id = ? ORDER BY event_date DESC", [$organizer_id], "i");

// Check edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = fetchSingle("SELECT * FROM events WHERE id=? AND organizer_id=?", [$_GET['edit'], $organizer_id], "ii");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Management</title>
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
.profile { text-align:center; 
    margin-bottom:20px; 
}
.profile h3 {
     color:#0c18fd; margin:10px 0; 
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
    padding:5px; border-radius:20px; margin:10px 30px; }
.roles a { display:block; padding:10px; color:#0c18fd; text-decoration:none; margin:5px 0; border-radius:5px; }
.roles a:hover { background:#0c18fd20; }
.act { background:#0c18fd !important; color:#fff !important; }
.new { background:#0c18fd; color:#fff; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; margin:10px 0; }
form { max-width:500px; margin:20px auto; padding:20px; background:#fff; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); }
label { display:block; margin:10px 0 5px; }
input, select, textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:5px; }
button[type="submit"] { background:#0c18fd; color:#fff; padding:12px; border:none; border-radius:5px; width:100%; cursor:pointer; }
.event-list { background:#fff; border-radius:10px; padding:20px; margin-top:20px; }
.event-row { display:flex; padding:15px; border-bottom:1px solid #eee; align-items:center; }
.event-row > div { padding:0 10px; }
.id { width:10%; color:#0c18fd; font-weight:bold; }
.name { width:40%; }
.date { width:20%; }
.actions { width:30%; text-align:right; }
.btn { padding:8px 15px; border:none; border-radius:5px; cursor:pointer; margin-left:5px; color:#fff; }
.show { background:#4CAF50; }
.edit { background:#2196F3; }
.delete { background:#f44336; }
.modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:0; width:90%; max-width:600px; border-radius:10px; z-index:1000; }
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; }
.modal-header { background:#0c18fd; color:#fff; padding:20px; border-radius:10px 10px 0 0; display:flex; justify-content:space-between; }
.modal-content { padding:20px; max-height:60vh; overflow-y:auto; }
.modal-actions { padding:20px; text-align:right; border-top:1px solid #eee; }
.msg { padding:10px; margin:10px 0; border-radius:5px; text-align:center; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
.close { background:none; border:none; color:#fff; font-size:24px; cursor:pointer; }
</style>
</head>
<body>
<main>
<aside>
<div/nclass="profile">
<h3>Grand/nEvent</h3>
<div class="img"><img src="../img/pro.jpg" alt="Profile"></div>
<div class="ro">ORGANIZER</div>
</div>
    <div class="roles">
    <a href="org_dash.php" class="act">📊  Dashboard</a>
    <a href="eve_ma.php">🎭 Event Management</a>
    <a href="tick.php">🎟️ Ticket & Seat Management</a>
    <a href="profile.php">👤 Profile</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
</aside>
<section>
<h1>Event Management</h1>
<hr>

<?php if($msg): ?>
<div class="msg success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<button class="new" id="newBtn">+ New Event</button>

<!-- Form -->
<div id="formBox" style="<?= $edit ? 'display:block;' : 'display:none;' ?>">
<form method="post">
<h3><?= $edit ? 'Edit Event' : 'New Event' ?></h3>
<?php if($edit): ?>
<input type="hidden" name="event_id" value="<?= $edit['id'] ?>">
<?php endif; ?>

<label>Event Name</label>
<input type="text" name="event_name" value="<?= $edit ? htmlspecialchars($edit['event_name']) : '' ?>" required>

<label>Date & Time</label>
<input type="date" name="event_date" value="<?= $edit ? $edit['event_date'] : '' ?>" required>
<input type="time" name="event_time" value="<?= $edit ? $edit['event_time'] : '' ?>" required>

<label>Location</label>
<input type="text" name="event_location" value="<?= $edit ? htmlspecialchars($edit['event_location']) : '' ?>" required>

<label>Category</label>
<select name="event_category" required>
<option value="">Select</option>
<option value="Music" <?= ($edit && $edit['event_category']=='Music')?'selected':'' ?>>Music</option>
<option value="Sport" <?= ($edit && $edit['event_category']=='Sport')?'selected':'' ?>>Sport</option>
<option value="Conference" <?= ($edit && $edit['event_category']=='Conference')?'selected':'' ?>>Conference</option>
<option value="Workshop" <?= ($edit && $edit['event_category']=='Workshop')?'selected':'' ?>>Workshop</option>
<option value="Festival" <?= ($edit && $edit['event_category']=='Festival')?'selected':'' ?>>Festival</option>
</select>

<label>Ticket Price ($)</label>
<input type="number" step="0.01" name="ticket_price" value="<?= $edit ? $edit['ticket_price'] : '' ?>" required>

<label>Total Tickets</label>
<input type="number" name="total_tickets" value="<?= $edit ? $edit['total_tickets'] : '' ?>" required>

<label>Description</label>
<textarea name="event_description" rows="4" required><?= $edit ? htmlspecialchars($edit['event_description']) : '' ?></textarea>

<button type="submit" name="<?= $edit ? 'update' : 'create' ?>">
<?= $edit ? 'Update Event' : 'Create Event' ?>
</button>
<button type="button" onclick="hideForm()" style="background:#666; margin-top:10px;">Cancel</button>
</form>
</div>

<!-- Events List -->
<div class="event-list">
<h2>Events (<?= count($events) ?>)</h2>

<?php if(empty($events)): ?>
<p>No events found.</p>
<?php else: ?>
<div class="event-row" style="background:#f5f5f5; font-weight:bold;">
<div class="id">ID</div>
<div class="name">Event Name</div>
<div class="date">Date</div>
<div class="actions">Actions</div>
</div>

<?php foreach($events as $e): ?>
<div class="event-row">
<div class="id">#<?= $e['id'] ?></div>
<div class="name"><?= htmlspecialchars($e['event_name']) ?></div>
<div class="date"><?= date('M d, Y', strtotime($e['event_date'])) ?></div>
<div class="actions">
<button class="btn show" onclick="showEvent(<?= $e['id'] ?>)">Show More</button>
<button class="btn edit" onclick="location.href='?edit=<?= $e['id'] ?>'">Edit</button>
<button class="btn delete" onclick="if(confirm('Delete this event?')) location.href='?delete=<?= $e['id'] ?>'">Delete</button>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>
</main>

<!-- Modal -->
<div class="modal-overlay" id="overlay"></div>
<div class="modal" id="modal">
<div class="modal-header">
<h3 id="modalTitle">Event Details</h3>
<button class="close" onclick="closeModal()">×</button>
</div>
<div class="modal-content" id="modalContent"></div>
<div class="modal-actions">
<button class="btn edit" id="modalEdit">Edit</button>
<button class="btn delete" id="modalDelete">Delete</button>
<button class="btn" onclick="closeModal()" style="background:#666;">Close</button>
</div>
</div>

<script>
// Store events data from PHP
const events = <?= json_encode($events) ?>;

// Form toggle
document.getElementById('newBtn').onclick = () => {
    document.getElementById('formBox').style.display = 'block';
    document.querySelector('form').reset();
    window.scrollTo({top: 0, behavior: 'smooth'});
};
function hideForm() {
    document.getElementById('formBox').style.display = 'none';
    location.href = '?';
}

// Modal functions
function showEvent(id) {
    const event = events.find(e => e.id == id);
    if (!event) return;
    
    document.getElementById('modalTitle').textContent = event.event_name;
    document.getElementById('modalContent').innerHTML = `
        <div style="margin-bottom:15px;">
            <strong>Event ID:</strong> #${event.id}<br>
            <strong>Date:</strong> ${event.event_date} at ${event.event_time}<br>
            <strong>Location:</strong> ${event.event_location}<br>
            <strong>Category:</strong> ${event.event_category}<br>
            <strong>Ticket Price:</strong> $${parseFloat(event.ticket_price).toFixed(2)}<br>
            <strong>Total Tickets:</strong> ${event.total_tickets}<br>
            <strong>Created:</strong> ${event.created_at}
        </div>
        <div>
            <strong>Description:</strong><br>
            <div style="background:#f9f9f9; padding:15px; border-radius:5px; margin-top:10px;">
                ${event.event_description.replace(/\n/g, '<br>')}
            </div>
        </div>
    `;
    
    // Set modal button actions
    document.getElementById('modalEdit').onclick = () => {
        location.href = `?edit=${event.id}`;
    };
    document.getElementById('modalDelete').onclick = () => {
        if(confirm('Delete this event?')) location.href = `?delete=${event.id}`;
    };
    
    // Show modal
    document.getElementById('modal').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}

// Close modal on overlay click
document.getElementById('overlay').onclick = closeModal;

// Close modal with ESC key
document.onkeydown = (e) => {
    if(e.key === 'Escape') closeModal();
};

// If editing, scroll to form
<?php if($edit): ?>
window.onload = () => {
    document.getElementById('formBox').scrollIntoView({behavior: 'smooth'});
};
<?php endif; ?>
</script>
</body>
</html>