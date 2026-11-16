<?php
declare(strict_types=1);

/*
 * Public HPM contact form handler.
 *
 * Protection order (cheapest checks first, nothing is saved or emailed until
 * every hard check has passed):
 *
 *   1. POST only, same-site Origin
 *   2. Honeypot field            -> bots get a fake "success", nothing is saved
 *   3. Rate limit per IP         -> too many attempts are refused
 *   4. Field validation          -> all fields checked on the server
 *   5. Google reCAPTCHA v2       -> token verified server-side, fails closed
 *   6. Spam filter               -> suspicious messages are saved as SPAM, no email
 *   7. Save to database, then send the email notification
 *
 * Result is returned to contact.html as ?contact=<status>, which shows the popup.
 */

// Visitors do not need a PHP session; skipping it makes the handler faster.
define('HPM_PUBLIC', true);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/enquiry-schema.php';
require __DIR__ . '/includes/spam-filter.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: contact.html');
    exit;
}

/* The form only lives on contact.html, so always return there. */
function contact_redirect(string $status): never
{
    header('Cache-Control: no-store');
    header('Location: contact.html?contact=' . rawurlencode($status) . '#contact-form-area');
    exit;
}

/* ---------------------------------------------------------------------------
 * 1. Same-site check. Browsers always send Origin on form POSTs; a mismatch
 *    means the form was posted from another website or script.
 * ------------------------------------------------------------------------ */
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && strcasecmp($origin, 'null') !== 0) {
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $serverHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    $strip = static fn(string $h): string => preg_replace('/^www\./', '', $h) ?? $h;
    if ($originHost !== '' && $strip($originHost) !== $strip($serverHost)) {
        error_log('HPM contact form: rejected cross-site POST from ' . $origin);
        contact_redirect('invalid');
    }
}

/* ---------------------------------------------------------------------------
 * 2. Honeypot. Real visitors never see or fill this field.
 *    The bot is told "success" so it does not learn that it was detected.
 * ------------------------------------------------------------------------ */
if (trim((string) ($_POST['_honey'] ?? '')) !== '') {
    error_log('HPM contact form: honeypot triggered from ' . hpm_client_ip());
    contact_redirect('success');
}

/* Load site configuration (reCAPTCHA secret, optional limits). */
$configFile = __DIR__ . '/config/config.php';
$siteConfig = is_file($configFile) ? require $configFile : [];
if (!is_array($siteConfig)) {
    $siteConfig = [];
}

$ip      = hpm_client_ip();
$ipHash  = $ip !== '' ? hash('sha256', $ip) : null;
$now     = time();

try {
    $pdo = db();
    hpm_enquiries_ensure_schema($pdo);
} catch (Throwable $exception) {
    error_log('HPM contact enquiry database error: ' . $exception->getMessage());
    contact_redirect('error');
}

/* ---------------------------------------------------------------------------
 * 3. Rate limit: repeated submissions from one IP address.
 *    Defaults: 5 attempts per 10 minutes and 20 per 24 hours. Both can be
 *    changed in config/config.php (contact_limit_10min, contact_limit_day).
 * ------------------------------------------------------------------------ */
$limit10 = max(1, (int) ($siteConfig['contact_limit_10min'] ?? 5));
$limitDay = max($limit10, (int) ($siteConfig['contact_limit_day'] ?? 20));

if ($ipHash !== null) {
    try {
        $count = $pdo->prepare('SELECT COUNT(*) FROM enquiry_attempts WHERE ip_hash = ? AND attempted_at > ?');

        $count->execute([$ipHash, $now - 600]);
        $recent = (int) $count->fetchColumn();

        $count->execute([$ipHash, $now - 86400]);
        $daily = (int) $count->fetchColumn();

        if ($recent >= $limit10 || $daily >= $limitDay) {
            error_log('HPM contact form: rate limit reached for ' . $ip);
            contact_redirect('ratelimit');
        }

        $pdo->prepare('INSERT INTO enquiry_attempts (ip_hash, attempted_at) VALUES (?, ?)')
            ->execute([$ipHash, $now]);

        // Housekeeping: forget attempts older than two days (about 1 in 20 requests).
        if (random_int(1, 20) === 1) {
            $pdo->prepare('DELETE FROM enquiry_attempts WHERE attempted_at < ?')->execute([$now - 172800]);
        }
    } catch (Throwable $exception) {
        // Never block a genuine customer because the limiter table is unavailable.
        error_log('HPM contact rate limiter error: ' . $exception->getMessage());
    }
}

/* ---------------------------------------------------------------------------
 * 4. Server-side validation of every field.
 *    No rule here depends on language or country: international names,
 *    non-Latin scripts and any country code are accepted.
 * ------------------------------------------------------------------------ */
$field = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    // Remove control characters (keep tab/newline in the message later).
    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
};

$name    = $field('name');
$email   = $field('email');
$company = $field('company');
$phone   = $field('phone');
$service = $field('service');
$message = str_replace(["\r\n", "\r"], "\n", $field('message'));

$digits = preg_replace('/\D+/', '', $phone) ?? '';

$invalid =
    $name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254 ||
    mb_strlen($company) > 150 ||
    // International numbers: digits plus + ( ) - . and spaces, 6 to 20 digits.
    !preg_match('/^[+()\d][\d\s().\-+]*$/', $phone) || strlen($digits) < 6 || strlen($digits) > 20 || mb_strlen($phone) > 30 ||
    $service === '' || mb_strlen($service) > 190 ||
    $message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000 ||
    // Plain-text fields must not contain markup.
    preg_match('/[<>]/', $name . $company . $phone . $service) === 1;

if ($invalid) {
    contact_redirect('invalid');
}

/* ---------------------------------------------------------------------------
 * 5. Google reCAPTCHA v2 server-side verification. Fails closed: if the secret
 *    key is missing, Google cannot be reached or the answer is not "success",
 *    the enquiry is NOT saved and NOT emailed.
 * ------------------------------------------------------------------------ */
$recaptchaSecret = trim((string) ($siteConfig['recaptcha_secret_key'] ?? ''));
if (
    $recaptchaSecret === '' ||
    stripos($recaptchaSecret, 'PASTE_') === 0 ||
    stripos($recaptchaSecret, 'YOUR_') === 0
) {
    error_log('HPM CAPTCHA is not configured: add the reCAPTCHA v2 secret key to config/config.php.');
    contact_redirect('captcha_config');
}

$captchaToken = $field('g-recaptcha-response');
if ($captchaToken === '' || strlen($captchaToken) > 4096) {
    contact_redirect('captcha');
}

$verifyPayload = http_build_query([
    'secret'   => $recaptchaSecret,
    'response' => $captchaToken,
    'remoteip' => $ip,
]);

$verifyResponse = false;
$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

if (function_exists('curl_init')) {
    $curl = curl_init($verifyUrl);
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $verifyPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
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
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $verifyPayload,
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $verifyResponse = @file_get_contents($verifyUrl, false, $context);
}

$captchaResult = is_string($verifyResponse) ? json_decode($verifyResponse, true) : null;

if (!is_array($captchaResult)) {
    // Google unreachable: fail closed, but tell the visitor it is a temporary problem.
    error_log('HPM CAPTCHA verification: no valid response from Google.');
    contact_redirect('error');
}

if (empty($captchaResult['success'])) {
    $codes = isset($captchaResult['error-codes']) && is_array($captchaResult['error-codes'])
        ? implode(',', array_map('strval', $captchaResult['error-codes']))
        : 'unknown';
    error_log('HPM CAPTCHA verification rejected an enquiry (' . $codes . ').');

    // A wrong secret/site key is a site configuration problem, not the visitor's fault.
    if (strpos($codes, 'invalid-input-secret') !== false || strpos($codes, 'missing-input-secret') !== false) {
        contact_redirect('captcha_config');
    }
    contact_redirect('captcha');
}

/* The token must have been issued for this website, not another site's key. */
if (!empty($captchaResult['hostname'])) {
    $tokenHost = strtolower((string) $captchaResult['hostname']);
    $strip = static fn(string $h): string => preg_replace('/^www\./', '', $h) ?? $h;
    $siteHost = strtolower((string) parse_url((string) ($siteConfig['site_url'] ?? ''), PHP_URL_HOST));
    $reqHost  = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if (
        $strip($tokenHost) !== $strip($reqHost) &&
        ($siteHost === '' || $strip($tokenHost) !== $strip($siteHost))
    ) {
        error_log('HPM CAPTCHA hostname mismatch: ' . $tokenHost);
        contact_redirect('captcha');
    }
}

/* ---------------------------------------------------------------------------
 * 6. Spam filter. A flagged message is stored as SPAM (visible in the admin
 *    "Spam" tab, never emailed) so a wrongly flagged genuine enquiry can be
 *    recovered with "Not spam". The visitor still sees the normal thank-you.
 * ------------------------------------------------------------------------ */
$spam = hpm_spam_check($name, $email, $company, $phone, $service, $message);
$isSpam = $spam['is_spam'];
$spamReason = $spam['reason'];

/* Source page: keep it only if it was supplied by this website. */
$pageUrl = '';
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if ($referer !== '') {
    $refHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
    $reqHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($refHost !== '' && $refHost === $reqHost) {
        $pageUrl = mb_substr($referer, 0, 500);
    }
}

/* ---------------------------------------------------------------------------
 * 7. Save. Duplicate protection: the same visitor sending the identical
 *    message twice (double click, refresh) is only stored once.
 * ------------------------------------------------------------------------ */
try {
    if ($ipHash !== null) {
        $dup = $pdo->prepare(
            'SELECT id FROM enquiries WHERE ip_hash = ? AND email = ? AND message = ? ORDER BY id DESC LIMIT 1'
        );
        $dup->execute([$ipHash, $email, $message]);
        if ($dup->fetchColumn()) {
            contact_redirect('success');
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO enquiries
            (name, email, company, phone, service, message, page_url, ip_hash, is_spam, spam_reason, spam_marked_at)
         VALUES
            (:name, :email, :company, :phone, :service, :message, :page_url, :ip_hash, :is_spam, :spam_reason, :spam_marked_at)'
    );

    $insert->execute([
        ':name'           => $name,
        ':email'          => $email,
        ':company'        => $company !== '' ? $company : null,
        ':phone'          => $phone,
        ':service'        => $service,
        ':message'        => $message,
        ':page_url'       => $pageUrl !== '' ? $pageUrl : null,
        ':ip_hash'        => $ipHash,
        ':is_spam'        => $isSpam ? 1 : 0,
        ':spam_reason'    => $isSpam ? mb_substr($spamReason, 0, 255) : null,
        ':spam_marked_at' => $isSpam ? gmdate('Y-m-d H:i:s') : null,
    ]);

    $enquiryId = (int) $pdo->lastInsertId();
} catch (Throwable $exception) {
    error_log('HPM contact enquiry database error: ' . $exception->getMessage());
    contact_redirect('error');
}

if ($isSpam) {
    // Stored for review, but no notification email for flagged messages.
    contact_redirect('success');
}

/* ---------------------------------------------------------------------------
 * 8. Email notification. Attempted AFTER the database insert, so a mail
 *    problem never loses the saved enquiry.
 * ------------------------------------------------------------------------ */
$mailSent = false;

/* Header values must never contain line breaks (email header injection). */
$oneLine = static fn(string $v): string => trim((string) preg_replace('/[\r\n]+/', ' ', $v));
$safe = static fn(string $value): string =>
    htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$subject = 'New HPM Website Enquiry #' . $enquiryId;
$htmlBody = '
    <h2>New website enquiry</h2>
    <p><strong>Enquiry ID:</strong> ' . $enquiryId . '</p>
    <p><strong>Name:</strong> ' . $safe($name) . '</p>
    <p><strong>Email:</strong> ' . $safe($email) . '</p>
    <p><strong>Company:</strong> ' . $safe($company !== '' ? $company : 'Not provided') . '</p>
    <p><strong>Phone:</strong> ' . $safe($phone) . '</p>
    <p><strong>Product / Service:</strong> ' . $safe($service) . '</p>
    <p><strong>Message:</strong><br>' . nl2br($safe($message)) . '</p>
    <p><strong>Source page:</strong> ' . $safe($pageUrl !== '' ? $pageUrl : 'Not available') . '</p>';
$textBody =
    "New HPM website enquiry #{$enquiryId}\n" .
    "Name: {$name}\nEmail: {$email}\nCompany: {$company}\n" .
    "Phone: {$phone}\nService: {$service}\n\nMessage:\n{$message}";

try {
    $autoload   = __DIR__ . '/vendor/autoload.php';
    $mailFile   = __DIR__ . '/includes/mail-config.php';
    $mailConfig = is_file($mailFile) ? require $mailFile : [];
    $mailConfig = is_array($mailConfig) ? $mailConfig : [];

    $smtpReady =
        is_file($autoload) &&
        !empty($mailConfig['host']) && $mailConfig['host'] !== 'YOUR_SMTP_HOST' &&
        !empty($mailConfig['username']) &&
        !empty($mailConfig['password']) && $mailConfig['password'] !== 'YOUR_EMAIL_ACCOUNT_PASSWORD';

    if ($smtpReady) {
        require_once $autoload;
    }

    if ($smtpReady && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host     = (string) $mailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $mailConfig['username'];
        $mail->Password = (string) $mailConfig['password'];
        $mail->Port     = (int) $mailConfig['port'];
        $mail->CharSet  = 'UTF-8';
        $mail->SMTPSecure = (($mailConfig['encryption'] ?? 'tls') === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom((string) $mailConfig['from_email'], $oneLine((string) $mailConfig['from_name']));
        $mail->addAddress((string) $mailConfig['to_email'], $oneLine((string) $mailConfig['to_name']));
        $mail->addReplyTo($email, $oneLine($name));
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        $mailSent = true;
    } else {
        /*
         * Fallback when SMTP / PHPMailer is not set up: PHP's built-in mail().
         * Works on most GoDaddy hosting plans. The From address must be a
         * mailbox on haripalletmachinery.com so the message is not rejected.
         */
        $to   = (string) ($mailConfig['to_email'] ?? $siteConfig['mail_to'] ?? 'info@haripalletmachinery.com');
        $from = (string) ($mailConfig['from_email'] ?? $siteConfig['mail_from'] ?? 'info@haripalletmachinery.com');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: Hari Pallet Machinery Website <' . $from . '>',
                'Reply-To: ' . $oneLine($email),
                'X-Mailer: HPM-Website',
            ];
            $mailSent = @mail(
                $to,
                '=?UTF-8?B?' . base64_encode($subject) . '?=',
                $htmlBody,
                implode("\r\n", $headers)
            );
            if (!$mailSent) {
                error_log('HPM enquiry email: mail() fallback failed.');
            }
        }
    }
} catch (Throwable $exception) {
    error_log('HPM enquiry email error: ' . $exception->getMessage());
}

contact_redirect($mailSent ? 'success' : 'saved');
