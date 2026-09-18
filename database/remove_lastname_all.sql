-- Remove last_name field from all tables that have it
-- This will remove last_name from: customers, users, employees

-- Update customers table
UPDATE customers 
SET first_name = CONCAT(first_name, ' ', last_name)
WHERE last_name IS NOT NULL AND last_name != '';
ALTER TABLE customers DROP COLUMN last_name;

-- Update users table  
UPDATE users 
SET first_name = CONCAT(first_name, ' ', last_name)
WHERE last_name IS NOT NULL AND last_name != '';
ALTER TABLE users DROP COLUMN last_name;

-- Update employees table
UPDATE employees 
SET first_name = CONCAT(first_name, ' ', last_name)
WHERE last_name IS NOT NULL AND last_name != '';
ALTER TABLE employees DROP COLUMN last_name;
