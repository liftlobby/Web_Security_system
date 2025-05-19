<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';
require_once '../includes/NotificationManager.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond') {
    try {
        $report_id = $_POST['report_id'];
        $response = $_POST['response'];
        $staff_id = $_SESSION['staff_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Update report status and add response
        $stmt = $conn->prepare("UPDATE reports SET status = 'responded', response = ?, response_date = NOW(), staff_id = ? WHERE report_id = ?");
        $stmt->bind_param("sii", $response, $staff_id, $report_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update report.");
        }
        
        // Get report details for email
        $stmt = $conn->prepare("SELECT r.email, r.name, r.subject FROM reports r WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        
        // Send email using NotificationManager
        $notificationManager = new NotificationManager($conn);
        try {
            $notificationManager->sendReportResponse($report['email'], $report['name'], $report['subject'], $response);
            $conn->commit();
            MessageUtility::setSuccessMessage("Response sent and email notification sent successfully!");
        } catch (Exception $e) {
            // If email fails, still save the response but notify staff
            $conn->commit();
            MessageUtility::setWarningMessage("Response saved but email notification failed: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        MessageUtility::setErrorMessage($e->getMessage());
    }
}

// Fetch all reports
$sql = "SELECT r.*, s.username as staff_name, 
        u.username as user_username 
        FROM reports r 
        LEFT JOIN staffs s ON r.staff_id = s.staff_id 
        LEFT JOIN users u ON r.user_id = u.user_id 
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            background-color: #343a40;
            color: white;
            width: 250px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: white;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-link:hover {
            color: #17a2b8;
        }
        .nav-link.active {
            background-color: #0056b3;
            color: white;
        }
        .report-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .report-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .report-body {
            padding: 15px;
            background-color: white;
        }
        .report-footer {
            padding: 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-new {
            background-color: #dc3545;
            color: white;
        }
        .status-responded {
            background-color: #28a745;
            color: white;
        }
        .response-form {
            margin-top: 15px;
            display: none;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .response-form.active {
            display: block;
        }
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .dashboard-header p {
            color: #666;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col content">
                <div class="dashboard-header">
                    <h2>Manage Reports</h2>
                    <p>View and respond to user reports</p>
                </div>
                
                <?php MessageUtility::displayMessages(); ?>
                
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($report = $result->fetch_assoc()): ?>
                        <div class="report-card">
                            <div class="report-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($report['subject']); ?></h5>
                                    <small class="text-muted">
                                        From: <?php echo htmlspecialchars($report['name']); ?> 
                                        (<?php echo htmlspecialchars($report['email']); ?>)
                                        <?php if ($report['user_username']): ?>
                                            - User: <?php echo htmlspecialchars($report['user_username']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="status-badge <?php echo $report['status'] === 'new' ? 'status-new' : 'status-responded'; ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                            </div>
                            <div class="report-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['message'])); ?></p>
                                <?php if ($report['status'] === 'responded'): ?>
                                    <hr>
                                    <div class="response-section">
                                        <h6>Response:</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['response'])); ?></p>
                                        <small class="text-muted">
                                            Responded by: <?php echo htmlspecialchars($report['staff_name']); ?> 
                                            on <?php echo date('d M Y, h:i A', strtotime($report['response_date'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($report['status'] === 'new'): ?>
                                <div class="report-footer">
                                    <button class="btn btn-primary btn-sm" onclick="showResponseForm(<?php echo $report['report_id']; ?>)">
                                        <i class='bx bx-reply'></i> Respond
                                    </button>
                                    <div class="response-form" id="responseForm<?php echo $report['report_id']; ?>">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="respond">
                                            <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Your Response</label>
                                                <textarea class="form-control" name="response" rows="4" required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-success">
                                                <i class='bx bx-send'></i> Send Response
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle me-2'></i> No reports found.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showResponseForm(reportId) {
            document.getElementById('responseForm' + reportId).classList.add('active');
        }
    </script>
</body>
</html>
