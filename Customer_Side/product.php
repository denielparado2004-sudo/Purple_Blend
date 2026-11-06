<?php
// Load one available product, its options, and related menu items.
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/includes/product_options.php';

$id = trim((string) ($_GET['id'] ?? ''));
$isBuyNow = ($_GET['action'] ?? '') === 'buy';
$product = null;
$relatedProducts = [];
$sizeVariants = [];

// Find the selected active menu item using its database product code.
if ($id !== '') {
    $stmt = $conn->prepare(
        'SELECT product_code, name, price, category, variant_group,
                size_label, image_url, stock
         FROM menu_items
         WHERE product_code = ? AND is_available = 1
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

// Keep the related-items section database-backed as well.
if ($product) {
    if (!empty($product['variant_group'])) {
        $variantStmt = $conn->prepare(
            "SELECT product_code, size_label, price, stock
             FROM menu_items
             WHERE variant_group = ? AND is_available = 1
             ORDER BY FIELD(size_label, '12 oz', '16 oz', '22 oz')"
        );

        if ($variantStmt) {
            $variantStmt->bind_param('s', $product['variant_group']);
            $variantStmt->execute();
            $variantResult = $variantStmt->get_result();

            while ($variant = $variantResult->fetch_assoc()) {
                $sizeVariants[] = $variant;
            }
            $variantStmt->close();
        }
    }

    $relatedStmt = $conn->prepare(
        'SELECT product_code, name, price, category, image_url, stock
         FROM menu_items
         WHERE is_available = 1
           AND (
                category = ?
                OR (
                    LOWER(TRIM(category)) IN (\'drinks\', \'beverage\')
                    AND LOWER(TRIM(?)) IN (\'drinks\', \'beverage\')
                )
           )
           AND product_code <> ?
           AND (variant_group IS NULL OR variant_group <> ?)
         ORDER BY id DESC
         LIMIT 6'
    );

    if ($relatedStmt) {
        $variantGroup = $product['variant_group'] ?? '';
        $relatedStmt->bind_param(
            'ssss',
            $product['category'],
            $product['category'],
            $product['product_code'],
            $variantGroup
        );
        $relatedStmt->execute();
        $relatedResult = $relatedStmt->get_result();

        if ($relatedResult) {
            while ($row = $relatedResult->fetch_assoc()) {
                $relatedProducts[] = $row;
            }
        }
        $relatedStmt->close();
    }
}

$productName = $product['name'] ?? '';
$riceOptions = $product ? riceOptionsForProduct($productName) : [];
$hasRiceOptions = !empty($riceOptions);

$cartProducts = [];
$cartProductsResult = $conn->query(
    'SELECT product_code, name, price,
            CASE WHEN is_available = 1 THEN stock ELSE 0 END AS stock
     FROM menu_items'
);
if (!$cartProductsResult) {
    error_log('Purple Blend could not load product-page inventory: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load current inventory. Please refresh the page.');
}
while ($cartProduct = $cartProductsResult->fetch_assoc()) {
    $cartProducts[] = [
        'id' => $cartProduct['product_code'],
        'name' => $cartProduct['name'],
        'price' => (float) $cartProduct['price'],
        'stock' => (int) $cartProduct['stock']
    ];
}
$cartProductsResult->free();

$baseName = $productName;
if ($hasRiceOptions) {
    $baseName = preg_replace('/\s+with\s+rice$/i', '', $baseName);
    $baseName = preg_replace('/\s+without\s+rice$/i', '', $baseName);
}

// Use database-provided image paths (usually relative to Customer_Side).
$image = trim((string) ($product['image_url'] ?? ''));
if ($image === '') {
    $image = 'images/food-placeholder.png';
}

function productImagePath(string $image): string
{
    // Allow complete image URLs; local image paths are used as stored.
    return $image;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function displayProductCategory(string $category): string
{
    return in_array(strtolower(trim($category)), ['drinks', 'beverage'], true)
        ? 'Beverage'
        : $category;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? e($product['name'] . (!empty($product['size_label']) ? ' ' . $product['size_label'] : '')) : 'Product Not Found' ?> | Purple Blend</title>
    <link rel="stylesheet" href="css/index.css?v=55">
    <link rel="stylesheet" href="css/product.css?v=3">
    <script>
        window.purpleBlendProducts = <?= json_encode(
            $cartProducts,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>;
    </script>
    <script src="js/product.js?v=10" defer></script>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="overlay" id="cartOverlay"></div>
<?php include __DIR__ . '/includes/cart_panel.php'; ?>

<main class="product-page">
<?php if ($product): ?>
    <div class="product-layout">
        <section class="product-details">
            <div class="product-image-container">
                <img src="<?= e(productImagePath($image)) ?>" alt="<?= e($product['name']) ?>" class="product-image">
            </div>

            <div class="product-information">
                <p class="product-category"><?= e(displayProductCategory($product['category'])) ?></p>
                <h1><?= e($product['name']) ?></h1>
                <p class="product-price" id="product-price">₱<?= number_format((float) $product['price'], 2) ?></p>
                <p class="product-description">
                    <?= e('A freshly prepared ' . strtolower($product['name']) . ' available from Purple Blend.') ?>
                </p>

                <?php if ($sizeVariants): ?>
                    <div class="size-variant-field">
                        <label for="size-variant-select">Choose size</label>
                        <select id="size-variant-select">
                            <?php foreach ($sizeVariants as $variant): ?>
                                <option
                                    value="<?= e($variant['product_code']) ?>"
                                    data-size="<?= e($variant['size_label']) ?>"
                                    data-price="<?= e($variant['price']) ?>"
                                    data-stock="<?= (int) $variant['stock'] ?>"
                                    <?= $variant['product_code'] === $product['product_code'] ? 'selected' : '' ?>
                                >
                                    <?= e($variant['size_label']) ?>
                                    <?= (int) $variant['stock'] < 1 ? '(Out of stock)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($hasRiceOptions): ?>
                    <fieldset class="rice-options">
                        <legend>Rice Option</legend>
                        <?php foreach ($riceOptions as $option => $details): ?>
                            <label>
                                <input
                                    type="radio"
                                    name="rice-option"
                                    value="<?= e($option) ?>"
                                    data-price="<?= e($details['price']) ?>"
                                    <?= $option === 'with' ? 'checked' : '' ?>
                                >
                                <span class="rice-option-copy">
                                    <strong><?= e($details['label']) ?></strong>
                                    <span><?= $option === 'with' ? 'Complete meal' : 'Pastil/adobo only' ?></span>
                                </span>
                                <span class="rice-option-price">₱<?= number_format($details['price'], 2) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <p class="stock-label">
                    <span id="product-stock-label">
                    <?= (int) $product['stock'] > 0
                        ? 'In stock: ' . (int) $product['stock']
                        : 'Currently out of stock' ?>
                    </span>
                </p>

                <div class="quantity-section">
                    <label for="quantity">Quantity</label>
                    <div class="quantity-control">
                        <button type="button" id="decrease-quantity" aria-label="Decrease quantity">−</button>
                        <input type="number" id="quantity" value="1" min="1" max="<?= max(1, min(99, (int) $product['stock'])) ?>">
                        <button type="button" id="increase-quantity" aria-label="Increase quantity">+</button>
                    </div>
                </div>

                <p class="subtotal">Subtotal: <strong id="subtotal">₱<?= number_format((float) $product['price'], 2) ?></strong></p>

                <button
                    type="button"
                    id="add-to-cart"
                    class="add-to-cart-button"
                    data-id="<?= e($product['product_code']) ?>"
                    data-name="<?= e($baseName) ?>"
                    data-price="<?= e($product['price']) ?>"
                    data-size="<?= e($product['size_label'] ?? '') ?>"
                    data-image="<?= e($image) ?>"
                    data-stock="<?= (int) $product['stock'] ?>"
                    data-buy-now="<?= $isBuyNow ? 'true' : 'false' ?>"
                    <?= $hasRiceOptions ? 'data-rice-option="with"' : '' ?>
                    <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>
                ><?= (int) $product['stock'] < 1
                    ? 'Out of Stock'
                    : ($isBuyNow ? 'Continue to Checkout' : 'Add to Cart') ?></button>
                <p id="cart-message" class="cart-message" aria-live="polite"></p>
            </div>
        </section>

        <aside class="related-products">
            <h2>You Might Also Like</h2>
            <p class="related-subtitle">More from <?= e(displayProductCategory($product['category'])) ?></p>

            <?php if ($relatedProducts): ?>
                <div class="related-list">
                    <?php foreach ($relatedProducts as $item): ?>
                        <?php $relatedImage = trim((string) ($item['image_url'] ?? '')); ?>
                        <article class="related-card">
                            <a class="related-image-link" href="product.php?id=<?= rawurlencode($item['product_code']) ?>">
                                <?php if ($relatedImage !== ''): ?>
                                    <img src="<?= e($relatedImage) ?>" alt="<?= e($item['name']) ?>" class="related-image" loading="lazy">
                                <?php endif; ?>
                            </a>
                            <div class="related-info">
                                <a class="related-name" href="product.php?id=<?= rawurlencode($item['product_code']) ?>"><?= e($item['name']) ?></a>
                                <p class="related-price">₱<?= number_format((float) $item['price'], 2) ?></p>
                                <a href="product.php?id=<?= rawurlencode($item['product_code']) ?>" class="view-product-button">View Product <span>→</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-related">No other items in this category yet.</p>
            <?php endif; ?>
        </aside>
    </div>
<?php else: ?>
    <section class="product-not-found">
        <h1>Product Not Found</h1>
        <p>This food item doesn't exist, is unavailable, or the link is invalid.</p>
        <a href="index.php#menu" class="back-menu">Return to Menu</a>
    </section>
<?php endif; ?>
</main>

<footer class="product-footer" id="footer">
    <p>© <?= date('Y') ?> Purple Blend Campus Canteen</p>
</footer>
</body>
</html>
