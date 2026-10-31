<?php
/** @var array $meter @var array $readings (absteigend) @var array $stats */
$reasons = reading_reasons();
$kw = static fn(float $v) => number_format($v, 0, ',', '.');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h2 class="h3 m-0"><?= e($meter['name']) ?><?php if ($meter['archived']): ?> <span class="chip chip-muted align-middle ms-2">archiviert</span><?php endif; ?></h2>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('meters/' . $meter['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesung</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'] . '/stats')) ?>"><i class="bi bi-bar-chart-line"></i> Statistik</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'] . '/edit')) ?>">Bearbeiten</a>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3 small">
    <span class="chip"><i class="bi <?= $meter['kind'] === 'analog' ? 'bi-speedometer' : 'bi-cpu' ?>"></i> <?= $meter['kind'] === 'analog' ? 'Analog' : 'Digital (OBIS)' ?></span>
    <?php if ($meter['location'] !== ''): ?><span class="chip"><i class="bi bi-geo-alt"></i> <?= e($meter['location']) ?></span><?php endif; ?>
    <?php foreach ($meter['registers'] as $r): ?><span class="chip chip-<?= $r['tariff'] === 'EXPORT' ? 'success' : 'primary' ?>" title="<?= e(tariff_labels()[$r['tariff']] ?? $r['tariff']) ?>"><?= e($r['label']) ?><?= $r['obis'] !== '' ? ' · ' . e($r['obis']) : '' ?> <b><?= e($r['tariff']) ?></b></span><?php endforeach; ?>
    <?php if ($meter['note'] !== ''): ?><span class="text-body-secondary"><i class="bi bi-sticky"></i> <?= e($meter['note']) ?></span><?php endif; ?>
</div>

<?php require __DIR__ . '/_summary.php'; ?>

<div class="card my-3">
    <div class="card-header split"><span><i class="bi bi-journal-text"></i> Ablesungen <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($readings) ?></span></span>
        <a class="btn btn-sm btn-primary" href="<?= e(url('meters/' . $meter['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Neue Ablesung</a></div>
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
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Datum</th><?php foreach ($meter['registers'] as $r): ?><th class="num"><?= e($r['label']) ?><?= $r['obis'] !== '' ? '<br><small class="fw-normal text-body-secondary text-lowercase">' . e($r['obis']) . '</small>' : '' ?></th><?php endforeach; ?><th class="d-none d-md-table-cell">Anlass</th><th>Fotos</th><th></th></tr></thead>
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
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('readings/' . $rd['id'] . '/edit')) ?>" title="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></a></td>
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

<details class="mt-4">
    <summary class="text-danger">Zähler löschen</summary>
    <p class="mt-2">Löscht den Zähler mit allen Registern, Ablesungen, Fotos und zugehörigen Verträgen/Zahlungen unwiderruflich.</p>
    <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/delete')) ?>" data-confirm="Zähler „<?= e($meter['name']) ?>“ wirklich endgültig löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Endgültig löschen</button>
    </form>
</details>
