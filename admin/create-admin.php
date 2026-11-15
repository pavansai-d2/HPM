<?php
declare(strict_types=1);

// Run from a terminal only. Do not expose this installer through the browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this installer from the command line only.');
}

require __DIR__ . '/../includes/bootstrap.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php admin/create-admin.php \"Admin Name\" admin@example.com \"StrongPassword12+\" [username]\n");
    exit(2);
}

$name = trim((string) $argv[1]);
$email = filter_var((string) $argv[2], FILTER_VALIDATE_EMAIL);
$password = (string) $argv[3];
$username = isset($argv[4]) ? trim((string) $argv[4]) : strtolower((string) strtok((string) $argv[2], '@'));

if ($name === '' || !$email || $username === '' || strlen($password) < 12) {
    fwrite(STDERR, "Provide a name, valid email, username, and password of at least 12 characters.\n");
    exit(2);
}

try {
    $pdo = db();
    $columns = $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    if (in_array('username', $columns, true)) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, name, email, password_hash) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $name, $email, $hash]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hash]);
    }

    echo "Admin account created successfully.\nUsername: {$username}\nDelete this installer file from the deployed server after use.\n";
} catch (Throwable $exception) {
    error_log('[HPM Create Admin] ' . $exception->getMessage());
    fwrite(STDERR, "Admin creation failed: " . $exception->getMessage() . "\n");
    exit(1);
}
