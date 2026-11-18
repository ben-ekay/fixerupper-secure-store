<?php

/*
|--------------------------------------------------------------------------
| AUTHENTICATION — ADMINS AND CUSTOMERS
|--------------------------------------------------------------------------
|
| One login system is shared by both roles. The signed-in user is kept in
| $_SESSION['auth'], which holds ONE identity at a time, so a browser can
| never be an admin and a customer at once.
|
| The shopping cart lives separately in $_SESSION['cart'], so it survives
| logging in, registering and session timeouts, and is only removed by an
| explicit log out.
|
*/

require_once __DIR__ . '/session.php';

// Password rules. bcrypt (PHP's PASSWORD_DEFAULT) silently ignores every
// byte after the 72nd, so longer passwords are rejected instead of being
// quietly shortened.
const PASSWORD_MIN_LENGTH = 12;      // characters
const PASSWORD_MAX_BYTES = 72;       // bytes ("£" counts as 2 bytes)

// Login throttling, using the login_attempts table.
const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_FAILS_PER_EMAIL = 5;
const LOGIN_MAX_FAILS_PER_IP = 20;

// The only pages a user may be returned to after logging in.
const AUTH_RETURN_ALLOWLIST = ['checkout.php', 'my-orders.php', 'cart.php', 'index.php'];

// bcrypt hash of a random value nobody knows. When an email is not
// registered we still verify against this, so the response takes the
// same time and does not reveal which emails have accounts.
// Generated with password_hash() at PHP 8.3's default cost (10).
const DUMMY_PASSWORD_HASH =
    '$2y$10$Se5whXJWb2hMkUoiJYtm7efIyvj/ggAKnC3abCj6ljWB4rvG5nC42';


/*
|--------------------------------------------------------------------------
| SMALL HELPERS
|--------------------------------------------------------------------------
*/

function normalise_email(string $email): string
{
    return mb_strtolower(trim($email));
}

/**
 * The visitor's IP address as seen by the web server.
 * Forwarded headers (X-Forwarded-For) are ignored because any client can
 * set them to a fake value to dodge the IP limit.
 */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute(['email' => $email]);

    return $stmt->fetch() ?: null;
}

/**
 * Check a new password against the rules. Returns error messages keyed
 * by form field name; an empty array means the password is acceptable.
 */
function validate_new_password(string $password, string $confirm): array
{
    $errors = [];

    if (!mb_check_encoding($password, 'UTF-8') || str_contains($password, "\0")) {
        $errors['password'] = 'Password contains characters that cannot be used.';
    } elseif (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least '
            . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif (strlen($password) > PASSWORD_MAX_BYTES) {
        $errors['password'] = 'Password is too long. Use at most '
            . PASSWORD_MAX_BYTES . ' plain letters and numbers '
            . '(accented letters and symbols such as £ count double).';
    }

    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    return $errors;
}


/*
|--------------------------------------------------------------------------
| LOGIN THROTTLING
|--------------------------------------------------------------------------
|
| Each FAILED login is stored (email typed + IP, never the password).
| A login is refused while either limit is reached in the last 15 minutes.
| The limit applies whether or not the email exists, so it does not reveal
| which emails have accounts.
|
*/

function login_is_throttled(PDO $pdo, string $email, string $ip): bool
{
    $window = (int) LOGIN_WINDOW_MINUTES;

    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM login_attempts
             WHERE email = :email
               AND attempted_at > NOW() - INTERVAL $window MINUTE) AS email_fails,
            (SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip
               AND attempted_at > NOW() - INTERVAL $window MINUTE) AS ip_fails"
    );

    $stmt->execute([
        'email' => mb_substr($email, 0, 190),
        'ip' => $ip,
    ]);

    $counts = $stmt->fetch();

    return (int) $counts['email_fails'] >= LOGIN_MAX_FAILS_PER_EMAIL
        || (int) $counts['ip_fails'] >= LOGIN_MAX_FAILS_PER_IP;
}

function record_failed_login(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (email, ip_address)
         VALUES (:email, :ip)'
    );

    $stmt->execute([
        'email' => mb_substr($email, 0, 190),   // column is VARCHAR(190)
        'ip' => $ip,
    ]);

    // Housekeeping: old rows are no longer needed.
    $pdo->exec(
        'DELETE FROM login_attempts
         WHERE attempted_at < NOW() - INTERVAL 1 DAY'
    );
}

function clear_failed_logins(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM login_attempts
         WHERE email = :email'
    );

    $stmt->execute(['email' => mb_substr($email, 0, 190)]);
}


/*
|--------------------------------------------------------------------------
| LOGIN AND REGISTRATION
|--------------------------------------------------------------------------
*/

/**
 * Check an email and password for the given role ('admin' or 'customer').
 *
 * Returns ['user' => array|null, 'error' => string|null].
 * Wrong email, wrong password and wrong role all give the same message.
 */
function attempt_login(
    PDO $pdo,
    string $email,
    string $password,
    string $requiredRole
): array {
    $email = normalise_email($email);
    $ip = client_ip();

    if (login_is_throttled($pdo, $email, $ip)) {
        return [
            'user' => null,
            'error' => 'Too many failed sign-in attempts. Please wait '
                . LOGIN_WINDOW_MINUTES . ' minutes and try again.',
        ];
    }

    $user = find_user_by_email($pdo, $email);

    // Passwords that registration would never accept cannot match an
    // account, and would make password_verify() throw (NUL byte).
    $passwordUsable = strlen($password) <= PASSWORD_MAX_BYTES
        && !str_contains($password, "\0");

    // Always run password_verify(), even for unknown emails, so the
    // response time does not reveal whether the account exists.
    $passwordOk = password_verify(
        $passwordUsable ? $password : '',
        $user['password'] ?? DUMMY_PASSWORD_HASH
    );

    if (
        $user === null
        || !$passwordUsable
        || !$passwordOk
        || $user['role'] !== $requiredRole
    ) {
        record_failed_login($pdo, $email, $ip);

        return [
            'user' => null,
            'error' => 'Invalid email or password.',
        ];
    }

    clear_failed_logins($pdo, $email);

    // Upgrade the stored hash if PHP's recommended settings have changed.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $stmt = $pdo->prepare('UPDATE users SET password = :hash WHERE id = :id');
        $stmt->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
    }

    return ['user' => $user, 'error' => null];
}

/**
 * Create a customer account.
 *
 * Returns ['user' => array|null, 'errors' => array keyed by field name].
 * The role is always 'customer': it is never read from the request.
 */
function register_customer(
    PDO $pdo,
    string $name,
    string $email,
    string $password,
    string $confirm
): array {
    $errors = [];

    $name = trim($name);
    $email = normalise_email($email);

    if ($name === '') {
        $errors['name'] = 'Enter your full name.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be 100 characters or fewer.';
    }

    if ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif (
        mb_strlen($email) > 190
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors['email'] = 'Enter a valid email address, like name@example.com.';
    }

    $errors += validate_new_password($password, $confirm);

    if (!isset($errors['email']) && find_user_by_email($pdo, $email) !== null) {
        $errors['email'] = 'An account with this email address already exists. '
            . 'Please log in instead.';
    }

    if ($errors !== []) {
        return ['user' => null, 'errors' => $errors];
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, role)
             VALUES (:name, :email, :password, 'customer')"
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $e) {
        // 1062 = duplicate key: someone registered the same email
        // between our check above and this insert.
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return [
                'user' => null,
                'errors' => ['email' => 'An account with this email address already exists. '
                    . 'Please log in instead.'],
            ];
        }

        throw $e;
    }

    return [
        'user' => [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
        ],
        'errors' => [],
    ];
}


/*
|--------------------------------------------------------------------------
| SESSION IDENTITY
|--------------------------------------------------------------------------
*/

/**
 * Sign a user in. Must be called before any page output.
 */
function auth_login(array $user): void
{
    start_secure_session();

    // New session ID on every login: an attacker who planted or saw the
    // old ID (session fixation) cannot use it. Session data, including
    // the cart, is carried over to the new ID.
    session_regenerate_id(true);

    // New CSRF token for the new privilege level.
    unset($_SESSION['csrf_token']);

    $_SESSION['auth'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'login_at' => time(),
        'last_activity' => time(),
    ];
}

/**
 * Remove the signed-in identity but KEEP the rest of the session (cart).
 * Used for timeouts and accounts that are no longer authorised.
 */
function auth_end_identity(?string $message = null): void
{
    start_secure_session();

    unset($_SESSION['auth'], $_SESSION['csrf_token']);

    session_regenerate_id(true);

    if ($message !== null) {
        flash_set($message, 'warning');
    }
}

/**
 * Explicit log out: remove EVERYTHING, including the cart, and delete
 * the session cookie. Suitable for shared computers.
 */
function auth_logout(): void
{
    start_secure_session();

    $_SESSION = [];

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]
    );

    session_destroy();
}

/**
 * The signed-in user, or null. Also enforces the session timeouts, so
 * every check of "who is logged in" goes through this function.
 *
 * Call it before any page output (header.php does this at the top).
 */
function auth_user(): ?array
{
    start_secure_session();

    $auth = $_SESSION['auth'] ?? null;

    if (!is_array($auth) || !isset($auth['id'], $auth['role'])) {
        return null;
    }

    $now = time();

    $idle = $now - (int) ($auth['last_activity'] ?? 0);
    $age = $now - (int) ($auth['login_at'] ?? 0);

    if ($idle > SESSION_IDLE_TIMEOUT || $age > SESSION_ABSOLUTE_TIMEOUT) {
        auth_end_identity('Your session has expired. Please log in again.');
        return null;
    }

    $_SESSION['auth']['last_activity'] = $now;

    return $_SESSION['auth'];
}

function admin_is_logged_in(): bool
{
    return (auth_user()['role'] ?? null) === 'admin';
}

function customer_is_logged_in(): bool
{
    return (auth_user()['role'] ?? null) === 'customer';
}

/**
 * Re-read the signed-in user from the database. If the account has been
 * deleted or its role changed, the session is no longer trusted: the
 * identity is removed (cart kept) and false is returned.
 */
function auth_matches_database(PDO $pdo): bool
{
    $auth = auth_user();

    if ($auth === null) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, role
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $auth['id']]);

    $user = $stmt->fetch();

    if (!$user || $user['role'] !== $auth['role']) {
        auth_end_identity(
            'Your account is no longer authorised for this area. Please log in again.'
        );
        return false;
    }

    // Keep the displayed name and email up to date.
    $_SESSION['auth']['name'] = $user['name'];
    $_SESSION['auth']['email'] = $user['email'];

    return true;
}


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

/**
 * Admin-only pages. Guests go to the admin login; customers get 403.
 */
function require_admin(?PDO $pdo = null): void
{
    $auth = auth_user();

    if ($auth !== null && $auth['role'] !== 'admin') {
        abort_request(403, 'This area is for administrators only.');
    }

    if ($auth === null || !auth_matches_database($pdo ?? get_db())) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

/**
 * Shopping cart pages and actions. Guests and customers may shop; an
 * administrator gets 403, so an admin account can never fill a cart or buy,
 * even by sending a request directly instead of using the page.
 */
function require_shopper(): void
{
    if (admin_is_logged_in()) {
        abort_request(
            403,
            'Administrator accounts cannot use the shopping cart. '
                . 'Log out and use a customer account to shop.'
        );
    }
}

/**
 * Customer-only pages (checkout, order history). Guests are sent to log
 * in and then returned to $returnPage; admins get 403.
 *
 * Returns the signed-in customer.
 */
function require_customer(string $returnPage, ?PDO $pdo = null): array
{
    $auth = auth_user();

    if ($auth !== null && $auth['role'] !== 'customer') {
        abort_request(
            403,
            'Administrator accounts cannot use customer pages. '
                . 'Log out and sign in with a customer account.'
        );
    }

    if ($auth === null) {
        remember_return_page($returnPage);

        flash_set(
            'Please log in or create an account to continue.',
            'info'
        );

        header('Location: ' . url('login.php'));
        exit;
    }

    // Session says "customer" but the database disagrees (account deleted
    // or role changed). auth_matches_database() has already removed the
    // identity and set an explanatory message.
    if (!auth_matches_database($pdo ?? get_db())) {
        remember_return_page($returnPage);

        header('Location: ' . url('login.php'));
        exit;
    }

    // Customer pages show personal details: do not let the browser or a
    // shared computer keep a copy, and make "Back" reload fresh data.
    header('Cache-Control: no-store');

    return auth_user();
}


/*
|--------------------------------------------------------------------------
| RETURN AFTER LOGIN
|--------------------------------------------------------------------------
|
| The page to return to is kept in the session (not in the URL) and must
| be an exact entry in AUTH_RETURN_ALLOWLIST, so the login form can never
| be used to redirect someone to another website (open redirect).
|
*/

function remember_return_page(mixed $page): void
{
    if (is_string($page) && in_array($page, AUTH_RETURN_ALLOWLIST, true)) {
        start_secure_session();
        $_SESSION['after_login'] = $page;
    }
}

function take_return_url(): string
{
    start_secure_session();

    $page = $_SESSION['after_login'] ?? 'index.php';
    unset($_SESSION['after_login']);

    if (!in_array($page, AUTH_RETURN_ALLOWLIST, true)) {
        $page = 'index.php';
    }

    return url($page);
}
