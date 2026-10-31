<?php
/** @var array $meter @var array $stats  Ergebnis von Calc::meterStats() */
$last = $stats['last'];
// Hauptregister: Eintarif, sonst HT, sonst erstes Register
$main = null;
foreach (['ET', 'HT'] as $t) {
    foreach ($stats['regs'] as $x) {
        if ($main === null && $x['reg']['tariff'] === $t) { $main = $x; }
    }
}
$main ??= $stats['regs'][0] ?? null;
$kw = static fn(float $v) => number_format($v, 0, ',', '.');
$ago = $stats['days_ago'];
$agoTxt = $ago === null ? '' : ($ago === 0 ? 'heute' : ($ago === 1 ? 'gestern' : 'vor ' . $ago . ' Tagen'));
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="kpi-hero">
            <div>
                <div class="k-label"><i class="bi bi-speedometer2"></i> <?= $main ? e(Repo::registerTitle($main['reg'])) : 'Zählerstand' ?></div>
                <?php if ($last && $main && $main['value'] !== null): ?>
                    <div class="k-val"><?= e($kw($main['value'])) ?> kWh</div>
                    <div class="k-sub">Stand vom <?= e(fmt_date($last['read_at'])) ?> · <?= e($agoTxt) ?></div>
                <?php else: ?>
                    <div class="k-val">–</div>
                    <div class="k-sub">Noch keine Ablesung erfasst.</div>
                <?php endif; ?>
            </div>
            <div class="yr">
                <?php if ($main && $main['delta'] !== null): ?>
                    <div class="d-flex justify-content-between"><span>Seit der letzten Ablesung</span><b>+<?= e($kw($main['delta'])) ?> kWh</b></div>
                <?php else: ?>
                    <div class="opacity-75">Ab zwei Ablesungen wird der Verbrauch berechnet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 row-cols-2 row-cols-md-3">
            <?php foreach ($stats['regs'] as $x): if ($main && $x['reg']['id'] === $main['reg']['id']) { continue; } ?>
                <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-<?= $x['reg']['tariff'] === 'EXPORT' ? 'box-arrow-up' : 'lightning-charge' ?>"></i> <?= e(Repo::registerTitle($x['reg'])) ?></div>
                    <div class="k-val"><?= $x['value'] !== null ? e($kw($x['value'])) . ' kWh' : '–' ?></div>
                    <div class="k-sub"><?= $x['delta'] !== null ? '+' . e($kw($x['delta'])) . ' kWh seit letzter Ablesung' : 'Zählerstand' ?></div></div></div>
            <?php endforeach; ?>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-clock-history"></i> Letzte Ablesung</div>
                <div class="k-val"><?= $last ? e(fmt_date($last['read_at'])) : '–' ?></div><div class="k-sub"><?= $last ? e($agoTxt) : 'noch keine' ?></div></div></div>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-journal-check"></i> Ablesungen</div>
                <div class="k-val"><?= (int) $stats['count'] ?></div><div class="k-sub"><?= $stats['first'] ? 'seit ' . e(fmt_date($stats['first']['read_at'])) : 'noch keine' ?></div></div></div>
            <?php if ($stats['year']): ?>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-calendar-check"></i> Verbrauch <?= e($stats['year']) ?></div>
                <div class="k-val"><?= $stats['count'] > 1 ? e($kw($stats['year_kwh'])) . ' kWh' : '–' ?></div><div class="k-sub">laut Ablesungen im Jahr</div></div></div>
            <?php endif; ?>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-bar-chart"></i> Verbrauch erfasst</div>
                <div class="k-val"><?= $stats['count'] > 1 ? e($kw($stats['consumed'])) . ' kWh' : '–' ?></div><div class="k-sub">Summe aller Ablesezeiträume</div></div></div>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-calendar3"></i> Ø pro Monat</div>
                <div class="k-val"><?= $stats['per_month'] !== null ? e($kw($stats['per_month'])) . ' kWh' : '–' ?></div><div class="k-sub"><?= $stats['span_days'] >= 1 ? 'über ' . e(nf($stats['span_days'] / 30.4375, 0)) . ' Monate' : 'ab zwei Ablesungen' ?></div></div></div>
            <div class="col"><div class="kpi"><div class="k-label"><i class="bi bi-camera"></i> Belegfotos</div>
                <div class="k-val"><?= (int) $stats['photos'] ?></div><div class="k-sub"><?= (int) $stats['with_photo'] ?> von <?= (int) $stats['count'] ?> Ablesungen mit Foto</div></div></div>
        </div>
    </div>
</div>
