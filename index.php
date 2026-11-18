<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$flash = flash_get();


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['q'] ?? ''
);

$categoryId = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'newest',
    'price_asc',
    'price_desc',
    'name_asc',
    'name_desc',
];

$sort = $_GET['sort'] ?? 'newest';

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = get_store_departments(
    $pdo
);


/*
|--------------------------------------------------------------------------
| VALIDATE CATEGORY FILTER
|--------------------------------------------------------------------------
*/

$activeCategoryName = null;

if ($categoryId) {

    foreach ($categories as $category) {

        if (
            (int) $category['id']
            === (int) $categoryId
        ) {

            $activeCategoryName =
                $category['name'];

            break;
        }
    }

    if ($activeCategoryName === null) {
        $categoryId = null;
    }
}


/*
|--------------------------------------------------------------------------
| PRODUCT QUERY
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.featured,
        p.image_url,
        p.created_at,
        p.category_id,
        c.name AS category_name

    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.id

    WHERE p.is_active = 1
';

$params = [];

/*
|--------------------------------------------------------------------------
| STOREFRONT DEPARTMENTS ONLY
|--------------------------------------------------------------------------
*/

$departmentIds = array_map(
    'intval',
    array_column($categories, 'id')
);

$sql .= $departmentIds
    ? ' AND p.category_id IN (' . implode(',', $departmentIds) . ')'
    : ' AND 1 = 0';


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= '
        AND (
            p.name LIKE :search_name
            OR p.description LIKE :search_description
            OR c.name LIKE :search_category
        )
    ';

    $term =
        '%' . $search . '%';

    $params['search_name'] =
        $term;

    $params['search_description'] =
        $term;

    $params['search_category'] =
        $term;
}


/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

if ($categoryId) {

    $sql .= '
        AND p.category_id = :category_id
    ';

    $params['category_id'] =
        $categoryId;
}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$orderBy = match ($sort) {

    'price_asc' =>
    'p.price ASC, p.id DESC',

    'price_desc' =>
    'p.price DESC, p.id DESC',

    'name_asc' =>
    'p.name ASC, p.id DESC',

    'name_desc' =>
    'p.name DESC, p.id DESC',

    default =>
    'p.featured DESC, p.created_at DESC, p.id DESC',
};

$sql .= '
    ORDER BY ' . $orderBy;


/*
|--------------------------------------------------------------------------
| EXECUTE QUERY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    $sql
);

$stmt->execute(
    $params
);

$products =
    $stmt->fetchAll();

$productCount =
    count($products);


/*
|--------------------------------------------------------------------------
| CATALOG TITLE
|--------------------------------------------------------------------------
*/

$catalogTitle = 'All Products';

if ($activeCategoryName !== null) {
    $catalogTitle = $activeCategoryName;
}

if ($search !== '') {
    $catalogTitle = 'Search Results';
}


/*
|--------------------------------------------------------------------------
| CATALOG SUBTITLE
|--------------------------------------------------------------------------
*/

$catalogSubtitle = $productCount === 1
    ? '1 product'
    : $productCount . ' products';

if (
    $search !== ''
    && $activeCategoryName !== null
) {

    $catalogSubtitle .=
        ' found in ' . $activeCategoryName;
} elseif ($search !== '') {

    $catalogSubtitle .=
        ' found';
}


/*
|--------------------------------------------------------------------------
| CATEGORY URL PARAMETERS
|--------------------------------------------------------------------------
*/

$categoryQuery = [];

if ($search !== '') {
    $categoryQuery['q'] =
        $search;
}

if ($sort !== 'newest') {
    $categoryQuery['sort'] =
        $sort;
}


/*
|--------------------------------------------------------------------------
| ALL PRODUCTS URL
|--------------------------------------------------------------------------
*/

$allProductsParams = [];

if ($search !== '') {
    $allProductsParams['q'] =
        $search;
}

if ($sort !== 'newest') {
    $allProductsParams['sort'] =
        $sort;
}

$allProductsUrl =
    url('index.php');

if (!empty($allProductsParams)) {

    $allProductsUrl .=
        '?' . http_build_query(
            $allProductsParams
        );
}

$allProductsUrl .=
    '#catalog';

$pageTitle = 'Tools and home improvement';

// The large featured card only appears on the unfiltered catalogue.
$showFeatured = $search === '' && !$categoryId && $sort === 'newest';

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <p class="eyebrow">Built for every project</p>
    <h1>The right tools.<br>Every time.</h1>
    <p class="hero-text">
        Discover quality tools, electrical essentials and everything
        you need to improve your home.
    </p>
    <a href="#catalog" class="button-primary hero-button">Shop Tools</a>
</section>

<section class="departments" id="departments" aria-labelledby="departments-heading">
    <h2 id="departments-heading" class="section-title">Shop by Department</h2>

    <div class="department-grid">
        <?php foreach ($categories as $department): ?>
            <?php $count = (int) $department['product_count']; ?>
            <a
                class="department-tile"
                href="<?= url('index.php') ?>?category=<?= (int) $department['id'] ?>#catalog">
                <span class="department-image">
                    <img
                        src="<?= htmlspecialchars(product_image_url($department['image_url'])) ?>"
                        alt=""
                        loading="lazy">
                </span>
                <span class="department-name"><?= htmlspecialchars($department['name']) ?></span>
                <span class="department-count"><?= $count ?> <?= $count === 1 ? 'product' : 'products' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="catalog-section" id="catalog" aria-labelledby="catalog-heading">

    <?php if ($flash): ?>
        <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>" role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="catalog-heading">
        <h2 id="catalog-heading" class="section-title"><?= htmlspecialchars($catalogTitle) ?></h2>
        <p class="product-count"><?= htmlspecialchars($catalogSubtitle) ?></p>
    </div>

    <nav class="category-chips" aria-label="Filter by department">
        <a
            href="<?= htmlspecialchars($allProductsUrl) ?>"
            class="category-chip <?= !$categoryId ? 'active' : '' ?>"
            <?= !$categoryId ? 'aria-current="true"' : '' ?>>
            All
        </a>

        <?php foreach ($categories as $category): ?>
            <?php
            $isActive = $categoryId === (int) $category['id'];
            $categoryUrl = url('index.php?')
                . http_build_query($categoryQuery + ['category' => (int) $category['id']])
                . '#catalog';
            ?>
            <a
                href="<?= htmlspecialchars($categoryUrl) ?>"
                class="category-chip <?= $isActive ? 'active' : '' ?>"
                <?= $isActive ? 'aria-current="true"' : '' ?>>
                <?= htmlspecialchars($category['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="catalog-search" action="<?= url('index.php') ?>#catalog" method="get" role="search">
        <?php if ($categoryId): ?>
            <input type="hidden" name="category" value="<?= (int) $categoryId ?>">
        <?php endif; ?>

        <div class="catalog-search-field">
            <label for="search">Search products</label>
            <input
                type="search"
                id="search"
                name="q"
                placeholder="Drill, saw, multimeter…"
                value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="catalog-search-field catalog-sort-field">
            <label for="sort">Sort by</label>
            <select id="sort" name="sort">
                <?php foreach ([
                    'newest' => 'Featured and newest',
                    'price_asc' => 'Price: low to high',
                    'price_desc' => 'Price: high to low',
                    'name_asc' => 'Name: A to Z',
                    'name_desc' => 'Name: Z to A',
                ] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-secondary">Apply</button>

        <?php if ($search !== '' || $categoryId || $sort !== 'newest'): ?>
            <a href="<?= url('index.php') ?>#catalog" class="catalog-clear">Clear all</a>
        <?php endif; ?>
    </form>

    <?php if (empty($products)): ?>

        <div class="empty-state">
            <h3>No products found</h3>
            <p>
                <?php if ($search !== ''): ?>
                    Nothing matches "<strong><?= htmlspecialchars($search) ?></strong>"<?= $activeCategoryName !== null ? ' in ' . htmlspecialchars($activeCategoryName) : '' ?>.
                <?php elseif ($activeCategoryName !== null): ?>
                    There are no products in <?= htmlspecialchars($activeCategoryName) ?> yet.
                <?php else: ?>
                    There are currently no products available.
                <?php endif; ?>
            </p>
            <a href="<?= url('index.php') ?>#catalog" class="button-primary">View all products</a>
        </div>

    <?php else: ?>

        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php
                $productId = (int) $product['id'];
                $stock = (int) $product['stock'];
                $stockStatus = get_stock_status($stock);
                $isFeaturedCard = $showFeatured && (int) $product['featured'] === 1;
                $productUrl = url('product.php') . '?id=' . $productId;
                ?>
                <article class="product-card <?= $isFeaturedCard ? 'product-card-featured' : '' ?>">

                    <a class="product-card-image" href="<?= $productUrl ?>" tabindex="-1" aria-hidden="true">
                        <img
                            src="<?= htmlspecialchars(product_image_url($product['image_url'])) ?>"
                            alt=""
                            loading="lazy">
                    </a>

                    <div class="product-card-body">
                        <p class="product-card-meta">
                            <?php if (!empty($product['category_name'])): ?>
                                <a
                                    class="product-category"
                                    href="<?= url('index.php') ?>?category=<?= (int) $product['category_id'] ?>#catalog">
                                    <?= htmlspecialchars($product['category_name']) ?>
                                </a>
                            <?php endif; ?>

                            <?php if ((int) $product['featured'] === 1): ?>
                                <span class="featured-label">Featured</span>
                            <?php endif; ?>
                        </p>

                        <h3><a href="<?= $productUrl ?>"><?= htmlspecialchars($product['name']) ?></a></h3>

                        <p class="product-card-text"><?= htmlspecialchars($product['description']) ?></p>

                        <p class="product-price"><?= format_pence(price_to_pence($product['price'])) ?></p>

                        <p class="stock <?= htmlspecialchars($stockStatus['class']) ?>"><?= htmlspecialchars(stock_summary($stock)) ?></p>

                        <div class="product-card-actions">
                            <?php if ($authRole !== 'admin'): ?>
                            <?php if ($stock > 0): ?>
                                <form action="<?= url('add-to-cart.php') ?>" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                                    <button type="submit" class="button-primary">
                                        Add to Cart<span class="visually-hidden">: <?= htmlspecialchars($product['name']) ?></span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="button-primary" disabled>Out of stock</button>
                            <?php endif; ?>
                            <?php endif; ?>

                            <a href="<?= $productUrl ?>" class="button-secondary">
                                View Details<span class="visually-hidden">: <?= htmlspecialchars($product['name']) ?></span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
