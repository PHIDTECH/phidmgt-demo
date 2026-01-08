-- Fix for foreign key constraint error on projects.service_id
-- Run this SQL on your phidtechog_db database

-- Step 1: Drop the foreign key constraint
ALTER TABLE projects DROP FOREIGN KEY projects_ibfk_2;

-- Step 2: Make service_id nullable and add service_name column if not exists
ALTER TABLE projects MODIFY COLUMN service_id INT NULL;

-- Step 3: Add service_name column if it doesn't exist (ignore error if already exists)
ALTER TABLE projects ADD COLUMN service_name VARCHAR(255) DEFAULT NULL;

-- Step 4: Optionally re-add the foreign key with ON DELETE SET NULL (optional)
-- ALTER TABLE projects ADD CONSTRAINT projects_ibfk_2 FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL;
