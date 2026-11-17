<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
admin_required();

$pdo = db();

$counts = [
    'products'   => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'published'  => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'enquiries'  => (int) $pdo->query('SELECT COUNT(*) FROM enquiries')->fetchColumn(),
];

$categories = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.slug,
        c.is_active,
        c.created_at,
        COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();

$rows = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.is_active,
        p.updated_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.updated_at DESC
    LIMIT 50
")->fetchAll();

$flash = isset($_GET['saved'])
    ? 'Changes saved successfully.'
    : (isset($_GET['deleted']) ? 'Item deleted successfully.' : '');

$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HPM Admin Dashboard | Hari Pallet Machinery</title>
    <link rel="icon" href="../assets/images/hpm-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css">
</head>

<body class="hpm-admin">

    <header class="admin-topbar">
        <a class="brand" href="index.php">
            <img decoding="async" class="admin-brand-logo" src="../assets/images/hpm-logo.svg" alt="Hari Pallet Machinery logo">
            <span>
                <b>Hari Pallet Machinery</b>
                <small>Administration Portal</small>
            </span>
        </a>

        <div class="topbar-right">
            <span class="admin-welcome">
                <strong>Admin</strong>
            </span>
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

            <a class="side-link active" href="index.php">
                <span>▦</span> Overview
            </a>

            <a class="side-link" href="index.php#categories">
                <span>▤</span> Categories
            </a>

            <a class="side-link" href="index.php#products">
                <span>▣</span> Products
            </a>

            <a class="side-link" href="enquiries.php">
                <span>✉</span> Enquiries
                <?php if ($counts['enquiries'] > 0): ?>
                    <span class="sidebar-count"><?= $counts['enquiries'] ?></span>
                <?php endif; ?>
            </a>

            <a class="side-link" href="../products.php" target="_blank" rel="noopener">
                <span>↗</span> View website
            </a>

            <div class="sidebar-note">
                <b>HPM Catalogue</b>
                <small>Manage products, categories and customer enquiries received through your website.</small>
            </div>
        </aside>

        <main class="admin-main">

            <div class="page-heading">
                <div>
                    <span class="eyebrow">CONTROL PANEL</span>
                    <h1>Dashboard overview</h1>
                    <p>Manage your product catalogue, website categories and customer enquiries.</p>
                </div>
                <!-- The top-right Add product button has intentionally been removed. -->
            </div>

            <?php if ($flash): ?>
                <div class="notice success"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notice error"><?= e($error) ?></div>
            <?php endif; ?>

            <section class="stats-grid">

                <article class="stat-card">
                    <span class="stat-icon orange">▣</span>
                    <small>Total products</small>
                    <strong><?= $counts['products'] ?></strong>
                    <span class="stat-foot">Catalogue entries</span>
                </article>

                <article class="stat-card">
                    <span class="stat-icon green">✓</span>
                    <small>Published products</small>
                    <strong><?= $counts['published'] ?></strong>
                    <span class="stat-foot">Visible on website</span>
                </article>

                <article class="stat-card">
                    <span class="stat-icon blue">▤</span>
                    <small>Categories</small>
                    <strong><?= $counts['categories'] ?></strong>
                    <span class="stat-foot">Product groups</span>
                </article>

                <a class="stat-card stat-card-link" href="enquiries.php" aria-label="Open customer enquiries">
                    <span class="stat-icon purple">✉</span>
                    <small>Enquiries</small>
                    <strong><?= $counts['enquiries'] ?></strong>
                    <span class="stat-foot">View website enquiries →</span>
                </a>

            </section>

            <section class="panel" id="categories">
                <div class="panel-heading">
                    <div>
                        <h2>Product categories</h2>
                        <p>Create and organise the groups used in the public catalogue.</p>
                    </div>
                </div>

                <form class="category-create" method="post" action="category-action.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <label class="sr-only" for="category-name">Category name</label>
                    <input
                        id="category-name"
                        name="name"
                        required
                        maxlength="120"
                        placeholder="Enter category name">

                    <label class="sr-only" for="category-status">Category status</label>
                    <select id="category-status" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>

                    <button class="primary-btn" type="submit">Create category</button>
                </form>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$categories): ?>
                                <tr>
                                    <td colspan="4" class="empty-cell">No categories created yet.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($cat['name']) ?></strong>
                                        <small class="table-sub"><?= e($cat['slug']) ?></small>
                                    </td>
                                    <td><?= (int) $cat['product_count'] ?></td>
                                    <td>
                                        <span class="status <?= $cat['is_active'] ? 'is-active' : 'is-hidden' ?>">
                                            <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <form method="post" action="category-action.php">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <button class="small-btn" type="submit">
                                                <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form
                                            method="post"
                                            action="category-action.php"
                                            onsubmit="return confirm('Delete this category? Categories containing products cannot be deleted.')">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="small-btn danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="products">
                <div class="panel-heading">
                    <div>
                        <h2>Products</h2>
                        <p>Latest catalogue entries, images, descriptions and specifications.</p>
                    </div>
                    <a class="outline-btn" href="product-edit.php">＋ Add product</a>
                </div>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Visibility</th>
                                <th>Last updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="5" class="empty-cell">
                                        No products added. Create a category first, then add your products.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($rows as $item): ?>
                                <tr>
                                    <td><strong><?= e($item['name']) ?></strong></td>
                                    <td><?= e($item['category_name'] ?: 'Unassigned') ?></td>
                                    <td>
                                        <span class="status <?= $item['is_active'] ? 'is-active' : 'is-hidden' ?>">
                                            <?= $item['is_active'] ? 'Published' : 'Hidden' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e(
                                            !empty($item['updated_at'])
                                                ? date('d M Y', strtotime($item['updated_at']))
                                                : '—'
                                        ) ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a class="small-btn" href="product-edit.php?id=<?= (int) $item['id'] ?>">Edit</a>

                                        <form
                                            method="post"
                                            action="product-delete.php"
                                            onsubmit="return confirm('Permanently delete this product?')">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="small-btn danger" type="submit">Delete</button>
                                        </form>
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

</body>

</html>