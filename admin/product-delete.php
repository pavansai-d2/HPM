<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

csrf_check();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?error=invalid_product');
    exit;
}

$pdo = db();

$productStmt = $pdo->prepare(
    'SELECT image, before_image, after_image FROM products WHERE id = ? LIMIT 1'
);
$productStmt->execute([$id]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php?error=product_not_found');
    exit;
}

$galleryStmt = $pdo->prepare(
    'SELECT image_path FROM product_images WHERE product_id = ?'
);
$galleryStmt->execute([$id]);
$gallery = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);

$pdo->beginTransaction();

try {
    $removeGalleryRows = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
    $removeGalleryRows->execute([$id]);

    $delete = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $delete->execute([$id]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[HPM Product Delete] ' . $exception->getMessage());
    http_response_code(500);
    exit('Could not delete the product.');
}

// Remove only files stored inside /uploads/products after the DB transaction succeeds.
$baseDir = realpath(__DIR__ . '/../uploads/products');
$paths = array_merge(
    $gallery,
    [$product['image'] ?? null, $product['before_image'] ?? null, $product['after_image'] ?? null]
);

if ($baseDir !== false) {
    foreach ($paths as $relativePath) {
        $relativePath = (string)$relativePath;

        if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/products/')) {
            continue;
        }

        $candidate = realpath(__DIR__ . '/../' . $relativePath);

        if (
            $candidate !== false &&
            str_starts_with($candidate, $baseDir . DIRECTORY_SEPARATOR) &&
            is_file($candidate)
        ) {
            @unlink($candidate);
        }
    }
}

header('Location: index.php?deleted=1');
exit;
