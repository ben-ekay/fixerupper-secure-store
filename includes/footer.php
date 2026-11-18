</main>

<footer class="site-footer">
    <div class="footer-container">

        <div class="footer-brand">
            <a class="brand" href="<?= url('index.php') ?>">
                <img class="brand-mark" src="<?= url('assets/images/logo-mark.svg') ?>" alt="">
                <span class="brand-text">FixerUpper</span>
            </a>
            <p>Tools, electrical essentials and everything you need to improve your home.</p>
        </div>

        <nav class="footer-links" aria-label="Footer">
            <a href="<?= url('index.php') ?>#catalog">Shop</a>
            <a href="<?= url('index.php') ?>#departments">Departments</a>
            <?php if ($authRole !== 'admin'): ?>
                <a href="<?= url('cart.php') ?>">Cart</a>
            <?php endif; ?>
            <a href="<?= url('image-credits.php') ?>">Image credits</a>
        </nav>

        <p class="footer-note">
            &copy; <?= date('Y') ?> FixerUpper. University demonstration store:
            no real orders are fulfilled and no payments are taken.
        </p>

    </div>
</footer>

<?php if ($isAdminPage || $authRole === 'admin'): ?>
    <script src="<?= url('assets/js/admin.js') ?>?v=2" defer></script>
<?php endif; ?>

</body>

</html>
