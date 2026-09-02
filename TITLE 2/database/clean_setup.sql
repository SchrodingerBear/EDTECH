-- Lavadora Laundry Management System - Clean Database Setup
-- This script will drop existing database and create a fresh, working setup
-- Generated: 2026-09-03

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Drop existing database if exists
DROP DATABASE IF EXISTS `lavadora_laundry`;
CREATE DATABASE `lavadora_laundry` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lavadora_laundry`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: roles
-- --------------------------------------------------------
CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: permissions
-- --------------------------------------------------------
CREATE TABLE `permissions` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `module` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: role_permissions
-- --------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `permission_id` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_page_access
-- --------------------------------------------------------
CREATE TABLE `user_page_access` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `page_key` varchar(64) NOT NULL,
  PRIMARY KEY (`user_id`,`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: customers
-- --------------------------------------------------------
CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: employees
-- --------------------------------------------------------
CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `position` varchar(64) DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hire_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `unit` varchar(32) NOT NULL DEFAULT 'kg' COMMENT 'Per kg, per piece, per set...',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `icon` varchar(32) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: laundry_orders
-- --------------------------------------------------------
CREATE TABLE `laundry_orders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `assigned_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','washing','drying','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `pickup_type` enum('walk_in','pickup','delivery') NOT NULL DEFAULT 'walk_in',
  `delivery_address` varchar(255) DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt_token` varchar(32) DEFAULT NULL COMMENT 'Unique token for public receipt access',
  `receipt_sent_at` datetime DEFAULT NULL COMMENT 'When receipt was sent to customer',
  `receipt_viewed_at` datetime DEFAULT NULL COMMENT 'When customer first viewed receipt',
  `receipt_view_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'How many times receipt was viewed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_no` (`order_no`),
  UNIQUE KEY `receipt_token` (`receipt_token`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_employee` (`assigned_employee_id`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: order_items
-- --------------------------------------------------------
CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  KEY `fk_items_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: inventory_items
-- --------------------------------------------------------
CREATE TABLE `inventory_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `category` enum('detergent','softener','bleach','packaging','other') NOT NULL DEFAULT 'other',
  `unit` varchar(32) NOT NULL DEFAULT 'ml',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: inventory_movements
-- --------------------------------------------------------
CREATE TABLE `inventory_movements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `type` enum('in','out','adjust') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movements_item` (`inventory_item_id`),
  KEY `fk_movements_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: inventory_usage
-- --------------------------------------------------------
CREATE TABLE `inventory_usage` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `usage_per_kg` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage_service_item` (`service_id`,`inventory_item_id`),
  KEY `fk_usage_item` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `business_name` varchar(191) NOT NULL DEFAULT 'Lavadora',
  `tagline` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `hero_image_path` varchar(255) DEFAULT NULL,
  `landing_html` mediumtext DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pw_resets_user` (`user_id`),
  KEY `idx_pw_resets_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: audit_logs
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `module` varchar(64) NOT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_user_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Foreign Key Constraints
-- --------------------------------------------------------

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_page_access`
  ADD CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `laundry_orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_orders_employee` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_movements_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_usage`
  ADD CONSTRAINT `fk_usage_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usage_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Initial Data
-- --------------------------------------------------------

-- Insert default roles
INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'owner', 'Owner', 'Full access: orders, customers, services, staff, reports, settings'),
(2, 'staff', 'Staff', 'Laundry staff: manage orders and customers, update job status');

-- Insert default permissions
INSERT INTO `permissions` (`id`, `slug`, `module`, `description`) VALUES
(1, 'orders.manage', 'laundry', 'Create, update and change laundry order status'),
(2, 'customers.manage', 'laundry', 'Manage customer records'),
(3, 'services.manage', 'laundry', 'Manage service and pricing list'),
(4, 'employees.manage', 'laundry', 'Manage laundry staff roster'),
(5, 'reports.view', 'laundry', 'View revenue and report analytics'),
(6, 'settings.manage', 'laundry', 'Configure business settings'),
(7, 'logs.view', 'system', 'View audit logs'),
(8, 'inventory.manage', 'laundry', 'View and manage inventory stock and settings'),
(9, 'settings.manage_system', 'laundry', 'Manage system settings');

-- Assign permissions to roles
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
-- Owner gets all permissions
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9),
-- Staff gets limited permissions
(2, 1), (2, 2), (2, 8);

-- Insert default users (password: admin123)
INSERT INTO `users` (`id`, `role_id`, `email`, `username`, `password_hash`, `first_name`, `phone`, `is_active`) VALUES
(1, 1, 'admin@lavadora.local', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', '0917-000-0000', 1),
(2, 2, 'staff@lavadora.local', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff User', '0917-000-0001', 1);

-- Insert default settings
INSERT INTO `settings` (`id`, `business_name`, `tagline`, `address`, `phone`, `email`, `delivery_fee`) VALUES
(1, 'Lavadora', 'Fresh, clean laundry at your doorstep', '123 Rizal Street, Barangay Poblacion, Manila', '0917-000-0000', 'hello@lavadora.ph', 0.00);

-- Insert default service (30/kg)
INSERT INTO `services` (`id`, `name`, `unit`, `price`, `icon`, `description`, `is_active`) VALUES
(1, 'Laundry Service', 'kg', 30.00, 'droplets', 'Professional laundry service at 30 per kilogram', 1);

COMMIT;
