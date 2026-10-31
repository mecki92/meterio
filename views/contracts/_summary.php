<?php
/** @var array $sum  Ergebnis von Calc::summary() */
$bal = $sum['balance_forecast'];
$yr = $sum['year'];
$span = max(1, $yr['end_ex'] - $yr['start']);
$pct = (int) round(max(0, min(100, (time() - $yr['start']) / $span * 100)));
$daysLeft = max(0, (int) ceil(($yr['end_ex'] - time()) / 86400));
$k = $sum['cost'] ? $sum['cost']['kwh'] : null;

if ($bal !== null) {
    $heroCls = $bal >= 0 ? 'good' : 'bad';
    $heroLabel = $bal >= 0 ? 'Voraussichtliches Guthaben' : 'Voraussichtliche Nachzahlung';
    $heroVal = eur(abs($bal));
    $heroSub = 'Prognose zum Ende des Vertragsjahres, nach Abschlägen';
} elseif ($sum['balance_now'] !== null) {
    $heroCls = $sum['balance_now'] >= 0 ? 'good' : 'bad';
    $heroLabel = 'Soll-Ist aktuell';
    $heroVal = ($sum['balance_now'] >= 0 ? '+' : '') . eur($sum['balance_now']);
    $heroSub = $sum['balance_now'] >= 0 ? 'bisher überzahlt' : 'bisher unterzahlt';
} else {
    $heroCls = '';
    $heroLabel = 'Auswertung';
    $heroVal = '–';
    $heroSub = 'Es werden mindestens zwei Ablesungen benötigt.';
}
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="kpi-hero <?= $heroCls ?>">
            <div>
                <div class="k-label"><?= e($heroLabel) ?></div>
                <div class="k-val"><?= e($heroVal) ?></div>
                <div class="k-sub"><?= e($heroSub) ?></div>
            </div>
            <div class="yr">
                <div class="d-flex justify-content-between"><span><?= (int) $yr['index'] ?>. Vertragsjahr</span><span><?= $pct ?> %</span></div>
                <div class="progress" role="progressbar" aria-label="Fortschritt Vertragsjahr" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                <div class="opacity-75"><?= e($yr['label']) ?><?= $daysLeft > 0 ? ' · noch ' . $daysLeft . ' Tage' : '' ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 row-cols-2 row-cols-md-3">
            <?php if ($sum['balance_now'] !== null): ?>
                <div class="col"><div class="kpi <?= $sum['balance_now'] >= 0 ? 'good' : 'bad' ?>"><div class="k-label"><i class="bi bi-arrow-left-right"></i> Soll-Ist aktuell</div>
                    <div class="k-val"><?= $sum['balance_now'] >= 0 ? '+' : '' ?><?= e(eur($sum['balance_now'])) ?></div><div class="k-sub"><?= $sum['balance_now'] >= 0 ? 'bisher überzahlt' : 'bisher unterzahlt' ?></div></div></div>
            <?php endif; ?>
            <?php if ($sum['cost']): ?>
                <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-receipt"></i> Realkosten bisher</div>
                    <div class="k-val"><?= e(eur($sum['cost']['total'])) ?></div>
                    <div class="k-sub">Stand <?= e(date('d.m.Y', $sum['accrual_to'])) ?><?= $sum['cost']['bonus'] > 0 ? ' · inkl. −' . e(eur($sum['cost']['bonus'])) . ' Gutschrift' : '' ?></div></div></div>
            <?php endif; ?>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-wallet2"></i> Abschläge bezahlt</div>
                <div class="k-val"><?= e(eur($sum['paid'])) ?></div><div class="k-sub"><?= (int) $sum['paid_count'] ?> Zahlung(en)</div></div></div>
            <?php if ($k): ?>
                <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-lightning-charge"></i> Verbrauch bisher</div>
                    <div class="k-val"><?= e(nf($k['ET'] + $k['HT'] + $k['NT'], 0)) ?> kWh</div>
                    <div class="k-sub"><?= $k['EXPORT'] > 0 ? 'Einspeisung ' . e(nf($k['EXPORT'], 0)) . ' kWh' : 'seit Beginn des Vertragsjahres' ?></div></div></div>
            <?php endif; ?>
            <?php if ($sum['forecast_cost'] !== null): ?>
                <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-graph-up-arrow"></i> Prognose Jahreskosten</div>
                    <div class="k-val"><?= e(eur($sum['forecast_cost'])) ?></div><div class="k-sub">Abschläge gesamt: <?= e(eur($sum['expected_payments'])) ?></div></div></div>
                <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-bar-chart"></i> Prognose Verbrauch</div>
                    <div class="k-val">≈ <?= e(nf($sum['forecast_kwh'], 0)) ?> kWh</div>
                    <div class="k-sub"><?= $sum['annual_kwh'] ? 'Vertrag: ' . e(nf($sum['annual_kwh'], 0)) . ' kWh' : 'bis Ende Vertragsjahr' ?></div></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if ($sum['warnings']): ?><div class="mt-3"><?php foreach ($sum['warnings'] as $w): ?><p class="small text-body-secondary mb-1"><i class="bi bi-info-circle"></i> <?= e($w) ?></p><?php endforeach; ?></div><?php endif; ?>
