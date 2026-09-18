-- E-Receipt System for Laundry Orders
-- This adds functionality to generate shareable receipt links for customers

-- Add receipt tracking to laundry_orders table
ALTER TABLE laundry_orders 
ADD COLUMN receipt_token VARCHAR(32) UNIQUE DEFAULT NULL COMMENT 'Unique token for public receipt access',
ADD COLUMN receipt_sent_at DATETIME DEFAULT NULL COMMENT 'When receipt was sent to customer',
ADD COLUMN receipt_viewed_at DATETIME DEFAULT NULL COMMENT 'When customer first viewed receipt',
ADD COLUMN receipt_view_count INT UNSIGNED DEFAULT 0 COMMENT 'How many times receipt was viewed';

-- Create index for faster receipt token lookups
CREATE INDEX idx_receipt_token ON laundry_orders(receipt_token);

-- Update existing orders to generate receipt tokens
UPDATE laundry_orders 
SET receipt_token = CONCAT(SUBSTRING(MD5(CONCAT(id, order_no, created_at)), 1, 16), id)
WHERE receipt_token IS NULL;
