<?php

/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
|
| Sent with every PHP page. Static files get their headers from .htaccess.
|
| Content-Security-Policy tells the browser to load scripts, styles, images
| and fonts only from this site, and refuses inline scripts and styles. This
| limits the damage if an XSS bug is ever found. For it to work, all JavaScript
| lives in assets/js/ and all CSS in assets/css/: no onclick="" or style="".
|
*/

const CONTENT_SECURITY_POLICY =
    "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self'; "
    . "img-src 'self'; "
    . "font-src 'self'; "
    . "connect-src 'self'; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'";

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    // Do not advertise the PHP version.
    header_remove('X-Powered-By');

    // Do not let browsers guess a file's type (e.g. run an upload as a script).
    header('X-Content-Type-Options: nosniff');

    // Do not let other sites load these pages in a frame (clickjacking).
    // frame-ancestors in the CSP does the same job in modern browsers.
    header('X-Frame-Options: DENY');

    // Do not send full page addresses to other websites.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // This site does not need these browser features.
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    header('Content-Security-Policy: ' . CONTENT_SECURITY_POLICY);

    // Tell browsers to insist on HTTPS, but only once the site is using it.
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
