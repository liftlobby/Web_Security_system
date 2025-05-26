
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';

// Set timezone to match your server's timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Current time
$current_time = date('Y-m-d H:i:s');

// Query to get available schedules
$sql = "SELECT * FROM schedules 
        WHERE departure_time >= ? 
        AND available_seats > 0 
        AND train_status = 'on_time'
        ORDER BY departure_time ASC";

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_time);
$stmt->execute();
$result = $stmt->get_result();

// Store results in array for multiple use
$schedules = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticketing & Reservation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style/style_ticketing.css">
    <style>
        /* Emergency override styles */
        main.schedule-container {
            width: 100% !important;
            max-width: 1200px !important;
            margin: 20px auto !important;
            padding: 20px !important;
            display: block !important;
        }
        .schedule-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)) !important;
            gap: 20px !important;
            width: 100% !important;
        }
        .schedule-card {
            display: flex !important;
            flex-direction: column !important;
            background: white !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    </style>
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <main class="schedule-container">
        <h1 class="page-title">Available Train Tickets</h1>

        <?php if (!empty($schedules)): ?>
            <div class="schedule-grid">
                <?php $count = 0; foreach ($schedules as $schedule): ?>
                    <div class="schedule-card" id="card-<?php echo $count; ?>">
                        <div class="schedule-info">
                            <div class="schedule-time">
                                <strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($schedule['departure_time'])); ?><br>
                                <strong>Arrival:</strong> <?php echo date('d M Y, h:i A', strtotime($schedule['arrival_time'])); ?>
                            </div>
                            <div class="schedule-stations">
                                <strong>From:</strong> <?php echo htmlspecialchars($schedule['departure_station']); ?><br>
                                <strong>To:</strong> <?php echo htmlspecialchars($schedule['arrival_station']); ?>
                            </div>
                            <div class="train-info">
                                <strong>Train:</strong> <?php echo htmlspecialchars($schedule['train_number']); ?>
                            </div>
                            <div class="schedule-price">
                                <strong>Price:</strong> RM <?php echo number_format($schedule['price'], 2); ?>
                            </div>
                            <div class="seats-info">
                                <i class="fas fa-chair"></i>
                                <span><?php echo $schedule['available_seats']; ?> seats available</span>
                                <?php if (strtotime($schedule['departure_time']) <= strtotime('+30 minutes')): ?>
                                    <span class="status-badge closing">Closing Soon</span>
                                <?php else: ?>
                                    <span class="status-badge available">Available</span>
                                <?php endif; ?>
                            </div>
                            <form action="process_booking.php" method="POST" class="booking-form">
                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                <input type="hidden" name="price" value="<?php echo $schedule['price']; ?>">
                                <div class="ticket-quantity">
                                    <label for="ticket_quantity_<?php echo $schedule['schedule_id']; ?>">Number of Tickets:</label>
                                    <select name="ticket_quantity" 
                                            id="ticket_quantity_<?php echo $schedule['schedule_id']; ?>"
                                            onchange="updateTotalPrice(this, <?php echo $schedule['price']; ?>)">
                                        <?php for ($i = 1; $i <= min(4, $schedule['available_seats']); $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="total-price" id="total_price_<?php echo $schedule['schedule_id']; ?>">
                                        Total: RM <?php echo number_format($schedule['price'], 2); ?>
                                    </div>
                                </div>
                                <div class="passenger-info">
                                    <label for="passenger_name_<?php echo $schedule['schedule_id']; ?>">Passenger Name:</label>
                                    <input type="text" 
                                           name="passenger_name" 
                                           id="passenger_name_<?php echo $schedule['schedule_id']; ?>"
                                           required
                                           placeholder="Enter passenger name">
                                </div>
                                <button type="submit" class="book-button">Book Now</button>
                            </form>
                        </div>
                    </div>
                <?php $count++; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-schedules">
                <p>No available trains at the moment.</p>
                <p>Please check back later for new schedules.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once 'Head_and_Foot/footer.php'; ?>

    <script>
    function updateTotalPrice(select, basePrice) {
        const quantity = select.value;
        const totalPrice = (quantity * basePrice).toFixed(2);
        const scheduleId = select.id.split('_')[2];
        document.getElementById(`total_price_${scheduleId}`).innerHTML = `Total: RM ${totalPrice}`;
    }
    </script>
</body>
</html>