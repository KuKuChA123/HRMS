<?php
session_start();


// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    // Get form data
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $birthdate = $conn->real_escape_string($_POST['birthdate']);
    $address = $conn->real_escape_string($_POST['address']);
    $department = $conn->real_escape_string($_POST['department']);
    $position = $conn->real_escape_string($_POST['position']);
    $role = $conn->real_escape_string($_POST['role']); // Get the role from the form
    $hire_date = $conn->real_escape_string($_POST['hire_date']);
   
    // Account creation fields
    $create_account = isset($_POST['create_account']) ? true : false;
    $account_username = $create_account ? $conn->real_escape_string($_POST['account_username']) : '';
    $account_password = $create_account ? $_POST['account_password'] : '';
   
    // Check if email already exists
    $email_check_query = "SELECT id FROM employees WHERE email = '$email'";
    $result = $conn->query($email_check_query);
   
    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Error: Email address is already in use.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
   
    // Handle file upload
    $profile_picture = "";
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . uniqid() . '.' . $file_ext;
       
        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check !== false && move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            $profile_picture = $target_file;
        }
    }


    // Insert employee data (including role)
    $stmt = $conn->prepare("INSERT INTO employees (first_name, last_name, email, phone, birthdate, address, department, position, hire_date, profile_picture, username)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $first_name, $last_name, $email, $phone, $birthdate, $address, $department, $position, $hire_date, $profile_picture, $account_username);
   
    if ($stmt->execute()) {
        $employee_id = $stmt->insert_id;
        $message = "Employee added successfully! ID: " . $employee_id;
       
        // Create user account if requested
        if ($create_account) {
            // Check if username already exists
            $check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_username->bind_param("s", $account_username);
            $check_username->execute();
            $check_username->store_result();
           
            if ($check_username->num_rows > 0) {
                $_SESSION['error_message'] = "Username already exists. Please choose a different one.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
           
            // Hash the password
            $hashed_password = password_hash($account_password, PASSWORD_BCRYPT);
           
            // Insert into users table, using the role selected
            $user_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $user_stmt->bind_param("ssss", $account_username, $email, $hashed_password, $role);
           
            if ($user_stmt->execute()) {
                $message .= "<br>Account created with username: " . $account_username;
                $_SESSION['success_message'] = $message;
            } else {
                $_SESSION['error_message'] = "Employee added but account creation failed: " . $user_stmt->error;
            }
           
            $user_stmt->close();
        } else {
            $_SESSION['success_message'] = $message;
        }
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }


    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$conn->close();


// Check for messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;


unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>






<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="addEmployees.css">
       
 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php 

// Sample backend logic (replace this with your actual form processing)
$success_message = '';
$error_message = '';



// Obtain and trim the phone number input
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = $_POST['email'];
    $username = $_POST['account_username'];
    $password = $_POST['account_password'];
    $birthdate  = trim($_POST['birthdate'] ?? '');
    $hire_date  = trim($_POST['hire_date'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    // Name validation: ensure names do not have numbers
    if (preg_match('/[0-9]/', $first_name)) {
        $error_message = "First name should not contain numbers.";
    } elseif (preg_match('/[0-9]/', $last_name)) {
        $error_message = "Last name should not contain numbers.";
    }
    // Dummy check for username already existing
    elseif ($username === 'existinguser') {
        $error_message = "The username already exists. Please choose a different one.";
    }
    
    // Phone number validation (only check if phone is provided)
    if (!empty($phone)) {
        // Use a single regular expression that verifies:
        // - "09" followed by exactly 9 digits, making 11 digits total, OR
        // - "+639" followed by exactly 9 digits, making 12 characters total.
        if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $phone)) {
            $error_message = "Invalid phone number. Use 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (12 characters), with no spaces or letters.";
        }
    }
    
    if (empty($error_message) && !empty($birthdate)) {
        // Check if the birthdate is a valid date using strtotime()
        if (!strtotime($birthdate)) {
            $error_message = "Please enter a valid birthdate.";
        } else {
            $birthDateObj = new DateTime($birthdate);
            $today = new DateTime();
            
            // Check if the birthdate is in the future.
            if ($birthDateObj > $today) {
                $error_message = "Birthdate cannot be in the future.";
            } else {
                // Calculate age difference.
                $ageInterval = $today->diff($birthDateObj);
                $age = $ageInterval->y;
                
                // Validate that age is between 20 and 65 (inclusive)
                if ($age < 20 || $age > 65) {
                    $error_message = "Age must be between 20 and 65 years old.";
                }
            }
        }
    }

    // If there are no errors, proceed with saving the data
    if (empty($error_message)) {
        // Code to save the data to the database (Insert statement)
        // For example: $db->query('INSERT ... ');
        $success_message = "Employee added successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Employee</title>
    <link rel="stylesheet" href="your-styles.css">
    <!-- In your <head> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Optionally add a Flatpickr theme (e.g., "dark" or "material_blue") -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

<!-- At the bottom of your <body> or in your build pipeline -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</head>
<body>
<div class="container">
    <div class="header">
        <h1>Add New Employee</h1>
        <a href="dashboard.php" class="close-btn" title="Close">&times;</a>
    </div>

    <div class="form-container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="employeeForm">
        <div class="form-section">
        <h2 class="form-section-title"><i class="fas fa-user"></i> Employee Information</h2>

        <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required
                                pattern="^[^0-9]+$" title="First name must not contain numbers."
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required
                                pattern="^[^0-9]+$" title="Last name must not contain numbers."
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
        <div class="form-col">
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-control" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
        </div>
        <div class="form-col">
            <div class="form-group">
                <label for="phone">Cellphone Number *</label>
                <input type="tel" id="phone" name="phone" class="form-control" required
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
        </div>
    </div>


                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="birthdate">Date of Birth *</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control" required
                                   value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="hire_date">Hire Date *</label>
                            <input type="date" id="hire_date" name="hire_date" class="form-control" required
                                   value="<?php echo isset($_POST['hire_date']) ? htmlspecialchars($_POST['hire_date']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" class="form-control"
                                   value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" class="form-control"
                                   value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="profile_picture">Profile Photo</label>
                            <div class="photo-upload">
                                <div class="photo-preview" id="photoPreview">
                                    <img id="previewImage" src="" alt="Preview" style="display: none;">
                                </div>
                                <div class="photo-upload-control">
                                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="form-section-title"><i class="fas fa-user-lock"></i> Account Setup</h2>

                <div class="form-group">
                    <label style="display: inline-block;">
                        Create System Account
                        <label class="toggle-switch">
                            <input type="checkbox" id="create_account_toggle" name="create_account" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </label>
                </div>

                <div id="account_fields" style="display: block;">
                    <div class="account-info-section">
                        <h3>Account Information</h3>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="account_username">Username *</label>
                                    <input type="text" id="account_username" name="account_username" class="form-control" required
                                           value="<?php echo isset($_POST['account_username']) ? htmlspecialchars($_POST['account_username']) : ''; ?>">
                                    <small class="password-hint">Username must be unique</small>
                                </div>
                            </div>

                            <div class="form-col">
                                <div class="form-group">
                                    <label for="account_password">Password *</label>
                                    <input type="password" id="account_password" name="account_password" class="form-control" required>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <small class="password-hint">Use at least 8 characters with a mix of letters, numbers, and symbols</small>
                                </div>
                            </div>

                            <div class="form-col">
                                <div class="form-group">
                                    <label for="role">Position/Role *</label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="">Select Position</option>
                                        <option value="HR" <?php echo (isset($_POST['role']) && $_POST['role'] === 'HR') ? 'selected' : ''; ?>>HR</option>
                                        <option value="Employee" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>



           <!-- ... other parts of your form remain unchanged ... -->

<!-- Wrap the submit button in a container -->
<div class="form-row submit-container">
    <div class="form-col" style="text-align: right; width: 100%;">
        <button type="submit" name="submit" class="btn btn-block">
            <i class="fas fa-save"></i> Save Employee
        </button>
    </div>
</div>

</div>
<!-- Client-side JavaScript validation is detailed below -->
<script>
    // Phone validation using JavaScript
    const phoneInput = document.getElementById('phone');
    const phoneError = document.createElement('div');
    phoneError.className = 'form-error';
    phoneError.style.color = 'red';
    phoneInput.parentNode.appendChild(phoneError);

    phoneInput.addEventListener('input', function () {
        const phone = phoneInput.value;
        phoneError.textContent = '';
        phoneInput.classList.remove('invalid');

        // Regular expression matching exactly the Philippine cellphone number formats:
        // - 09XXXXXXXXX (11 digits)
        // - +639XXXXXXXXX (12 characters)
        const validPattern = /^(09\d{9}|\+639\d{9})$/;

        if (!validPattern.test(phone)) {
            phoneError.textContent = 'Invalid phone number. Use 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (12 characters), with no spaces.';
            phoneInput.classList.add('invalid');
        }
    });
</script>

<script>
    const calendarIcon = document.querySelector(".calendar-icon");
    const birthdateInput = document.getElementById('birthdate');
    const birthdateError = document.createElement('div');
    birthdateError.className = 'form-error';
    birthdateError.style.color = 'red';
    birthdateInput.parentNode.appendChild(birthdateError);

    birthdateInput.addEventListener('change', function () {
        const birthdateValue = this.value;
        birthdateError.textContent = '';
        this.classList.remove('invalid');

        const birthdate = new Date(birthdateValue);
        const today = new Date();

        if (isNaN(birthdate.getTime())) {
            birthdateError.textContent = 'Please enter a valid date.';
            this.classList.add('invalid');
            return;
        }

        if (birthdate > today) {
            birthdateError.textContent = 'Birthdate cannot be in the future.';
            this.classList.add('invalid');
            return;
        }

        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        if (age < 20 || age > 65) {
            birthdateError.textContent = 'Age must be between 20 and 65 years old.';
            this.classList.add('invalid');
        }
    });

    flatpickr("#birthdate", {
        dateFormat: "Y-m-d",
        // Allow only dates that produce ages between 20 and 65
        minDate: "1960-01-01",
        maxDate: "2025-12-31",
        // Additional options (if needed)
    });
      
    const hireDateInput = document.querySelector("#hire_date");
    const hireDateError = document.createElement('div');
    hireDateError.className = 'form-error';
    hireDateInput.parentNode.appendChild(hireDateError);

    const maxHireDate = new Date();
    const minHireDate = new Date();
    minHireDate.setDate(maxHireDate.getDate() - 4);

    flatpickr(hireDateInput, {
      dateFormat: "Y-m-d",
      minDate: minHireDate,
      maxDate: maxHireDate
    });

    hireDateInput.addEventListener('change', function () {
      const value = this.value;
      hireDateError.textContent = '';
      this.classList.remove('invalid');

      const selected = new Date(value);
      const now = new Date();
      const limitPast = new Date();
      limitPast.setDate(now.getDate() - 5);

      if (isNaN(selected.getTime())) {
        hireDateError.textContent = 'Please enter a valid hire date.';
        this.classList.add('invalid');
        return;
      }

      if (selected > now) {
        hireDateError.textContent = 'Hire date cannot be in the future.';
        this.classList.add('invalid');
        return;
      }

      if (selected < limitPast) {
        hireDateError.textContent = 'Hire date cannot be more than 5 days ago.';
        this.classList.add('invalid');
      }
    });
  </script>



</body>
</html>
