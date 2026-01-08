-- Add customer_id to revenue table for tracking revenue from customers directly
-- Run this SQL on your phidtechog_db database

-- Step 1: Add customer_id column to revenue table
ALTER TABLE revenue ADD COLUMN customer_id INT NULL AFTER id;

-- Step 2: Add foreign key constraint
ALTER TABLE revenue ADD CONSTRAINT revenue_customer_fk FOREIGN KEY (customer_id) REFERENCES users(id);

-- Step 3: Add payment_method and transaction_reference for Selcom payments
ALTER TABLE revenue ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER category;
ALTER TABLE revenue ADD COLUMN transaction_reference VARCHAR(100) DEFAULT NULL AFTER payment_method;
ALTER TABLE revenue ADD COLUMN payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'completed' AFTER transaction_reference;
