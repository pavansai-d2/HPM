<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
admin_required();

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$p = [
    'name' => '',
    'slug' => '',
    'category_id' => '',
    'short_description' => '',
    'description' => '',
    'specifications' => '',
    'image' => '',
    'before_image' => '',
    'after_image' => '',
    'alt_text' => '',
    'video_url' => '',
    'is_featured' => 0,
    'is_active' => 1,
];

if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        exit('Product not found.');
    }

    $p = array_merge($p, $existing);
}

$categories = $pdo->query(
    'SELECT id, name FROM categories
     WHERE is_active = 1 OR id = ' . (int)($p['category_id'] ?? 0) . '
     ORDER BY sort_order, id'
)->fetchAll(PDO::FETCH_ASSOC);

$gallery = [];
if ($id > 0) {
    $galleryStmt = $pdo->prepare(
        'SELECT id, image_path, alt_text, sort_order
         FROM product_images
         WHERE product_id = ?
         ORDER BY sort_order, id'
    );
    $galleryStmt->execute([$id]);
    $gallery = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);
}

$error = '';

function hpm_product_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

/**
 * Shrinks a big phone/camera photo before it is stored, so product pages load
 * fast on mobile data: longest side limited to 1600 px, saved as WebP (or JPEG
 * when WebP is unavailable). Returns the new file name, or null when GD is not
 * available / anything goes wrong - the caller then stores the original upload.
 */
function hpm_product_optimize_image(string $tmpPath, string $mime, string $uploadDir): ?string
{
    try {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
            return null;
        }

        $info = @getimagesize($tmpPath);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return null;
        }
        [$width, $height] = [(int)$info[0], (int)$info[1]];
        // Very large pixel counts could exhaust server memory: keep the original.
        if ($width * $height > 40_000_000) {
            return null;
        }

        $source = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpPath) : false,
            'image/png'  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpPath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default      => false,
        };
        if (!$source) {
            return null;
        }

        // Respect the camera's rotation flag so photos are not saved sideways.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data') && function_exists('imagerotate')) {
            $exif = @exif_read_data($tmpPath);
            $orientation = (int)($exif['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $rotated = @imagerotate($source, $angle, 0);
                if ($rotated) {
                    imagedestroy($source);
                    $source = $rotated;
                    $width = imagesx($source);
                    $height = imagesy($source);
                }
            }
        }

        $maxSide = 1600;
        $longest = max($width, $height);
        $resized = $longest > $maxSide;
        if ($resized) {
            $scale = $maxSide / $longest;
            $newW = max(1, (int)round($width * $scale));
            $newH = max(1, (int)round($height * $scale));
        } else {
            [$newW, $newH] = [$width, $height];
        }

        $canvas = imagecreatetruecolor($newW, $newH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($source);

        $useWebp = function_exists('imagewebp');
        $extension = $useWebp ? 'webp' : 'jpg';
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $uploadDir . $filename;

        if ($useWebp) {
            $saved = imagewebp($canvas, $target, 82);
        } else {
            // JPEG has no transparency: flatten onto white.
            $flat = imagecreatetruecolor($newW, $newH);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $canvas, 0, 0, 0, 0, $newW, $newH);
            $saved = imagejpeg($flat, $target, 85);
            imagedestroy($flat);
        }
        imagedestroy($canvas);

        if (!$saved || !is_file($target) || filesize($target) === 0) {
            @unlink($target);
            return null;
        }

        // If re-encoding made a small, already-light image bigger, keep the original.
        if (!$resized && filesize($target) >= (int)filesize($tmpPath)) {
            @unlink($target);
            return null;
        }

        @chmod($target, 0644);
        return $filename;
    } catch (Throwable $exception) {
        error_log('[HPM Product Image] optimisation skipped: ' . $exception->getMessage());
        return null;
    }
}

/**
 * Save one image in /uploads/products.
 * Returns its relative path, or null when no file was selected.
 */
function hpm_product_save_image(
    array $file,
    string $label,
    ?int $maxBytes = null
): ?string {
    // Large camera photos are fine when the server can shrink them (GD library);
    // otherwise keep the original stricter 8 MB limit.
    $maxBytes ??= function_exists('imagecreatetruecolor') ? 20_971_520 : 8_388_608;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (
        $file['error'] !== UPLOAD_ERR_OK ||
        !is_uploaded_file($file['tmp_name'] ?? '')
    ) {
        throw new RuntimeException('The ' . $label . ' upload failed. Please try again.');
    }

    if ((int)($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('The ' . $label . ' must be ' . (int)($maxBytes / 1_048_576) . ' MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('The ' . $label . ' must be JPG, PNG or WebP.');
    }

    $uploadDir = __DIR__ . '/../uploads/products/';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)
    ) {
        throw new RuntimeException('Could not create the product upload directory.');
    }

    // Resize/compress large photos first; falls back to the original file.
    $optimised = hpm_product_optimize_image($file['tmp_name'], $mime, $uploadDir);
    if ($optimised !== null) {
        return 'uploads/products/' . $optimised;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('Could not save the ' . $label . '.');
    }

    return 'uploads/products/' . $filename;
}

/**
 * Only removes files inside this application's product upload directory.
 */
function hpm_product_remove_image(?string $relativePath): void
{
    if (!$relativePath || !str_starts_with($relativePath, 'uploads/products/')) {
        return;
    }

    $baseDir = realpath(__DIR__ . '/../uploads/products');
    $candidate = realpath(__DIR__ . '/../' . $relativePath);

    if (
        $baseDir &&
        $candidate &&
        str_starts_with($candidate, $baseDir . DIRECTORY_SEPARATOR) &&
        is_file($candidate)
    ) {
        @unlink($candidate);
    }
}

// When the photos are bigger than the server's upload limit PHP discards the whole
// form (even the security token). Explain that clearly instead of a confusing 403.
$hpmPostTooLarge = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$_POST && !$_FILES
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
if ($hpmPostTooLarge) {
    $error = 'The selected photos are larger than this server allows ('
        . ini_get('post_max_size') . ' per save). Choose fewer or smaller photos and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hpmPostTooLarge) {
    csrf_check();

    // Keep existing image paths unless the user uploads a replacement or removes an optional image.
    $oldImagePaths = [
        'image'        => $p['image'] ?? null,
        'before_image' => $p['before_image'] ?? null,
        'after_image'  => $p['after_image'] ?? null,
    ];

    foreach (
        [
            'name',
            'slug',
            'category_id',
            'short_description',
            'description',
            'specifications',
            'alt_text',
            'video_url',
        ] as $key
    ) {
        $p[$key] = trim((string)($_POST[$key] ?? ''));
    }

    $p['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $p['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $p['slug'] = hpm_product_slugify($p['slug'] ?: $p['name']);
    $p['category_id'] = (int)$p['category_id'];

    foreach (['description', 'specifications', 'video_url'] as $key) {
        if ($p[$key] === '') {
            $p[$key] = null;
        }
    }

    if (
        $p['name'] === '' ||
        !$p['category_id'] ||
        $p['short_description'] === ''
    ) {
        $error = 'Product name, category and short description are required.';
    } elseif (
        $p['video_url'] !== null &&
        (
            !filter_var($p['video_url'], FILTER_VALIDATE_URL) ||
            !preg_match('~^https?://~i', $p['video_url'])
        )
    ) {
        $error = 'Enter a valid YouTube, Vimeo or direct video URL (https://...).';
    }

    if (!$error) {
        $categoryCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $categoryCheck->execute([$p['category_id']]);

        if (!(int)$categoryCheck->fetchColumn()) {
            $error = 'Select a valid product category.';
        }
    }

    $newUploads = [];
    $galleryUploads = [];
    $removedGalleryPaths = [];
    $isNewProduct = $id <= 0;

    if (!$error) {
        try {
            // Main, Before and After images are all optional uploads.
            $uploadFields = [
                'product_image' => [
                    'field' => 'image',
                    'label' => 'main product image',
                ],
                'before_image_file' => [
                    'field' => 'before_image',
                    'label' => 'before image',
                ],
                'after_image_file' => [
                    'field' => 'after_image',
                    'label' => 'after image',
                ],
            ];

            foreach ($uploadFields as $inputName => $config) {
                if (!isset($_FILES[$inputName])) {
                    continue;
                }

                $savedPath = hpm_product_save_image(
                    $_FILES[$inputName],
                    $config['label']
                );

                if ($savedPath !== null) {
                    $newUploads[] = $savedPath;
                    $p[$config['field']] = $savedPath;
                }
            }

            // Optional removal of existing Before / After images.
            if (isset($_POST['remove_before_image'])) {
                $p['before_image'] = null;
            }

            if (isset($_POST['remove_after_image'])) {
                $p['after_image'] = null;
            }

            // Multiple additional gallery uploads.
            if (
                isset($_FILES['gallery_images']) &&
                is_array($_FILES['gallery_images']['name'] ?? null)
            ) {
                $files = $_FILES['gallery_images'];

                foreach ($files['name'] as $index => $originalName) {
                    if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $oneFile = [
                        'name'     => $originalName,
                        'type'     => $files['type'][$index] ?? '',
                        'tmp_name' => $files['tmp_name'][$index] ?? '',
                        'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $files['size'][$index] ?? 0,
                    ];

                    $savedPath = hpm_product_save_image($oneFile, 'gallery image');

                    if ($savedPath !== null) {
                        $newUploads[] = $savedPath;
                        $galleryUploads[] = $savedPath;
                    }
                }
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $update = $pdo->prepare(
                    'UPDATE products
                     SET name = ?, slug = ?, category_id = ?, short_description = ?,
                         description = ?, specifications = ?, image = ?,
                         before_image = ?, after_image = ?, alt_text = ?,
                         is_featured = ?, is_active = ?, video_url = ?
                     WHERE id = ?'
                );

                $update->execute([
                    $p['name'],
                    $p['slug'],
                    $p['category_id'],
                    $p['short_description'],
                    $p['description'],
                    $p['specifications'],
                    $p['image'],
                    $p['before_image'],
                    $p['after_image'],
                    $p['alt_text'],
                    $p['is_featured'],
                    $p['is_active'],
                    $p['video_url'],
                    $id,
                ]);
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO products
                     (name, slug, category_id, short_description, description,
                      specifications, image, before_image, after_image, alt_text,
                      is_featured, is_active, video_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $insert->execute([
                    $p['name'],
                    $p['slug'],
                    $p['category_id'],
                    $p['short_description'],
                    $p['description'],
                    $p['specifications'],
                    $p['image'],
                    $p['before_image'],
                    $p['after_image'],
                    $p['alt_text'],
                    $p['is_featured'],
                    $p['is_active'],
                    $p['video_url'],
                ]);

                $id = (int)$pdo->lastInsertId();
            }

            // Remove selected gallery records for this product only.
            $removeIds = array_values(array_filter(
                array_map('intval', (array)($_POST['remove_gallery'] ?? [])),
                static fn(int $value): bool => $value > 0
            ));

            if ($removeIds) {
                $placeholders = implode(',', array_fill(0, count($removeIds), '?'));

                $findRemoved = $pdo->prepare(
                    "SELECT id, image_path
                     FROM product_images
                     WHERE product_id = ? AND id IN ($placeholders)"
                );
                $findRemoved->execute(array_merge([$id], $removeIds));
                $removedRows = $findRemoved->fetchAll(PDO::FETCH_ASSOC);

                $deleteGallery = $pdo->prepare(
                    "DELETE FROM product_images
                     WHERE product_id = ? AND id IN ($placeholders)"
                );
                $deleteGallery->execute(array_merge([$id], $removeIds));

                foreach ($removedRows as $row) {
                    $removedGalleryPaths[] = $row['image_path'];
                }
            }

            // Add newly uploaded gallery images without deleting existing ones.
            if ($galleryUploads) {
                $insertGallery = $pdo->prepare(
                    'INSERT INTO product_images
                     (product_id, image_path, alt_text, sort_order)
                     VALUES (?, ?, ?, ?)'
                );

                $sortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), 0)
                     FROM product_images
                     WHERE product_id = ?'
                );
                $sortStmt->execute([$id]);
                $sort = (int)$sortStmt->fetchColumn();

                foreach ($galleryUploads as $path) {
                    $sort++;
                    $insertGallery->execute([
                        $id,
                        $path,
                        $p['alt_text'] ?: $p['name'],
                        $sort,
                    ]);
                }
            }

            $pdo->commit();

            // Delete replaced / removed files only after successful database commit.
            foreach (['image', 'before_image', 'after_image'] as $field) {
                if (
                    !empty($oldImagePaths[$field]) &&
                    $oldImagePaths[$field] !== ($p[$field] ?? null)
                ) {
                    hpm_product_remove_image($oldImagePaths[$field]);
                }
            }

            foreach ($removedGalleryPaths as $path) {
                hpm_product_remove_image($path);
            }

            flash_set(
                'success',
                'Product "' . $p['name'] . '" was ' . ($isNewProduct ? 'added' : 'updated') . ' successfully.'
                . ((int)$p['is_active'] === 1
                    ? ' It is now visible in its category on the Products page.'
                    : ' It is saved as Hidden, so it is not shown on the website yet.')
            );
            header('Location: product-edit.php?id=' . $id . '&saved=1');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($newUploads as $path) {
                hpm_product_remove_image($path);
            }

            error_log('[HPM Product Save] ' . $exception->getMessage());

            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Could not save the product. Check the database tables and upload permissions, then try again.';
        }
    }
}

/* Validation / save errors are shown as a popup as well as inline. */
if ($error !== '') {
    flash_set('error', $error);
}

function hpm_admin_image_url(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    return '../' . ltrim($path, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id ? 'Edit' : 'Add' ?> Product | HPM Admin</title>
    <link rel="icon" href="../assets/images/hpm-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css?v=20261117">

    <style>
        .hpm-upload-section {
            grid-column: 1 / -1;
            padding: 22px;
            border: 1px solid #e1e7ef;
            border-radius: 14px;
            background: #fbfcfe;
            margin-top: 8px;
        }

        .hpm-upload-section h2 {
            margin: 0 0 6px;
            font-size: 19px;
        }

        .hpm-upload-section > p,
        .hpm-field-help {
            color: #748198;
            font-size: 13px;
            line-height: 1.6;
        }

        .hpm-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 18px;
        }

        .hpm-upload-field {
            display: flex;
            flex-direction: column;
            gap: 9px;
            min-width: 0;
            font-weight: 600;
            color: #34445d;
        }

        .hpm-upload-field input[type="file"] {
            display: block;
            width: 100%;
            min-height: 52px;
            padding: 12px;
            border: 1px solid #d4ddea;
            border-radius: 9px;
            background: #fff;
            color: #34445d;
        }

        .hpm-current-image {
            display: inline-flex;
            flex-direction: column;
            gap: 9px;
            width: min(100%, 280px);
            padding: 10px;
            border: 1px solid #e1e7ef;
            border-radius: 11px;
            background: #fff;
        }

        .hpm-current-image img {
            display: block;
            width: 100%;
            height: 145px;
            object-fit: contain;
            background: #f2f5f8;
            border-radius: 7px;
        }

        .hpm-current-image small {
            color: #64748b;
            font-size: 12px;
        }

        .hpm-remove-image {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b42318;
            font-size: 13px;
            font-weight: 500;
        }

        .hpm-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
            gap: 14px;
            margin: 16px 0;
        }

        .hpm-gallery-item {
            display: flex;
            flex-direction: column;
            gap: 9px;
            padding: 9px;
            border: 1px solid #e1e7ef;
            border-radius: 10px;
            background: #fff;
        }

        .hpm-gallery-item img {
            width: 100%;
            height: 105px;
            object-fit: contain;
            background: #f2f5f8;
            border-radius: 6px;
        }

        .hpm-gallery-item label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #b42318;
            font-size: 12px;
        }

        .hpm-video-field {
            grid-column: 1 / -1;
        }

        @media (max-width: 700px) {
            .hpm-upload-grid {
                grid-template-columns: 1fr;
            }

            .hpm-upload-section {
                padding: 15px;
            }
        }
    </style>
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
        <a class="logout-link" href="index.php">← Dashboard</a>
    </div>
</header>

<main class="editor-page">
    <section class="editor-card">
        <h1><?= $id ? 'Edit product' : 'Add a product' ?></h1>
        <p class="editor-intro">
            Manage product information, images, gallery and video shown in the public catalogue.
        </p>

        <?php if ($error): ?>
            <div class="editor-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$categories): ?>
            <div class="editor-error">
                Create and activate a product category in the dashboard before adding products.
                <a href="index.php">Return to dashboard</a>.
            </div>
        <?php else: ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="form-grid">
                <label>
                    Product name *
                    <input name="name" required maxlength="190"
                           value="<?= e((string)$p['name']) ?>"
                           placeholder="Enter product name">
                </label>

                <label>
                    URL slug
                    <input name="slug" maxlength="220"
                           value="<?= e((string)$p['slug']) ?>"
                           placeholder="Auto-generated from product name">
                </label>

                <label>
                    Category *
                    <select name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                <?= (int)$p['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Image alt text
                    <input name="alt_text" maxlength="255"
                           value="<?= e((string)$p['alt_text']) ?>"
                           placeholder="Describe the product image">
                </label>

                <label class="full">
                    Short description *
                    <textarea name="short_description" required maxlength="500" rows="3"><?= e((string)$p['short_description']) ?></textarea>
                </label>

                <label class="full">
                    Full product description
                    <textarea name="description" rows="5"><?= e((string)($p['description'] ?? '')) ?></textarea>
                </label>

                <label class="full">
                    Specifications
                    <small>Enter one specification per line.</small>
                    <textarea name="specifications" rows="7"
                              placeholder="Machine Capacity: ...&#10;Power Load: ..."><?= e((string)($p['specifications'] ?? '')) ?></textarea>
                </label>

                <!-- Main image upload -->
                <section class="hpm-upload-section">
                    <h2>Main product image</h2>
                    <p>Upload the primary image customers see first in the product popup. JPG, PNG or WebP; maximum 8 MB.</p>

                    <div class="hpm-upload-grid">
                        <?php if (!empty($p['image'])): ?>
                            <div class="hpm-current-image">
                                <small>Current main product image</small>
                                <img loading="lazy" decoding="async" src="<?= e(hpm_admin_image_url($p['image'])) ?>"
                                     alt="<?= e((string)($p['alt_text'] ?: $p['name'])) ?>">
                            </div>
                        <?php else: ?>
                            <p class="hpm-field-help">No main product image uploaded yet.</p>
                        <?php endif; ?>

                        <label class="hpm-upload-field">
                            Upload / replace main image
                            <input type="file" name="product_image"
                                   accept="image/jpeg,image/png,image/webp">
                            <small class="hpm-field-help">Leave empty to keep the current image.</small>
                        </label>
                    </div>
                </section>

                <!-- Optional Before / After uploads -->
                <section class="hpm-upload-section">
                    <h2>Before and after images</h2>
                    <p>Both images are optional. Upload only the images you want to show. If an image is not uploaded, it will not be shown to customers.</p>

                    <div class="hpm-upload-grid">
                        <div>
                            <label class="hpm-upload-field">
                                Before image
                                <input type="file" name="before_image_file"
                                       accept="image/jpeg,image/png,image/webp">
                                <small class="hpm-field-help">Optional. JPG, PNG or WebP; maximum 8 MB.</small>
                            </label>

                            <?php if (!empty($p['before_image'])): ?>
                                <div class="hpm-current-image" style="margin-top:12px">
                                    <small>Current before image</small>
                                    <img loading="lazy" decoding="async" src="<?= e(hpm_admin_image_url($p['before_image'])) ?>"
                                         alt="Current before image">
                                    <label class="hpm-remove-image">
                                        <input type="checkbox" name="remove_before_image" value="1">
                                        Remove before image
                                    </label>
                                </div>
                            <?php else: ?>
                                <p class="hpm-field-help">No before image uploaded.</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="hpm-upload-field">
                                After image
                                <input type="file" name="after_image_file"
                                       accept="image/jpeg,image/png,image/webp">
                                <small class="hpm-field-help">Optional. JPG, PNG or WebP; maximum 8 MB.</small>
                            </label>

                            <?php if (!empty($p['after_image'])): ?>
                                <div class="hpm-current-image" style="margin-top:12px">
                                    <small>Current after image</small>
                                    <img loading="lazy" decoding="async" src="<?= e(hpm_admin_image_url($p['after_image'])) ?>"
                                         alt="Current after image">
                                    <label class="hpm-remove-image">
                                        <input type="checkbox" name="remove_after_image" value="1">
                                        Remove after image
                                    </label>
                                </div>
                            <?php else: ?>
                                <p class="hpm-field-help">No after image uploaded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Additional gallery uploads -->
                <section class="hpm-upload-section">
                    <h2>Product gallery images</h2>
                    <p>Add additional product images. They will join the main image in the automatic product image slider.</p>

                    <?php if ($gallery): ?>
                        <div class="hpm-gallery-grid">
                            <?php foreach ($gallery as $image): ?>
                                <div class="hpm-gallery-item">
                                    <img loading="lazy" decoding="async" src="<?= e(hpm_admin_image_url($image['image_path'])) ?>"
                                         alt="<?= e($image['alt_text'] ?: $p['name']) ?>">
                                    <label>
                                        <input type="checkbox" name="remove_gallery[]"
                                               value="<?= (int)$image['id'] ?>">
                                        Remove image
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="hpm-field-help">No additional gallery images have been added.</p>
                    <?php endif; ?>

                    <label class="hpm-upload-field">
                        Upload gallery images
                        <input type="file" name="gallery_images[]"
                               accept="image/jpeg,image/png,image/webp" multiple>
                        <small class="hpm-field-help">
                            Select multiple JPG, PNG or WebP images. Maximum 8 MB per image.
                            Existing images remain unless you select them for removal.
                        </small>
                    </label>
                </section>

                <!-- Product video -->
                <label class="hpm-video-field">
                    Product video URL
                    <input type="url" name="video_url" maxlength="1000"
                           value="<?= e((string)($p['video_url'] ?? '')) ?>"
                           placeholder="https://www.youtube.com/watch?v=...">
                    <small>Optional. Paste a YouTube video link (youtube.com/watch, youtu.be or youtube.com/shorts), Vimeo link, or direct MP4/WebM URL. Leave empty to hide the video section.</small>
                </label>

                <label class="check">
                    <input type="checkbox" name="is_featured"
                        <?= !empty($p['is_featured']) ? 'checked' : '' ?>>
                    Feature on catalogue
                </label>

                <label class="check">
                    <input type="checkbox" name="is_active"
                        <?= !empty($p['is_active']) ? 'checked' : '' ?>>
                    Published / visible
                </label>
            </div>

            <div class="editor-actions">
                <button class="primary-btn" type="submit">Save product</button>
                <a class="outline-btn" href="index.php">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/flash-popup.php'; ?>
</body>
</html>
