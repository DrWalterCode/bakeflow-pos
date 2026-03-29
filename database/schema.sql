-- BakeFlow POS — SQLite Schema
-- Charset: UTF-8

PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

-- -----------------------------------------------------
-- shops
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS shops (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT    NOT NULL DEFAULT 'BakeFlow Bakery',
    logo_path        TEXT    NULL,
    address          TEXT    NULL,
    phone            TEXT    NULL,
    email            TEXT    NULL,
    receipt_header   TEXT    NULL DEFAULT 'Thank you for your purchase!',
    receipt_footer   TEXT    NULL DEFAULT 'Please come again.',
    primary_color    TEXT    NOT NULL DEFAULT '#E8631A',
    currency_symbol  TEXT    NOT NULL DEFAULT '$',
    tax_rate         REAL    NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT    NOT NULL,
    username         TEXT    NOT NULL UNIQUE,
    password_hash    TEXT    NULL,
    pin_hash         TEXT    NULL,
    role             TEXT    NOT NULL DEFAULT 'cashier' CHECK(role IN ('admin','cashier')),
    is_active        INTEGER NOT NULL DEFAULT 1,
    pin_fail_count   INTEGER NOT NULL DEFAULT 0,
    pin_locked_until DATETIME NULL,
    last_login_at    DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    color      TEXT    NOT NULL DEFAULT '#6c757d',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active  INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- products
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    name        TEXT    NOT NULL,
    description TEXT    NULL,
    price       REAL    NOT NULL DEFAULT 0,
    barcode     TEXT    NULL UNIQUE,
    is_active   INTEGER NOT NULL DEFAULT 1,
    is_cake        INTEGER NOT NULL DEFAULT 0,
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    sort_order     INTEGER NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- cake_sizes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_sizes (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    name           TEXT    NOT NULL,
    label          TEXT    NOT NULL,
    price_base     REAL    NOT NULL DEFAULT 0,
    deposit_amount REAL    NOT NULL DEFAULT 10.00,
    is_active      INTEGER NOT NULL DEFAULT 1,
    sort_order     INTEGER NOT NULL DEFAULT 0
);

-- -----------------------------------------------------
-- cake_flavours
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_flavours (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    is_active  INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

-- -----------------------------------------------------
-- transactions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_ref  TEXT    NOT NULL UNIQUE,
    cashier_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    subtotal         REAL    NOT NULL DEFAULT 0,
    discount         REAL    NOT NULL DEFAULT 0,
    total            REAL    NOT NULL DEFAULT 0,
    payment_method   TEXT    NOT NULL DEFAULT 'cash' CHECK(payment_method IN ('cash','card','mobile','split')),
    cash_tendered    REAL    NOT NULL DEFAULT 0,
    change_given     REAL    NOT NULL DEFAULT 0,
    card_amount      REAL    NOT NULL DEFAULT 0,
    reference_number TEXT    NULL,
    status           TEXT    NOT NULL DEFAULT 'completed' CHECK(status IN ('completed','voided','refunded')),
    terminal_id      TEXT    NOT NULL DEFAULT 'TXN001',
    sync_status      TEXT    NOT NULL DEFAULT 'pending' CHECK(sync_status IN ('pending','synced','failed')),
    notes            TEXT    NULL,
    created_at       DATETIME NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_transactions_cashier   ON transactions(cashier_id);
CREATE INDEX IF NOT EXISTS idx_transactions_sync      ON transactions(sync_status);
CREATE INDEX IF NOT EXISTS idx_transactions_created   ON transactions(created_at);

-- -----------------------------------------------------
-- transaction_items
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_items (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id   INTEGER NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    product_id       INTEGER NULL REFERENCES products(id) ON DELETE SET NULL,
    product_name     TEXT    NOT NULL,
    unit_price       REAL    NOT NULL DEFAULT 0,
    quantity         INTEGER NOT NULL DEFAULT 1,
    line_total       REAL    NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_txn_items_transaction ON transaction_items(transaction_id);

-- -----------------------------------------------------
-- cake_orders
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cake_orders (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_item_id     INTEGER NOT NULL REFERENCES transaction_items(id) ON DELETE CASCADE,
    flavour_id              INTEGER NULL REFERENCES cake_flavours(id) ON DELETE SET NULL,
    size_id                 INTEGER NULL REFERENCES cake_sizes(id) ON DELETE SET NULL,
    shape                   TEXT    NOT NULL DEFAULT 'round' CHECK(shape IN ('round','square')),
    inscription             TEXT    NULL,
    pickup_date             DATE    NULL,
    notes                   TEXT    NULL,
    full_price              REAL    NOT NULL DEFAULT 0,
    deposit_amount          REAL    NOT NULL DEFAULT 0,
    amount_paid             REAL    NOT NULL DEFAULT 0,
    balance_due             REAL    NOT NULL DEFAULT 0,
    payment_status          TEXT    NOT NULL DEFAULT 'paid' CHECK(payment_status IN ('deposit','partial','paid')),
    order_status            TEXT    NOT NULL DEFAULT 'pending' CHECK(order_status IN ('pending','in_production','ready','collected')),
    balance_transaction_id  INTEGER NULL REFERENCES transactions(id),
    customer_name           TEXT    NULL,
    customer_phone          TEXT    NULL,
    created_at              DATETIME NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_cake_orders_status ON cake_orders(payment_status);
CREATE INDEX IF NOT EXISTS idx_cake_orders_pickup ON cake_orders(pickup_date);
CREATE INDEX IF NOT EXISTS idx_cake_orders_order_status ON cake_orders(order_status);

-- -----------------------------------------------------
-- sync_log
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_log (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    direction     TEXT    NOT NULL DEFAULT 'push' CHECK(direction IN ('push','pull')),
    status        TEXT    NOT NULL DEFAULT 'pending' CHECK(status IN ('success','failed','pending')),
    records_count INTEGER NOT NULL DEFAULT 0,
    error_msg     TEXT    NULL,
    synced_at     DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    key        TEXT    NOT NULL UNIQUE,
    value      TEXT    NULL,
    updated_at DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- daily_closings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_closings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    date          TEXT    NOT NULL UNIQUE,
    cashier_id    INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    expected_cash REAL    NOT NULL DEFAULT 0,
    actual_cash   REAL    NOT NULL DEFAULT 0,
    difference    REAL    GENERATED ALWAYS AS (actual_cash - expected_cash) STORED,
    notes         TEXT    NULL,
    closed_at     DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- expense_categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    description TEXT    NULL,
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- -----------------------------------------------------
-- expenses
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_category_id  INTEGER NOT NULL REFERENCES expense_categories(id) ON DELETE RESTRICT,
    description          TEXT    NOT NULL,
    amount               REAL    NOT NULL DEFAULT 0,
    expense_date         DATE    NOT NULL DEFAULT (date('now')),
    recorded_by          INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    receipt_ref          TEXT    NULL,
    notes                TEXT    NULL,
    created_at           DATETIME NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(expense_category_id);
CREATE INDEX IF NOT EXISTS idx_expenses_date     ON expenses(expense_date);

-- -----------------------------------------------------
-- production_entries
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS production_entries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id      INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity        INTEGER NOT NULL DEFAULT 0,
    produced_by     INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    batch_ref       TEXT    NULL,
    notes           TEXT    NULL,
    production_date DATE    NOT NULL DEFAULT (date('now')),
    created_at      DATETIME NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_production_product ON production_entries(product_id);
CREATE INDEX IF NOT EXISTS idx_production_date    ON production_entries(production_date);

-- -----------------------------------------------------
-- specials / promotions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS specials (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    description   TEXT    NULL,
    discount_type TEXT    NOT NULL DEFAULT 'percent' CHECK(discount_type IN ('percent','fixed')),
    discount_value REAL   NOT NULL DEFAULT 0,
    applies_to    TEXT    NOT NULL DEFAULT 'all' CHECK(applies_to IN ('all','category','product')),
    target_id     INTEGER NULL,
    start_date    DATE    NOT NULL,
    end_date      DATE    NOT NULL,
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT (datetime('now'))
);
