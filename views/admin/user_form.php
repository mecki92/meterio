<?php
/** @var array $old name, email, role, mode */
$roles = [
    'user'  => ['bi-person', 'Standard-Benutzer', 'Nutzt Zähler, Ablesungen und Verträge'],
    'admin' => ['bi-shield-check', 'Administrator', 'Verwaltet nur Benutzer – keine Zähler/Verträge'],
];
$modes = [
    'invite' => ['bi-envelope-paper', 'Einladungs-Link per E-Mail', 'Benutzer legt sein Passwort selbst fest (7 Tage gültig)'],
    'temp'   => ['bi-key', 'Temporäres Passwort', 'Wird einmalig angezeigt, Änderung beim ersten Login'],
];
?>
<div class="page-head">
    <div><h1>Benutzer anlegen</h1><p class="sub">Neues Konto für meterio</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/users')) ?>"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<form method="post" action="<?= e(url('admin/users')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-start">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-vcard"></i> Benutzerdaten</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="u-name">Name</label><input id="u-name" name="name" class="form-control" value="<?= e($old['name']) ?>" required maxlength="120" autofocus></div>
                        <div class="col-md-6"><label class="form-label" for="u-mail">E-Mail<?= info('Dient als Login und für Einladung, Passwort-Link und Zwei-Faktor-Codes.') ?></label><input id="u-mail" type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required></div>
                    </div>
                    <div class="form-label mt-3">Rolle<?= info('Standard-Benutzer verwalten Zähler, Ablesungen und Verträge. Administratoren verwalten ausschließlich Benutzer und haben keinen Zugriff auf Zähler und Verträge.') ?></div>
                    <div class="row g-3">
                        <?php foreach ($roles as $k => [$ic, $t, $sub]): ?>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="role" id="role-<?= e($k) ?>" value="<?= e($k) ?>" <?= $old['role'] === $k ? 'checked' : '' ?>>
                                <label class="choice" for="role-<?= e($k) ?>"><span class="icon-tile"><i class="bi <?= e($ic) ?>"></i></span><span><b><?= e($t) ?></b><small><?= e($sub) ?></small></span></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-door-open"></i> Zugang<?= info('Bei der ersten Anmeldung richtet der Benutzer verpflichtend die Zwei-Faktor-Authentifizierung ein.') ?></div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($modes as $k => [$ic, $t, $sub]): ?>
                            <div class="col-12">
                                <input type="radio" class="btn-check" name="mode" id="mode-<?= e($k) ?>" value="<?= e($k) ?>" <?= $old['mode'] === $k ? 'checked' : '' ?>>
                                <label class="choice" for="mode-<?= e($k) ?>"><span class="icon-tile"><i class="bi <?= e($ic) ?>"></i></span><span><b><?= e($t) ?></b><small><?= e($sub) ?></small></span></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> Benutzer anlegen</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/users')) ?>">Abbrechen</a>
            </div>
        </div>
    </div>
</form>
