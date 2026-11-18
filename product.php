<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    abort_request(
        400,
        'Invalid product ID.'
    );
}

$product = get_product_by_id(
    $pdo,
    $id
);

if (!$product) {
    abort_request(
        404,
        'Product not found.'
    );
}

$stockStatus = get_stock_status(
    (int) $product['stock']
);

$flash = flash_get();

$pageTitle = $product['name'];

$stock = (int) $product['stock'];
$isOnSale = product_is_on_sale($product);
$isAdmin = admin_is_logged_in();

require __DIR__ . '/includes/header.php';
?>

<section class="product-page">

    <a class="back-link" href="<?= url('index.php') ?>#catalog">← Back to products</a>

    <?php if ($flash): ?>
        <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>" role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="product-detail">

        <div class="product-detail-image">
            <img
                src="<?= htmlspecialchars(product_image_url($product['image_url'])) ?>"
                alt="<?= htmlspecialchars($product['name']) ?>">
        </div>

        <div class="product-detail-content">

            <p class="product-card-meta">
                <?php if (!empty($product['category_name'])): ?>
                    <span class="product-category"><?= htmlspecialchars($product['category_name']) ?></span>
                <?php endif; ?>

                <?php if ($isOnSale && (int) $product['featured'] === 1): ?>
                    <span class="featured-label">Featured</span>
                <?php endif; ?>
            </p>

            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <p class="product-detail-text"><?= htmlspecialchars($product['description']) ?></p>

            <?php if (!$isOnSale): ?>
                <p class="flash flash-warning" role="status">
                    <?php if ($isAdmin): ?>
                        Inactive: hidden from customers and cannot be bought.
                        Past orders are not affected.
                    <?php else: ?>
                        This product is no longer available.
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($isOnSale || $isAdmin): ?>
                <p class="product-price product-price-large"><?= format_pence(price_to_pence($product['price'])) ?></p>

                <p class="stock <?= htmlspecialchars($stockStatus['class']) ?>"><?= htmlspecialchars(stock_summary($stock)) ?></p>
            <?php endif; ?>

            <?php if (!$isOnSale): ?>
                <a href="<?= url('index.php') ?>#catalog" class="button-secondary product-browse-link">Browse current products</a>
            <?php elseif (!$isAdmin && $stock > 0): ?>
                <form class="product-buy-form" action="<?= url('add-to-cart.php') ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="<?= $stock ?>" value="1">
                    </div>

                    <button type="submit" class="button-primary">Add to Cart</button>
                </form>
            <?php elseif (!$isAdmin): ?>
                <button type="button" class="button-primary" disabled>Out of stock</button>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <div class="product-admin-actions">
                    <a href="<?= url('edit.php') ?>?id=<?= (int) $product['id'] ?>" class="button-secondary">Edit Product</a>

                    <form
                        method="post"
                        action="<?= url('delete.php') ?>"
                        data-confirm="Are you sure you want to delete this product?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                        <button type="submit" class="button-danger">Delete Product</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
