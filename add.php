<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

require_admin();

$pdo = get_db();

$categories = get_categories($pdo);

$errors = [];

$values = [
    'name' => '',
    'description' => '',
    'category_id' => '',
    'price' => '',
    'stock' => '',
    'featured' => '0',
    'is_active' => '1',
];

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
    | IMAGE UPLOAD
    |--------------------------------------------------------------------------
    */

    $uploadedImage = null;

    if (empty($errors)) {

        try {

            $uploadedImage = upload_product_image(
                $_FILES['product_image'] ?? []
            );

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PRODUCT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $productId = create_product(
                $pdo,
                $values,
                $uploadedImage
            );

            flash_set(
    'Product added successfully.'
);

header(
    'Location: ' . url('index.php')
);

exit;

        } catch (Throwable $e) {

            // The administrator sees a generic message; the details go to the log.
            error_log('Add product failed: ' . $e->getMessage());

            /*
             * If the image was uploaded but
             * product creation failed,
             * remove the uploaded image.
             */

            if ($uploadedImage !== null) {

                delete_product_image(
                    $uploadedImage
                );
            }

            $errors[] =
                'Unable to add the product. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| REUSABLE FORM SETTINGS
|--------------------------------------------------------------------------
*/

$formAction =
    url('add.php');

$submitLabel =
    'Add Product';

$cancelUrl =
    url('index.php');

$currentImage = null;
?>


<?php require __DIR__ . '/includes/header.php'; ?>


<section class="form-page">

    <div class="form-page-heading">

        <span class="eyebrow">
            PRODUCT MANAGEMENT
        </span>

        <h1>
            Add a new product
        </h1>

        <p>
            Create a new item for your FixerUpper catalogue.
            Add its details, category and inventory information below.
        </p>

    </div>


    <?php require __DIR__ . '/includes/product-form.php'; ?>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>