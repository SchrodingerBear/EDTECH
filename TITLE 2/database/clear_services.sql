-- Clear all existing services and add single 30 per kilo service
-- This script removes all services and adds only one service: Laundry Service at 30 per kilogram

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- First, delete all existing services
DELETE FROM services;

-- Reset auto-increment
ALTER TABLE services AUTO_INCREMENT = 1;

-- Insert single service: 30 per kilo
INSERT INTO services (id, name, unit, price, icon, description, is_active, created_at, updated_at) 
VALUES (1, 'Laundry Service', 'kg', 30.00, 'droplets', 'Professional laundry service at 30 per kilogram', 1, NOW(), NOW());

-- Update all order_items to use the new service ID
UPDATE order_items SET service_id = 1;

-- Optional: Clear inventory usage related to old services
DELETE FROM inventory_usage;

-- Reset auto-increment for inventory_usage
ALTER TABLE inventory_usage AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
