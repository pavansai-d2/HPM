-- HPM EXISTING DATABASE MIGRATION
-- BACK UP hpm_catalogue BEFORE RUNNING THIS SCRIPT.
-- This deliberately deletes every existing product record and resets its auto-increment counter.
USE hpm_catalogue;

CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 slug VARCHAR(150) NOT NULL UNIQUE,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_categories_active_order (is_active,sort_order)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories(name,slug,is_active,sort_order) VALUES
('Pallet Making Machines','pallet-making-machines',1,1),
('Wood Cutting, Processing & Finishing Machines','wood-cutting-processing-finishing-machines',1,2),
('Wood Treatment & Marking Equipment','wood-treatment-marking-equipment',1,3),
('Material Handling Equipment','material-handling-equipment',1,4);

-- Existing products.category is a text field. Add the new relational category field.
ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL AFTER id;

-- Existing records are intentionally removed as requested, so there is no category mapping step.
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE products;
SET FOREIGN_KEY_CHECKS=1;

ALTER TABLE products
 MODIFY category_id INT UNSIGNED NOT NULL,
 DROP COLUMN category,
 DROP INDEX idx_active_featured,
 ADD INDEX idx_products_category_active (category_id,is_active),
 ADD INDEX idx_products_active_featured (is_active,is_featured),
 ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
 ON UPDATE CASCADE ON DELETE RESTRICT;
