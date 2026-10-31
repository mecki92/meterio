<?php
/** @var array $st Ergebnis von Calc::settlement()  @var array $c */
$yr = $st['year'];
$reasons = reading_reasons();
$tl = tariff_labels();
$dt = static fn(int $t) => date('d.m.Y', $t);
$kw = static fn(float $v) => number_format($v, 0, ',', '.');
$nd = static fn(float $v) => number_format($v, 0, ',', '.');
$codes = [];
?>
<section class="settlement">
    <div class="settle-head">
        <div>
            <div class="settle-kicker">Jahresabrechnung</div>
            <h3 class="h5 mb-0">Vertragsjahr <?= (int) $yr['index'] ?> <span class="text-body-secondary fw-normal">· <?= e($yr['label']) ?></span></h3>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Drucken</button>
    </div>

    <?php if (!$st['complete']): ?>
        <div class="alert alert-warning py-2 small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Die Ablesungen decken nur <?= e(nf($st['covered_days'], 0)) ?> Tage dieses Zeitraums ab – die Abrechnung ist unvollständig.</div>
    <?php endif; ?>

    <div class="settle-kpis">
        <div><span>Energiekosten (brutto)</span><b><?= e(eur($st['total'])) ?></b></div>
        <div><span>Geleistete Abschläge (<?= (int) $st['paid_count'] ?>)</span><b><?= e(eur($st['paid'])) ?></b></div>
        <div class="<?= $st['balance'] >= 0 ? 'is-plus' : 'is-minus' ?>"><span><?= $st['balance'] >= 0 ? 'Guthaben' : 'Nachzahlung' ?></span><b><?= $st['balance'] >= 0 ? '+' : '−' ?><?= e(eur(abs($st['balance']))) ?></b></div>
    </div>

    <h4 class="settle-title"><i class="bi bi-lightning-charge"></i> Ihr Stromverbrauch im Detail</h4>
    <?php foreach ($st['usage'] as $u): ?>
        <div class="settle-sub"><?= e($u['reg']['label'] ?: ($tl[$u['reg']['tariff']] ?? '')) ?><?= !empty($u['reg']['obis']) ? ' · ' . e($u['reg']['obis']) : '' ?> <span class="chip chip-muted"><?= e($tl[$u['reg']['tariff']] ?? $u['reg']['tariff']) ?></span></div>
        <div class="table-responsive settle-table mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Zeitraum</th><th class="num">Stand alt</th><th class="num">Stand neu</th><th>Ablesung</th><th class="num">Verbrauch</th></tr></thead>
                <tbody>
                <?php foreach ($u['rows'] as $r):
                    if ($r['kwh'] == 0 && ($r['to'] - $r['from']) <= 86400 * 1.1) { continue; } ?>
                    <tr>
                        <td class="text-nowrap"><?= e($dt($r['from'])) ?> – <?= e($dt($r['to'] - ($r['to'] === $yr['end_ex'] ? 86400 : 0))) ?></td>
                        <td class="num"><?= e($kw($r['v0'])) ?><?= $r['est0'] ? '<sup>*</sup>' : '' ?></td>
                        <td class="num"><?= e($kw($r['v1'])) ?><?= $r['est1'] ? '<sup>*</sup>' : '' ?></td>
                        <td><?php if ($r['why'] === 'prorata'): ?><span class="chip chip-muted">anteilig</span><?php else: $rs = $reasons[$r['why']] ?? ['Ablesung', 'secondary']; if (isset($rs[2])) { $codes[$rs[2]] = $rs[0]; } ?><span class="chip chip-<?= e($rs[1]) ?>" title="<?= e($rs[0]) ?>"><?= e($rs[2] ?? $rs[0]) ?></span><?php endif; ?></td>
                        <td class="num fw-semibold"><?= e($kw($r['kwh'])) ?> kWh</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$u['rows']): ?><tr><td colspan="5" class="text-body-secondary">Keine Ablesungen im Zeitraum.</td></tr><?php endif; ?>
                </tbody>
                <tfoot><tr><td colspan="4">Gesamtverbrauch</td><td class="num"><?= e($kw($u['kwh'])) ?> kWh</td></tr></tfoot>
            </table>
        </div>
    <?php endforeach; ?>
    <p class="small text-body-secondary mb-1"><sup>*</sup> anteilig aus den benachbarten Ablesungen berechnet (lineare Verteilung).</p>
    <?php ksort($codes); if ($codes): ?><p class="small text-body-secondary">Ablesearten: <?php foreach ($codes as $cd => $lb): ?><span class="me-3"><b><?= e($cd) ?></b> = <?= e(preg_replace('/^[A-Z]\d · /u', '', $lb)) ?></span><?php endforeach; ?></p><?php endif; ?>

    <h4 class="settle-title"><i class="bi bi-coin"></i> Ihre Kosten für Strom im Detail</h4>
    <div class="table-responsive settle-table">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Position</th><th>Zeitraum</th><th class="num">Menge</th><th class="num">Preis</th><th class="num">Betrag</th></tr></thead>
            <tbody>
            <?php foreach ($st['lines'] as $l): ?>
                <tr><td>Arbeitspreis <?= e($tl[$l['tariff']] ?? $l['tariff']) ?></td><td class="text-nowrap small text-body-secondary"><?= e($dt($yr['start'])) ?> – <?= e($dt($yr['end_ex'] - 86400)) ?></td><td class="num"><?= e($kw($l['kwh'])) ?> kWh</td><td class="num"><?= e(price($l['price'])) ?> ct/kWh</td><td class="num"><?= e(eur($l['amount'])) ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($st['base']['segments'] as $sg): ?>
                <tr><td>Grundpreis</td><td class="text-nowrap small text-body-secondary"><?= e($dt($sg['from'])) ?> – <?= e($dt($sg['to'] - 86400)) ?></td><td class="num"><?= e($nd($sg['days'])) ?> von <?= (int) $sg['diy'] ?> Tagen</td><td class="num"><?= e(price($sg['per_year'])) ?> €/Jahr</td><td class="num"><?= e(eur($sg['amount'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <?php if ($st['net_basis']): ?>
                <tr class="sub"><td colspan="4">Summe netto</td><td class="num"><?= e(eur($st['sum_net'])) ?></td></tr>
                <tr class="sub"><td colspan="4">zzgl. USt <?= e(nf($st['vat_pct'], 0)) ?> %</td><td class="num"><?= e(eur($st['vat'])) ?></td></tr>
            <?php endif; ?>
            <?php if ($st['feedin']): ?>
                <tr class="sub"><td colspan="2">Einspeisevergütung</td><td class="num"><?= e($kw($st['feedin']['kwh'])) ?> kWh</td><td class="num"><?= e(price($st['feedin']['price'])) ?> ct/kWh</td><td class="num">− <?= e(eur($st['feedin']['amount'])) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($st['credits'] as $cr): ?>
                <tr class="sub"><td colspan="4">Gutschrift<?= $cr['label'] !== '' ? ' ' . e($cr['label']) : '' ?> (nach <?= (int) $cr['months'] ?> Monaten)</td><td class="num">− <?= e(eur($cr['amount'])) ?></td></tr>
            <?php endforeach; ?>
                <tr class="total"><td colspan="4">Ihre Energiekosten (brutto)</td><td class="num"><?= e(eur($st['total'])) ?></td></tr>
            <?php if (!$st['net_basis']): ?>
                <tr class="sub small"><td colspan="4">darin enthalten USt <?= e(nf($st['vat_pct'], 0)) ?> %</td><td class="num"><?= e(eur($st['vat'])) ?></td></tr>
            <?php endif; ?>
                <tr class="sub"><td colspan="4">Geleistete Abschläge (<?= (int) $st['paid_count'] ?>)</td><td class="num">− <?= e(eur($st['paid'])) ?></td></tr>
                <tr class="result <?= $st['balance'] >= 0 ? 'plus' : 'minus' ?>"><td colspan="4"><?= $st['balance'] >= 0 ? 'Guthaben' : 'Nachzahlung' ?></td><td class="num"><?= e(eur(abs($st['balance']))) ?></td></tr>
            </tfoot>
        </table>
    </div>
</section>
<?php if ($st !== end($settlements)): ?><hr class="my-4"><?php endif; ?>
