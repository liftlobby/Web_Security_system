<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify':
                $ticket_id = $_POST['ticket_id'];
                $staff_id = $_SESSION['staff_id'];

                // First check if ticket exists and is active
                $stmt = $conn->prepare("SELECT status FROM tickets WHERE ticket_id = ?");
                $stmt->bind_param("i", $ticket_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    MessageUtility::setErrorMessage("Ticket not found.");
                } else {
                    $ticket = $result->fetch_assoc();
                    if ($ticket['status'] !== 'active') {
                        MessageUtility::setErrorMessage("This ticket is " . $ticket['status'] . " and cannot be verified.");
                    } else {
                        // Start transaction
                        $conn->begin_transaction();
                        try {
                            // Update ticket status
                            $stmt = $conn->prepare("UPDATE tickets SET status = 'completed', updated_at = NOW() WHERE ticket_id = ?");
                            $stmt->bind_param("i", $ticket_id);
                            $stmt->execute();
                            
                            // Log verification
                            $stmt = $conn->prepare("INSERT INTO ticket_verifications (ticket_id, staff_id, verification_time, status) VALUES (?, ?, NOW(), 'success')");
                            $stmt->bind_param("ii", $ticket_id, $staff_id);
                            $stmt->execute();

                            $conn->commit();
                            MessageUtility::setSuccessMessage("Ticket verified successfully!");
                        } catch (Exception $e) {
                            $conn->rollback();
                            MessageUtility::setErrorMessage("Error verifying ticket: " . $e->getMessage());
                        }
                    }
                }
                break;

            case 'cancel':
                $ticket_id = $_POST['ticket_id'];
                $reason = $_POST['reason'] ?? 'Cancelled by staff';

                // Start transaction
                $conn->begin_transaction();
                try {
                    // First check if ticket exists and can be cancelled
                    $stmt = $conn->prepare("SELECT t.status, t.payment_status, t.payment_amount, t.user_id FROM tickets t WHERE t.ticket_id = ?");
                    $stmt->bind_param("i", $ticket_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $ticket = $result->fetch_assoc();

                    if (!$ticket) {
                        throw new Exception("Ticket not found.");
                    }

                    if ($ticket['status'] !== 'active') {
                        throw new Exception("This ticket cannot be cancelled as it is already " . $ticket['status']);
                    }

                    // Update ticket status
                    $stmt = $conn->prepare("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE ticket_id = ?");
                    $stmt->bind_param("i", $ticket_id);
                    $stmt->execute();

                    // If ticket was paid, create refund record
                    if ($ticket['payment_status'] === 'paid') {
                        $stmt = $conn->prepare("INSERT INTO refunds (ticket_id, amount, refund_date, status, reason, processed_by) VALUES (?, ?, NOW(), 'pending', ?, ?)");
                        $stmt->bind_param("idsi", $ticket_id, $ticket['payment_amount'], $reason, $_SESSION['staff_id']);
                        $stmt->execute();

                        // Update ticket payment status
                        $stmt = $conn->prepare("UPDATE tickets SET payment_status = 'refunded' WHERE ticket_id = ?");
                        $stmt->bind_param("i", $ticket_id);
                        $stmt->execute();
                    }

                    // Update schedule available seats
                    $stmt = $conn->prepare("UPDATE schedules s JOIN tickets t ON s.schedule_id = t.schedule_id SET s.available_seats = s.available_seats + 1 WHERE t.ticket_id = ?");
                    $stmt->bind_param("i", $ticket_id);
                    $stmt->execute();

                    $conn->commit();
                    MessageUtility::setSuccessMessage("Ticket cancelled successfully!");
                } catch (Exception $e) {
                    $conn->rollback();
                    MessageUtility::setErrorMessage("Error cancelling ticket: " . $e->getMessage());
                }
                break;
        }
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
