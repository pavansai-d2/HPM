<?php
declare(strict_types=1);

/*
 * Public HPM contact form handler.
 * Saves the enquiry to the existing hpm_catalogue.enquiries table.
 * Sends an email notification when PHPMailer and SMTP are configured.
 */

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

/* Honeypot: bots should leave this field empty. */
if (!empty($_POST['_honey'])) {
    header('Location: contact.html?contact=success');
    exit;
}

function contact_redirect(string $status): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $fallback = 'contact.html';

    // Redirect only to a same-site relative path; do not trust arbitrary Referer URLs.
    $path = parse_url($referer, PHP_URL_PATH);
    $target = is_string($path) && $path !== '' ? $path : $fallback;
    $query = parse_url($referer, PHP_URL_QUERY);
    $existing = is_string($query) ? $query : '';

    $url = $target . ($existing !== '' ? '?' . $existing . '&' : '?')
         . 'contact=' . rawurlencode($status);



    header('Location: ' . $url);
    exit;
}

/*
 * Google reCAPTCHA v2 server-side verification.
 * Never accept a form based only on the browser-side checkbox.
 */
$configFile = __DIR__ . '/config/config.php';
$siteConfig = is_file($configFile) ? require $configFile : [];
$recaptchaSecret = is_array($siteConfig)
    ? trim((string) ($siteConfig['recaptcha_secret_key'] ?? ''))
    : '';

if (
    $recaptchaSecret === '' ||
    $recaptchaSecret === 'YOUR_RECAPTCHA_V2_SECRET_KEY'
) {
    error_log('HPM CAPTCHA is not configured: add the reCAPTCHA v2 secret key to config/config.php.');
    contact_redirect('captcha_config');
}

$captchaToken = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
if ($captchaToken === '') {
    contact_redirect('captcha');
}

$verifyPayload = http_build_query([
    'secret' => $recaptchaSecret,
    'response' => $captchaToken,
    'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
]);

$verifyResponse = false;
if (function_exists('curl_init')) {
    $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $verifyPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $verifyResponse = curl_exec($curl);
    if ($verifyResponse === false) {
        error_log('HPM CAPTCHA verification request failed: ' . curl_error($curl));
    }
    curl_close($curl);
} elseif (ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\\r\\n",
            'content' => $verifyPayload,
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $verifyResponse = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify',
        false,
        $context
    );
}

$captchaResult = is_string($verifyResponse)
    ? json_decode($verifyResponse, true)
    : null;

if (!is_array($captchaResult) || empty($captchaResult['success'])) {
    error_log('HPM CAPTCHA verification rejected an enquiry.');
    contact_redirect('captcha');
}

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if (
    $name === '' || mb_strlen($name) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254 ||
    $phone === '' || mb_strlen($phone) > 30 ||
    $service === '' || mb_strlen($service) > 190 ||
    $message === '' || mb_strlen($message) > 5000 ||
    mb_strlen($company) > 150
) {
    contact_redirect('invalid');
}

/* Keep page_url useful, but store only a URL supplied by this site. */
$pageUrl = '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer !== '') {
    $host = (string) parse_url($referer, PHP_URL_HOST);
    $serverHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && strcasecmp($host, $serverHost) === 0) {
        $pageUrl = mb_substr($referer, 0, 500);
    }
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ipHash = $ip !== '' ? hash('sha256', $ip) : null;

try {
    $pdo = db();

    // Exact column mapping from the supplied hpm_catalogue SQL schema.
    $insert = $pdo->prepare("
        INSERT INTO enquiries
            (name, email, company, phone, service, message, page_url, ip_hash)
        VALUES
            (:name, :email, :company, :phone, :service, :message, :page_url, :ip_hash)
    ");

    $insert->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':company'  => $company !== '' ? $company : null,
        ':phone'    => $phone,
        ':service'  => $service,
        ':message'  => $message,
        ':page_url' => $pageUrl !== '' ? $pageUrl : null,
        ':ip_hash'  => $ipHash,
    ]);

    $enquiryId = (int) $pdo->lastInsertId();

} catch (Throwable $exception) {
    error_log('HPM contact enquiry database error: ' . $exception->getMessage());
    contact_redirect('error');
}

/*
 * Email is attempted AFTER the database insert.
 * A mail problem never deletes or rolls back the saved enquiry.
 */
$mailSent = false;

try {
    $autoload = __DIR__ . '/vendor/autoload.php';
    $configFile = __DIR__ . '/includes/mail-config.php';

    if (is_file($autoload) && is_file($configFile)) {
        require_once $autoload;

        $mailConfig = require $configFile;

        if (
            class_exists('PHPMailer\\PHPMailer\\PHPMailer') &&
            !empty($mailConfig['host']) &&
            $mailConfig['host'] !== 'YOUR_SMTP_HOST' &&
            !empty($mailConfig['username']) &&
            !empty($mailConfig['password']) &&
            $mailConfig['password'] !== 'YOUR_EMAIL_ACCOUNT_PASSWORD'
        ) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $mailConfig['username'];
            $mail->Password = (string) $mailConfig['password'];
            $mail->Port = (int) $mailConfig['port'];
            $mail->CharSet = 'UTF-8';

            if (($mailConfig['encryption'] ?? 'tls') === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom(
                (string) $mailConfig['from_email'],
                (string) $mailConfig['from_name']
            );
            $mail->addAddress(
                (string) $mailConfig['to_email'],
                (string) $mailConfig['to_name']
            );
            $mail->addReplyTo($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'New HPM Website Enquiry #' . $enquiryId;

            $safe = static fn(string $value): string =>
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $mail->Body = '
                <h2>New website enquiry</h2>
                <p><strong>Enquiry ID:</strong> ' . $enquiryId . '</p>
                <p><strong>Name:</strong> ' . $safe($name) . '</p>
                <p><strong>Email:</strong> ' . $safe($email) . '</p>
                <p><strong>Company:</strong> ' . $safe($company ?: 'Not provided') . '</p>
                <p><strong>Phone:</strong> ' . $safe($phone) . '</p>
                <p><strong>Product / Service:</strong> ' . $safe($service) . '</p>
                <p><strong>Message:</strong><br>' . nl2br($safe($message)) . '</p>
                <p><strong>Source page:</strong> ' . $safe($pageUrl ?: 'Not available') . '</p>
            ';

            $mail->AltBody =
                "New HPM website enquiry #{$enquiryId}\n" .
                "Name: {$name}\nEmail: {$email}\nCompany: {$company}\n" .
                "Phone: {$phone}\nService: {$service}\n\nMessage:\n{$message}";

            $mail->send();
            $mailSent = true;
        }
    }
} catch (Throwable $exception) {
    error_log('HPM enquiry email error: ' . $exception->getMessage());
}

contact_redirect($mailSent ? 'success' : 'saved');
