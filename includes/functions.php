<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orders.php';

send_security_headers();

/**
 * Build a site URL from a path relative to the project root.
 *
 * url('cart.php')  ->  /fixerupper-summative/cart.php
 * url()            ->  /fixerupper-summative/
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Return $returnUrl only if it points inside the admin area,
 * otherwise return ''. Prevents open redirects to other sites.
 */
function safe_admin_return_url(mixed $returnUrl): string
{
    // Request values can be arrays (?return[]=x), so accept strings only.
    if (!is_string($returnUrl)) {
        return '';
    }

    $returnUrl = trim($returnUrl);

    // Reject line breaks and backslashes (header and path tricks).
    if (preg_match('/[\r\n\\\\]/', $returnUrl)) {
        return '';
    }

    // Must be a local path that starts with /fixerupper-summative/admin/.
    // A full URL (https://evil.com) or //evil.com can never match.
    if (!str_starts_with($returnUrl, url('admin/'))) {
        return '';
    }

    return $returnUrl;
}

function csrf_token(): string
{
    start_secure_session();

    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(mixed $token): bool
{
    start_secure_session();

    // Request values can be arrays (csrf_token[]=x), so accept strings only.
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || !is_string($token)
        || $token === ''
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}

function get_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            id,
            name,
            slug
         FROM categories
         ORDER BY name ASC'
    );

    return $stmt->fetchAll();
}
/**
 * The storefront departments with their real product counts, plus one
 * product photo from each department to use on its tile.
 */
function get_store_departments(PDO $pdo): array
{
    $placeholders = implode(',', array_fill(0, count(STOREFRONT_DEPARTMENTS), '?'));

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.name,
            c.slug,
            COUNT(p.id) AS product_count,
            (SELECT p2.image_url
             FROM products p2
             WHERE p2.category_id = c.id
               AND p2.is_active = 1
               AND p2.image_url IS NOT NULL
             ORDER BY p2.featured DESC, p2.id ASC
             LIMIT 1) AS image_url
         FROM categories c
         LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
         WHERE c.slug IN ($placeholders)
         GROUP BY c.id, c.name, c.slug"
    );

    $stmt->execute(STOREFRONT_DEPARTMENTS);

    $departments = $stmt->fetchAll();

    usort(
        $departments,
        fn(array $a, array $b): int =>
            array_search($a['slug'], STOREFRONT_DEPARTMENTS, true)
            <=> array_search($b['slug'], STOREFRONT_DEPARTMENTS, true)
    );

    return $departments;
}

/**
 * Whether a product (from get_product_by_id) can currently be bought.
 */
function product_is_on_sale(array $product): bool
{
    return (int) $product['is_active'] === 1;
}

/**
 * The product's photo, or the local labelled placeholder if it has none.
 */
function product_image_url(?string $imageUrl): string
{
    return $imageUrl ?: url('assets/images/product-placeholder.svg');
}

/**
 * Stock wording for customers, always based on the real stock figure.
 */
function stock_summary(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of stock';
    }

    return $stock <= 5
        ? 'Low stock · ' . $stock . ' left'
        : 'In stock · ' . $stock . ' available';
}

function get_stock_status(int $stock): array
{
    if ($stock <= 0) {
        return [
            'label' => 'Out of stock',
            'class' => 'out-of-stock',
        ];
    }

    if ($stock <= 5) {
        return [
            'label' => 'Low stock',
            'class' => 'low-stock',
        ];
    }

    return [
        'label' => 'In stock',
        'class' => 'in-stock',
    ];
}
function upload_product_image(array $file): ?string
{
    if (
        !isset($file['error'])
        || $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'There was an error uploading the image.'
        );
    }

    $maxSize = 5 * 1024 * 1024;

    if ($file['size'] > $maxSize) {
        throw new RuntimeException(
            'Image must be 5MB or smaller.'
        );
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType = mime_content_type(
        $file['tmp_name']
    );

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException(
            'Only JPG, PNG and WebP images are allowed.'
        );
    }

    $extension = $allowedMimeTypes[$mimeType];

    $filename =
        bin2hex(random_bytes(16))
        . '.'
        . $extension;

    $uploadDirectory =
        __DIR__
        . '/../uploads/products';

    if (!is_dir($uploadDirectory)) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
            && !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'Unable to create upload directory.'
            );
        }
    }

    $destination =
        $uploadDirectory
        . '/'
        . $filename;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            'Unable to save uploaded image.'
        );
    }

    return url('uploads/products/' . $filename);
}


function delete_product_image(
    ?string $imageUrl
): void {
    if (!$imageUrl) {
        return;
    }

    $prefix = url('uploads/products/');

    if (!str_starts_with($imageUrl, $prefix)) {
        return;
    }

    $filename = basename($imageUrl);

    $path =
        __DIR__
        . '/../uploads/products/'
        . $filename;

    if (is_file($path)) {
        unlink($path);
    }
}
function validate_product_values(array $values): array
{
    $errors = [];

    if (($values['name'] ?? '') === '') {
        $errors[] = 'Name is required.';
    }

    if (($values['description'] ?? '') === '') {
        $errors[] = 'Description is required.';
    }

    $categoryId = $values['category_id'] ?? '';

    if (
        $categoryId === ''
        || !ctype_digit((string) $categoryId)
    ) {
        $errors[] = 'Please select a category.';
    }

    $price = $values['price'] ?? '';

    if (
        $price === ''
        || !is_numeric($price)
        || (float) $price < 0
    ) {
        $errors[] = 'Price must be a positive number.';
    }

    $stock = $values['stock'] ?? '';

    if (
        $stock === ''
        || filter_var(
            $stock,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0
                ]
            ]
        ) === false
    ) {
        $errors[] = 'Stock must be 0 or greater.';
    }

    return $errors;
}
function product_values_from_request(): array
{
    return [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => trim($_POST['category_id'] ?? ''),
        'price' => trim($_POST['price'] ?? ''),
        'stock' => trim($_POST['stock'] ?? ''),
        'featured' => isset($_POST['featured']) ? '1' : '0',
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
    ];
}
function get_product_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.name,
            p.description,
            p.category_id,
            p.price,
            p.stock,
            p.featured,
            p.is_active,
            p.image_url,
            p.created_at,
            p.updated_at,
            c.name AS category_name
         FROM products p
         LEFT JOIN categories c
            ON p.category_id = c.id
         WHERE p.id = :id'
    );

    $stmt->execute([
        'id' => $id
    ]);

    $product = $stmt->fetch();

    return $product ?: null;
}
function create_product(
    PDO $pdo,
    array $values,
    ?string $imageUrl
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO products
        (
            name,
            description,
            category_id,
            price,
            stock,
            featured,
            is_active,
            image_url
        )
        VALUES
        (
            :name,
            :description,
            :category_id,
            :price,
            :stock,
            :featured,
            :is_active,
            :image_url
        )'
    );

    $stmt->execute([
        'name' => $values['name'],
        'description' => $values['description'],
        'category_id' => (int) $values['category_id'],
        'price' => (float) $values['price'],
        'stock' => (int) $values['stock'],
        'featured' => (int) $values['featured'],
        'is_active' => (int) $values['is_active'],
        'image_url' => $imageUrl,
    ]);

    return (int) $pdo->lastInsertId();
}


function update_product(
    PDO $pdo,
    int $id,
    array $values,
    ?string $imageUrl
): void {
    $stmt = $pdo->prepare(
        'UPDATE products
         SET
            name = :name,
            description = :description,
            category_id = :category_id,
            price = :price,
            stock = :stock,
            featured = :featured,
            is_active = :is_active,
            image_url = :image_url
         WHERE id = :id'
    );

    $stmt->execute([
        'name' => $values['name'],
        'description' => $values['description'],
        'category_id' => (int) $values['category_id'],
        'price' => (float) $values['price'],
        'stock' => (int) $values['stock'],
        'featured' => (int) $values['featured'],
        'is_active' => (int) $values['is_active'],
        'image_url' => $imageUrl,
        'id' => $id,
    ]);
}
/**
 * Delete a product, unless it appears in past orders.
 *
 * Returns false, and deletes nothing, if an order refers to it. The check and
 * the delete share one transaction, holding the product's row lock. Checkout
 * takes the same lock before adding order lines, so an order cannot slip in
 * between the two steps.
 */
function delete_product_if_no_orders(PDO $pdo, int $id): bool
{
    $pdo->beginTransaction();

    try {
        $lock = $pdo->prepare(
            'SELECT id
             FROM products
             WHERE id = :id
             FOR UPDATE'
        );

        $lock->execute(['id' => $id]);

        $orders = $pdo->prepare(
            'SELECT COUNT(*)
             FROM order_items
             WHERE product_id = :id'
        );

        $orders->execute(['id' => $id]);

        if ((int) $orders->fetchColumn() > 0) {
            $pdo->rollBack();

            return false;
        }

        $delete = $pdo->prepare(
            'DELETE FROM products
             WHERE id = :id'
        );

        $delete->execute(['id' => $id]);

        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
function flash_set(
    string $message,
    string $type = 'success'
): void {
    start_secure_session();

    $allowedTypes = [
        'success',
        'error',
        'warning',
        'info',
    ];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'success';
    }

    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type,
    ];
}


/**
 * Accessibility attributes for a form field: marks it invalid and links
 * it to its error message (and optional hint) for screen readers.
 */
function field_aria(string $field, array $errors, string $hintId = ''): string
{
    $describedBy = array_filter([
        $hintId,
        isset($errors[$field]) ? $field . '-error' : '',
    ]);

    $attributes = isset($errors[$field]) ? 'aria-invalid="true"' : '';

    if ($describedBy !== []) {
        $attributes .= ' aria-describedby="'
            . htmlspecialchars(implode(' ', $describedBy)) . '"';
    }

    return trim($attributes);
}

function flash_get(): ?array
{
    start_secure_session();

    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];

    unset($_SESSION['flash_message']);

    return $flash;
}

/*
|--------------------------------------------------------------------------
| SHOPPING CART
|--------------------------------------------------------------------------
*/

/**
 * Return all products currently stored in the cart.
 */
function cart_items(): array
{
    start_secure_session();

    return $_SESSION['cart'] ?? [];
}


/**
 * Number of units in the cart that can actually be bought, for the header
 * badge. Products withdrawn from sale or deleted are not counted.
 */
function cart_count(): int
{
    $cart = cart_items();

    if ($cart === []) {
        return 0;
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = get_db()->prepare(
        "SELECT id
         FROM products
         WHERE is_active = 1
           AND id IN ($placeholders)"
    );

    $stmt->execute($ids);

    $count = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $count += (int) $cart[$id];
    }

    return $count;
}


/**
 * Add a product to the cart.
 */
function cart_add(
    int $productId,
    int $quantity = 1
): void {

    start_secure_session();

    if (
        $productId <= 0
        || $quantity <= 0
    ) {
        return;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$productId])) {

        $_SESSION['cart'][$productId] += $quantity;
    } else {

        $_SESSION['cart'][$productId] = $quantity;
    }
}

/*
|--------------------------------------------------------------------------
| REMOVE PRODUCT FROM CART
|--------------------------------------------------------------------------
*/

function cart_remove(int $productId): void
{
    start_secure_session();

    if ($productId <= 0) {
        return;
    }

    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE CART QUANTITY
|--------------------------------------------------------------------------
*/

function cart_update(
    int $productId,
    int $quantity
): void {

    start_secure_session();

    if ($productId <= 0) {
        return;
    }

    if ($quantity <= 0) {
        cart_remove($productId);
        return;
    }

    if (!isset($_SESSION['cart'][$productId])) {
        return;
    }

    $_SESSION['cart'][$productId] = $quantity;
}


function abort_request(
    int $statusCode,
    string $message
): never {
    // Start the session before the error page sends any output.
    start_secure_session();

    http_response_code($statusCode);

    $titles = [
        400 => 'Bad Request',
        403 => 'Access Denied',
        404 => 'Page Not Found',
        405 => 'Method Not Allowed',
        500 => 'Something Went Wrong',
    ];

    $errorTitle =
        $titles[$statusCode]
        ?? 'Something Went Wrong';

    $errorMessage = $message;

    require __DIR__ . '/error-page.php';

    exit;
}
function generate_slug(string $text): string
{
    $text = trim($text);
    $text = strtolower($text);

    $text = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $text
    );

    $text = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $text
    );

    $text = trim(
        $text,
        '-'
    );

    return $text;
}
function category_exists(
    PDO $pdo,
    string $name,
    string $slug,
    ?int $excludeId = null
): bool {
    $sql = '
        SELECT id
        FROM categories
        WHERE (
            name = :name
            OR slug = :slug
        )
    ';

    $params = [
        'name' => $name,
        'slug' => $slug,
    ];

    if ($excludeId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}


function create_category(
    PDO $pdo,
    string $name,
    string $slug
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO categories
        (
            name,
            slug
        )
        VALUES
        (
            :name,
            :slug
        )'
    );

    $stmt->execute([
        'name' => $name,
        'slug' => $slug,
    ]);

    return (int) $pdo->lastInsertId();
}


function update_category(
    PDO $pdo,
    int $id,
    string $name,
    string $slug
): void {
    $stmt = $pdo->prepare(
        'UPDATE categories
         SET
            name = :name,
            slug = :slug
         WHERE id = :id'
    );

    $stmt->execute([
        'name' => $name,
        'slug' => $slug,
        'id' => $id,
    ]);
}


function get_category_product_count(
    PDO $pdo,
    int $categoryId
): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM products
         WHERE category_id = :id'
    );

    $stmt->execute([
        'id' => $categoryId,
    ]);

    return (int) $stmt->fetchColumn();
}


function delete_category(
    PDO $pdo,
    int $categoryId
): void {
    $stmt = $pdo->prepare(
        'DELETE FROM categories
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $categoryId,
    ]);
}


function get_categories_with_product_count(
    PDO $pdo
): array {
    $stmt = $pdo->query(
        'SELECT
            c.id,
            c.name,
            c.slug,
            COUNT(p.id) AS product_count
         FROM categories c
         LEFT JOIN products p
            ON p.category_id = c.id
         GROUP BY
            c.id,
            c.name,
            c.slug
         ORDER BY c.name ASC'
    );

    return $stmt->fetchAll();
}
function category_id_exists(
    PDO $pdo,
    int $categoryId
): bool {
    $stmt = $pdo->prepare(
        'SELECT id
         FROM categories
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $categoryId,
    ]);

    return (bool) $stmt->fetchColumn();
}
function get_recent_products(
    PDO $pdo,
    int $limit = 5
): array {
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.name,
            p.image_url,
            p.created_at,
            p.updated_at,
            c.name AS category_name,
            CASE
                WHEN p.created_at = p.updated_at
                THEN "Added"
                ELSE "Updated"
            END AS activity_type
         FROM products p
         LEFT JOIN categories c
            ON p.category_id = c.id
         ORDER BY p.updated_at DESC
         LIMIT :limit'
    );

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->execute();

    return $stmt->fetchAll();
}
function build_admin_filter_url(
    array $currentFilters,
    array $changes = []
): string {

    $filters = array_merge(
        $currentFilters,
        $changes
    );

    $filters = array_filter(
        $filters,
        static fn($value) =>
        $value !== ''
            && $value !== null
    );

    $query = http_build_query(
        $filters
    );

    return url('admin/')
        . ($query !== ''
            ? '?' . $query
            : '')
        . '#products';
}
function time_ago(string $dateTime): string
{
    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return '';
    }

    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'just now';
    }

    if ($difference < 3600) {
        $minutes = (int) floor($difference / 60);

        return $minutes === 1
            ? '1 min ago'
            : $minutes . ' mins ago';
    }

    if ($difference < 86400) {
        $hours = (int) floor($difference / 3600);

        return $hours === 1
            ? '1 hour ago'
            : $hours . ' hours ago';
    }

    if ($difference < 604800) {
        $days = (int) floor($difference / 86400);

        return $days === 1
            ? '1 day ago'
            : $days . ' days ago';
    }

    return date(
        'd M Y',
        $timestamp
    );
}
