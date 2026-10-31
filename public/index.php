<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if ($configMissing || (string) Config::get('key', '') === '') {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $hint = $configMissing
        ? '<code>config.php</code> fehlt. Kopiere <code>config.example.php</code> nach <code>config.php</code> und trage Datenbank, SMTP und Schlüssel ein.'
        : 'In <code>config.php</code> ist <code>key</code> leer. Trage diesen frisch erzeugten Schlüssel ein und sichere ihn gut:<br><code>' . htmlspecialchars(Crypto::generateKey()) . '</code>';
    echo '<!doctype html><meta charset="utf-8"><title>meterio – Einrichtung</title><body style="font-family:sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem"><h1>⚡ meterio by MeckByte</h1><p>' . $hint . '</p></body>';
    exit;
}

$r = new Router();

// Rechtliches & Versionen
$r->get('/impressum', ['PageController', 'impressum']);
$r->get('/datenschutz', ['PageController', 'privacy']);
$r->get('/changelog', ['PageController', 'changelog']);

// Auth
$r->get('/', ['DashboardController', 'home']);
$r->get('/setup', ['AuthController', 'setupForm']);
$r->post('/setup', ['AuthController', 'setupStore']);
$r->get('/login', ['AuthController', 'loginForm']);
$r->post('/login', ['AuthController', 'login']);
$r->get('/login/2fa', ['AuthController', 'twofaForm']);
$r->post('/login/2fa', ['AuthController', 'twofaVerify']);
$r->post('/login/2fa/resend', ['AuthController', 'twofaResend']);
$r->post('/logout', ['AuthController', 'logout']);
$r->get('/forgot', ['AuthController', 'forgotForm']);
$r->post('/forgot', ['AuthController', 'forgot']);
$r->get('/reset/{token}', ['AuthController', 'resetForm']);
$r->post('/reset/{token}', ['AuthController', 'reset']);
$r->get('/invite/{token}', ['AuthController', 'resetForm']);
$r->post('/invite/{token}', ['AuthController', 'reset']);
$r->get('/password/force', ['AuthController', 'forceForm']);
$r->post('/password/force', ['AuthController', 'force']);

// 2FA-Einrichtung
$r->get('/2fa/setup', ['TwoFactorController', 'setup']);
$r->post('/2fa/email/send', ['TwoFactorController', 'emailSend']);
$r->post('/2fa/email/confirm', ['TwoFactorController', 'emailConfirm']);
$r->post('/2fa/totp/confirm', ['TwoFactorController', 'totpConfirm']);
$r->post('/2fa/totp/reset', ['TwoFactorController', 'totpReset']);
$r->get('/2fa/backup-codes', ['TwoFactorController', 'backupCodes']);
$r->post('/2fa/backup-codes/regenerate', ['TwoFactorController', 'backupRegenerate']);

// Profil
$r->get('/profile', ['ProfileController', 'index']);
$r->post('/profile', ['ProfileController', 'update']);
$r->post('/profile/password', ['ProfileController', 'password']);

// Dashboard
$r->get('/dashboard', ['DashboardController', 'index']);
$r->get('/api/chart', ['DashboardController', 'chart']);

// Zähler
$r->get('/meters', ['MeterController', 'index']);
$r->get('/meters/new', ['MeterController', 'create']);
$r->post('/meters', ['MeterController', 'store']);
$r->get('/meters/{id}', ['MeterController', 'show']);
$r->get('/meters/{id}/stats', ['MeterController', 'stats']);
$r->get('/meters/{id}/edit', ['MeterController', 'edit']);
$r->post('/meters/{id}', ['MeterController', 'update']);
$r->post('/meters/{id}/delete', ['MeterController', 'delete']);

// Ablesungen & Fotos
$r->get('/meters/{id}/readings/new', ['ReadingController', 'create']);
$r->post('/meters/{id}/readings/check', ['ReadingController', 'check']);
$r->post('/meters/{id}/readings', ['ReadingController', 'store']);
$r->get('/readings/{id}/edit', ['ReadingController', 'edit']);
$r->post('/readings/{id}', ['ReadingController', 'update']);
$r->post('/readings/{id}/delete', ['ReadingController', 'delete']);
$r->get('/photos/{id}', ['ReadingController', 'photo']);
$r->post('/photos/{id}/delete', ['ReadingController', 'photoDelete']);

// Verträge & Zahlungen
$r->get('/contracts', ['ContractController', 'index']);
$r->get('/contracts/new', ['ContractController', 'create']);
$r->post('/contracts', ['ContractController', 'store']);
$r->get('/contracts/{id}', ['ContractController', 'show']);
$r->get('/contracts/{id}/edit', ['ContractController', 'edit']);
$r->post('/contracts/{id}', ['ContractController', 'update']);
$r->post('/contracts/{id}/delete', ['ContractController', 'delete']);
$r->post('/contracts/{id}/payments', ['ContractController', 'paymentStore']);
$r->post('/payments/{id}', ['ContractController', 'paymentUpdate']);
$r->post('/payments/{id}/delete', ['ContractController', 'paymentDelete']);

// Admin
$r->get('/admin/users', ['AdminController', 'index']);
$r->get('/admin/users/new', ['AdminController', 'create']);
$r->post('/admin/users', ['AdminController', 'store']);
$r->post('/admin/users/{id}/toggle', ['AdminController', 'toggle']);
$r->post('/admin/users/{id}/reset-2fa', ['AdminController', 'reset2fa']);
$r->post('/admin/users/{id}/reset-password', ['AdminController', 'resetPassword']);
$r->post('/admin/users/{id}/delete', ['AdminController', 'delete']);

try {
    $r->dispatch();
} catch (Throwable $e) {
    error_log((string) $e);
    abort(500, Config::get('app.debug') ? $e->getMessage() : 'Es ist ein interner Fehler aufgetreten.');
}
