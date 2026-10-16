<?php
/**
 * BSD Database Connection
 * Matches C:\Users\BSD\Desktop\BSD\bsd\db.php PDO PostgreSQL setup.
 * Includes graceful fallback so the app works standalone even if Postgres is stopped.
 */

require_once __DIR__ . '/config.php';

$pdo = null;

try {
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;",
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2,
    ];

    if (extension_loaded('pdo_pgsql')) {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
} catch (\Throwable $e) {
    // Database optional during standalone operation (JSON store acts as primary fallback)
    $pdo = null;
}
