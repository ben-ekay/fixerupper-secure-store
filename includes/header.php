<?php
require_once __DIR__ . '/functions.php';

start_secure_session();

// Work out who is signed in BEFORE any HTML is sent: if the session has
// timed out, auth_user() issues a new session cookie, which must happen
// before output starts.
$authUser = auth_user();
$authRole = $authUser['role'] ?? null;

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
);

$isProducts =
    $currentPath === url()
    || $currentPath === BASE_URL
    || $currentPath === url('index.php')
    || $currentPath === url('product.php');

$isDashboard =
    $currentPath === url('admin/')
    || $currentPath === url('admin/index.php')
    || $currentPath === url('edit.php');

$isCategories =
    $currentPath === url('admin/categories.php');

// Admin screens also load the older admin stylesheet.
$isAdminPage =
    str_starts_with((string) $currentPath, url('admin/'))
    || $currentPath === url('add.php')
    || $currentPath === url('edit.php');

// Pages can set $pageTitle before including this file.
$documentTitle = isset($pageTitle)
    ? $pageTitle . ' | FixerUpper'
    : 'FixerUpper | Tools and home improvement';
?>
<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="FixerUpper: quality tools, electrical essentials and everything you need to improve your home.">

    <title><?= htmlspecialchars($documentTitle) ?></title>

    <link rel="icon" href="<?= url('assets/images/logo-mark.svg') ?>" type="image/svg+xml">

    <?php if ($isAdminPage): ?>
        <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=21">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= url('assets/css/store.css') ?>?v=6">
</head>

<body>

    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">

        <div class="header-container">

            <a class="brand" href="<?= url('index.php') ?>">
                <img class="brand-mark" src="<?= url('assets/images/logo-mark.svg') ?>" alt="">
                <span class="brand-text">FixerUpper</span>
            </a>

            <nav class="main-nav" aria-label="Main">

                <a
                    href="<?= url('index.php') ?>#catalog"
                    class="<?= $isProducts ? 'nav-active' : '' ?>">
                    Shop
                </a>

                <?php if ($authRole === 'admin'): ?>

                    <a
                        href="<?= url('admin/') ?>"
                        class="<?= $isDashboard ? 'nav-active' : '' ?>">
                        Dashboard
                    </a>

                    <a
                        href="<?= url('admin/categories.php') ?>"
                        class="<?= $isCategories ? 'nav-active' : '' ?>">
                        Categories
                    </a>

                <?php elseif ($authRole === 'customer'): ?>

                    <a
                        href="<?= url('my-orders.php') ?>"
                        class="<?= $currentPath === url('my-orders.php') ? 'nav-active' : '' ?>">
                        My orders
                    </a>

                    <span class="nav-greeting">
                        Hi, <?= htmlspecialchars(
                                strtok($authUser['name'], ' ') ?: $authUser['name']
                            ) ?>
                    </span>

                <?php else: ?>

                    <a
                        href="<?= url('login.php') ?>"
                        class="<?= $currentPath === url('login.php') ? 'nav-active' : '' ?>">
                        Log in
                    </a>

                    <a
                        href="<?= url('register.php') ?>"
                        class="<?= $currentPath === url('register.php') ? 'nav-active' : '' ?>">
                        Register
                    </a>

                <?php endif; ?>

                <?php if ($authRole !== null): ?>

                    <form
                        method="post"
                        action="<?= url('logout.php') ?>"
                        class="nav-logout-form">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(csrf_token()) ?>">

                        <button
                            type="submit"
                            class="nav-link-button">
                            Log out
                        </button>
                    </form>

                <?php endif; ?>

                <?php if ($authRole !== 'admin'): ?>

                <a
                    href="<?= url('cart.php') ?>"
                    class="<?= $currentPath === url('cart.php') ? 'nav-active' : '' ?> cart-nav-link">
                    <svg class="cart-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11h10.2L20 7H6"/>
                        <circle cx="9" cy="19.5" r="1.5" fill="currentColor"/>
                        <circle cx="17" cy="19.5" r="1.5" fill="currentColor"/>
                    </svg>
                    Cart

                    <?php if (cart_count() > 0): ?>
                        <span class="cart-count">
                            <?= (int) cart_count() ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php endif; ?>

            </nav>

        </div>

    </header>

    <main class="main-content" id="main-content">
