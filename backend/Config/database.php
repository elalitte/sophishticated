<?php
/**
 * PDO singleton factory.
 *
 * Reads connection parameters from $_ENV (loaded by phpdotenv in bootstrap.php).
 * Returns the same PDO instance on every call.
 */

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port     = $_ENV['DB_PORT']     ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? 'larreere_phish';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $socket = $_ENV['DB_SOCKET'] ?? '/var/run/mysqld/mysqld.sock';
        if ($host === '127.0.0.1' || $host === 'localhost') {
            $dsn = "mysql:unix_socket={$socket};dbname={$database};charset=utf8mb4";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
