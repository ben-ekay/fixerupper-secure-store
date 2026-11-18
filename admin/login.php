<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

if (admin_is_logged_in()) {
    header('Location: ' . url('admin/'));
    exit;
}

$pdo = get_db();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        abort_request(403, 'Invalid CSRF token.');
    }

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // Shared, throttled login check. Only accounts with the admin role
    // can sign in here; customers get the same generic error.
    $result = attempt_login($pdo, $email, $password, 'admin');

    if ($result['user'] !== null) {
        auth_login($result['user']);

        // Administrators cannot shop, so a cart left from browsing as a
        // guest is removed now that the sign-in has succeeded.
        unset($_SESSION['cart']);

        header('Location: ' . url('admin/'));
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Administrator sign-in';
require __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">

    <div class="auth-card">

        <div class="auth-heading">

            <p class="eyebrow">FixerUpper admin</p>

            <h1>Welcome back</h1>

            <p>
                Sign in to manage products,
                stock and departments.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error-box">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            action="<?= url('admin/login.php') ?>"
            class="auth-form">

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
                    required>

            </div>

            <button
                type="submit"
                class="button-primary auth-button">
                Sign In
            </button>

        </form>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>