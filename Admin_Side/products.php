<?php
// Manage menu products, drink size variants, and product images.
session_start();

// Generate a CSRF token for product-management requests.
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminStatusStmt = $conn->prepare("SELECT is_active FROM admins WHERE id = ? LIMIT 1");
$adminId = (int) $_SESSION['admin_id'];
$adminStatusStmt->bind_param('i', $adminId);
$adminStatusStmt->execute();
$adminStatus = $adminStatusStmt->get_result()->fetch_assoc();
$adminStatusStmt->close();

if (!$adminStatus || (int) $adminStatus['is_active'] !== 1) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

$message = '';
$messageType = 'success';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $message, string $type = 'success'): void
{
    $_SESSION['product_message'] = [
        'message' => $message,
        'type' => $type
    ];

    header('Location: products.php');
    exit;
}

// Product images are saved in Customer_Side/images/.
function uploadProductImage(array $file): array
{
    $uploadDirectory = __DIR__ . '/../Customer_Side/images/';

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid image upload.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'The image could not be uploaded. Please try again.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Image size must not exceed 2 MB.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid uploaded file.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        return [
            'success' => false,
            'message' => 'Only JPG, PNG, and WebP images are allowed.'
        ];
    }

    // Confirm the uploaded file is a valid image.
    if (@getimagesize($file['tmp_name']) === false) {
        return ['success' => false, 'message' => 'The uploaded file is not a valid image.'];
    }

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return ['success' => false, 'message' => 'The image folder could not be created.'];
        }
    }

    if (!is_writable($uploadDirectory)) {
        return ['success' => false, 'message' => 'The image folder is not writable.'];
    }

    try {
        $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    } catch (Throwable $exception) {
        return ['success' => false, 'message' => 'Could not prepare the image filename.'];
    }

    $destination = $uploadDirectory . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Could not save the uploaded image.'];
    }

    return [
        'success' => true,
        'path' => 'images/' . $fileName,
        'absolute_path' => $destination
    ];
}

// Display messages after redirect.
if (isset($_SESSION['product_message'])) {
    $message = $_SESSION['product_message']['message'];
    $messageType = $_SESSION['product_message']['type'];
    unset($_SESSION['product_message']);
}

// Categories must match Customer_Side/index.php filters.
$categories = [
    'Ulam',
    'Meryenda / Dessert',
    'Beverage',
    'Milk Tea',
    'Fruity Soda',
    'Yakult Series',
    'Coke Float',
    'Donut',
    'Snacks',
    "Candy's",
    'Biscuits'
];
$legacyCategories = ['Drinks', 'Donuts'];

function displayCategory(string $category): string
{
    $normalizedCategory = strtolower(trim($category));

    if (in_array($normalizedCategory, ['drinks', 'beverage'], true)) {
        return 'Beverage';
    }

    return in_array($normalizedCategory, ['donut', 'donuts'], true)
        ? 'Donut'
        : $category;
}

$filterCategories = array_values(array_unique(array_map(
    'displayCategory',
    array_merge($categories, $legacyCategories)
)));

// Validate and save product additions, edits, and deletions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !isset($_SESSION['admin_csrf_token']) ||
        !hash_equals($_SESSION['admin_csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $id = $id === false || $id === null ? 0 : $id;

        $productCode = strtoupper(trim($_POST['product_code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $priceInput = trim($_POST['price'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $stockInput = trim($_POST['stock'] ?? '');
        $isSizedDrinkAdd = $action === 'add' &&
            in_array($category, ['Milk Tea', 'Fruity Soda', 'Yakult Series', 'Coke Float'], true);
        $variantSizes = [
            '12 oz' => '12oz',
            '16 oz' => '16oz',
            '22 oz' => '22oz'
        ];
        $variantValues = [];

        $errors = [];

        if (!$isSizedDrinkAdd && !preg_match('/^PB-[0-9]{3}$/', $productCode)) {
            $errors[] = 'Product code must follow PB-001 format.';
        }

        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Product name is required and must not exceed 150 characters.';
        }

        if ($isSizedDrinkAdd) {
            foreach ($variantSizes as $sizeLabel => $fieldSuffix) {
                $variantPrice = trim($_POST['price_' . $fieldSuffix] ?? '');
                $variantStock = trim($_POST['stock_' . $fieldSuffix] ?? '');

                if (
                    $variantPrice === '' ||
                    !preg_match('/^(?:\d+)(?:\.\d{1,2})?$/', $variantPrice) ||
                    !is_finite((float) $variantPrice) ||
                    (float) $variantPrice < 0 ||
                    (float) $variantPrice > 99999999.99
                ) {
                    $errors[] = 'Enter a valid price for the ' . $sizeLabel . ' size.';
                }

                if (
                    $variantStock === '' ||
                    !preg_match('/^(0|[1-9][0-9]*)$/', $variantStock) ||
                    filter_var($variantStock, FILTER_VALIDATE_INT) === false ||
                    (int) $variantStock > 2147483647
                ) {
                    $errors[] = 'Enter a valid whole-number stock for the ' . $sizeLabel . ' size.';
                }

                $variantValues[$sizeLabel] = [
                    'price' => (float) $variantPrice,
                    'stock' => (int) $variantStock
                ];
            }
        } elseif (
            $priceInput === '' ||
            !preg_match('/^(?:\d+)(?:\.\d{1,2})?$/', $priceInput) ||
            !is_finite((float) $priceInput) ||
            (float) $priceInput < 0 ||
            (float) $priceInput > 99999999.99
        ) {
            $errors[] = 'Enter a valid price with no more than two decimal places.';
        }

        $isLegacyCategory = $action === 'update' &&
            in_array($category, $legacyCategories, true);

        if (!in_array($category, $categories, true) && !$isLegacyCategory) {
            $errors[] = 'Please select a valid product category.';
        }

        // Stock must be a non-negative whole number.
        if (!$isSizedDrinkAdd && (
            $stockInput === '' ||
            !preg_match('/^(0|[1-9][0-9]*)$/', $stockInput) ||
            filter_var($stockInput, FILTER_VALIDATE_INT) === false ||
            (int) $stockInput > 2147483647
        )) {
            $errors[] = 'Stock must be a non-negative whole number.';
        }

        if ($action === 'update' && $id < 1) {
            $errors[] = 'Invalid product selected for editing.';
        }

        if ($errors) {
            redirectWithMessage(implode(' ', $errors), 'error');
        }

        $price = $isSizedDrinkAdd ? 0 : (float) $priceInput;
        $stock = $isSizedDrinkAdd ? 0 : (int) $stockInput;
        $available = isset($_POST['is_available']) ? 1 : 0;

        $variantCodes = [];
        if ($isSizedDrinkAdd) {
            $usedCodes = [];
            $codeResult = $conn->query(
                "SELECT product_code FROM menu_items
                 WHERE product_code REGEXP '^PB-[0-9]{3}$'"
            );
            if (!$codeResult) {
                redirectWithMessage('Could not assign product codes. Please try again.', 'error');
            }
            while ($codeRow = $codeResult->fetch_assoc()) {
                $usedCodes[$codeRow['product_code']] = true;
            }

            $nextCodeNumber = 1;
            foreach ($variantSizes as $sizeLabel => $fieldSuffix) {
                while (
                    $nextCodeNumber <= 999 &&
                    isset($usedCodes[sprintf('PB-%03d', $nextCodeNumber)])
                ) {
                    $nextCodeNumber++;
                }

                if ($nextCodeNumber > 999) {
                    redirectWithMessage(
                        'No product codes remain. Please review the PB-001 to PB-999 product code range.',
                        'error'
                    );
                }

                $code = sprintf('PB-%03d', $nextCodeNumber++);
                $usedCodes[$code] = true;
                $variantCodes[$sizeLabel] = $code;
            }
        }

        // Check duplicate product codes.
        if ($isSizedDrinkAdd) {
            $duplicate = false;
        } elseif ($action === 'add') {
            $stmt = $conn->prepare(
                'SELECT id FROM menu_items WHERE product_code = ? LIMIT 1'
            );
            $stmt->bind_param('s', $productCode);
        } else {
            $stmt = $conn->prepare(
                'SELECT id FROM menu_items
                 WHERE product_code = ? AND id <> ?
                 LIMIT 1'
            );
            $stmt->bind_param('si', $productCode, $id);
        }

        if (!$isSizedDrinkAdd) {
            $stmt->execute();
            $duplicate = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        if ($duplicate) {
            redirectWithMessage(
                'That product code already exists. Every product must have a unique code.',
                'error'
            );
        }

        // Get the existing image path when editing.
        $imagePath = '';

        if ($action === 'update') {
            $stmt = $conn->prepare(
                'SELECT image_url, variant_group
                 FROM menu_items WHERE id = ? LIMIT 1'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existingProduct = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existingProduct) {
                redirectWithMessage('Product not found.', 'error');
            }

            $imagePath = $existingProduct['image_url'] ?? '';
        }

        // Upload a new image only if the admin selected one.
        $uploadedImage = null;

        if (
            isset($_FILES['product_image']) &&
            $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $uploadedImage = uploadProductImage($_FILES['product_image']);

            if (!$uploadedImage['success']) {
                redirectWithMessage($uploadedImage['message'], 'error');
            }

            $imagePath = $uploadedImage['path'];
        }

        if ($isSizedDrinkAdd) {
            $variantGroup = bin2hex(random_bytes(16));
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                'INSERT INTO menu_items
                    (product_code, name, price, category, variant_group,
                     size_label, image_url, stock, is_available)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmt) {
                $conn->rollback();
                if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                    @unlink($uploadedImage['absolute_path']);
                }
                redirectWithMessage('Could not prepare the drink sizes. Please try again.', 'error');
            }

            $insertFailed = false;
            foreach ($variantSizes as $sizeLabel => $fieldSuffix) {
                $variantCode = $variantCodes[$sizeLabel];
                $variantPrice = $variantValues[$sizeLabel]['price'];
                $variantStock = $variantValues[$sizeLabel]['stock'];
                $stmt->bind_param(
                    'ssdssssii',
                    $variantCode,
                    $name,
                    $variantPrice,
                    $category,
                    $variantGroup,
                    $sizeLabel,
                    $imagePath,
                    $variantStock,
                    $available
                );

                if (!$stmt->execute()) {
                    $insertFailed = true;
                    break;
                }
            }

            $errorCode = $stmt->errno;
            $stmt->close();

            if ($insertFailed || !$conn->commit()) {
                $conn->rollback();
                if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                    @unlink($uploadedImage['absolute_path']);
                }
                redirectWithMessage(
                    $errorCode === 1062
                        ? 'A product code was taken while saving. Please try again.'
                        : 'Could not add all drink sizes. No variants were saved; please try again.',
                    'error'
                );
            }

            redirectWithMessage(ucfirst($category) . ' flavor added with 12 oz, 16 oz, and 22 oz sizes.');
        }

        if ($action === 'add') {
            $stmt = $conn->prepare(
                'INSERT INTO menu_items
                    (product_code, name, price, category, image_url, stock, is_available)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'ssdssii',
                $productCode,
                $name,
                $price,
                $category,
                $imagePath,
                $stock,
                $available
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectWithMessage('Product added successfully.');
            }

            $errorCode = $stmt->errno;
            $stmt->close();

            // Remove the newly uploaded file if saving the product failed.
            if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                @unlink($uploadedImage['absolute_path']);
            }

            redirectWithMessage(
                $errorCode === 1062
                    ? 'That product code already exists.'
                    : 'Could not add the product. Please try again.',
                'error'
            );
        }

        // Update an existing product.
        $isGroupedVariant = $action === 'update' &&
            !empty($existingProduct['variant_group']);

        if ($isGroupedVariant) {
            $conn->begin_transaction();
            $groupStmt = $conn->prepare(
                'UPDATE menu_items
                 SET name = ?, category = ?, image_url = ?, is_available = ?
                 WHERE variant_group = ?'
            );

            if (!$groupStmt) {
                $conn->rollback();
                if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                    @unlink($uploadedImage['absolute_path']);
                }
                redirectWithMessage('Could not prepare the milk tea update. Please try again.', 'error');
            }

            $variantGroup = $existingProduct['variant_group'];
            $groupStmt->bind_param(
                'sssis',
                $name,
                $category,
                $imagePath,
                $available,
                $variantGroup
            );

            if (!$groupStmt->execute()) {
                $groupStmt->close();
                $conn->rollback();
                if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                    @unlink($uploadedImage['absolute_path']);
                }
                redirectWithMessage('Could not update the milk tea flavor. Please try again.', 'error');
            }
            $groupStmt->close();
        }

        $stmt = $conn->prepare(
            'UPDATE menu_items
             SET product_code = ?, name = ?, price = ?,
                 category = ?, image_url = ?, stock = ?,
                 is_available = ?
             WHERE id = ?'
        );

        $stmt->bind_param(
            'ssdssiii',
            $productCode,
            $name,
            $price,
            $category,
            $imagePath,
            $stock,
            $available,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            if ($isGroupedVariant && !$conn->commit()) {
                $conn->rollback();
                if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
                    @unlink($uploadedImage['absolute_path']);
                }
                redirectWithMessage('Could not save the milk tea update. Please try again.', 'error');
            }
            redirectWithMessage('Product updated successfully.');
        }

        $errorCode = $stmt->errno;
        $stmt->close();
        if ($isGroupedVariant) {
            $conn->rollback();
        }

        if ($uploadedImage && isset($uploadedImage['absolute_path'])) {
            @unlink($uploadedImage['absolute_path']);
        }

        redirectWithMessage(
            $errorCode === 1062
                ? 'That product code already exists.'
                : 'Could not update the product. Please try again.',
            'error'
        );
    }

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null || $id < 1) {
            redirectWithMessage('Invalid product selected.', 'error');
        }

        $stmt = $conn->prepare('DELETE FROM menu_items WHERE id = ?');
        $stmt->bind_param('i', $id);

        if ($stmt->execute() && $stmt->affected_rows === 1) {
            $stmt->close();
            redirectWithMessage('Product deleted successfully.');
        }

        $errorCode = $stmt->errno;
        $stmt->close();

        redirectWithMessage(
            $errorCode === 1451
                ? 'This product is linked to existing records and cannot be deleted.'
                : 'Could not delete the product. It may no longer exist.',
            'error'
        );
    }

    redirectWithMessage('Invalid action.', 'error');
}

// Load a selected product so the form can edit its saved values.
$editProduct = null;

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId === false || $editId === null || $editId < 1) {
        redirectWithMessage('Invalid product selected.', 'error');
    }

    $stmt = $conn->prepare(
        'SELECT id, product_code, name, price, category,
                variant_group, size_label, image_url, stock, is_available
         FROM menu_items
         WHERE id = ?'
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();

    $editProduct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editProduct) {
        redirectWithMessage('Product not found.', 'error');
    }
}

// Load all products; the shared browser script filters them while typing.
$search = trim($_GET['search'] ?? '');

if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$products = $conn->query(
    'SELECT id, product_code, name, price, category,
            variant_group, size_label, image_url, stock, is_available
     FROM menu_items
     ORDER BY id DESC'
);

$isEditing = is_array($editProduct);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu | Purple Blend</title>

    <style>
        :root {
            --purple: #6F2B91;
            --dark-purple: #4E176B;
            --light-purple: #F7F0FB;
            --background: #FCFAFD;
            --text: #30243A;
            --muted: #81758A;
            --border: #EEE7F2;
            --white: #FFFFFF;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background: var(--background);
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 245px;
            padding: 28px 18px;
            display: flex;
            flex-direction: column;
            background: #441D56;
            color: white;
        }

        .brand {
            display: block;
            width: calc(100% + 36px);
            margin: -28px -18px 35px;
        }

        .brand-logo {
            display: block;
            width: 100%;
            height: auto;
        }

        .nav-link {
            display: block;
            margin-bottom: 6px;
            padding: 12px 13px;
            border-radius: 8px;
            color: #F2E9F7;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--purple);
            color: white;
        }

        .main {
            margin-left: 245px;
            padding: 34px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -.8px;
        }

        .page-header p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            background: var(--purple);
            color: white;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover { background: var(--dark-purple); }

        .panel {
            margin-bottom: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
        }

        .product-form-panel {
            margin: 0;
        }

        .product-modal {
            width: min(680px, calc(100% - 32px));
            max-width: none;
            max-height: min(88vh, 820px);
            margin: auto;
            padding: 0;
            overflow: auto;
            border: 0;
            border-radius: 14px;
            background: transparent;
            color: inherit;
        }

        .product-modal::backdrop {
            background: rgba(32, 18, 38, 0.58);
            backdrop-filter: blur(3px);
        }

        .modal-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .modal-heading h2 {
            margin: 0 0 7px;
            font-size: 20px;
        }

        .modal-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .modal-close {
            display: grid;
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: white;
            color: var(--muted);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .modal-close:hover {
            border-color: var(--purple);
            color: var(--purple);
        }

        .panel h2 {
            margin: 0 0 20px;
            font-size: 18px;
        }

        .message {
            margin-bottom: 22px;
            padding: 13px 15px;
            border-radius: 8px;
            font-size: 13px;
        }

        .message.success {
            background: #E6F6F0;
            color: #147456;
        }

        .message.error {
            background: #FFF0F1;
            color: #A32032;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .variant-fields {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #FCFAFD;
        }

        .variant-fields[hidden] {
            display: none;
        }

        .variant-fields legend {
            padding: 0 6px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .variant-size {
            display: grid;
            gap: 10px;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
        }

        .variant-size h3 {
            margin: 0;
            color: var(--purple);
            font-size: 14px;
        }

        .variant-size .field label {
            margin-bottom: 5px;
            font-size: 12px;
        }

        .variant-help {
            grid-column: 1 / -1;
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 43px;
            padding: 10px 12px;
            border: 1px solid #DED6E4;
            border-radius: 8px;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 13px;
        }

        .field input:focus,
        .field select:focus {
            outline: 2px solid #6F2B9125;
            border-color: var(--purple);
        }

        .field small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
        }

        .image-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .image-preview img,
        .product-thumbnail {
            width: 58px;
            height: 58px;
            border: 1px solid var(--border);
            border-radius: 8px;
            object-fit: cover;
            background: var(--light-purple);
        }

        .image-preview span {
            color: var(--muted);
            font-size: 12px;
        }

        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
        }

        .checkbox-field input {
            width: 16px;
            height: 16px;
            accent-color: var(--purple);
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 22px;
        }

        .cancel {
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-form input {
            flex: 1;
            min-width: 0;
            padding: 12px;
            border: 1px solid #DED6E4;
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
        }

        .product-category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: -4px 0 20px;
        }

        .product-category-filter {
            min-height: 36px;
            padding: 8px 13px;
            border: 1px solid #E5D9EA;
            border-radius: 999px;
            background: #FFFFFF;
            color: var(--dark-purple);
            font: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease,
                color 0.18s ease;
        }

        .product-category-filter:hover {
            border-color: var(--purple);
            background: var(--light-purple);
        }

        .product-category-filter.active {
            border-color: var(--purple);
            background: var(--purple);
            color: #FFFFFF;
        }

        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            white-space: nowrap;
        }

        th {
            padding: 14px;
            background: #FDFBFE;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
        }

        td {
            padding: 14px;
            border-top: 1px solid #F2EDF5;
            font-size: 13px;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-name { font-weight: 700; }

        .product-code {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status.available {
            background: #E6F6F0;
            color: #147456;
        }

        .status.unavailable {
            background: #FFF0F1;
            color: #B42332;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .edit-link {
            color: var(--purple);
            font-weight: 700;
            text-decoration: none;
        }

        .delete-button {
            padding: 0;
            border: none;
            background: none;
            color: #B42332;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .empty-state {
            padding: 35px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .form-note {
            margin: -4px 0 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                padding: 20px;
            }

            .brand {
                width: calc(100% + 40px);
                margin: -20px -20px 20px;
            }

            .main {
                margin-left: 0;
                padding: 22px 16px;
            }
        }

        @media (max-width: 560px) {
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid { grid-template-columns: 1fr; }
            .variant-fields { grid-template-columns: 1fr; }
            .panel { padding: 18px; }
            .search-form { flex-wrap: wrap; }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-navigation.css?v=1">
    <script src="js/live-search.js?v=3" defer></script>
    <script src="js/mobile-navigation.js?v=1" defer></script>
</head>

<body>

<aside class="sidebar">
    <a class="brand" href="dashboard.php" aria-label="Purple Blend admin dashboard">
        <img class="brand-logo" src="../Images/purple-blend-logo.jpg" alt="Purple Blend">
    </a>

    <nav>
        <a class="nav-link" href="dashboard.php">Home</a>
        <a class="nav-link active" href="products.php">Menu</a>
        <a class="nav-link" href="inventory.php">Stock</a>
        <a class="nav-link" href="orders.php">Orders</a>
        <a class="nav-link" href="customers.php">Customers</a>
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

<main class="main">
    <header class="page-header">
        <div>
            <h1>Menu</h1>
            <p>Add, update, or remove items customers can order.</p>
        </div>
        <?php if (!$isEditing): ?>
            <button class="button" type="button" id="open-product-modal">
                Add Menu Item
            </button>
        <?php endif; ?>
    </header>

    <?php if ($message !== ''): ?>
        <div class="message <?= e($messageType) ?>" role="alert">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!$isEditing): ?>
        <dialog class="product-modal" id="add-product-modal" aria-labelledby="product-modal-title">
    <?php endif; ?>

    <section class="panel product-form-panel" id="product-form">
        <?php if ($isEditing): ?>
            <h2>Edit Menu Item</h2>
        <?php else: ?>
            <div class="modal-heading">
                <div>
                    <h2 id="product-modal-title">Add Menu Item</h2>
                    <p>Enter the item name, price, and starting stock.</p>
                </div>
                <button class="modal-close" type="button" id="close-product-modal" aria-label="Close add product form">
                    &times;
                </button>
            </div>
        <?php endif; ?>
        <?php if ($isEditing && !empty($editProduct['size_label'])): ?>
            <p class="form-note">
                Editing the <?= e($editProduct['size_label']) ?> size.
                Its price and stock are separate. The name and image are shared with other sizes.
            </p>
        <?php endif; ?>

        <form method="POST"
              enctype="multipart/form-data"
              action="products.php<?= $isEditing ? '?edit=' . (int) $editProduct['id'] : '' ?>">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['admin_csrf_token']) ?>">
            <input type="hidden" name="action"
                   value="<?= $isEditing ? 'update' : 'add' ?>">

            <?php if ($isEditing): ?>
                <input type="hidden" name="id"
                       value="<?= (int) $editProduct['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="field" id="single-code-field">
                    <label for="product_code">Item Code</label>
                    <input type="text"
                           id="product_code"
                           name="product_code"
                           maxlength="6"
                           pattern="PB-[0-9]{3}"
                           title="Use the format PB-001."
                           value="<?= e($editProduct['product_code'] ?? '') ?>"
                           placeholder="PB-001"
                           required>
                    <small>Use the next code in order, for example PB-001.</small>
                </div>

                <div class="field">
                    <label for="name">Product Name</label>
                    <input type="text"
                           id="name"
                           name="name"
                           maxlength="150"
                           value="<?= e($editProduct['name'] ?? '') ?>"
                           placeholder="Enter product name"
                           required>
                </div>

                <div class="field" id="single-price-field">
                    <label for="price">Price (₱)</label>
                    <input type="number"
                           id="price"
                           name="price"
                           min="0"
                           max="99999999.99"
                           step="0.01"
                           value="<?= e($editProduct['price'] ?? '') ?>"
                           placeholder="0.00"
                           required>
                </div>

                <fieldset class="variant-fields" id="sized-drink-variants" hidden>
                    <legend>Drink Sizes</legend>
                    <p class="variant-help">
                        Add the flavor once. Each size gets its own price, stock, and automatically assigned product code.
                    </p>

                    <?php foreach (['12 oz' => '12oz', '16 oz' => '16oz', '22 oz' => '22oz'] as $sizeLabel => $fieldSuffix): ?>
                        <div class="variant-size">
                            <h3><?= e($sizeLabel) ?></h3>
                            <div class="field">
                                <label for="price_<?= e($fieldSuffix) ?>">Price (₱)</label>
                                <input
                                    type="number"
                                    id="price_<?= e($fieldSuffix) ?>"
                                    name="price_<?= e($fieldSuffix) ?>"
                                    min="0"
                                    max="99999999.99"
                                    step="0.01"
                                    data-variant-required
                                    disabled
                                >
                            </div>
                            <div class="field">
                                <label for="stock_<?= e($fieldSuffix) ?>">Pieces Available</label>
                                <input
                                    type="number"
                                    id="stock_<?= e($fieldSuffix) ?>"
                                    name="stock_<?= e($fieldSuffix) ?>"
                                    min="0"
                                    max="2147483647"
                                    step="1"
                                    data-variant-required
                                    disabled
                                >
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="field">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>

                        <?php $currentCategory = $editProduct['category'] ?? ''; ?>

                        <?php foreach ($categories as $categoryOption): ?>
                            <option value="<?= e($categoryOption) ?>"
                                <?= $currentCategory === $categoryOption ? 'selected' : '' ?>>
                                <?= e($categoryOption) ?>
                            </option>
                        <?php endforeach; ?>

                        <?php if (in_array($currentCategory, $legacyCategories, true)): ?>
                            <option value="<?= e($currentCategory) ?>" selected>
                                <?= e(displayCategory($currentCategory)) ?>
                                (existing<?= $currentCategory === 'Drinks' ? ': Drinks' : ' category' ?>)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field" id="single-stock-field">
                    <label for="stock">Pieces Available</label>
                    <input type="number"
                           id="stock"
                           name="stock"
                           min="0"
                           max="2147483647"
                           step="1"
                           value="<?= e($editProduct['stock'] ?? '0') ?>"
                           required>
                </div>

                <div class="field">
                    <label for="product_image">Product Image (optional)</label>
                    <input type="file"
                           id="product_image"
                           name="product_image"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small>Choose JPG, PNG, or WebP. Maximum file size: 2 MB.</small>

                    <?php if ($isEditing && !empty($editProduct['image_url'])): ?>
                        <div class="image-preview">
                            <img src="../Customer_Side/<?= e($editProduct['image_url']) ?>"
                                 alt="Current product image">
                            <span>Current image. Leave the field empty to keep it.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <label class="checkbox-field">
                    <input type="checkbox"
                           name="is_available"
                           value="1"
                        <?= !isset($editProduct) || (int) $editProduct['is_available'] === 1 ? 'checked' : '' ?>>
                    Show this item to customers
                </label>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">
                    <?= $isEditing ? 'Save Changes' : 'Add Menu Item' ?>
                </button>

                <?php if ($isEditing): ?>
                    <a class="cancel" href="products.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if (!$isEditing): ?>
        </dialog>
    <?php endif; ?>

    <section class="panel">
        <h2>Menu Items</h2>

        <form method="GET" class="search-form" data-admin-live-search>
            <input type="search"
                   name="search"
                   maxlength="100"
                   data-admin-live-search-input
                   placeholder="Find an item by name or category..."
                   value="<?= e($search) ?>">

            <button class="button" type="submit">Find Item</button>

            <?php if ($search !== ''): ?>
                <a class="button" href="products.php">Clear</a>
            <?php endif; ?>
        </form>

        <div class="product-category-filters"
             role="group"
             aria-label="Filter products by category"
             data-admin-category-filters>
            <button type="button"
                    class="product-category-filter active"
                    data-admin-category-filter="All"
                    aria-pressed="true">
                All
            </button>
            <?php foreach ($filterCategories as $filterCategory): ?>
                <button type="button"
                        class="product-category-filter"
                        data-admin-category-filter="<?= e($filterCategory) ?>"
                        aria-pressed="false">
                    <?= e($filterCategory) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if ($products && $products->num_rows > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Menu Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Pieces Left</th>
                            <th>Shown to Customers</th>
                            <th>Options</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr data-admin-search-row="<?= e(
                            $product['name'] . ' ' .
                            $product['product_code'] . ' ' .
                            ($product['size_label'] ?? '') . ' ' .
                            displayCategory($product['category']) . ' ' .
                            $product['category']
                        ) ?>"
                            data-admin-category="<?= e($product['category']) ?>">
                            <td>
                                <div class="product-cell">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img class="product-thumbnail"
                                             src="../Customer_Side/<?= e($product['image_url']) ?>"
                                             alt="<?= e($product['name']) ?>">
                                    <?php else: ?>
                                        <div class="product-thumbnail"></div>
                                    <?php endif; ?>

                                    <div>
                                        <span class="product-name">
                                            <?= e($product['name']) ?>
                                        </span>
                                        <span class="product-code">
                                            <?= e($product['product_code']) ?>
                                            <?= !empty($product['size_label']) ? ' · ' . e($product['size_label']) : '' ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td><?= e(displayCategory($product['category'])) ?></td>
                            <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                            <td><?= (int) $product['stock'] ?></td>

                            <td>
                                <?php if ((int) $product['is_available'] === 1): ?>
                                    <span class="status available">Yes</span>
                                <?php else: ?>
                                    <span class="status unavailable">No</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="actions">
                                    <a class="edit-link"
                                       href="products.php?edit=<?= (int) $product['id'] ?>#product-form">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          action="products.php"
                                          onsubmit="return confirm('Remove this item from the menu? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['admin_csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="delete-button">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                No menu items found. Add an item using the button above.
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
    const categorySelect = document.getElementById("category");
    const productAction = document.querySelector('input[name="action"]').value;
    const variantFields = document.getElementById("sized-drink-variants");
    const addProductModal = document.getElementById("add-product-modal");
    const openProductModal = document.getElementById("open-product-modal");
    const closeProductModal = document.getElementById("close-product-modal");
    const singleFields = [
        document.getElementById("single-code-field"),
        document.getElementById("single-price-field"),
        document.getElementById("single-stock-field")
    ];

    function updateSizedDrinkFields() {
        const isSizedDrinkAdd =
            productAction === "add" &&
            ["Milk Tea", "Fruity Soda", "Yakult Series", "Coke Float"].includes(categorySelect.value);

        variantFields.hidden = !isSizedDrinkAdd;
        variantFields.querySelectorAll("[data-variant-required]").forEach(input => {
            input.disabled = !isSizedDrinkAdd;
            input.required = isSizedDrinkAdd;
        });

        singleFields.forEach(field => {
            field.hidden = isSizedDrinkAdd;
            const input = field.querySelector("input");
            input.required = !isSizedDrinkAdd;
            input.disabled = isSizedDrinkAdd;
        });
    }

    categorySelect.addEventListener("change", updateSizedDrinkFields);
    updateSizedDrinkFields();

    if (addProductModal) {
        openProductModal.addEventListener("click", () => addProductModal.showModal());
        closeProductModal.addEventListener("click", () => addProductModal.close());

        addProductModal.addEventListener("click", event => {
            if (event.target === addProductModal) {
                addProductModal.close();
            }
        });

        <?php if ($messageType === 'error'): ?>
            addProductModal.showModal();
        <?php endif; ?>
    }
</script>

</body>
</html>