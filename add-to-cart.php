<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();


/*
|--------------------------------------------------------------------------
| ONLY ALLOW POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    abort_request(
        405,
        'Method not allowed.'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF VALIDATION
|--------------------------------------------------------------------------
*/

if (!csrf_validate(
    $_POST['csrf_token'] ?? null
)) {

    abort_request(
        403,
        'Invalid CSRF token.'
    );
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

    abort_request(
        400,
        'Invalid product.'
    );
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

if (!$quantity || $quantity < 1) {
    $quantity = 1;
}


/*
|--------------------------------------------------------------------------
| PRODUCT
|--------------------------------------------------------------------------
*/

$product = get_product_by_id(
    $pdo,
    $productId
);

if (!$product) {

    abort_request(
        404,
        'Product not found.'
    );
}


/*
|--------------------------------------------------------------------------
| STOCK VALIDATION
|--------------------------------------------------------------------------
*/

$stock = (int) $product['stock'];

if ($stock <= 0) {

    flash_set(
        'This product is currently out of stock.',
        'error'
    );

    header(
        'Location: /product-catalog/index.php#catalog'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EXISTING CART QUANTITY
|--------------------------------------------------------------------------
*/

$cart = cart_items();

$currentQuantity =
    $cart[$productId] ?? 0;

$newQuantity =
    $currentQuantity + $quantity;


/*
|--------------------------------------------------------------------------
| DO NOT EXCEED STOCK
|--------------------------------------------------------------------------
*/

if ($newQuantity > $stock) {

    flash_set(
        'You cannot add more than the available stock.',
        'error'
    );

    header(
        'Location: /product-catalog/index.php#catalog'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

cart_add(
    $productId,
    $quantity
);


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

flash_set(
    $product['name']
        . ' added to your cart.'
);


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: /product-catalog/index.php#catalog'
);

exit;
