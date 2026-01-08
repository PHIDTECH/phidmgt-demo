-- Add recurring task fields to tasks table
ALTER TABLE tasks 
ADD COLUMN is_recurring TINYINT(1) DEFAULT 0,
ADD COLUMN recurrence_type ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none',
ADD COLUMN recurrence_day INT DEFAULT NULL COMMENT 'Day of month (1-31) for monthly, or day of year (1-365) for yearly',
ADD COLUMN recurrence_month INT DEFAULT NULL COMMENT 'Month (1-12) for yearly recurrence',
ADD COLUMN recurrence_start_date DATE DEFAULT NULL,
ADD COLUMN recurrence_end_date DATE DEFAULT NULL,
ADD COLUMN last_recurrence_date DATE DEFAULT NULL,
ADD COLUMN next_recurrence_date DATE DEFAULT NULL,
ADD COLUMN notify_customer TINYINT(1) DEFAULT 0 COMMENT 'Send notification to project customer',
ADD COLUMN notify_days_before INT DEFAULT 1 COMMENT 'Days before due date to send reminder';

-- Index for efficient recurring task queries
CREATE INDEX idx_recurring ON tasks(is_recurring, next_recurrence_date);
