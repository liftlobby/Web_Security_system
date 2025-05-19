<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';

// Check if staff is logged in and is admin
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Check if the logged-in staff is admin
$staff_id = $_SESSION['staff_id'];
$admin_check = $conn->prepare("SELECT role FROM staffs WHERE staff_id = ?");
$admin_check->bind_param("i", $staff_id);
$admin_check->execute();
$is_admin = $admin_check->get_result()->fetch_assoc()['role'] === 'admin';

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        
        if ($_POST['action'] === 'update') {
            // Update user information
            $username = $_POST['username'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, no_phone = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $username, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                MessageUtility::setSuccessMessage("User information updated successfully!");
            } else {
                MessageUtility::setErrorMessage("Failed to update user information.");
            }
        } elseif ($_POST['action'] === 'suspend') {
            // Suspend user account
            $stmt = $conn->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                MessageUtility::setSuccessMessage("User account suspended successfully!");
            } else {
                MessageUtility::setErrorMessage("Failed to suspend user account.");
            }
        } elseif ($_POST['action'] === 'activate') {
            // Activate user account
            $stmt = $conn->prepare("UPDATE users SET account_status = 'active', failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                MessageUtility::setSuccessMessage("User account activated successfully!");
            } else {
                MessageUtility::setErrorMessage("Failed to activate user account.");
            }
        } elseif ($_POST['action'] === 'delete') {
            try {
                // Start transaction
                $conn->begin_transaction();

                // First, check if user has any active tickets
                $check_tickets = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND status = 'active'");
                $check_tickets->bind_param("i", $user_id);
                $check_tickets->execute();
                $active_tickets = $check_tickets->get_result()->fetch_assoc()['count'];

                if ($active_tickets > 0) {
                    throw new Exception("Cannot delete user with active tickets.");
                }

                // Delete user's tickets first (to maintain referential integrity)
                $delete_tickets = $conn->prepare("DELETE FROM tickets WHERE user_id = ?");
                $delete_tickets->bind_param("i", $user_id);
                if (!$delete_tickets->execute()) {
                    throw new Exception("Failed to delete user's tickets.");
                }

                // Delete user's reports
                $delete_reports = $conn->prepare("DELETE FROM reports WHERE user_id = ?");
                $delete_reports->bind_param("i", $user_id);
                if (!$delete_reports->execute()) {
                    throw new Exception("Failed to delete user's reports.");
                }

                // Finally, delete the user
                $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $delete_user->bind_param("i", $user_id);
                if (!$delete_user->execute()) {
                    throw new Exception("Failed to delete user.");
                }

                $conn->commit();
                MessageUtility::setSuccessMessage("User and all associated data deleted successfully!");
            } catch (Exception $e) {
                $conn->rollback();
                MessageUtility::setErrorMessage($e->getMessage());
            }
        } elseif ($_POST['action'] === 'add') {
            // Add new user
            $username = $_POST['username'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, no_phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $phone, $password);
            
            if ($stmt->execute()) {
                MessageUtility::setSuccessMessage("New user added successfully!");
            } else {
                MessageUtility::setErrorMessage("Failed to add new user.");
            }
        }
    }
}

// Fetch all users with their ticket counts
$sql = "SELECT u.*, 
        COUNT(DISTINCT t.ticket_id) as total_tickets,
        SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tickets
        FROM users u 
        LEFT JOIN tickets t ON u.user_id = t.user_id 
        GROUP BY u.user_id 
        ORDER BY u.created_at DESC";
$users = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Railway System</title>
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
        .user-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .user-status.active {
            background-color: #d4edda;
            color: #155724;
        }
        .user-status.locked {
            background-color: #fff3cd;
            color: #856404;
        }
        .user-status.suspended {
            background-color: #f8d7da;
            color: #721c24;
        }
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: none;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .modal-content {
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .modal-header {
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        .modal-body h6 {
            color: #0056b3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col content">
                <div class="dashboard-header">
                    <h2>Manage Users</h2>
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="text-muted mb-0">View and manage user accounts</p>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class='bx bx-user-plus'></i> Add New User
                            </button>
                        </div>
                    </div>
                </div>

                <?php MessageUtility::displayMessages(); ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Tickets</th>
                                        <th>Registration Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['no_phone']); ?></td>
                                        <td>
                                            <span class="user-status <?php echo $user['account_status']; ?>">
                                                <?php echo ucfirst($user['account_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['active_tickets']; ?> active / <?php echo $user['total_tickets']; ?> total
                                        </td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $user['user_id']; ?>">
                                                    <i class='bx bx-info-circle'></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $user['user_id']; ?>">
                                                    <i class='bx bx-edit'></i>
                                                </button>
                                                <?php if ($user['account_status'] === 'active'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class='bx bx-block'></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class='bx bx-check'></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <!-- Delete User Button -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('WARNING: This will permanently delete the user and all their data. This action cannot be undone. Are you sure you want to proceed?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-danger btn-sm" <?php echo $user['account_status'] === 'active' ? 'disabled title="Cannot delete user with active account"' : ''; ?>>
                                                        <i class='bx bx-trash'></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- User Details Modal -->
                                    <div class="modal fade" id="detailsModal<?php echo $user['user_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">User Details #<?php echo $user['user_id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-4">
                                                        <h6>Basic Information</h6>
                                                        <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($user['no_phone']); ?></p>
                                                    </div>
                                                    <div class="mb-4">
                                                        <h6>Account Status</h6>
                                                        <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst($user['account_status']); ?></p>
                                                        <p class="mb-1"><strong>Failed Attempts:</strong> <?php echo $user['failed_attempts']; ?></p>
                                                        <?php if ($user['locked_until']): ?>
                                                        <p class="mb-0"><strong>Locked Until:</strong> <?php echo date('d M Y, h:i A', strtotime($user['locked_until'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-4">
                                                        <h6>Activity Information</h6>
                                                        <p class="mb-1"><strong>Registration Date:</strong> <?php echo date('d M Y, h:i A', strtotime($user['created_at'])); ?></p>
                                                        <p class="mb-1"><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('d M Y, h:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                                                        <p class="mb-1"><strong>Last Password Change:</strong> <?php echo $user['last_password_change'] ? date('d M Y, h:i A', strtotime($user['last_password_change'])) : 'Never'; ?></p>
                                                        <p class="mb-0"><strong>Active Tickets:</strong> <?php echo $user['active_tickets']; ?> (Total: <?php echo $user['total_tickets']; ?>)</p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <?php if ($user['account_status'] !== 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-success">Activate Account</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit User Modal -->
                                    <div class="modal fade" id="editModal<?php echo $user['user_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit User #<?php echo $user['user_id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="username<?php echo $user['user_id']; ?>" class="form-label">Username</label>
                                                            <input type="text" class="form-control" id="username<?php echo $user['user_id']; ?>" 
                                                                   name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="email<?php echo $user['user_id']; ?>" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email<?php echo $user['user_id']; ?>" 
                                                                   name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="phone<?php echo $user['user_id']; ?>" class="form-label">Phone</label>
                                                            <input type="text" class="form-control" id="phone<?php echo $user['user_id']; ?>" 
                                                                   name="phone" value="<?php echo htmlspecialchars($user['no_phone']); ?>" required>
                                                        </div>
                                                        <div class="text-end">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update User</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="newUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="newUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="newEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPhone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="newPhone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="newPassword" name="password" required>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
