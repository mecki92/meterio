<?php
/** @var array $meter @var array $readings (absteigend) @var array $stats */
$reasons = reading_reasons();
$kw = static fn(float $v) => number_format($v, 0, ',', '.');
$isOwner = ($meter['access'] ?? 'owner') === 'owner';
$canWrite = $isOwner || ($meter['access'] ?? '') === 'write';
?>
<div class="page-head">
    <h2 class="h3 m-0"><?= e($meter['name']) ?><?php if ($meter['archived']): ?> <span class="chip chip-muted align-middle ms-2">archiviert</span><?php endif; ?></h2>
    <div class="actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'] . '/stats')) ?>"><i class="bi bi-bar-chart-line"></i> Statistik</a>
        <?php if ($isOwner): ?><a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'] . '/edit')) ?>">Bearbeiten</a><?php endif; ?>
    </div>
</div>
<?php if (!$isOwner): ?>
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <span><i class="bi bi-people"></i> Freigegeben von <b><?= e($meter['owner_name']) ?></b> · <?= $canWrite ? 'du kannst Zählerstände erfassen' : 'nur Ansicht' ?>. Verträge und Kosten sind nicht freigegeben.</span>
    <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/leave')) ?>" class="m-0" data-confirm="Freigabe verlassen? Du siehst den Zähler danach nicht mehr."><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Freigabe verlassen</button></form>
</div>
<?php endif; ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-4 small">
    <span class="chip"><i class="bi <?= $meter['kind'] === 'analog' ? 'bi-speedometer' : 'bi-cpu' ?>"></i> <?= $meter['kind'] === 'analog' ? 'Analog' : 'Digital (OBIS)' ?></span>
    <?php if ($meter['location'] !== ''): ?><span class="chip"><i class="bi bi-geo-alt"></i> <?= e($meter['location']) ?></span><?php endif; ?>
    <?php foreach ($meter['registers'] as $r): ?><span class="chip chip-<?= $r['tariff'] === 'EXPORT' ? 'success' : 'primary' ?>" title="<?= e(tariff_labels()[$r['tariff']] ?? $r['tariff']) ?>"><?= e($r['label']) ?><?= $r['obis'] !== '' ? ' · ' . e($r['obis']) : '' ?> <b><?= e($r['tariff']) ?></b></span><?php endforeach; ?>
    <?php if ($meter['note'] !== ''): ?><span class="text-body-secondary"><i class="bi bi-sticky"></i> <?= e($meter['note']) ?></span><?php endif; ?>
</div>

<?php require __DIR__ . '/_summary.php'; ?>

<div class="card my-3">
    <div class="card-header split"><span><i class="bi bi-journal-text"></i> Ablesungen <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($readings) ?></span></span>
        <?php if ($canWrite): ?><a class="btn btn-sm btn-primary" href="<?= e(url('meters/' . $meter['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Neue Ablesung</a><?php endif; ?></div>
    <?php if (!$readings): ?>
        <div class="card-body text-body-secondary">Noch keine Ablesung erfasst.</div>
    <?php else: ?>
    <div class="card-body">
    <?php
    // Nach Kalenderjahr bündeln (Liste ist absteigend); Verbrauch je Jahr = Summe der Zuwächse gegenüber der Vorablesung
    $groups = [];
    foreach ($readings as $i => $rd) {
        $y = (int) date('Y', $rd['ts']);
        $groups[$y] ??= ['rows' => [], 'kwh' => 0.0];
        $prev = $readings[$i + 1] ?? null;
        foreach ($meter['registers'] as $r) {
            $v = $rd['values'][$r['id']] ?? null;
            $pv = $prev['values'][$r['id']] ?? null;
            if ($r['tariff'] !== 'EXPORT' && $v !== null && $pv !== null && $v > $pv) {
                $groups[$y]['kwh'] += $v - $pv;
            }
        }
        $groups[$y]['rows'][] = [$rd, $prev];
    }
    $first = true;
    foreach ($groups as $y => $g): $open = $first; $first = false; ?>
    <div class="year-group <?= $open ? 'open' : '' ?>">
        <button type="button" class="year-head <?= $open ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#yr-<?= $y ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="yr-<?= $y ?>">
            <span class="yr-title"><i class="bi bi-chevron-down yr-caret"></i> <?= $y ?></span>
            <span class="yr-meta"><span class="chip"><?= count($g['rows']) ?> Ablesung<?= count($g['rows']) === 1 ? '' : 'en' ?></span><?php if ($g['kwh'] > 0): ?><span class="chip"><i class="bi bi-lightning-charge"></i> <b><?= e($kw($g['kwh'])) ?> kWh</b></span><?php endif; ?></span>
        </button>
        <div class="collapse <?= $open ? 'show' : '' ?>" id="yr-<?= $y ?>">
    <div class="table-responsive pay-table">
    <table class="table table-hover align-middle mb-0 reading-table">
        <thead><tr><th class="c-date">Datum</th><?php foreach ($meter['registers'] as $r): ?><th class="num"><?= e($r['label']) ?><?= $r['obis'] !== '' ? '<br><small class="fw-normal text-body-secondary text-lowercase">' . e($r['obis']) . '</small>' : '' ?></th><?php endforeach; ?><th class="d-none d-md-table-cell c-reason">Anlass</th><th class="c-photos">Fotos</th><th class="c-edit"></th></tr></thead>
        <tbody>
        <?php foreach ($g['rows'] as [$rd, $prev]):
            [$rl, $rc] = $reasons[$rd['reason']] ?? ['Sonstiges', 'secondary']; ?>
            <tr>
                <td class="text-nowrap"><?= e(date('d.m.Y', $rd['ts'])) ?>
                    <?php if ($rd['reason'] !== 'regular'): ?><span class="chip chip-<?= e($rc) ?> d-md-none ms-1"><?= e($reasons[$rd['reason']][2] ?? $rl) ?></span><?php endif; ?>
                    <?= $rd['note'] !== '' ? '<br><small class="text-body-secondary">' . e($rd['note']) . '</small>' : '' ?></td>
                <?php foreach ($meter['registers'] as $r):
                    $v = $rd['values'][$r['id']] ?? null; $pv = $prev['values'][$r['id']] ?? null; ?>
                    <td class="num"><?php if ($v !== null): ?><span class="fw-semibold"><?= e($kw($v)) ?></span><?php if ($pv !== null && $v >= $pv): ?><br><small class="text-body-secondary">+<?= e($kw($v - $pv)) ?></small><?php endif; ?><?php else: ?>–<?php endif; ?></td>
                <?php endforeach; ?>
                <td class="d-none d-md-table-cell"><span class="chip chip-<?= e($rc) ?>"><?= e($rl) ?></span></td>
                <td class="text-nowrap"><?php foreach ($rd['photos'] as $pi => $p): ?><a class="btn btn-sm btn-outline-secondary px-2 py-0" href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener" title="Foto <?= $pi + 1 ?>"><i class="bi bi-camera"></i></a> <?php endforeach; ?></td>
                <td class="text-end"><?php if ($isOwner || ($canWrite && (int) ($rd['created_by'] ?? 0) === $uid)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('readings/' . $rd['id'] . '/edit')) ?>" title="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($isOwner): ?>
<div class="card my-3" id="freigabe">
    <div class="card-header"><i class="bi bi-people"></i> Zähler teilen<?= info('Mit einem Freigabe-Code können andere Benutzer diesen Zähler sehen und – je nach Berechtigung – Zählerstände erfassen. Deine Verträge, Kosten und Zahlungen werden niemals geteilt. Der Code ist 7 Tage gültig und nur einmal verwendbar. Der Zugriff gilt höchstens 365 Tage und lässt sich verlängern oder jederzeit löschen.') ?></div>
    <div class="card-body">
        <?php if (!empty($newShare)): ?>
            <div class="alert alert-success">
                <div class="small mb-1">Neuer Freigabe-Code (<?= $newShare['perm'] === 'write' ? 'Ablesen &amp; erfassen' : 'nur Ansicht' ?>, <?= (int) $newShare['days'] ?> Tage zum Einlösen, danach <?= (int) $newShare['access'] ?> Tage Zugriff) – wird nur jetzt angezeigt:</div>
                <div class="d-flex flex-wrap align-items-center gap-2"><code class="fs-4 user-select-all" id="share-code"><?= e($newShare['code']) ?></code>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="#share-code"><i class="bi bi-clipboard"></i> Kopieren</button></div>
                <div class="small mt-2">Gib den Code der Person weiter. Sie löst ihn unter <b>Zähler → Freigabecode einlösen</b> ein.</div>
            </div>
        <?php endif; ?>
        <?php if ($shares): ?>
            <ul class="list-group mb-3">
            <?php foreach ($shares as $sh): ?>
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi <?= $sh['redeemed'] ? 'bi-person-check' : 'bi-hourglass-split' ?>"></i>
                        <b><?= e($sh['redeemed'] ? $sh['user_name'] : ($sh['label'] !== '' ? $sh['label'] : 'Offene Einladung')) ?></b>
                        <?php if ($sh['redeemed'] && $sh['label'] !== ''): ?><span class="text-body-secondary small">(<?= e($sh['label']) ?>)</span><?php endif; ?>
                        <span class="chip ms-1"><?= $sh['permission'] === 'write' ? 'Ablesen &amp; erfassen' : 'Nur ansehen' ?></span>
                        <?php if (!$sh['redeemed']): ?><span class="chip chip-<?= $sh['expired'] ? 'muted' : 'info' ?> ms-1"><?= $sh['expired'] ? 'abgelaufen' : 'Code offen bis ' . e(fmt_date($sh['expires_at'])) ?></span>
                        <?php elseif ($sh['access_until']): ?><span class="chip chip-<?= $sh['access_expired'] ? 'muted' : ($sh['days_left'] < 30 ? 'warning' : 'info') ?> ms-1"><?= $sh['access_expired'] ? 'Zugriff abgelaufen am ' : 'Zugriff bis ' ?><?= e(fmt_date($sh['access_until'])) ?></span><?php endif; ?></span>
                    <span class="d-flex flex-wrap gap-2">
                    <?php if ($sh['redeemed']): ?>
                        <form method="post" action="<?= e(url('shares/' . $sh['id'] . '/extend')) ?>" class="m-0 d-flex gap-1"><?= csrf_field() ?>
                            <select name="duration" class="form-select form-select-sm w-auto" aria-label="Verlängern um"><?php foreach ([30 => '30 Tage', 90 => '90 Tage', 180 => '180 Tage', 365 => '365 Tage'] as $d => $l): ?><option value="<?= $d ?>" <?= $d === 365 ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Verlängern</button></form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('shares/' . $sh['id'] . '/revoke')) ?>" class="m-0" data-confirm="<?= $sh['redeemed'] ? 'Zugriff entziehen und Freigabe löschen?' : 'Code löschen?' ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> <?= $sh['redeemed'] ? 'Zugriff entziehen' : 'Code löschen' ?></button></form>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/shares')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-sm-3"><label class="form-label small mb-1" for="sh-label">Bezeichnung (optional)</label><input id="sh-label" name="label" class="form-control" maxlength="60" placeholder="z. B. Sohn"></div>
            <div class="col-sm-3"><label class="form-label small mb-1" for="sh-dur">Zugriff gültig für</label>
                <select id="sh-dur" name="duration" class="form-select"><option value="30">30 Tage</option><option value="90">90 Tage</option><option value="180">180 Tage</option><option value="365" selected>365 Tage (Maximum)</option></select></div>
            <div class="col-sm-3"><label class="form-label small mb-1" for="sh-perm">Berechtigung</label>
                <select id="sh-perm" name="permission" class="form-select"><option value="write">Ablesen &amp; Zählerstände erfassen</option><option value="read">Nur ansehen</option></select></div>
            <div class="col-sm-3"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-key"></i> Code erzeugen</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner): ?>
<details class="mt-4">
    <summary class="text-danger">Zähler löschen</summary>
    <p class="mt-2">Löscht den Zähler mit allen Registern, Ablesungen, Fotos und zugehörigen Verträgen/Zahlungen unwiderruflich.</p>
    <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/delete')) ?>" data-confirm="Zähler „<?= e($meter['name']) ?>“ wirklich endgültig löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Endgültig löschen</button>
    </form>
</details>
<?php endif; ?>
