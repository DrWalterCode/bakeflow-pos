-- Migration 0004: Add quick-access product flags

ALTER TABLE products ADD COLUMN is_quick_item TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN quick_item_order INT NOT NULL DEFAULT 0;
