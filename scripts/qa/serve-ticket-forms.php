<?php

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$public = realpath(dirname(__DIR__, 2).'/public');
$path = realpath($public.rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if ($path && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path)) {
    return false;
}

require __DIR__.'/ticket-environment.php';
ticketQaEnvironment();
require $public.'/index.php';
