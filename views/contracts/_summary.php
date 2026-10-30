<?php
/** @var array $sum  Ergebnis von Calc::summary() */
$bal = $sum['balance_forecast'];
?>
<div class="kpis">
    <div class="kpi"><span>Vertragsjahr</span><b><?= e($sum['year']['label']) ?></b></div>
    <?php if ($sum['cost']): $k = $sum['cost']['kwh']; ?>
        <div class="kpi"><span>Verbrauch bisher</span><b><?= e(nf($k['ET'] + $k['HT'] + $k['NT'], 0)) ?> kWh</b>
            <?php if ($k['EXPORT'] > 0): ?><small>Einspeisung <?= e(nf($k['EXPORT'], 0)) ?> kWh</small><?php endif; ?></div>
        <div class="kpi"><span>Realkosten bisher</span><b><?= e(eur($sum['cost']['total'])) ?></b><small>Stand <?= e(date('d.m.Y', $sum['accrual_to'])) ?></small></div>
    <?php endif; ?>
    <div class="kpi"><span>Abschläge bezahlt</span><b><?= e(eur($sum['paid'])) ?></b><small><?= (int) $sum['paid_count'] ?> Zahlung(en)</small></div>
    <?php if ($sum['balance_now'] !== null): ?>
        <div class="kpi <?= $sum['balance_now'] >= 0 ? 'good' : 'bad' ?>"><span>Soll-Ist aktuell</span>
            <b><?= $sum['balance_now'] >= 0 ? '+' : '' ?><?= e(eur($sum['balance_now'])) ?></b><small><?= $sum['balance_now'] >= 0 ? 'bisher überzahlt' : 'bisher unterzahlt' ?></small></div>
    <?php endif; ?>
    <?php if ($sum['forecast_cost'] !== null): ?>
        <div class="kpi"><span>Prognose Jahreskosten</span><b><?= e(eur($sum['forecast_cost'])) ?></b>
            <small>≈ <?= e(nf($sum['forecast_kwh'], 0)) ?> kWh<?= $sum['annual_kwh'] ? ' (Vertrag: ' . e(nf($sum['annual_kwh'], 0)) . ')' : '' ?></small></div>
        <div class="kpi <?= $bal >= 0 ? 'good' : 'bad' ?>"><span><?= $bal >= 0 ? 'Voraussichtl. Guthaben' : 'Voraussichtl. Nachzahlung' ?></span>
            <b><?= e(eur(abs($bal))) ?></b><small>nach Abschlägen bis Jahresende</small></div>
    <?php endif; ?>
</div>
<?php foreach ($sum['warnings'] as $w): ?><p class="small muted">ℹ️ <?= e($w) ?></p><?php endforeach; ?>
