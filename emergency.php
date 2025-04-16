<?php
// Start session
session_start();




// Database connection
$servername = "localhost";
$username = "root"; // Change as per your setup
$password = ""; // Change as per your setup
$dbname = "hrms";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Get employee information
$user_id = $_SESSION['id'];


// First check if this user has an employee record
$employee_query = "SELECT id FROM employees WHERE user_id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();


$employee_data = $result->fetch_assoc();
$employee_id = $employee_data['id'];


// Process form submission for adding new emergency contact
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_contact'])) {
    $name = $_POST['name'];
    $relationship = $_POST['relationship'];
    $phone = $_POST['phone'];
   
    // Input validation
    if (empty($name) || empty($relationship) || empty($phone)) {
        $error_message = "All fields are required";
    } else {
        // Insert into database
        $insert_query = "INSERT INTO emergency_contacts (employee_id, name, relationship, phone)
                         VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isss", $employee_id, $name, $relationship, $phone);
       
        if ($stmt->execute()) {
            $success_message = "Emergency contact added successfully!";
        } else {
            $error_message = "Error adding contact: " . $conn->error;
        }
    }
}


// Process delete contact request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_contact'])) {
    $contact_id = $_POST['contact_id'];
   
    // Delete from database
    $delete_query = "DELETE FROM emergency_contacts WHERE id = ? AND employee_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $contact_id, $employee_id);
   
    if ($stmt->execute()) {
        $success_message = "Emergency contact deleted successfully!";
    } else {
        $error_message = "Error deleting contact: " . $conn->error;
    }
}


// Process update contact request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_contact'])) {
    $contact_id = $_POST['contact_id'];
    $name = $_POST['name'];
    $relationship = $_POST['relationship'];
    $phone = $_POST['phone'];
   
    // Input validation
    if (empty($name) || empty($relationship) || empty($phone)) {
        $error_message = "All fields are required";
    } else {
        // Update database
        $update_query = "UPDATE emergency_contacts
                        SET name = ?, relationship = ?, phone = ?
                        WHERE id = ? AND employee_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssii", $name, $relationship, $phone, $contact_id, $employee_id);
       
        if ($stmt->execute()) {
            $success_message = "Emergency contact updated successfully!";
        } else {
            $error_message = "Error updating contact: " . $conn->error;
        }
    }
}


// Fetch emergency contacts for this employee
$contacts_query = "SELECT * FROM emergency_contacts WHERE employee_id = ?";
$stmt = $conn->prepare($contacts_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$contacts_result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Contacts - HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            background-color: #343a40;
            min-height: 100vh;
            color: white;
        }
        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
            display: block;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            color: white;
            background-color: #495057;
        }
        .content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5>HR Management System</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="profile.php" class="nav-link">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="timesheet.php" class="nav-link">
                                <i class="fas fa-clock me-2"></i> Timesheet
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="leave_requests.php" class="nav-link">
                                <i class="fas fa-calendar me-2"></i> Leave Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="emergency.php" class="nav-link active">
                                <i class="fas fa-phone-alt me-2"></i> Emergency Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="inbox.php" class="nav-link">
                                <i class="fas fa-envelope me-2"></i> Inbox
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
           
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2><i class="fas fa-phone-alt"></i> Emergency Contacts</h2>
                </div>
               
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
               
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
               
                <!-- Add New Emergency Contact -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Emergency Contact</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="relationship" class="form-label">Relationship</label>
                                    <input type="text" class="form-control" id="relationship" name="relationship" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" name="add_contact" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Add Contact
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
               
                <!-- Emergency Contacts List -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>My Emergency Contacts</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($contacts_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Relationship</th>
                                            <th>Phone Number</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($contact = $contacts_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                                <td><?php echo htmlspecialchars($contact['relationship']); ?></td>
                                                <td><?php echo htmlspecialchars($contact['phone']); ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $contact['id']; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $contact['id']; ?>">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                           
                                            <!-- Edit Modal -->
                                            <div class="modal fade" id="editModal<?php echo $contact['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-warning text-dark">
                                                            <h5 class="modal-title" id="editModalLabel">Edit Emergency Contact</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="edit_name" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($contact['name']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="edit_relationship" class="form-label">Relationship</label>
                                                                    <input type="text" class="form-control" id="edit_relationship" name="relationship" value="<?php echo htmlspecialchars($contact['relationship']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="edit_phone" class="form-label">Phone Number</label>
                                                                    <input type="text" class="form-control" id="edit_phone" name="phone" value="<?php echo htmlspecialchars($contact['phone']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="update_contact" class="btn btn-warning">Update Contact</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                           
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $contact['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title" id="deleteModalLabel">Delete Emergency Contact</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($contact['name']); ?></strong> from your emergency contacts?</p>
                                                            <p class="text-danger"><small>This action cannot be undone.</small></p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                                <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_contact" class="btn btn-danger">Delete Contact</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> You haven't added any emergency contacts yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
               
                <!-- Information Card -->
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                        <p>Emergency contacts are individuals who should be contacted in case of an emergency involving you. It's recommended to add at least two emergency contacts.</p>
                        <ul>
                            <li>Make sure phone numbers are accurate and up-to-date.</li>
                            <li>Consider adding contacts who are usually available during your working hours.</li>
                            <li>Inform your emergency contacts that you've listed them as such.</li>
                        </ul>
                    </div>
                </div>
               
            </div>
        </div>
    </div>
   
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
// Close connection
$conn->close();
?>

