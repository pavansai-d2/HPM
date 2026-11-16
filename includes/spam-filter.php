<?php
declare(strict_types=1);

/*
 * HPM contact-form spam filter.
 *
 * Scores a submission. A score of 3 or more is treated as spam and the
 * enquiry is saved in the admin "Spam" tab instead of the inbox (no email).
 *
 * Design rules:
 *  - Language and country are NEVER used as a signal. Names, messages and
 *    phone numbers in any script (Arabic, Hindi, Chinese, Cyrillic, ...) and
 *    from any country score exactly like English ones.
 *  - A single link is normal for a genuine enquiry (a product page, a brochure).
 *    Only several links, HTML/script content or repeated promotional text count.
 *  - Anything flagged is recoverable with "Not spam" in the admin panel.
 */

/**
 * @return array{is_spam: bool, score: int, reason: string}
 */
function hpm_spam_check(
    string $name,
    string $email,
    string $company,
    string $phone,
    string $service,
    string $message
): array {
    $score = 0;
    $reasons = [];
    $add = static function (int $points, string $why) use (&$score, &$reasons): void {
        $score += $points;
        $reasons[] = $why;
    };

    $lowerMessage = mb_strtolower($message);

    /* ---- Links in the message ------------------------------------------ */
    $urlPattern = '~(?:https?://|ftp://|www\.)[^\s<>"\']+|\b[a-z0-9][a-z0-9\-]{1,60}\.(?:com|net|org|info|biz|xyz|top|ru|cn|site|online|shop|club|link|click|vip|loan|work|live|store|tech|icu|cc|ws|pw|tk|ml|ga|cf|gq|buzz|rest|fun)\b(?:/[^\s<>"\']*)?~iu';
    preg_match_all($urlPattern, $message, $urlMatches);
    $urls = $urlMatches[0] ?? [];
    $linkCount = count($urls);

    if ($linkCount >= 4) {
        $add(4, "{$linkCount} links in the message");
    } elseif ($linkCount === 3) {
        $add(2, '3 links in the message');
    } elseif ($linkCount === 2) {
        $add(1, '2 links in the message');
    }

    if ($linkCount >= 2 && count(array_unique(array_map('strtolower', $urls))) < $linkCount) {
        $add(1, 'same link repeated');
    }

    // How much of the message is just links.
    if ($linkCount > 0) {
        $urlChars = array_sum(array_map('mb_strlen', $urls));
        if (mb_strlen($message) > 0 && ($urlChars / mb_strlen($message)) > 0.5) {
            $add(2, 'message is mostly links');
        }
    }

    // Links in short identity fields are a classic spam sign.
    if (preg_match($urlPattern, $name) || preg_match($urlPattern, $company) || preg_match($urlPattern, $service)) {
        $add(3, 'link in name/company/service field');
    }
    if (strpos($name, '@') !== false) {
        $add(2, 'email address used as name');
    }

    /* ---- Suspicious HTML / script content ------------------------------ */
    if (preg_match('~<\s*/?\s*(script|iframe|img|object|embed|svg|form|style|link|meta|base|video|audio|body)\b~i', $message)
        || preg_match('~\bon[a-z]{3,20}\s*=~i', $message)
        || preg_match('~javascript\s*:|data\s*:\s*text/html|vbscript\s*:~i', $message)) {
        $add(4, 'script / unsafe HTML in message');
    } elseif (preg_match('~<\s*/?\s*[a-z][a-z0-9]*(\s[^>]*)?>~i', $message)) {
        $add(2, 'HTML tags in message');
    }

    if (preg_match('~\[(?:url|link|img|b|i)(?:=[^\]]*)?\]~i', $message)) {
        $add(3, 'BBCode links in message');
    }
    // Spintax templates such as {Hello|Hi|Dear} used by bulk-posting tools.
    if (preg_match('~\{[^{}|]{1,60}(?:\|[^{}|]{1,60}){1,}\}~u', $message)) {
        $add(3, 'spintax template');
    }

    /* ---- Promotional / repeated content -------------------------------- */
    $promo = [
        'seo service', 'seo expert', 'search engine optimi', 'backlink', 'rank your website', 'first page of google',
        'guest post', 'increase your traffic', 'website traffic', 'boost your sales', 'lead generation service',
        'email list', 'buy followers', 'social media marketing', 'digital marketing offer', 'web design offer',
        'casino', 'poker', 'betting', 'viagra', 'cialis', 'pharmacy', 'cheap pills', 'weight loss', 'porn', 'escort',
        'dating', 'bitcoin', 'crypto', 'forex', 'binary option', 'make money online', 'earn money', 'work from home',
        'passive income', 'investment opportunity', 'loan offer', 'payday loan', 'lottery', 'you have won',
        'click here', 'limited time offer', 'act now', '100% free', 'no obligation', 'unsubscribe',
    ];
    $promoHits = [];
    foreach ($promo as $phrase) {
        if (mb_strpos($lowerMessage, $phrase) !== false) {
            $promoHits[] = $phrase;
        }
    }
    if (count($promoHits) >= 2) {
        $add(3, 'promotional content (' . implode(', ', array_slice($promoHits, 0, 4)) . ')');
    } elseif (count($promoHits) === 1) {
        $add(1, 'promotional wording (' . $promoHits[0] . ')');
    }

    // Repeated words / repeated sentences.
    preg_match_all('/[\p{L}\p{N}]+/u', $lowerMessage, $wordMatches);
    $words = $wordMatches[0] ?? [];
    if (count($words) >= 25) {
        $uniqueRatio = count(array_unique($words)) / count($words);
        if ($uniqueRatio < 0.30) {
            $add(3, 'highly repetitive text');
        }
    }
    $sentences = preg_split('/[.!?\n]+/u', $lowerMessage) ?: [];
    $sentenceCounts = [];
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (mb_strlen($sentence) >= 15) {
            $sentenceCounts[$sentence] = ($sentenceCounts[$sentence] ?? 0) + 1;
        }
    }
    if ($sentenceCounts && max($sentenceCounts) >= 3) {
        $add(3, 'same sentence repeated 3+ times');
    }

    /* ---- Basic sanity (language-neutral) ------------------------------- */
    // A real enquiry contains at least a few letters or ideographs.
    preg_match_all('/\p{L}/u', $message, $letterMatches);
    if (count($letterMatches[0] ?? []) < 5) {
        $add(3, 'message has almost no letters');
    }
    if (mb_strtolower($name) === $lowerMessage) {
        $add(2, 'name identical to message');
    }
    if (preg_match('/(.)\1{14,}/su', $message)) {
        $add(1, 'long run of one repeated character');
    }
    if (preg_match('/^\d+$/', preg_replace('/\s+/', '', $name) ?? '')) {
        $add(2, 'name is only digits');
    }

    return [
        'is_spam' => $score >= 3,
        'score' => $score,
        'reason' => $reasons ? 'Score ' . $score . ': ' . implode('; ', $reasons) : '',
    ];
}
