-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 01, 2026 at 03:30 PM
-- Server version: 8.0.46-0ubuntu0.24.04.3
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lavadora_laundry`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `actor_user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `action`, `module`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `meta_json`, `created_at`) VALUES
(1, 1, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-30 18:45:03'),
(2, 2, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-30 18:45:03'),
(3, 2, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:58:56'),
(4, 1, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:33:50'),
(5, 2, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:34:07'),
(6, 1, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:34:22'),
(7, 1, 'order.status', 'orders', 'order', 1, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:34:37'),
(8, 1, 'inventory.receive', 'inventory', 'inventory_item', 1, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:35:01'),
(9, 1, 'inventory.adjust', 'inventory', 'inventory_item', 1, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:35:01'),
(10, 1, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 17:36:31'),
(11, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 18:31:05'),
(12, 1, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 18:35:32'),
(13, 1, 'customer.create', 'customers', 'customer', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 18:39:47'),
(14, 2, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 18:41:40'),
(15, 2, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 18:51:35'),
(16, 2, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Edg/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 19:52:25'),
(17, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 23:24:27'),
(18, 1, 'account.toggle', 'accounts', 'user', 2, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Edg/152.0.0.0 Mobile Safari/537.36', NULL, '2026-09-01 23:28:11'),
(19, 1, 'customer.create', 'customers', 'customer', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 08:00:00'),
(20, 1, 'customer.create', 'customers', 'customer', 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 09:00:00'),
(21, 1, 'order.create', 'orders', 'order', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 08:30:00'),
(22, 1, 'order.create', 'orders', 'order', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 09:15:00'),
(23, 1, 'order.create', 'orders', 'order', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 10:00:00'),
(24, 1, 'order.status', 'orders', 'order', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 10:30:00'),
(25, 1, 'order.status', 'orders', 'order', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 12:00:00'),
(26, 1, 'order.status', 'orders', 'order', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 14:00:00'),
(27, 1, 'order.status', 'orders', 'order', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 11:00:00'),
(28, 1, 'order.status', 'orders', 'order', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 13:00:00'),
(29, 1, 'order.status', 'orders', 'order', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 15:30:00'),
(30, 1, 'inventory.receive', 'inventory', 'inventory_item', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 14:00:00'),
(31, 1, 'inventory.adjust', 'inventory', 'inventory_item', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-09-01 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int UNSIGNED NOT NULL,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `phone`, `email`, `address`, `notes`, `created_at`, `updated_at`) VALUES
(2, 'T', 'T', 'T', NULL, NULL, NULL, '2026-08-31 18:39:47', '2026-08-31 18:39:47'),
(3, 'Maria', 'Santos', '0917-123-4567', 'maria.santos@email.com', '456 Mabini Street, Manila', 'Regular customer, prefers wash & fold', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
(4, 'Juan', 'Reyes', '0918-234-5678', 'juan.reyes@email.com', '789 Aurora Boulevard, Quezon City', 'Delicate fabrics only', '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
(5, 'Ana', 'Garcia', '0919-345-6789', 'ana.garcia@email.com', '123 Shaw Boulevard, Mandaluyong', 'Prefers dry cleaning for suits', '2026-09-01 10:00:00', '2026-09-01 10:00:00'),
(6, 'Carlos', 'Diaz', '0920-456-7890', 'carlos.diaz@email.com', '567 Taft Avenue, Manila', 'Bulk orders - comforters and blankets', '2026-09-01 11:00:00', '2026-09-01 11:00:00'),
(7, 'Elena', 'Ramos', '0921-567-8901', 'elena.ramos@email.com', '890 EDSA, Makati', 'VIP customer', '2026-09-01 12:00:00', '2026-09-01 12:00:00'),
(8, 'Miguel', 'Torres', '0922-678-9012', 'miguel.torres@email.com', '234 Ortigas Avenue, Pasig', 'Regular pickup/delivery', '2026-09-01 13:00:00', '2026-09-01 13:00:00'),
(9, 'Sofia', 'Castillo', '0923-789-0123', 'sofia.castillo@email.com', '678 Bonifacio Street, Taguig', 'New customer', '2026-09-01 14:00:00', '2026-09-01 14:00:00'),
(10, 'Diego', 'Flores', '0924-890-1234', 'diego.flores@email.com', '901 Del Monte Avenue, Quezon City', 'Prefers iron only service', '2026-09-01 15:00:00', '2026-09-01 15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int UNSIGNED NOT NULL,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hire_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `phone`, `position`, `salary`, `hire_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Roberto', 'Cruz', '0915-111-2222', 'Washing Supervisor', 18000.00, '2026-08-01', 1, '2026-08-01 00:00:00', '2026-08-01 00:00:00'),
(2, 'Luisa', 'Fernandez', '0915-222-3333', 'Ironing Specialist', 16500.00, '2026-08-05', 1, '2026-08-05 00:00:00', '2026-08-05 00:00:00'),
(3, 'Antonio', 'Lopez', '0915-333-4444', 'Delivery Driver', 15000.00, '2026-08-10', 1, '2026-08-10 00:00:00', '2026-08-10 00:00:00'),
(4, 'Carmen', 'Gomez', '0915-444-5555', 'Front Desk', 14500.00, '2026-08-15', 1, '2026-08-15 00:00:00', '2026-08-15 00:00:00'),
(5, 'Jose', 'Martinez', '0915-555-6666', 'Quality Control', 17000.00, '2026-08-20', 1, '2026-08-20 00:00:00', '2026-08-20 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('detergent','softener','bleach','packaging','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ml',
  `current_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `category`, `unit`, `current_stock`, `minimum_stock`, `cost_per_unit`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Laundry Detergent', 'detergent', 'ml', 5000.00, 1000.00, 0.15, 1, '2026-08-31 17:31:49', '2026-08-31 17:35:01'),
(2, 'Fabric Softener', 'softener', 'ml', 3000.00, 800.00, 0.20, 1, '2026-08-31 17:31:49', '2026-08-31 17:34:48'),
(3, 'Bleach', 'bleach', 'ml', 2000.00, 500.00, 0.10, 1, '2026-08-31 17:31:49', '2026-08-31 17:34:48'),
(4, 'Packaging Bags', 'packaging', 'pieces', 200.00, 50.00, 2.00, 1, '2026-08-31 17:31:49', '2026-08-31 17:31:49'),
(5, 'Stain Remover', 'other', 'ml', 1000.00, 300.00, 0.25, 1, '2026-08-31 17:31:49', '2026-08-31 17:31:49');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int UNSIGNED NOT NULL,
  `inventory_item_id` int UNSIGNED NOT NULL,
  `type` enum('in','out','adjust') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `inventory_item_id`, `type`, `quantity`, `reference`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'in', 2000.00, 'STK-001', 'Initial stock receipt', 1, '2026-08-31 10:00:00'),
(2, 2, 'in', 1500.00, 'STK-001', 'Initial stock receipt', 1, '2026-08-31 10:00:00'),
(3, 3, 'in', 1000.00, 'STK-001', 'Initial stock receipt', 1, '2026-08-31 10:00:00'),
(4, 4, 'in', 100.00, 'STK-001', 'Initial stock receipt', 1, '2026-08-31 10:00:00'),
(5, 5, 'in', 500.00, 'STK-001', 'Initial stock receipt', 1, '2026-08-31 10:00:00'),
(6, 1, 'out', 75.00, 'ORD-2026-0001', 'Used for order #1', 1, '2026-09-01 08:30:00'),
(7, 2, 'out', 50.00, 'ORD-2026-0001', 'Used for order #1', 1, '2026-09-01 08:30:00'),
(8, 3, 'out', 25.00, 'ORD-2026-0001', 'Used for order #1', 1, '2026-09-01 08:30:00'),
(9, 1, 'out', 120.00, 'ORD-2026-0003', 'Used for order #3', 1, '2026-09-01 10:00:00'),
(10, 2, 'out', 80.00, 'ORD-2026-0003', 'Used for order #3', 1, '2026-09-01 10:00:00'),
(11, 3, 'out', 40.00, 'ORD-2026-0003', 'Used for order #3', 1, '2026-09-01 10:00:00'),
(12, 5, 'out', 12.00, 'ORD-2026-0003', 'Used for order #3', 1, '2026-09-01 10:00:00'),
(13, 1, 'out', 150.00, 'ORD-2026-0004', 'Used for order #4', 1, '2026-09-01 11:00:00'),
(14, 2, 'out', 120.00, 'ORD-2026-0004', 'Used for order #4', 1, '2026-09-01 11:00:00'),
(15, 3, 'out', 60.00, 'ORD-2026-0004', 'Used for order #4', 1, '2026-09-01 11:00:00'),
(16, 1, 'adjust', -50.00, 'ADJ-001', 'Stock correction - spillage', 1, '2026-09-01 12:00:00'),
(17, 1, 'in', 1000.00, 'STK-002', 'Restock delivery', 1, '2026-09-01 14:00:00'),
(18, 2, 'in', 800.00, 'STK-002', 'Restock delivery', 1, '2026-09-01 14:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_usage`
--

CREATE TABLE `inventory_usage` (
  `id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `inventory_item_id` int UNSIGNED NOT NULL,
  `usage_per_kg` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_usage`
--

INSERT INTO `inventory_usage` (`id`, `service_id`, `inventory_item_id`, `usage_per_kg`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 15.0000, '2026-08-31 17:32:13', '2026-08-31 17:32:13'),
(2, 1, 2, 10.0000, '2026-08-31 17:32:13', '2026-08-31 17:32:13'),
(3, 1, 3, 5.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(4, 2, 1, 15.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(5, 2, 2, 10.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(6, 2, 3, 5.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(7, 2, 5, 3.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(8, 3, 1, 20.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(9, 3, 2, 15.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(10, 3, 3, 8.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(11, 3, 5, 5.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(12, 5, 1, 25.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(13, 5, 2, 20.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(14, 5, 3, 10.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(15, 6, 1, 20.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(16, 6, 2, 15.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14'),
(17, 6, 5, 5.0000, '2026-08-31 17:32:14', '2026-08-31 17:32:14');

-- --------------------------------------------------------

--
-- Table structure for table `laundry_orders`
--

CREATE TABLE `laundry_orders` (
  `id` int UNSIGNED NOT NULL,
  `order_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `assigned_employee_id` int UNSIGNED DEFAULT NULL,
  `status` enum('pending','washing','drying','ready','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `pickup_type` enum('walk_in','pickup','delivery') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'walk_in',
  `delivery_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `laundry_orders`
--

INSERT INTO `laundry_orders` (`id`, `order_no`, `customer_id`, `assigned_employee_id`, `status`, `payment_status`, `pickup_type`, `delivery_address`, `pickup_date`, `notes`, `subtotal`, `delivery_fee`, `discount`, `total`, `amount_paid`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'ORD-2026-0001', 3, 1, 'completed', 'paid', 'walk_in', NULL, '2026-09-01', NULL, 300.00, 0.00, 0.00, 300.00, 300.00, 1, '2026-09-01 08:30:00', '2026-09-01 14:00:00'),
(2, 'ORD-2026-0002', 4, 2, 'ready', 'paid', 'walk_in', NULL, '2026-09-01', 'Handle with care', 450.00, 0.00, 0.00, 450.00, 450.00, 1, '2026-09-01 09:15:00', '2026-09-01 15:30:00'),
(3, 'ORD-2026-0003', 5, 1, 'drying', 'unpaid', 'pickup', '123 Shaw Boulevard, Mandaluyong', '2026-09-02', NULL, 600.00, 50.00, 0.00, 650.00, 0.00, 1, '2026-09-01 10:00:00', '2026-09-01 16:00:00'),
(4, 'ORD-2026-0004', 6, 1, 'washing', 'partial', 'delivery', '567 Taft Avenue, Manila', '2026-09-02', 'Bulk order', 720.00, 50.00, 50.00, 720.00, 360.00, 1, '2026-09-01 11:00:00', '2026-09-01 16:30:00'),
(5, 'ORD-2026-0005', 7, 2, 'pending', 'unpaid', 'walk_in', NULL, '2026-09-01', NULL, 540.00, 0.00, 0.00, 540.00, 0.00, 1, '2026-09-01 12:00:00', '2026-09-01 12:00:00'),
(6, 'ORD-2026-0006', 8, 3, 'ready', 'paid', 'delivery', '234 Ortigas Avenue, Pasig', '2026-09-01', NULL, 380.00, 50.00, 0.00, 430.00, 430.00, 1, '2026-09-01 13:00:00', '2026-09-01 17:00:00'),
(7, 'ORD-2026-0007', 9, 1, 'washing', 'unpaid', 'walk_in', NULL, '2026-09-02', NULL, 270.00, 0.00, 0.00, 270.00, 0.00, 1, '2026-09-01 14:00:00', '2026-09-01 17:30:00'),
(8, 'ORD-2026-0008', 10, 2, 'drying', 'paid', 'walk_in', NULL, '2026-09-01', NULL, 200.00, 0.00, 0.00, 200.00, 200.00, 1, '2026-09-01 15:00:00', '2026-09-01 18:00:00'),
(9, 'ORD-2026-0009', 3, 1, 'pending', 'unpaid', 'pickup', '456 Mabini Street, Manila', '2026-09-02', NULL, 180.00, 50.00, 0.00, 230.00, 0.00, 1, '2026-09-01 16:00:00', '2026-09-01 16:00:00'),
(10, 'ORD-2026-0010', 4, 1, 'completed', 'paid', 'walk_in', NULL, '2026-09-01', NULL, 320.00, 0.00, 0.00, 320.00, 320.00, 1, '2026-09-01 17:00:00', '2026-09-01 18:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `service_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1, 1, 5.00, 60.00, 300.00),
(2, 2, 3, 3.00, 150.00, 450.00),
(3, 3, 2, 4.00, 90.00, 360.00),
(4, 3, 5, 1.00, 180.00, 180.00),
(5, 3, 1, 1.00, 60.00, 60.00),
(6, 4, 5, 4.00, 180.00, 720.00),
(7, 5, 1, 6.00, 60.00, 360.00),
(8, 5, 2, 2.00, 90.00, 180.00),
(9, 6, 6, 2.00, 120.00, 240.00),
(10, 6, 1, 2.00, 60.00, 120.00),
(11, 6, 1, 0.33, 60.00, 20.00),
(12, 7, 1, 4.50, 60.00, 270.00),
(13, 8, 4, 4.00, 50.00, 200.00),
(14, 9, 1, 3.00, 60.00, 180.00),
(15, 10, 1, 3.00, 60.00, 180.00),
(16, 10, 2, 1.00, 90.00, 90.00),
(17, 10, 4, 1.00, 50.00, 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` smallint UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint UNSIGNED NOT NULL,
  `slug` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'owner', 'Owner', 'Full access: orders, customers, services, staff, reports, settings'),
(2, 'staff', 'Staff', 'Laundry staff: manage orders and customers, update job status');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` tinyint UNSIGNED NOT NULL,
  `permission_id` smallint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(1, 2),
(2, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(2, 8),
(1, 9);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg' COMMENT 'Per kg, per piece, per set...',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `icon` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `unit`, `price`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Wash & Fold', 'kg', 60.00, 'droplets', 'Machine wash, dry and fold. Per kilogram. 1-day turnaround.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51'),
(2, 'Wash & Iron', 'kg', 90.00, 'shirt', 'Wash, dry and professional hand ironing. Per kilogram.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51'),
(3, 'Dry Cleaning', 'piece', 150.00, 'tag', 'Gentle chemical cleaning for suits, gowns and delicate fabrics.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51'),
(4, 'Iron Only', 'kg', 50.00, 'wrench', 'Press and fold already-washed clothes. Per kilogram.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51'),
(5, 'Comforter', 'piece', 180.00, 'layers', 'Full wash and dry for blankets, quilts and comforters.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51'),
(6, 'Shoes & Sneakers', 'pair', 120.00, 'shirt', 'Deep clean wash for footwear. Per pair.', 1, '2026-08-30 18:44:51', '2026-08-30 18:44:51');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` tinyint UNSIGNED NOT NULL,
  `business_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lavadora',
  `tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `business_name`, `tagline`, `address`, `phone`, `email`, `logo_path`, `hero_image_path`, `landing_html`, `delivery_fee`, `updated_at`) VALUES
(1, 'Lavadora', 'Fresh, clean laundry at your doorstep', '123 Rizal Street, Barangay Poblacion, Manila', '0917-000-0000', 'hello@lavadora.ph', NULL, NULL, NULL, 0.00, '2026-08-30 18:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `phone`, `avatar_path`, `is_active`, `last_login_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'thesis_2_owner@lavadora.local', 'thesis_2_owner', '$2y$10$fsuIqA7lHffMsgpsk2Y3l.S2sCnwbpwxbUkUqWM/cpJgBSh/nHODW', 'Thesis 2', 'Owner', '0917-000-0001', NULL, 1, '2026-09-01 23:24:27', NULL, '2026-08-30 18:44:50', '2026-09-01 23:24:27', NULL),
(2, 2, 'thesis_2_staff@lavadora.local', 'thesis_2_staff', '$2y$10$fsuIqA7lHffMsgpsk2Y3l.S2sCnwbpwxbUkUqWM/cpJgBSh/nHODW', 'Thesis 2', 'Staff', '0917-000-0002', NULL, 0, '2026-08-31 19:52:25', NULL, '2026-08-30 18:44:50', '2026-09-01 23:28:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_page_access`
--

CREATE TABLE `user_page_access` (
  `user_id` int UNSIGNED NOT NULL,
  `page_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_actor` (`actor_user_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_phone` (`phone`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_movements_item` (`inventory_item_id`),
  ADD KEY `fk_movements_user` (`created_by`);

--
-- Indexes for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usage_service_item` (`service_id`,`inventory_item_id`),
  ADD KEY `fk_usage_item` (`inventory_item_id`);

--
-- Indexes for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_no` (`order_no`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_employee` (`assigned_employee_id`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_order` (`order_id`),
  ADD KEY `fk_items_service` (`service_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pw_resets_user` (`user_id`),
  ADD KEY `idx_pw_resets_hash` (`token_hash`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_slug` (`slug`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `user_page_access`
--
ALTER TABLE `user_page_access`
  ADD PRIMARY KEY (`user_id`,`page_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_movements_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  ADD CONSTRAINT `fk_usage_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usage_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_orders_employee` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_page_access`
--
ALTER TABLE `user_page_access`
  ADD CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;