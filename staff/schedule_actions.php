<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';
require_once '../includes/NotificationManager.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Function to cleanup old schedules
function cleanupOldSchedules($conn) {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Get schedules that departed more than 30 minutes ago
        $sql = "SELECT schedule_id FROM schedules 
                WHERE departure_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                AND NOT EXISTS (
                    SELECT 1 FROM tickets 
                    WHERE tickets.schedule_id = schedules.schedule_id 
                    AND tickets.status = 'active'
                )";
                
        $result = $conn->query($sql);
        $deletedCount = 0;
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $schedule_id = $row['schedule_id'];
                
                // Delete auth_tokens first
                $stmt = $conn->prepare("
                    DELETE at FROM auth_tokens at
                    JOIN tickets t ON at.ticket_id = t.ticket_id
                    WHERE t.schedule_id = ?
                ");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();

                // Delete tickets
                $stmt = $conn->prepare("DELETE FROM tickets WHERE schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();

                // Delete schedule
                $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                
                $deletedCount++;
            }
        }

        // Commit transaction
        $conn->commit();
        
        if ($deletedCount > 0) {
            MessageUtility::setSuccessMessage("Cleaned up $deletedCount completed train schedules.");
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn) {
            $conn->rollback();
        }
        MessageUtility::setErrorMessage("Error cleaning up schedules: " . $e->getMessage());
    }
}

// Run cleanup before processing any actions
cleanupOldSchedules($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $train_number = $_POST['train_number'];
                $departure_station = $_POST['departure_station'];
                $arrival_station = $_POST['arrival_station'];
                $departure_time = $_POST['departure_time'];
                $arrival_time = $_POST['arrival_time'];
                $price = $_POST['price'];
                $platform_number = $_POST['platform_number'] ?? null;
                $available_seats = $_POST['available_seats'] ?? 100;

                $stmt = $conn->prepare("INSERT INTO schedules (train_number, departure_station, arrival_station, departure_time, arrival_time, platform_number, train_status, price, available_seats) VALUES (?, ?, ?, ?, ?, ?, 'on_time', ?, ?)");
                $stmt->bind_param("sssssidi", $train_number, $departure_station, $arrival_station, $departure_time, $arrival_time, $platform_number, $price, $available_seats);

                if ($stmt->execute()) {
                    MessageUtility::setSuccessMessage("Schedule added successfully!");
                } else {
                    MessageUtility::setErrorMessage("Error adding schedule: " . $conn->error);
                }
                break;

            case 'edit':
                $schedule_id = $_POST['schedule_id'];
                $train_number = $_POST['train_number'];
                $departure_station = $_POST['departure_station'];
                $arrival_station = $_POST['arrival_station'];
                $departure_time = $_POST['departure_time'];
                $arrival_time = $_POST['arrival_time'];
                $platform_number = $_POST['platform_number'];
                // Get current schedule details
                $stmt = $conn->prepare("SELECT * FROM schedules WHERE schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_schedule = $result->fetch_assoc();

                $stmt = $conn->prepare("UPDATE schedules SET train_number = ?, departure_station = ?, arrival_station = ?, departure_time = ?, arrival_time = ?, platform_number = ? WHERE schedule_id = ?");
                $stmt->bind_param("ssssssi", $train_number, $departure_station, $arrival_station, $departure_time, $arrival_time, $platform_number, $schedule_id);

                if ($stmt->execute()) {
                    // Get all active tickets for this schedule
                    $stmt = $conn->prepare("
                        SELECT t.ticket_id, t.user_id, u.email, u.username 
                        FROM tickets t 
                        JOIN users u ON t.user_id = u.user_id
                        WHERE t.schedule_id = ? AND t.status = 'active'
                    ");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();
                    $affected_tickets = $stmt->get_result();

                    if ($affected_tickets->num_rows > 0) {
                        // Create notification manager
                        $notificationManager = new NotificationManager($conn);

                        // Check what changed
                        $changes = array();
                        if ($platform_number !== $current_schedule['platform_number']) {
                            $changes[] = "Platform changed from {$current_schedule['platform_number']} to {$platform_number}";
                        }
                        if ($departure_time !== $current_schedule['departure_time']) {
                            $old_time = date('d M Y, h:i A', strtotime($current_schedule['departure_time']));
                            $new_time = date('d M Y, h:i A', strtotime($departure_time));
                            $changes[] = "Departure time changed from {$old_time} to {$new_time}";
                        }
                        if ($arrival_time !== $current_schedule['arrival_time']) {
                            $old_time = date('d M Y, h:i A', strtotime($current_schedule['arrival_time']));
                            $new_time = date('d M Y, h:i A', strtotime($arrival_time));
                            $changes[] = "Arrival time changed from {$old_time} to {$new_time}";
                        }
                        if ($train_number !== $current_schedule['train_number']) {
                            $changes[] = "Train number changed from {$current_schedule['train_number']} to {$train_number}";
                        }

                        if (!empty($changes)) {
                            // Format changes for HTML
                            $changesList = "<ul style='margin: 0; padding-left: 20px;'>";
                            foreach ($changes as $change) {
                                $changesList .= "<li style='margin-bottom: 8px;'>{$change}</li>";
                            }
                            $changesList .= "</ul>";
                            
                            // Send notification to each ticket holder
                            while ($ticket = $affected_tickets->fetch_assoc()) {
                                // Prepare ticket details with changes
                                $notificationDetails = array(
                                    'train_number' => $train_number,
                                    'departure_station' => $departure_station,
                                    'arrival_station' => $arrival_station,
                                    'departure_time' => $departure_time,
                                    'arrival_time' => $arrival_time,
                                    'platform_number' => $platform_number,
                                    'user_name' => $ticket['username'],
                                    'changes_list' => $changesList
                                );
                                
                                // Send notification
                                $notificationManager->sendTicketStatusNotification(
                                    $ticket['ticket_id'],
                                    'schedule_change',
                                    $notificationDetails
                                );
                            }
                        }
                    }

                    MessageUtility::setSuccessMessage("Schedule updated successfully!");
                } else {
                    MessageUtility::setErrorMessage("Error updating schedule: " . $conn->error);
                }
                break;

            case 'delete':
                $schedule_id = $_POST['schedule_id'];
                
                try {
                    // Start transaction
                    $conn->begin_transaction();

                    // First check if there are any active tickets for this schedule
                    $stmt = $conn->prepare("SELECT COUNT(*) as ticket_count FROM tickets WHERE schedule_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();

                    if ($row['ticket_count'] > 0) {
                        throw new Exception("Cannot delete schedule: There are active tickets for this schedule.");
                    }

                    // Delete auth_tokens first
                    $stmt = $conn->prepare("
                        DELETE at FROM auth_tokens at
                        JOIN tickets t ON at.ticket_id = t.ticket_id
                        WHERE t.schedule_id = ?
                    ");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();

                    // Delete tickets
                    $stmt = $conn->prepare("DELETE FROM tickets WHERE schedule_id = ?");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();

                    // Finally delete the schedule
                    $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id = ?");
                    $stmt->bind_param("i", $schedule_id);
                    $stmt->execute();

                    // Commit transaction
                    $conn->commit();
                    MessageUtility::setSuccessMessage("Schedule deleted successfully!");

                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    MessageUtility::setErrorMessage($e->getMessage());
                }
                break;

            case 'update_status':
                $schedule_id = $_POST['schedule_id'];
                $train_status = $_POST['train_status'];
                $stmt = $conn->prepare("UPDATE schedules SET train_status = ? WHERE schedule_id = ?");
                $stmt->bind_param("si", $train_status, $schedule_id);

                if ($stmt->execute()) {
                    MessageUtility::setSuccessMessage("Schedule status updated successfully!");
                } else {
                    MessageUtility::setErrorMessage("Error updating schedule status: " . $conn->error);
                }
                break;
        }
    }
}

header("Location: manage_schedules.php");
exit();
?>
