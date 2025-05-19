
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and ticket_id is provided
if (!isset($_SESSION['user_id']) || !isset($_GET['ticket_id'])) {
    header("Location: history.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['ticket_id'];

try {
    // Fetch ticket details
    $sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
                   s.departure_time, s.arrival_time, s.price, s.platform_number
            FROM tickets t
            JOIN schedules s ON t.schedule_id = s.schedule_id
            WHERE t.ticket_id = ? AND t.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Ticket not found or unauthorized access");
    }

    $ticket = $result->fetch_assoc();

    // Create ticket content as HTML
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Train Ticket #<?php echo $ticket['ticket_id']; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
                background: #f0f0f0;
            }
            .ticket-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .ticket-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 20px;
            }
            .ticket-header h1 {
                color: #003366;
                margin: 0;
            }
            .ticket-details {
                margin: 20px 0;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .ticket-details table {
                width: 100%;
                border-collapse: collapse;
            }
            .ticket-details td {
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .ticket-details td:first-child {
                font-weight: bold;
                width: 150px;
            }
            .qr-code {
                text-align: center;
                margin: 30px 0;
            }
            .qr-code img {
                width: 150px;
                height: 150px;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                color: #666;
                font-size: 0.9em;
            }
            @media print {
                body {
                    background: white;
                }
                .ticket-container {
                    box-shadow: none;
                }
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="ticket-container">
            <div class="ticket-header">
                <h1>Railway Ticket</h1>
                <h2>Train <?php echo htmlspecialchars($ticket['train_number']); ?></h2>
            </div>

            <div class="ticket-details">
                <table>
                    <tr>
                        <td>Ticket ID:</td>
                        <td>#<?php echo htmlspecialchars($ticket['ticket_id']); ?></td>
                    </tr>
                    <tr>
                        <td>From:</td>
                        <td><?php echo htmlspecialchars($ticket['departure_station']); ?></td>
                    </tr>
                    <tr>
                        <td>To:</td>
                        <td><?php echo htmlspecialchars($ticket['arrival_station']); ?></td>
                    </tr>
                    <tr>
                        <td>Departure:</td>
                        <td><?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></td>
                    </tr>
                    <tr>
                        <td>Arrival:</td>
                        <td><?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></td>
                    </tr>
                    <?php if (!empty($ticket['seat_number'])): ?>
                    <tr>
                        <td>Seat Number:</td>
                        <td><?php echo htmlspecialchars($ticket['seat_number']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Platform:</td>
                        <td><?php echo htmlspecialchars($ticket['platform_number']); ?></td>
                    </tr>
                    <tr>
                        <td>Price:</td>
                        <td>RM <?php echo number_format($ticket['price'], 2); ?></td>
                    </tr>
                </table>
            </div>

            <div class="qr-code">
                <img src="generate_qr.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" alt="Ticket QR Code">
                <p><small>Show this QR code at the station gate for entry</small></p>
            </div>

            <div class="footer">
                <p>This is an electronically generated ticket.</p>
                <p>Thank you for choosing Railway!</p>
            </div>

            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="
                    padding: 10px 20px;
                    background: #003366;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                    margin-right: 10px;">
                    Print Ticket
                </button>
                <a href="history.php" style="
                    padding: 10px 20px;
                    background: #28a745;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                    text-decoration: none;
                    display: inline-block;">
                    Back to History
                </a>
            </div>
        </div>

        <script>
            // Auto-trigger print dialog when page loads
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: history.php");
    exit();
}
?>
