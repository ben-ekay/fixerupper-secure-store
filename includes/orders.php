<?php

/*
|--------------------------------------------------------------------------
| CHECKOUT AND ORDERS
|--------------------------------------------------------------------------
|
| Money is handled in whole pence (integers). PHP floats are not exact
| (19.99 * 5 = 99.94999999999999), so prices are converted from the
| database's DECIMAL strings straight to pence, added up as integers,
| and only turned back into "12.34" strings for the database or display.
|
| No payment is taken and no payment details are requested or stored.
|
*/

const DELIVERY_FEE_PENCE = 499;          // £4.99; collection is free
const FULFILMENT_METHODS = ['collection', 'delivery'];

// Order references: "FU" + 10 characters. Letters and digits that look
// alike (0/O, 1/I/L) are left out so references are easy to read aloud.
const ORDER_REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

// How many unused checkout forms (e.g. open tabs) are remembered.
const CHECKOUT_TOKENS_KEPT = 10;


/*
|--------------------------------------------------------------------------
| MONEY
|--------------------------------------------------------------------------
*/

/**
 * "34.99" (from a DECIMAL(10,2) column) -> 3499, without using floats.
 */
function price_to_pence(string $price): int
{
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($price), $m)) {
        throw new InvalidArgumentException('Invalid price: ' . $price);
    }

    return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
}

/**
 * 3499 -> "34.99" (the format the database expects).
 */
function pence_to_decimal(int $pence): string
{
    return sprintf('%d.%02d', intdiv($pence, 100), $pence % 100);
}

/**
 * 123456 -> "£1,234.56" (for display).
 */
function format_pence(int $pence): string
{
    return '£' . number_format(intdiv($pence, 100)) . sprintf('.%02d', $pence % 100);
}

function delivery_fee_pence(string $method): int
{
    return $method === 'delivery' ? DELIVERY_FEE_PENCE : 0;
}


/*
|--------------------------------------------------------------------------
| CART -> PRICED LINES
|--------------------------------------------------------------------------
*/

/**
 * Turn the session cart into lines priced from the DATABASE.
 *
 * With $lock = true this must run inside a transaction: the product rows
 * are locked (SELECT ... FOR UPDATE) until commit or rollback, so no
 * other checkout can change their stock in the meantime. Rows are locked
 * in ascending id order, so two checkouts always lock in the same order
 * and cannot deadlock each other.
 *
 * Returns ['lines' => [...], 'subtotal_pence' => int, 'problems' => [...]].
 */
function price_cart(PDO $pdo, array $cart, bool $lock = false): array
{
    $quantities = [];

    foreach ($cart as $productId => $quantity) {
        if ((int) $productId > 0 && (int) $quantity > 0) {
            $quantities[(int) $productId] = (int) $quantity;
        }
    }

    ksort($quantities);

    $result = ['lines' => [], 'subtotal_pence' => 0, 'problems' => []];

    if ($quantities === []) {
        return $result;
    }

    $placeholders = implode(',', array_fill(0, count($quantities), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, name, price, stock, is_active, image_url
         FROM products
         WHERE id IN ($placeholders)
         ORDER BY id"
        . ($lock ? ' FOR UPDATE' : '')
    );

    $stmt->execute(array_keys($quantities));

    $products = [];

    foreach ($stmt->fetchAll() as $row) {
        $products[(int) $row['id']] = $row;
    }

    foreach ($quantities as $productId => $quantity) {
        $product = $products[$productId] ?? null;

        if ($product === null) {
            $result['problems'][] = 'A product in your cart is no longer available. '
                . 'Please review your cart.';
            continue;
        }

        $stock = (int) $product['stock'];
        $isOnSale = (int) $product['is_active'] === 1;

        // Inside the transaction this reads the locked row, so a product
        // withdrawn while the customer was on the checkout page is refused.
        if (!$isOnSale) {
            $result['problems'][] = $product['name']
                . ' is no longer available. Please remove it from your cart.';
        } elseif ($stock <= 0) {
            $result['problems'][] = $product['name'] . ' is now out of stock.';
        } elseif ($quantity > $stock) {
            $result['problems'][] = 'Only ' . $stock . ' × ' . $product['name']
                . ' left in stock (you have ' . $quantity . ' in your cart).';
        }

        $unitPence = price_to_pence($product['price']);
        $linePence = $unitPence * $quantity;

        $result['lines'][] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'image_url' => $product['image_url'],
            'quantity' => $quantity,
            'stock' => $stock,
            'is_on_sale' => $isOnSale,
            'unit_pence' => $unitPence,
            'line_pence' => $linePence,
        ];

        // A withdrawn item is listed so the customer can see it, but is not payable.
        if ($isOnSale) {
            $result['subtotal_pence'] += $linePence;
        }
    }

    return $result;
}


/*
|--------------------------------------------------------------------------
| CHECKOUT FORM VALIDATION
|--------------------------------------------------------------------------
*/

/**
 * Validate the checkout form. Only these fields are read; prices, totals
 * and stock are never taken from the browser.
 *
 * Returns ['details' => [...], 'errors' => [field => message]].
 */
function validate_checkout_form(array $post): array
{
    $text = fn(string $key): string =>
        is_string($post[$key] ?? null) ? trim($post[$key]) : '';

    $errors = [];

    $method = $text('fulfilment_method');

    if (!in_array($method, FULFILMENT_METHODS, true)) {
        $errors['fulfilment_method'] = 'Choose collection or delivery.';
        $method = '';
    }

    // Optional phone: digits, spaces, +, brackets and dashes; 7-15 digits.
    $phone = $text('customer_phone');

    if ($phone !== '') {
        $digits = preg_replace('/\D/', '', $phone);

        if (
            mb_strlen($phone) > 30
            || !preg_match('/^[0-9+()\- ]+$/', $phone)
            || strlen($digits) < 7
            || strlen($digits) > 15
        ) {
            $errors['customer_phone'] = 'Enter a valid telephone number, or leave it blank.';
        }
    }

    $details = [
        'fulfilment_method' => $method,
        'customer_phone' => $phone !== '' ? $phone : null,
        'address_line1' => null,
        'address_line2' => null,
        'city' => null,
        'postcode' => null,
    ];

    // Address fields are only read for delivery. For collection they are
    // stored as NULL, which the database CHECK constraint requires.
    if ($method === 'delivery') {
        $line1 = $text('address_line1');
        $line2 = $text('address_line2');
        $city = $text('city');
        $postcode = strtoupper(preg_replace('/\s+/', '', $text('postcode')));

        if ($line1 === '') {
            $errors['address_line1'] = 'Enter the first line of your delivery address.';
        } elseif (mb_strlen($line1) > 150) {
            $errors['address_line1'] = 'Address line 1 must be 150 characters or fewer.';
        }

        if (mb_strlen($line2) > 150) {
            $errors['address_line2'] = 'Address line 2 must be 150 characters or fewer.';
        }

        if ($city === '') {
            $errors['city'] = 'Enter your town or city.';
        } elseif (mb_strlen($city) > 100) {
            $errors['city'] = 'Town or city must be 100 characters or fewer.';
        }

        // UK postcode shape, e.g. SW1A 1AA, M1 1AE, B33 8TH.
        if ($postcode === '') {
            $errors['postcode'] = 'Enter your postcode.';
        } elseif (!preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $postcode)) {
            $errors['postcode'] = 'Enter a valid UK postcode, like SW1A 1AA.';
        } else {
            // Store in the standard format with one space: "SW1A 1AA".
            $postcode = substr($postcode, 0, -3) . ' ' . substr($postcode, -3);
        }

        $details['address_line1'] = $line1;
        $details['address_line2'] = $line2 !== '' ? $line2 : null;
        $details['city'] = $city;
        $details['postcode'] = $postcode;
    }

    return ['details' => $details, 'errors' => $errors];
}


/*
|--------------------------------------------------------------------------
| ONE-TIME CHECKOUT TOKENS (duplicate-order protection)
|--------------------------------------------------------------------------
|
| Every checkout form carries a random one-time token stored in the
| session. A token can create at most one order:
|   unused  -> the order is placed and the token is marked with its reference
|   used    -> no new order; the customer is shown the order already placed
|   unknown -> the form is out of date; nothing is placed
|
| PHP locks the session file for the whole request, so two simultaneous
| submissions from the same browser run one after the other: the second
| one always sees the token already used.
|
*/

function checkout_token_issue(): string
{
    start_secure_session();

    $token = bin2hex(random_bytes(32));

    $tokens = $_SESSION['checkout_tokens'] ?? [];
    $tokens[$token] = null;

    // Keep only the most recent tokens.
    $_SESSION['checkout_tokens'] = array_slice($tokens, -CHECKOUT_TOKENS_KEPT, null, true);

    return $token;
}

/**
 * Returns 'unknown', 'unused', or the order reference already placed.
 */
function checkout_token_state(mixed $token): string
{
    start_secure_session();

    $tokens = $_SESSION['checkout_tokens'] ?? [];

    if (!is_string($token) || !array_key_exists($token, $tokens)) {
        return 'unknown';
    }

    return $tokens[$token] ?? 'unused';
}

function checkout_token_complete(string $token, string $orderNumber): void
{
    $_SESSION['checkout_tokens'][$token] = $orderNumber;
}


/*
|--------------------------------------------------------------------------
| PLACING AN ORDER
|--------------------------------------------------------------------------
*/

/**
 * Thrown for problems the customer can fix (e.g. not enough stock).
 * The message is safe to show on screen.
 */
class CheckoutException extends RuntimeException
{
}

/**
 * Cryptographically secure random reference, e.g. "FU7K2QXH9MPA".
 * random_int() uses the operating system's secure random generator.
 */
function generate_order_reference(): string
{
    $reference = 'FU';
    $max = strlen(ORDER_REFERENCE_ALPHABET) - 1;

    for ($i = 0; $i < 10; $i++) {
        $reference .= ORDER_REFERENCE_ALPHABET[random_int(0, $max)];
    }

    return $reference;
}

/**
 * Place an order in ONE database transaction. Either every step succeeds
 * and is committed, or nothing is saved at all.
 *
 *   1. lock the cart's product rows (SELECT ... FOR UPDATE, id order)
 *   2. check every product exists, is still on sale and has enough stock
 *   3. price everything in pence from the database
 *   4. insert the order, then its items (name + price snapshots)
 *   5. reduce stock
 *   6. commit
 *
 * Returns the order reference. Throws CheckoutException for problems the
 * customer can fix; any other exception means something went wrong and
 * the transaction has been rolled back.
 */
function place_order(PDO $pdo, array $customer, array $cart, array $details): string
{
    $pdo->beginTransaction();

    try {
        $priced = price_cart($pdo, $cart, true);

        if ($priced['lines'] === []) {
            throw new CheckoutException('Your cart is empty.');
        }

        if ($priced['problems'] !== []) {
            throw new CheckoutException(implode(' ', $priced['problems']));
        }

        $subtotalPence = $priced['subtotal_pence'];
        $feePence = delivery_fee_pence($details['fulfilment_method']);
        $totalPence = $subtotalPence + $feePence;

        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (
                order_number, user_id,
                customer_name, customer_email, customer_phone,
                fulfilment_method, address_line1, address_line2, city, postcode,
                status, subtotal, delivery_fee, total_amount
             ) VALUES (
                :order_number, :user_id,
                :customer_name, :customer_email, :customer_phone,
                :fulfilment_method, :address_line1, :address_line2, :city, :postcode,
                \'confirmed\', :subtotal, :delivery_fee, :total_amount
             )'
        );

        $orderValues = [
            'user_id' => $customer['id'],
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $details['customer_phone'],
            'fulfilment_method' => $details['fulfilment_method'],
            'address_line1' => $details['address_line1'],
            'address_line2' => $details['address_line2'],
            'city' => $details['city'],
            'postcode' => $details['postcode'],
            'subtotal' => pence_to_decimal($subtotalPence),
            'delivery_fee' => pence_to_decimal($feePence),
            'total_amount' => pence_to_decimal($totalPence),
        ];

        // A duplicate reference is astronomically unlikely (31^10
        // combinations), but the UNIQUE key would reject it, so retry.
        for ($attempt = 1; ; $attempt++) {
            $orderNumber = generate_order_reference();

            try {
                $insertOrder->execute(['order_number' => $orderNumber] + $orderValues);
                break;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062 || $attempt >= 3) {
                    throw $e;
                }
            }
        }

        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_id, product_name, unit_price, quantity, line_total)
             VALUES
                (:order_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
        );

        // Stock is reduced only if the product is still on sale and enough is
        // there. The row is locked,
        // so this always succeeds after the check above; it is a safety net.
        // "updated_at = updated_at" stops sales showing up as product edits
        // in the admin dashboard's recent-activity list.
        $reduceStock = $pdo->prepare(
            'UPDATE products
             SET stock = stock - :quantity, updated_at = updated_at
             WHERE id = :id AND is_active = 1 AND stock >= :required'
        );

        foreach ($priced['lines'] as $line) {
            $insertItem->execute([
                'order_id' => $orderId,
                'product_id' => $line['product_id'],
                'product_name' => $line['name'],
                'unit_price' => pence_to_decimal($line['unit_pence']),
                'quantity' => $line['quantity'],
                'line_total' => pence_to_decimal($line['line_pence']),
            ]);

            $reduceStock->execute([
                'quantity' => $line['quantity'],
                'id' => $line['product_id'],
                'required' => $line['quantity'],
            ]);

            if ($reduceStock->rowCount() !== 1) {
                throw new CheckoutException(
                    'Sorry, ' . $line['name'] . ' has just sold out. Please review your cart.'
                );
            }
        }

        $pdo->commit();

        return $orderNumber;
    } catch (Throwable $e) {
        // Undo everything: no order, no items, no stock change.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*
|--------------------------------------------------------------------------
| READING ORDERS (always scoped to the owner)
|--------------------------------------------------------------------------
|
| Every query includes "AND user_id = :user_id" with the id from the
| signed-in session, never from the URL. Changing the reference in the
| address bar can therefore only ever find the customer's own orders.
|
*/

function find_customer_order(PDO $pdo, string $orderNumber, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, order_number, customer_name, customer_email, customer_phone,
                fulfilment_method, address_line1, address_line2, city, postcode,
                status, subtotal, delivery_fee, total_amount,
                UNIX_TIMESTAMP(created_at) AS created_ts
         FROM orders
         WHERE order_number = :order_number
           AND user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        'order_number' => $orderNumber,
        'user_id' => $userId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    $items = $pdo->prepare(
        'SELECT product_name, unit_price, quantity, line_total
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY id'
    );

    $items->execute(['order_id' => $order['id']]);

    $order['items'] = $items->fetchAll();

    return $order;
}

function list_customer_orders(PDO $pdo, int $userId): array
{
    // Uses the idx_orders_user_created index (user_id, created_at).
    $stmt = $pdo->prepare(
        'SELECT o.order_number, o.fulfilment_method, o.status, o.total_amount,
                UNIX_TIMESTAMP(o.created_at) AS created_ts,
                (SELECT SUM(i.quantity) FROM order_items i WHERE i.order_id = o.id) AS item_count
         FROM orders o
         WHERE o.user_id = :user_id
         ORDER BY o.created_at DESC, o.id DESC'
    );

    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function format_order_date(int $timestamp): string
{
    return date('j F Y, H:i', $timestamp);
}
