<h2 class="h3 mb-3">Profil</h2>
<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Persönliche Daten &amp; Erinnerungen</div>
        <div class="card-body">
            <form method="post" action="<?= e(url('profile')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($u['name']) ?>" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">E-Mail (Login)</label><input class="form-control" value="<?= e($u['email']) ?>" disabled></div>
                <div class="mb-3"><label class="form-label">Ablese-Erinnerung per E-Mail</label>
                    <select name="reminder_mode" class="form-select">
                        <option value="monthly" <?= $u['reminder_mode'] === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                        <option value="contract_year" <?= $u['reminder_mode'] === 'contract_year' ? 'selected' : '' ?>>Zum Ende des Vertragsjahres (14 Tage vorher)</option>
                        <option value="off" <?= $u['reminder_mode'] === 'off' ? 'selected' : '' ?>>Keine Erinnerung</option>
                    </select></div>
                <div class="mb-3"><label class="form-label">Tag im Monat (nur monatlich)</label>
                    <input type="number" name="reminder_day" class="form-control" min="1" max="28" value="<?= e($u['reminder_day']) ?>"></div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Passwort ändern</div>
        <div class="card-body">
            <form method="post" action="<?= e(url('profile/password')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Aktuelles Passwort</label><input type="password" name="current" class="form-control" autocomplete="current-password" required></div>
                <div class="mb-3"><label class="form-label">Neues Passwort</label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="10" required></div>
                <div class="mb-3"><label class="form-label">Wiederholen</label><input type="password" name="password2" class="form-control" autocomplete="new-password" minlength="10" required></div>
                <button type="submit" class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>
    </div></div>
</div>
<div class="card">
    <div class="card-header">Zwei-Faktor-Authentifizierung</div>
    <div class="card-body">
        <p>Methode: <b><?= $u['twofa_method'] === 'totp' ? 'Authenticator-App (TOTP)' : 'Code per E-Mail' ?></b> · Unbenutzte Backup-Codes: <b><?= (int) $backupLeft ?></b></p>
        <a class="btn btn-outline-secondary mb-3" href="<?= e(url('2fa/setup')) ?>">Methode wechseln / neu einrichten</a>
        <details>
            <summary>Neue Backup-Codes erzeugen</summary>
            <form method="post" action="<?= e(url('2fa/backup-codes/regenerate')) ?>" class="mt-2" style="max-width:420px">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Passwort zur Bestätigung</label><input type="password" name="password" class="form-control" autocomplete="current-password" required></div>
                <button type="submit" class="btn btn-secondary">Neue Codes erzeugen (alte werden ungültig)</button>
            </form>
        </details>
    </div>
</div>
