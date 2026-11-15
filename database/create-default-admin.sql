-- HPM local starter admin account
-- Username: admin
-- Password: admin@123
-- The password is stored as a bcrypt hash, not plaintext.
USE hpm_catalogue;
INSERT INTO admins (username, name, email, password_hash)
VALUES ('admin', 'HPM Administrator', 'admin@localhost.invalid', '$2y$12$s9tPXhRkFx.jbmRxc5mmHOTzt8UChhDB.vsJ1UaeeyN7OYoIxxR8K')
ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash);
