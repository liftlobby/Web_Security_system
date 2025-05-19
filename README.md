# Web_Security_system
# Railway System

A railway ticket booking and management system for Railway System.

## Features

### For Passengers
- **Account Management**
  - User registration and login
  - Profile management
  - View booking history

- **Ticket Booking**
  - Search available trains
  - Select seats
  - Make payments
  - View booking details
  - Download/Print tickets with QR codes

### For Staff
- **Ticket Management**
  - Scan QR codes for ticket verification
  - Manual ticket ID entry
  - Real-time ticket status verification
  - View detailed ticket information

- **Train Management**
  - View train schedules
  - Monitor train status
  - Update train information

## Technical Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache Web Server
- XAMPP (recommended for local development)

### Browser Requirements
- Modern web browser with JavaScript enabled
- Camera access for QR code scanning (staff portal)
- Support for HTML5 and CSS3

### Dependencies
- Bootstrap 5.3.0
- Boxicons 2.0.7
- HTML5-QRCode 2.3.8
- jQuery (latest version)

## Installation

1. **Set up XAMPP**
   - Install XAMPP
   - Start Apache and MySQL services

2. **Database Setup**
   - Import the database schema from `database/railway_system.sql`
   - Configure database connection in `config/database.php`

3. **Project Setup**
   - Clone/copy the project to `htdocs` directory
   - Ensure proper file permissions
   - Configure base URL in configuration files
   - Need to add your email and app password in `../includes/NotificationManager.php` so that notification emails can be sent

4. **Admin Account Setup**
   - Remember to run admin_hash.php in staff directory (Code: ../staff/> php admin_hash.php)
     - To generate admin account (Username: admin, Password: Admin@123)

## Usage

### Passenger Portal
1. Access the system through `http://localhost/<your_directory_name>/`
2. Register a new account or login
3. Search for available trains
4. Select seats and make booking
5. Complete payment
6. Download/print ticket with QR code

### Staff Portal
1. Access the staff portal through `http://localhost/<your_directory_name>/staff/` or click the `staff login` in the main page footer
2. Login with staff credentials
3. Use QR scanner or manual entry to verify tickets
4. View and manage train schedules

## Security Features
- Session management
- Password hashing with Argon2id, salting (Unique Salt) and peppering (RAILWAY_SECURE_2024)
- Input validation
- SQL injection prevention
- XSS protection
- CSRF protection
- QR code authentication with PHP QR Code and special tokens generated with SHA-256

## License
This project is proprietary software. All rights reserved. Unauthorized commercial use is strictly prohibited. Please contact the author for further information.
