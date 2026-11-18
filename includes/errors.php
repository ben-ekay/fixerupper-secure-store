<?php

/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
|
| Visitors only ever see a generic message. The details (error message, file
| and line) go to the server's PHP error log, where developers can read them.
| Showing errors on screen could reveal file paths, SQL or database details.
|
*/

const PUBLIC_ERROR_MESSAGE =
    'Sorry, something went wrong on our side. Please try again later.';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Leave function arguments (which could include passwords) out of stack traces.
ini_set('zend.exception_ignore_args', '1');

error_reporting(E_ALL);

/**
 * Show the generic error page, discarding any half-finished page output.
 */
function show_public_error(): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    try {
        abort_request(500, PUBLIC_ERROR_MESSAGE);
    } catch (Throwable $e) {
        error_log('Could not show the error page: ' . $e->getMessage());

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo htmlspecialchars(PUBLIC_ERROR_MESSAGE);

        exit;
    }
}

// Any exception that no page has caught.
set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        'Uncaught %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    show_public_error();
});

// Fatal errors stop the script before the handler above can run. PHP has
// already logged them, so only the visitor's message is needed here.
register_shutdown_function(function (): void {
    $error = error_get_last();

    if (
        $error === null
        || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
    ) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo htmlspecialchars(PUBLIC_ERROR_MESSAGE);
});
