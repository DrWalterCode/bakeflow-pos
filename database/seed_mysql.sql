-- BakeFlow POS — MySQL Seed Data

-- -----------------------------------------------------
-- Default shop
-- -----------------------------------------------------
INSERT INTO shops (id, name, address, phone, receipt_header, receipt_footer, primary_color, currency_symbol)
VALUES (1, 'BakeFlow Bakery', '123 Main Street', '+1 555 0100',
        'Thank you for your purchase!', 'Come back soon!', '#C4748E', '$');

-- -----------------------------------------------------
-- Default users
-- -----------------------------------------------------
INSERT INTO users (name, username, password_hash, pin_hash, role) VALUES
    ('Admin User',    'admin',
     '$2y$12$VD5..UijRu71qB70Ede04eWLJw/mG5MbfLO1SJRjDJRxxoC9SclrO',
     NULL, 'admin'),
    ('Alice Cashier', 'alice',
     NULL,
     '$2y$12$zrinjHCNriOWqKXXNMvXYu8WxhS9y1nQhl/dyxOHRektiixP.iWIu',
     'cashier'),
    ('Bob Cashier',   'bob',
     NULL,
     '$2y$12$zrinjHCNriOWqKXXNMvXYu8WxhS9y1nQhl/dyxOHRektiixP.iWIu',
     'cashier');

-- -----------------------------------------------------
-- Categories
-- -----------------------------------------------------
INSERT INTO categories (id, name, color, sort_order, is_active) VALUES
    (1, 'Pies',        '#D94F3D', 1, 1),
    (2, 'Cupcakes',    '#E8631A', 2, 1),
    (3, 'Muffins',     '#F0A500', 3, 0),
    (4, 'Cookies',     '#8B6914', 4, 1),
    (5, 'Cake Slices', '#C0392B', 5, 0),
    (6, 'Pastries',    '#9B59B6', 6, 1),
    (7, 'Cakes',       '#2980B9', 7, 1);

-- -----------------------------------------------------
-- Products
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, is_quick_item, quick_item_order, sort_order) VALUES
    (1, 'Beef and Mince Pie (BMP)',        1.50, 1,  1, 1),
    (1, 'Chicken and Mushroom Pie (CMP)',  1.50, 1,  2, 2),
    (1, 'Sausage Roll',                    1.50, 1,  3, 3);

INSERT INTO products (category_id, name, price, is_quick_item, quick_item_order, sort_order) VALUES
    (2, 'Cup Cakes',                       0.50, 1, 10, 1);

INSERT INTO products (category_id, name, price, is_quick_item, quick_item_order, sort_order) VALUES
    (4, 'Large Biscuits',                  1.00, 1, 11, 1);

INSERT INTO products (category_id, name, price, is_quick_item, quick_item_order, sort_order) VALUES
    (6, 'Samosa - Beef',                   1.50, 1,  4, 1),
    (6, 'Samosa - Chicken',                1.50, 1,  5, 2),
    (6, 'Croissant',                       1.50, 1,  6, 3),
    (6, 'Cinnamon Rolls',                  1.50, 1,  7, 4),
    (6, 'Scone',                           1.50, 1,  8, 5),
    (6, 'Donuts',                          1.50, 1,  9, 6),
    (6, 'Cream Puff',                      1.50, 1, 14, 7);

INSERT INTO products (category_id, name, price, is_quick_item, quick_item_order, sort_order) VALUES
    (7, 'Vanilla Cake',                    2.00, 1, 12, 1),
    (7, 'Lemon Cake',                      2.00, 1, 13, 2);

INSERT INTO products (category_id, name, price, is_cake, is_quick_item, quick_item_order, sort_order) VALUES
    (7, 'Custom Cake', 0.00, 1, 0, 0, 99);

-- -----------------------------------------------------
-- Cake Sizes
-- -----------------------------------------------------
INSERT INTO cake_sizes (id, name, label, price_base, deposit_amount, sort_order) VALUES
    (1, 'Small',        'Small',        20.00, 10.00, 1),
    (2, 'Medium',       'Medium',       25.00, 12.00, 2),
    (3, 'Large',        'Large',        30.00, 15.00, 3),
    (4, 'XL',           'XL',           40.00, 20.00, 4),
    (5, 'Double Layer', 'Double Layer', 50.00, 25.00, 5),
    (6, '16-inch',      '16-inch',      65.00, 30.00, 6);

-- -----------------------------------------------------
-- Cake Flavours
-- -----------------------------------------------------
INSERT INTO cake_flavours (id, name, sort_order) VALUES
    (1,  'Chocolate',           1),
    (2,  'Black Forest',        2),
    (3,  'Vanilla',             3),
    (4,  'Red Velvet',          4),
    (5,  'Marble',              5),
    (6,  'Lemon Poppy Seed',    6),
    (7,  'Orange',              7),
    (8,  'Banana',              8),
    (9,  'Strawberry',          9),
    (10, 'German Chocolate',    10);

-- -----------------------------------------------------
-- Expense Categories
-- -----------------------------------------------------
INSERT INTO expense_categories (id, name, description) VALUES
    (1, 'Ingredients',   'Flour, sugar, butter, eggs, etc.'),
    (2, 'Packaging',     'Boxes, bags, labels, wrapping'),
    (3, 'Utilities',     'Electricity, water, gas'),
    (4, 'Rent',          'Premises rental'),
    (5, 'Staff Salary',  'Monthly salaries and wages'),
    (6, 'Transport',     'Delivery, fuel, vehicle maintenance'),
    (7, 'Equipment',     'Ovens, mixers, utensils, repairs'),
    (8, 'Marketing',     'Adverts, signage, promotions'),
    (9, 'Miscellaneous', 'Other uncategorised expenses'),
    (10, 'Staff Lunch',  'Staff meals and lunch expenses');

-- -----------------------------------------------------
-- Default Settings
-- -----------------------------------------------------
INSERT INTO settings (`key`, value) VALUES
    ('terminal_id',         'TXN001'),
    ('idle_timeout',        '600'),
    ('sync_interval',       '300'),
    ('sync_remote_url',     ''),
    ('sync_api_key',        ''),
    ('receipt_copies',      '1'),
    ('tax_rate',            '0'),
    ('currency_symbol',     '$'),
    ('products_cache_ttl',  '300');

-- -----------------------------------------------------
-- Mark all migrations as applied (schema is current)
-- -----------------------------------------------------
INSERT INTO migrations (filename) VALUES
    ('0001_expenses_production_stock.sql'),
    ('0002_cake_deposits.sql'),
    ('0003_cake_order_status.sql');
