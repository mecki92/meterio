<?php
/** @var array $u @var int $backupLeft */
$mode = $u['reminder_mode'];
$modes = [
    'month_end'     => ['bi-calendar-check', 'Zum Monatsende', 'Am letzten Tag jedes Monats'],
    'monthly'       => ['bi-calendar-event', 'Fester Tag im Monat', 'Am gewählten Tag jedes Monats'],
    'contract_year' => ['bi-arrow-repeat', 'Vor Ende des Vertragsjahres', '14 Tage vorher, für die Jahresabrechnung'],
    'off'           => ['bi-bell-slash', 'Keine Erinnerung', 'Keine automatische Mail'],
];
?>
<div class="page-head">
    <div>
        <h1>Profil</h1>
        <p class="sub"><?= e($u['name']) ?> · <?= e($u['email']) ?></p>
    </div>
</div>
<div class="row g-3 align-items-start">
<div class="col-lg-8">
    <form method="post" action="<?= e(url('profile')) ?>">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person"></i> Persönliche Daten</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="p-name">Name</label><input id="p-name" name="name" class="form-control" value="<?= e($u['name']) ?>" required maxlength="120"></div>
                    <div class="col-md-6"><label class="form-label" for="p-mail">E-Mail (Login)</label><input id="p-mail" class="form-control" value="<?= e($u['email']) ?>" disabled></div>
                </div>
            </div>
        </div>

        <?php if ($u['role'] !== 'admin'): /* Administratoren haben keine Zähler: keine Erinnerungen/Benachrichtigungen */ ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-bell"></i> E-Mail-Benachrichtigungen</div>
            <div class="card-body">
                <div class="form-label">Ablese-Erinnerung<?= info('Du bekommst eine E-Mail mit der Bitte, die Zähler abzulesen – inklusive Datum der letzten Ablesung je Zähler.') ?></div>
                <div class="row g-3 mb-3" id="remmodes">
                    <?php foreach ($modes as $k => [$ic, $t, $sub]): ?>
                        <div class="col-sm-6">
                            <input type="radio" class="btn-check" name="reminder_mode" id="rm-<?= e($k) ?>" value="<?= e($k) ?>" <?= $mode === $k ? 'checked' : '' ?>>
                            <label class="choice" for="rm-<?= e($k) ?>"><span class="icon-tile"><i class="bi <?= e($ic) ?>"></i></span><span><b><?= e($t) ?></b><small><?= e($sub) ?></small></span></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3" id="remday" <?= $mode === 'monthly' ? '' : 'hidden' ?>>
                    <label class="form-label" for="reminder_day">Tag im Monat<?= info('Von 1 bis 28, damit die Erinnerung in jedem Monat vorkommt.') ?></label>
                    <div class="input-group" style="max-width:200px"><input type="number" id="reminder_day" name="reminder_day" class="form-control" min="1" max="28" value="<?= e($u['reminder_day']) ?>"><span class="input-group-text">. des Monats</span></div>
                </div>
                <?php if ($u['last_reminder_at']): ?><p class="small text-body-secondary mb-3"><i class="bi bi-clock-history"></i> Letzte Erinnerung: <?= e(fmt_date($u['last_reminder_at'])) ?></p><?php endif; ?>
                <hr>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="notify_reading" id="notify_reading" value="1" <?= (int) ($u['notify_reading'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_reading">Neue Zählerstände per E-Mail zusenden<?= info('Beim Erfassen einer Ablesung ist „Per E-Mail zusenden“ vorausgewählt. Die Mail enthält den Stand und die Fotos als Erinnerung, ihn beim Netzbetreiber einzutragen. Im Formular lässt es sich jederzeit abwählen.') ?></label>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
    </form>
</div>
<div class="col-lg-4"><div class="side-sticky">
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-key"></i> Passwort ändern</div>
        <div class="card-body">
            <form method="post" action="<?= e(url('profile/password')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="pw-cur">Aktuelles Passwort</label><input id="pw-cur" type="password" name="current" class="form-control" autocomplete="current-password" required></div>
                <div class="mb-3"><label class="form-label" for="pw-new">Neues Passwort<?= info('Mindestens 10 Zeichen, mit Buchstaben und Ziffern.') ?></label><input id="pw-new" type="password" name="password" class="form-control" autocomplete="new-password" minlength="10" required></div>
                <div class="mb-3"><label class="form-label" for="pw-new2">Wiederholen</label><input id="pw-new2" type="password" name="password2" class="form-control" autocomplete="new-password" minlength="10" required></div>
                <button type="submit" class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><i class="bi bi-shield-lock"></i> Zwei-Faktor-Authentifizierung</div>
        <div class="card-body">
            <p class="mb-2">Methode: <b><?= $u['twofa_method'] === 'totp' ? 'Authenticator-App (TOTP)' : 'Code per E-Mail' ?></b></p>
            <p class="small text-body-secondary">Unbenutzte Backup-Codes: <b><?= (int) $backupLeft ?></b></p>
            <a class="btn btn-outline-secondary btn-sm mb-3" href="<?= e(url('2fa/setup')) ?>">Methode wechseln / neu einrichten</a>
            <details>
                <summary>Neue Backup-Codes erzeugen</summary>
                <form method="post" action="<?= e(url('2fa/backup-codes/regenerate')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label" for="bc-pw">Passwort zur Bestätigung</label><input id="bc-pw" type="password" name="password" class="form-control" autocomplete="current-password" required></div>
                    <button type="submit" class="btn btn-secondary btn-sm">Neue Codes erzeugen (alte werden ungültig)</button>
                </form>
            </details>
        </div>
    </div>
</div></div>
</div>
