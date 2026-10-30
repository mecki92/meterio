<?php
declare(strict_types=1);

final class ProfileController
{
    public function index(): void
    {
        $u = Auth::require();
        view('profile/index', [
            'title' => 'Profil', 'active' => 'profile', 'u' => $u,
            'backupLeft' => Auth::unusedBackupCodes((int) $u['id']),
        ]);
    }

    public function update(): void
    {
        $u = Auth::require();
        $name = mb_trim_len((string) ($_POST['name'] ?? ''), 120);
        $mode = in_array($_POST['reminder_mode'] ?? '', ['off', 'monthly', 'contract_year'], true) ? $_POST['reminder_mode'] : 'monthly';
        $day = max(1, min(28, (int) ($_POST['reminder_day'] ?? 1)));
        if ($name === '') {
            flash('error', 'Name darf nicht leer sein.');
            redirect('profile');
        }
        Db::exec('UPDATE users SET name=?, reminder_mode=?, reminder_day=? WHERE id=?', [$name, $mode, $day, $u['id']]);
        flash('ok', 'Profil gespeichert.');
        redirect('profile');
    }

    public function password(): void
    {
        $u = Auth::require();
        $new = (string) ($_POST['password'] ?? '');
        $err = null;
        if (!password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) {
            $err = 'Das aktuelle Passwort ist falsch.';
        } elseif ($new !== (string) ($_POST['password2'] ?? '')) {
            $err = 'Die neuen Passwörter stimmen nicht überein.';
        } else {
            $err = Auth::passwordError($new);
        }
        if ($err) {
            flash('error', $err);
            redirect('profile');
        }
        Db::exec('UPDATE users SET password_hash=? WHERE id=?', [Auth::hash($new), $u['id']]);
        flash('ok', 'Passwort geändert.');
        redirect('profile');
    }
}
