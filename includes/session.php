<?php

require_once __DIR__ . '/../config/app.php';

/**
 * True when this request arrived over HTTPS.
 */
function request_is_https(): bool
{
    return !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';
}

/**
 * Start the session with hardened cookie settings.
 *
 * Every page must start its session through this function so that
 * the same security settings are always applied.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        throw new RuntimeException(
            'Cannot start session after headers are sent.'
        );
    }

    // Reject session IDs the server did not create (session fixation).
    ini_set('session.use_strict_mode', '1');

    // Never accept a session ID from the URL, only from the cookie.
    ini_set('session.use_only_cookies', '1');

    // Keep session data (e.g. the cart) at least as long as a login can
    // last. PHP's default (24 minutes) is shorter than our 30-minute idle
    // timeout and could delete a guest's cart before it expires.
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);

    // Own cookie name, so FixerUpper never shares PHPSESSID
    // with other projects on the same localhost.
    session_name('FIXERUPPER_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,                 // expires when the browser closes
        'path' => BASE_URL . '/',        // only sent to FixerUpper pages
        // Sent over HTTPS only whenever the site is using HTTPS. It is off only
        // on plain-HTTP localhost, where a Secure cookie would never be sent.
        'secure' => request_is_https(),
        'httponly' => true,              // not readable by JavaScript (XSS)
        'samesite' => 'Lax',             // not sent on cross-site POSTs (CSRF)
    ]);

    session_start();
}
