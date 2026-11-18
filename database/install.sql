-- install.sql
-- FixerUpper Secure Store: complete installation. THIS IS THE FILE TO IMPORT.
--
-- Creates the fixerupper_summative database, all six tables and the catalogue.
-- It only ever uses the database named fixerupper_summative, and it never touches
-- the separate NovaStore database (product_catalog).
--
-- HOW TO RUN
--   phpMyAdmin: open the Import tab with no database selected, choose this file.
--   mysql client: mysql -u YOUR_USER -p --host=127.0.0.1 --port=8889 < database/install.sql
--
-- SAFE ON AN EXISTING INSTALLATION
--   No table is dropped or replaced (CREATE TABLE IF NOT EXISTS), and a row is
--   added only if it is not already there, so running it twice adds nothing.
--   To upgrade an older FixerUpper database, use the numbered migrations instead:
--   this file assumes a database that is empty or was created by this file.
--
-- AFTERWARDS
--   Create an administrator account (see the end of this file), and copy
--   config/db.example.php to config/db.php with your own database details.
--
-- No user accounts, password hashes or orders are included. No payment
-- information is stored anywhere. Prices and stock are demonstration values.

CREATE DATABASE IF NOT EXISTS fixerupper_summative
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE fixerupper_summative;

-- ---------------------------------------------------------------------
-- PART 1: TABLES
-- ---------------------------------------------------------------------

-- Product categories.
CREATE TABLE IF NOT EXISTS categories (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product catalogue.
CREATE TABLE IF NOT EXISTS products (
  id           INT           NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120)  NOT NULL,
  description  VARCHAR(500)  NOT NULL,
  category_id  INT           DEFAULT NULL,
  price        DECIMAL(10,2) NOT NULL,
  stock        INT UNSIGNED  NOT NULL DEFAULT 0,
  featured     TINYINT(1)    NOT NULL DEFAULT 0,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,   -- 0 = withdrawn from sale
  image_url    VARCHAR(500)  DEFAULT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_products_category (category_id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_products_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrators and customers.
-- `password` stores a password_hash() bcrypt hash, never plain text.
CREATE TABLE IF NOT EXISTS users (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        VARCHAR(30)  NOT NULL DEFAULT 'customer',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email),
  CONSTRAINT chk_users_role CHECK (role IN ('admin', 'customer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1. LOGIN ATTEMPTS
-- ---------------------------------------------------------------------
-- Customers register into the existing `users` table (role 'customer'),
-- so no new customer table is needed. This table supports throttling:
-- e.g. "max 5 failed attempts per email or IP in 15 minutes".
-- Only the email typed and the IP are stored, never the password typed.
-- No foreign key on email: attempts for unknown emails must be recorded too.

CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT          NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,        -- 45 chars fits IPv6
  attempted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_email_time (email, attempted_at),
  KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. ORDERS
-- ---------------------------------------------------------------------
-- Customer name and email are copied at checkout, so the order record
-- stays accurate even if the customer later edits their account.
--
-- chk_orders_address makes the database itself enforce:
--   collection -> every address column NULL and no delivery fee
--   delivery   -> address line 1, city and postcode present and not blank;
--                 address line 2 optional, but not blank if given
--
-- "Not blank" is written as  column REGEXP '[^[:space:]]'  which means
-- "contains at least one character that is not whitespace".
-- TRIM(column) <> '' was tested first, but MySQL's TRIM() removes only
-- spaces, so a value made of tabs or newlines slipped through.
-- The PHP code must store empty optional fields as NULL, not ''.

CREATE TABLE IF NOT EXISTS orders (
  id                 INT           NOT NULL AUTO_INCREMENT,
  order_number       CHAR(12)      NOT NULL,  -- random reference shown to the customer
  user_id            INT           NOT NULL,

  customer_name      VARCHAR(100)  NOT NULL,
  customer_email     VARCHAR(190)  NOT NULL,
  customer_phone     VARCHAR(30)   DEFAULT NULL,

  fulfilment_method  ENUM('collection', 'delivery') NOT NULL,
  address_line1      VARCHAR(150)  DEFAULT NULL,
  address_line2      VARCHAR(150)  DEFAULT NULL,
  city               VARCHAR(100)  DEFAULT NULL,
  postcode           VARCHAR(10)   DEFAULT NULL,

  status             ENUM('pending', 'confirmed', 'completed', 'cancelled')
                                   NOT NULL DEFAULT 'confirmed',

  subtotal           DECIMAL(10,2) NOT NULL,
  delivery_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount       DECIMAL(10,2) NOT NULL,

  created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),

  -- "My orders, newest first": WHERE user_id = ? ORDER BY created_at DESC.
  -- Also serves as the index the user_id foreign key needs.
  KEY idx_orders_user_created (user_id, created_at),

  -- Orders are kept for the record: a user with orders cannot be deleted.
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT chk_orders_customer CHECK (
    customer_name REGEXP '[^[:space:]]'
    AND customer_email REGEXP '[^[:space:]]'
    AND (customer_phone IS NULL OR customer_phone REGEXP '[^[:space:]]')
  ),

  CONSTRAINT chk_orders_amounts CHECK (
    subtotal >= 0
    AND delivery_fee >= 0
    AND total_amount = subtotal + delivery_fee
  ),

  CONSTRAINT chk_orders_address CHECK (
    (
      fulfilment_method = 'collection'
      AND address_line1 IS NULL
      AND address_line2 IS NULL
      AND city IS NULL
      AND postcode IS NULL
      AND delivery_fee = 0
    )
    OR
    (
      fulfilment_method = 'delivery'
      AND address_line1 IS NOT NULL AND address_line1 REGEXP '[^[:space:]]'
      AND city          IS NOT NULL AND city REGEXP '[^[:space:]]'
      AND postcode      IS NOT NULL AND postcode REGEXP '[^[:space:]]'
      AND (address_line2 IS NULL OR address_line2 REGEXP '[^[:space:]]')
    )
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. ORDER ITEMS
-- ---------------------------------------------------------------------
-- product_name and unit_price are snapshots taken from `products` at
-- checkout (never from the browser or session), so past orders do not
-- change when an admin edits prices or deletes a product.

CREATE TABLE IF NOT EXISTS order_items (
  id            INT           NOT NULL AUTO_INCREMENT,
  order_id      INT           NOT NULL,
  product_id    INT           DEFAULT NULL,   -- NULL once the product is deleted
  product_name  VARCHAR(120)  NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  quantity      INT UNSIGNED  NOT NULL,
  line_total    DECIMAL(10,2) NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_items_order_product (order_id, product_id),
  KEY idx_items_product (product_id),

  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id)
    REFERENCES products (id) ON DELETE SET NULL,

  CONSTRAINT chk_items_values CHECK (
    product_name REGEXP '[^[:space:]]'
    AND quantity > 0
    AND unit_price >= 0
    AND line_total = unit_price * quantity
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PART 2: DEPARTMENTS AND PRODUCTS
-- ---------------------------------------------------------------------

START TRANSACTION;

-- Ids 1 to 5 are the original demonstration categories, which only the
-- withdrawn products below use.
INSERT INTO categories (id, name, slug)
SELECT new_rows.id, new_rows.name, new_rows.slug
FROM (
    SELECT 1 AS id, 'Home & Living' AS name, 'home-living' AS slug
    UNION ALL SELECT 2, 'Technology', 'technology'
    UNION ALL SELECT 3, 'Stationery', 'stationery'
    UNION ALL SELECT 4, 'Accessories', 'accessories'
    UNION ALL SELECT 5, 'Audio & Sound', 'audio-sound'
) AS new_rows
WHERE NOT EXISTS (
    SELECT 1 FROM categories c WHERE c.slug = new_rows.slug
);

INSERT INTO categories (name, slug)
SELECT new_rows.name, new_rows.slug
FROM (
    SELECT 'Power Tools' AS name, 'power-tools' AS slug
    UNION ALL SELECT 'Hand Tools', 'hand-tools'
    UNION ALL SELECT 'Electrical', 'electrical'
    UNION ALL SELECT 'Saws & Cutting', 'saws-cutting'
    UNION ALL SELECT 'Home Improvement', 'home-improvement'
    UNION ALL SELECT 'Tool Storage', 'tool-storage'
) AS new_rows
WHERE NOT EXISTS (
    SELECT 1 FROM categories c WHERE c.slug = new_rows.slug
);

-- The original demonstration products: kept, but withdrawn from sale
-- (is_active = 0), so they appear only in the admin area.
INSERT INTO products (id, name, description, category_id, price, stock, featured, is_active, image_url)
SELECT new_rows.id, new_rows.name, new_rows.description,
    (SELECT c.id FROM categories c WHERE c.slug = new_rows.department),
    new_rows.price, new_rows.stock, new_rows.featured, 0, new_rows.image_url
FROM (
    SELECT 1 AS id, 'Ceramic Mug' AS name, 'Hand-glazed 350ml mug, dishwasher safe.' AS description,
        'home-living' AS department, 21.00 AS price, 30 AS stock, 1 AS featured,
        '/fixerupper-summative/uploads/products/ceramic-mug.png' AS image_url
    UNION ALL SELECT 2, 'Canvas Tote Bag', 'Heavy-duty cotton canvas, fits a laptop.',
        'home-living', 18.00, 8, 0, '/fixerupper-summative/uploads/products/canvas-tote-bag.png'
    UNION ALL SELECT 3, 'Desk Lamp', 'Adjustable LED lamp with warm/cool modes.',
        'home-living', 34.99, 5, 0, '/fixerupper-summative/uploads/products/desk-lamp-white.png'
    UNION ALL SELECT 4, 'Notebook Set', 'Pack of 3 dot-grid notebooks, A5 size.',
        'stationery', 9.75, 22, 0, '/fixerupper-summative/uploads/products/notebook-set.png'
    UNION ALL SELECT 5, 'Wireless Mouse', 'Compact 2.4GHz mouse with USB receiver.',
        'technology', 22.00, 5, 1, '/fixerupper-summative/uploads/products/wireless-mouse.png'
) AS new_rows
WHERE NOT EXISTS (
    SELECT 1 FROM products p WHERE p.id = new_rows.id OR p.name = new_rows.name
);

-- The hardware catalogue, on sale (is_active defaults to 1).
INSERT INTO products (name, description, category_id, price, stock, featured, image_url)
SELECT
    new_rows.name,
    new_rows.description,
    (SELECT c.id FROM categories c WHERE c.slug = new_rows.department),
    new_rows.price,
    new_rows.stock,
    new_rows.featured,
    new_rows.image_url
FROM (
    SELECT
        'Cordless Drill Driver' AS name,
        'Compact cordless drill driver with a battery pack and keyless chuck, for drilling and driving screws around the home and workshop.' AS description,
        'power-tools' AS department,
        79.00 AS price, 12 AS stock, 1 AS featured,
        '/fixerupper-summative/uploads/products/cordless-drill-driver.jpg' AS image_url
    UNION ALL SELECT
        'Screwdriver Set',
        'Set of seven screwdrivers with cushioned grips and a range of slotted and cross-head tips, for everyday repairs.',
        'hand-tools', 16.50, 25, 0,
        '/fixerupper-summative/uploads/products/screwdriver-set.jpg'
    UNION ALL SELECT
        'Adjustable Spanner Set',
        'Two adjustable spanners in different sizes, for gripping nuts and bolts without carrying a full spanner set.',
        'hand-tools', 24.00, 18, 0,
        '/fixerupper-summative/uploads/products/adjustable-spanner-set.jpg'
    UNION ALL SELECT
        'Digital Multimeter',
        'Handheld digital multimeter for measuring voltage, current and resistance on household and workshop projects.',
        'electrical', 19.99, 15, 0,
        '/fixerupper-summative/uploads/products/digital-multimeter.jpg'
    UNION ALL SELECT
        'LED Work Light',
        'Portable LED work light for lighting up loft spaces, garages and other dark work areas.',
        'electrical', 29.50, 20, 0,
        '/fixerupper-summative/uploads/products/led-work-light.jpg'
    UNION ALL SELECT
        'Circular Saw',
        'Mains-powered handheld circular saw with an adjustable base plate, for straight cuts in timber and sheet materials.',
        'saws-cutting', 89.00, 8, 0,
        '/fixerupper-summative/uploads/products/circular-saw.jpg'
    UNION ALL SELECT
        'Measuring Tape',
        'Retractable measuring tape with a belt clip, for measuring up rooms, timber and fittings.',
        'home-improvement', 7.25, 40, 0,
        '/fixerupper-summative/uploads/products/measuring-tape.jpg'
    UNION ALL SELECT
        'Tool Storage Box',
        'Steel cantilever toolbox with fold-out trays, for keeping hand tools and small parts organised. Tools not included.',
        'tool-storage', 34.00, 10, 0,
        '/fixerupper-summative/uploads/products/tool-storage-box.jpg'
) AS new_rows
WHERE NOT EXISTS (
    SELECT 1 FROM products p WHERE p.name = new_rows.name
);

COMMIT;

-- ---------------------------------------------------------------------
-- VERIFY (read-only)
-- ---------------------------------------------------------------------

SELECT DATABASE() AS installed_into;

SELECT
    (SELECT COUNT(*) FROM categories) AS categories,
    (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products_on_sale,
    (SELECT COUNT(*) FROM products WHERE is_active = 0) AS products_withdrawn;

-- ---------------------------------------------------------------------
-- CREATING AN ADMINISTRATOR ACCOUNT
-- ---------------------------------------------------------------------
-- Deliberately not included, so no password or hash is stored in this file.
-- 1. Create a hash of a password you choose (in Terminal, on MAMP for Mac):
--      /Applications/MAMP/bin/php/php8.3.30/bin/php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
-- 2. Run this in fixerupper_summative (phpMyAdmin: SQL tab), pasting the hash in:
--      INSERT INTO users (name, email, password, role)
--      VALUES ('Admin', 'admin@example.com', 'PASTE-THE-HASH-HERE', 'admin');
-- 3. Sign in at /fixerupper-summative/admin/login.php
-- Customers register on the website, so they need no setup.
