<?php

declare(strict_types=1);

return [
    'host' => getenv('RFH_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('RFH_DB_PORT') ?: 3306),
    'database' => getenv('RFH_DB_NAME') ?: 'researchflow_db',
    'username' => getenv('RFH_DB_USER') ?: 'root',
    'password' => getenv('RFH_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'ssl_ca' => getenv('RFH_DB_SSL_CA') ?: '',
    'ssl_verify_server_cert' => filter_var(
        getenv('RFH_DB_SSL_VERIFY') ?: false,
        FILTER_VALIDATE_BOOLEAN
    ),
];
