<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: change-password.php');
    exit;
}

$message = 'If that administrator email exists, a password reset link will be sent shortly.';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid administrator email address.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id, name, email FROM admins WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

                $pdo->prepare('DELETE FROM admin_password_resets WHERE admin_id = ? OR expires_at < NOW()')
                    ->execute([(int) $admin['id']]);

                $insert = $pdo->prepare(
                    'INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, ?)'
                );
                $insert->execute([(int) $admin['id'], $tokenHash, $expires]);

                $config = require __DIR__ . '/../config/config.php';
                $baseUrl = rtrim((string) ($config['site_url'] ?? ''), '/');
                if ($baseUrl === '') {
                    throw new RuntimeException('Set site_url in config/config.php before enabling password reset.');
                }

                $resetUrl = $baseUrl . '/admin/reset-password.php?token=' . urlencode($token);
                $from = (string) ($config['mail_from'] ?? '');
                if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Set a valid mail_from address in config/config.php.');
                }

                $subject = 'HPM Admin Password Reset';
                $body = "Hello " . (string) $admin['name'] . ",\n\n"
                    . "A password reset was requested for your HPM administrator account.\n\n"
                    . "Use this one-time link within 30 minutes:\n" . $resetUrl . "\n\n"
                    . "If you did not request this, you can ignore this email.\n";
                $headers = [
                    'From: Hari Pallet Machinery <' . $from . '>',
                    'Content-Type: text/plain; charset=UTF-8',
                    'X-Mailer: PHP/' . PHP_VERSION,
                ];

                if (!mail((string) $admin['email'], $subject, $body, implode("\r\n", $headers))) {
                    throw new RuntimeException('The server could not send the password reset email. Configure outgoing email on the hosting account.');
                }
            }
        } catch (Throwable $exception) {
            error_log('[HPM Forgot Password] ' . $exception->getMessage());
            // Do not disclose whether an account exists.
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | HPM Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="login.css">
</head>
<body class="hpm-admin-login-page">
<main class="hpm-login-shell">
    <section class="hpm-login-card">
        <div class="hpm-login-brand">
            <a class="hpm-login-logo-link" href="../index.html"><img decoding="async" class="hpm-login-logo" src="../assets/images/hpm-logo.svg" alt="HPM logo"></a>
            <p class="hpm-login-brand-name">HARI PALLET MACHINERY <span>ADMIN</span></p>
            <h1>Forgot Password?</h1>
            <p class="hpm-login-subtitle">Enter your administrator email address and we will send a secure reset link.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="hpm-login-error" role="alert"><?= e($error) ?></div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="hpm-password-success" role="status"><?= e($message) ?></div>
        <?php endif; ?>

        <form class="hpm-login-form" method="post" action="forgot-password.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="hpm-login-field">
                <label for="email">Administrator email</label>
                <input id="email" name="email" type="email" autocomplete="email" required placeholder="Enter your admin email">
            </div>
            <button class="hpm-login-submit" type="submit">Send Reset Link</button>
        </form>
        <div class="hpm-login-card-footer">
            <a href="login.php">← Back to sign in</a>
            <span>Link expires in 30 minutes</span>
        </div>
    </section>
</main>
</body>
</html>
