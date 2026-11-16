<?php

declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/enquiry-schema.php';
admin_required();

/*
 * Enquiry messages are untrusted text typed by anyone on the internet.
 * Everything is printed through e() (HTML-escaped, shown as plain text), and
 * this policy additionally stops any injected HTML from running scripts or
 * loading external images, frames or styles even if escaping were missed.
 */
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; frame-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$pdo = db();
hpm_enquiries_ensure_schema($pdo);

$view = (($_GET['view'] ?? '') === 'spam') ? 'spam' : 'inbox';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $backView = (($_POST['view'] ?? '') === 'spam') ? 'spam' : 'inbox';
    $back = 'enquiries.php' . ($backView === 'spam' ? '?view=spam' : '');

    try {
        if ($action === 'delete_all_spam') {
            $removed = $pdo->exec('DELETE FROM enquiries WHERE is_spam = 1');
            flash_set('success', (int) $removed . ' spam enquiries permanently deleted.');
        } elseif (!$id) {
            flash_set('error', 'Invalid enquiry reference.');
        } elseif ($action === 'status') {
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['New', 'Contacted', 'In Progress', 'Resolved'], true)) {
                flash_set('error', 'Invalid status.');
            } else {
                $pdo->prepare('UPDATE enquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
                flash_set('success', 'Enquiry status updated to "' . $status . '".');
            }
        } elseif ($action === 'spam') {
            $pdo->prepare(
                "UPDATE enquiries SET is_spam = 1, spam_reason = 'Marked as spam by admin', spam_marked_at = ? WHERE id = ?"
            )->execute([gmdate('Y-m-d H:i:s'), $id]);
            flash_set('success', 'Enquiry moved to Spam.');
        } elseif ($action === 'not_spam') {
            $pdo->prepare(
                'UPDATE enquiries SET is_spam = 0, spam_reason = NULL, spam_marked_at = NULL WHERE id = ?'
            )->execute([$id]);
            flash_set('success', 'Enquiry restored to the Inbox.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$id]);
            flash_set('success', 'Enquiry deleted.');
        } else {
            flash_set('error', 'This action is unavailable.');
        }
    } catch (Throwable $exception) {
        error_log('[HPM Enquiries] ' . $exception->getMessage());
        flash_set('error', 'Could not complete that action. Please try again.');
    }

    header('Location: ' . $back);
    exit;
}

$counts = $pdo->query(
    'SELECT SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) AS spam,
            SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) AS inbox
     FROM enquiries'
)->fetch(PDO::FETCH_ASSOC) ?: [];
$inboxCount = (int) ($counts['inbox'] ?? 0);
$spamCount = (int) ($counts['spam'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM enquiries WHERE is_spam = ? ORDER BY id DESC LIMIT 500');
$stmt->execute([$view === 'spam' ? 1 : 0]);
$enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$value = static function (array $row, string $col, string $fallback = '—'): string {
    if (!isset($row[$col]) || trim((string) $row[$col]) === '') {
        return $fallback;
    }
    return (string) $row[$col];
};
$dateValue = static function (array $row) use ($value): string {
    $raw = $value($row, 'created_at', '');
    if ($raw === '') {
        return '—';
    }
    $time = strtotime($raw);
    return $time ? date('d M Y, h:i A', $time) : $raw;
};
/* Only produce mailto:/tel: links from values that really look like an email / phone number. */
$mailLink = static function (string $email): string {
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $email : '';
};
$telLink = static function (string $phone): string {
    $clean = preg_replace('/[^\d+]/', '', $phone) ?? '';
    return $clean !== '' ? 'tel:' . $clean : '';
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Customer Enquiries | HPM Admin</title>
    <link rel="icon" href="../assets/images/HPM Logo.png" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css?v=20261117">
    <style>
        .enquiry-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px
        }

        .enquiry-search {
            width: min(380px, 100%);
            height: 42px;
            padding: 0 13px;
            border: 1px solid #d8e0e9;
            border-radius: 8px;
            outline: none
        }

        .enquiry-search:focus {
            border-color: #f47700;
            box-shadow: 0 0 0 3px #f4770020
        }

        .enquiry-customer strong {
            display: block;
            color: #263c54
        }

        .enquiry-customer a {
            display: block;
            color: #63758a;
            font-size: 12px;
            margin-top: 3px
        }

        .enquiry-message {
            max-width: 340px;
            white-space: pre-wrap;
            overflow-wrap: anywhere
        }

        .enquiry-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap
        }

        .enquiry-actions select {
            height: 33px;
            max-width: 125px;
            border: 1px solid #dce3eb;
            border-radius: 7px;
            padding: 0 6px;
            background: white
        }

        .schema-warning {
            padding: 13px 15px;
            margin-bottom: 16px;
            border: 1px solid #f0d9b8;
            border-radius: 8px;
            background: #fff8ed;
            color: #81521c
        }


        .enquiry-tabs {
            display: flex;
            gap: 8px;
            margin: 0 0 16px;
            flex-wrap: wrap
        }

        .enquiry-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border: 1px solid #dce3eb;
            border-radius: 999px;
            background: #fff;
            color: #4a5b70;
            font-weight: 600;
            text-decoration: none
        }

        .enquiry-tab b {
            display: inline-block;
            min-width: 22px;
            padding: 1px 7px;
            border-radius: 999px;
            background: #eef2f6;
            font-size: 12px;
            text-align: center
        }

        .enquiry-tab.active {
            background: #0d3765;
            border-color: #0d3765;
            color: #fff
        }

        .enquiry-tab.active b {
            background: rgba(255, 255, 255, .2)
        }

        .spam-reason {
            display: block;
            margin-top: 6px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #fff3f2;
            color: #a8322d;
            font-size: 12px;
            overflow-wrap: anywhere
        }

        .enquiry-plain {
            display: block;
            color: #63758a;
            font-size: 12px;
            margin-top: 3px;
            overflow-wrap: anywhere
        }

        .enquiry-message {
            max-height: 220px;
            overflow: auto
        }

        @media(max-width:700px) {
            .enquiry-toolbar {
                align-items: stretch;
                flex-direction: column
            }

            .enquiry-search {
                width: 100%
            }
        }
    </style>
</head>

<body class="hpm-admin">
    <header class="admin-topbar">
        <a class="brand" href="index.php"><img decoding="async" class="admin-brand-logo" src="../assets/images/hpm-logo.svg" alt="Hari Pallet Machinery logo"><span><b>Hari Pallet Machinery</b><small>Administration Portal</small></span></a>
        <div class="topbar-right"><span class="admin-welcome">Signed in as <strong>Admin</strong></span>
            <a class="logout-link" href="logout.php">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span>Log out</span>
            </a>
        </div>
    </header>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <span class="sidebar-label">WORKSPACE</span>
            <a class="side-link" href="index.php"><span>▦</span> Overview</a>
            <a class="side-link" href="index.php#categories"><span>▤</span> Categories</a>
            <a class="side-link" href="index.php#products"><span>▣</span> Products</a>
            <a class="side-link active" href="enquiries.php"><span>✉</span> Enquiries</a>
            <a class="side-link" href="../products.php" target="_blank" rel="noopener"><span>↗</span> View website</a>
            <div class="sidebar-note"><b>Customer Enquiries</b><small>Review website messages and contact potential customers.</small></div>
        </aside>
        <main class="admin-main">
            <div class="page-heading">
                <div><span class="eyebrow">CUSTOMER COMMUNICATION</span>
                    <h1>Website Enquiries</h1>
                    <p>View customer messages and follow up with potential customers.</p>
                </div><a class="outline-btn" href="index.php">← Back to dashboard</a>
            </div>
            <section class="stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));max-width:560px">
                <article class="stat-card"><span class="stat-icon purple">✉</span><small>Genuine enquiries</small><strong><?= $inboxCount ?></strong><span class="stat-foot">In your inbox</span></article>
                <article class="stat-card"><span class="stat-icon orange">⚑</span><small>Marked as spam</small><strong><?= $spamCount ?></strong><span class="stat-foot">Kept separate, never emailed</span></article>
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2><?= $view === 'spam' ? 'Spam Enquiries' : 'Customer Enquiries' ?></h2>
                        <p><?= $view === 'spam'
                                ? 'Messages caught by the spam filter or marked as spam. Use “Not spam” to move a genuine one back to the inbox.'
                                : 'Contact customers directly by email or phone. Messages are shown as plain text for safety.' ?></p>
                    </div>
                    <?php if ($view === 'spam' && $spamCount > 0): ?>
                        <form method="post" action="enquiries.php?view=spam" onsubmit="return confirm('Permanently delete ALL spam enquiries? This cannot be undone.')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_all_spam"><input type="hidden" name="view" value="spam">
                            <button class="small-btn danger" type="submit">Delete all spam</button>
                        </form>
                    <?php endif; ?>
                </div>
                <nav class="enquiry-tabs" aria-label="Enquiry folders">
                    <a class="enquiry-tab <?= $view === 'inbox' ? 'active' : '' ?>" href="enquiries.php">Inbox <b><?= $inboxCount ?></b></a>
                    <a class="enquiry-tab <?= $view === 'spam' ? 'active' : '' ?>" href="enquiries.php?view=spam">Spam <b><?= $spamCount ?></b></a>
                </nav>
                <div class="enquiry-toolbar"><input class="enquiry-search" id="enquirySearch" type="search" placeholder="Search name, email, phone or subject..."><span class="enquiry-count"><?= count($enquiries) ?> record(s)</span></div>
                <div class="table-scroll">
                    <table id="enquiriesTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$enquiries): ?><tr>
                                    <td colspan="6" class="empty-cell"><?= $view === 'spam' ? 'No spam enquiries.' : 'No enquiries have been submitted yet.' ?></td>
                                </tr><?php endif; ?>
                            <?php foreach ($enquiries as $row):
                                $name = $value($row, 'name', 'Website visitor');
                                $email = $value($row, 'email', '');
                                $phone = $value($row, 'phone', '');
                                $company = $value($row, 'company', '');
                                $subject = $value($row, 'service', 'General enquiry');
                                $message = $value($row, 'message');
                                $status = $value($row, 'status', 'New');
                                $isSpamRow = (int) ($row['is_spam'] ?? 0) === 1;
                                $search = strtolower(implode(' ', [$name, $email, $phone, $company, $subject, $message]));
                                $mailHref = $isSpamRow ? '' : $mailLink($email);
                                $telHref = $isSpamRow ? '' : $telLink($phone);
                            ?>
                                <tr data-search="<?= e($search) ?>">
                                    <td class="enquiry-customer"><strong><?= e($name) ?></strong>
                                        <?php if ($company !== ''): ?><span class="enquiry-plain"><?= e($company) ?></span><?php endif; ?>
                                        <?php if ($email !== ''): ?><?php if ($mailHref !== ''): ?><a href="<?= e($mailHref) ?>"><?= e($email) ?></a><?php else: ?><span class="enquiry-plain"><?= e($email) ?></span><?php endif; ?><?php endif; ?>
                                        <?php if ($phone !== ''): ?><?php if ($telHref !== ''): ?><a href="<?= e($telHref) ?>"><?= e($phone) ?></a><?php else: ?><span class="enquiry-plain"><?= e($phone) ?></span><?php endif; ?><?php endif; ?></td>
                                    <td><?= e($subject) ?></td>
                                    <td>
                                        <!-- Plain text only: escaped by e(), never rendered as HTML -->
                                        <div class="enquiry-message"><?= e($message) ?></div>
                                        <?php if ($isSpamRow && !empty($row['spam_reason'])): ?><span class="spam-reason">Why: <?= e($row['spam_reason']) ?></span><?php endif; ?>
                                    </td>
                                    <td><?= e($dateValue($row)) ?></td>
                                    <td><span class="status <?= strtolower($status) === 'resolved' ? 'is-active' : 'is-hidden' ?>"><?= e($isSpamRow ? 'Spam' : $status) ?></span></td>
                                    <td>
                                        <div class="enquiry-actions">
                                            <?php if ($mailHref !== ''): ?><a class="small-btn" href="<?= e($mailHref . '?subject=' . rawurlencode('HPM enquiry follow-up')) ?>">Email</a><?php endif; ?>
                                            <?php if ($telHref !== ''): ?><a class="small-btn" href="<?= e($telHref) ?>">Call</a><?php endif; ?>
                                            <?php if (!$isSpamRow): ?>
                                                <form method="post" action="enquiries.php">
                                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>">
                                                    <select name="status" aria-label="Update enquiry status" onchange="this.form.submit()"><?php foreach (['New', 'Contacted', 'In Progress', 'Resolved'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
                                                </form>
                                                <form method="post" action="enquiries.php" onsubmit="return confirm('Move this enquiry to Spam?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="spam"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>"><button class="small-btn danger" type="submit">Mark as Spam</button></form>
                                            <?php else: ?>
                                                <form method="post" action="enquiries.php?view=spam"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="not_spam"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="view" value="spam"><button class="small-btn" type="submit">Not spam</button></form>
                                            <?php endif; ?>
                                            <form method="post" action="enquiries.php<?= $view === 'spam' ? '?view=spam' : '' ?>" onsubmit="return confirm('Permanently delete this enquiry?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>"><button class="small-btn danger" type="submit">Delete</button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <footer class="admin-footer">HPM Admin || Hari Pallet Machinery <br> </footer>
            <footer>Design and Development by <a href="https://www.digital-services.pavanasutra.in/" style="color: #f45108;" target="_blank"><b>Pavanasutra</b></a></footer>
        </main>
    </div>
    <script>
        document.getElementById('enquirySearch')?.addEventListener('input', function() {
            const term = this.value.trim().toLowerCase();
            document.querySelectorAll('#enquiriesTable tbody tr[data-search]').forEach(row => row.hidden = !row.dataset.search.includes(term));
        });
    </script>
    <?php require __DIR__ . '/flash-popup.php'; ?>
</body>

</html>