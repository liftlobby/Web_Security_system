
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>About Us</title>
</head>

<body>
    <?php include 'Head_and_Foot\header.php'; ?>

    <div class="about-us-content">
        <h1>About Us</h1>
        
        <section class="history">
            <h2>History of Railway</h2>
            <p>
                The history of railway in Malaysia dates back to the 1880s when the first railway line was built to connect the tin mines of Perak to the port of Penang.
            </p>
        </section>
        
        <section class="mission">
            <h2>Our Mission</h2>
            <p>
                Our mission is to provide safe, reliable, and efficient rail services that meet the needs of our customers and contribute to the sustainable development of Malaysia's transportation system.
            </p>
        </section>
        
        <section class="vision">
            <h2>Our Vision</h2>
            <p>
                Our vision is to be the leading rail service provider in Malaysia, recognized for our commitment to excellence, innovation, and sustainability.
            </p>
        </section>
    </div>

    <?php include 'Head_and_Foot\footer.php'; ?>
</body>

</html>