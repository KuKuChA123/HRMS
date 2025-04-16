<?php
// Start the session
session_start();


// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);


// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}


$response = ['success' => false, 'message' => ''];


// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the input data from the form
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    // Validate inputs
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address';
    } elseif (empty($password) || strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters';
    } else {
        // Query to check if the email exists and get role
        $sql = "SELECT id, password, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();


        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();


            if (password_verify($password, $user['password'])) {
                // Store user session
                $_SESSION['id'] = $user['id'];  // Changed from user_id to id
                $_SESSION['user_role'] = $user['role'];


                // Set redirect path based on role
                if ($user['role'] === 'hr') {
                    $response = ['success' => true, 'redirect' => 'dashboard.php'];
                } elseif ($user['role'] === 'employee') {
                    $response = ['success' => true, 'redirect' => 'employeeDashboard.php'];
                } else {
                    $response['message'] = 'Unknown role detected.';
                }
            } else {
                $response['message'] = 'Invalid email or password';
            }
        } else {
            $response['message'] = 'Invalid email or password';
        }


        $stmt->close();
    }


    // Send JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        // Regular form submission - redirect
        if ($response['success']) {
            header('Location: ' . $response['redirect']);
            exit();
        } else {
            echo "<script>alert('" . $response['message'] . "'); window.history.back();</script>";
        }
    }
}


$conn->close();
?>






<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Corporate Login Portal</title>
  <meta name="description" content="Secure login portal for corporate employees">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔒</text></svg>">


  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Poppins:wght@600&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Poppins:wght@600&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />


  <style>
    :root {
      --primary: #4B3F72;
      --primary-light: #6B5CA5;
      --primary-dark: #3A3159;
      --secondary: #BFA2DB;
      --light: #F7EDF0;
      --white: #FFFFFF;
      --error: #FF6B6B;
      --success: #4BB543;
      --text: #2D2A4A;
      --text-light: #A0A0B2;
      --gray: #E5E5E5;
      --border-radius: 10px;
      --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.12);
      --transition: all 0.25s ease;
      --focus-ring: 0 0 0 3px rgba(191, 162, 219, 0.5);
    }


    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }


    body {
      background: linear-gradient(135deg, var(--light) 0%, var(--white) 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      line-height: 1.5;
      color: var(--text);
    }


    .login-card {
      background-color: var(--white);
      border-radius: var(--border-radius);
      box-shadow: var(--shadow);
      width: 100%;
      max-width: 420px;
      padding: 40px 32px;
      animation: fadeIn 0.5s ease-out;
      transition: var(--transition);
    }


    .login-card:hover {
      box-shadow: var(--shadow-hover);
    }


    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }


    .logo {
      text-align: center;
      margin-bottom: 32px;
    }


    .logo h1 {
      font-family: 'Poppins', sans-serif;
      font-size: 36px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      letter-spacing: 1px;
      margin-bottom: 4px;
    }


    .logo p {
      color: var(--text-light);
      font-size: 14px;
      font-weight: 300;
    }


    .form-group {
      margin-bottom: 20px;
      position: relative;
    }


    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--text);
      font-weight: 500;
      font-size: 15px;
    }


    .input-field {
      position: relative;
    }


    .input-field i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 16px;
      transition: var(--transition);
    }


    .input-field input {
      width: 100%;
      padding: 14px 44px 14px 40px;
      border: 1px solid var(--gray);
      border-radius: var(--border-radius);
      font-size: 15px;
      transition: var(--transition);
      background-color: var(--white);
    }


    .input-field input:focus {
      outline: none;
      border-color: var(--secondary);
      box-shadow: var(--focus-ring);
    }


    .input-field input:focus + i {
      color: var(--primary);
    }


    .input-field.error input {
      border-color: var(--error);
    }


    .input-field.success input {
      border-color: var(--success);
    }


    .password-toggle {
      position: absolute;
      top: 50%;
      right: 20px;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--text-light);
      font-size: 16px;
      transition: var(--transition);
      background: none;
      border: none;
      padding: 0;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }


    .password-toggle:hover {
      color: var(--primary);
    }


    .password-toggle:focus {
      outline: none;
      color: var(--primary);
    }


    .error-message {
      color: var(--error);
      font-size: 13px;
      margin-top: 6px;
      display: none;
      animation: fadeIn 0.3s ease-out;
    }


    .forgot-password {
      display: block;
      text-align: left;
      margin: -12px 0 20px;
      font-size: 13px;
      color: var(--secondary);
      text-decoration: none;
      transition: var(--transition);
      font-weight: 500;
    }


    .forgot-password:hover, .forgot-password:focus {
      color: var(--primary);
      text-decoration: underline;
      outline: none;
    }


    .login-btn {
      width: 100%;
      padding: 14px;
      background-color: var(--primary);
      color: var(--white);
      border: none;
      border-radius: var(--border-radius);
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      margin-bottom: 24px;
      letter-spacing: 0.3px;
    }


    .login-btn:hover {
      background-color: var(--primary-light);
      box-shadow: 0 6px 12px rgba(75, 63, 114, 0.2);
    }


    .login-btn:active {
      background-color: var(--primary-dark);
      transform: translateY(1px);
    }


    .login-btn:focus {
      outline: none;
      box-shadow: var(--focus-ring);
    }


    .login-btn:disabled {
      background-color: var(--gray);
      color: var(--text-light);
      cursor: not-allowed;
      box-shadow: none;
    }


    .footer {
      display: flex;
      justify-content: space-between;
      padding-top: 20px;
      border-top: 1px solid var(--gray);
      font-size: 13px;
      color: var(--text-light);
    }


    .footer-links {
      display: flex;
      gap: 16px;
    }


    .footer-links a {
      color: var(--text-light);
      text-decoration: none;
      transition: var(--transition);
      font-weight: 400;
    }


    .footer-links a:hover, .footer-links a:focus {
      color: var(--primary);
      text-decoration: underline;
      outline: none;
    }


    .developer {
      color: var(--text-light);
    }


    .loading-spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--white);
      animation: spin 1s ease-in-out infinite;
      margin: 0 auto;
    }


    @keyframes spin {
      to { transform: rotate(360deg); }
    }


    .status-message {
      text-align: center;
      margin-bottom: 16px;
      padding: 10px;
      border-radius: var(--border-radius);
      display: none;
      animation: fadeIn 0.3s ease-out;
    }


    .status-message.error {
      background-color: rgba(255, 107, 107, 0.1);
      color: var(--error);
      border: 1px solid rgba(255, 107, 107, 0.3);
    }


    .status-message.success {
      background-color: rgba(75, 181, 67, 0.1);
      color: var(--success);
      border: 1px solid rgba(75, 181, 67, 0.3);
    }


    @media (prefers-reduced-motion: reduce) {
      * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }


    @media (max-width: 480px) {
      .login-card {
        padding: 32px 24px;
      }


      .footer {
        flex-direction: column;
        gap: 8px;
        align-items: center;
      }


      .footer-links {
        order: 2;
      }


      .developer {
        order: 1;
        margin-bottom: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="logo">
      <h1>HRMS</h1>
      <p>Human Resource Management System</p>
    </div>


    <div class="status-message" id="statusMessage"></div>


    <form id="loginForm" novalidate>
      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-field">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="employee@company.com" required autocomplete="username">
        </div>
        <span class="error-message" id="email-error">Please enter a valid company email address</span>
      </div>


      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-field">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password" minlength="8">
          <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
            <i class="fas fa-eye" id="eyeIcon" style="display: none;"></i> <!-- Initially hide the eye icon -->
          </button>
        </div>
        <span class="error-message" id="password-error">Password must be at least 8 characters</span>
      </div>


      <a href="#" class="forgot-password" tabindex="0">Forgot Password?</a>
      <button type="submit" class="login-btn" id="loginButton">
        <span id="buttonText">Log In</span>
        <div class="loading-spinner" id="spinner"></div>
      </button>
    </form>


    <div class="footer">
      <div class="footer-links">
        <a href="#" tabindex="0">Help</a>
        <a href="#" tabindex="0">Contact Us</a>
        <a href="#" tabindex="0">Terms</a>
      </div>
      <div class="developer">© 2025 The Clique</div>
    </div>
  </div>


  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const loginForm = document.getElementById('loginForm');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');
      const togglePassword = document.getElementById('togglePassword');
      const eyeIcon = document.getElementById('eyeIcon');
      const emailError = document.getElementById('email-error');
      const passwordError = document.getElementById('password-error');
      const loginButton = document.getElementById('loginButton');
      const buttonText = document.getElementById('buttonText');
      const spinner = document.getElementById('spinner');
      const statusMessage = document.getElementById('statusMessage');


      // Toggle password visibility
      togglePassword.addEventListener('click', function (e) {
        e.preventDefault();
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);


        // Toggle visibility of the eye icon
        if (type === 'text') {
          eyeIcon.style.display = 'block';  // Show the eye icon when the password is visible
        } else {
          eyeIcon.style.display = 'none';   // Hide the eye icon when the password is hidden
        }


        togglePassword.setAttribute('aria-label', type === 'text' ? 'Hide password' : 'Show password');
      });


      // Email format validation
      function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
      }


      function validateForm() {
        let isValid = true;


        if (!emailInput.value || !validateEmail(emailInput.value)) {
          emailInput.parentElement.classList.add('error');
          emailError.style.display = 'block';
          isValid = false;
        } else {
          emailInput.parentElement.classList.remove('error');
          emailError.style.display = 'none';
        }


        if (!passwordInput.value || passwordInput.value.length < 8) {
          passwordInput.parentElement.classList.add('error');
          passwordError.style.display = 'block';
          isValid = false;
        } else {
          passwordInput.parentElement.classList.remove('error');
          passwordError.style.display = 'none';
        }


        return isValid;
      }


      function showStatusMessage(message, type) {
        statusMessage.textContent = message;
        statusMessage.className = 'status-message ' + type;
        statusMessage.style.display = 'block';
      }


      loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();


        if (!validateForm()) return;


        // Show loading
        loginButton.disabled = true;
        buttonText.style.display = 'none';
        spinner.style.display = 'block';
        statusMessage.style.display = 'none';


        const email = emailInput.value.trim();
        const password = passwordInput.value;


        try {
          const response = await fetch('login.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
          });


          const result = await response.json();


          if (result.success) {
            showStatusMessage(result.message, 'success');
           
            // Redirect based on role
            if (result.redirect) {
              setTimeout(() => {
                window.location.href = result.redirect;  // Redirect to either dashboard.php or employeeDashboard.php
              }, 1000);
            }
          } else {
            showStatusMessage(result.message || 'Invalid credentials.', 'error');
          }
        } catch (error) {
          showStatusMessage('Login failed. Please try again.', 'error');
          console.error('Login error:', error);
        } finally {
          loginButton.disabled = false;
          buttonText.style.display = 'block';
          spinner.style.display = 'none';
        }
      });
    });
  </script>


</body>
</html>



