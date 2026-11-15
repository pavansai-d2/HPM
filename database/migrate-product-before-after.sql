-- Run once in phpMyAdmin against hpm_catalogue
ALTER TABLE products
  ADD COLUMN before_image VARCHAR(255) NULL AFTER image,
  ADD COLUMN after_image VARCHAR(255) NULL AFTER before_image;
