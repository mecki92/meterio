<h2>Profil</h2>
<div class="grid">
    <article>
        <header>Persönliche Daten &amp; Erinnerungen</header>
        <form method="post" action="<?= e(url('profile')) ?>">
            <?= csrf_field() ?>
            <label>Name <input name="name" value="<?= e($u['name']) ?>" required maxlength="120"></label>
            <label>E-Mail (Login) <input value="<?= e($u['email']) ?>" disabled></label>
            <label>Ablese-Erinnerung per E-Mail
                <select name="reminder_mode" id="rmode">
                    <option value="monthly" <?= $u['reminder_mode'] === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                    <option value="contract_year" <?= $u['reminder_mode'] === 'contract_year' ? 'selected' : '' ?>>Zum Ende des Vertragsjahres (14 Tage vorher)</option>
                    <option value="off" <?= $u['reminder_mode'] === 'off' ? 'selected' : '' ?>>Keine Erinnerung</option>
                </select>
            </label>
            <label>Tag im Monat (nur monatlich)
                <input type="number" name="reminder_day" min="1" max="28" value="<?= e($u['reminder_day']) ?>">
            </label>
            <button type="submit">Speichern</button>
        </form>
    </article>

    <article>
        <header>Passwort ändern</header>
        <form method="post" action="<?= e(url('profile/password')) ?>">
            <?= csrf_field() ?>
            <label>Aktuelles Passwort <input type="password" name="current" autocomplete="current-password" required></label>
            <label>Neues Passwort <input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
            <label>Wiederholen <input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
            <button type="submit">Passwort ändern</button>
        </form>
    </article>
</div>

<article>
    <header>Zwei-Faktor-Authentifizierung</header>
    <p>Methode: <b><?= $u['twofa_method'] === 'totp' ? 'Authenticator-App (TOTP)' : 'Code per E-Mail' ?></b> · Unbenutzte Backup-Codes: <b><?= (int) $backupLeft ?></b></p>
    <p><a role="button" class="secondary" href="<?= e(url('2fa/setup')) ?>">Methode wechseln / neu einrichten</a></p>
    <details>
        <summary>Neue Backup-Codes erzeugen</summary>
        <form method="post" action="<?= e(url('2fa/backup-codes/regenerate')) ?>" class="inlineform">
            <?= csrf_field() ?>
            <label>Passwort zur Bestätigung <input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit" class="secondary">Neue Codes erzeugen (alte werden ungültig)</button>
        </form>
    </details>
</article>
