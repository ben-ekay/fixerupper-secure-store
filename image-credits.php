<?php

/*
|--------------------------------------------------------------------------
| IMAGE CREDITS
|--------------------------------------------------------------------------
|
| Where each product image came from. The product images are AI-generated
| illustrations, so they are labelled as such rather than as photographs,
| and no third-party author or licence is claimed for them.
|
*/

require_once __DIR__ . '/includes/functions.php';

$credits = [
    ['product' => 'Cordless Drill Driver', 'file' => 'cordless-drill-driver.jpg'],
    ['product' => 'Screwdriver Set', 'file' => 'screwdriver-set.jpg'],
    ['product' => 'Adjustable Spanner Set', 'file' => 'adjustable-spanner-set.jpg'],
    ['product' => 'Digital Multimeter', 'file' => 'digital-multimeter.jpg'],
    ['product' => 'LED Work Light', 'file' => 'led-work-light.jpg'],
    [
        'product' => 'Circular Saw',
        'file' => 'circular-saw.jpg',
        'changes' => 'Framing and background lighting adjusted to match the other product images.',
    ],
    ['product' => 'Measuring Tape', 'file' => 'measuring-tape.jpg'],
    [
        'product' => 'Tool Storage Box',
        'file' => 'tool-storage-box.jpg',
        'changes' => 'Framing and background lighting adjusted to match the other product images.',
    ],
];

$pageTitle = 'Image credits';

require __DIR__ . '/includes/header.php';
?>

<section class="cart-page">

    <div class="cart-heading">
        <p class="section-label">About this site</p>
        <h1>Image credits</h1>
        <p>
            The product images on this site are AI-generated illustrations, made for
            this student project. They are not photographs of real stock, and they do
            not show any real manufacturer's product or brand. They illustrate each
            type of product only, so the item supplied could look different.
        </p>
    </div>

    <ul class="credits-list">
        <?php foreach ($credits as $credit): ?>
            <li class="credits-item">
                <img src="<?= url('uploads/products/' . $credit['file']) ?>" alt="" loading="lazy">
                <div>
                    <h2><?= htmlspecialchars($credit['product']) ?></h2>
                    <p>
                        AI-generated illustration, 1200 × 900 pixels.
                        Not a photograph of the actual product.
                        <?php if (isset($credit['changes'])): ?>
                            <?= htmlspecialchars($credit['changes']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
