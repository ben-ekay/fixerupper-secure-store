<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

require_admin();


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
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
| CSRF
|--------------------------------------------------------------------------
*/

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
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

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    abort_request(
        400,
        'Invalid product ID.'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$pdo = get_db();

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


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

$redirect = $_POST['redirect'] ?? '';

// Past orders refer to this product, so keep it. This check runs on the
// server, so it cannot be bypassed by sending a request directly.
if (!delete_product_if_no_orders($pdo, $id)) {
    flash_set(
        '"' . $product['name'] . '" cannot be deleted because it appears in past orders. '
            . 'To stop selling it, edit the product and untick "Active (on sale)".',
        'error'
    );

    header(
        'Location: ' . ($redirect === 'dashboard'
            ? url('admin/#products')
            : url('product.php?id=' . (int) $id))
    );

    exit;
}

delete_product_image(
    $product['image_url'] ?? null
);


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

flash_set(
    'Product deleted successfully.'
);


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

if ($redirect === 'dashboard') {
    header('Location: ' . url('admin/#products'));
    exit;
}

header(
    'Location: ' . url('index.php')
);

exit;