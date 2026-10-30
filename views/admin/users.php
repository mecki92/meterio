<div class="pagehead"><h2>Benutzerverwaltung</h2><a role="button" href="<?= e(url('admin/users/new')) ?>">+ Benutzer anlegen</a></div>
<div class="tablewrap">
<table class="striped">
    <thead><tr><th>Name / E-Mail</th><th>Rolle</th><th>Status</th><th>2FA</th><th>Zähler</th><th>Letzter Login</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($users as $x): $self = (int) $x['id'] === (int) Auth::user()['id']; ?>
        <tr>
            <td><b><?= e($x['name']) ?></b><br><small class="muted"><?= e($x['email']) ?></small></td>
            <td><?= $x['role'] === 'admin' ? '<span class="badge">Admin</span>' : 'User' ?></td>
            <td><?= $x['is_active'] ? 'aktiv' : '<span class="badge warn">deaktiviert</span>' ?></td>
            <td><?= !$x['twofa_ready'] ? '<small class="muted">nicht eingerichtet</small>' : ($x['twofa_method'] === 'totp' ? 'App' : 'E-Mail') ?></td>
            <td><?= (int) $x['meter_count'] ?></td>
            <td><small><?= e(fmt_date($x['last_login_at'], 'd.m.Y H:i')) ?></small></td>
            <td class="rowactions">
                <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/reset-password')) ?>" class="inline" data-confirm="Passwort-Link an <?= e($x['email']) ?> senden?"><?= csrf_field() ?><button class="linklike" type="submit">Passwort-Link</button></form>
                <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/reset-2fa')) ?>" class="inline" data-confirm="2FA von <?= e($x['email']) ?> zurücksetzen?"><?= csrf_field() ?><button class="linklike" type="submit">2FA zurücksetzen</button></form>
                <?php if (!$self): ?>
                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/toggle')) ?>" class="inline"><?= csrf_field() ?><button class="linklike" type="submit"><?= $x['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?></button></form>
                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e($x['email']) ?> und ALLE Daten endgültig löschen?"><?= csrf_field() ?><button class="linklike danger-text" type="submit">Löschen</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
