<?php

/**
 * Database connection.
 *
 * Copy this file to config/db.php and enter your own details. config/db.php is
 * ignored by Git, so real passwords are never committed.
 *
 * Keep DB_NAME as fixerupper_summative: every script in database/ installs into
 * that database.
 */

const DB_HOST = 'localhost';
const DB_PORT = '8889';
const DB_NAME = 'fixerupper_summative';
const DB_USER = 'YOUR_DATABASE_USERNAME';
const DB_PASS = 'YOUR_DATABASE_PASSWORD';

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
            . ';port=' . DB_PORT
            . ';dbname=' . DB_NAME
            . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // The details go to the server log only. A new exception is thrown
            // so the original, which may hold connection details, goes no further.
            error_log('Database connection failed: ' . $e->getMessage());

            throw new RuntimeException('Database connection failed.');
        }
    }

    return $pdo;
}
