<?php
require_once __DIR__ . '/../config/database.php';

// Get user profile picture if logged in
$profile_picture = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $profile_picture = $result->fetch_assoc()['profile_picture'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .notification-badge {
            background: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            position: relative;
            top: -10px;
            right: 5px;
        }
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        .profile-button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        .profile-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        .profile-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 5px;
            z-index: 1000;
        }
        .profile-menu a {
            color: #333;
            padding: 8px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .profile-menu a:hover {
            background-color: #f8f9fa;
        }
        .profile-menu.show {
            display: block;
        }
        .profile-avatar {
            width: 28px;
            height: 28px;
            background: #003d82;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            overflow: hidden;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .nav-divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 4px 0;
        }
        .username-text {
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php">
            <img src="image\train_icon.png" alt="railway Logo">
        </a>
        <nav>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Navigation for logged-in users -->
                <a href="schedules.php">Schedules</a>
                <a href="ticketing.php">Buy Tickets</a>
                <a href="history.php">Purchase History</a>
                <a href="ticket_cancellation.php">Cancel Ticket</a>
                <a href="report.php">Report Issue</a>
                <a href="about_us.php">About Us</a>
                <a href="notifications.php">
                    <i class="bi bi-bell"></i>
                    <?php
                        require_once __DIR__ . '/../includes/NotificationManager.php';
                        $notificationManager = new NotificationManager($conn);
                        $unreadCount = count($notificationManager->getUnreadNotifications($_SESSION['user_id']));
                        if ($unreadCount > 0):
                    ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <div class="profile-dropdown">
                    <button class="profile-button" onclick="toggleProfileMenu()">
                        <div class="profile-avatar">
                            <?php if ($profile_picture): ?>
                                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <span class="username-text"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="profile-menu" id="profileMenu">
                        <a href="profile.php">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                        <a href="edit_profile.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <div class="nav-divider"></div>
                        <a href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Navigation for guests -->
                <a href="schedules.php">Schedules</a>
                <a href="about_us.php">About Us</a>
                <a href="login.php">Login/Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <!-- Main Content Container -->
    <div class="container mt-4">

    <script>
        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-button') && !event.target.matches('.profile-button *')) {
                var dropdowns = document.getElementsByClassName('profile-menu');
                for (var i = 0; i <dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>