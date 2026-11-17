<?php
declare(strict_types=1);

/*
 * HPM enquiry spam-protection schema helper.
 *
 * Adds the columns and the small rate-limit table the new contact form needs.
 * It runs automatically (once the columns exist it costs one tiny query), so
 * the live site keeps working even if database/2026-11-enquiry-spam-protection.sql
 * has not been imported yet. Importing that SQL file by hand does exactly the same.
 *
 * Existing enquiries are never changed or removed: new columns default to
 * "not spam" and status "New".
 */

function hpm_enquiries_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // Fast path: columns already exist.
    try {
        $pdo->query('SELECT is_spam, spam_reason, status, spam_marked_at FROM enquiries LIMIT 0');
    } catch (Throwable $missing) {
        $alterations = [
            "ALTER TABLE enquiries ADD COLUMN is_spam TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE enquiries ADD COLUMN spam_reason VARCHAR(255) NULL",
            "ALTER TABLE enquiries ADD COLUMN spam_marked_at TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE enquiries ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'New'",
        ];
        foreach ($alterations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $exists) {
                // "Duplicate column" means a previous partial run already added it.
            }
        }
    }

    // One row per form attempt, used only for rate limiting. Pruned automatically.
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS enquiry_attempts (
                ip_hash CHAR(64) NOT NULL,
                attempted_at INT UNSIGNED NOT NULL
            )'
        );
    } catch (Throwable $exception) {
        error_log('[HPM Enquiry schema] Could not create enquiry_attempts: ' . $exception->getMessage());
    }
}


/*
 * HPM catalogue schema helper.
 *
 * Makes sure the product media columns and the password-reset table exist, so a
 * database that was imported from an older export keeps working instead of
 * failing with "Unknown column 'video_url'". Nothing is ever deleted. When the
 * columns already exist this costs a single tiny query.
 * Same result as running database/hpm_hostinger_upgrade.sql by hand.
 */
function hpm_catalogue_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $have = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
        $wanted = [
            'before_image' => "ALTER TABLE products ADD COLUMN before_image VARCHAR(255) NULL AFTER image",
            'after_image'  => "ALTER TABLE products ADD COLUMN after_image VARCHAR(255) NULL AFTER before_image",
            'video_url'    => "ALTER TABLE products ADD COLUMN video_url VARCHAR(1000) NULL AFTER after_image",
        ];
        foreach ($wanted as $column => $sql) {
            if (!in_array($column, $have, true)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $exists) {
                    error_log('[HPM Schema] products.' . $column . ': ' . $exists->getMessage());
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('[HPM Schema] products check failed: ' . $exception->getMessage());
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_password_resets (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $exception) {
        error_log('[HPM Schema] admin_password_resets: ' . $exception->getMessage());
    }
}
