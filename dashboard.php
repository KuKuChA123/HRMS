<?php
// Start the session
session_start();

// Database connection
$host = 'localhost';
$dbname = 'hrms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Check if the user is logged in and has a valid session
if (!isset($_SESSION['id'])) {
    // Redirect to login page if session ID is not set
    header('Location: login.php');
    exit();
}

// Get the current user ID from the session
$currentUserId = $_SESSION['id'];

// Fetch user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$currentUserId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch employee data
$employeeStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$employeeStmt->execute([$currentUserId]);
$currentEmployee = $employeeStmt->fetch(PDO::FETCH_ASSOC);


// Fetch announcements
$announcementsStmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1");
$latestAnnouncement = $announcementsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch employee count
$employeeCountStmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
$employeeCount = $employeeCountStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Fetch payroll status
$payrollStmt = $pdo->query("SELECT COUNT(*) as processed FROM payroll WHERE status = 'processed'");
$payrollProcessed = $payrollStmt->fetch(PDO::FETCH_ASSOC)['processed'];

// Fetch time off data for current user
$timeOffStmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN leave_type = 'vacation' AND status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as vacation_days,
        SUM(CASE WHEN leave_type = 'sick' AND status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as sick_days,
        SUM(CASE WHEN leave_type = 'personal' AND status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as personal_days
    FROM time_off_requests 
    WHERE employee_id = ? AND YEAR(start_date) = YEAR(CURDATE())
");
$timeOffStmt->execute([$currentEmployee['id']]);
$timeOffData = $timeOffStmt->fetch(PDO::FETCH_ASSOC);

// Fetch today's interviews
$today = date('Y-m-d');
$interviewsStmt = $pdo->prepare("
    SELECT i.*, e.first_name as interviewer_first, e.last_name as interviewer_last 
    FROM interviews i
    LEFT JOIN employees e ON i.interviewer_id = e.id
    WHERE interview_date = ?
    ORDER BY interview_time
");
$interviewsStmt->execute([$today]);
$todaysInterviews = $interviewsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch calendar events for current month
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');
$eventsStmt = $pdo->prepare("
    SELECT * FROM calendar_events 
    WHERE event_date BETWEEN ? AND ? 
    ORDER BY event_date, start_time
");
$eventsStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
$monthEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle time off request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_time_off'])) {
    $leaveType = $_POST['leave_type'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $notes = $_POST['notes'];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO time_off_requests 
        (employee_id, leave_type, start_date, end_date, notes, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    
    if ($insertStmt->execute([$currentEmployee['id'], $leaveType, $startDate, $endDate, $notes])) {
        $requestSuccess = "Time off request submitted successfully!";
    } else {
        $requestError = "Failed to submit time off request.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-users logo-icon"></i>
                <h1 class="logo-text">HRPro</h1>
            </div>
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span class="menu-text">Home</span>
            </a>
            <a href="personal.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span class="menu-text">Personal</span>
            </a>
            <a href="timesheet.php" class="menu-item">
                <i class="fas fa-clock"></i>
                <span class="menu-text">Timesheet</span>
            </a>
            <a href="timeoff.php" class="menu-item">
                <i class="fas fa-calendar-minus"></i>
                <span class="menu-text">Time Off</span>
            </a>
            <a href="emergency.php" class="menu-item">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Emergency</span>
            </a>
            <a href="performance.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span class="menu-text">Performance</span>
            </a>
            <a href="professionalpath.php" class="menu-item">
                <i class="fas fa-briefcase"></i>
                <span class="menu-text">Professional Path</span>
            </a>
            <a href="inbox.php" class="menu-item">
                <i class="fas fa-inbox"></i>
                <span class="menu-text">Inbox</span>
            </a>
            <a href="addEmployees.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span class="menu-text">Add Employee</span>
            </a>
            <a href="login.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>

        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <h1>HR Dashboard</h1>
                <p>Welcome back to your workspace</p>
            </div>
            <div class="header-info">
            <div class="current-time" id="currentTime">
    <?php 
    // Set timezone to Philippine Standard Time (PHT)
    date_default_timezone_set('Asia/Manila');
    echo date('l, F j, Y g:i A'); 
    ?>
</div>

                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $initials = '';
                        if (!empty($currentEmployee['first_name'])) {
                            $initials .= substr($currentEmployee['first_name'], 0, 1);
                        }
                        if (!empty($currentEmployee['last_name'])) {
                            $initials .= substr($currentEmployee['last_name'], 0, 1);
                        }
                        echo $initials ?: 'UK';
                        ?>
                    </div>
                    <span>
    <?php 
        echo htmlspecialchars(
            (!empty($currentEmployee['username']) ? 
            $currentEmployee['username'] . ' - ' . ucfirst($currentUser['role']) : 
            'Unknown User')
        )
    ?>
</span>

                </div>
            </div>
        </header>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Announcement Banner -->
            <section class="announcement-banner">
                <i class="fas fa-bullhorn announcement-icon"></i>
                <div class="announcement-text">
                    <h3>Today's Announcements</h3>
                    <p><?php echo !empty($latestAnnouncement['message']) ? htmlspecialchars($latestAnnouncement['message']) : 'No announcements today'; ?></p>
                </div>
                <i class="fas fa-chevron-right"></i>
            </section>

            <!-- Stats Cards -->
<section class="stats-card">
    <div class="stats-header">
        <span class="stats-title">Payroll</span>
        <div class="stats-icon payroll">
            <i class="fas fa-money-bill-wave"></i>
        </div>
    </div>
    <div class="stats-value" id="payroll-days"></div>
    <div class="stats-change">
        <i class="fas fa-clock"></i> <span id="payroll-remaining"></span>
    </div>
</section>

<script>
    function getNextPayrollDate() {
        const today = new Date();
        const day = today.getDate();
        const month = today.getMonth();
        const year = today.getFullYear();

        let nextPayroll;

        if (day < 15) {
            nextPayroll = new Date(year, month, 15);
        } else if (day < 30) {
            nextPayroll = new Date(year, month, 30);
        } else {
            // If it's past the 30th, move to the 15th of the next month
            nextPayroll = new Date(year, month + 1, 15);
        }

        const timeDiff = nextPayroll - today;
        const daysRemaining = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));

        return {
            nextPayroll,
            daysRemaining
        };
    }

    const payrollInfo = getNextPayrollDate();
    document.getElementById("payroll-days").innerText = "Payroll: " +
        payrollInfo.nextPayroll.toLocaleDateString(undefined, { month: 'long', day: 'numeric' });
    document.getElementById("payroll-remaining").innerText = `${payrollInfo.daysRemaining} day${payrollInfo.daysRemaining !== 1 ? 's' : ''} left`;
</script>


            <section class="stats-card">
                <div class="stats-header">
                    <span class="stats-title">Total Employees</span>
                    <div class="stats-icon employees">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stats-value">
                    <?php echo $employeeCount; ?> 
                    <span style="font-size: 16px; color: var(--success);">+2</span>
                </div>
                <div class="stats-change">
                    <i class="fas fa-arrow-up"></i> 2 new hires
                </div>
            </section>

            <!-- Stats Cards -->
<section class="stats-card">
    <div class="stats-header">
        <span class="stats-title">Working Days</span>
        <div class="stats-icon days">
            <i class="fas fa-calendar-alt"></i>
        </div>
    </div>
    <div class="stats-value" id="working-days"></div>
    <div class="stats-change negative">
        <i class="fas fa-arrow-down"></i> <span id="holiday-count"></span>
    </div>
</section>


<script>
const philippineHolidays = [
{ date: "2025-01-01", name: "New Year's Day", description: "Regular holiday celebrating the start of the new year" },
{ date: "2025-01-25", name: "Chinese New Year", description: "Special non-working holiday celebrating the Chinese New Year" },
{ date: "2025-02-25", name: "EDSA People Power Revolution", description: "Special non-working holiday commemorating the 1986 EDSA Revolution" },
{ date: "2025-04-04", name: "Good Friday", description: "Regular holiday marking the crucifixion of Jesus Christ" },
{ date: "2025-04-05", name: "Black Saturday", description: "Special non-working holiday before Easter Sunday" },
{ date: "2025-04-09", name: "Araw ng Kagitingan", description: "Regular holiday commemorating the Fall of Bataan" },
{ date: "2025-05-01", name: "Labor Day", description: "Regular holiday celebrating workers' contributions" },
{ date: "2025-06-12", name: "Independence Day", description: "Regular holiday celebrating Philippine independence" },
{ date: "2025-06-24", name: "Manila Day", description: "Special non-working holiday in Manila" },
{ date: "2025-08-21", name: "Ninoy Aquino Day", description: "Special non-working holiday commemorating Benigno Aquino Jr.'s assassination" },
{ date: "2025-08-26", name: "National Heroes Day", description: "Regular holiday honoring all Philippine national heroes" },
{ date: "2025-11-01", name: "All Saints' Day", description: "Special non-working holiday honoring saints" },
{ date: "2025-11-02", name: "All Souls' Day", description: "Special non-working holiday for remembering the dead" },
{ date: "2025-11-30", name: "Bonifacio Day", description: "Regular holiday honoring Andrés Bonifacio" },
{ date: "2025-12-08", name: "Feast of the Immaculate Conception", description: "Special non-working holiday" },
{ date: "2025-12-25", name: "Christmas Day", description: "Regular holiday celebrating the birth of Jesus Christ" },
{ date: "2025-12-30", name: "Rizal Day", description: "Regular holiday commemorating José Rizal's martyrdom" },
{ date: "2025-12-31", name: "New Year's Eve", description: "Special non-working holiday before the new year" }
];

const phHolidays = <?php echo json_encode($philippineHolidays); ?>;

let currentDate = new Date();

// Update the calendar generation to highlight holidays
function updateCalendar() {
    const calendarDays = document.getElementById('calendarDays');
    const monthYearDisplay = document.getElementById('currentMonthYear');
    
    // Set month and year display
    monthYearDisplay.textContent = currentDate.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric'
    });
    
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Get first day of month and total days in month
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Get days from previous month
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    
    // Clear calendar
    calendarDays.innerHTML = '';
    
    // Previous month days
    for (let i = firstDay - 1; i >= 0; i--) {
        const dayElement = document.createElement('div');
        dayElement.classList.add('calendar-day', 'other-month');
        dayElement.textContent = daysInPrevMonth - i;
        calendarDays.appendChild(dayElement);
    }
    
    // Current month days
    const today = new Date();
    for (let i = 1; i <= daysInMonth; i++) {
        const dayElement = document.createElement('div');
        dayElement.classList.add('calendar-day');
        
        const currentDateObj = new Date(year, month, i);
        const dateStr = currentDateObj.toISOString().split('T')[0];
        
        // Highlight today
        if (i === today.getDate() && 
            month === today.getMonth() && 
            year === today.getFullYear()) {
            dayElement.classList.add('today');
        }
        
        // Highlight holidays
        const holidayName = isHoliday(currentDateObj);
        if (holidayName) {
            dayElement.classList.add('holiday');
            
            // Add tooltip with holiday name
            dayElement.setAttribute('data-tooltip', holidayName);
            
            // Add holiday indicator
            const holidayIndicator = document.createElement('div');
            holidayIndicator.style.width = '6px';
            holidayIndicator.style.height = '6px';
            holidayIndicator.style.backgroundColor = 'var(--danger)';
            holidayIndicator.style.borderRadius = '50%';
            holidayIndicator.style.position = 'absolute';
            holidayIndicator.style.bottom = '5px';
            holidayIndicator.style.left = '50%';
            holidayIndicator.style.transform = 'translateX(-50%)';
            dayElement.style.position = 'relative';
            dayElement.appendChild(holidayIndicator);
        }
        
        dayElement.textContent = i;
        calendarDays.appendChild(dayElement);
    }
    
    // Next month days
    const totalCells = firstDay + daysInMonth;
    const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
    
    for (let i = 1; i <= remainingCells; i++) {
        const dayElement = document.createElement('div');
        dayElement.classList.add('calendar-day', 'other-month');
        dayElement.textContent = i;
        calendarDays.appendChild(dayElement);
    }
}
</script>


            <section class="stats-card">
                <div class="stats-header">
                    <span class="stats-title">Payroll Processed</span>
                    <div class="stats-icon processed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stats-value"><?php echo $payrollProcessed; ?>/<?php echo $employeeCount; ?></div>
                <div class="stats-change">
                    <i class="fas fa-arrow-up"></i> 
                    <?php echo $employeeCount > 0 ? round(($payrollProcessed / $employeeCount) * 100) : 0; ?>% complete
                </div>
            </section>

            <!-- Calendar Section -->
            <section class="calendar-section">
                <div class="section-header">
                    <h2 class="section-title">Calendar</h2>
                </div>
                
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <button class="calendar-nav-btn" id="prevMonth">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="calendar-nav-btn" id="nextMonth">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="calendar-month" id="currentMonthYear"><?php echo date('F Y'); ?></div>
                </div>
                
                <div class="calendar-weekdays">
                    <div>S</div>
                    <div>M</div>
                    <div>T</div>
                    <div>W</div>
                    <div>T</div>
                    <div>F</div>
                    <div>S</div>
                </div>
                
                <div class="calendar-days" id="calendarDays">
                    <!-- Calendar days will be inserted by JavaScript -->
                </div>
                
                <div class="time-details">
                    <div class="time-detail">
                        <span class="time-label">Start Time</span>
                        <span class="time-value">9:00 AM</span>
                    </div>
                    <div class="time-detail">
                        <span class="time-label">End Time</span>
                        <span class="time-value">6:00 PM</span>
                    </div>
                    <div class="time-detail">
                        <span class="time-label">Break Time</span>
                        <span class="time-value">60 min</span>
                    </div>
                </div>
            </section>

            <script>
    // Calendar functionality
    let currentDate = new Date();
    
    function updateCalendar() {
        const calendarDays = document.getElementById('calendarDays');
        const monthYearDisplay = document.getElementById('currentMonthYear');
        
        // Set month and year display
        monthYearDisplay.textContent = currentDate.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric'
        });
        
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Get first day of month and total days in month
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        // Get days from previous month
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Clear calendar
        calendarDays.innerHTML = '';
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayElement = document.createElement('div');
            dayElement.classList.add('calendar-day', 'other-month');
            dayElement.textContent = daysInPrevMonth - i;
            calendarDays.appendChild(dayElement);
        }
        
        // Current month days
        const today = new Date();
        for (let i = 1; i <= daysInMonth; i++) {
            const dayElement = document.createElement('div');
            dayElement.classList.add('calendar-day');
            
            // Highlight today if it's the current month/year
            if (i === today.getDate() && 
                month === today.getMonth() && 
                year === today.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            // Add event indicators if there are events on this day
            const eventDate = new Date(year, month, i).toISOString().split('T')[0];
            if (hasEventOnDate(eventDate)) {
                const eventDot = document.createElement('div');
                eventDot.style.width = '6px';
                eventDot.style.height = '6px';
                eventDot.style.backgroundColor = 'var(--primary)';
                eventDot.style.borderRadius = '50%';
                eventDot.style.position = 'absolute';
                eventDot.style.bottom = '5px';
                eventDot.style.left = '50%';
                eventDot.style.transform = 'translateX(-50%)';
                dayElement.style.position = 'relative';
                dayElement.appendChild(eventDot);
            }
            
            dayElement.textContent = i;
            calendarDays.appendChild(dayElement);
        }
        
        // Next month days
        const totalCells = firstDay + daysInMonth;
        const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
        
        for (let i = 1; i <= remainingCells; i++) {
            const dayElement = document.createElement('div');
            dayElement.classList.add('calendar-day', 'other-month');
            dayElement.textContent = i;
            calendarDays.appendChild(dayElement);
        }
    }
    
    // Check if there are events on a specific date (mock function)
    function hasEventOnDate(date) {
        // This would normally check your events data
        // For now, we'll just randomly show some dots for demonstration
        return Math.random() > 0.8; // 20% chance of an event
    }
    
    // Navigation buttons
    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        updateCalendar();
    });
    
    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        updateCalendar();
    });
    
    // Initialize calendar
    updateCalendar();
</script>

            <!-- Time Off Section -->
            <section class="timeoff-section">
  <div class="section-header">
    <h2 class="section-title">Time Off</h2>
  </div>
  <div class="timeoff-progress">
    <div class="timeoff-type">
      <span class="timeoff-name">Vacation</span>
      <span class="timeoff-days"><?php echo $timeOffData['vacation_days'] ?? 0; ?> days YTD</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: <?php echo min(($timeOffData['vacation_days'] ?? 0) * 10, 100); ?>%;"></div>
    </div>
    <div class="timeoff-type">
      <span class="timeoff-name">Sick Leave</span>
      <span class="timeoff-days"><?php echo $timeOffData['sick_days'] ?? 0; ?> days YTD</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: <?php echo min(($timeOffData['sick_days'] ?? 0) * 20, 100); ?>%;"></div>
    </div>
    <div class="timeoff-type">
      <span class="timeoff-name">Personal Days</span>
      <span class="timeoff-days"><?php echo $timeOffData['personal_days'] ?? 0; ?> days YTD</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: <?php echo min(($timeOffData['personal_days'] ?? 0) * 20, 100); ?>%;"></div>
    </div>
    <div class="timeoff-type">
      <span class="timeoff-name">Bereavement</span>
      <span class="timeoff-days"><?php echo $timeOffData['bereavement_days'] ?? 0; ?> days YTD</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: <?php echo min(($timeOffData['bereavement_days'] ?? 0) * 20, 100); ?>%;"></div>
    </div>
  </div>
  
  <!-- Time Off Request Form with Flatpickr -->
  <div class="timeoff-request-form" id="timeOffRequestForm" style="display: none;">
    <h3>Request Time Off</h3>
    <form id="requestForm">
      <div class="form-group">
        <label for="timeoffType">Time Off Type</label>
        <select id="timeoffType" class="form-control" required>
          <option value="">Select type</option>
          <option value="vacation">Vacation</option>
          <option value="sick">Sick Leave</option>
          <option value="personal">Personal Day</option>
          <option value="bereavement">Bereavement</option>
        </select>
      </div>
      <div class="form-group">
        <label for="startDate">Start Date</label>
        <input type="text" id="startDate" class="form-control flatpickr-input" placeholder="Select start date" required>
      </div>
      <div class="form-group">
        <label for="endDate">End Date</label>
        <input type="text" id="endDate" class="form-control flatpickr-input" placeholder="Select end date" required>
      </div>
      <div class="form-group">
        <label for="comments">Comments</label>
        <textarea id="comments" class="form-control" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Submit Request</button>
        <button type="button" class="btn btn-outline" id="cancelRequest">Cancel</button>
      </div>
    </form>
  </div>
  
  <div class="timeoff-actions">
    <button class="btn btn-primary" id="requestTimeOffBtn">
      <i class="fas fa-paper-plane"></i> Request Time Off
    </button>
    <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'hr'): ?>
    <button class="btn btn-outline" id="approveTimeOffBtn">
      <i class="fas fa-check"></i> Approve Time Off
    </button>
    <?php endif; ?>
  </div>
</section>

<!-- Include Flatpickr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<!-- Include Flatpickr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Flatpickr
  const startDatePicker = flatpickr("#startDate", {
    dateFormat: "Y-m-d",
    minDate: "today",
    onChange: function(selectedDates) {
      // Update the minimum date of the end date picker when start date is selected
      endDatePicker.set('minDate', selectedDates[0]);
    }
  });
  
  const endDatePicker = flatpickr("#endDate", {
    dateFormat: "Y-m-d",
    minDate: "today"
  });
  
  // Show/hide the request form
  const requestBtn = document.getElementById('requestTimeOffBtn');
  const requestForm = document.getElementById('timeOffRequestForm');
  const cancelBtn = document.getElementById('cancelRequest');
  
  requestBtn.addEventListener('click', function() {
    requestForm.style.display = 'block';
    requestBtn.style.display = 'none';
  });
  
  cancelBtn.addEventListener('click', function() {
    requestForm.style.display = 'none';
    requestBtn.style.display = 'inline-block';
  });
  
  // Form submission
  document.getElementById('requestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Add your form submission code here
    // You can collect the form data and send it to your server
    
    // For demonstration purposes, just hide form and show button
    requestForm.style.display = 'none';
    requestBtn.style.display = 'inline-block';
    
    // You might want to show a success message
    alert('Time off request submitted successfully!');
  });
});
</script>

            <!-- Interviews Section -->
            <section class="interviews-section">
                <div class="section-header">
                    <h2 class="section-title">Interviews</h2>
                </div>
                
                <div class="tabs">
                    <div class="tab active">Today</div>
                    <div class="tab">Upcoming</div>
                    <div class="tab">Completed</div>
                </div>
                
                <div class="interview-list">
                    <?php if (!empty($todaysInterviews)): ?>
                        <?php foreach ($todaysInterviews as $interview): ?>
                            <div class="interview-item">
                                <div class="interview-time"><?php echo date('g:i A', strtotime($interview['interview_time'])); ?></div>
                                <div class="interview-details">
                                    <div class="interview-name"><?php echo htmlspecialchars($interview['candidate_name']); ?></div>
                                    <div class="interview-position"><?php echo htmlspecialchars($interview['position']); ?></div>
                                </div>
                                <div class="interview-status"><?php echo ucfirst($interview['status']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="interview-item" style="justify-content: center; color: var(--gray);">
                            No interviews scheduled for today
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Time Off Request Modal -->
    <div class="modal" id="timeOffRequestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Request Time Off</h3>
                <button class="modal-close" id="closeRequestModal">&times;</button>
            </div>
            <form id="timeOffRequestForm" method="POST">
                <?php if (isset($requestSuccess)): ?>
                    <div class="alert alert-success"><?php echo $requestSuccess; ?></div>
                <?php elseif (isset($requestError)): ?>
                    <div class="alert alert-error"><?php echo $requestError; ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="leaveType">Leave Type</label>
                    <select class="form-control" id="leaveType" name="leave_type" required>
                        <option value="">Select leave type</option>
                        <option value="vacation">Vacation</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal Day</option>
                        <option value="bereavement">Bereavement</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="startDate">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="endDate">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="end_date" required>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelRequest">Cancel</button>
                    <button type="submit" name="submit_time_off" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Time Off Approval Modal -->
    <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'hr'): ?>
    <div class="modal" id="timeOffApproveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Approve Time Off Requests</h3>
                <button class="modal-close" id="closeApproveModal">&times;</button>
            </div>
            
            <div class="form-group">
                <label>Pending Requests</label>
                <div class="pending-requests">
                    <?php 
                    // Fetch pending time off requests
                    $pendingRequestsStmt = $pdo->query("
                        SELECT t.*, e.first_name, e.last_name 
                        FROM time_off_requests t
                        JOIN employees e ON t.employee_id = e.id
                        WHERE t.status = 'pending'
                        ORDER BY t.created_at DESC
                    ");
                    $pendingRequests = $pendingRequestsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($pendingRequests)): ?>
                        <?php foreach ($pendingRequests as $request): ?>
                            <div class="request-item">
                                <div class="request-header">
                                    <span class="request-name"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></span>
                                    <span class="request-days"><?php echo (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1; ?> days</span>
                                </div>
                                <div class="request-dates">
                                    <?php echo date('M j', strtotime($request['start_date'])) . ' - ' . date('M j, Y', strtotime($request['end_date'])); ?>
                                </div>
                                <div class="request-type"><?php echo ucfirst($request['leave_type']); ?></div>
                                <div class="request-actions">
                                    <button class="btn btn-outline btn-sm">View Details</button>
                                    <div class="approve-reject">
                                        <button class="btn btn-success btn-sm">Approve</button>
                                        <button class="btn btn-error btn-sm">Reject</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--gray); padding: 20px;">
                            No pending time off requests
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelApprove">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.querySelector('.main-content');
        
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            sidebarToggle.querySelector('i').classList.toggle('fa-chevron-left');
            sidebarToggle.querySelector('i').classList.toggle('fa-chevron-right');
        });

        // Mobile sidebar toggle
        function handleMobileView() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                sidebar.style.transform = 'translateX(-100%)';
            } else {
                sidebar.style.transform = 'translateX(0)';
            }
        }

        window.addEventListener('resize', handleMobileView);
        handleMobileView();

        // Update current time
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
        }

        setInterval(updateTime, 60000); // Update every minute

        // Generate calendar days
        function generateCalendar() {
            const calendarDays = document.getElementById('calendarDays');
            const date = new Date();
            const year = date.getFullYear();
            const month = date.getMonth();
            
            // Get first day of month and total days in month
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Get days from previous month
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            
            // Clear calendar
            calendarDays.innerHTML = '';
            
            // Previous month days
            for (let i = firstDay - 1; i >= 0; i--) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day', 'other-month');
                dayElement.textContent = daysInPrevMonth - i;
                calendarDays.appendChild(dayElement);
            }
            
            // Current month days
            const today = new Date();
            for (let i = 1; i <= daysInMonth; i++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day');
                if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    dayElement.classList.add('today');
                }
                dayElement.textContent = i;
                calendarDays.appendChild(dayElement);
            }
            
            // Next month days
            const totalCells = firstDay + daysInMonth;
            const remainingCells = totalCells > 35 ? 42 - totalCells : 35 - totalCells;
            
            for (let i = 1; i <= remainingCells; i++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day', 'other-month');
                dayElement.textContent = i;
                calendarDays.appendChild(dayElement);
            }
        }

        generateCalendar();

        // Modal Handling
        const requestModal = document.getElementById('timeOffRequestModal');
        const approveModal = document.getElementById('timeOffApproveModal');
        const requestBtn = document.getElementById('requestTimeOffBtn');
        const approveBtn = document.getElementById('approveTimeOffBtn');
        const closeRequest = document.getElementById('closeRequestModal');
        const closeApprove = document.getElementById('closeApproveModal');
        const cancelRequest = document.getElementById('cancelRequest');
        const cancelApprove = document.getElementById('cancelApprove');

        if (requestBtn) {
            requestBtn.addEventListener('click', () => {
                requestModal.classList.add('active');
            });
        }

        if (approveBtn) {
            approveBtn.addEventListener('click', () => {
                approveModal.classList.add('active');
            });
        }

        if (closeRequest) {
            closeRequest.addEventListener('click', () => {
                requestModal.classList.remove('active');
            });
        }

        if (closeApprove) {
            closeApprove.addEventListener('click', () => {
                approveModal.classList.remove('active');
            });
        }

        if (cancelRequest) {
            cancelRequest.addEventListener('click', () => {
                requestModal.classList.remove('active');
            });
        }

        if (cancelApprove) {
            cancelApprove.addEventListener('click', () => {
                approveModal.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === requestModal) {
                requestModal.classList.remove('active');
            }
            if (e.target === approveModal) {
                approveModal.classList.remove('active');
            }
        });

        // Form submission
        document.getElementById('timeOffRequestForm')?.addEventListener('submit', (e) => {
            // Form is already handled by PHP
        });
    </script>
</body>
</html>
