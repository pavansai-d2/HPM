<?php
declare(strict_types=1);

/*
 * HPM shared product-category endpoint.
 * Place this file at: /api/categories.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
// Category changes made in the admin panel should be visible on the next page load.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $pdo = db();

    $stmt = $pdo->query(
        "SELECT name, slug
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC"
    );

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(
        [
            'success' => true,
            'categories' => array_map(
                static function (array $category): array {
                    return [
                        'name' => (string) $category['name'],
                        'slug' => (string) $category['slug'],
                    ];
                },
                $categories
            ),
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    error_log('HPM category navigation API error: ' . $exception->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'categories' => [],
        'message' => 'Product categories are temporarily unavailable.',
    ]);
}
