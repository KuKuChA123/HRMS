<?php
session_start();
require_once 'db_connection.php'; // Assume this file contains the database connection


// Get employee details
$employee_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_timesheet'])) {
        // Timesheet submission
        $date = $_POST['date'];
        $clock_in = $_POST['clock_in'];
        $clock_out = $_POST['clock_out'];
        $break_duration = $_POST['break_duration'];
       
        // Calculate total hours
        $total_hours = (strtotime($clock_out) - strtotime($clock_in)) / 3600 - ($break_duration / 60);
       
        $stmt = $conn->prepare("INSERT INTO timesheets (employee_id, date, clock_in, clock_out, break_duration, total_hours) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssid", $employee_id, $date, $clock_in, $clock_out, $break_duration, $total_hours);
        $stmt->execute();
        $stmt->close();
       
        $timesheet_success = "Timesheet submitted successfully!";
    }
    elseif (isset($_POST['submit_time_off'])) {
        // Time off request
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $notes = $_POST['notes'];
       
        $stmt = $conn->prepare("INSERT INTO time_off_requests (employee_id, leave_type, start_date, end_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $employee_id, $leave_type, $start_date, $end_date, $notes);
        $stmt->execute();
        $stmt->close();
       
        $timeoff_success = "Time off request submitted successfully!";
    }
    elseif (isset($_POST['update_emergency_contact'])) {
        // Emergency contact update
        $name = $_POST['name'];
        $relationship = $_POST['relationship'];
        $phone = $_POST['phone'];
       
        // Check if contact exists
        $stmt = $conn->prepare("SELECT id FROM emergency_contacts WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result->num_rows > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE emergency_contacts SET name = ?, relationship = ?, phone = ? WHERE employee_id = ?");
            $stmt->bind_param("sssi", $name, $relationship, $phone, $employee_id);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO emergency_contacts (employee_id, name, relationship, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $employee_id, $name, $relationship, $phone);
        }
        $stmt->execute();
        $stmt->close();
       
        $emergency_success = "Emergency contact updated successfully!";
    }
}


// Get employee data for display
$timesheets = $conn->query("SELECT * FROM timesheets WHERE employee_id = $employee_id ORDER BY date DESC LIMIT 5");
$timeoff_requests = $conn->query("SELECT * FROM time_off_requests WHERE employee_id = $employee_id ORDER BY start_date DESC LIMIT 5");
$emergency_contact = $conn->query("SELECT * FROM emergency_contacts WHERE employee_id = $employee_id")->fetch_assoc();
$performance = $conn->query("SELECT * FROM performance WHERE employee_id = $employee_id ORDER BY review_date DESC LIMIT 1")->fetch_assoc();
$messages = $conn->query("SELECT * FROM inbox WHERE receiver_id = {$_SESSION['id']} ORDER BY sent_at DESC LIMIT 5");
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$calendar_events = $conn->query("SELECT * FROM calendar_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #4361ee;
        --primary-dark: #3a56d4;
        --secondary: #3f37c9;
        --accent: #4895ef;
        --light-accent: #f0f7ff;
        --dark: #2b2d42;
        --medium: #8d99ae;
        --light: #edf2f4;
        --white: #ffffff;
        --success: #4cc9f0;
        --warning: #f8961e;
        --danger: #f72585;
        --success-bg: #e8f9fd;
        --warning-bg: #fff3e6;
        --danger-bg: #ffe6ee;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 5px 10px rgba(0,0,0,0.05);
        --shadow-xl: 0 20px 40px rgba(0,0,0,0.15), 0 10px 10px rgba(0,0,0,0.05);
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }


    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }


    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        background-color: #f5f7fa;
        color: var(--dark);
        line-height: 1.6;
        font-weight: 400;
    }


    .dashboard {
        display: grid;
        grid-template-columns: 280px 1fr;
        min-height: 100vh;
    }


    /* Sidebar Styles */
    .sidebar {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        padding: 2rem 1.5rem;
        box-shadow: var(--shadow-md);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        transition: transform 0.3s ease;
        color: var(--white);
        z-index: 100;
    }


    .sidebar-header {
        text-align: center;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }


    .profile-pic {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,0.2);
        box-shadow: var(--shadow-md);
        margin-bottom: 1rem;
        transition: var(--transition);
    }


    .profile-pic:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-lg);
    }


    .sidebar h3 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }


    .sidebar p {
        font-size: 0.9rem;
        opacity: 0.9;
    }


    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }


    .nav-menu li {
        margin-bottom: 0.75rem;
    }


    .nav-menu a {
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }


    .nav-menu a:hover, .nav-menu a.active {
        background-color: rgba(255,255,255,0.15);
        color: var(--white);
        transform: translateX(5px);
    }


    .nav-menu a i {
        width: 20px;
        text-align: center;
    }


    /* Main Content Styles */
    .main-content {
        padding: 2rem 2.5rem;
        background-color: var(--white);
    }


    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        color: var(--white);
        padding: 1.5rem 2rem;
        border-radius: var(--radius-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
    }


    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }


    .welcome-banner::after {
        content: '';
        position: absolute;
        bottom: -80px;
        right: -30px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }


    .welcome-banner h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }


    .welcome-banner p {
        font-size: 1rem;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }


    .welcome-banner .greeting {
        font-size: 1.1rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }


    /* Card Styles */
    .card {
        background: var(--white);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: var(--transition);
        border: 1px solid rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
    }


    .card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-3px);
    }


    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }


    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }


    .card-title i {
        color: var(--primary);
    }


    .card-body {
        position: relative;
    }


    /* Button Styles */
    .btn {
        background-color: var(--primary);
        color: var(--white);
        border: none;
        padding: 0.75rem 1.25rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        box-shadow: var(--shadow-sm);
    }


    .btn:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }


    .btn i {
        font-size: 0.9rem;
    }


    .btn-outline {
        background-color: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }


    .btn-outline:hover {
        background-color: var(--primary);
        color: var(--white);
    }


    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }


    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        border-radius: var(--radius-sm);
        overflow: hidden;
        background-color: var(--white);
        box-shadow: var(--shadow-sm);
    }


    table th {
        background-color: var(--light-accent);
        color: var(--primary);
        font-weight: 600;
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid var(--light);
    }


    table td {
        padding: 1rem;
        border-bottom: 1px solid var(--light);
        color: var(--dark);
    }


    table tr:last-child td {
        border-bottom: none;
    }


    table tr:hover td {
        background-color: var(--light-accent);
    }


    /* Status Badges */
    .badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }


    .badge-pending {
        background-color: var(--warning-bg);
        color: var(--warning);
    }


    .badge-approved {
        background-color: var(--success-bg);
        color: var(--success);
    }


    .badge-rejected {
        background-color: var(--danger-bg);
        color: var(--danger);
    }


    /* Grid Layout */
    .grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }


    /* Stats Cards */
    .stat-card {
        background: var(--white);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border-left: 4px solid var(--primary);
    }


    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }


    .stat-card h3 {
        font-size: 0.9rem;
        color: var(--medium);
        margin-bottom: 0.5rem;
        font-weight: 500;
    }


    .stat-card .value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }


    .stat-card .change {
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }


    .change.up {
        color: var(--success);
    }


    .change.down {
        color: var(--danger);
    }


    /* Form Styles */
    .form-group {
        margin-bottom: 1.25rem;
    }


    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--dark);
    }


    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--light);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 1rem;
        transition: var(--transition);
        background-color: var(--white);
    }


    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
    }


    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }


    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        transition: opacity 0.3s ease;
    }


    .modal-content {
        background-color: var(--white);
        margin: 5% auto;
        padding: 2rem;
        border-radius: var(--radius-md);
        width: 90%;
        max-width: 600px;
        box-shadow: var(--shadow-xl);
        transform: translateY(-20px);
        transition: transform 0.3s ease;
        position: relative;
    }


    .modal-content.active {
        transform: translateY(0);
    }


    .modal-header {
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }


    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark);
    }


    .close {
        font-size: 1.5rem;
        font-weight: 300;
        color: var(--medium);
        cursor: pointer;
        transition: var(--transition);
    }


    .close:hover {
        color: var(--danger);
        transform: rotate(90deg);
    }


    /* Success Messages */
    .alert {
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }


    .alert-success {
        background-color: var(--success-bg);
        color: var(--success);
        border-left: 4px solid var(--success);
    }


    .alert i {
        font-size: 1.25rem;
    }


    /* Responsive Design */
    @media (max-width: 1024px) {
        .dashboard {
            grid-template-columns: 1fr;
        }


        .sidebar {
            position: fixed;
            width: 280px;
            transform: translateX(-100%);
            z-index: 1000;
        }


        .sidebar.active {
            transform: translateX(0);
        }


        .main-content {
            margin-left: 0;
            padding: 1.5rem;
        }


        .welcome-banner {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
    }


    @media (max-width: 768px) {
        .grid-container {
            grid-template-columns: 1fr;
        }


        .modal-content {
            width: 95%;
            margin: 10% auto;
            padding: 1.5rem;
        }
    }


    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }


    .animate-fade {
        animation: fadeIn 0.5s ease forwards;
    }


    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }


    ::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.05);
    }


    ::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.1);
        border-radius: 4px;
    }


    ::-webkit-scrollbar-thumb:hover {
        background: rgba(0,0,0,0.2);
    }
</style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo $employee['profile_picture'] ?: 'default-profile.jpg'; ?>" alt="Profile" class="profile-pic">
                <h3><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
                <p><?php echo htmlspecialchars($employee['position']); ?></p>
            </div>
           
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#timesheets"><i class="far fa-clock"></i> Timesheets</a></li>
                <li><a href="#timeoff"><i class="fas fa-umbrella-beach"></i> Time Off</a></li>
                <li><a href="#emergency"><i class="fas fa-user-plus"></i> Emergency Contacts</a></li>
                <li><a href="#performance"><i class="fas fa-chart-line"></i> Performance</a></li>
                <li><a href="employeeInbox.php"><i class="far fa-envelope"></i> Messages</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
       
        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner animate-fade">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($employee['first_name']); ?>!</h1>
                    <p>Today is <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="greeting">
                    <?php if(date('H') < 12): ?>
                        <i class="fas fa-sun"></i> Good morning!
                    <?php elseif(date('H') < 17): ?>
                        <i class="fas fa-cloud-sun"></i> Good afternoon!
                    <?php else: ?>
                        <i class="fas fa-moon"></i> Good evening!
                    <?php endif; ?>
                </div>
            </div>
           
            <!-- Success Messages -->
            <?php if(isset($timesheet_success)): ?>
                <div class="alert alert-success animate-fade">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $timesheet_success; ?>
                </div>
            <?php endif; ?>
           
            <?php if(isset($timeoff_success)): ?>
                <div class="alert alert-success animate-fade">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $timeoff_success; ?>
                </div>
            <?php endif; ?>
           
            <?php if(isset($emergency_success)): ?>
                <div class="alert alert-success animate-fade">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $emergency_success; ?>
                </div>
            <?php endif; ?>
           
            <!-- Quick Stats Grid -->
            <div class="grid-container">
                <!-- Timesheet Card -->
                <div class="stat-card animate-fade" style="animation-delay: 0.1s;">
                    <h3>Time Tracking</h3>
                    <div class="value">
                        <?php
                        $today = $conn->query("SELECT * FROM timesheets WHERE employee_id = $employee_id AND date = CURDATE()")->fetch_assoc();
                        echo $today ? number_format($today['total_hours'], 2) . 'h' : '--';
                        ?>
                    </div>
                    <p>Today's Hours</p>
                    <div class="change up">
                        <i class="fas fa-arrow-up"></i>
                        <?php
                        $week = $conn->query("SELECT SUM(total_hours) as total FROM timesheets WHERE employee_id = $employee_id AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc();
                        echo $week['total'] ? number_format($week['total'], 2) . 'h this week' : 'No records';
                        ?>
                    </div>
                    <button class="btn btn-sm" onclick="openModal('timesheetModal')" style="margin-top: 1rem;">
                        <i class="far fa-clock"></i> Clock In/Out
                    </button>
                </div>
               
                <!-- Time Off Card -->
                <div class="stat-card animate-fade" style="animation-delay: 0.2s;">
                    <h3>Time Off</h3>
                    <div class="value">15 days</div>
                    <p>Available PTO</p>
                    <div class="change down">
                        <i class="fas fa-arrow-down"></i>
                        <?php
                        $used = $conn->query("SELECT COUNT(*) as days FROM time_off_requests WHERE employee_id = $employee_id AND YEAR(start_date) = YEAR(CURDATE()) AND status = 'approved'")->fetch_assoc();
                        echo $used['days'] . ' days used';
                        ?>
                    </div>
                    <button class="btn btn-sm" onclick="openModal('timeoffModal')" style="margin-top: 1rem;">
                        <i class="fas fa-calendar-plus"></i> Request Time Off
                    </button>
                </div>
               
                <!-- Performance Card -->
                <div class="stat-card animate-fade" style="animation-delay: 0.3s;">
                    <h3>Performance</h3>
                    <?php if($performance): ?>
                        <div class="value"><?php echo $performance['rating']; ?>/5</div>
                        <p>Last Review: <?php echo date('M j, Y', strtotime($performance['review_date'])); ?></p>
                        <div style="color: gold; font-size: 1.5rem; margin: 0.5rem 0;">
                            <?php echo str_repeat('★', $performance['rating']) . str_repeat('☆', 5 - $performance['rating']); ?>
                        </div>
                    <?php else: ?>
                        <div class="value">--</div>
                        <p>No reviews yet</p>
                    <?php endif; ?>
                </div>
            </div>
           
            <!-- Recent Timesheets -->
            <div class="card animate-fade" id="timesheets" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <h2 class="card-title"><i class="far fa-clock"></i> Recent Timesheets</h2>
                    <a href="timesheets.php" class="btn btn-sm">
                        <i class="fas fa-list"></i> View All
                    </a>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Break</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $timesheets->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo date('g:i a', strtotime($row['clock_in'])); ?></td>
                                <td><?php echo $row['clock_out'] ? date('g:i a', strtotime($row['clock_out'])) : '--'; ?></td>
                                <td><?php echo $row['break_duration'] ? $row['break_duration'] . ' mins' : '--'; ?></td>
                                <td><strong><?php echo number_format($row['total_hours'], 2); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($timesheets->num_rows == 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No timesheet records found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
           
            <!-- Time Off Requests -->
            <div class="card animate-fade" id="timeoff" style="animation-delay: 0.5s;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-umbrella-beach"></i> Time Off Requests</h2>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $timeoff_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst($row['leave_type']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['end_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['notes'] ?: '--'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($timeoff_requests->num_rows == 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No time off requests found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
           
            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Emergency Contact -->
                <div class="card animate-fade" id="emergency" style="animation-delay: 0.6s;">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-user-plus"></i> Emergency Contact</h2>
                        <button class="btn btn-sm" onclick="openModal('emergencyModal')">
                            <i class="fas fa-edit"></i> <?php echo $emergency_contact ? 'Edit' : 'Add'; ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if($emergency_contact): ?>
                            <div class="form-group">
                                <label>Name</label>
                                <p style="font-weight: 500;"><?php echo htmlspecialchars($emergency_contact['name']); ?></p>
                            </div>
                            <div class="form-group">
                                <label>Relationship</label>
                                <p style="font-weight: 500;"><?php echo htmlspecialchars($emergency_contact['relationship']); ?></p>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <p style="font-weight: 500;"><?php echo htmlspecialchars($emergency_contact['phone']); ?></p>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--medium);">No emergency contact information on file</p>
                        <?php endif; ?>
                    </div>
                </div>
               
                <!-- Announcements -->
                <div class="card animate-fade" style="animation-delay: 0.7s;">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-bullhorn"></i> Announcements</h2>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php while($row = $announcements->fetch_assoc()): ?>
                            <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--primary);">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </h3>
                                <p style="margin-bottom: 0.5rem; color: var(--dark);">
                                    <?php echo htmlspecialchars($row['message']); ?>
                                </p>
                                <small style="font-size: 0.8rem; color: var(--medium);">
                                    <?php echo date('M j, Y g:i a', strtotime($row['created_at'])); ?>
                                </small>
                            </div>
                        <?php endwhile; ?>
                        <?php if($announcements->num_rows == 0): ?>
                            <p style="text-align: center; color: var(--medium);">No announcements</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
           
            <!-- Calendar Events -->
            <div class="card animate-fade" style="animation-delay: 0.8s;">
                <div class="card-header">
                    <h2 class="card-title"><i class="far fa-calendar-alt"></i> Upcoming Events</h2>
                </div>
                <div class="card-body">
                    <?php while($row = $calendar_events->fetch_assoc()): ?>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.05); align-items: flex-start;">
                            <div style="background-color: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: var(--radius-sm); text-align: center; min-width: 80px;">
                                <div style="font-size: 0.9rem; text-transform: uppercase;">
                                    <?php echo date('M', strtotime($row['event_date'])); ?>
                                </div>
                                <div style="font-size: 1.5rem; font-weight: 700;">
                                    <?php echo date('j', strtotime($row['event_date'])); ?>
                                </div>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; margin-bottom: 0.25rem; color: var(--dark);">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </h3>
                                <p style="margin-bottom: 0.5rem; color: var(--medium);">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </p>
                                <p style="font-size: 0.9rem; color: var(--primary);">
                                    <i class="far fa-clock"></i>
                                    <?php if($row['start_time']): ?>
                                        <?php echo date('g:i a', strtotime($row['start_time'])); ?>
                                        <?php if($row['end_time']): ?>
                                            - <?php echo date('g:i a', strtotime($row['end_time'])); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        All day
                                    <?php endif; ?>
                                </p>
                                </div>
                    <?php endwhile; ?>
                    <?php if($calendar_events->num_rows == 0): ?>
                        <p style="text-align: center; color: var(--medium);">No upcoming events</p>
                    <?php endif; ?>
                </div>
            </div>
           
            <!-- Footer -->
            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--light); color: var(--medium); font-size: 0.9rem;">
                <p>&copy; <?php echo date('Y'); ?> Company Name. All rights reserved.</p>
                <p style="margin-top: 0.5rem;">Employee Portal v1.0</p>
            </div>
        </div>
    </div>
   
    <!-- Timesheet Modal -->
    <div id="timesheetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Submit Timesheet</h2>
                <span class="close" onclick="closeModal('timesheetModal')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="clock_in">Clock In</label>
                    <input type="time" id="clock_in" name="clock_in" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="clock_out">Clock Out</label>
                    <input type="time" id="clock_out" name="clock_out" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="break_duration">Break Duration (minutes)</label>
                    <input type="number" id="break_duration" name="break_duration" class="form-control" min="0" step="5" value="0" required>
                </div>
                <button type="submit" name="submit_timesheet" class="btn">
                    <i class="fas fa-save"></i> Submit Timesheet
                </button>
            </form>
        </div>
    </div>
   
    <!-- Time Off Modal -->
    <div id="timeoffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Request Time Off</h2>
                <span class="close" onclick="closeModal('timeoffModal')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="form-group">
                    <label for="leave_type">Type of Leave</label>
                    <select id="leave_type" name="leave_type" class="form-control" required>
                        <option value="vacation">Vacation</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal Leave</option>
                        <option value="bereavement">Bereavement</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Optional details about your time off request"></textarea>
                </div>
                <button type="submit" name="submit_time_off" class="btn">
                    <i class="far fa-calendar-plus"></i> Submit Request
                </button>
            </form>
        </div>
    </div>
   
    <!-- Emergency Contact Modal -->
    <div id="emergencyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Emergency Contact</h2>
                <span class="close" onclick="closeModal('emergencyModal')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="form-group">
                    <label for="name">Contact Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo $emergency_contact['name'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="relationship">Relationship</label>
                    <input type="text" id="relationship" name="relationship" class="form-control" value="<?php echo $emergency_contact['relationship'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $emergency_contact['phone'] ?? ''; ?>" required>
                </div>
                <button type="submit" name="update_emergency_contact" class="btn">
                    <i class="fas fa-save"></i> Save Contact
                </button>
            </form>
        </div>
    </div>
   
    <script>
        // Function to open modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            setTimeout(() => {
                document.querySelector(`#${modalId} .modal-content`).classList.add('active');
            }, 10);
        }
       
        // Function to close modal
        function closeModal(modalId) {
            document.querySelector(`#${modalId} .modal-content`).classList.remove('active');
            setTimeout(() => {
                document.getElementById(modalId).style.display = 'none';
            }, 300);
        }
       
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeModal(event.target.id);
            }
        }
       
        // Mobile sidebar toggle
        const toggleSidebar = () => {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>
                       

