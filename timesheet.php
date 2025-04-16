<?php
// Include the database connection
require_once('db_connection.php');


// Start the session to get the user ID
session_start();


// Verify user is logged in and HR
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}


$user_id = $_SESSION['id'];


// Fetch the role of the logged-in user
$query_role = "SELECT role FROM users WHERE id = ?";
$stmt_role = $conn->prepare($query_role);
$stmt_role->bind_param('i', $user_id);
$stmt_role->execute();
$result_role = $stmt_role->get_result();
$user_data = $result_role->fetch_assoc();


// Check if user exists and has HR role
if (!$user_data || $user_data['role'] !== 'hr') {
    header('Location: dashboard.php');
    exit();
}


// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $search_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
    $search_date = isset($_GET['date']) ? $_GET['date'] : null;
   
    // Validate date format if provided
    if ($search_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $search_date)) {
        $search_date = null;
    }
   
    // Generate the data for export
    $export_data = getTimesheetData($conn, $search_employee_id, $search_date);
   
    if ($export_type === 'pdf') {
        exportAsPDF($export_data);
    } elseif ($export_type === 'excel') {
        exportAsExcel($export_data);
    }
    exit();
}


// Function to get timesheet data
function getTimesheetData($conn, $employee_id = null, $date = null) {
    $whereClauses = [];
    $params = [];
    $types = '';


    if ($employee_id) {
        $whereClauses[] = "ts.employee_id = ?";
        $params[] = $employee_id;
        $types .= 'i';
    }
    if ($date) {
        $whereClauses[] = "ts.date = ?";
        $params[] = $date;
        $types .= 's';
    }


    $whereSql = '';
    if (count($whereClauses) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }


    $query = "SELECT ts.id, e.first_name, e.last_name, ts.date, ts.clock_in, ts.clock_out,
                     ts.break_duration, ts.total_hours
              FROM timesheets ts
              JOIN employees e ON ts.employee_id = e.id
              $whereSql
              ORDER BY ts.date DESC";


    $stmt = $conn->prepare($query);
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}


// Function to export as PDF
function exportAsPDF($data) {
    require_once('tcpdf/tcpdf.php');
   
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Timesheet Management System');
    $pdf->SetAuthor('Your Company');
    $pdf->SetTitle('Timesheet Report');
    $pdf->SetSubject('Timesheet Data');
    $pdf->SetKeywords('Timesheet, Report, PDF');
   
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'Timesheet Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
   
    // Table header
    $html = '<table border="1" cellpadding="4">
        <tr style="background-color:#f2f2f2;">
            <th><b>Employee</b></th>
            <th><b>Date</b></th>
            <th><b>Clock In</b></th>
            <th><b>Clock Out</b></th>
            <th><b>Break (mins)</b></th>
            <th><b>Total Hours</b></th>
        </tr>';
   
    // Table data
    while ($row = $data->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
            <td>' . htmlspecialchars($row['date']) . '</td>
            <td>' . htmlspecialchars($row['clock_in']) . '</td>
            <td>' . htmlspecialchars($row['clock_out']) . '</td>
            <td>' . htmlspecialchars($row['break_duration']) . '</td>
            <td>' . htmlspecialchars($row['total_hours']) . '</td>
        </tr>';
    }
   
    $html .= '</table>';
   
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Output('timesheet_report_'.date('Ymd').'.pdf', 'D');
}


// Function to export as Excel
function exportAsExcel($data) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="timesheet_report_'.date('Ymd').'.xls"');
    header('Cache-Control: max-age=0');
   
    echo '<table border="1">
        <tr>
            <th>Employee</th>
            <th>Date</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Break (mins)</th>
            <th>Total Hours</th>
        </tr>';
   
    while ($row = $data->fetch_assoc()) {
        echo '<tr>
            <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
            <td>' . htmlspecialchars($row['date']) . '</td>
            <td>' . htmlspecialchars($row['clock_in']) . '</td>
            <td>' . htmlspecialchars($row['clock_out']) . '</td>
            <td>' . htmlspecialchars($row['break_duration']) . '</td>
            <td>' . htmlspecialchars($row['total_hours']) . '</td>
        </tr>';
    }
   
    echo '</table>';
    exit();
}


// Handle timesheet updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timesheet_id'])) {
    $timesheet_id = intval($_POST['timesheet_id']);
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $date = isset($_POST['date']) ? $_POST['date'] : null;
   
    // Status field should exist in the form
    $status = isset($_POST['status']) ? $_POST['status'] : '';
   
    // Update the timesheet record
    $update_query = "UPDATE timesheets SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('si', $status, $timesheet_id);
    $update_stmt->execute();
   
    header('Location: timesheet.php?update=success&employee_id='.$employee_id.'&date='.$date);
    exit();
}


// Fetch timesheet data for display
$search_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
$search_date = isset($_GET['date']) ? $_GET['date'] : null;


// Validate date format if provided
if ($search_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $search_date)) {
    $search_date = null;
}


$result = getTimesheetData($conn, $search_employee_id, $search_date);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Management | HR Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-color: #4e54c8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
       
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
       
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }
       
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
       
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 10px 20px;
            transition: all 0.3s;
        }
       
        .btn-primary:hover {
            background: var(--accent-color);
            transform: translateY(-2px);
        }
       
        .btn-export {
            background: #28a745;
            color: white;
        }
       
        .btn-export:hover {
            background: #218838;
            color: white;
        }
       
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
       
        .table {
            margin-bottom: 0;
        }
       
        .table thead th {
            background-color: var(--accent-color);
            color: white;
            border-bottom: none;
        }
       
        .table tbody tr:hover {
            background-color: rgba(78, 84, 200, 0.1);
        }
       
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
       
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
       
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 84, 200, 0.25);
        }
       
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
       
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 1.5rem;
            opacity: 0.8;
            transition: all 0.3s;
        }
       
        .close-btn:hover {
            opacity: 1;
            transform: scale(1.1);
            color: white;
        }
       
        .action-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
       
        .action-form select {
            flex-grow: 1;
        }
       
        @media (max-width: 768px) {
            .action-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card">
            <div class="card-header position-relative">
                <h2 class="mb-0 text-center"><i class="fas fa-clock me-2"></i>Timesheet Management</h2>
                <a href="dashboard.php" class="close-btn"><i class="fas fa-times"></i></a>
            </div>
           
            <div class="card-body">
                <!-- Display success message if update was successful -->
                <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Timesheet record updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
               
                <!-- Search Form -->
                <div class="search-card">
                    <form action="timesheet.php" method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select name="employee_id" id="employee_id" class="form-select">
                                <option value="">All Employees</option>
                                <?php
                                $employeeQuery = "SELECT id, first_name, last_name FROM employees ORDER BY last_name, first_name";
                                $employees = $conn->query($employeeQuery);
                                while ($employee = $employees->fetch_assoc()) {
                                    $selected = ($search_employee_id == $employee['id']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($employee['id']) . "' $selected>" .
                                         htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                       
                        <div class="col-md-5">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($search_date); ?>">
                        </div>
                       
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
               
                <!-- Export Buttons -->
                <div class="d-flex justify-content-end mb-4 gap-2">
                    <a href="?export=pdf&employee_id=<?php echo urlencode($search_employee_id); ?>&date=<?php echo urlencode($search_date); ?>"
                       class="btn btn-export">
                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                    </a>
                    <a href="?export=excel&employee_id=<?php echo urlencode($search_employee_id); ?>&date=<?php echo urlencode($search_date); ?>"
                       class="btn btn-export">
                        <i class="fas fa-file-excel me-2"></i>Export as Excel
                    </a>
                </div>
               
                <!-- Timesheet Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Break (mins)</th>
                                <th>Total Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['clock_in']); ?></td>
                                        <td><?php echo htmlspecialchars($row['clock_out']); ?></td>
                                        <td><?php echo htmlspecialchars($row['break_duration']); ?></td>
                                        <td><?php echo htmlspecialchars($row['total_hours']); ?></td>
                                        <td>
                                            <form action="timesheet.php" method="POST" class="action-form">
                                                <input type="hidden" name="timesheet_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="employee_id" value="<?php echo $search_employee_id; ?>">
                                                <input type="hidden" name="date" value="<?php echo $search_date; ?>">
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <option value="">Select Status</option>
                                                    <option value="approved">Approved</option>
                                                    <option value="rejected">Rejected</option>
                                                    <option value="pending">Pending Review</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Update Status">
                                                    <i class="fas fa-save"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No timesheet records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
           
            // Set today's date as default if no date is selected
            const dateInput = document.getElementById('date');
            if (!dateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.value = today;
            }
           
            // Auto-dismiss alerts after 5 seconds
            var alertList = document.querySelectorAll('.alert');
            alertList.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>

