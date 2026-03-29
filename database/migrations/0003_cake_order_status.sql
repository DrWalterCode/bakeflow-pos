-- Migration: Cake Order Status Tracking (MySQL)

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cake_orders' AND column_name = 'order_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE cake_orders ADD COLUMN order_status VARCHAR(20) NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing fully-paid orders are already collected
UPDATE cake_orders SET order_status = 'collected' WHERE payment_status = 'paid';
