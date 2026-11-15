<?php
declare(strict_types=1);

// Command-line diagnostic only. Delete after testing.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

require __DIR__ . '/../includes/bootstrap.php';

try {
    $email = 'admin@haripalletmachinery.com';

    $stmt = db()->prepare(
        'SELECT password_hash FROM admins WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        fwrite(STDERR, "Admin account not found.\n");
        exit(1);
    }

    echo "Enter the password to verify: ";
    $password = rtrim(fgets(STDIN), "\r\n");

    if (password_verify($password, $admin['password_hash'])) {
        echo "SUCCESS: The password matches the stored hash.\n";

        // Check whether the stored hash should be upgraded.
        if (password_needs_rehash(
            $admin['password_hash'],
            PASSWORD_DEFAULT
        )) {
            echo "Note: The password hash should be upgraded.\n";
        }
    } else {
        echo "FAILED: The password does not match the stored hash.\n";
    }

} catch (Throwable $exception) {
    error_log('[HPM Password Verify] ' . $exception->getMessage());
    fwrite(STDERR, "Verification failed. Check the PHP error log.\n");
    exit(1);
}
