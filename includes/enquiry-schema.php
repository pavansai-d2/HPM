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
