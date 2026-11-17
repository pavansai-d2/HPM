-- =====================================================================
-- HPM  -  Hostinger database UPGRADE  (safe to run more than once)
-- Database: u271831330_db_40FqVfVZ
--
-- HOW TO RUN: hPanel > Databases > phpMyAdmin > select your database
--             > "SQL" tab > paste this whole file > Go.
--
-- WHY: the database export you sent was missing columns/tables that the
-- current website code uses:
--   products.before_image, products.after_image, products.video_url
--       -> product popup (api/product.php) and Admin > Add/Edit product
--   enquiries.is_spam / spam_reason / spam_marked_at / status
--       -> admin dashboard counters and the Enquiries page
--   enquiry_attempts            -> contact-form rate limit
--   admin_password_resets       -> "Forgot password" page
--
-- Nothing is deleted. Existing rows are kept. Every statement uses
-- IF NOT EXISTS, so running it twice does no harm (MariaDB 10.x / 11.x).
-- =====================================================================

-- 1) Product media columns -------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `before_image` VARCHAR(255)  NULL AFTER `image`,
  ADD COLUMN IF NOT EXISTS `after_image`  VARCHAR(255)  NULL AFTER `before_image`,
  ADD COLUMN IF NOT EXISTS `video_url`    VARCHAR(1000) NULL AFTER `after_image`;

-- 2) Extra product photos: speed up the gallery lookup ----------------
ALTER TABLE `product_images`
  ADD INDEX IF NOT EXISTS `idx_product_images_product_sort` (`product_id`, `sort_order`);

-- 3) Enquiry spam protection ------------------------------------------
ALTER TABLE `enquiries`
  ADD COLUMN IF NOT EXISTS `is_spam`         TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `spam_reason`     VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS `spam_marked_at`  TIMESTAMP    NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `status`          VARCHAR(20)  NOT NULL DEFAULT 'New';

CREATE TABLE IF NOT EXISTS `enquiry_attempts` (
  `ip_hash`      CHAR(64)     NOT NULL,
  `attempted_at` INT UNSIGNED NOT NULL,
  KEY `idx_enquiry_attempts_ip_time` (`ip_hash`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Admin "Forgot password" tokens -----------------------------------
CREATE TABLE IF NOT EXISTS `admin_password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED    NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expires_at` DATETIME        NOT NULL,
  `used_at`    DATETIME        NULL DEFAULT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_admin_password_resets_admin`  (`admin_id`),
  KEY `idx_admin_password_resets_expiry` (`expires_at`),
  CONSTRAINT `fk_admin_password_resets_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) OPTIONAL SECURITY STEP (recommended) ------------------------------
-- Your `admins` table stores the admin password in clear text in
-- `password_plain`. The login page checks that column FIRST, so anyone who
-- can read the database (or a backup/dump like the one you sent) can read
-- the admin password. After you have logged in once, remove it:
--
--   UPDATE `admins` SET `password_plain` = NULL;
--
-- (Login then uses the secure bcrypt `password_hash`. Change the password
--  afterwards from Admin > Change password.)

-- 6) Check: you should see the new columns listed ----------------------
SHOW COLUMNS FROM `products`;
