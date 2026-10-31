<?php
declare(strict_types=1);

final class AuthController
{
    private const GENERIC_FAIL = 'E-Mail oder Passwort ist falsch, oder das Konto ist gesperrt.';

    // ---------- Erst-Einrichtung (nur solange kein Benutzer existiert) ----------
    public function setupForm(): void
    {
        $this->guardSetup();
        view('auth/setup', ['title' => 'Ersteinrichtung', 'bare' => true, 'old' => []]);
    }

    public function setupStore(): void
    {
        $this->guardSetup();
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = mb_trim_len((string) ($_POST['name'] ?? ''), 120);
        $pw = (string) ($_POST['password'] ?? '');
        $err = null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } elseif ($name === '') {
            $err = 'Bitte einen Namen angeben.';
        } elseif ($pw !== (string) ($_POST['password2'] ?? '')) {
            $err = 'Die Passwörter stimmen nicht überein.';
        } else {
            $err = Auth::passwordError($pw);
        }
        if ($err) {
            flash('error', $err);
            view('auth/setup', ['title' => 'Ersteinrichtung', 'bare' => true, 'old' => ['email' => $email, 'name' => $name]]);
            return;
        }
        Auth::createUser($email, $name, $pw, 'admin');
        flash('ok', 'Admin-Konto angelegt. Bitte jetzt anmelden und die Zwei-Faktor-Authentifizierung einrichten.');
        redirect('login');
    }

    private function guardSetup(): void
    {
        if ((int) Db::val('SELECT COUNT(*) FROM users') > 0) {
            redirect('login');
        }
    }

    // ---------- Login ----------
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect(Auth::home());
        }
        if ((int) Db::val('SELECT COUNT(*) FROM users') === 0) {
            redirect('setup');
        }
        view('auth/login', ['title' => 'Anmelden', 'bare' => true, 'email' => '']);
    }

    public function login(): void
    {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $pw = (string) ($_POST['password'] ?? '');

        if (Auth::tooMany('login', $email)) {
            flash('error', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
            view('auth/login', ['title' => 'Anmelden', 'bare' => true, 'email' => $email]);
            return;
        }
        $user = Auth::findByEmail($email);
        // Konstante Laufzeit auch bei unbekannter E-Mail
        $hash = $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJp3sQ0e9kJHqcZ9dK4m1o8wq1rYhX2a';
        $ok = password_verify($pw, $hash) && $user && $user['is_active'];

        if (!$ok) {
            Auth::fail('login', $email);
            flash('error', self::GENERIC_FAIL);
            view('auth/login', ['title' => 'Anmelden', 'bare' => true, 'email' => $email]);
            return;
        }
        Auth::clearFails('login', $email);
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Db::exec('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($pw), $user['id']]);
        }
        Auth::startPre($user);
        $this->nextStep($user, true);
    }

    /** Leitet je nach Zustand zur Passwortänderung, 2FA-Einrichtung oder 2FA-Prüfung. */
    private function nextStep(array $user, bool $sendOtp): never
    {
        if ($user['force_pw_change']) {
            redirect('password/force');
        }
        if (!$user['twofa_ready']) {
            redirect('2fa/setup');
        }
        if ($user['twofa_method'] === 'email' && $sendOtp) {
            Auth::sendEmailOtp($user);
        }
        redirect('login/2fa');
    }

    public function twofaForm(): void
    {
        $user = Auth::pre();
        if (!$user) {
            redirect('login');
        }
        view('auth/twofa', ['title' => 'Zwei-Faktor-Code', 'bare' => true, 'method' => $user['twofa_method'], 'email' => $user['email']]);
    }

    public function twofaVerify(): void
    {
        $user = Auth::pre();
        if (!$user) {
            redirect('login');
        }
        if (Auth::tooMany('2fa', $user['email'])) {
            unset($_SESSION['pre']);
            flash('error', 'Zu viele Fehlversuche. Bitte später erneut anmelden.');
            redirect('login');
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $ok = false;
        $isBackup = preg_match('/^[A-Za-z0-9]{5}-?[A-Za-z0-9]{5}$/', $code) === 1;
        if ($isBackup) {
            $ok = Auth::useBackupCode((int) $user['id'], $code);
        } elseif ($user['twofa_method'] === 'totp') {
            $ok = Auth::verifyTotp($user, $code);
        } else {
            $ok = Auth::verifyEmailOtp((int) $user['id'], $code);
        }
        if (!$ok) {
            Auth::fail('2fa', $user['email']);
            flash('error', 'Der Code ist ungültig oder abgelaufen.');
            redirect('login/2fa');
        }
        Auth::clearFails('2fa', $user['email']);
        Auth::complete($user);
        if ($isBackup) {
            $left = Auth::unusedBackupCodes((int) $user['id']);
            flash('warn', "Ein Backup-Code wurde verwendet. Noch $left übrig – im Profil kannst du neue erzeugen.");
        }
        redirect(Auth::home($user));
    }

    public function twofaResend(): void
    {
        $user = Auth::pre();
        if (!$user || $user['twofa_method'] !== 'email') {
            redirect('login');
        }
        if (time() - (int) ($_SESSION['otp_sent'] ?? 0) < 30) {
            flash('warn', 'Bitte einen Moment warten, bevor ein neuer Code angefordert wird.');
            redirect('login/2fa');
        }
        $_SESSION['otp_sent'] = time();
        Auth::sendEmailOtp($user);
        flash('ok', 'Ein neuer Code wurde gesendet.');
        redirect('login/2fa');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('ok', 'Du wurdest abgemeldet.');
        redirect('login');
    }

    // ---------- Erzwungene Passwortänderung (temporäres Passwort) ----------
    public function forceForm(): void
    {
        $user = Auth::pre();
        if (!$user || !$user['force_pw_change']) {
            redirect('login');
        }
        view('auth/force_password', ['title' => 'Neues Passwort', 'bare' => true]);
    }

    public function force(): void
    {
        $user = Auth::pre();
        if (!$user || !$user['force_pw_change']) {
            redirect('login');
        }
        $pw = (string) ($_POST['password'] ?? '');
        $err = $pw !== (string) ($_POST['password2'] ?? '') ? 'Die Passwörter stimmen nicht überein.' : Auth::passwordError($pw);
        if (!$err && password_verify($pw, $user['password_hash'])) {
            $err = 'Das neue Passwort muss sich vom temporären unterscheiden.';
        }
        if ($err) {
            flash('error', $err);
            redirect('password/force');
        }
        Db::exec('UPDATE users SET password_hash = ?, force_pw_change = 0 WHERE id = ?', [Auth::hash($pw), $user['id']]);
        $user = Auth::findById((int) $user['id']);
        $this->nextStep($user, true);
    }

    // ---------- Passwort vergessen / Einladung ----------
    public function forgotForm(): void
    {
        view('auth/forgot', ['title' => 'Passwort vergessen', 'bare' => true]);
    }

    public function forgot(): void
    {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!Auth::tooMany('reset', $email)) {
            Auth::fail('reset', $email);
            $user = Auth::findByEmail($email);
            if ($user && $user['is_active']) {
                Auth::sendResetMail($user);
            }
        }
        flash('ok', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde ein Link zum Zurücksetzen gesendet.');
        redirect('login');
    }

    public function resetForm(string $token): void
    {
        $t = Auth::findToken($token);
        if (!$t || !$t['is_active']) {
            flash('error', 'Der Link ist ungültig oder abgelaufen.');
            redirect('login');
        }
        view('auth/reset', ['title' => $t['type'] === 'invite' ? 'Passwort festlegen' : 'Neues Passwort', 'bare' => true, 'invite' => $t['type'] === 'invite', 'token' => $token]);
    }

    public function reset(string $token): void
    {
        $t = Auth::findToken($token);
        if (!$t || !$t['is_active']) {
            flash('error', 'Der Link ist ungültig oder abgelaufen.');
            redirect('login');
        }
        $pw = (string) ($_POST['password'] ?? '');
        $err = $pw !== (string) ($_POST['password2'] ?? '') ? 'Die Passwörter stimmen nicht überein.' : Auth::passwordError($pw);
        if ($err) {
            flash('error', $err);
            redirect(($t['type'] === 'invite' ? 'invite/' : 'reset/') . $token);
        }
        Db::tx(function () use ($t, $pw) {
            Db::exec('UPDATE users SET password_hash = ?, force_pw_change = 0 WHERE id = ?', [Auth::hash($pw), $t['user_id']]);
            Db::exec('UPDATE tokens SET used_at = NOW() WHERE id = ?', [$t['id']]);
            Db::exec('DELETE FROM tokens WHERE user_id = ?', [$t['user_id']]);
        });
        Auth::clearFails('login', (string) $t['email']);
        flash('ok', 'Passwort gespeichert. Du kannst dich jetzt anmelden.');
        redirect('login');
    }
}
