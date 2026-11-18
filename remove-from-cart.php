<?php

require __DIR__ . '/includes/functions.php';

// Administrators cannot use the cart (see require_shopper()).
require_shopper();

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
| REMOVE PRODUCT
|--------------------------------------------------------------------------
*/

$cart = cart_items();

if (isset($cart[$productId])) {

    cart_remove($productId);

    flash_set(
        'Product removed from your cart.'
    );
}

/*
|--------------------------------------------------------------------------
| REDIRECT TO CART
|--------------------------------------------------------------------------
*/

header('Location: ' . url('cart.php'));

exit;
