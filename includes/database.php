<?php

declare(strict_types=1);

function get_database_connection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $configFile = __DIR__ . '/../config/database.php';

    if (!is_file($configFile)) {
        throw new RuntimeException(
            'Database configuration is missing. Create config/database.php from the example file.'
        );
    }

    $config = require $configFile;

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $requiredKeys = ['host', 'port', 'database', 'username', 'password', 'charset'];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Database configuration is incomplete.');
        }
    }

    $host = (string) $config['host'];
    $port = filter_var($config['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    $database = (string) $config['database'];
    $username = (string) $config['username'];
    $password = (string) $config['password'];
    $charset = (string) $config['charset'];

    if (!preg_match('/\A[a-zA-Z0-9.-]+\z/', $host)) {
        throw new RuntimeException('Database host is invalid.');
    }

    if ($port === false) {
        throw new RuntimeException('Database port is invalid.');
    }

    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $database)) {
        throw new RuntimeException('Database name is invalid.');
    }

    if ($username === '' || $charset !== 'utf8mb4') {
        throw new RuntimeException('Database configuration is invalid.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $exception) {
        error_log('Database connection failed with PDO code: ' . $exception->getCode());
        throw new RuntimeException('Database connection failed.');
    }

    return $connection;
}
