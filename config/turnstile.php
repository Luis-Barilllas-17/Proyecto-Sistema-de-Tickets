<?php

$applicationHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);

return [
    'enabled' => (bool) env('TURNSTILE_ENABLED', env('APP_ENV') === 'production'),
    'site_key' => (string) env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
    'expected_hostname' => strtolower((string) env('TURNSTILE_EXPECTED_HOSTNAME', $applicationHost ?: '')),
    'expected_action' => (string) env('TURNSTILE_EXPECTED_ACTION', 'login'),
    'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    'timeout_seconds' => max(2, min(10, (int) env('TURNSTILE_TIMEOUT_SECONDS', 7))),
];
