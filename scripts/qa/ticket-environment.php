<?php

// Configuración exclusiva del servidor y la base de pruebas; no modifica .env.
function ticketQaEnvironment(): string
{
    $root = dirname(__DIR__, 2);
    $directory = $root.'/storage/framework/testing/ticket-forms';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    if (! is_dir($directory.'/views')) {
        mkdir($directory.'/views', 0777, true);
    }
    foreach ([
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://127.0.0.1:8017',
        'APP_CONFIG_CACHE' => $directory.'/unused-config.php', 'APP_ROUTES_CACHE' => $directory.'/unused-routes.php',
        'VIEW_COMPILED_PATH' => $directory.'/views',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/qa.sqlite', 'DB_URL' => '',
        'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'zolic_ticket_forms_qa',
        'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
        'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
        'BROADCAST_CONNECTION' => 'null', 'TURNSTILE_ENABLED' => 'false',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    return $directory;
}
