<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
admin_required();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'Please complete all password fields.';
    } elseif (strlen($new) < 12) {
        $error = 'Your new password must contain at least 12 characters.';
    } elseif (!hash_equals($new, $confirm)) {
        $error = 'The new password and confirmation do not match.';
    } elseif (hash_equals($current, $new)) {
        $error = 'Choose a new password that is different from your current password.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['admin_id']]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($current, (string) $admin['password_hash'])) {
                $error = 'Your current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                if ($hash === false) {
                    throw new RuntimeException('Password hashing failed.');
                }

                $update = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
                $update->execute([$hash, (int) $_SESSION['admin_id']]);

                // Invalidate all previously issued password-reset links for this account.
                try {
                    $delete = $pdo->prepare('DELETE FROM admin_password_resets WHERE admin_id = ?');
                    $delete->execute([(int) $_SESSION['admin_id']]);
                } catch (PDOException $ignored) {
                    // Existing installations may not have run the reset-token migration yet.
                }

                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $success = 'Your password has been changed successfully.';
            }
        } catch (Throwable $exception) {
            error_log('[HPM Change Password] ' . $exception->getMessage());
            $error = 'Unable to update the password. Please check the server error log.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password | HPM Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="login.css">
</head>
<body class="hpm-admin-login-page">
<main class="hpm-login-shell">
    <section class="hpm-login-card hpm-password-card">
        <div class="hpm-login-brand">
            <a class="hpm-login-logo-link" href="index.php" aria-label="HPM Admin dashboard">
                <img decoding="async" class="hpm-login-logo" src="../assets/images/hpm-logo.svg" alt="HPM logo">
            </a>
            <p class="hpm-login-brand-name">HARI PALLET MACHINERY <span>ADMIN</span></p>
            <h1>Change Password</h1>
            <p class="hpm-login-subtitle">Update your administrator password securely.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="hpm-login-error" role="alert"><span class="hpm-login-error-icon">!</span><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="hpm-password-success" role="status"><?= e($success) ?></div>
        <?php endif; ?>

        <form class="hpm-login-form" method="post" action="change-password.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="hpm-login-field">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="hpm-login-field">
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required>
            </div>
            <div class="hpm-login-field">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required>
            </div>
            <button class="hpm-login-submit" type="submit">Update Password</button>
        </form>
        <div class="hpm-login-card-footer">
            <a href="index.php">← Back to dashboard</a>
            <span>Minimum 12 characters</span>
        </div>
    </section>
</main>
</body>
</html>
