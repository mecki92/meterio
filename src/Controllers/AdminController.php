<?php
declare(strict_types=1);

final class AdminController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $users = Db::all('SELECT u.*, (SELECT COUNT(*) FROM meters m WHERE m.user_id=u.id) AS meter_count FROM users u ORDER BY u.id');
        view('admin/users', ['title' => 'Benutzerverwaltung', 'active' => 'admin', 'users' => $users]);
    }

    public function create(): void
    {
        Auth::requireAdmin();
        view('admin/user_form', ['title' => 'Benutzer anlegen', 'active' => 'admin', 'old' => ['name' => '', 'email' => '', 'role' => 'user', 'mode' => 'invite']]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        $name = mb_trim_len((string) ($_POST['name'] ?? ''), 120);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $mode = ($_POST['mode'] ?? '') === 'temp' ? 'temp' : 'invite';
        $old = compact('name', 'email', 'role', 'mode');

        $err = null;
        if ($name === '') {
            $err = 'Bitte einen Namen angeben.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } elseif (Auth::findByEmail($email)) {
            $err = 'Diese E-Mail-Adresse ist bereits vergeben.';
        }
        if ($err) {
            flash('error', $err);
            view('admin/user_form', ['title' => 'Benutzer anlegen', 'active' => 'admin', 'old' => $old]);
            return;
        }

        if ($mode === 'temp') {
            $tmp = $this->tempPassword();
            $id = Auth::createUser($email, $name, $tmp, $role, true);
            Mailer::send($email, 'Dein Konto wurde angelegt', Mailer::wrap('Willkommen bei ' . APP_BRAND,
                '<p>Hallo ' . e($name) . ',</p><p>für dich wurde ein Konto angelegt. Dein Administrator übergibt dir das temporäre Passwort persönlich. Beim ersten Login legst du ein eigenes Passwort fest und richtest die Zwei-Faktor-Authentifizierung ein.</p>'
                . Mailer::button(abs_url('login'), 'Zur Anmeldung')));
            flash('ok', "Benutzer angelegt. Temporäres Passwort (wird nur jetzt angezeigt): $tmp");
        } else {
            $id = Auth::createUser($email, $name, bin2hex(random_bytes(16)) . 'aA1', $role, true);
            $sent = Auth::sendResetMail(Auth::findById($id), true);
            flash($sent ? 'ok' : 'warn', $sent ? 'Benutzer angelegt, Einladung per E-Mail gesendet (7 Tage gültig).' : 'Benutzer angelegt, aber die Einladung konnte nicht gesendet werden (SMTP prüfen). Über „Passwort-Link senden“ erneut versuchen.');
        }
        redirect('admin/users');
    }

    public function toggle(int $id): void
    {
        $admin = Auth::requireAdmin();
        $u = $this->target($id);
        if ($u['id'] === (int) $admin['id']) {
            flash('error', 'Du kannst dein eigenes Konto nicht deaktivieren.');
            redirect('admin/users');
        }
        Db::exec('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('ok', $u['is_active'] ? 'Konto deaktiviert.' : 'Konto aktiviert.');
        redirect('admin/users');
    }

    public function reset2fa(int $id): void
    {
        Auth::requireAdmin();
        $u = $this->target($id);
        Db::tx(function () use ($id) {
            Db::exec("UPDATE users SET twofa_ready=0, twofa_method='email', totp_secret_enc=NULL, totp_last_step=0 WHERE id=?", [$id]);
            Db::exec('DELETE FROM backup_codes WHERE user_id=?', [$id]);
            Db::exec('DELETE FROM otp_codes WHERE user_id=?', [$id]);
        });
        Mailer::send($u['email'], '2FA zurückgesetzt', Mailer::wrap('Zwei-Faktor-Authentifizierung zurückgesetzt',
            '<p>Hallo ' . e($u['name']) . ',</p><p>ein Administrator hat deine Zwei-Faktor-Authentifizierung zurückgesetzt. Du richtest sie bei der nächsten Anmeldung neu ein.</p>'));
        flash('ok', '2FA für ' . $u['email'] . ' zurückgesetzt. Beim nächsten Login wird sie neu eingerichtet.');
        redirect('admin/users');
    }

    public function resetPassword(int $id): void
    {
        Auth::requireAdmin();
        $u = $this->target($id);
        $sent = Auth::sendResetMail($u, false);
        flash($sent ? 'ok' : 'error', $sent ? 'Link zum Zurücksetzen gesendet (1 Stunde gültig).' : 'E-Mail konnte nicht gesendet werden (SMTP prüfen).');
        redirect('admin/users');
    }

    public function delete(int $id): void
    {
        $admin = Auth::requireAdmin();
        $u = $this->target($id);
        if ($u['id'] === (int) $admin['id']) {
            flash('error', 'Du kannst dein eigenes Konto nicht löschen.');
            redirect('admin/users');
        }
        ImageProcessor::deleteUserDir($id);
        Db::exec('DELETE FROM users WHERE id = ?', [$id]); // alle Daten per CASCADE
        flash('ok', 'Benutzer ' . $u['email'] . ' und alle zugehörigen Daten wurden gelöscht.');
        redirect('admin/users');
    }

    private function target(int $id): array
    {
        $u = Auth::findById($id) ?? abort(404, 'Benutzer nicht gefunden.');
        $u['id'] = (int) $u['id'];
        return $u;
    }

    private function tempPassword(): string
    {
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $p = '';
        for ($i = 0; $i < 14; $i++) {
            $p .= $a[random_int(0, strlen($a) - 1)];
        }
        return $p . random_int(10, 99);
    }
}
