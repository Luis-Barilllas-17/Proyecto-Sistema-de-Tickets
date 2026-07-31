<?php

return [
    'institutional_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INSTITUTIONAL_EMAIL_DOMAINS', 'zolicguate.com'))
    ))),

    'max_login_attempts' => (int) env('SECURITY_MAX_LOGIN_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('SECURITY_LOCKOUT_MINUTES', 15),
    'session_timeout_minutes' => (int) env('SECURITY_SESSION_TIMEOUT', 30),
];
