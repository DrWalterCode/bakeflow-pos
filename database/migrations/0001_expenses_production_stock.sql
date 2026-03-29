-- Migration 0001: Add expenses, production, and stock tracking
-- MySQL compatible

-- ─── Expense categories ───
CREATE TABLE IF NOT EXISTS expense_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default categories (only if table is empty)
INSERT IGNORE INTO expense_categories (id, name, description) VALUES
    (1, 'Ingredients',   'Flour, sugar, butter, eggs, etc.'),
    (2, 'Packaging',     'Boxes, bags, labels, wrapping'),
    (3, 'Utilities',     'Electricity, water, gas'),
    (4, 'Rent',          'Premises rental'),
    (5, 'Staff Salary',  'Monthly salaries and wages'),
    (6, 'Transport',     'Delivery, fuel, vehicle maintenance'),
    (7, 'Equipment',     'Ovens, mixers, utensils, repairs'),
    (8, 'Marketing',     'Adverts, signage, promotions'),
    (9, 'Miscellaneous', 'Other uncategorised expenses');

-- ─── Expenses ───
CREATE TABLE IF NOT EXISTS expenses (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    expense_category_id  INT           NOT NULL,
    description          TEXT          NOT NULL,
    amount               DECIMAL(10,2) NOT NULL DEFAULT 0,
    expense_date         DATE          NOT NULL DEFAULT (CURDATE()),
    recorded_by          INT           NULL,
    receipt_ref          VARCHAR(255)  NULL,
    notes                TEXT          NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_expenses_category ON expenses(expense_category_id);
CREATE INDEX idx_expenses_date     ON expenses(expense_date);

-- ─── Production entries ───
CREATE TABLE IF NOT EXISTS production_entries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT          NOT NULL,
    quantity        INT          NOT NULL DEFAULT 0,
    produced_by     INT          NULL,
    batch_ref       VARCHAR(100) NULL,
    notes           TEXT         NULL,
    production_date DATE         NOT NULL DEFAULT (CURDATE()),
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (produced_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_production_product ON production_entries(product_id);
CREATE INDEX idx_production_date    ON production_entries(production_date);

-- ─── Add stock_quantity to products (if column doesn't exist) ───
-- MySQL: use a procedure to conditionally add the column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'stock_quantity');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
