<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
admin_required();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
csrf_check();

$action = (string)($_POST['action'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$pdo = db();

/* Every outcome is shown to the admin as a popup on the dashboard. */
function category_done(string $type, string $message): never
{
    flash_set($type, $message);
    header('Location: index.php#categories');
    exit;
}

try {
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $active = isset($_POST['is_active']) && (string)$_POST['is_active'] === '0' ? 0 : 1;

        if ($name === '') {
            category_done('error', 'Enter a category name.');
        }
        if (mb_strlen($name) > 120) {
            category_done('error', 'Category name must be 120 characters or fewer.');
        }

        $dup = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $dup->execute([$name]);
        if ($dup->fetchColumn()) {
            category_done('error', 'A category named "' . $name . '" already exists.');
        }

        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($slug === '') { $slug = 'category'; }
        $base = $slug; $n = 2;
        while (true) {
            $check = $pdo->prepare('SELECT id FROM categories WHERE slug=? LIMIT 1');
            $check->execute([$slug]);
            if (!$check->fetchColumn()) break;
            $slug = $base . '-' . $n++;
        }
        $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();
        $st = $pdo->prepare('INSERT INTO categories(name,slug,is_active,sort_order) VALUES(?,?,?,?)');
        $st->execute([$name, $slug, $active, $max + 1]);

        category_done('success', $active
            ? 'Category "' . $name . '" was added successfully. It now appears in the website menu and on the Products page.'
            : 'Category "' . $name . '" was added as Inactive. Activate it when you want it to appear on the website.');
    } elseif (($action === 'move_up' || $action === 'move_down') && $id) {
        // Renumber everything 1..n in the current menu order, then swap with the neighbour.
        $ids = array_map('intval', $pdo->query('SELECT id FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_COLUMN));
        $pos = array_search($id, $ids, true);
        $target = $action === 'move_up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$target])) {
            [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
        }
        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
        foreach ($ids as $i => $categoryId) { $upd->execute([$i + 1, $categoryId]); }
        $pdo->commit();
        category_done('success', 'Menu order updated.');
    } elseif ($action === 'toggle' && $id) {
        $row = $pdo->prepare('SELECT name, is_active FROM categories WHERE id=?');
        $row->execute([$id]);
        $cat = $row->fetch(PDO::FETCH_ASSOC);
        if (!$cat) { category_done('error', 'Category not found.'); }
        $newState = ((int)$cat['is_active'] === 1) ? 0 : 1;
        $st = $pdo->prepare('UPDATE categories SET is_active=? WHERE id=?');
        $st->execute([$newState, $id]);
        category_done('success', 'Category "' . $cat['name'] . '" is now ' . ($newState ? 'Active and visible on the website.' : 'Inactive and hidden from the website.'));
    } elseif ($action === 'delete' && $id) {
        $row = $pdo->prepare('SELECT name FROM categories WHERE id=?');
        $row->execute([$id]);
        $catName = (string)$row->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            category_done('error', 'This category contains products. Reassign or delete those products first.');
        }
        $st = $pdo->prepare('DELETE FROM categories WHERE id=?');
        $st->execute([$id]);
        category_done('success', 'Category "' . $catName . '" was deleted.');
    } else {
        category_done('error', 'Invalid category action.');
    }
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[HPM Category] ' . $ex->getMessage());
    category_done('error', 'Could not save the category. Please try again.');
}
