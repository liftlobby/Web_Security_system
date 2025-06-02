<?php
require_once 'includes/Session.php';
Session::initialize();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <!-- Session Management -->
    <script src="js/session-manager.js"></script>
    <style>
        .alert {
            padding: 15px;
            margin: 20px auto;
            max-width: 800px;
            border-radius: 4px;
            position: relative;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert .close {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: inherit;
        }
    </style>
    <title>Railway Ticketing and Reservation System</title>
</head>

<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
            <span class="close" onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
            <span class="close" onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>

    <div class="index-content">
        <h1>Welcome to Ticketing and Reservation System</h1>
        <h2>Have A Safe Journey With Us</h2>
        <br/><br/><br/>
        <?php if (isset($_SESSION['user_id'])): ?>
            <button onclick="window.location.href='ticketing.php'">Buy tickets</button>
        <?php else: ?>
            <button onclick="window.location.href='login.php'">Login to Buy Tickets</button>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        <?php endif; ?>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>

    <?php
    var_dump($_SESSION);
    echo "<br>Session ID: " . session_id() . "<br><br>";
    ?>
</body>

</html>