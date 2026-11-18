<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

// Administrators cannot use the cart (see require_shopper()).
require_shopper();

$pdo = get_db();

/*
|--------------------------------------------------------------------------
| ONLY ALLOW POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_request(405, 'Method not allowed.');
}

/*
|--------------------------------------------------------------------------
| CSRF VALIDATION
|--------------------------------------------------------------------------
*/

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    abort_request(403, 'Invalid CSRF token.');
}

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId = filter_input(
    INPUT_POST,
    'product_id',
    FILTER_VALIDATE_INT
);

if (!$productId || $productId <= 0) {
    abort_request(400, 'Invalid product.');
}

/*
|--------------------------------------------------------------------------
| QUANTITY
|--------------------------------------------------------------------------
*/

$quantity = filter_input(
    INPUT_POST,
    'quantity',
    FILTER_VALIDATE_INT
);

if ($quantity === false || $quantity === null || $quantity < 0) {
    flash_set('Please enter a valid quantity.', 'error');

    header('Location: ' . url('cart.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK CART
|--------------------------------------------------------------------------
*/

$cart = cart_items();

if (!isset($cart[$productId])) {
    flash_set('Product not found in your cart.', 'error');

    header('Location: ' . url('cart.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| REMOVE IF QUANTITY IS ZERO
|--------------------------------------------------------------------------
*/

if ($quantity === 0) {
    cart_update($productId, 0);

    flash_set('Product removed from your cart.');

    header('Location: ' . url('cart.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK PRODUCT AND STOCK
|--------------------------------------------------------------------------
*/

$product = get_product_by_id($pdo, $productId);

if (!$product || !product_is_on_sale($product)) {
    flash_set('This product is no longer available. Please remove it from your cart.', 'error');

    header('Location: ' . url('cart.php'));
    exit;
}

$stock = (int) $product['stock'];

if ($quantity > $stock) {
    flash_set(
        'Only ' . $stock . ' units are available.',
        'error'
    );

    header('Location: ' . url('cart.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE CART
|--------------------------------------------------------------------------
*/

cart_update($productId, $quantity);

flash_set('Cart updated successfully.');

header('Location: ' . url('cart.php'));
exit;
