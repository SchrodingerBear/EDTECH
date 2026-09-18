-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 01, 2026 at 03:29 PM
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

-- ============================================================
-- TEST DATA POPULATION
-- ============================================================

-- --------------------------------------------------------
-- Additional Customers
-- --------------------------------------------------------

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `phone`, `email`, `address`, `notes`, `created_at`, `updated_at`) VALUES
(3, 'Maria', 'Santos', '0917-123-4567', 'maria.santos@email.com', '456 Mabini Street, Manila', 'Regular customer, prefers wash & fold', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
(4, 'Juan', 'Reyes', '0918-234-5678', 'juan.reyes@email.com', '789 Aurora Boulevard, Quezon City', 'Delicate fabrics only', '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
(5, 'Ana', 'Garcia', '0919-345-6789', 'ana.garcia@email.com', '123 Shaw Boulevard, Mandaluyong', 'Prefers dry cleaning for suits', '2026-09-01 10:00:00', '2026-09-01 10:00:00'),
(6, 'Carlos', 'Diaz', '0920-456-7890', 'carlos.diaz@email.com', '567 Taft Avenue, Manila', 'Bulk orders - comforters and blankets', '2026-09-01 11:00:00', '2026-09-01 11:00:00'),
(7, 'Elena', 'Ramos', '0921-567-8901', 'elena.ramos@email.com', '890 EDSA, Makati', 'VIP customer', '2026-09-01 12:00:00', '2026-09-01 12:00:00'),
(8, 'Miguel', 'Torres', '0922-678-9012', 'miguel.torres@email.com', '234 Ortigas Avenue, Pasig', 'Regular pickup/delivery', '2026-09-01 13:00:00', '2026-09-01 13:00:00'),
(9, 'Sofia', 'Castillo', '0923-789-0123', 'sofia.castillo@email.com', '678 Bonifacio Street, Taguig', 'New customer', '2026-09-01 14:00:00', '2026-09-01 14:00:00'),
(10, 'Diego', 'Flores', '0924-890-1234', 'diego.flores@email.com', '901 Del Monte Avenue, Quezon City', 'Prefers iron only service', '2026-09-01 15:00:00', '2026-09-01 15:00:00');

-- --------------------------------------------------------
-- Employees
-- --------------------------------------------------------

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `phone`, `position`, `salary`, `hire_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Roberto', 'Cruz', '0915-111-2222', 'Washing Supervisor', 18000.00, '2026-08-01', 1, '2026-08-01 00:00:00', '2026-08-01 00:00:00'),
(2, 'Luisa', 'Fernandez', '0915-222-3333', 'Ironing Specialist', 16500.00, '2026-08-05', 1, '2026-08-05 00:00:00', '2026-08-05 00:00:00'),
(3, 'Antonio', 'Lopez', '0915-333-4444', 'Delivery Driver', 15000.00, '2026-08-10', 1, '2026-08-10 00:00:00', '2026-08-10 00:00:00'),
(4, 'Carmen', 'Gomez', '0915-444-5555', 'Front Desk', 14500.00, '2026-08-15', 1, '2026-08-15 00:00:00', '2026-08-15 00:00:00'),
(5, 'Jose', 'Martinez', '0915-555-6666', 'Quality Control', 17000.00, '2026-08-20', 1, '2026-08-20 00:00:00', '2026-08-20 00:00:00');

-- --------------------------------------------------------
-- Laundry Orders
-- --------------------------------------------------------

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
-- Order Items
-- --------------------------------------------------------

INSERT INTO `order_items` (`id`, `order_id`, `service_id`, `quantity`, `unit_price`, `line_total`) VALUES
-- Order 1 - Wash & Fold
(1, 1, 1, 5.00, 60.00, 300.00),
-- Order 2 - Dry Cleaning
(2, 2, 3, 3.00, 150.00, 450.00),
-- Order 3 - Wash & Iron + Comforter
(3, 3, 2, 4.00, 90.00, 360.00),
(4, 3, 5, 1.00, 180.00, 180.00),
(5, 3, 1, 1.00, 60.00, 60.00),
-- Order 4 - Bulk comforters
(6, 4, 5, 4.00, 180.00, 720.00),
-- Order 5 - Mixed services
(7, 5, 1, 6.00, 60.00, 360.00),
(8, 5, 2, 2.00, 90.00, 180.00),
-- Order 6 - Shoes + Wash & Fold
(9, 6, 6, 2.00, 120.00, 240.00),
(10, 6, 1, 2.00, 60.00, 120.00),
(11, 6, 1, 0.33, 60.00, 20.00),
-- Order 7 - Wash & Fold
(12, 7, 1, 4.50, 60.00, 270.00),
-- Order 8 - Iron Only
(13, 8, 4, 4.00, 50.00, 200.00),
-- Order 9 - Wash & Fold
(14, 9, 1, 3.00, 60.00, 180.00),
-- Order 10 - Mixed
(15, 10, 1, 3.00, 60.00, 180.00),
(16, 10, 2, 1.00, 90.00, 90.00),
(17, 10, 4, 1.00, 50.00, 50.00);

-- --------------------------------------------------------
-- Inventory Movements
-- --------------------------------------------------------

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
-- Additional Audit Logs
-- --------------------------------------------------------

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `action`, `module`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `meta_json`, `created_at`) VALUES
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

-- ============================================================
-- UPDATE AUTO_INCREMENT VALUES
-- ============================================================

ALTER TABLE `customers` AUTO_INCREMENT = 11;
ALTER TABLE `employees` AUTO_INCREMENT = 6;
ALTER TABLE `laundry_orders` AUTO_INCREMENT = 11;
ALTER TABLE `order_items` AUTO_INCREMENT = 18;
ALTER TABLE `inventory_movements` AUTO_INCREMENT = 19;
ALTER TABLE `audit_logs` AUTO_INCREMENT = 32;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
