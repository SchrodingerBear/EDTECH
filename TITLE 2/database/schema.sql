-- =============================================================================
-- Lavadora — Laundry Management System
-- Canonical MySQL / MariaDB schema (utf8mb4, InnoDB)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';


-- -----------------------------------------------------------------------------
-- DROP TABLES (safe re-import). Reverse dependency order; FK checks are off.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `inventory_usage`;
DROP TABLE IF EXISTS `inventory_items`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `laundry_orders`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_page_access`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- -----------------------------------------------------------------------------
-- 1. ROLES & USERS
-- owner = full access (laundry owner/managers)
-- staff = laundry staff (process orders, manage customers)
-- -----------------------------------------------------------------------------

CREATE TABLE `roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'owner', 'Owner', 'Full access: orders, customers, services, staff, reports, settings'),
(2, 'staff', 'Staff', 'Laundry staff: manage orders and customers, update job status');

CREATE TABLE `permissions` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL,
  `module` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`slug`, `module`, `description`) VALUES
('orders.manage', 'laundry', 'Create, update and change laundry order status'),
('customers.manage', 'laundry', 'Manage customer records'),
('services.manage', 'laundry', 'Manage service and pricing list'),
('employees.manage', 'laundry', 'Manage laundry staff roster'),
('reports.view', 'laundry', 'View revenue and report analytics'),
('settings.manage', 'laundry', 'Configure business settings'),
('logs.view', 'system', 'View audit logs'),
('inventory.manage', 'laundry', 'View and manage inventory stock and settings'),
('settings.manage_system', 'laundry', 'Manage system settings');

CREATE TABLE `role_permissions` (
  `role_id` TINYINT UNSIGNED NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- owner -> all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- staff -> orders + customers only
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN ('orders.manage', 'customers.manage', 'inventory.manage');

-- per-account page access (owner-controlled; empty set = full access)

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` TINYINT UNSIGNED NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `username` VARCHAR(64) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `avatar_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Labeled accounts (password for BOTH is: password). Labeled so it's obvious
-- which thesis you're logging into when both systems share the same host.
--   thesis_2_owner@lavadora.local -> role owner (admin/dashboard)
--   thesis_2_staff@lavadora.local -> role staff (admin/dashboard)
INSERT INTO `users` (`id`, `role_id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `phone`, `is_active`) VALUES
(1, 1, 'thesis_2_owner@lavadora.local', 'thesis_2_owner', '$2y$10$fsuIqA7lHffMsgpsk2Y3l.S2sCnwbpwxbUkUqWM/cpJgBSh/nHODW', 'Thesis 2', 'Owner', '0917-000-0001', 1),
(2, 2, 'thesis_2_staff@lavadora.local', 'thesis_2_staff', '$2y$10$fsuIqA7lHffMsgpsk2Y3l.S2sCnwbpwxbUkUqWM/cpJgBSh/nHODW', 'Thesis 2', 'Staff', '0917-000-0002', 1);

CREATE TABLE `user_page_access` (
  `user_id` INT UNSIGNED NOT NULL,
  `page_key` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`user_id`, `page_key`),
  CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pw_resets_user` (`user_id`),
  KEY `idx_pw_resets_hash` (`token_hash`),
  CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. BUSINESS & LOGS
-- -----------------------------------------------------------------------------

CREATE TABLE `settings` (
  `id` TINYINT UNSIGNED NOT NULL,
  `business_name` VARCHAR(191) NOT NULL DEFAULT 'Lavadora',
  `tagline` VARCHAR(255) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `hero_image_path` VARCHAR(255) DEFAULT NULL,
  `landing_html` MEDIUMTEXT DEFAULT NULL,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `business_name`, `tagline`, `address`, `phone`, `email`) VALUES
(1, 'Lavadora', 'Fresh, clean laundry at your doorstep', '123 Rizal Street, Barangay Poblacion, Manila', '0917-000-0000', 'hello@lavadora.ph');

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `module` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `meta_json` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_user_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. LAUNDRY CORE
-- -----------------------------------------------------------------------------

-- Services / price list
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `unit` VARCHAR(32) NOT NULL DEFAULT 'kg' COMMENT 'Per kg, per piece, per set...',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `icon` VARCHAR(32) DEFAULT NULL,
  `description` TEXT,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `services` (`name`, `unit`, `price`, `icon`, `description`, `is_active`) VALUES
('Wash & Fold', 'kg', 60.00, 'droplets', 'Machine wash, dry and fold. Per kilogram. 1-day turnaround.', 1),
('Wash & Iron', 'kg', 90.00, 'shirt', 'Wash, dry and professional hand ironing. Per kilogram.', 1),
('Dry Cleaning', 'piece', 150.00, 'tag', 'Gentle chemical cleaning for suits, gowns and delicate fabrics.', 1),
('Iron Only', 'kg', 50.00, 'wrench', 'Press and fold already-washed clothes. Per kilogram.', 1),
('Comforter', 'piece', 180.00, 'layers', 'Full wash and dry for blankets, quilts and comforters.', 1),
('Shoes & Sneakers', 'pair', 120.00, 'shirt', 'Deep clean wash for footwear. Per pair.', 1);

-- Default inventory items
INSERT INTO `inventory_items` (`name`, `category`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `is_active`) VALUES
('Laundry Detergent', 'detergent', 'ml', 5000, 1000, 0.15, 1),
('Fabric Softener', 'softener', 'ml', 3000, 800, 0.20, 1),
('Bleach', 'bleach', 'ml', 2000, 500, 0.10, 1),
('Packaging Bags', 'packaging', 'pieces', 200, 50, 2.00, 1),
('Stain Remover', 'other', 'ml', 1000, 300, 0.25, 1);

-- Default inventory usage rates (per kg of laundry)
-- These map to service IDs: 1=Wash&Fold, 2=Wash&Iron, 3=DryCleaning, 4=IronOnly, 5=Comforter, 6=Shoes
INSERT INTO `inventory_usage` (`service_id`, `inventory_item_id`, `usage_per_kg`) VALUES
(1, 1, 15.00), (1, 2, 10.00), (1, 3, 5.00),
(2, 1, 15.00), (2, 2, 10.00), (2, 3, 5.00), (2, 5, 3.00),
(3, 1, 20.00), (3, 2, 15.00), (3, 3, 8.00), (3, 5, 5.00),
(5, 1, 25.00), (5, 2, 20.00), (5, 3, 10.00),
(6, 1, 20.00), (6, 2, 15.00), (6, 5, 5.00);

-- Customers
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(32) NOT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employees (laundry staff roster, not login accounts)
CREATE TABLE `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `position` VARCHAR(64) DEFAULT NULL,
  `salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `hire_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders (one order = one customer, multiple items)
CREATE TABLE `laundry_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(32) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `assigned_employee_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','washing','drying','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `pickup_type` ENUM('walk_in','pickup','delivery') NOT NULL DEFAULT 'walk_in',
  `delivery_address` VARCHAR(255) DEFAULT NULL,
  `pickup_date` DATE DEFAULT NULL,
  `notes` TEXT,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_no` (`order_no`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_employee` (`assigned_employee_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_orders_employee` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order line items (service + quantity/weight)
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. INVENTORY SYSTEM
-- -----------------------------------------------------------------------------

-- Inventory items (detergent, softener, bleach, packaging, etc.)
CREATE TABLE `inventory_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `category` ENUM('detergent','softener','bleach','packaging','other') NOT NULL DEFAULT 'other',
  `unit` VARCHAR(32) NOT NULL DEFAULT 'ml' COMMENT 'ml, kg, pieces, liters',
  `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `cost_per_unit` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Smart inventory: usage rates per service per kg
CREATE TABLE `inventory_usage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `usage_per_kg` DECIMAL(10,4) NOT NULL DEFAULT 0 COMMENT 'ml or g per kg of laundry',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage_service_item` (`service_id`, `inventory_item_id`),
  CONSTRAINT `fk_usage_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usage_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory movement log
CREATE TABLE `inventory_movements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `type` ENUM('in','out','adjust') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `reference` VARCHAR(120) DEFAULT NULL,
  `notes` TEXT,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movements_item` (`inventory_item_id`),
  CONSTRAINT `fk_movements_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
