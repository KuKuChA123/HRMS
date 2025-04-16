<?php
// Include the database connection
require_once('db_connection.php');


// Start the session and get the user ID
session_start();


// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}


$user_id = $_SESSION['id']; // Get the user_id from the session


// Fetch user and employee data
$query = "SELECT u.username, u.email, u.role, e.first_name, e.last_name, e.phone, e.birthdate, e.address, e.department, e.position, e.hire_date
          FROM users u
          JOIN employees e ON u.id = e.id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();


// Check if the user is found
if ($result->num_rows === 0) {
    // Redirect to login if user not found
    header('Location: login.php?error=user_not_found');
    exit();
}


$user = $result->fetch_assoc();
$error_message = '';
$success = false;


// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form inputs
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $birthdate = trim($_POST['birthdate']);
    $address = trim($_POST['address']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';


    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    }
    // Validate phone number (basic validation)
    else if (!preg_match("/^[0-9\-\(\)\/\+\s]*$/", $phone)) {
        $error_message = "Invalid phone number format.";
    }
    // Validate password if user wants to change it
    else if (!empty($password) && $password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    }
    else {
        // Begin transaction
        $conn->begin_transaction();
       
        try {
            // Update the user data
            $update_user_query = "UPDATE users SET email = ? WHERE id = ?";
            $stmt = $conn->prepare($update_user_query);
            $stmt->bind_param('si', $email, $user_id);
            $stmt->execute();
           
            // If password is provided, update it
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_pwd_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_pwd_query);
                $stmt->bind_param('si', $hashed_password, $user_id);
                $stmt->execute();
            }
           
            // Update employee data
            $update_employee_query = "UPDATE employees SET first_name = ?, last_name = ?, phone = ?, birthdate = ?, address = ? WHERE id = ?";
            $stmt = $conn->prepare($update_employee_query);
            $stmt->bind_param('sssssi', $first_name, $last_name, $phone, $birthdate, $address, $user_id);
            $stmt->execute();
           
            // Commit transaction
            $conn->commit();
           
            // Set success flag
            $success = true;
           
            // Refresh user data
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
           
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Information | Employee Portal</title>
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
            padding: 20px 0;
        }
       
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease;
            max-width: 1200px;
            margin: 0 auto;
        }
       
        .card:hover {
            transform: translateY(-5px);
        }
       
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
            position: relative;
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
            text-decoration: none;
        }
       
        .form-label {
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
        }
       
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
       
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 84, 200, 0.25);
        }
       
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
            transition: all 0.3s;
        }
       
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(106, 17, 203, 0.4);
        }
       
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28a745;
            border-radius: 10px;
        }
       
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
            border-radius: 10px;
        }
       
        .profile-info {
            background-color: rgba(78, 84, 200, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
       
        .profile-info h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
       
        .card-footer {
            background: #f8f9fa;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px 30px;
        }
       
        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
       
        .password-field {
            position: relative;
        }
       
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--accent-color);
            z-index: 10;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            width: 36px;
            height: 36px;
        }
       
        .password-toggle:focus {
            outline: none;
        }
       
        @media (min-width: 992px) {
            .container {
                max-width: 1200px;
            }
           
            .card-body {
                padding: 2rem;
            }
           
            .form-layout {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
           
            .form-layout .full-width {
                grid-column: span 2;
            }
           
            .password-section {
                grid-column: span 2;
            }
           
            .profile-info-container {
                display: flex;
                gap: 20px;
            }
           
            .profile-info {
                flex: 1;
            }
        }
       
        @media (max-width: 991px) {
            .container {
                padding: 0 15px;
            }
           
            .form-layout > div {
                margin-bottom: 1rem;
            }
           
            .profile-info-container {
                display: block;
            }
           
            .profile-info {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header position-relative">
                        <h2 class="mb-0 text-center"><i class="fas fa-user-edit me-2"></i>Personal Information</h2>
                        <a href="dashboard.php" class="close-btn"><i class="fas fa-times"></i></a>
                    </div>
                   
                    <div class="card-body">
                        <!-- Display success or error message -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Your information has been updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                       
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                       
                        <!-- Profile Information Display -->
                        <div class="profile-info-container">
                            <div class="profile-info">
                                <h5><i class="fas fa-id-card me-2"></i>Account Details</h5>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                            </div>
                            <div class="profile-info">
                                <h5><i class="fas fa-briefcase me-2"></i>Employment Info</h5>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($user['position']); ?></p>
                                <p><strong>Hire Date:</strong> <?php echo htmlspecialchars($user['hire_date']); ?></p>
                            </div>
                        </div>
                       
                        <!-- Edit Form -->
                        <form action="personal.php" method="POST" id="personalForm" novalidate>
                            <div class="form-layout">
                                <div>
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div>
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                               
                                <div class="full-width">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                               
                                <div>
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                                <div>
                                    <label for="birthdate" class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate']); ?>" required>
                                </div>
                               
                                <div class="full-width">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                                </div>
                               
                                <div class="password-section">
                                    <hr class="my-4">
                                   
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="changePassword">
                                        <label class="form-check-label" for="changePassword">Change Password</label>
                                    </div>
                                   
                                    <div id="passwordFields" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="password-field">
                                                    <label for="password" class="form-label">New Password</label>
                                                    <input type="password" class="form-control" id="password" name="password">
                                                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <div class="form-text">Password must be at least 8 characters long and include letters and numbers.</div>
                                            </div>
                                           
                                            <div class="col-md-6 mb-3">
                                                <div class="password-field">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)" aria-label="Toggle password visibility">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                           
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                   
                    <div class="card-footer text-center">
                        <p class="mb-0"><small>Last updated: <?php echo date('F j, Y, g:i a'); ?></small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle change password checkbox
            const changePasswordCheckbox = document.getElementById('changePassword');
            const passwordFields = document.getElementById('passwordFields');
           
            changePasswordCheckbox.addEventListener('change', function() {
                passwordFields.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    document.getElementById('password').value = '';
                    document.getElementById('confirm_password').value = '';
                }
            });
           
            // Form validation
            const form = document.getElementById('personalForm');
           
            form.addEventListener('submit', function(event) {
                let valid = true;
                const email = document.getElementById('email').value;
                const phone = document.getElementById('phone').value;
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
               
                // Validate email
                if (!/^\S+@\S+\.\S+$/.test(email)) {
                    alert('Please enter a valid email address.');
                    valid = false;
                }
               
                // Validate phone number
                if (!/^[0-9\-\(\)\/\+\s]*$/.test(phone)) {
                    alert('Please enter a valid phone number.');
                    valid = false;
                }
               
                // Validate password if enabled
                if (changePasswordCheckbox.checked) {
                    if (password.length < 8) {
                        alert('Password must be at least 8 characters long.');
                        valid = false;
                    } else if (!/\d/.test(password) || !/[a-zA-Z]/.test(password)) {
                        alert('Password must include both letters and numbers.');
                        valid = false;
                    } else if (password !== confirmPassword) {
                        alert('Passwords do not match.');
                        valid = false;
                    }
                }
               
                if (!valid) {
                    event.preventDefault();
                }
            });
           
            // Auto-dismiss alerts after 5 seconds
            var alertList = document.querySelectorAll('.alert');
            alertList.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
       
        // Toggle password visibility
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
           
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

