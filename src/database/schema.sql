-- VitalPBX Asterisk Wallboard Database Schema
-- Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- CONFIGURATION TABLES
-- ============================================

-- AMI Connection Configuration
CREATE TABLE `ami_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ami_host` VARCHAR(255) NOT NULL,
    `ami_port` INT UNSIGNED NOT NULL DEFAULT 5038,
    `ami_username` VARCHAR(100) NOT NULL,
    `ami_password` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company/Instance Configuration
CREATE TABLE `company_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL DEFAULT 'Call Center',
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'America/Chicago',
    `refresh_rate` INT UNSIGNED NOT NULL DEFAULT 5,
    `business_hours_start` TIME NOT NULL DEFAULT '08:00:00',
    `business_hours_end` TIME NOT NULL DEFAULT '18:00:00',
    `wrapup_time` INT UNSIGNED NOT NULL DEFAULT 60,
    `ring_timeout` INT UNSIGNED NOT NULL DEFAULT 20,
    `repeat_caller_days` INT UNSIGNED NOT NULL DEFAULT 30,
    `repeat_caller_threshold` INT UNSIGNED NOT NULL DEFAULT 2,
    `data_retention_days` INT UNSIGNED NOT NULL DEFAULT 90,
    `theme` VARCHAR(20) NOT NULL DEFAULT 'dark',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMTP Email Configuration
CREATE TABLE `smtp_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `smtp_host` VARCHAR(255) DEFAULT NULL,
    `smtp_port` INT UNSIGNED DEFAULT 587,
    `smtp_username` VARCHAR(255) DEFAULT NULL,
    `smtp_password` VARCHAR(255) DEFAULT NULL,
    `smtp_encryption` VARCHAR(10) DEFAULT 'tls',
    `from_address` VARCHAR(255) DEFAULT NULL,
    `from_name` VARCHAR(255) DEFAULT NULL,
    `is_configured` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- QUEUE & EXTENSION TABLES
-- ============================================

-- Queues to Monitor
CREATE TABLE `queues` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_number` VARCHAR(20) NOT NULL,
    `queue_name` VARCHAR(100) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `group_name` VARCHAR(100) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `show_on_wallboard` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `queue_number` (`queue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extensions/Agents
CREATE TABLE `extensions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `extension` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `display_name` VARCHAR(200) GENERATED ALWAYS AS (CONCAT(first_name, ' ', COALESCE(last_name, ''))) STORED,
    `team` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `extension` (`extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ALERT TABLES
-- ============================================

-- Alert Rules Configuration
CREATE TABLE `alert_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(50) NOT NULL,
    `alert_name` VARCHAR(100) NOT NULL,
    `threshold` INT UNSIGNED NOT NULL,
    `threshold_unit` VARCHAR(20) DEFAULT 'seconds',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `alert_type` (`alert_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Recipients
CREATE TABLE `alert_recipients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `receives_email` TINYINT(1) NOT NULL DEFAULT 1,
    `receives_sms` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert History Log
CREATE TABLE `alert_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(50) NOT NULL,
    `alert_message` TEXT NOT NULL,
    `alert_data` JSON DEFAULT NULL,
    `sent_to` TEXT DEFAULT NULL,
    `sent_via` VARCHAR(20) DEFAULT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alert_type` (`alert_type`),
    KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER TABLES
-- ============================================

-- Admin/Manager Users
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('admin', 'manager', 'viewer') NOT NULL DEFAULT 'viewer',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CALL TRACKING TABLES
-- ============================================

-- All Calls (Inbound & Outbound)
CREATE TABLE `calls` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unique_id` VARCHAR(100) NOT NULL,
    `linked_id` VARCHAR(100) DEFAULT NULL,
    `call_type` ENUM('inbound', 'outbound', 'internal') NOT NULL DEFAULT 'inbound',
    `caller_number` VARCHAR(50) DEFAULT NULL,
    `caller_name` VARCHAR(100) DEFAULT NULL,
    `called_number` VARCHAR(50) DEFAULT NULL,
    `queue_number` VARCHAR(20) DEFAULT NULL,
    `queue_name` VARCHAR(100) DEFAULT NULL,
    `agent_extension` VARCHAR(20) DEFAULT NULL,
    `agent_name` VARCHAR(200) DEFAULT NULL,
    `status` ENUM('new', 'waiting', 'ringing', 'answered', 'completed', 'abandoned', 'transferred', 'voicemail') NOT NULL DEFAULT 'new',
    `entered_queue_at` TIMESTAMP NULL DEFAULT NULL,
    `ring_started_at` TIMESTAMP NULL DEFAULT NULL,
    `answered_at` TIMESTAMP NULL DEFAULT NULL,
    `ended_at` TIMESTAMP NULL DEFAULT NULL,
    `wait_time` INT UNSIGNED DEFAULT NULL,
    `ring_time` INT UNSIGNED DEFAULT NULL,
    `talk_time` INT UNSIGNED DEFAULT NULL,
    `hold_time` INT UNSIGNED DEFAULT 0,
    `wrap_time` INT UNSIGNED DEFAULT NULL,
    `was_transferred` TINYINT(1) NOT NULL DEFAULT 0,
    `transfer_to` VARCHAR(50) DEFAULT NULL,
    `brand_tag` VARCHAR(50) DEFAULT NULL,
    `disposition` VARCHAR(50) DEFAULT NULL,
    `recording_path` VARCHAR(500) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_id` (`unique_id`),
    KEY `idx_linked_id` (`linked_id`),
    KEY `idx_call_type` (`call_type`),
    KEY `idx_queue_number` (`queue_number`),
    KEY `idx_agent_extension` (`agent_extension`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_caller_number` (`caller_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Real-time Queue Statistics
CREATE TABLE `queue_stats_realtime` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_number` VARCHAR(20) NOT NULL,
    `queue_name` VARCHAR(100) DEFAULT NULL,
    `calls_waiting` INT UNSIGNED NOT NULL DEFAULT 0,
    `longest_wait_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_agents` INT UNSIGNED NOT NULL DEFAULT 0,
    `agents_available` INT UNSIGNED NOT NULL DEFAULT 0,
    `agents_on_call` INT UNSIGNED NOT NULL DEFAULT 0,
    `agents_paused` INT UNSIGNED NOT NULL DEFAULT 0,
    `agents_wrapup` INT UNSIGNED NOT NULL DEFAULT 0,
    `agents_ringing` INT UNSIGNED NOT NULL DEFAULT 0,
    `calls_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `answered_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `abandoned_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `sla_percent_today` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `avg_wait_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_talk_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `queue_number` (`queue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agent/Extension Real-time Status
CREATE TABLE `agent_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `extension` VARCHAR(20) NOT NULL,
    `agent_name` VARCHAR(200) DEFAULT NULL,
    `status` ENUM('unknown', 'available', 'on_call', 'ringing', 'wrapup', 'paused', 'offline') NOT NULL DEFAULT 'unknown',
    `pause_reason` VARCHAR(100) DEFAULT NULL,
    `status_since` TIMESTAMP NULL DEFAULT NULL,
    `current_call_id` VARCHAR(100) DEFAULT NULL,
    `current_call_type` ENUM('inbound', 'outbound', 'internal') DEFAULT NULL,
    `talking_to` VARCHAR(100) DEFAULT NULL,
    `talking_to_name` VARCHAR(200) DEFAULT NULL,
    `call_started_at` TIMESTAMP NULL DEFAULT NULL,
    `brand_tag` VARCHAR(50) DEFAULT NULL,
    `calls_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `talk_time_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_handle_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `missed_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `paused_time_today` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `extension` (`extension`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agent Queue Membership (which queues they're signed into)
CREATE TABLE `agent_queue_membership` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `extension` VARCHAR(20) NOT NULL,
    `queue_number` VARCHAR(20) NOT NULL,
    `is_signed_in` TINYINT(1) NOT NULL DEFAULT 0,
    `is_paused` TINYINT(1) NOT NULL DEFAULT 0,
    `pause_reason` VARCHAR(100) DEFAULT NULL,
    `penalty` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_call_at` TIMESTAMP NULL DEFAULT NULL,
    `calls_taken` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `extension_queue` (`extension`, `queue_number`),
    KEY `idx_queue_number` (`queue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Callback Queue
CREATE TABLE `callbacks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caller_number` VARCHAR(50) NOT NULL,
    `caller_name` VARCHAR(100) DEFAULT NULL,
    `queue_number` VARCHAR(20) DEFAULT NULL,
    `queue_name` VARCHAR(100) DEFAULT NULL,
    `position` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('waiting', 'in_progress', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'waiting',
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `attempted_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `agent_extension` VARCHAR(20) DEFAULT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_caller_number` (`caller_number`),
    KEY `idx_queue_number` (`queue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Missed Calls (Ring No Answer)
CREATE TABLE `missed_calls` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `call_id` INT UNSIGNED DEFAULT NULL,
    `unique_id` VARCHAR(100) DEFAULT NULL,
    `extension` VARCHAR(20) NOT NULL,
    `agent_name` VARCHAR(200) DEFAULT NULL,
    `caller_number` VARCHAR(50) DEFAULT NULL,
    `queue_number` VARCHAR(20) DEFAULT NULL,
    `ring_time` INT UNSIGNED DEFAULT NULL,
    `missed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_extension` (`extension`),
    KEY `idx_missed_at` (`missed_at`),
    KEY `idx_call_id` (`call_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- HISTORY & REPORTING TABLES
-- ============================================

-- Daily Statistics (Aggregated)
CREATE TABLE `daily_stats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `queue_number` VARCHAR(20) DEFAULT NULL,
    `total_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `answered_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `abandoned_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `voicemail_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `sla_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `avg_wait_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_wait_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_handle_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_talk_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_talk_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `date_queue` (`stat_date`, `queue_number`),
    KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agent Daily Statistics (Aggregated)
CREATE TABLE `agent_daily_stats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `extension` VARCHAR(20) NOT NULL,
    `agent_name` VARCHAR(200) DEFAULT NULL,
    `total_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `inbound_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `outbound_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_handle_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_talk_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_hold_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_wrapup_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `missed_calls` INT UNSIGNED NOT NULL DEFAULT 0,
    `transfers_made` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_paused_time` INT UNSIGNED NOT NULL DEFAULT 0,
    `login_time` TIMESTAMP NULL DEFAULT NULL,
    `logout_time` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `date_extension` (`stat_date`, `extension`),
    KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repeat Callers Tracking
CREATE TABLE `repeat_callers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caller_number` VARCHAR(50) NOT NULL,
    `caller_name` VARCHAR(100) DEFAULT NULL,
    `call_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_call_at` TIMESTAMP NOT NULL,
    `last_call_at` TIMESTAMP NOT NULL,
    `last_queue` VARCHAR(20) DEFAULT NULL,
    `last_agent` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_flagged` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `caller_number` (`caller_number`),
    KEY `idx_call_count` (`call_count`),
    KEY `idx_last_call` (`last_call_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report Configuration
CREATE TABLE `report_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_type` VARCHAR(50) NOT NULL,
    `report_name` VARCHAR(100) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `schedule` VARCHAR(20) DEFAULT 'daily',
    `send_time` TIME DEFAULT '06:00:00',
    `recipients` TEXT DEFAULT NULL,
    `last_sent_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report History
CREATE TABLE `report_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_type` VARCHAR(50) NOT NULL,
    `report_name` VARCHAR(100) DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` INT UNSIGNED DEFAULT NULL,
    `sent_to` TEXT DEFAULT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_report_type` (`report_type`),
    KEY `idx_generated_at` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AMI EVENT LOG (for debugging/analysis)
-- ============================================

CREATE TABLE `ami_event_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(50) NOT NULL,
    `event_data` JSON DEFAULT NULL,
    `raw_event` TEXT DEFAULT NULL,
    `processed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Insert default company config
INSERT INTO `company_config` (`company_name`, `timezone`, `refresh_rate`, `wrapup_time`, `ring_timeout`, `repeat_caller_days`, `repeat_caller_threshold`) 
VALUES ('Call Center', 'America/Chicago', 5, 60, 20, 30, 2);

-- Insert default SMTP config (unconfigured)
INSERT INTO `smtp_config` (`is_configured`) VALUES (0);

-- Insert default alert rules
INSERT INTO `alert_rules` (`alert_type`, `alert_name`, `threshold`, `threshold_unit`, `is_enabled`) VALUES
('sla_breach', 'SLA Breach', 80, 'percent', 1),
('max_wait_time', 'Max Wait Time Exceeded', 120, 'seconds', 1),
('queue_overflow', 'Queue Overflow', 5, 'calls', 1),
('no_agents', 'No Agents Available', 60, 'seconds', 1),
('long_hold', 'Long Hold Time', 120, 'seconds', 1),
('abandoned_call', 'Call Abandoned', 1, 'count', 0),
('long_break', 'Extended Break', 900, 'seconds', 0);

-- Insert default report configs
INSERT INTO `report_config` (`report_type`, `report_name`, `is_enabled`, `schedule`, `send_time`) VALUES
('daily_summary', 'Daily Summary Report', 0, 'daily', '06:00:00'),
('agent_performance', 'Agent Performance Report', 0, 'daily', '06:00:00'),
('queue_breakdown', 'Queue Breakdown Report', 0, 'weekly', '06:00:00');

COMMIT;
