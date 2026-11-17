<?php

declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
admin_required();
$pdo = db();

/* Read the existing table without changing or truncating it. */
$columns = array_column($pdo->query('SHOW COLUMNS FROM enquiries')->fetchAll(PDO::FETCH_ASSOC), 'Field');
$pick = static function (array $names) use ($columns): ?string {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) return $name;
    }
    return null;
};
$qid = static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`';

$idCol = $pick(['id', 'enquiry_id']);
$nameCol = $pick(['name', 'full_name', 'customer_name', 'contact_name']);
$emailCol = $pick(['email', 'email_address', 'customer_email']);
$phoneCol = $pick(['phone', 'mobile', 'mobile_number', 'phone_number', 'contact_number']);
$subjectCol = $pick(['subject', 'enquiry_subject', 'service']);
$messageCol = $pick(['message', 'enquiry', 'description', 'comments']);
$dateCol = $pick(['created_at', 'submitted_at', 'created_on', 'date_created']);
$statusCol = $pick(['status', 'enquiry_status']);

$flash = isset($_GET['updated']) ? 'Enquiry status updated.' : (isset($_GET['deleted']) ? 'Enquiry deleted.' : '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id || !$idCol) {
        $error = 'Invalid enquiry reference.';
    } elseif ($action === 'status' && $statusCol) {
        $status = (string)($_POST['status'] ?? '');
        $allowed = ['New', 'Contacted', 'In Progress', 'Resolved'];
        if (!in_array($status, $allowed, true)) {
            $error = 'Invalid status.';
        } else {
            $stmt = $pdo->prepare('UPDATE enquiries SET ' . $qid($statusCol) . ' = ? WHERE ' . $qid($idCol) . ' = ?');
            $stmt->execute([$status, $id]);
            header('Location: enquiries.php?updated=1');
            exit;
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM enquiries WHERE ' . $qid($idCol) . ' = ?');
        $stmt->execute([$id]);
        header('Location: enquiries.php?deleted=1');
        exit;
    } else {
        $error = 'This action is unavailable. Check the enquiries table columns.';
    }
}

$order = $dateCol ?: $idCol;
$sql = 'SELECT * FROM enquiries' . ($order ? ' ORDER BY ' . $qid($order) . ' DESC' : '');
$enquiries = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$value = static function (array $row, ?string $col, string $fallback = '—'): string {
    if (!$col || !isset($row[$col]) || trim((string)$row[$col]) === '') return $fallback;
    return (string)$row[$col];
};
$dateValue = static function (array $row) use ($dateCol, $value): string {
    $raw = $value($row, $dateCol, '');
    if ($raw === '') return '—';
    $time = strtotime($raw);
    return $time ? date('d M Y, h:i A', $time) : $raw;
};
$missing = [];
if (!$idCol) $missing[] = 'id';
if (!$nameCol) $missing[] = 'name/full_name';
if (!$emailCol) $missing[] = 'email';
if (!$messageCol) $missing[] = 'message';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Customer Enquiries | HPM Admin</title>
    <link rel="icon" href="../assets/images/HPM Logo.png" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css">
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
            <?php if ($flash): ?><div class="notice success"><?= e($flash) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($missing): ?><div class="schema-warning"><strong>Database column check required.</strong> Could not identify <?= e(implode(', ', $missing)) ?> in the existing enquiries table. Run <code>DESCRIBE enquiries;</code> and share the result so the field mapping can be matched exactly.</div><?php endif; ?>
            <section class="stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));max-width:560px">
                <article class="stat-card"><span class="stat-icon purple">✉</span><small>Total enquiries</small><strong><?= count($enquiries) ?></strong><span class="stat-foot">Customer submissions stored</span></article>
                <article class="stat-card"><span class="stat-icon orange">◷</span><small>Latest submission</small><strong style="font-size:17px;margin-top:10px"><?= $enquiries ? e($dateValue($enquiries[0])) : '—' ?></strong><span class="stat-foot">Most recent enquiry</span></article>
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>All Customer Enquiries</h2>
                        <p>Contact customers directly by email or phone.</p>
                    </div>
                </div>
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
                                    <td colspan="6" class="empty-cell">No enquiries have been submitted yet.</td>
                                </tr><?php endif; ?>
                            <?php foreach ($enquiries as $row):
                                $name = $value($row, $nameCol, 'Website visitor');
                                $email = $value($row, $emailCol, '');
                                $phone = $value($row, $phoneCol, '');
                                $subject = $value($row, $subjectCol, 'General enquiry');
                                $message = $value($row, $messageCol);
                                $status = $value($row, $statusCol, 'New');
                                $search = strtolower(implode(' ', [$name, $email, $phone, $subject, $message]));
                            ?>
                                <tr data-search="<?= e($search) ?>">
                                    <td class="enquiry-customer"><strong><?= e($name) ?></strong>
                                        <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
                                        <?php if ($phone !== ''): ?><a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a><?php endif; ?></td>
                                    <td><?= e($subject) ?></td>
                                    <td>
                                        <div class="enquiry-message"><?= e($message) ?></div>
                                    </td>
                                    <td><?= e($dateValue($row)) ?></td>
                                    <td><span class="status <?= strtolower($status) === 'resolved' ? 'is-active' : 'is-hidden' ?>"><?= e($status) ?></span></td>
                                    <td>
                                        <div class="enquiry-actions">
                                            <?php if ($email !== ''): ?><a class="small-btn" href="mailto:<?= e($email) ?>?subject=<?= rawurlencode('HPM enquiry follow-up') ?>">Email</a><?php endif; ?>
                                            <?php if ($phone !== ''): ?><a class="small-btn" href="tel:<?= e($phone) ?>">Call</a><?php endif; ?>
                                            <?php if ($statusCol): ?><form method="post" action="enquiries.php">
                                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$row[$idCol] ?>">
                                                    <select name="status" aria-label="Update enquiry status" onchange="this.form.submit()"><?php foreach (['New', 'Contacted', 'In Progress', 'Resolved'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
                                                </form><?php endif; ?>
                                            <form method="post" action="enquiries.php" onsubmit="return confirm('Permanently delete this enquiry?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row[$idCol] ?>"><button class="small-btn danger" type="submit">Delete</button></form>
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
</body>

</html>