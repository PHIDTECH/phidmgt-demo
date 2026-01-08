-- Fix customer_id to allow NULL for pending orders
-- Run this in phpMyAdmin

-- First drop the foreign key constraint
ALTER TABLE projects DROP FOREIGN KEY projects_ibfk_1;

-- Modify customer_id to allow NULL
ALTER TABLE projects MODIFY customer_id INT(11) NULL DEFAULT NULL;

-- Re-add the foreign key with ON DELETE SET NULL
ALTER TABLE projects 
ADD CONSTRAINT projects_ibfk_1 
FOREIGN KEY (customer_id) REFERENCES users(id) 
ON DELETE SET NULL ON UPDATE CASCADE;
