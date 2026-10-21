<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$cart = cart_items();

$cartProducts = [];
$subtotal = 0;


/*
|--------------------------------------------------------------------------
| LOAD CART PRODUCTS
|--------------------------------------------------------------------------
*/

foreach ($cart as $productId => $quantity) {

    $product = get_product_by_id(
        $pdo,
        (int) $productId
    );

    if (!$product) {
        continue;
    }

    $quantity = (int) $quantity;

    if ($quantity <= 0) {
        continue;
    }

    $price = (float) $product['price'];

    $lineTotal = $price * $quantity;

    $subtotal += $lineTotal;

    $cartProducts[] = [
        'product' => $product,
        'quantity' => $quantity,
        'line_total' => $lineTotal,
    ];
}

?>

<?php require __DIR__ . '/includes/header.php'; ?>

<?php $flash = flash_get(); ?>

<?php if ($flash): ?>
    <div
        class="cart-alert <?= $flash['type'] === 'error' ? 'cart-alert-error' : 'cart-alert-success' ?>"
        role="status">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>


<section class="cart-page">

    <div class="cart-heading">

        <p class="section-label">
            YOUR SELECTION
        </p>

        <h1>Shopping Cart</h1>

        <p>
            Review the products you have added.
        </p>

    </div>


    <?php if (empty($cartProducts)): ?>

        <div class="empty-state">

            <h3>Your cart is empty</h3>

            <p>
                Discover our products and add
                something to your cart.
            </p>

            <a
                href="/product-catalog/index.php#catalog"
                class="empty-button">
                Explore Products
            </a>

        </div>

    <?php else: ?>

        <div class="cart-layout">

            <div class="cart-items">

                <?php foreach ($cartProducts as $item): ?>

                    <?php

                    $product = $item['product'];

                    ?>

                    <article class="cart-item">

                        <a
                            href="/product-catalog/product.php?id=<?= (int) $product['id'] ?>">

                            <img
                                src="<?= htmlspecialchars(
                                            $product['image_url']
                                                ?: 'https://placehold.co/600x450?text=No+Image'
                                        ) ?>"
                                alt="<?= htmlspecialchars(
                                            $product['name']
                                        ) ?>"
                                loading="lazy">

                        </a>

                        <div class="cart-item-details">

                            <h2>
                                <?= htmlspecialchars(
                                    $product['name']
                                ) ?>
                            </h2>

                            <p>
                                £<?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?> each
                            </p>

                            <form
                                method="post"
                                action="/product-catalog/update-cart.php"
                                class="cart-quantity-form">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token()) ?>">

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $product['id'] ?>">

                                <label for="quantity-<?= (int) $product['id'] ?>">
                                    Quantity
                                </label>

                                <div class="cart-quantity-controls">
                                    <input
                                        type="number"
                                        id="quantity-<?= (int) $product['id'] ?>"
                                        name="quantity"
                                        min="0"
                                        max="<?= max(0, (int) $product['stock']) ?>"
                                        value="<?= (int) $item['quantity'] ?>"
                                        required>

                                    <button type="submit" class="cart-update-button">
                                        Update
                                    </button>
                                </div>
                            </form>

                            <form
                                method="post"
                                action="/product-catalog/remove-from-cart.php"
                                class="cart-remove-form">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token()) ?>">

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $product['id'] ?>">

                                <button
                                    type="submit"
                                    class="cart-remove-button">
                                    Remove
                                </button>
                            </form>

                        </div>

                        <strong class="cart-item-total">
                            £<?= number_format(
                                    $item['line_total'],
                                    2
                                ) ?>
                        </strong>

                    </article>

                <?php endforeach; ?>

            </div>


            <aside class="cart-summary">

                <h2>Order Summary</h2>

                <div class="cart-summary-row">

                    <span>Items</span>

                    <strong>
                        <?= array_sum(
                            array_column(
                                $cartProducts,
                                'quantity'
                            )
                        ) ?>
                    </strong>

                </div>

                <div class="cart-summary-row">

                    <span>Subtotal</span>

                    <strong>
                        £<?= number_format(
                                $subtotal,
                                2
                            ) ?>
                    </strong>

                </div>

                <p class="cart-summary-note">
                    This is a demonstration shopping cart.
                    No payment will be taken.
                </p>

                <a
                    href="/product-catalog/index.php#catalog"
                    class="button-primary">
                    Continue Shopping
                </a>

            </aside>

        </div>

    <?php endif; ?>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>