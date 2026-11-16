<?php
declare(strict_types=1);

/*
 * HPM shared product-category endpoint.
 * Place this file at: /api/categories.php
 */
define('HPM_PUBLIC', true); // no PHP session needed: faster, and cacheable by ETag
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db();

    $stmt = $pdo->query(
        "SELECT id, name, slug
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC"
    );

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $json = json_encode(
        [
            'success' => true,
            'categories' => array_map(
                static function (array $category): array {
                    return [
                        'id' => (int) $category['id'],
                        'name' => (string) $category['name'],
                        'slug' => (string) $category['slug'],
                    ];
                },
                $categories
            ),
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    // Always re-checked by the browser, so a category added in the admin panel
    // shows up on the next page load; unchanged lists return a tiny 304.
    hpm_send_with_etag((string) $json, 'application/json; charset=utf-8');
} catch (Throwable $exception) {
    error_log('HPM category navigation API error: ' . $exception->getMessage());

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'categories' => [],
        'message' => 'Product categories are temporarily unavailable.',
    ]);
}
