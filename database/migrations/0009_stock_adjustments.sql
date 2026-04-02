CREATE TABLE IF NOT EXISTS stock_adjustments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    product_id        INT NOT NULL,
    adjustment_type   ENUM('increase','decrease') NOT NULL,
    quantity          INT NOT NULL DEFAULT 0,
    reason_code       VARCHAR(100) NOT NULL,
    reason_label      VARCHAR(255) NOT NULL,
    notes             TEXT NULL,
    previous_quantity INT NOT NULL DEFAULT 0,
    new_quantity      INT NOT NULL DEFAULT 0,
    adjusted_by       INT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (adjusted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_stock_adjustments_product ON stock_adjustments(product_id);
CREATE INDEX idx_stock_adjustments_created ON stock_adjustments(created_at);
CREATE INDEX idx_stock_adjustments_reason  ON stock_adjustments(reason_code);
