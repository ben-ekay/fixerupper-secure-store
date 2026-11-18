<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$pdo = get_db();

remember_return_page($_GET['next'] ?? null);

$authUser = auth_user();
$isAdmin = ($authUser['role'] ?? null) === 'admin';

if (($authUser['role'] ?? null) === 'customer') {
    header('Location: ' . take_return_url());
    exit;
}

$errors = [];
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        abort_request(403, 'Invalid CSRF token.');
    }

    if ($isAdmin) {
        abort_request(403, 'You are signed in as an administrator. Log out first.');
    }

    // Only these four fields are read. Anything else the browser sends,
    // such as a "role" field, is ignored: new accounts are always customers.
    $field = fn(string $key): string =>
        is_string($_POST[$key] ?? null) ? $_POST[$key] : '';

    $values['name'] = trim($field('name'));
    $values['email'] = trim($field('email'));

    $result = register_customer(
        $pdo,
        $values['name'],
        $values['email'],
        $field('password'),
        $field('password_confirm')
    );

    if ($result['user'] !== null) {
        // Sign the new customer in. The cart is kept.
        auth_login($result['user']);

        flash_set('Welcome to FixerUpper, ' . $result['user']['name'] . '. Your account has been created.');

        header('Location: ' . take_return_url());
        exit;
    }

    $errors = $result['errors'];
}

$flash = flash_get();

$labels = [
    'name' => 'Full name',
    'email' => 'Email address',
    'password' => 'Password',
    'password_confirm' => 'Confirm password',
];

$pageTitle = 'Create an account';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-page">

    <div class="auth-card">

        <div class="auth-heading">

            <p class="eyebrow">Customer account</p>

            <h1>Create an account</h1>

            <p>
                You need an account to check out.
                Anything already in your cart will be kept.
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
                Log out before creating a customer account.
            </div>

        <?php else: ?>

            <?php if ($errors !== []): ?>
                <div class="error-box" role="alert" id="form-errors">
                    <strong>Please correct the following:</strong>
                    <ul>
                        <?php foreach ($errors as $name => $message): ?>
                            <li>
                                <a href="#<?= htmlspecialchars($name) ?>">
                                    <?= htmlspecialchars($labels[$name] ?? $name) ?>:
                                    <?= htmlspecialchars($message) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= url('register.php') ?>"
                class="auth-form"
                novalidate>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">
                    <label for="name">Full name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        value="<?= htmlspecialchars($values['name']) ?>"
                        autocomplete="name"
                        <?= field_aria('name', $errors) ?>
                        required>
                    <?php if (isset($errors['name'])): ?>
                        <p class="field-error" id="name-error"><?= htmlspecialchars($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="190"
                        value="<?= htmlspecialchars($values['email']) ?>"
                        autocomplete="email"
                        <?= field_aria('email', $errors) ?>
                        required>
                    <?php if (isset($errors['email'])): ?>
                        <p class="field-error" id="email-error"><?= htmlspecialchars($errors['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <p class="form-hint" id="password-hint">
                        At least <?= PASSWORD_MIN_LENGTH ?> characters.
                        A short phrase of several words is easy to remember and hard to guess.
                    </p>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="<?= PASSWORD_MIN_LENGTH ?>"
                        autocomplete="new-password"
                        <?= field_aria('password', $errors, 'password-hint') ?>
                        required>
                    <?php if (isset($errors['password'])): ?>
                        <p class="field-error" id="password-error"><?= htmlspecialchars($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        autocomplete="new-password"
                        <?= field_aria('password_confirm', $errors) ?>
                        required>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <p class="field-error" id="password_confirm-error"><?= htmlspecialchars($errors['password_confirm']) ?></p>
                    <?php endif; ?>
                </div>

                <button
                    type="submit"
                    class="button-primary auth-button">
                    Create account
                </button>

            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="<?= url('login.php') ?>">Log in</a>
            </p>

        <?php endif; ?>

    </div>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
