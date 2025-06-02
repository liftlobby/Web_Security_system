
<?php
require_once 'includes/Session.php';
if (!Session::initialize()) {
    // Session was timed out, user will be redirected
    exit();
}

require_once 'config/database.php';
require_once 'includes/NotificationManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$notificationManager = new NotificationManager($conn);
$notifications = $notificationManager->getUnreadNotifications($_SESSION['user_id']);

// Mark notification as read if requested
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notificationManager->markNotificationAsRead($_POST['notification_id']);
    header("Location: notifications.php");
    exit;
}

// Get the count of unread notifications
$unreadCount = count($notifications);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Session Management -->
    <script src="js/session-manager.js"></script>
    <style>
        .notification-item {
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            transition: background-color 0.3s;
        }
        .notification-item:hover {
            background-color: #e9ecef;
        }
        .notification-item.unread {
            background-color: #e7f3ff;
        }
        .notification-time {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background: red;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'Head_and_Foot/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-bell"></i> Notifications 
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </h2>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You have no new notifications.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item p-3 rounded">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="notification-time">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                    <div class="mt-2">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                </div>
                                <form method="POST" class="ms-3">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-check2"></i> Mark as Read
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'Head_and_Foot/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
