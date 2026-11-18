<?php

/*
|--------------------------------------------------------------------------
| LOG OUT (customers and administrators)
|--------------------------------------------------------------------------
|
| POST only, with a CSRF token, so another website cannot log a user out
| with a hidden link or image. Removes the whole session, including the
| cart, and deletes the session cookie.
|
*/

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_request(405, 'Method not allowed.');
}

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    abort_request(403, 'Invalid CSRF token.');
}

auth_logout();

// A fresh, empty session just to carry the confirmation message.
flash_set('You have been logged out.', 'info');

header('Location: ' . url('index.php'));
exit;
