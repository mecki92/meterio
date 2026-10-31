<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h3 m-0">Benutzerverwaltung</h2>
    <a class="btn btn-primary" href="<?= e(url('admin/users/new')) ?>"><i class="bi bi-plus-lg"></i> Benutzer anlegen</a>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name / E-Mail</th><th>Rolle</th><th>Status</th><th>2FA</th><th>Zähler</th><th>Letzter Login</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($users as $x): $self = (int) $x['id'] === (int) Auth::user()['id']; ?>
        <tr>
            <td><b><?= e($x['name']) ?></b><br><small class="text-body-secondary"><?= e($x['email']) ?></small></td>
            <td><?= $x['role'] === 'admin' ? '<span class="badge text-bg-primary">Admin</span>' : 'User' ?></td>
            <td><?= $x['is_active'] ? '<span class="badge text-bg-success">aktiv</span>' : '<span class="badge text-bg-warning">deaktiviert</span>' ?></td>
            <td><?= !$x['twofa_ready'] ? '<small class="text-body-secondary">nicht eingerichtet</small>' : ($x['twofa_method'] === 'totp' ? 'App' : 'E-Mail') ?></td>
            <td><?= (int) $x['meter_count'] ?></td>
            <td><small><?= e(fmt_date($x['last_login_at'], 'd.m.Y H:i')) ?></small></td>
            <td>
                <div class="d-flex flex-wrap gap-1">
                <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/reset-password')) ?>" class="m-0" data-confirm="Passwort-Link an <?= e($x['email']) ?> senden?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Passwort-Link</button></form>
                <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/reset-2fa')) ?>" class="m-0" data-confirm="2FA von <?= e($x['email']) ?> zurücksetzen?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">2FA zurücksetzen</button></form>
                <?php if (!$self): ?>
                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/toggle')) ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-outline-warning" type="submit"><?= $x['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?></button></form>
                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/delete')) ?>" class="m-0" data-confirm="<?= e($x['email']) ?> und ALLE Daten endgültig löschen?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button></form>
                <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
