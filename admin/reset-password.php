<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
$error = '';
$success = false;
$adminId = 0;

if ($tokenValid) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT admin_id FROM admin_password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $adminId = (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
        error_log('[HPM Reset Password] ' . $exception->getMessage());
        $error = 'Password reset is not configured yet. Please contact the website administrator.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid && $adminId > 0) {
    csrf_check();

    $password = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 12) {
        $error = 'Your new password must contain at least 12 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'The password confirmation does not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // Recheck and lock the token to prevent simultaneous reuse.
            $lock = $pdo->prepare(
                'SELECT admin_id FROM admin_password_resets
                 WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
                 FOR UPDATE'
            );
            $lock->execute([hash('sha256', $token)]);
            $lockedAdminId = (int) ($lock->fetchColumn() ?: 0);

            if ($lockedAdminId !== $adminId) {
                throw new RuntimeException('This reset link has expired or has already been used.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Password hashing failed.');
            }

            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([$hash, $adminId]);
            $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE token_hash = ?')
                ->execute([hash('sha256', $token)]);
            $pdo->prepare('DELETE FROM admin_password_resets WHERE admin_id = ? AND token_hash <> ?')
                ->execute([$adminId, hash('sha256', $token)]);

            $pdo->commit();
            $success = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[HPM Reset Password] ' . $exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to reset your password. Please try again.';
        }
    }
}

if (!$tokenValid || $adminId < 1) {
    if ($error === '') {
        $error = 'This password reset link is invalid, expired or has already been used. Request a new link.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | HPM Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="login.css">
</head>
<body class="hpm-admin-login-page">
<main class="hpm-login-shell">
    <section class="hpm-login-card">
        <div class="hpm-login-brand">
            <a class="hpm-login-logo-link" href="../index.html"><img decoding="async" class="hpm-login-logo" src="../assets/images/hpm-logo.svg" alt="HPM logo"></a>
            <p class="hpm-login-brand-name">HARI PALLET MACHINERY <span>ADMIN</span></p>
            <h1>Set New Password</h1>
            <p class="hpm-login-subtitle">Choose a new password for your administrator account.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="hpm-login-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="hpm-password-success" role="status">Your password has been reset successfully. You can now sign in with your new password.</div>
            <div class="hpm-login-card-footer"><a href="login.php">← Go to sign in</a></div>
        <?php elseif ($adminId > 0): ?>
            <form class="hpm-login-form" method="post" action="reset-password.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="hpm-login-field">
                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required>
                </div>
                <div class="hpm-login-field">
                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required>
                </div>
                <button class="hpm-login-submit" type="submit">Reset Password</button>
            </form>
            <div class="hpm-login-card-footer"><a href="login.php">← Back to sign in</a><span>One-time secure link</span></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
