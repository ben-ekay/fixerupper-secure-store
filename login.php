<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

// "Log in to check out" links pass ?next=checkout.php. Only pages in the
// allowlist are remembered; anything else is ignored.
remember_return_page($_GET['next'] ?? null);

$authUser = auth_user();
$isAdmin = ($authUser['role'] ?? null) === 'admin';

// Already signed in as a customer: nothing to do here.
if (($authUser['role'] ?? null) === 'customer') {
    header('Location: ' . take_return_url());
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        abort_request(403, 'Invalid CSRF token.');
    }

    if ($isAdmin) {
        abort_request(403, 'You are signed in as an administrator. Log out first.');
    }

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // Only customer accounts can sign in here. Admin accounts get the same
    // generic error as a wrong password.
    $result = attempt_login($pdo, $email, $password, 'customer');

    if ($result['user'] !== null) {
        auth_login($result['user']);

        flash_set('Welcome back, ' . $result['user']['name'] . '.');

        header('Location: ' . take_return_url());
        exit;
    }

    $error = $result['error'];
}

$flash = flash_get();

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-page">

    <div class="auth-card">

        <div class="auth-heading">

            <p class="eyebrow">Customer account</p>

            <h1>Log in</h1>

            <p>
                Sign in to check out and view your orders.
                Your cart is kept while you sign in.
            </p>

        </div>

        <?php if ($flash): ?>
            <div
                class="flash flash-<?= htmlspecialchars($flash['type']) ?>"
                role="status">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>

            <div class="flash flash-warning" role="status">
                You are signed in as an administrator.
                Administrator accounts cannot shop or check out.
                Log out to use a customer account.
            </div>

        <?php else: ?>

            <?php if ($error !== ''): ?>
                <div class="error-box" role="alert" id="login-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= url('login.php') ?>"
                class="auth-form"
                novalidate>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">

                    <label for="email">
                        Email address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        <?= $error !== '' ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
                        required>

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        <?= $error !== '' ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
                        required>

                </div>

                <button
                    type="submit"
                    class="button-primary auth-button">
                    Log in
                </button>

            </form>

            <p class="auth-switch">
                New to FixerUpper?
                <a href="<?= url('register.php') ?>">Create an account</a>
            </p>

            <p class="auth-switch auth-switch-staff">
                Staff member?
                <a href="<?= url('admin/login.php') ?>">Administrator sign-in</a>
            </p>

        <?php endif; ?>

    </div>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
