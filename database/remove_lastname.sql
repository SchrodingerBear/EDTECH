-- Remove last_name field from customers table
-- This will keep only first_name, phone, email, address, notes

-- First, let's update any existing data to combine first and last name into first_name
UPDATE customers 
SET first_name = CONCAT(first_name, ' ', last_name)
WHERE last_name IS NOT NULL AND last_name != '';

-- Now drop the last_name column
ALTER TABLE customers DROP COLUMN last_name;
