
<?php
require_once 'includes/Session.php';
if (!Session::initialize()) {
    exit();
}
require_once 'config/database.php';
require_once 'includes/MessageUtility.php';

// Handle clearing of last report (for "Submit Another Report" functionality)
if (isset($_GET['new']) && $_GET['new'] == '1') {
    unset($_SESSION['last_report']);
    header("Location: report.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        MessageUtility::setErrorMessage("Please fill in all required fields.");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        MessageUtility::setErrorMessage("Please enter a valid email address.");
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Insert the report
            $stmt = $conn->prepare("INSERT INTO reports (user_id, name, email, subject, message, status) VALUES (?, ?, ?, ?, ?, 'new')");
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $stmt->bind_param("issss", $userId, $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $reportId = $conn->insert_id;
                
                // Try to send email, but don't let email failure affect the report submission
                try {
                    // Prepare email content
                    $to = $email;
                    $emailSubject = "Report Submission Confirmation - Railway System";
                    $emailMessage = "
                        <html>
                        <head>
                            <title>Report Submission Confirmation</title>
                        </head>
                        <body>
                            <h2>Thank you for your report</h2>
                            <p>Dear $name,</p>
                            <p>We have received your report with the following details:</p>
                            <p><strong>Subject:</strong> $subject</p>
                            <p><strong>Message:</strong><br><em>\"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"</em></p>
                            <p>Your Report ID is: <strong>#$reportId</strong></p>
                            <p>We will review your report and get back to you as soon as possible.</p>
                            <p>Best regards,<br>Railway System Team</p>
                        </body>
                        </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=utf-8\r\n";
                    $headers .= "From: Railway System <noreply@railway.com>\r\n";
                    
                    // Try to send email but don't throw error if it fails
                    @mail($to, $emailSubject, $emailMessage, $headers);
                } catch (Exception $emailError) {
                    // Log email error but continue with report submission
                    error_log("Failed to send confirmation email for report #$reportId: " . $emailError->getMessage());
                }
                
                $conn->commit();
                
                // Store report details in session for display
                $_SESSION['last_report'] = [
                    'id' => $reportId,
                    'name' => $name,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => $message,
                    'submitted_at' => date('Y-m-d H:i:s')
                ];
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                throw new Exception("Failed to submit report. Please try again.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            MessageUtility::setErrorMessage($e->getMessage());
        }
    }
}

// Get report details from session if available
$lastReport = $_SESSION['last_report'] ?? null;

// Clear the last report from session if it's older than 10 minutes to prevent permanent blocking
if ($lastReport && isset($lastReport['submitted_at'])) {
    $submittedTime = strtotime($lastReport['submitted_at']);
    $currentTime = time();
    // Clear if more than 10 minutes old
    if (($currentTime - $submittedTime) > 600) {
        unset($_SESSION['last_report']);
        $lastReport = null;
    }
}

// Transfer any session messages to MessageUtility
if (isset($_SESSION['success_message'])) {
    MessageUtility::setSuccessMessage($_SESSION['success_message']);
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    MessageUtility::setErrorMessage($_SESSION['error_message']);
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report a Problem - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style/style_report.css">
    <!-- Session Management -->
    <script src="js/session-manager.js"></script>
</head>
<body>
    <?php include 'Head_and_Foot/header.php'; ?>
    
    <div class="container">
        <div class="form-container">
            <h2>Report a Problem</h2>
            
            <?php MessageUtility::displayMessages(); ?>
            
            <?php if (!isset($_SESSION['last_report'])): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea class="form-control" id="message" name="message" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-submit">Submit Report</button>
            </form>            <?php else: ?>
                <div class="alert alert-success position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2" 
                            onclick="window.location.href='report.php?new=1'" 
                            aria-label="Close" title="Submit another report"></button>
                    <h4 class="alert-heading">Report Submitted Successfully!</h4>
                    <p>Report ID: <strong>#<?php echo htmlspecialchars($lastReport['id']); ?></strong></p>
                    <p>Please save this Report ID for future reference.</p>
                    <hr>
                    <div class="report-details">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($lastReport['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($lastReport['email']); ?></p>
                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($lastReport['subject']); ?></p>
                        <p><strong>Status:</strong> Under Review</p>
                    </div>                    <p class="note mb-0">Our team will review your report and respond via email.</p>
                </div>
                <a href="report.php?new=1" class="btn btn-primary">Submit Another Report</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'Head_and_Foot/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
