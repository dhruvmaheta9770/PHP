-- ============================================================
--  Bill Generator — Database Setup Script
--  Run this once in phpMyAdmin or MySQL CLI before using the app
-- ============================================================

-- 1. Create the database
CREATE DATABASE IF NOT EXISTS bill_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 2. Select it
USE bill_db;

-- 3. Bills table (one row per invoice)
CREATE TABLE IF NOT EXISTS bills (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    bill_no     VARCHAR(20)   NOT NULL,          -- e.g. INV-3F9A2C
    cust_name   VARCHAR(150)  NOT NULL,
    mobile      VARCHAR(20)   NOT NULL,
    grand_total DECIMAL(12,2) NOT NULL,
    bill_date   DATE          NOT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bill items table (multiple rows per invoice)
CREATE TABLE IF NOT EXISTS bill_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    bill_id     INT           NOT NULL,           -- links to bills.id
    item_name   VARCHAR(200)  NOT NULL,
    qty         DECIMAL(10,2) NOT NULL,
    price       DECIMAL(12,2) NOT NULL,
    subtotal    DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Optional: create a dedicated MySQL user for the app
--  (recommended over using root in production)
-- ============================================================
-- CREATE USER 'bill_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON bill_db.* TO 'bill_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================================
--  Done! Update your PHP config block to match:
--
--    define('DB_HOST', 'localhost');
--    define('DB_USER', 'root');          -- or 'bill_user'
--    define('DB_PASS', '');             -- your password
--    define('DB_NAME', 'bill_db');
-- ============================================================
