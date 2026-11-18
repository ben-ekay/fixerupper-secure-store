<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

// Administrators cannot use the cart (see require_shopper()).
require_shopper();

$pdo = get_db();

$cart = cart_items();

$cartProducts = [];
$subtotalPence = 0;
$payableItems = 0;
$unavailableProducts = 0;
$removedDeletedProduct = false;


/*
|--------------------------------------------------------------------------
| LOAD CART PRODUCTS
|--------------------------------------------------------------------------
|
| Prices come from the database, in whole pence. Withdrawn products stay
| visible so they can be removed, but are never part of the payable total.
|
*/

foreach ($cart as $productId => $quantity) {

    $product = get_product_by_id(
        $pdo,
        (int) $productId
    );

    // Deleted since it was added: nothing to show, so take it out of the cart.
    if (!$product) {
        cart_remove((int) $productId);
        $removedDeletedProduct = true;
        continue;
    }

    $quantity = (int) $quantity;

    if ($quantity <= 0) {
        continue;
    }

    $linePence = price_to_pence($product['price']) * $quantity;

    if (product_is_on_sale($product)) {
        $subtotalPence += $linePence;
        $payableItems += $quantity;
    } else {
        $unavailableProducts++;
    }

    $cartProducts[] = [
        'product' => $product,
        'quantity' => $quantity,
        'line_pence' => $linePence,
    ];
}

if ($removedDeletedProduct) {
    flash_set('A product in your cart is no longer sold, so it has been removed.', 'warning');
}

$pageTitle = 'Shopping cart';
require __DIR__ . '/includes/header.php';
?>

<?php $flash = flash_get(); ?>

<section class="cart-page">

    <?php if ($flash): ?>
        <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>" role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="cart-heading">

        <p class="section-label">Your selection</p>

        <h1>Shopping Cart</h1>

        <p>
            Review the products you have added.
        </p>

    </div>


    <?php if (empty($cartProducts)): ?>

        <div class="empty-state">

            <h3>Your cart is empty</h3>

            <p>
                Browse our tools and add something to your cart.
            </p>

            <a
                href="<?= url('index.php') ?>#catalog"
                class="button-primary">
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
                            href="<?= url('product.php') ?>?id=<?= (int) $product['id'] ?>">

                            <img
                                src="<?= htmlspecialchars(product_image_url($product['image_url'])) ?>"
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

                            <?php if (product_is_on_sale($product)): ?>
                                <p><?= format_pence(price_to_pence($product['price'])) ?> each</p>
                            <?php endif; ?>

                            <?php if (product_is_on_sale($product)): ?>

                            <form
                                method="post"
                                action="<?= url('update-cart.php') ?>"
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

                            <?php else: ?>

                                <p class="cart-unavailable">
                                    No longer available. Please remove this item.
                                </p>

                            <?php endif; ?>

                            <form
                                method="post"
                                action="<?= url('remove-from-cart.php') ?>"
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
                                    Remove<span class="visually-hidden">: <?= htmlspecialchars($product['name']) ?></span>
                                </button>
                            </form>

                        </div>

                        <strong class="cart-item-total">
                            <?php if (product_is_on_sale($product)): ?>
                                <?= format_pence($item['line_pence']) ?>
                            <?php else: ?>
                                Not included
                            <?php endif; ?>
                        </strong>

                    </article>

                <?php endforeach; ?>

            </div>


            <aside class="cart-summary">

                <h2>Order Summary</h2>

                <div class="cart-summary-row">

                    <span>Items</span>

                    <strong><?= $payableItems ?></strong>

                </div>

                <div class="cart-summary-row">

                    <span>Subtotal</span>

                    <strong><?= format_pence($subtotalPence) ?></strong>

                </div>

                <p class="cart-summary-note">
                    This is a demonstration shopping cart.
                    No payment will be taken.
                </p>

                <?php if ($unavailableProducts > 0): ?>

                    <p class="cart-unavailable" role="status">
                        <?= $unavailableProducts === 1
                            ? 'One item is no longer available and is not included above.'
                            : $unavailableProducts . ' items are no longer available and are not included above.' ?>
                        Remove <?= $unavailableProducts === 1 ? 'it' : 'them' ?> to continue to checkout.
                    </p>

                <?php else: ?>

                    <a
                        href="<?= url('checkout.php') ?>"
                        class="button-primary">
                        Proceed to Checkout
                    </a>

                    <?php if ($authRole === null): ?>
                        <p class="cart-summary-note">
                            You will be asked to log in or create an account.
                            Your cart will be kept.
                        </p>
                    <?php endif; ?>

                <?php endif; ?>

                <a
                    href="<?= url('index.php') ?>#catalog"
                    class="button-secondary">
                    Continue Shopping
                </a>

            </aside>

        </div>

    <?php endif; ?>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>