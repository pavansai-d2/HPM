<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
admin_required();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
csrf_check();
$action = (string)($_POST['action'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$pdo = db();
try {
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $active = isset($_POST['is_active']) && (string)$_POST['is_active'] === '0' ? 0 : 1;
        if ($name === '') { header('Location: index.php?error=' . rawurlencode('Enter a category name.')); exit; }
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
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
        $st->execute([$name,$slug,$active,$max+1]);
    } elseif ($action === 'toggle' && $id) {
        $st = $pdo->prepare('UPDATE categories SET is_active=IF(is_active=1,0,1) WHERE id=?');
        $st->execute([$id]);
    } elseif ($action === 'delete' && $id) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) { header('Location: index.php?error=' . rawurlencode('This category contains products. Reassign or delete those products first.')); exit; }
        $st = $pdo->prepare('DELETE FROM categories WHERE id=?');
        $st->execute([$id]);
    } else {
        header('Location: index.php?error=' . rawurlencode('Invalid category action.')); exit;
    }
    header('Location: index.php?saved=1'); exit;
} catch (PDOException $ex) {
    error_log('[HPM Category] '.$ex->getMessage());
    header('Location: index.php?error=' . rawurlencode('Could not save the category. Check whether its name already exists.')); exit;
}
