CREATE DATABASE IF NOT EXISTS hpm_catalogue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hpm_catalogue;

CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- IMPORTANT: Create the first administrator securely using admin/create-admin.php.
-- Do not use a default password in production.

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

CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(220) NOT NULL UNIQUE,
 short_description VARCHAR(500) NOT NULL DEFAULT '',
 description MEDIUMTEXT NULL,
 specifications TEXT NULL,
 image VARCHAR(255) NULL,
 alt_text VARCHAR(255) NULL,
 is_featured TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 seo_title VARCHAR(255) NULL,
 seo_description VARCHAR(320) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_products_category_active (category_id,is_active),
 INDEX idx_products_active_featured (is_active,is_featured),
 FULLTEXT KEY ft_products (name,short_description,description),
 CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
 ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enquiries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(254) NOT NULL,
 company VARCHAR(150) NULL,
 phone VARCHAR(30) NOT NULL,
 service VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 page_url VARCHAR(500) NULL,
 ip_hash CHAR(64) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_enquiries_created (created_at)
) ENGINE=InnoDB;


-- One-time, expiring tokens used by the administrator forgot-password flow.
CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_password_resets_admin (admin_id),
    INDEX idx_admin_password_resets_expiry (expires_at),
    CONSTRAINT fk_admin_password_resets_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
