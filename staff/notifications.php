<?php
session_start();
require_once '../config/database.php';
require_once '../includes/NotificationManager.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$notificationManager = new NotificationManager($conn);

// Mark notifications as read if requested
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = $_POST['notification_id'];
    $notificationManager->markNotificationAsRead($notification_id);
}

// Get all notifications for staff
$notifications = $notificationManager->getNotifications($_SESSION['staff_id'], 'staff');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Notifications - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        
        .notification-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .notification-message {
            margin: 5px 0;
            white-space: pre-line;
        }
        
        .notification-actions {
            margin-top: 10px;
        }
        
        .no-notifications {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php require_once 'staff_header.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Notifications</h3>
                        <?php if (!empty($notifications)): ?>
                        <form action="" method="POST" class="d-inline">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                Mark All as Read
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="no-notifications">
                                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                                <p>No notifications at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="notification-type">
                                                <?php
                                                $icon = '';
                                                switch ($notification['type']) {
                                                    case 'delayed':
                                                        $icon = '<i class="fas fa-clock text-warning"></i>';
                                                        break;
                                                    case 'cancelled':
                                                        $icon = '<i class="fas fa-ban text-danger"></i>';
                                                        break;
                                                    case 'platform_change':
                                                        $icon = '<i class="fas fa-exchange-alt text-info"></i>';
                                                        break;
                                                    default:
                                                        $icon = '<i class="fas fa-info-circle text-primary"></i>';
                                                }
                                                echo $icon . ' ' . ucfirst($notification['type']);
                                                ?>
                                            </div>
                                            <div class="notification-message">
                                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                            </div>
                                            <div class="notification-time">
                                                <?php echo date('d M Y, h:i A', strtotime($notification['created_at'])); ?>
                                            </div>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                        <div class="notification-actions">
                                            <form action="" method="POST" class="d-inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                <input type="hidden" name="mark_read" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    Mark as Read
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'staff_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
