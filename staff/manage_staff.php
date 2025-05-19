<?php
session_start();
require_once '../config/database.php';
require_once '../includes/PasswordPolicy.php';
require_once '../includes/MessageUtility.php';

// Check if user is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has permission to access this page
if ($_SESSION['staff_role'] !== 'admin' && $_SESSION['staff_role'] !== 'staff') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' && $_SESSION['staff_role'] === 'admin') {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Check for duplicate username
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staffs WHERE username = ?");
            $stmt->bind_param("s", $_POST['username']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception("Username already exists. Please choose a different username.");
            }

            // Check for duplicate email
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staffs WHERE email = ?");
            $stmt->bind_param("s", $_POST['email']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception("Email address already registered. Please use a different email.");
            }

            // Generate a secure password
            $password = PasswordPolicy::generateSecurePassword();
            $hashed_password = PasswordPolicy::hashPassword($password);

            // Insert new staff
            $stmt = $conn->prepare("INSERT INTO staffs (username, password, email, role, account_status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssss", 
                $_POST['username'],
                $hashed_password,
                $_POST['email'],
                $_POST['role']
            );
            $stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Staff added successfully! Initial password: " . $password;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($action === 'edit') {
        try {
            // Check if user has permission to edit
            if ($_SESSION['staff_role'] !== 'admin' && $_SESSION['staff_id'] != $_POST['staff_id']) {
                throw new Exception("You don't have permission to edit this staff member.");
            }

            $conn->begin_transaction();

            // Check for duplicate username except current staff
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staffs WHERE username = ? AND staff_id != ?");
            $stmt->bind_param("si", $_POST['username'], $_POST['staff_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception("Username already exists. Please choose a different username.");
            }

            // Check for duplicate email except current staff
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staffs WHERE email = ? AND staff_id != ?");
            $stmt->bind_param("si", $_POST['email'], $_POST['staff_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception("Email address already registered. Please use a different email.");
            }

            // If password is being changed
            if (!empty($_POST['new_password'])) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM staffs WHERE staff_id = ?");
                $stmt->bind_param("i", $_POST['staff_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $staff = $result->fetch_assoc();

                if (!PasswordPolicy::verifyPassword($_POST['current_password'], $staff['password'])) {
                    throw new Exception("Current password is incorrect.");
                }

                // Validate new password
                $password_errors = PasswordPolicy::validatePassword($_POST['new_password']);
                if (!empty($password_errors)) {
                    throw new Exception(implode(" ", $password_errors));
                }

                $hashed_password = PasswordPolicy::hashPassword($_POST['new_password']);
                
                $stmt = $conn->prepare("UPDATE staffs SET username = ?, email = ?, password = ? WHERE staff_id = ?");
                $stmt->bind_param("sssi", 
                    $_POST['username'],
                    $_POST['email'],
                    $hashed_password,
                    $_POST['staff_id']
                );
            } else {
                $stmt = $conn->prepare("UPDATE staffs SET username = ?, email = ? WHERE staff_id = ?");
                $stmt->bind_param("ssi", 
                    $_POST['username'],
                    $_POST['email'],
                    $_POST['staff_id']
                );
            }
            
            $stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Staff updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($action === 'reset_password' && $_SESSION['staff_role'] === 'admin') {
        try {
            $conn->begin_transaction();

            // Generate new password
            $new_password = PasswordPolicy::generateSecurePassword();
            $hashed_password = PasswordPolicy::hashPassword($new_password);

            $stmt = $conn->prepare("UPDATE staffs SET password = ? WHERE staff_id = ?");
            $stmt->bind_param("si", $hashed_password, $_POST['staff_id']);
            $stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Password reset successfully! New password: " . $new_password;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($action === 'toggle_status' && $_SESSION['staff_role'] === 'admin') {
        try {
            // Check if trying to suspend own account
            if ($_POST['staff_id'] == $_SESSION['staff_id']) {
                throw new Exception("You cannot suspend your own account.");
            }

            $new_status = $_POST['current_status'] === 'active' ? 'suspended' : 'active';
            $stmt = $conn->prepare("UPDATE staffs SET account_status = ? WHERE staff_id = ?");
            $stmt->bind_param("si", $new_status, $_POST['staff_id']);
            $stmt->execute();
            
            $status_message = $new_status === 'active' ? 'activated' : 'suspended';
            $_SESSION['success'] = "Staff account {$status_message} successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

// Fetch all staff members if admin, or just the current staff if not admin
if ($_SESSION['staff_role'] === 'admin') {
    $stmt = $conn->prepare("SELECT staff_id, username, email, role, account_status, created_at, last_login FROM staffs ORDER BY username");
} else {
    $stmt = $conn->prepare("SELECT staff_id, username, email, role, account_status, created_at, last_login FROM staffs WHERE staff_id = ? ORDER BY username");
    $stmt->bind_param("i", $_SESSION['staff_id']);
}
$stmt->execute();
$result = $stmt->get_result();
$staffs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
        }
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            background-color: #343a40;
            color: white;
            width: 250px;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            width: calc(100% - 250px);
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
            border-radius: 5px;
            padding: 8px 15px;
        }
        .staff-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .staff-status.active {
            background-color: #d4edda;
            color: #155724;
        }
        .staff-status.suspended {
            background-color: #f8d7da;
            color: #721c24;
        }
        .staff-status.locked {
            background-color: #fff3cd;
            color: #856404;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <?php include 'sidebar.php'; ?>

            <div class="main-content">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Manage Staff</h2>
                        <?php if ($_SESSION['staff_role'] === 'admin'): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class='bx bx-user-plus'></i> Add New Staff
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staffs as $staff): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['role']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $staff['account_status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($staff['account_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($staff['created_at'])); ?></td>
                                            <td><?php echo $staff['last_login'] ? date('Y-m-d H:i:s', strtotime($staff['last_login'])) : 'Never'; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editStaffModal<?php echo $staff['staff_id']; ?>">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                    <?php if ($_SESSION['staff_role'] === 'admin' && $staff['staff_id'] != $_SESSION['staff_id']): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="resetPassword(<?php echo $staff['staff_id']; ?>)">
                                                        <i class='bx bx-key'></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-<?php echo $staff['account_status'] === 'active' ? 'danger' : 'success'; ?>"
                                                            onclick="toggleStatus(<?php echo $staff['staff_id']; ?>, '<?php echo $staff['account_status']; ?>')">
                                                        <i class='bx bx-power-off'></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Edit Staff Modal -->
                                        <div class="modal fade" id="editStaffModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Staff</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="" method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Username</label>
                                                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($staff['username']); ?>" required>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                                                            </div>

                                                            <!-- Password change section -->
                                                            <div class="mb-3">
                                                                <label class="form-label">Current Password (required for password change)</label>
                                                                <input type="password" class="form-control" name="current_password">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">New Password (leave blank to keep current)</label>
                                                                <input type="password" class="form-control" name="new_password">
                                                                <div class="form-text">
                                                                    Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character.
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <?php if ($_SESSION['staff_role'] === 'admin'): ?>
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetPassword(staffId) {
            if (confirm('Are you sure you want to reset this staff member\'s password?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="staff_id" value="${staffId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        function toggleStatus(staffId, currentStatus) {
            if (confirm('Are you sure you want to ' + (currentStatus === 'active' ? 'suspend' : 'activate') + ' this staff member?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="staff_id" value="${staffId}">
                    <input type="hidden" name="current_status" value="${currentStatus}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
