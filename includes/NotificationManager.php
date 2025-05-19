
<?php
// Include PHPMailer files
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class NotificationManager {
    private $conn;
    private $mailer;

    public function __construct($conn) {
        if (!$conn) {
            require_once __DIR__ . '/../config/database.php';
        }
        $this->conn = $conn ?? $GLOBALS['conn'];
        $this->initializeMailer();
    }

    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'kaizen20020222@gmail.com';
        $this->mailer->Password = 'vqfv fism uusu pswn';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->setFrom('kaizen20020222@gmail.com', 'Railway System');
    }

    private function getTicketDetails($ticketId) {
        $sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
                       s.departure_time, s.arrival_time, s.platform_number,
                       u.email, u.username as user_name
                FROM tickets t
                JOIN schedules s ON t.schedule_id = s.schedule_id
                JOIN users u ON t.user_id = u.user_id
                WHERE t.ticket_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getEmailTemplate($type, $ticketDetails) {
        $template = [
            'subject' => '',
            'message' => ''
        ];

        switch ($type) {
            case 'booked':
                $template['subject'] = "Ticket Booking Confirmation - Train {$ticketDetails['train_number']}";
                $template['message'] = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: #003366; color: white; padding: 20px; text-align: center;'>
                        <h2 style='margin:0;'>Booking Confirmation</h2>
                    </div>
                    <div style='padding: 20px; background-color: #f8f9fa;'>
                        <p>Dear {$ticketDetails['user_name']},</p>
                        <p>Thank you for choosing Railway System. Your ticket has been confirmed!</p>
                        
                        <div style='background-color: white; padding: 15px; border-left: 4px solid #003366; margin: 20px 0;'>
                            <h3 style='color: #003366; margin-top: 0;'>Booking Details</h3>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Ticket ID:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['ticket_id']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Train Number:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['train_number']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>From:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['departure_station']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>To:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['arrival_station']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Departure:</strong></td>
                                    <td style='padding: 8px 0;'>" . date('d M Y, h:i A', strtotime($ticketDetails['departure_time'])) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Arrival:</strong></td>
                                    <td style='padding: 8px 0;'>" . date('d M Y, h:i A', strtotime($ticketDetails['arrival_time'])) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Platform:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['platform_number']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Passenger:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['passenger_name']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Seat(s):</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['seat_number']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Number of Tickets:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['num_seats']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Total Amount:</strong></td>
                                    <td style='padding: 8px 0;'>RM " . number_format($ticketDetails['payment_amount'], 2) . "</td>
                                </tr>
                            </table>
                        </div>

                        <div style='background-color: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                            <h3 style='color: #003366; margin-top: 0;'>Important Notes</h3>
                            <ul style='margin: 0; padding-left: 20px; color: #666;'>
                                <li style='margin-bottom: 8px;'>Please arrive at the station at least 30 minutes before departure</li>
                                <li style='margin-bottom: 8px;'>Keep this ticket safe and present it during inspection</li>
                                <li style='margin-bottom: 8px;'>Your e-ticket has been attached to this email</li>
                                <li style='margin-bottom: 0;'>For cancellations, please visit our website at least 24 hours before departure</li>
                            </ul>
                        </div>
                        
                        <p style='margin-top: 20px;'>Best regards,<br>Railway System</p>
                    </div>
                    <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                        This is an automated notification. Please do not reply to this email.
                    </div>
                </div>";
                break;

            case 'cancelled':
                $template['subject'] = "Ticket Cancellation Confirmation - Train {$ticketDetails['train_number']}";
                $template['message'] = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: #003366; color: white; padding: 20px; text-align: center;'>
                        <h2 style='margin:0;'>Ticket Cancellation Confirmation</h2>
                    </div>
                    <div style='padding: 20px; background-color: #f8f9fa;'>
                        <p>Dear {$ticketDetails['user_name']},</p>
                        <p>Your ticket has been successfully cancelled.</p>
                        
                        <div style='background-color: white; padding: 15px; border-left: 4px solid #003366; margin: 20px 0;'>
                            <h3 style='color: #003366; margin-top: 0;'>Cancelled Ticket Details</h3>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Ticket ID:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['ticket_id']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Train Number:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['train_number']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>From:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['departure_station']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>To:</strong></td>
                                    <td style='padding: 8px 0;'>{$ticketDetails['arrival_station']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Original Departure:</strong></td>
                                    <td style='padding: 8px 0;'>" . date('d M Y, h:i A', strtotime($ticketDetails['departure_time'])) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0;'><strong>Refund Amount:</strong></td>
                                    <td style='padding: 8px 0;'>RM " . number_format($ticketDetails['payment_amount'], 2) . "</td>
                                </tr>
                            </table>
                        </div>

                        <div style='background-color: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                            <h3 style='color: #003366; margin-top: 0;'>Refund Information</h3>
                            <p style='margin: 0; color: #666;'>Visit any station with your booking confirmation and valid ID to collect your refund within 7 working days.</p>
                        </div>

                        <p style='margin-top: 20px;'>Best regards,<br>Railway System</p>
                    </div>
                    <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                        This is an automated notification. Please do not reply to this email.
                    </div>
                </div>";
                break;

            case 'delayed':
                $template['subject'] = "Important: Train Delay Notice - {$ticketDetails['train_number']}";
                $template['message'] = "Dear {$ticketDetails['user_name']},\n\n";
                $template['message'] .= "We regret to inform you that your train has been delayed.\n\n";
                $template['message'] .= "Affected Train Details:\n";
                $template['message'] .= "Train Number: {$ticketDetails['train_number']}\n";
                $template['message'] .= "From: {$ticketDetails['departure_station']}\n";
                $template['message'] .= "To: {$ticketDetails['arrival_station']}\n";
                $template['message'] .= "Original Departure: " . date('d M Y, h:i A', strtotime($ticketDetails['departure_time'])) . "\n";
                $template['message'] .= "New Departure: " . date('d M Y, h:i A', strtotime($ticketDetails['new_departure_time'])) . "\n\n";
                $template['message'] .= "We apologize for any inconvenience caused.\n";
                break;

            case 'platform_change':
                $template['subject'] = "Platform Change Notice - {$ticketDetails['train_number']}";
                $template['message'] = "Dear {$ticketDetails['user_name']},\n\n";
                $template['message'] .= "Please note that there has been a platform change for your train.\n\n";
                $template['message'] .= "Train Details:\n";
                $template['message'] .= "Train Number: {$ticketDetails['train_number']}\n";
                $template['message'] .= "From: {$ticketDetails['departure_station']}\n";
                $template['message'] .= "To: {$ticketDetails['arrival_station']}\n";
                $template['message'] .= "Departure: " . date('d M Y, h:i A', strtotime($ticketDetails['departure_time'])) . "\n";
                $template['message'] .= "New Platform: {$ticketDetails['platform_number']}\n\n";
                $template['message'] .= "Please proceed to the new platform.\n";
                break;

            case 'schedule_change':
                $template['subject'] = "Schedule Change Notice - Train {$ticketDetails['train_number']}";
                $template['message'] = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: #003366; color: white; padding: 20px; text-align: center;'>
                        <h2 style='margin:0;'>Schedule Change Notice</h2>
                    </div>
                    <div style='padding: 20px; background-color: #f8f9fa;'>
                        <p>Dear {$ticketDetails['user_name']},</p>
                        <p>There have been changes to your train schedule. Please review the details below:</p>
                        
                        <div style='background-color: white; padding: 15px; border-left: 4px solid #003366; margin: 20px 0;'>
                            <h3 style='color: #003366; margin-top: 0;'>Train Details</h3>
                            <p><strong>Train Number:</strong> {$ticketDetails['train_number']}</p>
                            <p><strong>Route:</strong> {$ticketDetails['departure_station']} to {$ticketDetails['arrival_station']}</p>
                        </div>

                        <div style='background-color: white; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;'>
                            <h3 style='color: #ff9800; margin-top: 0;'>Schedule Changes</h3>
                            {$ticketDetails['changes_list']}
                        </div>

                        <div style='background-color: white; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;'>
                            <h3 style='color: #4CAF50; margin-top: 0;'>Updated Schedule</h3>
                            <p><strong>Departure:</strong> " . date('d M Y, h:i A', strtotime($ticketDetails['departure_time'])) . "</p>
                            <p><strong>Arrival:</strong> " . date('d M Y, h:i A', strtotime($ticketDetails['arrival_time'])) . "</p>
                            <p><strong>Platform:</strong> {$ticketDetails['platform_number']}</p>
                        </div>

                        <p style='color: #666;'><strong>Important:</strong> Please arrive at the station at least 30 minutes before the departure time.</p>

                        
                        <p style='margin-top: 20px;'>Best regards,<br>Railway System</p>
                    </div>
                    <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                        This is an automated notification. Please do not reply to this email.
                    </div>
                </div>";
                break;
        }

        $template['message'] .= "\nFor any assistance, please contact our customer service or visit the nearest counter.\n\n";
        $template['message'] .= "Thank you for choosing Railway System.\n";
        $template['message'] .= "This is an automated message, please do not reply.";

        return $template;
    }

    public function sendTicketStatusNotification($ticketId, $type, $messageOrDetails) {
        try {
            $ticketDetails = $this->getTicketDetails($ticketId);
            if (!$ticketDetails) {
                error_log("Error: Ticket details not found for ID: $ticketId");
                return false;
            }

            // Get email template
            if (is_array($messageOrDetails)) {
                // Merge ticket details with provided details
                $ticketDetails = array_merge($ticketDetails, $messageOrDetails);
            }
            
            $template = $this->getEmailTemplate($type, $ticketDetails);
            
            // Create notification in database with plain text version
            $plainTextMessage = is_array($messageOrDetails) ? 
                "Schedule changes for Train {$ticketDetails['train_number']}" : 
                strip_tags($messageOrDetails);
            $this->createNotification($ticketDetails['user_id'], $type, $plainTextMessage, $ticketId);

            // Send email
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($ticketDetails['email']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['message'];
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</div>', '</p>'], "\n", $template['message']));

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    public function createNotification($userId, $type, $message, $relatedId = null) {
        try {
            if (!$this->conn) {
                return false;
            }
            
            $query = "INSERT INTO notifications (user_id, type, message, related_id, created_at, is_read) 
                     VALUES (?, ?, ?, ?, NOW(), 0)";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparing statement: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("issi", $userId, $type, $message, $relatedId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendTrainDelayNotification($scheduleId, $delayMinutes, $reason = '') {
        // Get affected tickets and users
        try {
            if (!$this->conn) {
                return false;
            }
            
            $query = "SELECT t.ticket_id, t.user_id, u.email, u.username, u.no_phone, 
                            s.train_number, s.departure_station, s.arrival_station, s.departure_time 
                     FROM tickets t 
                     JOIN users u ON t.user_id = u.user_id 
                     JOIN schedules s ON t.schedule_id = s.schedule_id 
                     WHERE t.schedule_id = ? AND t.status = 'active'";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparing statement: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("i", $scheduleId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($ticket = $result->fetch_assoc()) {
                // Create delay message
                $message = $this->createDelayMessage($ticket, $delayMinutes, $reason);

                // Save to database
                $this->createNotification($ticket['user_id'], 'train_delay', $message, $ticket['ticket_id']);

                // Send email
                $this->sendEmail($ticket['email'], "Train Delay - {$ticket['train_number']}", $message);

                // Send SMS if phone number is available
                if (!empty($ticket['no_phone'])) {
                    $this->sendSMS($ticket['no_phone'], $message);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error sending train delay notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendTrainCancellationNotification($scheduleId, $reason = '') {
        // Get affected tickets and users
        try {
            if (!$this->conn) {
                return false;
            }
            
            $query = "SELECT t.ticket_id, t.user_id, u.email, u.username, u.no_phone, 
                            s.train_number, s.departure_station, s.arrival_station, s.departure_time 
                     FROM tickets t 
                     JOIN users u ON t.user_id = u.user_id 
                     JOIN schedules s ON t.schedule_id = s.schedule_id 
                     WHERE t.schedule_id = ? AND t.status = 'active'";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparing statement: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("i", $scheduleId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($ticket = $result->fetch_assoc()) {
                // Create cancellation message
                $message = $this->createCancellationMessage($ticket, $reason);

                // Save to database
                $this->createNotification($ticket['user_id'], 'train_cancellation', $message, $ticket['ticket_id']);

                // Send email
                $this->sendEmail($ticket['email'], "Train Cancellation - {$ticket['train_number']}", $message);

                // Send SMS if phone number is available
                if (!empty($ticket['no_phone'])) {
                    $this->sendSMS($ticket['no_phone'], $message);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error sending train cancellation notification: " . $e->getMessage());
            return false;
        }
    }

    private function createDelayMessage($ticket, $delayMinutes, $reason) {
        $message = "Dear {$ticket['username']},\n\n";
        $message .= "Your train has been delayed:\n";
        $message .= "Train: {$ticket['train_number']}\n";
        $message .= "Route: {$ticket['departure_station']} to {$ticket['arrival_station']}\n";
        $message .= "Original Departure: " . date('d M Y, h:i A', strtotime($ticket['departure_time'])) . "\n";
        $message .= "Delay: {$delayMinutes} minutes\n";
        
        if (!empty($reason)) {
            $message .= "Reason: {$reason}\n";
        }

        $message .= "\nWe apologize for any inconvenience caused. For more information, please log in to your account or contact our support.";
        return $message;
    }

    private function createCancellationMessage($ticket, $reason) {
        $message = "Dear {$ticket['username']},\n\n";
        $message .= "Unfortunately, your train has been cancelled:\n";
        $message .= "Train: {$ticket['train_number']}\n";
        $message .= "Route: {$ticket['departure_station']} to {$ticket['arrival_station']}\n";
        $message .= "Scheduled Departure: " . date('d M Y, h:i A', strtotime($ticket['departure_time'])) . "\n";
        
        if (!empty($reason)) {
            $message .= "Reason: {$reason}\n";
        }

        $message .= "\nPlease log in to your account for refund information or contact our support for assistance.";
        return $message;
    }

    private function sendEmail($to, $subject, $message) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true); // Enable HTML email
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            // Set plain text version for non-HTML mail clients
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</div>', '</p>'], "\n", $message));
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    private function sendSMS($phoneNumber, $message) {
        // Further exploration
        return true;
    }

    public function getUnreadNotifications($userId) {
        try {
            if (!$this->conn) {
                return [];
            }
            
            $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparing statement: " . $this->conn->error);
                return [];
            }
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }

    public function markNotificationAsRead($notificationId) {
        try {
            if (!$this->conn) {
                return false;
            }
            
            $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparing statement: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("i", $notificationId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    public function sendReportResponse($email, $name, $subject, $response) {
        $emailSubject = "Re: " . $subject . " - Railway System";
        $emailMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #003366; color: white; padding: 20px; text-align: center;'>
                <h2>Railway System Response</h2>
            </div>
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Dear {$name},</p>
                <p>Thank you for your report regarding \"{$subject}\". Here is our response:</p>
                
                <div style='background-color: white; padding: 15px; border-left: 4px solid #003366; margin: 20px 0;'>
                    " . nl2br(htmlspecialchars($response)) . "
                </div>
                <p>If you have any further questions, please don't hesitate to contact us.</p>
                <p>Best regards,<br>Railway System Staff</p>
            </div>
            <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                This is an automated response. Please do not reply to this email.
            </div>
        </div>";
        
        return $this->sendEmail($email, $emailSubject, $emailMessage);
    }

    public function sendScheduleChangeNotification($email, $username, $subject, $message) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            
            // Create HTML version of the message
            $htmlMessage = nl2br(htmlspecialchars($message));
            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center;'>
                    <h2 style='color: #0056b3;'>Schedule Update</h2>
                </div>
                <div style='padding: 20px;'>
                    <p>Dear {$username},</p>
                    <p>We're writing to inform you about changes to your upcoming train journey.</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        {$htmlMessage}
                    </div>
                    <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
                    <p>Best regards,<br>Management</p>
                </div>
                <div style='background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                    <p>This is an automated message from Railway System. Please do not reply to this email.</p>
                </div>
            </div>";
            
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $message;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error sending schedule change email: " . $e->getMessage());
            return false;
        }
    }
}
?>
