-- BakeFlow POS — MySQL Schema
-- Charset: UTF-8 (utf8mb4)

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- shops
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS shops (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255) NOT NULL DEFAULT 'BakeFlow Bakery',
    logo_path        VARCHAR(500) NULL,
    address          TEXT         NULL,
    phone            VARCHAR(50)  NULL,
    email            VARCHAR(255) NULL,
    receipt_header   TEXT         NULL,
    receipt_footer   TEXT         NULL,
    primary_color    VARCHAR(20)  NOT NULL DEFAULT '#E8631A',
    currency_symbol  VARCHAR(10)  NOT NULL DEFAULT '$',
    tax_rate         DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255) NOT NULL,
    username         VARCHAR(100) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NULL,
    pin_hash         VARCHAR(255) NULL,
    role             ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    pin_fail_count   INT          NOT NULL DEFAULT 0,
    pin_locked_until DATETIME     NULL,
    last_login_at    DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    color      VARCHAR(20)  NOT NULL DEFAULT '#6c757d',
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- products
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    category_id    INT          NOT NULL,
    name           VARCHAR(255) NOT NULL,
    description    TEXT         NULL,
    price          DECIMAL(10,2) NOT NULL DEFAULT 0,
    barcode        VARCHAR(100) NULL UNIQUE,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    is_cake        TINYINT(1)   NOT NULL DEFAULT 0,
    stock_quantity INT          NOT NULL DEFAULT 0,
    is_quick_item  TINYINT(1)   NOT NULL DEFAULT 0,
    quick_item_order INT        NOT NULL DEFAULT 0,
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- cake_sizes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_sizes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    label          VARCHAR(100) NOT NULL,
    price_base     DECIMAL(10,2) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- cake_flavours
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_flavours (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- transactions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref  VARCHAR(100) NOT NULL UNIQUE,
    cashier_id       INT          NOT NULL,
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount         DECIMAL(10,2) NOT NULL DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method   ENUM('cash','card','mobile','split') NOT NULL DEFAULT 'cash',
    cash_tendered    DECIMAL(10,2) NOT NULL DEFAULT 0,
    change_given     DECIMAL(10,2) NOT NULL DEFAULT 0,
    card_amount      DECIMAL(10,2) NOT NULL DEFAULT 0,
    reference_number VARCHAR(255) NULL,
    status           ENUM('completed','voided','refunded') NOT NULL DEFAULT 'completed',
    terminal_id      VARCHAR(50)  NOT NULL DEFAULT 'TXN001',
    sync_status      ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    notes            TEXT         NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_transactions_cashier ON transactions(cashier_id);
CREATE INDEX idx_transactions_sync    ON transactions(sync_status);
CREATE INDEX idx_transactions_created ON transactions(created_at);

-- -----------------------------------------------------
-- transaction_items
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_items (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id   INT          NOT NULL,
    product_id       INT          NULL,
    product_name     VARCHAR(255) NOT NULL,
    unit_price       DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity         INT          NOT NULL DEFAULT 1,
    line_total       DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_txn_items_transaction ON transaction_items(transaction_id);

-- -----------------------------------------------------
-- cake_orders
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_orders (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    transaction_item_id     INT          NOT NULL,
    flavour_id              INT          NULL,
    size_id                 INT          NULL,
    shape                   ENUM('round','square') NOT NULL DEFAULT 'round',
    inscription             VARCHAR(500) NULL,
    pickup_date             DATE         NULL,
    notes                   TEXT         NULL,
    additional_cost         DECIMAL(10,2) NOT NULL DEFAULT 0,
    full_price              DECIMAL(10,2) NOT NULL DEFAULT 0,
    deposit_amount          DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid             DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance_due             DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status          ENUM('deposit','partial','paid') NOT NULL DEFAULT 'paid',
    order_status            ENUM('pending','in_production','ready','collected') NOT NULL DEFAULT 'pending',
    balance_transaction_id  INT          NULL,
    customer_name           VARCHAR(255) NULL,
    customer_phone          VARCHAR(50)  NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_item_id) REFERENCES transaction_items(id) ON DELETE CASCADE,
    FOREIGN KEY (flavour_id) REFERENCES cake_flavours(id) ON DELETE SET NULL,
    FOREIGN KEY (size_id) REFERENCES cake_sizes(id) ON DELETE SET NULL,
    FOREIGN KEY (balance_transaction_id) REFERENCES transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cake_orders_status       ON cake_orders(payment_status);
CREATE INDEX idx_cake_orders_pickup       ON cake_orders(pickup_date);
CREATE INDEX idx_cake_orders_order_status ON cake_orders(order_status);

-- -----------------------------------------------------
-- sync_log
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    direction     ENUM('push','pull') NOT NULL DEFAULT 'push',
    status        ENUM('success','failed','pending') NOT NULL DEFAULT 'pending',
    records_count INT      NOT NULL DEFAULT 0,
    error_msg     TEXT     NULL,
    synced_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    `key`      VARCHAR(100) NOT NULL UNIQUE,
    value      TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- daily_closings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_closings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    date          VARCHAR(20)   NOT NULL UNIQUE,
    cashier_id    INT           NULL,
    expected_cash DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_cash   DECIMAL(10,2) NOT NULL DEFAULT 0,
    difference    DECIMAL(10,2) GENERATED ALWAYS AS (actual_cash - expected_cash) STORED,
    notes         TEXT          NULL,
    closed_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- expense_categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- expenses
-- -----------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_expenses_category ON expenses(expense_category_id);
CREATE INDEX idx_expenses_date     ON expenses(expense_date);

-- -----------------------------------------------------
-- production_entries
-- -----------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_production_product ON production_entries(product_id);
CREATE INDEX idx_production_date    ON production_entries(production_date);

-- -----------------------------------------------------
-- specials / promotions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS specials (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(255)  NOT NULL,
    description    TEXT          NULL,
    discount_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    applies_to     ENUM('all','category','product') NOT NULL DEFAULT 'all',
    target_id      INT           NULL,
    start_date     DATE          NOT NULL,
    end_date       DATE          NOT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- migrations tracking
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    filename   VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
