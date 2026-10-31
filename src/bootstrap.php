<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
const APP_VERSION = '1.2.0';
const APP_BRAND   = 'meterio by MeckByte';

spl_autoload_register(static function (string $class): void {
    foreach ([ROOT . '/src/', ROOT . '/src/Controllers/'] as $dir) {
        $f = $dir . $class . '.php';
        if (is_file($f)) {
            require $f;
            return;
        }
    }
});

require ROOT . '/src/helpers.php';

$configMissing = !is_file(ROOT . '/config.php');
if (!$configMissing) {
    Config::load();
}

$debug = !$configMissing && (bool) Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/error.log');

date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Berlin'));
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || str_starts_with((string) Config::get('app.url', ''), 'https://');

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: blob:; "
        . "font-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    $sessDir = ROOT . '/storage/sessions';
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('meterio_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() ?: '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
