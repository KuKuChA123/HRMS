<?php
// Start session
session_start();


// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}


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


// Get current user information from session - FIX HERE
$current_user_id = $_SESSION['id']; // Changed from 'user_id' to 'id' to match your session structure
$current_user_role = $_SESSION['role'];


// Function to get user details
function getUserDetails($conn, $user_id) {
    $sql = "SELECT u.*, e.first_name, e.last_name, e.department, e.position, e.profile_picture
            FROM users u
            LEFT JOIN employees e ON u.id = e.user_id
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}


// Function to get conversation messages
function getConversation($conn, $user1_id, $user2_id) {
    $sql = "SELECT i.*,
                  CONCAT(e_sender.first_name, ' ', e_sender.last_name) as sender_name,
                  e_sender.profile_picture as sender_picture,
                  u_sender.role as sender_role,
                  CONCAT(e_receiver.first_name, ' ', e_receiver.last_name) as receiver_name,
                  e_receiver.profile_picture as receiver_picture,
                  u_receiver.role as receiver_role
           FROM inbox i
           LEFT JOIN users u_sender ON i.sender_id = u_sender.id
           LEFT JOIN employees e_sender ON u_sender.id = e_sender.user_id
           LEFT JOIN users u_receiver ON i.receiver_id = u_receiver.id
           LEFT JOIN employees e_receiver ON u_receiver.id = e_receiver.user_id
           WHERE (i.sender_id = ? AND i.receiver_id = ?)
              OR (i.sender_id = ? AND i.receiver_id = ?)
           ORDER BY i.sent_at ASC";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    return $result->fetch_all(MYSQLI_ASSOC);
}


// Function to count unread messages
function countUnreadMessages($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM inbox WHERE receiver_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}


// Function to get user conversations
function getUserConversations($conn, $user_id) {
    // This query gets the latest message from each conversation
    $sql = "SELECT i.*,
            CONCAT(e_other.first_name, ' ', e_other.last_name) as other_name,
            e_other.profile_picture as other_picture,
            e_other.department as other_department,
            e_other.position as other_position,
            u_other.role as other_role,
            u_other.id as other_id,
            (SELECT COUNT(*) FROM inbox WHERE
                ((sender_id = i.sender_id AND receiver_id = i.receiver_id) OR
                (sender_id = i.receiver_id AND receiver_id = i.sender_id)) AND
                receiver_id = ? AND is_read = 0) as unread_count
            FROM inbox i
            JOIN (
                SELECT
                    CASE
                        WHEN sender_id = ? THEN receiver_id
                        ELSE sender_id
                    END as other_user_id,
                    MAX(sent_at) as max_sent_at
                FROM inbox
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY other_user_id
            ) latest ON
                ((i.sender_id = ? AND i.receiver_id = latest.other_user_id) OR
                (i.sender_id = latest.other_user_id AND i.receiver_id = ?)) AND
                i.sent_at = latest.max_sent_at
            JOIN users u_other ON latest.other_user_id = u_other.id
            LEFT JOIN employees e_other ON u_other.id = e_other.user_id
            ORDER BY i.sent_at DESC";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    return $result->fetch_all(MYSQLI_ASSOC);
}


// Get list of available HR staff for employees to message
function getAvailableHRStaff($conn) {
    $sql = "SELECT u.id, u.username, u.role, e.first_name, e.last_name, e.department, e.position, e.profile_picture
            FROM users u
            LEFT JOIN employees e ON u.id = e.user_id
            WHERE u.role IN ('hr', 'admin')
            ORDER BY e.last_name, e.first_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
   
    return $result->fetch_all(MYSQLI_ASSOC);
}


// Process message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'];
   
    // Validate input
    if (empty($message)) {
        $error_message = "Message cannot be empty";
    } else {
        // Verify the receiver is HR or admin (for employee security)
        $sql = "SELECT role FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $receiver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $receiver = $result->fetch_assoc();
       
        if ($receiver && ($receiver['role'] === 'hr' || $receiver['role'] === 'admin')) {
            $sql = "INSERT INTO inbox (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $current_user_id, $receiver_id, $subject, $message);
           
            if ($stmt->execute()) {
                $success_message = "Message sent successfully";
                // If we're in a conversation, redirect back to it
                if (isset($_GET['user_id'])) {
                    header("Location: employeeinbox.php?user_id=" . $_GET['user_id']);
                    exit;
                }
            } else {
                $error_message = "Error sending message: " . $conn->error;
            }
        } else {
            $error_message = "You can only send messages to HR staff";
        }
    }
}


// Mark messages as read when viewing a conversation
if (isset($_GET['user_id'])) {
    $other_user_id = $_GET['user_id'];
   
    // Verify the other user is HR or admin (for employee security)
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $other_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $other_user_role = $result->fetch_assoc()['role'];
   
    if ($other_user_role === 'hr' || $other_user_role === 'admin') {
        // Mark messages as read
        $sql = "UPDATE inbox SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $other_user_id, $current_user_id);
        $stmt->execute();
       
        // Get conversation
        $conversation = getConversation($conn, $current_user_id, $other_user_id);
        $other_user = getUserDetails($conn, $other_user_id);
    } else {
        // Redirect if trying to access non-HR conversation
        header("Location: employeeinbox.php");
        exit;
    }
}


// Get user's conversations for the sidebar
$conversations = getUserConversations($conn, $current_user_id);
$unread_count = countUnreadMessages($conn, $current_user_id);


// Get available HR staff for new message
$available_hr_staff = getAvailableHRStaff($conn);


// Get current user details
$current_user = getUserDetails($conn, $current_user_id);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Inbox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
  body {
    background-color: var(--light);
  }


  .chat-container {
    height: calc(100vh - 56px);
    margin-top: 56px;
  }


  .contacts-list {
    height: 100%;
    overflow-y: auto;
    background-color: var(--white);
    border-right: 1px solid var(--gray);
  }


  .message-area {
    display: flex;
    flex-direction: column;
    height: 100%;
  }


  .message-header {
    padding: 15px;
    border-bottom: 1px solid var(--gray);
    background-color: var(--white);
  }


  .messages-container {
    flex-grow: 1;
    overflow-y: auto;
    padding: 15px;
    background-color: var(--white);
  }


  .message-input {
    padding: 15px;
    border-top: 1px solid var(--gray);
    background-color: var(--white);
  }


  .message-bubble {
    max-width: 75%;
    padding: 10px 15px;
    border-radius: 15px;
    margin-bottom: 10px;
    position: relative;
    word-wrap: break-word;
    box-shadow: var(--shadow);
  }


  .message-sent {
    background-color: var(--success);
    color: var(--white);
    margin-left: auto;
    border-bottom-right-radius: 5px;
  }


  .message-received {
    background-color: var(--primary-light);
    color: var(--white);
    margin-right: auto;
    border-bottom-left-radius: 5px;
  }


  .message-time {
    font-size: 0.7em;
    color: var(--text-light);
    margin-top: 5px;
    text-align: right;
  }


  .contact-item {
    cursor: pointer;
    padding: 10px 15px;
    border-bottom: 1px solid var(--gray);
    transition: var(--transition);
  }


  .contact-item:hover,
  .contact-item.active {
    background-color: var(--light);
  }


  .contact-item .unread-badge {
    font-size: 0.7em;
    background-color: var(--primary);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 10px;
  }


  .profile-pic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
  }


  .empty-state {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    color: var(--text-light);
  }


  .navbar .badge-notification {
    position: absolute;
    top: 0px;
    right: 0px;
    font-size: 0.6em;
    background-color: var(--error);
    color: var(--white);
    padding: 2px 5px;
    border-radius: 50%;
  }


  .subject-line {
    font-size: 0.9em;
    color: var(--text-light);
    margin-bottom: 5px;
  }


  .preview-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-light);
  }


  .message-date-divider {
    text-align: center;
    margin: 15px 0;
    position: relative;
  }


  .message-date-divider span {
    background-color: var(--white);
    padding: 0 10px;
    position: relative;
    z-index: 1;
    font-size: 0.8em;
    color: var(--text-light);
  }


  .message-date-divider:before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background-color: var(--gray);
    z-index: 0;
  }


  .mobile-toggle {
    display: none;
  }


  .hr-badge {
    background-color: var(--success);
    color: var(--white);
    font-size: 0.7em;
    padding: 3px 6px;
    border-radius: 3px;
  }


  .help-box {
    background-color: var(--secondary);
    color: var(--text);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
  }


  @media (max-width: 768px) {
    .contacts-list {
      position: fixed;
      top: 56px;
      left: 0;
      width: 100%;
      z-index: 1000;
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      height: calc(100vh - 56px);
    }


    .contacts-list.show {
      transform: translateX(0);
    }


    .mobile-toggle {
      display: block;
    }
  }
</style>


</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">HRMS Employee Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="employeedashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="myprofile.php">My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaverequests.php">Leave Requests</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="employeeinbox.php">
                            Messages
                            <?php if ($unread_count > 0): ?>
                            <span class="position-relative">
                                <i class="fas fa-envelope"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-notification">
                                    <?php echo $unread_count; ?>
                                </span>
                            </span>
                            <?php else: ?>
                            <i class="fas fa-envelope"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="text-white me-3">
                        <?php echo $current_user['first_name'] . ' ' . $current_user['last_name']; ?>
                        <small class="text-white-50">(<?php echo $current_user['department']; ?>)</small>
                    </div>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>


    <div class="container-fluid chat-container">
        <div class="row h-100">
            <!-- Contacts List -->
            <div class="col-md-3 col-lg-3 p-0 contacts-list" id="contactsList">
                <div class="p-3 bg-light border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">HR Messages</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                            <i class="fas fa-plus"></i> New Message
                        </button>
                    </div>
                </div>
               
                <!-- Contact List -->
                <div class="contact-list">
                    <?php if (count($conversations) > 0): ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php if ($conv['other_role'] === 'hr' || $conv['other_role'] === 'admin'): ?>
                                <div class="contact-item <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $conv['other_id']) ? 'active' : ''; ?>"
                                     data-user-id="<?php echo $conv['other_id']; ?>"
                                     onclick="window.location.href='employeeinbox.php?user_id=<?php echo $conv['other_id']; ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="position-relative me-2">
                                            <?php if (!empty($conv['other_picture'])): ?>
                                                <img src="<?php echo $conv['other_picture']; ?>" alt="Profile" class="profile-pic">
                                            <?php else: ?>
                                                <div class="profile-pic bg-secondary d-flex justify-content-center align-items-center text-white">
                                                    <?php echo strtoupper(substr($conv['other_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($conv['other_role'] == 'hr'): ?>
                                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success" style="font-size: 0.6em;">HR</span>
                                            <?php elseif ($conv['other_role'] == 'admin'): ?>
                                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-danger" style="font-size: 0.6em;">Admin</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($conv['other_name']); ?></h6>
                                                <small class="text-muted"><?php echo date('M d', strtotime($conv['sent_at'])); ?></small>
                                            </div>
                                            <?php if (!empty($conv['other_department'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($conv['other_department']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo ucfirst($conv['other_role']); ?></small>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <div class="preview-text">
                                                    <?php if ($conv['sender_id'] == $current_user_id): ?>
                                                        <small class="text-muted">You: </small>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars(substr($conv['message'], 0, 30)); ?>
                                                    <?php echo (strlen($conv['message']) > 30) ? '...' : ''; ?>
                                                </div>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="badge bg-primary rounded-pill unread-badge"><?php echo $conv['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">
                            <p>No conversations with HR yet</p>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                Contact HR
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Message Area -->
            <div class="col-md-9 col-lg-9 p-0 message-area">
                <?php if (isset($_GET['user_id']) && isset($other_user) && ($other_user['role'] === 'hr' || $other_user['role'] === 'admin')): ?>
                    <!-- Message Header -->
                    <div class="message-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-secondary me-2 mobile-toggle" id="toggleContacts">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div class="position-relative me-2">
                                <?php if (!empty($other_user['profile_picture'])): ?>
                                    <img src="<?php echo $other_user['profile_picture']; ?>" alt="Profile" class="profile-pic">
                                <?php else: ?>
                                    <div class="profile-pic bg-secondary d-flex justify-content-center align-items-center text-white">
                                        <?php echo strtoupper(substr($other_user['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($other_user['role'] == 'hr'): ?>
                                    <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success" style="font-size: 0.6em;">HR</span>
                                <?php elseif ($other_user['role'] == 'admin'): ?>
                                    <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-danger" style="font-size: 0.6em;">Admin</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $other_user['first_name'] . ' ' . $other_user['last_name']; ?></h5>
                                <small class="text-muted">
                                    <?php if (!empty($other_user['department'])): ?>
                                        <?php echo $other_user['department']; ?> - <?php echo ucfirst($other_user['role']); ?>
                                    <?php else: ?>
                                        <?php echo ucfirst($other_user['role']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>


                    <!-- Messages Container -->
                    <div class="messages-container" id="messagesContainer">
                        <?php
                        $date_shown = null;
                        foreach ($conversation as $message):
                            $message_date = date('Y-m-d', strtotime($message['sent_at']));
                            if ($message_date != $date_shown):
                                $date_shown = $message_date;
                                $date_display = date('F j, Y', strtotime($message['sent_at']));
                                if ($date_display == date('F j, Y')) {
                                    $date_display = 'Today';
                                } elseif ($date_display == date('F j, Y', strtotime('-1 day'))) {
                                    $date_display = 'Yesterday';
                                }
                        ?>
                            <div class="message-date-divider">
                                <span><?php echo $date_display; ?></span>
                            </div>
                        <?php endif; ?>


                            <div class="d-flex flex-column <?php echo ($message['sender_id'] == $current_user_id) ? 'align-items-end' : 'align-items-start'; ?>">
                                <div class="message-bubble <?php echo ($message['sender_id'] == $current_user_id) ? 'message-sent' : 'message-received'; ?>">
                                    <?php if (!empty($message['subject'])): ?>
                                        <div class="subject-line"><strong><?php echo htmlspecialchars($message['subject']); ?></strong></div>
                                    <?php endif; ?>
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    <div class="message-time">
                                        <?php echo date('h:i A', strtotime($message['sent_at'])); ?>
                                        <?php if ($message['sender_id'] == $current_user_id): ?>
                                            <i class="fas fa-check-double ms-1" style="font-size: 0.8em; <?php echo $message['is_read'] ? 'color: #4fc3f7;' : ''; ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>


                    <!-- Message Input -->
                    <div class="message-input">
                        <form method="post" action="employeeinbox.php?user_id=<?php echo $other_user_id; ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $other_user_id; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="message" rows="3" placeholder="Type your message..." required></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <!-- Could add file attachments or other controls here -->
                                </div>
                                <button type="submit" name="send_message" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="text-center">
                            <i class="fas fa-comments fa-4x mb-3"></i>
                            <h4>HR Communications</h4>
                            <p>Select a conversation or contact HR</p>
                           
                            <div class="help-box mt-3 mb-3 mx-auto" style="max-width: 500px;">
                                <h5><i class="fas fa-info-circle me-2"></i> Need Help?</h5>
                                <p>Use this messaging system to communicate with your HR representative about:</p>
                                <ul class="text-start">
                                    <li>Benefits questions</li>
                                    <li>Workplace concerns</li>
                                    <li>Leave requests</li>
                                    <li>General HR inquiries</li>
                                </ul>
                                <p class="mb-0">All communications are confidential between you and the HR department.</p>
                            </div>
                           
                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i> Contact HR
                            </button>
                           
                            <button class="btn btn-outline-secondary mt-2 d-md-none" id="showContactsBtn">
                                <i class="fas fa-users me-1"></i> Show Conversations
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newMessageModalLabel">Contact HR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="employeeinbox.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">Send to HR Representative</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Select HR representative...</option>
                                <?php foreach ($available_hr_staff as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo $staff['first_name'] . ' ' . $staff['last_name']; ?>
                                        <?php if ($staff['role'] == 'hr'): ?>(HR)<?php endif; ?>
                                        <?php if ($staff['role'] == 'admin'): ?>(Admin)<?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter subject">
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required placeholder="Type your message here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Bootstrap and JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of messages container
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messagesContainer');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
           
            // Mobile responsive handlers
            const toggleContacts = document.getElementById('toggleContacts');
            const contactsList = document.getElementById('contactsList');
            const showContactsBtn = document.getElementById('showContactsBtn');
           
            if (toggleContacts) {
                toggleContacts.addEventListener('click', function() {
                    contactsList.classList.toggle('show');
                });
            }
           
            if (showContactsBtn) {
                showContactsBtn.addEventListener('click', function() {
                    contactsList.classList.add('show');
                });
            }
           
            // Close contacts list when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInsideContacts = contactsList.contains(event.target);
                const isClickToggleBtn = toggleContacts && toggleContacts.contains(event.target);
                const isClickShowBtn = showContactsBtn && showContactsBtn.contains(event.target);
               
                if (!isClickInsideContacts && !isClickToggleBtn && !isClickShowBtn && window.innerWidth <= 768) {
                    contactsList.classList.remove('show');
                }
            });
           
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    contactsList.classList.remove('show');
                }
            });
        });
       
        // Handle success and error messages with auto-dismiss
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-5';
            alert.setAttribute('role', 'alert');
            alert.innerHTML = `
                ${urlParams.get('success')}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alert);
           
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        }
       
        if (urlParams.has('error')) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-5';
            alert.setAttribute('role', 'alert');
            alert.innerHTML = `
                ${urlParams.get('error')}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alert);
           
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>

