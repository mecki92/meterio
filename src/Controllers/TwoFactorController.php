<?php
declare(strict_types=1);

final class TwoFactorController
{
    private function user(): array
    {
        $u = Auth::setupUser();
        if (!$u) {
            redirect('login');
        }
        // Im Zwischenzustand nur erlaubt, solange 2FA noch nicht eingerichtet ist
        if (!Auth::user() && ($u['twofa_ready'] || $u['force_pw_change'])) {
            redirect('login');
        }
        return $u;
    }

    public function setup(): void
    {
        $u = $this->user();
        if (empty($_SESSION['totp_pending'])) {
            $_SESSION['totp_pending'] = Totp::generateSecret();
        }
        $secret = $_SESSION['totp_pending'];
        view('twofa/setup', [
            'title' => 'Zwei-Faktor-Authentifizierung', 'bare' => !Auth::user(), 'active' => 'profile', 'u' => $u,
            'secret' => $secret, 'uri' => Totp::uri($secret, $u['email']),
            'emailSent' => !empty($_SESSION['email_setup_sent']),
            'changing' => (bool) Auth::user(),
        ]);
    }

    public function emailSend(): void
    {
        $u = $this->user();
        if (time() - (int) ($_SESSION['otp_sent'] ?? 0) < 30) {
            flash('warn', 'Bitte einen Moment warten, bevor ein neuer Code angefordert wird.');
            redirect('2fa/setup');
        }
        $_SESSION['otp_sent'] = time();
        if (!Auth::sendEmailOtp($u)) {
            flash('error', 'Die E-Mail konnte nicht gesendet werden. Bitte SMTP-Konfiguration prüfen.');
            redirect('2fa/setup');
        }
        $_SESSION['email_setup_sent'] = true;
        flash('ok', 'Code gesendet an ' . $u['email'] . '.');
        redirect('2fa/setup');
    }

    public function emailConfirm(): void
    {
        $u = $this->user();
        if (Auth::tooMany('2fa', $u['email'])) {
            flash('error', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
            redirect('2fa/setup');
        }
        if (!Auth::verifyEmailOtp((int) $u['id'], (string) ($_POST['code'] ?? ''))) {
            Auth::fail('2fa', $u['email']);
            flash('error', 'Der Code ist ungültig oder abgelaufen.');
            redirect('2fa/setup');
        }
        Db::exec("UPDATE users SET twofa_method='email', twofa_ready=1, totp_secret_enc=NULL, totp_last_step=0 WHERE id=?", [$u['id']]);
        unset($_SESSION['email_setup_sent'], $_SESSION['totp_pending']);
        $this->finish($u);
    }

    public function totpConfirm(): void
    {
        $u = $this->user();
        $secret = (string) ($_SESSION['totp_pending'] ?? '');
        if (Auth::tooMany('2fa', $u['email'])) {
            flash('error', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
            redirect('2fa/setup');
        }
        $step = $secret !== '' ? Totp::verify($secret, (string) ($_POST['code'] ?? '')) : null;
        if ($step === null) {
            Auth::fail('2fa', $u['email']);
            flash('error', 'Der Code stimmt nicht. Prüfe die Uhrzeit deines Geräts und versuche es erneut.');
            redirect('2fa/setup');
        }
        Db::exec("UPDATE users SET twofa_method='totp', twofa_ready=1, totp_secret_enc=?, totp_last_step=? WHERE id=?", [Crypto::enc($secret), $step, $u['id']]);
        unset($_SESSION['totp_pending'], $_SESSION['email_setup_sent']);
        $this->finish($u);
    }

    public function totpReset(): void
    {
        $this->user();
        $_SESSION['totp_pending'] = Totp::generateSecret();
        redirect('2fa/setup');
    }

    private function finish(array $u): never
    {
        $wasPre = !Auth::user();
        if ($wasPre) {
            Auth::complete(Auth::findById((int) $u['id']));
        }
        // Erste Einrichtung (oder nach Admin-Reset): neue Backup-Codes
        if (Auth::unusedBackupCodes((int) $u['id']) === 0) {
            $_SESSION['new_backup_codes'] = Auth::generateBackupCodes((int) $u['id']);
            flash('ok', 'Zwei-Faktor-Authentifizierung aktiviert. Bitte Backup-Codes jetzt sichern!');
            redirect('2fa/backup-codes');
        }
        flash('ok', 'Zwei-Faktor-Methode geändert.');
        redirect('profile');
    }

    public function backupCodes(): void
    {
        $u = Auth::require();
        $codes = $_SESSION['new_backup_codes'] ?? null;
        unset($_SESSION['new_backup_codes']); // nur einmal anzeigen
        if (!$codes) {
            flash('warn', 'Backup-Codes werden nur direkt nach dem Erzeugen angezeigt.');
            redirect('profile');
        }
        view('twofa/backup_codes', ['title' => 'Backup-Codes', 'active' => 'profile', 'codes' => $codes, 'u' => $u]);
    }

    public function backupRegenerate(): void
    {
        $u = Auth::require();
        if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
            flash('error', 'Passwort falsch.');
            redirect('profile');
        }
        $_SESSION['new_backup_codes'] = Auth::generateBackupCodes((int) $u['id']);
        redirect('2fa/backup-codes');
    }
}
