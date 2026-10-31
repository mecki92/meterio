<div class="page-head">
    <div>
        <h2 class="h3 m-0">Benutzerverwaltung</h2>
        <div class="text-body-secondary small"><?= count($users) ?> Konto<?= count($users) === 1 ? '' : 'en' ?></div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/users/new')) ?>"><i class="bi bi-plus-lg"></i> Benutzer anlegen</a>
</div>
<div class="row g-3">
<?php foreach ($users as $x):
    $self = (int) $x['id'] === (int) Auth::user()['id'];
    $isAdmin = $x['role'] === 'admin';
    $blocked = !$isAdmin && (int) $x['data_count'] > 0;
    $ini = mb_strtoupper(mb_substr(trim((string) $x['name']) !== '' ? trim((string) $x['name']) : (string) $x['email'], 0, 1));
    $act = static fn(string $p) => e(url('admin/users/' . $x['id'] . '/' . $p));
?>
    <div class="col-12 col-xl-6">
        <div class="card h-100 user-card<?= $x['is_active'] ? '' : ' opacity-75' ?>">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-start">
                    <div class="icon-tile fw-semibold"><?= e($ini) ?></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <b class="text-break"><?= e($x['name']) ?></b>
                            <?php if ($self): ?><span class="badge bg-body-secondary text-body border">Du</span><?php endif; ?>
                        </div>
                        <div class="small text-body-secondary text-break"><?= e($x['email']) ?></div>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?= $isAdmin ? '<span class="badge text-bg-primary"><i class="bi bi-shield-check"></i> Administrator</span>' : '<span class="badge text-bg-secondary-subtle border text-body"><i class="bi bi-person"></i> Benutzer</span>' ?>
                            <?= $x['is_active'] ? '<span class="badge text-bg-success-subtle text-success-emphasis border">aktiv</span>' : '<span class="badge text-bg-warning">deaktiviert</span>' ?>
                            <?php if (!$x['twofa_ready']): ?>
                                <span class="badge text-bg-warning-subtle text-warning-emphasis border"><i class="bi bi-shield-exclamation"></i> 2FA fehlt</span>
                            <?php else: ?>
                                <span class="badge bg-body-secondary text-body border"><i class="bi bi-shield-lock"></i> 2FA: <?= $x['twofa_method'] === 'totp' ? 'App' : 'E-Mail' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aktionen"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><form method="post" action="<?= $act('reset-password') ?>" class="m-0" data-confirm="Passwort-Link an <?= e($x['email']) ?> senden?"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-envelope me-2"></i>Passwort-Link senden</button></form></li>
                            <li><form method="post" action="<?= $act('reset-2fa') ?>" class="m-0" data-confirm="2FA von <?= e($x['email']) ?> zurücksetzen?"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-shield-x me-2"></i>2FA zurücksetzen</button></form></li>
                            <?php if (!$self): ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($blocked): ?>
                                    <li><span class="dropdown-item disabled" title="Nicht möglich: Es sind noch Zähler, Verträge oder geteilte Zähler hinterlegt."><i class="bi bi-shield-check me-2"></i>Zu Admin (gesperrt)</span></li>
                                <?php else: ?>
                                    <li><form method="post" action="<?= $act('role') ?>" class="m-0" data-confirm="<?= $isAdmin ? 'Zum Standard-Benutzer machen? Er kann dann Zähler und Verträge nutzen, aber keine Benutzer verwalten.' : 'Zum Administrator machen? Administratoren verwalten nur Benutzer und können keine Zähler oder Verträge nutzen.' ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-<?= $isAdmin ? 'person' : 'shield-check' ?> me-2"></i><?= $isAdmin ? 'Zu Standard-Benutzer' : 'Zu Administrator' ?></button></form></li>
                                <?php endif; ?>
                                <li><form method="post" action="<?= $act('toggle') ?>" class="m-0"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-<?= $x['is_active'] ? 'pause-circle' : 'play-circle' ?> me-2"></i><?= $x['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?></button></form></li>
                                <li><form method="post" action="<?= $act('delete') ?>" class="m-0" data-confirm="<?= e($x['email']) ?> und ALLE Daten endgültig löschen?"><?= csrf_field() ?><button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Löschen</button></form></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php if (!$isAdmin): ?>
                <div class="row text-center g-2 mt-3">
                    <div class="col-6"><div class="user-stat"><div class="fs-5 fw-semibold"><?= (int) $x['meter_count'] ?></div><div class="small text-body-secondary"><i class="bi bi-speedometer2"></i> Zähler</div></div></div>
                    <div class="col-6"><div class="user-stat"><div class="fs-5 fw-semibold"><?= (int) $x['contract_count'] ?></div><div class="small text-body-secondary"><i class="bi bi-file-earmark-text"></i> Verträge</div></div></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer small text-body-secondary bg-transparent"><i class="bi bi-clock-history"></i> Letzter Login: <?= $x['last_login_at'] ? e(fmt_date($x['last_login_at'], 'd.m.Y H:i')) : 'noch nie' ?></div>
        </div>
    </div>
<?php endforeach; ?>
</div>
