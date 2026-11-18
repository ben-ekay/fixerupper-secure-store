<?php

/*
|--------------------------------------------------------------------------
| MY ORDERS — the signed-in customer's own order history
|--------------------------------------------------------------------------
*/

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

$customer = require_customer('my-orders.php', $pdo);

// Only orders belonging to the session's customer id.
$orders = list_customer_orders($pdo, (int) $customer['id']);

$flash = flash_get();

$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>

<section class="cart-page">

    <div class="cart-heading">
        <p class="section-label">Your account</p>
        <h1>My orders</h1>
        <p>Orders placed with <?= htmlspecialchars($customer['email']) ?>.</p>
    </div>

    <?php if ($flash): ?>
        <div
            class="flash flash-<?= htmlspecialchars($flash['type']) ?>"
            role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($orders === []): ?>

        <div class="cart-summary orders-empty">
            <h2>No orders yet</h2>
            <p class="cart-summary-note">When you place an order it will appear here.</p>
            <a href="<?= url('index.php') ?>#catalog" class="button-primary">Start shopping</a>
        </div>

    <?php else: ?>

        <div class="cart-summary orders-table-wrap">
            <table class="confirmation-items orders-table">
                <caption class="visually-hidden">Your orders, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Date</th>
                        <th scope="col">Method</th>
                        <th scope="col" class="num">Items</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Reference">
                                <a href="<?= url('order-confirmation.php') ?>?ref=<?= urlencode($order['order_number']) ?>">
                                    <?= htmlspecialchars($order['order_number']) ?>
                                </a>
                            </td>
                            <td data-label="Date"><?= htmlspecialchars(format_order_date((int) $order['created_ts'])) ?></td>
                            <td data-label="Method"><?= $order['fulfilment_method'] === 'delivery' ? 'Delivery' : 'Collection' ?></td>
                            <td data-label="Items" class="num"><?= (int) $order['item_count'] ?></td>
                            <td data-label="Total" class="num"><?= format_pence(price_to_pence($order['total_amount'])) ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
