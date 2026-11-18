<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

require_admin();

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


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = get_categories($pdo);


/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

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
| INITIAL VALUES
|--------------------------------------------------------------------------
*/

$errors = [];

$values = [
    'name' => $product['name'],
    'description' => $product['description'],
    'category_id' => (string) ($product['category_id'] ?? ''),
    'price' => $product['price'],
    'stock' => $product['stock'],
    'featured' => (string) $product['featured'],
    'is_active' => (string) $product['is_active'],
];

$currentImage = $product['image_url'] ?? null;


/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/
/*
 * Where to go after saving. Only admin-area paths are accepted,
 * so a crafted link cannot redirect the admin to another site.
 */
$returnUrl = safe_admin_return_url(
    $_POST['return']
    ?? $_GET['return']
    ?? ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    abort_request(
        403,
        'Invalid CSRF token.'
    );
}


    /*
    |--------------------------------------------------------------------------
    | FORM VALUES
    |--------------------------------------------------------------------------
    */

    $values = product_values_from_request();


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $errors = validate_product_values($values);

    if (
    empty($errors)
    && !category_id_exists(
        $pdo,
        (int) $values['category_id']
    )
) {
    $errors[] =
        'The selected category does not exist.';
}


    /*
    |--------------------------------------------------------------------------
    | REPLACEMENT IMAGE
    |--------------------------------------------------------------------------
    */

    $newImage = null;

    if (empty($errors)) {

        try {

            $newImage = upload_product_image(
                $_FILES['product_image'] ?? []
            );

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | IMAGE TO SAVE
    |--------------------------------------------------------------------------
    */

    $imageToSave =
        $newImage ?? $currentImage;


    /*
    |--------------------------------------------------------------------------
    | UPDATE DATABASE
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            update_product(
                $pdo,
                $id,
                $values,
                $imageToSave
            );


            /*
            |--------------------------------------------------------------------------
            | DELETE OLD IMAGE
            |--------------------------------------------------------------------------
            */

            if (
                $newImage !== null
                && $currentImage !== null
                && $newImage !== $currentImage
            ) {
                delete_product_image(
                    $currentImage
                );
            }


            /*
            |--------------------------------------------------------------------------
            | REDIRECT
            |--------------------------------------------------------------------------
            */

            flash_set(
    'Product updated successfully.'
);

if ($returnUrl !== '') {

    header(
        'Location: ' . $returnUrl
    );

    exit;
}

header(
    'Location: ' . url('product.php?id=')
    . $id
);

exit;

exit;

        } catch (Throwable $e) {

            // The administrator sees a generic message; the details go to the log.
            error_log('Edit product failed: ' . $e->getMessage());

            /*
             * If database update fails after
             * uploading a new image,
             * remove the new image.
             */

            if ($newImage !== null) {

                delete_product_image(
                    $newImage
                );
            }

            $errors[] =
                'Unable to update the product. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| REUSABLE FORM SETTINGS
|--------------------------------------------------------------------------
*/

$formAction =
    url('edit.php?id=')
    . (int) $id;

$submitLabel =
    'Save Changes';

$cancelUrl =
    url('product.php?id=')
    . (int) $id;
?>


<?php require __DIR__ . '/includes/header.php'; ?>


<section class="form-page">

    <div class="form-page-heading">

        <span class="eyebrow">
            PRODUCT MANAGEMENT
        </span>

        <h1>
            Edit product
        </h1>

        <p>
            Update the product details, inventory,
            category and product image.
        </p>

    </div>


    <?php require __DIR__ . '/includes/product-form.php'; ?>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>