<?php
/** @var array $meter @var array $st  Ergebnis von Calc::meterStatistics() */
$kw = static fn(float $v) => number_format($v, 0, ',', '.');
$tl = tariff_labels();
$monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$years = $st['years'];
$hasExport = false; $tariffs = [];
foreach ($years as $y) { if ($y['export'] > 0) { $hasExport = true; } foreach ($y['by'] as $t => $v) { $tariffs[$t] = true; } }
$tariffs = array_keys($tariffs);
$perDay = $st['span_days'] >= 1 ? $st['total'] / $st['span_days'] : null;
// Höchster / niedrigster vollständiger Monat
$maxM = null; $minM = null;
foreach ($st['months'] as $yy => $ms) { foreach ($ms as $mo => $m) { if ($m['partial']) { continue; }
    if ($maxM === null || $m['kwh'] > $maxM[2]) { $maxM = [$yy, $mo, $m['kwh']]; }
    if ($minM === null || $m['kwh'] < $minM[2]) { $minM = [$yy, $mo, $m['kwh']]; } } }
$chartYears = []; foreach ($years as $y) { $chartYears[] = ['y' => $y['y'], 'by' => $y['by'], 'kwh' => round($y['kwh'], 1), 'proj' => $y['proj'] !== null ? round($y['proj'], 1) : null, 'export' => round($y['export'], 1)]; }
$chartMonths = []; foreach ($st['months'] as $yy => $ms) { $row = array_fill(1, 12, null); foreach ($ms as $mo => $m) { $row[$mo] = $m['partial'] ? null : round($m['kwh'], 1); } $chartMonths[$yy] = array_values($row); }
?>
<div class="page-head">
    <div><h1>Statistik</h1><p class="sub"><?= e($meter['name']) ?><?= $meter['location'] !== '' ? ' · ' . e($meter['location']) : '' ?></p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'])) ?>"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (!$st['has_data']): ?>
    <div class="alert alert-info">Für eine Statistik werden mindestens zwei Ablesungen benötigt.</div>
<?php else: ?>
<div class="row g-3 row-cols-2 row-cols-lg-4 mb-3">
    <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-lightning-charge"></i> Verbrauch gesamt</div><div class="k-val"><?= e($kw($st['total'])) ?> kWh</div><div class="k-sub">in <?= e($kw($st['span_days'])) ?> Tagen erfasst</div></div></div>
    <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-calendar-day"></i> Ø pro Tag</div><div class="k-val"><?= $perDay !== null ? e(nf($perDay, 1)) : '–' ?> kWh</div><div class="k-sub">≈ <?= $perDay !== null ? e($kw($perDay * 30.4375)) : '–' ?> kWh pro Monat</div></div></div>
    <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-calendar3"></i> Ø pro Jahr</div><div class="k-val"><?= $perDay !== null ? e($kw($perDay * 365)) : '–' ?> kWh</div><div class="k-sub">hochgerechnet aus dem Schnitt</div></div></div>
    <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-graph-up-arrow"></i> Stärkster Monat</div><div class="k-val"><?= $maxM ? e($kw($maxM[2])) . ' kWh' : '–' ?></div><div class="k-sub"><?= $maxM ? e($monthNames[$maxM[1] - 1] . ' ' . $maxM[0]) : 'noch kein voller Monat' ?><?= $minM ? ' · schwächster: ' . e($monthNames[$minM[1] - 1] . ' ' . $minM[0]) . ' (' . e($kw($minM[2])) . ')' : '' ?></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart"></i> Verbrauch je Kalenderjahr</div>
    <div class="card-body">
        <div class="chartbox"><canvas id="statYears" role="img" aria-label="Verbrauch je Jahr" data-years="<?= e(json_encode($chartYears)) ?>" data-tariffs="<?= e(json_encode($tariffs)) ?>"></canvas></div>
        <div class="table-responsive pay-table mt-3"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Jahr</th><th class="num">Verbrauch</th><?php foreach ($tariffs as $t): if (count($tariffs) > 1): ?><th class="num d-none d-md-table-cell"><?= e($t) ?></th><?php endif; endforeach; ?><th class="num">Ø/Tag</th><th class="num d-none d-sm-table-cell">Hochgerechnet</th><th class="num">Vorjahr</th><?php if ($hasExport): ?><th class="num d-none d-md-table-cell">Einspeisung</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach (array_reverse($years) as $y): ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= (int) $y['y'] ?><?php if ($y['partial']): ?><br><small class="text-body-secondary fw-normal"><?= e($kw($y['cov_days'])) ?> / <?= e($kw($y['days'])) ?> Tage</small><?php endif; ?></td>
                    <td class="num fw-semibold"><?= e($kw($y['kwh'])) ?> kWh</td>
                    <?php if (count($tariffs) > 1): foreach ($tariffs as $t): ?><td class="num d-none d-md-table-cell"><?= isset($y['by'][$t]) ? e($kw($y['by'][$t])) : '–' ?></td><?php endforeach; endif; ?>
                    <td class="num"><?= e(nf($y['per_day'], 1)) ?></td>
                    <td class="num d-none d-sm-table-cell"><?= $y['proj'] !== null ? '≈ ' . e($kw($y['proj'])) . ' kWh' : '–' ?></td>
                    <td class="num"><?php if ($y['delta'] !== null): ?><span class="chip chip-<?= $y['delta'] > 0 ? 'warning' : 'ok' ?>"><?= $y['delta'] > 0 ? '+' : '' ?><?= e(nf($y['delta'], 1)) ?> %</span><?php else: ?>–<?php endif; ?></td>
                    <?php if ($hasExport): ?><td class="num d-none d-md-table-cell"><?= $y['export'] > 0 ? e($kw($y['export'])) . ' kWh' : '–' ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="small text-body-secondary mt-2 mb-0"><i class="bi bi-info-circle"></i> Der Verbrauch wird zwischen zwei Ablesungen gleichmäßig auf die Tage verteilt (anteilig). Unvollständige Jahre sind aufs Volljahr hochgerechnet und mit dem Vorjahr verglichen.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-graph-up"></i> Monatsverlauf im Jahresvergleich</div>
    <div class="card-body">
        <div class="chartbox"><canvas id="statMonths" role="img" aria-label="Verbrauch je Monat" data-months="<?= e(json_encode($chartMonths)) ?>"></canvas></div>
        <div class="table-responsive pay-table mt-3"><table class="table table-sm table-hover align-middle mb-0 stat-months">
            <thead><tr><th>Monat</th><?php foreach (array_keys($st['months']) as $yy): ?><th class="num"><?= (int) $yy ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php for ($mo = 1; $mo <= 12; $mo++): ?>
                <tr><td><?= e($monthNames[$mo - 1]) ?></td>
                <?php foreach ($st['months'] as $yy => $ms): $m = $ms[$mo] ?? null; ?>
                    <td class="num"><?php if ($m): ?><span class="<?= $m['partial'] ? 'text-body-secondary' : '' ?>" <?= $m['partial'] ? 'title="Monat nur teilweise von Ablesungen abgedeckt"' : '' ?>><?= e($kw($m['kwh'])) ?><?= $m['partial'] ? '*' : '' ?></span><?php else: ?><span class="text-body-secondary">–</span><?php endif; ?></td>
                <?php endforeach; ?></tr>
            <?php endfor; ?>
            </tbody>
        </table></div>
        <p class="small text-body-secondary mt-2 mb-0">kWh je Monat, * = nur teilweise abgedeckt.</p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js" defer></script>
<?php endif; ?>
