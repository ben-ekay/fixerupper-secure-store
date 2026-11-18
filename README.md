# FixerUpper

FixerUpper is a demonstration hardware and home improvement store, built with
PHP, MySQL, HTML, CSS and JavaScript for the FdSc Computing module CMP214
(Secure Website Development). No real orders are fulfilled and no payments are
taken or stored.

## Features

- Product catalogue with departments, search, sorting and stock levels
- Guest shopping cart: add, update and remove items
- Customer registration, login, logout and order history
- Checkout for collection (free) or delivery (£4.99), with order confirmation
- Administrator area for products, categories and stock

## Security

- Passwords hashed with `password_hash()`; 12 character minimum and a 72 byte limit
- Prepared statements (PDO) for all database queries
- Output escaping with `htmlspecialchars()`
- CSRF tokens on every form that changes data
- Hardened session cookies, session ID regeneration, and idle and absolute timeouts
- Login throttling per email address and per IP address
- Role checks against the database on protected pages
- Orders placed in one transaction, with row locks and integer pence arithmetic
- One-time checkout tokens to prevent duplicate orders
- Order pages restricted to the customer who placed the order
- Apache rules blocking internal files and folder listings
- Security headers, including a strict Content Security Policy
- Generic public error pages, with details written to the server log only
- Products can be withdrawn from sale (`is_active`) without affecting past orders

## Installation (MAMP for Mac)

1. Place the application folder inside MAMP's `htdocs` directory, so that it is at
   `/Applications/MAMP/htdocs/fixerupper-summative`. **The folder must be named
   exactly `fixerupper-summative`** (not, for example, `fixerupper-summative-main` or
   `fixerupper-summative 2`), because page links, the session cookie and the product
   image paths stored in the database all use this name. If the folder is renamed or
   placed inside another folder, pages and images will not load correctly.
2. Start MAMP, then import **`database/install.sql`**. This is the only SQL file
   needed for a fresh installation. It creates the `fixerupper_summative` database,
   all tables and the catalogue, and uses no other database. In phpMyAdmin, use the
   Import tab with no database selected.
3. Copy `config/db.example.php` to `config/db.php` and enter your database username
   and password (on a default MAMP installation, both are `root`). The file already
   has MAMP's port, 8889; if your MySQL uses another port, change `DB_PORT`.
   `config/db.php` is ignored by Git.
4. Open `http://localhost:8888/fixerupper-summative/`.

### Trying the site

- **Customers:** add products to the cart as a guest, then register when you reach
  checkout. No accounts are included.
- **Administrator (optional):** no administrator account is included, so that no
  password is stored in the project. To create your own:
  1. In Terminal, create a hash of a password you choose:
     ```
     /Applications/MAMP/bin/php/php8.3.30/bin/php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
     ```
     (Use whichever PHP version your MAMP has, under `/Applications/MAMP/bin/php/`.)
  2. In phpMyAdmin, select `fixerupper_summative`, open the SQL tab and run this,
     pasting the hash in:
     ```sql
     INSERT INTO users (name, email, password, role)
     VALUES ('Admin', 'admin@example.com', 'PASTE-THE-HASH-HERE', 'admin');
     ```
  3. Sign in at `http://localhost:8888/fixerupper-summative/admin/login.php`.

### Database files

The submitted package contains one SQL file:

| File | Purpose |
|---|---|
| `database/install.sql` | Creates the complete database for a fresh installation. **Required.** |

The project's development history is kept in its Git repository. This history
includes the earlier schema versions, the migrations that upgraded the original
database step by step, and their rollback scripts. These files are **intentionally
excluded from the Turnitin ZIP**. They are not needed to install or run the site,
because `database/install.sql` already contains their final result. Do not import
them into a fresh installation.

### HTTPS in production

This project runs on plain HTTP on localhost. On a public server:

- Serve the whole site over HTTPS. The session cookie then gets the `Secure` flag
  and the `Strict-Transport-Security` header is sent automatically, because PHP
  sees `$_SERVER['HTTPS']`. Behind a proxy or load balancer, make sure that value
  is set correctly.
- Redirect all HTTP requests to HTTPS.
- Set `display_errors = Off` and `expose_php = Off` in `php.ini`, and
  `ServerTokens Prod` in the Apache configuration.
- Use a dedicated database user with only the permissions the site needs, not `root`.

## Project structure

```text
admin/        Administrator pages
assets/       Stylesheets, JavaScript and site images
config/       Application settings and database connection
database/     install.sql for a fresh installation (not accessible over the web)
includes/     Shared PHP: sessions, authentication, orders, helpers, templates
uploads/      Product images
```

## Image credits

The eight product images are AI-generated illustrations made for this project. They
are not photographs of real stock and show no real brand. They are listed on the
site's Image credits page (`image-credits.php`).
