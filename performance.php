<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";


$conn = new mysqli($servername, $username, $password, $dbname);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Initialize variables
$successMessage = "";
$errorMessage = "";


// Handle form submission for adding or updating performance ratings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_rating"])) {
    $employee_id = $_POST["employee_id"];
    $rating = $_POST["rating"];
    $comments = $_POST["comments"];
    $review_date = date("Y-m-d"); // Current date
   
    // Check if this is an update or a new rating
    $check_sql = "SELECT id FROM performance WHERE employee_id = ? AND DATE(review_date) = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $employee_id, $review_date);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
   
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $performance_id = $row["id"];
       
        $update_sql = "UPDATE performance SET rating = ?, comments = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("isi", $rating, $comments, $performance_id);
       
        if ($update_stmt->execute()) {
            $successMessage = "Performance rating updated successfully!";
        } else {
            $errorMessage = "Error updating performance rating: " . $conn->error;
        }
    } else {
        // Insert new record
        $insert_sql = "INSERT INTO performance (employee_id, review_date, rating, comments) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isis", $employee_id, $review_date, $rating, $comments);
       
        if ($insert_stmt->execute()) {
            $successMessage = "Performance rating added successfully!";
        } else {
            $errorMessage = "Error adding performance rating: " . $conn->error;
        }
    }
}


// Get employee data with their most recent performance rating
$sql = "SELECT e.id, e.first_name, e.last_name, e.email, e.department, e.position,
        (SELECT rating FROM performance WHERE employee_id = e.id ORDER BY review_date DESC LIMIT 1) as latest_rating,
        (SELECT comments FROM performance WHERE employee_id = e.id ORDER BY review_date DESC LIMIT 1) as latest_comments,
        (SELECT review_date FROM performance WHERE employee_id = e.id ORDER BY review_date DESC LIMIT 1) as latest_review_date
        FROM employees e
        ORDER BY e.department, e.last_name, e.first_name";


$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rating-stars input {
            display: none;
        }
        .rating-stars label {
            cursor: pointer;
            font-size: 25px;
            color: #ddd;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #ffc107;
        }
        .employee-card {
            transition: all 0.3s;
            border-left: 5px solid transparent;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .department-section {
            margin-bottom: 30px;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .performance-high {
            border-left-color: #28a745;
        }
        .performance-medium {
            border-left-color: #ffc107;
        }
        .performance-low {
            border-left-color: #dc3545;
        }
        .performance-none {
            border-left-color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">HR Performance Management</a>
        </div>
    </nav>


    <div class="container mt-4">
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
       
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Employee Performance Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="filter-section mb-3">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name, department, position...">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select id="departmentFilter" class="form-select">
                                        <option value="">All Departments</option>
                                        <?php
                                        $dept_sql = "SELECT DISTINCT department FROM employees ORDER BY department";
                                        $dept_result = $conn->query($dept_sql);
                                        while ($dept_row = $dept_result->fetch_assoc()) {
                                            echo "<option value='" . $dept_row["department"] . "'>" . $dept_row["department"] . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select id="ratingFilter" class="form-select">
                                        <option value="">All Ratings</option>
                                        <option value="5">★★★★★ (5)</option>
                                        <option value="4">★★★★☆ (4)</option>
                                        <option value="3">★★★☆☆ (3)</option>
                                        <option value="2">★★☆☆☆ (2)</option>
                                        <option value="1">★☆☆☆☆ (1)</option>
                                        <option value="0">Not Rated</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button id="resetFilters" class="btn btn-secondary w-100">Reset</button>
                                </div>
                            </div>
                        </div>


                        <div class="row" id="employeeContainer">
                            <?php
                            $current_department = "";
                           
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $performance_class = "performance-none";
                                    if ($row["latest_rating"] >= 4) {
                                        $performance_class = "performance-high";
                                    } elseif ($row["latest_rating"] >= 3) {
                                        $performance_class = "performance-medium";
                                    } elseif ($row["latest_rating"] > 0) {
                                        $performance_class = "performance-low";
                                    }
                                   
                                    // Check if we're starting a new department
                                    if ($row["department"] != $current_department) {
                                        $current_department = $row["department"];
                                        echo '<div class="col-12 department-section" data-department="' . $current_department . '">
                                                <h4 class="text-primary mt-4 mb-3">' . $current_department . ' Department</h4>
                                            </div>';
                                    }
                            ?>
                                <div class="col-md-6 col-lg-4 mb-4 employee-item"
                                     data-name="<?php echo strtolower($row["first_name"] . " " . $row["last_name"]); ?>"
                                     data-department="<?php echo strtolower($row["department"]); ?>"
                                     data-position="<?php echo strtolower($row["position"]); ?>"
                                     data-rating="<?php echo $row["latest_rating"] ?: 0; ?>">
                                    <div class="card employee-card h-100 <?php echo $performance_class; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $row["first_name"] . " " . $row["last_name"]; ?></h5>
                                            <p class="card-text">
                                                <small class="text-muted"><?php echo $row["position"]; ?></small><br>
                                                <small class="text-muted"><?php echo $row["email"]; ?></small>
                                            </p>
                                           
                                            <div class="mb-3">
                                                <strong>Current Rating:</strong>
                                                <div class="d-inline-block">
                                                    <?php
                                                    $rating = $row["latest_rating"] ?: 0;
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star text-warning"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star text-muted"></i>';
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($row["latest_review_date"]): ?>
                                                        <small class="text-muted ms-2">(<?php echo date("M d, Y", strtotime($row["latest_review_date"])); ?>)</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                           
                                            <button type="button" class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rateEmployeeModal"
                                                    data-employee-id="<?php echo $row["id"]; ?>"
                                                    data-employee-name="<?php echo $row["first_name"] . " " . $row["last_name"]; ?>"
                                                    data-current-rating="<?php echo $rating; ?>"
                                                    data-current-comments="<?php echo htmlspecialchars($row["latest_comments"] ?: ""); ?>">
                                                Rate Performance
                                            </button>
                                           
                                            <button type="button" class="btn btn-outline-secondary btn-sm view-history"
                                                    data-employee-id="<?php echo $row["id"]; ?>"
                                                    data-employee-name="<?php echo $row["first_name"] . " " . $row["last_name"]; ?>">
                                                View History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                }
                            } else {
                                echo '<div class="col-12"><p>No employees found.</p></div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Rate Employee Modal -->
    <div class="modal fade" id="rateEmployeeModal" tabindex="-1" aria-labelledby="rateEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rateEmployeeModalLabel">Rate Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="employee_id">
                       
                        <div class="mb-3">
                            <label class="form-label">Employee:</label>
                            <h5 id="employee_name_display"></h5>
                        </div>
                       
                        <div class="mb-3">
                            <label class="form-label">Rating:</label>
                            <div class="rating-stars">
                                <input type="radio" id="star5" name="rating" value="5" />
                                <label for="star5" class="fas fa-star"></label>
                                <input type="radio" id="star4" name="rating" value="4" />
                                <label for="star4" class="fas fa-star"></label>
                                <input type="radio" id="star3" name="rating" value="3" />
                                <label for="star3" class="fas fa-star"></label>
                                <input type="radio" id="star2" name="rating" value="2" />
                                <label for="star2" class="fas fa-star"></label>
                                <input type="radio" id="star1" name="rating" value="1" />
                                <label for="star1" class="fas fa-star"></label>
                            </div>
                        </div>
                       
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments:</label>
                            <textarea class="form-control" id="comments" name="comments" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_rating" class="btn btn-primary">Submit Rating</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
   
    <!-- Performance History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">Performance History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Rating modal handling
            const rateEmployeeModal = document.getElementById('rateEmployeeModal');
            if (rateEmployeeModal) {
                rateEmployeeModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const employeeId = button.getAttribute('data-employee-id');
                    const employeeName = button.getAttribute('data-employee-name');
                    const currentRating = button.getAttribute('data-current-rating');
                    const currentComments = button.getAttribute('data-current-comments');
                   
                    document.getElementById('employee_id').value = employeeId;
                    document.getElementById('employee_name_display').textContent = employeeName;
                    document.getElementById('comments').value = currentComments;
                   
                    // Set current rating
                    if (currentRating > 0) {
                        document.getElementById('star' + currentRating).checked = true;
                    }
                });
            }


            // View performance history
            document.querySelectorAll('.view-history').forEach(button => {
                button.addEventListener('click', function() {
                    const employeeId = this.getAttribute('data-employee-id');
                    const employeeName = this.getAttribute('data-employee-name');
                   
                    // Update modal title
                    document.getElementById('historyModalLabel').textContent = 'Performance History - ' + employeeName;
                   
                    // Load history data via AJAX
                    fetch('get_performance_history.php?employee_id=' + employeeId)
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('historyContent').innerHTML = data;
                            new bootstrap.Modal(document.getElementById('historyModal')).show();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('historyContent').innerHTML = `
                                <div class="alert alert-danger">
                                    Error loading performance history. Please try again.
                                </div>
                                <p>Please ensure that the file 'get_performance_history.php' exists and is configured correctly.</p>
                                <pre>
// Example get_performance_history.php file:
&lt;?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if (isset($_GET['employee_id'])) {
    $employee_id = $_GET['employee_id'];
   
    // Get employee info
    $emp_sql = "SELECT first_name, last_name FROM employees WHERE id = ?";
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $emp_row = $emp_result->fetch_assoc();
   
    // Get performance history
    $sql = "SELECT p.*, e.first_name, e.last_name
            FROM performance p
            JOIN employees e ON p.employee_id = e.id
            WHERE p.employee_id = ?
            ORDER BY p.review_date DESC";
           
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    if ($result->num_rows > 0) {
        echo '&lt;table class="table table-striped">
                &lt;thead>
                    &lt;tr>
                        &lt;th>Date&lt;/th>
                        &lt;th>Rating&lt;/th>
                        &lt;th>Comments&lt;/th>
                    &lt;/tr>
                &lt;/thead>
                &lt;tbody>';
               
        while ($row = $result->fetch_assoc()) {
            echo '&lt;tr>
                    &lt;td>' . date("M d, Y", strtotime($row["review_date"])) . '&lt;/td>
                    &lt;td>';
                   
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $row["rating"]) {
                    echo '&lt;i class="fas fa-star text-warning">&lt;/i>';
                } else {
                    echo '&lt;i class="far fa-star text-muted">&lt;/i>';
                }
            }
           
            echo '&lt;/td>
                    &lt;td>' . nl2br(htmlspecialchars($row["comments"])) . '&lt;/td>
                  &lt;/tr>';
        }
       
        echo '&lt;/tbody>&lt;/table>';
    } else {
        echo '&lt;div class="alert alert-info">No performance history found for this employee.&lt;/div>';
    }
} else {
    echo '&lt;div class="alert alert-danger">Invalid request.&lt;/div>';
}


$conn->close();
?&gt;
                                </pre>
                            `;
                            new bootstrap.Modal(document.getElementById('historyModal')).show();
                        });
                });
            });


            // Filtering functionality
            const searchInput = document.getElementById('searchInput');
            const departmentFilter = document.getElementById('departmentFilter');
            const ratingFilter = document.getElementById('ratingFilter');
            const resetFiltersBtn = document.getElementById('resetFilters');
           
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const departmentTerm = departmentFilter.value.toLowerCase();
                const ratingTerm = ratingFilter.value;
               
                document.querySelectorAll('.employee-item').forEach(item => {
                    const name = item.getAttribute('data-name');
                    const department = item.getAttribute('data-department');
                    const position = item.getAttribute('data-position');
                    const rating = item.getAttribute('data-rating');
                   
                    let matchesSearch = true;
                    let matchesDepartment = true;
                    let matchesRating = true;
                   
                    if (searchTerm) {
                        matchesSearch = name.includes(searchTerm) ||
                                       department.includes(searchTerm) ||
                                       position.includes(searchTerm);
                    }
                   
                    if (departmentTerm) {
                        matchesDepartment = department === departmentTerm;
                    }
                   
                    if (ratingTerm !== "") {
                        matchesRating = rating == ratingTerm;
                    }
                   
                    if (matchesSearch && matchesDepartment && matchesRating) {
                        item.style.display = "";
                    } else {
                        item.style.display = "none";
                    }
                });
               
                // Show/hide department headers based on visible employees
                document.querySelectorAll('.department-section').forEach(section => {
                    const departmentName = section.getAttribute('data-department').toLowerCase();
                    const hasVisibleEmployees = Array.from(
                        document.querySelectorAll(`.employee-item[data-department="${departmentName.toLowerCase()}"]`)
                    ).some(emp => emp.style.display !== "none");
                   
                    section.style.display = hasVisibleEmployees ? "" : "none";
                });
            }
           
            searchInput.addEventListener('input', applyFilters);
            departmentFilter.addEventListener('change', applyFilters);
            ratingFilter.addEventListener('change', applyFilters);
           
            resetFiltersBtn.addEventListener('click', function() {
                searchInput.value = "";
                departmentFilter.value = "";
                ratingFilter.value = "";
                applyFilters();
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>

