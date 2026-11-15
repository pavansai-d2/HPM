-- Run ONLY if you already imported an older hpm_catalogue database
-- whose admins table has email but no username column.
USE hpm_catalogue;
ALTER TABLE admins ADD COLUMN username VARCHAR(80) NULL AFTER id;
UPDATE admins SET username = LOWER(SUBSTRING_INDEX(email, '@', 1)) WHERE username IS NULL;
UPDATE admins SET username = 'admin' WHERE email = 'admin@haripalletmachinery.com';
ALTER TABLE admins MODIFY username VARCHAR(80) NOT NULL;
CREATE UNIQUE INDEX uq_admins_username ON admins (username);
-- Then run database/create-default-admin.sql to set the starter password.
