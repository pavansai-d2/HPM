<?php
declare(strict_types=1);

// Do not add spaces, HTML, or output before this PHP opening tag.

// --------------------------------------------------
// 1. SESSION CONFIGURATION
// --------------------------------------------------
if (session_status() === PHP_SESSION_DISABLED) {
    error_log('[HPM Session] PHP sessions are disabled.');
    http_response_code(500);
    exit('Session service is unavailable. Check the PHP configuration.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('HPM_ADMIN_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// --------------------------------------------------
// 2. DATABASE CONFIGURATION
// --------------------------------------------------
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Read connection settings from the single local configuration file.
    // For XAMPP defaults, config/config.php should use localhost, hpm_catalogue,
    // root, and an empty password unless you set a MySQL root password.
    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile) || !is_readable($configFile)) {
        throw new RuntimeException('Database configuration is missing or unreadable.');
    }

    $config = require $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $host = (string) ($config['db_host'] ?? 'localhost');
    $database = (string) ($config['db_name'] ?? 'hpm_catalogue');
    $username = (string) ($config['db_user'] ?? 'root');
    $password = (string) ($config['db_pass'] ?? '');
    $port = (int) ($config['db_port'] ?? 3306);

    if ($password === '' || $password === 'PASTE_YOUR_CURRENT_GODADDY_DATABASE_PASSWORD_HERE') {
        throw new RuntimeException('Live database password has not been configured.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    } catch (PDOException $exception) {
        error_log('[HPM Database] ' . $exception->getMessage());
        throw $exception;
    }

    return $pdo;
}

// --------------------------------------------------
// 3. CSRF TOKEN GENERATION
// --------------------------------------------------
function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// --------------------------------------------------
// 4. CSRF TOKEN VALIDATION
// --------------------------------------------------
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $submitted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submitted) ||
        !is_string($stored) ||
        $submitted === '' ||
        $stored === '' ||
        !hash_equals($stored, $submitted)
    ) {
        http_response_code(403);
        exit(
            'Your session expired or the security token is invalid. ' .
            'Refresh the page and try again.'
        );
    }
}

// --------------------------------------------------
// 5. ADMIN AUTHENTICATION GUARD
// --------------------------------------------------
function admin_required(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// --------------------------------------------------
// 6. SAFE HTML OUTPUT
// --------------------------------------------------
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// --------------------------------------------------
// 7. WHATSAPP ENQUIRY URL
// --------------------------------------------------
function whatsapp_url(
    string $productName,
    string $reference = ''
): string {
    $config = require __DIR__ . '/../config/config.php';
    $phone = preg_replace('/\D+/', '', (string)($config['whatsapp_number'] ?? '971542188600'));
    if ($phone === '') { $phone = '971542188600'; }

    $message = 'Hello Hari Pallet Machinery, I would like to enquire about ' .
        $productName;

    if ($reference !== '') {
        $message .= ' (Reference: ' . $reference . ')';
    }

    return 'https://wa.me/' . $phone . '?text=' .
        rawurlencode($message);
}