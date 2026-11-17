<?php

// HPM Admin Login
require __DIR__ . '/../includes/bootstrap.php';

// Redirect already logged-in administrators.
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check();

    $login = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {

        $pdo = db();

        // ==========================================
        // CHECK ADMIN TABLE COLUMNS
        // ==========================================

        $columns = $pdo->query(
            'SHOW COLUMNS FROM admins'
        )->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'Field');

        $hasUsername = in_array('username', $columnNames, true);
        $hasPasswordPlain = in_array('password_plain', $columnNames, true);

        // Build SELECT dynamically.
        $selectColumns = ['id'];

        if ($hasUsername) {
            $selectColumns[] = 'username';
        }

        $selectColumns[] = 'name';
        $selectColumns[] = 'email';
        $selectColumns[] = 'password_hash';

        if ($hasPasswordPlain) {
            $selectColumns[] = 'password_plain';
        }

        $selectSQL = implode(', ', $selectColumns);

        // ==========================================
        // FIND ADMIN ACCOUNT
        // ==========================================

        if ($hasUsername) {

            $stmt = $pdo->prepare(
                "SELECT {$selectSQL}
                 FROM admins
                 WHERE username = :username
                    OR email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                'username' => $login,
                'email' => strtolower($login),
            ]);

        } else {

            // Backward compatibility for email-only accounts.
            $email = strtolower(
                $login === 'admin'
                    ? 'admin@haripalletmachinery.com'
                    : $login
            );

            $stmt = $pdo->prepare(
                "SELECT {$selectSQL}
                 FROM admins
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
        }

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // ==========================================
        // PASSWORD VERIFICATION
        // ==========================================

        $passwordMatches = false;
        $needsPasswordUpgrade = false;

        $storedHash = (string) ($admin['password_hash'] ?? '');

        $plainPassword = $hasPasswordPlain
            ? (string) ($admin['password_plain'] ?? '')
            : '';

        if ($admin && $password !== '') {

            /*
             * PRIORITY 1:
             * Check password_plain first.
             *
             * When you change password_plain in phpMyAdmin,
             * the new password takes priority over the old hash.
             */

            if ($plainPassword !== '') {

                if (hash_equals($plainPassword, $password)) {

                    $passwordMatches = true;
                    $needsPasswordUpgrade = true;
                }

            } else {

                /*
                 * PRIORITY 2:
                 * If password_plain is empty,
                 * use the existing secure password hash.
                 */

                if (
                    $storedHash !== '' &&
                    password_verify($password, $storedHash)
                ) {

                    $passwordMatches = true;

                    if (password_needs_rehash(
                        $storedHash,
                        PASSWORD_DEFAULT
                    )) {
                        $needsPasswordUpgrade = true;
                    }

                } elseif (
                    $storedHash !== '' &&
                    hash_equals($storedHash, $password)
                ) {

                    // Backward compatibility with a directly
                    // entered password_hash value.
                    $passwordMatches = true;
                    $needsPasswordUpgrade = true;
                }
            }
        }

        // ==========================================
        // SUCCESSFUL LOGIN
        // ==========================================

        if ($passwordMatches) {

            /*
             * Synchronize password_hash whenever:
             * - password_plain was used, or
             * - an old hash needs upgrading.
             */

            if ($needsPasswordUpgrade) {

                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare(
                    'UPDATE admins
                     SET password_hash = ?
                     WHERE id = ?'
                );

                $update->execute([
                    $newHash,
                    $admin['id']
                ]);
            }

            // Regenerate session ID.
            session_regenerate_id(true);

            // Set admin session.
            $_SESSION['admin_id'] = (int) $admin['id'];

            $_SESSION['admin_name'] =
                $admin['name'] ?? 'Administrator';

            $_SESSION['admin_username'] = $hasUsername
                ? ($admin['username'] ?? 'admin')
                : ($admin['email'] ?? 'admin');

            // Generate new CSRF token.
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );

            // Redirect to dashboard.
            header('Location: index.php');
            exit;

        } else {

            $error = 'Username or password is incorrect.';
        }

    } catch (Throwable $exception) {

        error_log(
            '[HPM Admin Login] ' . get_class($exception) . ': ' . $exception->getMessage()
        );

        http_response_code(500);

        $error = 'Database error. Check the PHP error log.';

        // TEMPORARY DIAGNOSTICS: set 'debug' => true in config/config.php to see
        // the exact reason here (wrong password, wrong host, missing column...).
        // Set it back to false once login works.
        $debugConfigFile = __DIR__ . '/../config/config.php';
        $debugConfig = is_file($debugConfigFile) ? require $debugConfigFile : [];
        if (is_array($debugConfig) && !empty($debugConfig['debug'])) {
            $error = 'Database error: ' . get_class($exception) . ' - ' . $exception->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>HPM Admin Login | Hari Pallet Machinery</title>

    <meta name="robots" content="noindex, nofollow">

    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="login.css">

</head>

<body class="hpm-admin-login-page">

    <main class="hpm-login-shell">

        <section
            class="hpm-login-card"
            aria-labelledby="hpm-login-title"
        >

            <!-- BRAND SECTION -->
            <div class="hpm-login-brand">

                <a
                    class="hpm-login-logo-link"
                    href="../index.html"
                    aria-label="Hari Pallet Machinery homepage"
                >

                    <img decoding="async"
                        class="hpm-login-logo"
                        src="../assets/images/hpm-logo.svg"
                        alt="Hari Pallet Machinery logo"
                    >

                </a>

                <p class="hpm-login-brand-name">
                    <span>ADMIN</span>
                </p>

                <h1 id="hpm-login-title">
                    Welcome Back
                </h1>

                <p class="hpm-login-subtitle">
                    Sign in to manage your product catalogue.
                </p>

            </div>

            <!-- ERROR MESSAGE -->
            <?php if ($error !== ''): ?>

                <div class="hpm-login-error" role="alert">

                    <span
                        class="hpm-login-error-icon"
                        aria-hidden="true"
                    >!</span>

                    <span><?= e($error) ?></span>

                </div>

            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form
                class="hpm-login-form"
                method="post"
                action="login.php"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <!-- USERNAME -->
                <div class="hpm-login-field">

                    <label for="username">
                        Username
                    </label>

                    <input
                        id="username"
                        type="text"
                        name="username"
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                    >

                </div>

                <!-- PASSWORD -->
                <div class="hpm-login-field">

                    <label for="password">
                        Password
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                </div>

                <!-- SIGN IN -->
                <button
                    class="hpm-login-submit"
                    type="submit"
                >

                    <span>Sign In</span>

                </button>

            </form>

            <!-- FOOTER -->
            <div class="hpm-login-card-footer">

                <a href="../index.html">
                    ← Back to website
                </a>

            </div>

        </section>

        <footer class="admin-footer">
            HPM Admin || Hari Pallet Machinery
        </footer>

        <footer>
            Design and Development by

            <a
                href="https://www.digital-services.pavanasutra.in/"
                style="color: #f45108;"
                target="_blank"
                rel="noopener noreferrer"
            >
                <b>Pavanasutra</b>
            </a>
        </footer>

    </main>

</body>

</html>
