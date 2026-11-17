<?php
declare(strict_types=1);

// Public JSON endpoint: no login session required.
define('HPM_SKIP_SESSION', true);

require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

try {
    $sql = "
        SELECT
            p.id,
            p.name,
            p.category_id,
            p.short_description,
            p.description,
            p.specifications,
            p.image,
            p.before_image,
            p.after_image,
            p.video_url,
            p.alt_text,
            c.name AS category
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.id = ?
          AND p.is_active = 1
          AND c.is_active = 1
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    // Normalize empty database values for the frontend.
    $product['image'] = trim((string)($product['image'] ?? ''));
    $product['before_image'] = trim((string)($product['before_image'] ?? ''));
    $product['after_image'] = trim((string)($product['after_image'] ?? ''));
    $product['video_url'] = trim((string)($product['video_url'] ?? ''));
    $product['alt_text'] = trim((string)($product['alt_text'] ?? ''));
    $product['description'] = trim((string)($product['description'] ?? ''));
    $product['specifications'] = trim((string)($product['specifications'] ?? ''));

    $product['whatsapp_url'] = whatsapp_url(
        (string)$product['name'],
        'HPM-' . (int)$product['id']
    );

    echo json_encode(
        $product,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    error_log('[HPM Product API] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load product details']);
}
