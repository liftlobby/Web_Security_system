
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'Head_and_Foot/header.php';
require_once 'config/database.php';

// Get filter parameters
$departure = isset($_GET['departure']) ? $_GET['departure'] : '';
$arrival = isset($_GET['arrival']) ? $_GET['arrival'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Base query
$query = "SELECT * FROM schedules WHERE 1=1";
$params = [];
$types = "";

// Add filters if provided
if (!empty($departure)) {
    $query .= " AND departure_station LIKE ?";
    $params[] = "%$departure%";
    $types .= "s";
}
if (!empty($arrival)) {
    $query .= " AND arrival_station LIKE ?";
    $params[] = "%$arrival%";
    $types .= "s";
}
if (!empty($date)) {
    $query .= " AND DATE(departure_time) = ?";
    $params[] = $date;
    $types .= "s";
}

$query .= " ORDER BY departure_time ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$schedules = $stmt->get_result();

// Get unique stations for dropdowns
$stations_query = "SELECT DISTINCT departure_station FROM schedules 
                  UNION 
                  SELECT DISTINCT arrival_station FROM schedules 
                  ORDER BY departure_station";
$stations = $conn->query($stations_query);
$station_list = [];
while ($station = $stations->fetch_assoc()) {
    $station_list[] = $station['departure_station'];
}
?>

<div class="container">
    <h2 class="mb-4">Train Schedules</h2>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="departure" class="form-label">Departure Station</label>
                    <select class="form-select" name="departure" id="departure">
                        <option value="">All Stations</option>
                        <?php foreach ($station_list as $station): ?>
                            <option value="<?php echo htmlspecialchars($station); ?>" 
                                    <?php echo $departure === $station ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($station); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="arrival" class="form-label">Arrival Station</label>
                    <select class="form-select" name="arrival" id="arrival">
                        <option value="">All Stations</option>
                        <?php foreach ($station_list as $station): ?>
                            <option value="<?php echo htmlspecialchars($station); ?>"
                                    <?php echo $arrival === $station ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($station); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" id="date" 
                           value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="schedules.php" class="btn btn-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedules Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Train</th>
                    <th>Departure Station</th>
                    <th>Departure Time</th>
                    <th>Arrival Station</th>
                    <th>Arrival Time</th>
                    <th>Price (RM)</th>
                    <th>Status</th>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($schedule = $schedules->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($schedule['train_number']); ?></td>
                        <td><?php echo htmlspecialchars($schedule['departure_station']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($schedule['departure_time'])); ?></td>
                        <td><?php echo htmlspecialchars($schedule['arrival_station']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($schedule['arrival_time'])); ?></td>
                        <td><?php echo number_format($schedule['price'], 2); ?></td>
                        <td>
                            <?php
                            $now = new DateTime();
                            $departure = new DateTime($schedule['departure_time']);
                            if ($departure < $now) {
                                echo '<span class="badge bg-secondary">Departed</span>';
                            } else {
                                echo '<span class="badge bg-success">Scheduled</span>';
                            }
                            ?>
                        </td>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <td>
                            <a href="ticketing.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" 
                               class="btn btn-sm btn-primary">
                                Book Now
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'Head_and_Foot/footer.php'; ?>
