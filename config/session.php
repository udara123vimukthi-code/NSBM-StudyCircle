<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Local WAMP uses HTTP. The hosted website will use HTTPS.
    $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isHttps = $httpsValue !== ''
        && $httpsValue !== 'off'
        && $httpsValue !== '0';

    session_name('STUDYCIRCLE_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}