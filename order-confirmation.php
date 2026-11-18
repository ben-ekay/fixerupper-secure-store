<?php

/*
|--------------------------------------------------------------------------
| ORDER CONFIRMATION
|--------------------------------------------------------------------------
|
| Reached by a redirect after checkout (POST/Redirect/GET), so refreshing
| this page only reads the order again; it can never place a new one.
|
| Ownership: the order is looked up by reference AND the signed-in
| customer's id from the session. Another customer's reference returns
| the same "not found" page as a reference that does not exist, so the
| page does not even confirm that the order exists.
|
*/

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$customer = require_customer('my-orders.php', $pdo);

$reference = is_string($_GET['ref'] ?? null) ? strtoupper(trim($_GET['ref'])) : '';

if (!preg_match('/^FU[' . ORDER_REFERENCE_ALPHABET . ']{10}$/', $reference)) {
    abort_request(404, 'Order not found.');
}

$order = find_customer_order($pdo, $reference, (int) $customer['id']);

if ($order === null) {
    abort_request(404, 'Order not found.');
}

$flash = flash_get();
$isDelivery = $order['fulfilment_method'] === 'delivery';

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>

<section class="cart-page confirmation-page">

    <?php if ($flash): ?>
        <div
            class="flash flash-<?= htmlspecialchars($flash['type']) ?>"
            role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="confirmation-card">

        <div class="confirmation-header">
            <span class="confirmation-tick" aria-hidden="true">✓</span>
            <div>
                <p class="section-label">Order <?= htmlspecialchars($order['status']) ?></p>
                <h1>Thank you, <?= htmlspecialchars(strtok($order['customer_name'], ' ') ?: $order['customer_name']) ?></h1>
                <p>
                    A record of this order is saved in
                    <a href="<?= url('my-orders.php') ?>">My orders</a>.
                </p>
            </div>
        </div>

        <dl class="confirmation-facts">
            <div>
                <dt>Order reference</dt>
                <dd class="confirmation-reference"><?= htmlspecialchars($order['order_number']) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span></dd>
            </div>
            <div>
                <dt>Order date</dt>
                <dd><?= htmlspecialchars(format_order_date((int) $order['created_ts'])) ?></dd>
            </div>
            <div>
                <dt><?= $isDelivery ? 'Home delivery' : 'Collection' ?></dt>
                <dd>
                    <?php if ($isDelivery): ?>
                        <?= htmlspecialchars($order['address_line1']) ?><br>
                        <?php if ($order['address_line2'] !== null): ?>
                            <?= htmlspecialchars($order['address_line2']) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($order['city']) ?><br>
                        <?= htmlspecialchars($order['postcode']) ?>
                    <?php else: ?>
                        Collect from the FixerUpper store.
                        Please bring your order reference with you.
                    <?php endif; ?>
                </dd>
            </div>
        </dl>

        <h2 class="confirmation-items-heading">Items</h2>

        <table class="confirmation-items">
            <caption class="visually-hidden">Products in order <?= htmlspecialchars($order['order_number']) ?></caption>
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col" class="num">Price</th>
                    <th scope="col" class="num">Qty</th>
                    <th scope="col" class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="num"><?= format_pence(price_to_pence($item['unit_price'])) ?></td>
                        <td class="num"><?= (int) $item['quantity'] ?></td>
                        <td class="num"><?= format_pence(price_to_pence($item['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="3">Subtotal</th>
                    <td class="num"><?= format_pence(price_to_pence($order['subtotal'])) ?></td>
                </tr>
                <tr>
                    <th scope="row" colspan="3"><?= $isDelivery ? 'Delivery' : 'Collection' ?></th>
                    <td class="num"><?= price_to_pence($order['delivery_fee']) === 0 ? 'Free' : format_pence(price_to_pence($order['delivery_fee'])) ?></td>
                </tr>
                <tr class="confirmation-total">
                    <th scope="row" colspan="3">Total</th>
                    <td class="num"><?= format_pence(price_to_pence($order['total_amount'])) ?></td>
                </tr>
            </tfoot>
        </table>

        <p class="cart-summary-note">
            No payment has been taken. This is a university demonstration store.
        </p>

        <div class="confirmation-actions">
            <a href="<?= url('index.php') ?>#catalog" class="button-primary">Continue shopping</a>
            <a href="<?= url('my-orders.php') ?>" class="button-secondary">View my orders</a>
        </div>

    </div>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
