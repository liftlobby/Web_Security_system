-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `railway_system`
--

CREATE DATABASE IF NOT EXISTS `railway_system`;
USE `railway_system`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `no_phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL,
  `last_password_change` timestamp NULL,
  `failed_attempts` int DEFAULT 0,
  `locked_until` timestamp NULL,
  `account_status` enum('active','locked','suspended') DEFAULT 'active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL,
  `last_password_change` timestamp NULL,
  `failed_attempts` int DEFAULT 0,
  `locked_until` timestamp NULL,
  `account_status` enum('active','locked','suspended') DEFAULT 'active',
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `train_number` varchar(20) NOT NULL,
  `departure_station` varchar(100) NOT NULL,
  `arrival_station` varchar(100) NOT NULL,
  `departure_time` datetime NOT NULL,
  `arrival_time` datetime NOT NULL,
  `platform_number` varchar(10) DEFAULT NULL,
  `train_status` enum('on_time','delayed','cancelled') DEFAULT 'on_time',
  `price` decimal(10,2) NOT NULL,
  `available_seats` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  INDEX `idx_departure_time` (`departure_time`),
  INDEX `idx_train_number` (`train_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `booking_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `passenger_name` varchar(100) DEFAULT NULL,
  `seat_number` varchar(10) NOT NULL,
  `num_seats` int(11) DEFAULT 1,
  `special_requests` text DEFAULT NULL,
  `status` enum('active','cancelled','completed') NOT NULL DEFAULT 'active',
  `qr_code` varchar(255) NOT NULL,
  `payment_status` enum('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `payment_amount` decimal(10,2) NOT NULL,
  `updated_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`schedule_id`) ON DELETE CASCADE,
  INDEX `idx_user_booking` (`user_id`, `booking_date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_verifications`
--

CREATE TABLE `ticket_verifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `verification_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(50),
    `remarks` TEXT,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`),
    FOREIGN KEY (`staff_id`) REFERENCES `staffs`(`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
    `payment_id` INT PRIMARY KEY AUTO_INCREMENT,
    `ticket_id` INT NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATETIME NOT NULL,
    `status` VARCHAR(20) DEFAULT 'completed',
    `transaction_id` VARCHAR(100) NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`),
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
    `refund_id` INT PRIMARY KEY AUTO_INCREMENT,
    `ticket_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `refund_date` DATETIME NOT NULL,
    `refund_method` varchar(50) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    `reason` TEXT NULL,
    `processing_notes` TEXT NULL,
    `processed_by` INT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`),
    FOREIGN KEY (`processed_by`) REFERENCES `staffs`(`staff_id`),
    INDEX `idx_refund_date` (`refund_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `token` VARCHAR(64) NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `ticket_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expiry_time` DATETIME NOT NULL,
    `is_used` TINYINT(1) DEFAULT 0,
    `used_time` DATETIME NULL,
    `revoked` BOOLEAN NOT NULL DEFAULT 0,
    `created_by_ip` VARCHAR(45) NULL,
    `used_by_ip` VARCHAR(45) NULL,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_ticket` (`ticket_id`),
    INDEX `idx_expiry` (`expiry_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `password_history`
--

CREATE TABLE `password_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_history` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_password_history`
--

CREATE TABLE `staff_password_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `staffs`(`staff_id`) ON DELETE CASCADE,
    INDEX `idx_staff_history` (`staff_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) DEFAULT 0,
    INDEX `idx_username_ip` (`username`, `ip_address`),
    INDEX `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NULL,
    `staff_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`staff_id`) REFERENCES `staffs`(`staff_id`) ON DELETE SET NULL,
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
    `report_id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NULL,  -- NULL if submitted by non-logged in user
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('new', 'responded') DEFAULT 'new',
    `staff_id` INT NULL,
    `response` TEXT NULL,
    `response_date` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`staff_id`) REFERENCES `staffs`(`staff_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `type` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `related_id` INT,
    `created_at` DATETIME NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_unread` (`user_id`, `is_read`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;