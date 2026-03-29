-- BakeFlow POS — Seed Data
-- Default admin PIN: 1234 (bcrypt hash below)
-- Default admin password: admin
-- Change both immediately after first login!

-- -----------------------------------------------------
-- Default shop
-- -----------------------------------------------------
INSERT INTO shops (id, name, address, phone, receipt_header, receipt_footer, primary_color, currency_symbol)
VALUES (1, 'BakeFlow Bakery', '123 Main Street', '+1 555 0100',
        'Thank you for your purchase!', 'Come back soon!', '#C4748E', '$');

-- -----------------------------------------------------
-- Default users
-- Admin: username=admin, password=admin (bcrypt)
-- Cashier: username=cashier, PIN=1234 (bcrypt)
-- -----------------------------------------------------
INSERT INTO users (name, username, password_hash, pin_hash, role)
VALUES
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

-- PIN 1234 hash: $2y$12$zrinjHCNriOWqKXXNMvXYu8WxhS9y1nQhl/dyxOHRektiixP.iWIu
-- Admin password 'admin' hash: $2y$12$VD5..UijRu71qB70Ede04eWLJw/mG5MbfLO1SJRjDJRxxoC9SclrO

-- -----------------------------------------------------
-- Categories
-- -----------------------------------------------------
INSERT INTO categories (id, name, color, sort_order) VALUES
    (1, 'Pies',       '#D94F3D', 1),
    (2, 'Cupcakes',   '#E8631A', 2),
    (3, 'Muffins',    '#F0A500', 3),
    (4, 'Cookies',    '#8B6914', 4),
    (5, 'Cake Slices','#C0392B', 5),
    (6, 'Pastries',   '#9B59B6', 6),
    (7, 'Cakes',      '#2980B9', 7);

-- -----------------------------------------------------
-- Products — Pies ($1.50)
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (1, 'Steak Pie',             1.50, 1),
    (1, 'Chicken Mushroom Pie',  1.50, 2),
    (1, 'Beef Mince Pie',        1.50, 3),
    (1, 'Steak & Kidney Pie',    1.50, 4),
    (1, 'Pepper Steak Pie',      1.50, 5),
    (1, 'Bacon & Cheese Sticks', 1.50, 6);

-- -----------------------------------------------------
-- Products — Cupcakes
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (2, 'Cupcakes (Dozen)', 10.00, 1);

-- -----------------------------------------------------
-- Products — Muffins
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (3, 'Muffins Standard (Dozen)', 10.00, 1),
    (3, 'Muffins Jumbo (Dozen)',    20.00, 2);

-- -----------------------------------------------------
-- Products — Cookies ($1.00)
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (4, 'Oatmeal Cookie',       1.00, 1),
    (4, 'Choc Chip Cookie',     1.00, 2),
    (4, 'Snickerdoodle Cookie', 1.00, 3);

-- -----------------------------------------------------
-- Products — Cake Slices
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (5, 'Cake Slice',     2.00, 1),
    (5, 'Fudge Brownie',  2.00, 2),
    (5, 'Cherry Almond',  3.00, 3);

-- -----------------------------------------------------
-- Products — Pastries (price 0 = TBD, set in admin)
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, sort_order) VALUES
    (6, 'Donut',           0.00, 1),
    (6, 'Samosa',          0.00, 2),
    (6, 'Croissant',       0.00, 3),
    (6, 'Cinnamon Roll',   0.00, 4),
    (6, 'Tray Bake',       0.00, 5);

-- -----------------------------------------------------
-- Products — Cakes (is_cake=1, price set via cake_sizes)
-- -----------------------------------------------------
INSERT INTO products (category_id, name, price, is_cake, sort_order) VALUES
    (7, 'Custom Cake', 0.00, 1, 1);

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
INSERT INTO settings (key, value) VALUES
    ('terminal_id',         'TXN001'),
    ('idle_timeout',        '600'),
    ('sync_interval',       '300'),
    ('sync_remote_url',     ''),
    ('sync_api_key',        ''),
    ('receipt_copies',      '1'),
    ('tax_rate',            '0'),
    ('currency_symbol',     '$'),
    ('products_cache_ttl',  '300');
