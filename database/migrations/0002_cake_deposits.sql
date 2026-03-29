-- Migration: Cake Deposit & Balance Payment Support (MySQL)

-- ── cake_sizes: add deposit_amount ──────────────────────────
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cake_sizes' AND column_name = 'deposit_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE cake_sizes ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 10.00', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE cake_sizes SET deposit_amount = 10.00 WHERE id = 1;
UPDATE cake_sizes SET deposit_amount = 12.00 WHERE id = 2;
UPDATE cake_sizes SET deposit_amount = 15.00 WHERE id = 3;
UPDATE cake_sizes SET deposit_amount = 20.00 WHERE id = 4;
UPDATE cake_sizes SET deposit_amount = 25.00 WHERE id = 5;
UPDATE cake_sizes SET deposit_amount = 30.00 WHERE id = 6;

-- ── cake_orders: deposit tracking columns ───────────────────
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cake_orders' AND column_name = 'full_price');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE cake_orders ADD COLUMN full_price DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN balance_due DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT ''paid'', ADD COLUMN balance_transaction_id INT NULL, ADD COLUMN customer_name VARCHAR(255) NULL, ADD COLUMN customer_phone VARCHAR(50) NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
