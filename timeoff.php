<?php
// Include the database connection
require_once('db_connection.php');

// Start the session
session_start();

$user_id = $_SESSION['id'];
$error_message = '';
$success_message = '';

// Function to get time off balance for an employee
function getTimeOffBalance($conn, $employee_id, $year = null) {
    // If year is not specified, use current year
    if ($year === null) {
        $year = date('Y');
    }
    
    // Default balances (you can modify these or pull from a configuration table)
    $balances = [
        'vacation' => 15, // 15 days per year
        'sick' => 10,     // 10 days per year
        'personal' => 5,  // 5 days per year
        'bereavement' => 3, // 3 days per year
        'other' => 2      // 2 days per year
    ];
    
    // Get used days
    $used = [
        'vacation' => 0,
        'sick' => 0,
        'personal' => 0,
        'bereavement' => 0,
        'other' => 0
    ];
    
    // Query to get approved time off days in the given year
    $query = "SELECT leave_type, 
              SUM(DATEDIFF(end_date, start_date) + 1) as days_used 
              FROM time_off_requests 
              WHERE employee_id = ? 
              AND status = 'approved' 
              AND YEAR(start_date) = ? 
              GROUP BY leave_type";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $employee_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $used[$row['leave_type']] = $row['days_used'];
    }
    
    // Calculate remaining days
    $remaining = [];
    foreach ($balances as $type => $total) {
        $remaining[$type] = $total - $used[$type];
    }
    
    return [
        'total' => $balances,
        'used' => $used,
        'remaining' => $remaining
    ];
}

// Function to count business days between two dates (excluding weekends)
function getBusinessDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include the end date
    
    $interval = new DateInterval('P1D');
    $periods = new DatePeriod($start, $interval, $end);
    
    $business_days = 0;
    foreach ($periods as $period) {
        $day_of_week = $period->format('N');
        if ($day_of_week < 6) { // 1 (Monday) to 5 (Friday)
            $business_days++;
        }
    }
    
    return $business_days;
}

// Function to get a list of pending time off requests
function getPendingRequests($conn) {
    $query = "SELECT r.id, e.first_name, e.last_name, r.leave_type, 
              r.start_date, r.end_date, r.notes, r.created_at, e.id as employee_id,
              e.department, e.position
              FROM time_off_requests r
              JOIN employees e ON r.employee_id = e.id 
              WHERE r.status = 'pending'
              ORDER BY r.created_at DESC";
    
    $result = $conn->query($query);
    $requests = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate business days
            $business_days = getBusinessDays($row['start_date'], $row['end_date']);
            $row['business_days'] = $business_days;
            
            // Calculate total days
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $interval = $start->diff($end);
            $row['total_days'] = $interval->days + 1; // Include both start and end days
            
            $requests[] = $row;
        }
    }
    
    return $requests;
}

// Function to get all time off requests with filters
function getAllRequests($conn, $filters = []) {
    $query = "SELECT r.id, e.first_name, e.last_name, r.leave_type, 
              r.start_date, r.end_date, r.notes, r.status, r.created_at, 
              e.id as employee_id, e.department, e.position
              FROM time_off_requests r
              JOIN employees e ON r.employee_id = e.id WHERE 1=1";
    
    // Apply filters
    if (!empty($filters['status'])) {
        $query .= " AND r.status = '" . $filters['status'] . "'";
    }
    
    if (!empty($filters['department'])) {
        $query .= " AND e.department = '" . $filters['department'] . "'";
    }
    
    if (!empty($filters['leave_type'])) {
        $query .= " AND r.leave_type = '" . $filters['leave_type'] . "'";
    }
    
    if (!empty($filters['employee_id'])) {
        $query .= " AND e.id = " . $filters['employee_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND r.start_date >= '" . $filters['start_date'] . "'";
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND r.end_date <= '" . $filters['end_date'] . "'";
    }
    
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query .= " AND (e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%')";
    }
    
    // Order by
    $query .= " ORDER BY " . (!empty($filters['order_by']) ? $filters['order_by'] : "r.created_at DESC");
    
    // Limit
    if (!empty($filters['limit'])) {
        $query .= " LIMIT " . $filters['limit'];
    }
    
    $result = $conn->query($query);
    $requests = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate business days
            $business_days = getBusinessDays($row['start_date'], $row['end_date']);
            $row['business_days'] = $business_days;
            
            // Calculate total days
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $interval = $start->diff($end);
            $row['total_days'] = $interval->days + 1; // Include both start and end days
            
            $requests[] = $row;
        }
    }
    
    return $requests;
}

// Function to get all departments
function getAllDepartments($conn) {
    $query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $result = $conn->query($query);
    $departments = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row['department'];
        }
    }
    
    return $departments;
}

// Function to get employee details
function getEmployee($conn, $employee_id) {
    $query = "SELECT e.*, u.email, u.username FROM employees e 
              JOIN users u ON e.user_id = u.id 
              WHERE e.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Handle approve/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve' || $_POST['action'] === 'reject') {
        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
        $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
        
        if ($request_id > 0) {
            $query = "UPDATE time_off_requests SET status = ?, comments = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $status, $comments, $request_id);
            
            if ($stmt->execute()) {
                $success_message = "The time off request has been " . $status . " successfully.";
                
                // Could add email notification here
            } else {
                $error_message = "Error updating request: " . $conn->error;
            }
        }
    } else if ($_POST['action'] === 'bulk_approve' && isset($_POST['request_ids'])) {
        $ids = $_POST['request_ids'];
        $success_count = 0;
        
        foreach ($ids as $id) {
            $query = "UPDATE time_off_requests SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $success_message = "$success_count request(s) have been approved successfully.";
        }
    }
}

// Get filters from URL
$filters = [
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'department' => isset($_GET['department']) ? $_GET['department'] : '',
    'leave_type' => isset($_GET['leave_type']) ? $_GET['leave_type'] : '',
    'employee_id' => isset($_GET['employee_id']) ? intval($_GET['employee_id']) : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : '',
    'order_by' => isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at DESC'
];

// Get data for the page
$pending_requests = getPendingRequests($conn);
$all_requests = getAllRequests($conn, $filters);
$departments = getAllDepartments($conn);

// Get statistics
$stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'vacation' => 0,
    'sick' => 0,
    'personal' => 0,
    'bereavement' => 0,
    'other' => 0
];

$stats_query = "SELECT status, COUNT(*) as count FROM time_off_requests GROUP BY status";
$result = $conn->query($stats_query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
}

$leave_stats_query = "SELECT leave_type, COUNT(*) as count FROM time_off_requests GROUP BY leave_type";
$result = $conn->query($leave_stats_query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stats[$row['leave_type']] = $row['count'];
    }
}

// Current month stats
$current_month = date('Y-m');
$month_query = "SELECT COUNT(*) as count FROM time_off_requests 
                WHERE DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?";
$stmt = $conn->prepare($month_query);
$stmt->bind_param('ss', $current_month, $current_month);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['current_month'] = $row['count'];

// Get employee calendar data for the current month
$calendar_query = "SELECT e.id, e.first_name, e.last_name, r.start_date, r.end_date, r.leave_type, r.status 
                   FROM time_off_requests r
                   JOIN employees e ON r.employee_id = e.id
                   WHERE (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)
                   AND r.status = 'approved'";
$stmt = $conn->prepare($calendar_query);
$stmt->bind_param('ss', $current_month, $current_month);
$stmt->execute();
$result = $stmt->get_result();
$calendar_data = [];

while ($row = $result->fetch_assoc()) {
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    $end->modify('+1 day'); // Include end date
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        if (!isset($calendar_data[$date_str])) {
            $calendar_data[$date_str] = [];
        }
        
        $calendar_data[$date_str][] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'type' => $row['leave_type'],
            'status' => $row['status']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Off Management | HR Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-color: #4e54c8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }
        
        .card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stats-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stats-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .pending-badge {
            background-color: var(--warning-color);
            color: #000;
        }
        
        .approved-badge {
            background-color: var(--success-color);
        }
        
        .rejected-badge {
            background-color: var(--danger-color);
        }
        
        .vacation-badge {
            background-color: #64b5f6;
        }
        
        .sick-badge {
            background-color: #ef5350;
        }
        
        .personal-badge {
            background-color: #9575cd;
        }
        
        .bereavement-badge {
            background-color: #4db6ac;
        }
        
        .other-badge {
            background-color: #ffb74d;
        }
        
        .request-card {
            border-left: 5px solid transparent;
            transition: all 0.2s ease;
        }
        
        .request-card:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .request-card.vacation {
            border-left-color: #64b5f6;
        }
        
        .request-card.sick {
            border-left-color: #ef5350;
        }
        
        .request-card.personal {
            border-left-color: #9575cd;
        }
        
        .request-card.bereavement {
            border-left-color: #4db6ac;
        }
        
        .request-card.other {
            border-left-color: #ffb74d;
        }
        
        .request-actions .btn {
            min-width: 100px;
        }
        
        .filter-form .form-control,
        .filter-form .form-select {
            border-radius: 10px;
        }
        
        .table th {
            background-color: rgba(0, 0, 0, 0.03);
            font-weight: 600;
        }
        
        .calendar {
            width: 100%;
        }
        
        .calendar th, .calendar td {
            width: 14.28%;
            height: 100px;
            border: 1px solid #dee2e6;
            padding: 5px;
            vertical-align: top;
        }
        
        .calendar th {
            height: auto;
            text-align: center;
            background-color: rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        
        .calendar .day-header {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .calendar .day-number {
            float: right;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .calendar .day-event {
            font-size: 0.8rem;
            margin-bottom: 2px;
            border-radius: 3px;
            padding: 2px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .calendar .current-day {
            background-color: rgba(106, 17, 203, 0.05);
        }
        
        .calendar .other-month {
            background-color: #f8f9fa;
            color: #adb5bd;
        }
        
        .time-balance-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }
        
        .progress {
            height: 12px;
            border-radius: 6px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            font-weight: 500;
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.2s ease;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        /* Print styles */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
            }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .calendar th, .calendar td {
                height: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .calendar th, .calendar td {
                height: auto;
                padding: 5px 2px;
            }
            
            .calendar .day-event {
                font-size: 0.7rem;
                padding: 1px 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav aria-label="breadcrumb" class="no-print mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Time Off Management</li>
            </ol>
        </nav>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Time Off Management</h4>
                <div>
                    <button class="btn btn-sm btn-outline-light" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <a href="timeoff_report.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-file-export me-1"></i> Export
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stats-card bg-gradient-purple text-white h-100">
                            <div class="card-body text-center">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">Pending Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stats-card bg-gradient-success text-white h-100">
                            <div class="card-body text-center">
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $stats['approved']; ?></div>
                                <div class="stat-label">Approved Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stats-card bg-gradient-danger text-white h-100">
                            <div class="card-body text-center">
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                                <div class="stat-label">Rejected Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stats-card bg-gradient-info text-white h-100">
                            <div class="card-body text-center">
                                <div class="icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="stat-value"><?php echo $stats['current_month']; ?></div>
                                <div class="stat-label">This Month</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Tabs -->
                <ul class="nav nav-tabs no-print" id="timeoffTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" 
                                data-bs-target="#pending" type="button" role="tab">
                            <i class="fas fa-hourglass-half me-1"></i> Pending Requests 
                            <span class="badge bg-warning text-dark"><?php echo count($pending_requests); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-tab" data-bs-toggle="tab" 
                                data-bs-target="#all" type="button" role="tab">
                            <i class="fas fa-list me-1"></i> All Requests
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" 
                                data-bs-target="#calendar" type="button" role="tab">
                            <i class="fas fa-calendar me-1"></i> Calendar View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" 
                                data-bs-target="#reports" type="button" role="tab">
                            <i class="fas fa-chart-pie me-1"></i> Reports
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="timeoffTabsContent">
                    <!-- Pending Requests Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <?php if (empty($pending_requests)): ?>
                            <div class="text-center p-5">
                                <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                                <h4>No pending requests</h4>
                                <p class="text-muted">All time off requests have been processed.</p>
                            </div>
                        <?php else: ?>
                            <!-- Bulk actions -->
                            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                                <h5>Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                                <div>
                                    <form id="bulkActionForm" method="post" action="">
                                        <input type="hidden" name="action" value="bulk_approve">
                                        <button type="submit" class="btn btn-sm btn-success" id="bulkApproveBtn" disabled>
                                            <i class="fas fa-check me-1"></i> Approve Selected
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Pending requests list -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="30" class="no-print">
                                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                            </th>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Duration</th>
                                            <th>Dates</th>
                                            <th>Requested</th>
                                            <th width="150" class="no-print">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td class="no-print">
                                                <input type="checkbox" class="form-check-input request-checkbox" 
                                                       name="request_ids[]" value="<?php echo $request['id']; ?>" 
                                                       form="bulkActionForm">
                                                       </td>
                                            <td>
                                                <?php echo $request['first_name'] . ' ' . $request['last_name']; ?>
                                            </td>
                                            <td><?php echo $request['department']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $request['leave_type']; ?>-badge">
                                                    <?php echo ucfirst($request['leave_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $request['business_days']; ?> business days
                                                <small class="text-muted d-block">(<?php echo $request['total_days']; ?> total)</small>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($request['start_date'])); ?>
                                                <small class="text-muted d-block">to <?php echo date('M d, Y', strtotime($request['end_date'])); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                            </td>
                                            <td class="no-print">
                                                <div class="request-actions">
                                                    <button type="button" class="btn btn-sm btn-success approve-btn" 
                                                            data-request-id="<?php echo $request['id']; ?>"
                                                            data-employee-id="<?php echo $request['employee_id']; ?>"
                                                            data-employee-name="<?php echo $request['first_name'] . ' ' . $request['last_name']; ?>"
                                                            data-leave-type="<?php echo $request['leave_type']; ?>"
                                                            data-start-date="<?php echo $request['start_date']; ?>"
                                                            data-end-date="<?php echo $request['end_date']; ?>"
                                                            data-notes="<?php echo htmlspecialchars($request['notes']); ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger reject-btn"
                                                            data-request-id="<?php echo $request['id']; ?>"
                                                            data-employee-name="<?php echo $request['first_name'] . ' ' . $request['last_name']; ?>"
                                                            data-leave-type="<?php echo $request['leave_type']; ?>"
                                                            data-start-date="<?php echo $request['start_date']; ?>"
                                                            data-end-date="<?php echo $request['end_date']; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- All Requests Tab -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        <!-- Filter form -->
                        <form method="get" class="row g-3 mb-4 filter-form no-print">
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                            <?php echo $dept; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="leave_type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="vacation" <?php echo $filters['leave_type'] === 'vacation' ? 'selected' : ''; ?>>Vacation</option>
                                    <option value="sick" <?php echo $filters['leave_type'] === 'sick' ? 'selected' : ''; ?>>Sick</option>
                                    <option value="personal" <?php echo $filters['leave_type'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                                    <option value="bereavement" <?php echo $filters['leave_type'] === 'bereavement' ? 'selected' : ''; ?>>Bereavement</option>
                                    <option value="other" <?php echo $filters['leave_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control datepicker" name="start_date" placeholder="From Date" value="<?php echo $filters['start_date']; ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control datepicker" name="end_date" placeholder="To Date" value="<?php echo $filters['end_date']; ?>">
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Search" value="<?php echo $filters['search']; ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- All requests table -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                        <th width="100" class="no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_requests)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <p class="text-muted">No time off requests found matching your criteria.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_requests as $request): ?>
                                            <tr class="request-card <?php echo $request['leave_type']; ?>">
                                                <td>
                                                    <?php echo $request['first_name'] . ' ' . $request['last_name']; ?>
                                                </td>
                                                <td><?php echo $request['department']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $request['leave_type']; ?>-badge">
                                                        <?php echo ucfirst($request['leave_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $request['business_days']; ?> business days
                                                    <small class="text-muted d-block">(<?php echo $request['total_days']; ?> total)</small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($request['start_date'])); ?>
                                                    <small class="text-muted d-block">to <?php echo date('M d, Y', strtotime($request['end_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $request['status']; ?>-badge">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                </td>
                                                <td class="no-print">
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-details-btn"
                                                            data-request-id="<?php echo $request['id']; ?>"
                                                            data-employee-id="<?php echo $request['employee_id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Calendar View Tab -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <div class="mb-3 no-print">
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary" id="prevMonth">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="btn btn-outline-secondary" id="currentMonth">
                                    <?php echo date('F Y'); ?>
                                </button>
                                <button class="btn btn-outline-secondary" id="nextMonth">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="calendar-container">
                            <table class="calendar">
                                <thead>
                                    <tr>
                                        <th>Sunday</th>
                                        <th>Monday</th>
                                        <th>Tuesday</th>
                                        <th>Wednesday</th>
                                        <th>Thursday</th>
                                        <th>Friday</th>
                                        <th>Saturday</th>
                                    </tr>
                                </thead>
                                <tbody id="calendarBody">
                                    <!-- Calendar will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-2">
                                <div class="me-3">
                                    <span class="badge vacation-badge">&nbsp;</span> Vacation
                                </div>
                                <div class="me-3">
                                    <span class="badge sick-badge">&nbsp;</span> Sick
                                </div>
                                <div class="me-3">
                                    <span class="badge personal-badge">&nbsp;</span> Personal
                                </div>
                                <div class="me-3">
                                    <span class="badge bereavement-badge">&nbsp;</span> Bereavement
                                </div>
                                <div>
                                    <span class="badge other-badge">&nbsp;</span> Other
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Time Off by Type</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="timeOffByTypeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Time Off by Department</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="timeOffByDepartmentChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Monthly Time Off Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyTrendsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="no-print mb-3">
                            <h5>Generate Reports</h5>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="timeoff_report.php?type=department" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-building me-1"></i> Department Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="timeoff_report.php?type=employee" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-user me-1"></i> Employee Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="timeoff_report.php?type=monthly" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-calendar-alt me-1"></i> Monthly Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="timeoff_report.php?type=custom" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-sliders-h me-1"></i> Custom Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <!-- Approve Request Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Time Off Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="request_id" id="approve_request_id">
                        
                        <div class="mb-3">
                            <h6 id="approve_employee_name"></h6>
                            <p id="approve_request_details" class="text-muted"></p>
                        </div>
                        
                        <div id="time_off_balance_container" class="mb-3"></div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" rows="3" placeholder="Add any comments or notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Request Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Time Off Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" id="reject_request_id">
                        
                        <div class="mb-3">
                            <h6 id="reject_employee_name"></h6>
                            <p id="reject_request_details" class="text-muted"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="comments" rows="3" required placeholder="Provide a reason for rejecting this request..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <!-- Will be populated by AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers
            flatpickr('.datepicker', {
                dateFormat: 'Y-m-d',
                allowInput: true
            });
            
            // Select all checkbox
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.request-checkbox');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updateBulkActions();
                });
            }
            
            // Individual checkboxes
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    updateBulkActions();
                });
            });
            
            // Update bulk action buttons
            function updateBulkActions() {
                const checkedCheckboxes = document.querySelectorAll('.request-checkbox:checked');
                const bulkBtn = document.getElementById('bulkApproveBtn');
                if (bulkBtn) {
                    bulkBtn.disabled = checkedCheckboxes.length === 0;
                }
                
                // Update select all checkbox if needed
                if (selectAllCheckbox) {
                    const allCheckboxes = document.querySelectorAll('.request-checkbox');
                    selectAllCheckbox.checked = allCheckboxes.length > 0 && 
                                             checkedCheckboxes.length === allCheckboxes.length;
                }
            }
            
            // Approve button click
            const approveButtons = document.querySelectorAll('.approve-btn');
            approveButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const requestId = btn.getAttribute('data-request-id');
                    const employeeName = btn.getAttribute('data-employee-name');
                    const leaveType = btn.getAttribute('data-leave-type');
                    const startDate = btn.getAttribute('data-start-date');
                    const endDate = btn.getAttribute('data-end-date');
                    const employeeId = btn.getAttribute('data-employee-id');
                    
                    // Set values in modal
                    document.getElementById('approve_request_id').value = requestId;
                    document.getElementById('approve_employee_name').textContent = employeeName;
                    document.getElementById('approve_request_details').textContent = 
                        `${ucfirst(leaveType)} leave from ${formatDate(startDate)} to ${formatDate(endDate)}`;
                    
                    // Fetch time off balance
                    fetchTimeOffBalance(employeeId);
                    
                    // Open modal
                    const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
                    approveModal.show();
                });
            });
            
            // Reject button click
            const rejectButtons = document.querySelectorAll('.reject-btn');
            rejectButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const requestId = btn.getAttribute('data-request-id');
                    const employeeName = btn.getAttribute('data-employee-name');
                    const leaveType = btn.getAttribute('data-leave-type');
                    const startDate = btn.getAttribute('data-start-date');
                    const endDate = btn.getAttribute('data-end-date');
                    
                    // Set values in modal
                    document.getElementById('reject_request_id').value = requestId;
                    document.getElementById('reject_employee_name').textContent = employeeName;
                    document.getElementById('reject_request_details').textContent = 
                        `${ucfirst(leaveType)} leave from ${formatDate(startDate)} to ${formatDate(endDate)}`;
                    
                    // Open modal
                    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
                    rejectModal.show();
                });
            });
            
            // View details buttons
            const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
            viewDetailsButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const requestId = btn.getAttribute('data-request-id');
                    const employeeId = btn.getAttribute('data-employee-id');
                    
                    // Fetch request details
                    fetchRequestDetails(requestId, employeeId);
                    
                    // Open modal
                    const detailsModal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
                    detailsModal.show();
                });
            });
            
            // Helper function to capitalize first letter
            function ucfirst(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Helper function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
            }
            
            // Function to fetch time off balance
            function fetchTimeOffBalance(employeeId) {
                // This would typically be an AJAX call to a server endpoint
                // For demo purposes, we'll use a placeholder
                const container = document.getElementById('time_off_balance_container');
                container.innerHTML = `
                    <div class="card time-balance-card">
                        <div class="card-body">
                            <h6 class="card-title">Time Off Balance</h6>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Vacation</small>
                                    <small>10 of 15 days remaining</small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 66%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Sick</small>
                                    <small>8 of 10 days remaining</small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 80%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Personal</small>
                                    <small>3 of 5 days remaining</small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 60%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Function to fetch request details
            function fetchRequestDetails(requestId, employeeId) {
                // This would typically be an AJAX call to a server endpoint
                // For demo purposes, we'll use placeholder data
                const container = document.getElementById('requestDetailsContent');
                
                // Simulate loading
                setTimeout(function() {
                    container.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="130">Request ID:</th>
                                        <td>#${requestId}</td>
                                    </tr>
                                    <tr>
                                        <th>Type:</th>
                                        <td><span class="badge vacation-badge">Vacation</span></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><span class="badge approved-badge">Approved</span></td>
                                    </tr>
                                    <tr>
                                        <th>Duration:</th>
                                        <td>5 business days (7 total days)</td>
                                    </tr>
                                    <tr>
                                        <th>Date Range:</th>
                                        <td>Jun 15, 2023 - Jun 21, 2023</td>
                                    </tr>
                                    <tr>
                                        <th>Created:</th>
                                        <td>May 20, 2023</td>
                                    </tr>
                                    <tr>
                                        <th>Notes:</th>
                                        <td>Family vacation planned months ago.</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Employee Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="130">Name:</th>
                                        <td>John Smith</td>
                                    </tr>
                                    <tr>
                                        <th>Employee ID:</th>
                                        <td>EMP${employeeId}</td>
                                    </tr>
                                    <tr>
                                        <th>Department:</th>
                                        <td>Engineering</td>
                                    </tr>
                                    <tr>
                                        <th>Position:</th>
                                        <td>Senior Developer</td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td>john.smith@example.com</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Time Off History</h6>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Date Range</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge sick-badge">Sick</span></td>
                                        <td>Apr 10, 2023</td>
                                        <td>1 day</td>
                                        <td><span class="badge approved-badge">Approved</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge personal-badge">Personal</span></td>
                                        <td>Mar 17, 2023</td>
                                        <td>1 day</td>
                                        <td><span class="badge approved-badge">Approved</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge vacation-badge">Vacation</span></td>
                                        <td>Dec 24, 2022 - Jan 02, 2023</td>
                                        <td>7 days</td>
                                        <td><span class="badge approved-badge">Approved</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    `;
                }, 500);
            }
            
            // Initialize charts for the Reports tab
          // Initialize charts for the Reports tab
          if (document.getElementById('timeOffByTypeChart')) {
                const typeCtx = document.getElementById('timeOffByTypeChart').getContext('2d');
                new Chart(typeCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Vacation', 'Sick', 'Personal', 'Bereavement', 'Other'],
                        datasets: [{
                            data: [
                                <?php echo $stats['vacation']; ?>,
                                <?php echo $stats['sick']; ?>,
                                <?php echo $stats['personal']; ?>,
                                <?php echo $stats['bereavement']; ?>,
                                <?php echo $stats['other']; ?>
                            ],
                            backgroundColor: ['#64b5f6', '#ef5350', '#9575cd', '#4db6ac', '#ffb74d']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }
            
            // Department chart (sample data - would come from the backend)
            if (document.getElementById('timeOffByDepartmentChart')) {
                const deptCtx = document.getElementById('timeOffByDepartmentChart').getContext('2d');
                new Chart(deptCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Engineering', 'Sales', 'Marketing', 'HR', 'Finance', 'Operations'],
                        datasets: [{
                            label: '# of Time Off Requests',
                            data: [12, 8, 6, 3, 5, 9],
                            backgroundColor: '#6a11cb'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
            
            // Monthly trends chart (sample data - would come from the backend)
            if (document.getElementById('monthlyTrendsChart')) {
                const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
                new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [
                            {
                                label: 'Vacation',
                                data: [5, 3, 4, 6, 8, 12, 15, 14, 9, 7, 5, 8],
                                borderColor: '#64b5f6',
                                backgroundColor: 'rgba(100, 181, 246, 0.2)',
                                fill: true
                            },
                            {
                                label: 'Sick',
                                data: [8, 10, 7, 5, 4, 3, 2, 3, 5, 7, 9, 8],
                                borderColor: '#ef5350',
                                backgroundColor: 'rgba(239, 83, 80, 0.2)',
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
            
            // Calendar functionality
            function generateCalendar(year, month) {
                const today = new Date();
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                
                // Get the day of the week (0-6) for the first day of the month
                const startingDay = firstDay.getDay();
                const totalDays = lastDay.getDate();
                
                // Get calendar body element
                const calendarBody = document.getElementById('calendarBody');
                if (!calendarBody) return;
                
                let html = '';
                let day = 1;
                let dateStr;
                
                // Create calendar rows and cells
                for (let i = 0; i < 6; i++) { // Up to 6 rows
                    html += '<tr>';
                    
                    for (let j = 0; j < 7; j++) { // 7 days in a week
                        if (i === 0 && j < startingDay) {
                            // Empty cells before the first day of the month
                            html += '<td class="other-month"></td>';
                        } else if (day > totalDays) {
                            // Empty cells after the last day of the month
                            html += '<td class="other-month"></td>';
                        } else {
                            // Format the date string (YYYY-MM-DD)
                            dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                            
                            // Check if this is the current day
                            const isCurrentDay = year === today.getFullYear() && 
                                               month === today.getMonth() && 
                                               day === today.getDate();
                            
                            html += `<td class="${isCurrentDay ? 'current-day' : ''}">`;
                            html += `<div class="day-header"><span class="day-number">${day}</span></div>`;
                            
                            // Add events for this day
                            const events = window.calendarData[dateStr] || [];
                            events.forEach(event => {
                                html += `<div class="day-event ${event.type}-badge" title="${event.name}: ${event.type}">${event.name}</div>`;
                            });
                            
                            html += '</td>';
                            day++;
                        }
                    }
                    
                    html += '</tr>';
                    if (day > totalDays) break; // Stop if we've reached the end of the month
                }
                
                calendarBody.innerHTML = html;
                
                // Update month label
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                const currentMonthBtn = document.getElementById('currentMonth');
                if (currentMonthBtn) {
                    currentMonthBtn.textContent = `${monthNames[month]} ${year}`;
                }
            }
            
            // Store calendar data globally
            window.calendarData = <?php echo json_encode($calendar_data); ?>;
            
            // Set up current date for calendar
            let calendarYear = new Date().getFullYear();
            let calendarMonth = new Date().getMonth();
            
            // Generate initial calendar
            generateCalendar(calendarYear, calendarMonth);
            
            // Previous month button
            const prevMonthBtn = document.getElementById('prevMonth');
            if (prevMonthBtn) {
                prevMonthBtn.addEventListener('click', function() {
                    calendarMonth--;
                    if (calendarMonth < 0) {
                        calendarMonth = 11;
                        calendarYear--;
                    }
                    generateCalendar(calendarYear, calendarMonth);
                    
                    // Here you would typically fetch new data for the selected month
                    // via AJAX and update window.calendarData
                });
            }
            
            // Next month button
            const nextMonthBtn = document.getElementById('nextMonth');
            if (nextMonthBtn) {
                nextMonthBtn.addEventListener('click', function() {
                    calendarMonth++;
                    if (calendarMonth > 11) {
                        calendarMonth = 0;
                        calendarYear++;
                    }
                    generateCalendar(calendarYear, calendarMonth);
                    
                    // Here you would typically fetch new data for the selected month
                    // via AJAX and update window.calendarData
                });
            }
            
            // If on current month button, reset to current month
            const currentMonthBtn = document.getElementById('currentMonth');
            if (currentMonthBtn) {
                currentMonthBtn.addEventListener('click', function() {
                    calendarYear = new Date().getFullYear();
                    calendarMonth = new Date().getMonth();
                    generateCalendar(calendarYear, calendarMonth);
                    
                    // Here you would typically fetch new data for the current month
                    // via AJAX and update window.calendarData
                });
            }
        });
    </script>
</body>
</html>

