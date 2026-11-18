<?php

/*
|--------------------------------------------------------------------------
| CHECKOUT
|--------------------------------------------------------------------------
|
| Customers only: guests are sent to log in (cart kept) and returned here;
| administrators receive 403.
|
| Security rules on this page:
|   - only the customer's choices are read from the form (collection or
|     delivery, address, phone); prices, stock and totals always come from
|     the database
|   - the order is placed in one transaction (see place_order())
|   - a one-time checkout token stops double-clicks, refreshes and replays
|     from creating a second order
|   - after success the browser is redirected (POST/Redirect/GET), so
|     refreshing the confirmation page cannot submit the order again
|
*/

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$customer = require_customer('checkout.php', $pdo);

$errors = [];
$problems = [];

$values = [
    'fulfilment_method' => 'collection',
    'customer_phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'postcode' => '',
];


/*
|--------------------------------------------------------------------------
| PLACE ORDER (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        abort_request(403, 'Invalid CSRF token.');
    }

    $token = $_POST['checkout_token'] ?? null;
    $tokenState = checkout_token_state($token);

    // Same form submitted again (double-click, refresh, replay):
    // show the order that was already placed instead of creating another.
    if ($tokenState !== 'unused' && $tokenState !== 'unknown') {
        flash_set('This order has already been placed. No duplicate order was created.', 'info');
        header('Location: ' . url('order-confirmation.php?ref=' . urlencode($tokenState)), true, 303);
        exit;
    }

    if ($tokenState === 'unknown') {
        flash_set('Your checkout page was out of date. Please check your order and try again.', 'warning');
        header('Location: ' . url('checkout.php'), true, 303);
        exit;
    }

    foreach (array_keys($values) as $key) {
        if (is_string($_POST[$key] ?? null)) {
            $values[$key] = trim($_POST[$key]);
        }
    }

    $form = validate_checkout_form($_POST);
    $errors = $form['errors'];

    if ($errors === []) {
        try {
            $orderNumber = place_order($pdo, $customer, cart_items(), $form['details']);

            // Only after a successful commit: mark the token used and
            // empty the cart.
            checkout_token_complete($token, $orderNumber);
            $_SESSION['cart'] = [];

            flash_set('Thank you. Your order has been placed.');

            header('Location: ' . url('order-confirmation.php?ref=' . urlencode($orderNumber)), true, 303);
            exit;
        } catch (CheckoutException $e) {
            $problems[] = $e->getMessage();
        } catch (Throwable $e) {
            // Details go to the server log, never to the customer.
            error_log('Checkout failed: ' . $e->getMessage());

            $problems[] = 'Sorry, we could not place your order. '
                . 'No order was created and your cart has not changed. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| SHOW THE CHECKOUT PAGE
|--------------------------------------------------------------------------
*/

$cart = cart_items();

if ($cart === []) {
    flash_set('Your cart is empty. Add some products before checking out.', 'info');
    header('Location: ' . url('cart.php'));
    exit;
}

// Current prices and stock from the database (display only; the order
// itself is re-priced inside the transaction).
$priced = price_cart($pdo, $cart);

$problems = array_values(array_unique(array_merge($problems, $priced['problems'])));
$canOrder = $priced['problems'] === [];

$subtotalPence = $priced['subtotal_pence'];
$feePence = delivery_fee_pence($values['fulfilment_method']);
$isDelivery = $values['fulfilment_method'] === 'delivery';

$checkoutToken = checkout_token_issue();

$flash = flash_get();

$labels = [
    'fulfilment_method' => 'Collection or delivery',
    'address_line1' => 'Address line 1',
    'address_line2' => 'Address line 2',
    'city' => 'Town or city',
    'postcode' => 'Postcode',
    'customer_phone' => 'Telephone',
];

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<section class="cart-page checkout-page">

    <div class="cart-heading">
        <p class="section-label">Secure checkout</p>
        <h1>Checkout</h1>
        <p>Choose collection or delivery, check your order and confirm.</p>
    </div>

    <?php if ($flash): ?>
        <div
            class="flash flash-<?= htmlspecialchars($flash['type']) ?>"
            role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($problems !== []): ?>
        <div class="error-box" role="alert">
            <strong>We could not place your order:</strong>
            <ul>
                <?php foreach ($problems as $problem): ?>
                    <li><?= htmlspecialchars($problem) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$canOrder): ?>
                <p class="checkout-fix-link">
                    <a href="<?= url('cart.php') ?>">Update your cart</a>
                    to continue.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="error-box" role="alert" id="form-errors">
            <strong>Please correct the following:</strong>
            <ul>
                <?php foreach ($errors as $name => $message): ?>
                    <li>
                        <a href="#<?= htmlspecialchars($name === 'fulfilment_method' ? 'method-collection' : $name) ?>">
                            <?= htmlspecialchars($labels[$name] ?? $name) ?>:
                            <?= htmlspecialchars($message) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="cart-layout checkout-layout">

        <form
            method="post"
            action="<?= url('checkout.php') ?>"
            class="checkout-form"
            id="checkout-form"
            novalidate>

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="checkout_token" value="<?= htmlspecialchars($checkoutToken) ?>">

            <!-- Collection or delivery -->
            <fieldset class="checkout-section">
                <legend>Collection or delivery</legend>

                <div class="fulfilment-options">

                    <label class="fulfilment-option" for="method-collection">
                        <input
                            type="radio"
                            id="method-collection"
                            name="fulfilment_method"
                            value="collection"
                            data-fee-pence="0"
                            <?= !$isDelivery ? 'checked' : '' ?>>
                        <span class="fulfilment-option-text">
                            <strong>Collect from store</strong>
                            <span>Free · ready to collect in 2 working days</span>
                        </span>
                        <span class="fulfilment-option-price">Free</span>
                    </label>

                    <label class="fulfilment-option" for="method-delivery">
                        <input
                            type="radio"
                            id="method-delivery"
                            name="fulfilment_method"
                            value="delivery"
                            data-fee-pence="<?= DELIVERY_FEE_PENCE ?>"
                            <?= $isDelivery ? 'checked' : '' ?>>
                        <span class="fulfilment-option-text">
                            <strong>Home delivery</strong>
                            <span>UK addresses · 3 to 5 working days</span>
                        </span>
                        <span class="fulfilment-option-price"><?= format_pence(DELIVERY_FEE_PENCE) ?></span>
                    </label>

                </div>

                <?php if (isset($errors['fulfilment_method'])): ?>
                    <p class="field-error" id="fulfilment_method-error"><?= htmlspecialchars($errors['fulfilment_method']) ?></p>
                <?php endif; ?>
            </fieldset>

            <!-- Delivery address (shown only for delivery) -->
            <fieldset class="checkout-section" id="delivery-fields" data-delivery-fields>
                <legend>Delivery address</legend>

                <p class="form-hint">Only needed for home delivery.</p>

                <div class="form-group">
                    <label for="address_line1">Address line 1</label>
                    <input type="text" id="address_line1" name="address_line1" maxlength="150"
                        autocomplete="address-line1"
                        value="<?= htmlspecialchars($values['address_line1']) ?>"
                        <?= field_aria('address_line1', $errors) ?>>
                    <?php if (isset($errors['address_line1'])): ?>
                        <p class="field-error" id="address_line1-error"><?= htmlspecialchars($errors['address_line1']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="address_line2">Address line 2 <span class="optional">(optional)</span></label>
                    <input type="text" id="address_line2" name="address_line2" maxlength="150"
                        autocomplete="address-line2"
                        value="<?= htmlspecialchars($values['address_line2']) ?>"
                        <?= field_aria('address_line2', $errors) ?>>
                    <?php if (isset($errors['address_line2'])): ?>
                        <p class="field-error" id="address_line2-error"><?= htmlspecialchars($errors['address_line2']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="checkout-row">
                    <div class="form-group">
                        <label for="city">Town or city</label>
                        <input type="text" id="city" name="city" maxlength="100"
                            autocomplete="address-level2"
                            value="<?= htmlspecialchars($values['city']) ?>"
                            <?= field_aria('city', $errors) ?>>
                        <?php if (isset($errors['city'])): ?>
                            <p class="field-error" id="city-error"><?= htmlspecialchars($errors['city']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input type="text" id="postcode" name="postcode" maxlength="10"
                            autocomplete="postal-code"
                            value="<?= htmlspecialchars($values['postcode']) ?>"
                            <?= field_aria('postcode', $errors) ?>>
                        <?php if (isset($errors['postcode'])): ?>
                            <p class="field-error" id="postcode-error"><?= htmlspecialchars($errors['postcode']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </fieldset>

            <!-- Contact details -->
            <fieldset class="checkout-section">
                <legend>Your details</legend>

                <dl class="checkout-account">
                    <div>
                        <dt>Name</dt>
                        <dd><?= htmlspecialchars($customer['name']) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= htmlspecialchars($customer['email']) ?></dd>
                    </div>
                </dl>

                <div class="form-group">
                    <label for="customer_phone">Telephone <span class="optional">(optional)</span></label>
                    <p class="form-hint" id="customer_phone-hint">So we can contact you about your order.</p>
                    <input type="tel" id="customer_phone" name="customer_phone" maxlength="30"
                        autocomplete="tel"
                        value="<?= htmlspecialchars($values['customer_phone']) ?>"
                        <?= field_aria('customer_phone', $errors, 'customer_phone-hint') ?>>
                    <?php if (isset($errors['customer_phone'])): ?>
                        <p class="field-error" id="customer_phone-error"><?= htmlspecialchars($errors['customer_phone']) ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <div class="checkout-confirm">
                <p class="cart-summary-note">
                    This is a university demonstration. No payment is taken and
                    no card or bank details are requested.
                </p>

                <button
                    type="submit"
                    class="button-primary checkout-button"
                    <?= $canOrder ? '' : 'disabled aria-disabled="true"' ?>>
                    <span>Confirm order · <span data-total-text><?= format_pence($subtotalPence + $feePence) ?></span></span>
                </button>

                <a href="<?= url('cart.php') ?>" class="checkout-back-link">← Back to cart</a>
            </div>

        </form>

        <aside class="cart-summary checkout-summary" aria-labelledby="summary-heading">

            <h2 id="summary-heading">Order summary</h2>

            <ul class="checkout-lines">
                <?php foreach ($priced['lines'] as $line): ?>
                    <li class="checkout-line">
                        <img src="<?= htmlspecialchars(product_image_url($line['image_url'])) ?>" alt="" class="checkout-line-image">
                        <span class="checkout-line-name">
                            <?= htmlspecialchars($line['name']) ?>
                            <span class="checkout-line-qty">
                                <?= (int) $line['quantity'] ?> × <?= format_pence($line['unit_pence']) ?>
                            </span>
                            <?php if (!$line['is_on_sale']): ?>
                                <span class="checkout-line-warning">No longer available</span>
                            <?php elseif ($line['quantity'] > $line['stock']): ?>
                                <span class="checkout-line-warning">
                                    <?= $line['stock'] > 0 ? 'Only ' . $line['stock'] . ' left' : 'Out of stock' ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <strong><?= $line['is_on_sale'] ? format_pence($line['line_pence']) : 'Not included' ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="cart-summary-row">
                <span>Subtotal</span>
                <strong data-subtotal-pence="<?= $subtotalPence ?>"><?= format_pence($subtotalPence) ?></strong>
            </div>

            <div class="cart-summary-row">
                <span>Delivery</span>
                <strong data-fee-text><?= $feePence === 0 ? 'Free' : format_pence($feePence) ?></strong>
            </div>

            <div class="cart-summary-row checkout-total-row">
                <span>Total</span>
                <strong data-total-text><?= format_pence($subtotalPence + $feePence) ?></strong>
            </div>

            <p class="cart-summary-note">
                Prices and stock are checked again when you confirm.
            </p>

        </aside>

    </div>

</section>

<script src="<?= url('assets/js/checkout.js') ?>?v=1" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
