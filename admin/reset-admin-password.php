<?php
declare(strict_types=1);

// Run from a terminal only. Never expose this reset utility through the browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

require __DIR__ . '/../includes/bootstrap.php';

$newPassword = $argv[1] ?? '';
if (strlen($newPassword) < 12) {
    fwrite(STDERR, "Usage: php admin/reset-admin-password.php \"A-Strong-Password-12+\"\n");
    fwrite(STDERR, "Choose a unique password of at least 12 characters.\n");
    exit(2);
}

try {
    $pdo = db();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    // Prefer the username used by the login form; fall back to the known HPM email
    // for older records that may not have a username column.
    $columns = $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('username', $columns, true)) {
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin' LIMIT 1");
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE email = ? LIMIT 1");
    }
    if (in_array('username', $columns, true)) {
        $stmt->execute([$hash]);
    } else {
        $stmt->execute([$hash, 'admin@haripalletmachinery.com']);
    }

    if ($stmt->rowCount() < 1) {
        // rowCount may be zero if the generated hash happens to be unchanged; verify account exists.
        if (in_array('username', $columns, true)) {
            $check = $pdo->query("SELECT id FROM admins WHERE username = 'admin' LIMIT 1");
        } else {
            $check = $pdo->query("SELECT id FROM admins WHERE email = 'admin@haripalletmachinery.com' LIMIT 1");
        }
        if (!$check->fetchColumn()) {
            throw new RuntimeException('Admin account not found.');
        }
    }

    echo "Admin password reset successfully.\n";
    echo "Login username: admin\n";
    echo "Use the password you supplied to this command.\n";
} catch (Throwable $exception) {
    error_log('[HPM Password Reset] ' . $exception->getMessage());
    fwrite(STDERR, "Reset failed: " . $exception->getMessage() . "\n");
    exit(1);
}
