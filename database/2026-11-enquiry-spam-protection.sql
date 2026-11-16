-- OPTIONAL manual migration. The site applies this automatically the first time
-- contact-submit.php or admin/enquiries.php runs (your live database already has it).
-- Run in phpMyAdmin only on a database that lacks these columns/table.
-- "Duplicate column" errors mean it already exists and can be ignored.

ALTER TABLE enquiries ADD COLUMN is_spam TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE enquiries ADD COLUMN spam_reason VARCHAR(255) NULL;
ALTER TABLE enquiries ADD COLUMN spam_marked_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE enquiries ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'New';

CREATE TABLE IF NOT EXISTS enquiry_attempts (
  ip_hash CHAR(64) NOT NULL,
  attempted_at INT UNSIGNED NOT NULL
);
