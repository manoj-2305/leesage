-- Leesage E-commerce Database Structure
-- This file contains the complete database structure for the Leesage admin system

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `leesage_db`;
USE `leesage_db`;

-- Admin users table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password_hash` varchar(255) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `role` enum('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    `profile_image` varchar(255) DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `login_attempts` int(11) DEFAULT 0,
    `account_locked` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_username` (`username`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin sessions table for security
CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `session_token` varchar(255) NOT NULL UNIQUE,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text,
    `expires_at` datetime NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_session_token` (`session_token`),
    KEY `idx_expires_at` (`expires_at`),
    FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin activity logs
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `action_type` varchar(50) NOT NULL,
    `action_description` text,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_action_type` (`action_type`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) 
VALUES (
    'admin', 
    'admin@leesage.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'System Administrator', 
    'super_admin', 
    1
);

-- Create indexes for better performance
CREATE INDEX idx_admin_users_active ON admin_users(is_active);
CREATE INDEX idx_admin_sessions_active ON admin_sessions(expires_at);
CREATE INDEX idx_activity_logs_recent ON admin_activity_logs(created_at DESC);

-- Users table for public users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions table for public users
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `session_token` VARCHAR(255) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_session_token` (`session_token`),
    KEY `idx_expires_at` (`expires_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for public user tables
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_user_sessions_active ON user_sessions(expires_at);
