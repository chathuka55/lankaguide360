<?php
/**
 * LankaGuide 360 — Database Connection
 * Uses PDO with prepared statements throughout the app to prevent SQL injection.
 * Update the credentials below to match your local XAMPP / WAMP / LAMP setup.
 */

// Credentials fall back to standard XAMPP defaults but can be overridden with
// environment variables (used by the Docker image and Kubernetes manifests).
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'lankaguide360');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem"><h2>Connection error</h2>'
                . '<p>LankaGuide 360 could not reach the database. Make sure MySQL is running and that '
                . '<code>database/lankaguide360.sql</code> has been imported. See README.md for setup steps.</p></div>');
        }
    }

    return $pdo;
}
