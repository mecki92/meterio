<?php
/** @var array $c @var ?array $meter @var array $payments @var ?array $sum @var ?array $edit @var array $settlements */
$d = $c['data'];
$refTs = max(strtotime($c['start_date']), min(time(), $c['end_date'] ? strtotime($c['end_date']) : time()));
$de = Calc::dataAt($c, $refTs);   // aktuell geltende Preise (inkl. Preisänderungen)
$changes = Calc::priceChanges($c);
$periodMax = Calc::maxPeriod($c);
$pe = $edit ?? ['id' => 0, 'paid_on' => date('Y-m-d'), 'data' => ['amount' => $de['monthly_advance'], 'status' => 'paid', 'kind' => 'advance', 'note' => '']];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h2 class="h3 m-0"><?= e($d['provider'] ?: 'Vertrag #' . $c['id']) ?><?php if (strtotime($c['start_date']) > time()): ?> <span class="chip chip-info align-middle ms-2"><i class="bi bi-calendar-event"></i> zukünftig</span><?php endif; ?></h2>
    <a class="btn btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '/edit')) ?>">Bearbeiten</a>
</div>
<p class="text-body-secondary small">
    Zähler: <?= $meter ? '<a href="' . e(url('meters/' . $meter['id'])) . '">' . e($meter['name']) . '</a>' : '?' ?>
    · <?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?>
    <?= $d['contract_no'] !== '' ? ' · Nr. ' . e($d['contract_no']) : '' ?>
    <?php if ($mte = Calc::minTermEnd($c)): ?> · Mindestlaufzeit <?= (int) $d['min_term_months'] ?> Monate, bis <?= e($mte->format('d.m.Y')) ?>
        <?= $mte->getTimestamp() + 86400 > time() ? '' : '(erreicht)' ?><?php endif; ?>
</p>

<?php if ($sum) { require __DIR__ . '/_summary.php'; } ?>

<?php
$hasSettle = !empty($settlements);
$paidSum = 0.0; $openSum = 0.0;
foreach ($payments as $p) { if ($p['data']['status'] === 'paid') { $paidSum += (float) $p['data']['amount']; } else { $openSum += (float) $p['data']['amount']; } }
$bonuses = Calc::bonusPayments($c);
$bonusSum = array_sum(array_column($bonuses, 'amount'));
$tabSettle = $hasSettle && !$edit;   // Abrechnung zuerst bei abgeschlossenen Verträgen
?>
<div class="card my-3">
    <div class="card-header">Konditionen</div>
    <div class="card-body">
    <dl class="cond-grid mb-0">
        <?php $pb = '<span class="text-body-secondary fw-normal">' . ($d['price_basis'] === 'net' ? 'netto' : 'brutto') . '</span>'; ?>
        <div><dt>Grundgebühr</dt><dd><?= e(eur($de['base_fee'] !== null ? (float) $de['base_fee'] : null)) ?> / <?= $de['base_period'] === 'year' ? 'Jahr' : 'Monat' ?> <?= $pb ?></dd></div>
        <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT', 'feedin' => 'Einspeisevergütung'] as $k => $l): if ($de[$k] !== null): ?>
            <div><dt><?= e($l) ?></dt><dd><?= e(price((float) $de[$k])) ?> ct/kWh<?= $k !== 'feedin' ? ' ' . $pb : '' ?></dd></div>
        <?php endif; endforeach; ?>
        <?php if ($d['annual_kwh'] !== null): ?><div><dt>Jahresverbrauch (Vertrag)</dt><dd><?= e(nf((float) $d['annual_kwh'], 0)) ?> kWh</dd></div><?php endif; ?>
        <?php if ($de['monthly_advance'] !== null): ?><div><dt>Monatlicher Abschlag</dt><dd><?= e(eur((float) $de['monthly_advance'])) ?></dd></div><?php endif; ?>
        <?php if (Calc::firstBillingEx($c)): ?><div><dt>Erste Abrechnung</dt><dd><?= e(fmt_date($d['first_billing'])) ?></dd></div><?php endif; ?>
        <?php if (Calc::billingMonths($c) !== 12): ?><div><dt>Abrechnungszeitraum</dt><dd><?= (int) Calc::billingMonths($c) ?> <?= Calc::billingMonths($c) === 1 ? 'Monat' : 'Monate' ?></dd></div><?php endif; ?>
        <?php if ($d['min_term_months']): ?><div><dt>Mindestlaufzeit</dt><dd><?= (int) $d['min_term_months'] ?> Monate</dd></div><?php endif; ?>
        <?php foreach ($d['credits'] as $cr): ?>
            <div><dt>Bonus<?= $cr['label'] !== '' ? ' · ' . e($cr['label']) : '' ?></dt><dd><?= e(eur((float) $cr['amount'])) ?> <span class="text-body-secondary fw-normal">nach <?= (int) $cr['months'] ?> Monaten <?php $endEx = $c['end_date'] ? strtotime($c['end_date'] . ' +1 day') : null; ?><?= $endEx !== null && Calc::creditTs($c, $cr) > $endEx ? '· entfällt (Vertrag endet vorher)' : '· Buchung am ' . e(date('d.m.Y', Calc::bonusDueTs($c, $cr))) ?></span></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if ($changes): ?>
        <h3 class="h6 mt-4 mb-2"><i class="bi bi-graph-up-arrow"></i> Preisänderungen</h3>
        <ul class="pc-timeline">
            <li><span class="pc-date text-body-secondary"><?= e(fmt_date($c['start_date'])) ?></span><span>Vertragsbeginn – Ausgangspreise</span></li>
            <?php foreach ($changes as [$cts, $pc]): $parts = [];
                if (isset($pc['base_fee'])) { $parts[] = 'Grundgebühr ' . eur((float) $pc['base_fee']) . ' / ' . (($pc['base_period'] ?? 'month') === 'year' ? 'Jahr' : 'Monat'); }
                foreach (['price_et' => 'Arbeitspreis', 'price_ht' => 'HT', 'price_nt' => 'NT', 'feedin' => 'Einspeisung'] as $k => $l) { if (isset($pc[$k])) { $parts[] = $l . ' ' . price((float) $pc[$k]) . ' ct/kWh'; } }
                if (isset($pc['monthly_advance'])) { $parts[] = 'Abschlag ' . eur((float) $pc['monthly_advance']); } ?>
                <li><span class="pc-date"><?= e(date('d.m.Y', $cts)) ?></span>
                    <span><?= !empty($pc['label']) ? '<b>' . e($pc['label']) . '</b> · ' : '' ?><?= e(implode(' · ', $parts)) ?>
                    <?= $cts > time() ? '<span class="chip chip-info ms-1">geplant</span>' : '' ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($d['note'] !== ''): ?><p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-sticky"></i> <?= e($d['note']) ?></p><?php endif; ?>
    </div>
</div>

<?php
$rows = [];
                            foreach ($payments as $p) { $rows[] = ['ts' => strtotime($p['paid_on']), 'p' => $p, 'b' => null]; }
                            foreach ($bonuses as $b) { $rows[] = ['ts' => $b['ts'], 'p' => null, 'b' => $b]; }
                            usort($rows, static fn($x, $y) => $y['ts'] <=> $x['ts']);
                            // nach Abrechnungszeitraum gruppieren (neueste zuerst)
                            $groups = [];
                            foreach ($rows as $row) {
                                $gi = $row['b'] ? Calc::creditYearIndex($c, ['months' => $row['b']['months']]) : Calc::paymentPeriod($c, $row['p']);
                                $groups[$gi][] = $row;
                            }
foreach (range(1, max(1, $periodMax)) as $k) { $groups[$k] ??= []; }   // auch Zeiträume ohne Zahlungen
krsort($groups);
$stByIdx = [];
foreach ($settlements as $st0) { $stByIdx[$st0['year']['index']] = $st0; }
$selReq = (int) ($_GET['period'] ?? 0);
$sel = $edit ? Calc::paymentPeriod($c, $edit) : ($selReq !== 0 && isset($groups[$selReq]) ? $selReq : max(1, $periodMax));
$grows = $groups[$sel] ?? [];
$st = $stByIdx[$sel] ?? null;
$tabSettle = $st && !$edit && !isset($_GET['period']);   // Abrechnung zuerst bei abgeschlossenen Verträgen
// Neue Zahlung: Zeitraum vorwählen, wenn ein anderer als der aktuelle angezeigt wird (z. B. Rückstand)
$todayPeriod = Calc::paymentPeriod($c, ['paid_on' => date('Y-m-d'), 'data' => []]);
$preSel = $edit ? (int) ($pe['data']['period'] ?? 0) : ($sel >= 1 && $sel < 9999 && $sel !== $todayPeriod ? $sel : 0);
$gPaid = 0.0; $gOpen = 0.0; $gBonus = 0.0; $gCount = 0;
foreach ($grows as $gr) {
    if ($gr['b']) { $gBonus += $gr['b']['amount']; continue; }
    $gCount++;
    if ($gr['p']['data']['status'] === 'paid') { $gPaid += (float) $gr['p']['data']['amount']; } else { $gOpen += (float) $gr['p']['data']['amount']; }
}
$periodLabel = static function (int $gi) use ($c): string {
    if ($gi === 0) { return 'Vor Vertragsbeginn (Datum prüfen)'; }
    if ($gi === 9999) { return 'Nach Vertragsende (Datum prüfen)'; }
    return $gi . '. · ' . Calc::periodInfo($c, $gi)['label'];
};
?>
<div class="card my-3" id="contract-tabs">
    <div class="period-bar">
        <?php $keys = array_keys($groups); sort($keys); $pos = array_search($sel, $keys, true);
              $prevK = $pos !== false && $pos > 0 ? $keys[$pos - 1] : null; $nextK = $pos !== false && isset($keys[$pos + 1]) ? $keys[$pos + 1] : null; ?>
        <form method="get" action="<?= e(url('contracts/' . $c['id'])) ?>" id="period-form" class="period-pick">
            <label for="period-sel" class="period-lbl"><i class="bi bi-calendar-range"></i> <?= e(period_word($c)) ?></label>
            <div class="d-flex align-items-center gap-1">
                <a class="btn btn-outline-secondary btn-sm period-nav <?= $prevK === null ? 'disabled' : '' ?>" <?= $prevK !== null ? 'href="' . e(url('contracts/' . $c['id'] . '?period=' . $prevK)) . '"' : 'aria-disabled="true"' ?> title="Vorheriger Zeitraum" aria-label="Vorheriger Zeitraum"><i class="bi bi-chevron-left"></i></a>
                <select id="period-sel" name="period" class="form-select">
                    <?php foreach ($groups as $gi => $_g): ?><option value="<?= (int) $gi ?>" <?= $gi === $sel ? 'selected' : '' ?>><?= e($periodLabel((int) $gi)) ?></option><?php endforeach; ?>
                </select>
                <a class="btn btn-outline-secondary btn-sm period-nav <?= $nextK === null ? 'disabled' : '' ?>" <?= $nextK !== null ? 'href="' . e(url('contracts/' . $c['id'] . '?period=' . $nextK)) . '"' : 'aria-disabled="true"' ?> title="Nächster Zeitraum" aria-label="Nächster Zeitraum"><i class="bi bi-chevron-right"></i></a>
            </div>
            <noscript><button class="btn btn-sm btn-outline-secondary mt-1" type="submit">Anzeigen</button></noscript>
        </form>
        <div class="period-sum">
            <?php if ($st): ?><span class="chip <?= $st['balance'] >= 0 ? 'chip-ok' : 'chip-warning' ?>"><?= $st['balance'] >= 0 ? 'Guthaben +' : 'Nachzahlung −' ?><?= e(eur(abs($st['balance']))) ?></span><?php endif; ?>
            <span class="small text-body-secondary">bezahlt <b class="text-body"><?= e(eur($gPaid)) ?></b></span>
        </div>
    </div>
    <div class="card-header pb-0 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs mb-0" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link <?= $tabSettle ? '' : 'active' ?>" id="tab-pay" data-bs-toggle="tab" data-bs-target="#pane-pay" type="button" role="tab"><i class="bi bi-cash-coin"></i> Zahlungen <span class="badge rounded-pill text-bg-secondary ms-1"><?= $gCount ?></span></button></li>
            <?php if ($st): ?>
            <li class="nav-item" role="presentation"><button class="nav-link <?= $tabSettle ? 'active' : '' ?>" id="tab-settle" data-bs-toggle="tab" data-bs-target="#pane-settle" type="button" role="tab"><i class="bi bi-receipt"></i> Abrechnung</button></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade <?= $tabSettle ? '' : 'show active' ?>" id="pane-pay" role="tabpanel" tabindex="0">
            <div class="card-body">
                <div class="pay-stats mb-3">
                    <div><span>Bezahlt</span><b><?= e(eur($gPaid)) ?></b></div>
                    <div><span>Offen</span><b><?= e(eur($gOpen)) ?></b></div>
                    <?php if ($gBonus > 0): ?><div><span>Bonus</span><b><?= e(eur($gBonus)) ?></b></div><?php endif; ?>
                    <div><span>Zahlungen</span><b><?= $gCount ?></b></div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-8">
                    <?php if (!$grows): ?>
                        <p class="text-body-secondary mb-0">Keine Zahlungen in diesem Zeitraum.</p>
                    <?php else: ?>
                        <div class="table-responsive pay-table"><table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Datum</th><th class="d-none d-md-table-cell">Art</th><th>Status</th><th class="num">Betrag</th><th class="d-none d-md-table-cell">Notiz</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($grows as $row):
                                if ($row['b']): $b = $row['b']; $due = $b['ts'] <= time() + 86399; ?>
                                <tr class="row-bonus">
                                    <td class="text-nowrap"><?= e(date('d.m.Y', $b['ts'])) ?></td>
                                    <td class="d-none d-md-table-cell"><i class="bi bi-gift"></i> Bonus</td>
                                    <td><?= $due ? '<span class="chip chip-ok">gutgeschrieben</span>' : '<span class="chip chip-info">erwartet</span>' ?></td>
                                    <td class="num fw-semibold"><?= e(eur($b['amount'])) ?></td>
                                    <td class="small text-body-secondary d-none d-md-table-cell"><?= e($b['label'] !== '' ? $b['label'] : 'Einmalige Gutschrift') ?> · nach <?= (int) $b['months'] ?> Monaten</td>
                                    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '/edit')) ?>" title="Im Vertrag ändern" aria-label="Im Vertrag ändern"><i class="bi bi-pencil"></i></a></td>
                                </tr>
                                <?php continue; endif; $p = $row['p']; ?>
                                <tr class="<?= $edit && $edit['id'] === $p['id'] ? 'table-active' : '' ?>">
                                    <td class="text-nowrap"><?= e(fmt_date($p['paid_on'])) ?></td>
                                    <td class="d-none d-md-table-cell"><?= $p['data']['kind'] === 'special' ? 'Sonderzahlung' : 'Abschlag' ?></td>
                                    <td><?= $p['data']['status'] === 'paid' ? '<span class="chip chip-ok">bezahlt</span>' : '<span class="chip chip-muted">offen</span>' ?></td>
                                    <td class="num fw-semibold"><?= e(eur((float) $p['data']['amount'])) ?></td>
                                    <td class="small text-body-secondary d-none d-md-table-cell"><?= e($p['data']['note']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '?edit=' . $p['id'] . '&period=' . (int) $sel)) ?>" title="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('payments/' . $p['id'] . '/delete')) ?>" class="d-inline" data-confirm="Zahlung löschen?"><?= csrf_field() ?><input type="hidden" name="view" value="<?= (int) $sel ?>"><button class="btn btn-sm btn-outline-danger" type="submit" title="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="pay-form">
                        <h3 class="h6 mb-3"><?= $edit ? 'Zahlung bearbeiten' : 'Zahlung erfassen' ?></h3>
                        <form method="post" action="<?= e(url($edit ? 'payments/' . $edit['id'] : 'contracts/' . $c['id'] . '/payments')) ?>">
        <?= csrf_field() ?><input type="hidden" name="view" value="<?= (int) $sel ?>">
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-12"><label class="form-label">Datum</label><input type="date" name="paid_on" class="form-control" value="<?= e($pe['paid_on']) ?>" required></div>
            <div class="col-6 col-lg-12"><label class="form-label">Betrag (€)</label><input name="amount" class="form-control" inputmode="decimal" value="<?= e($pe['data']['amount'] !== null ? number_format((float) $pe['data']['amount'], 2, ',', '') : '') ?>" required></div>
            <div class="col-6 col-lg-12"><label class="form-label">Art</label>
                <select name="kind" class="form-select">
                    <option value="advance" <?= $pe['data']['kind'] === 'advance' ? 'selected' : '' ?>>Monatsabschlag</option>
                    <option value="special" <?= $pe['data']['kind'] === 'special' ? 'selected' : '' ?>>Sonderzahlung / Nachzahlung / Erstattung</option>
                </select></div>
            <div class="col-6 col-lg-12"><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="paid" <?= $pe['data']['status'] === 'paid' ? 'selected' : '' ?>>Bezahlt</option>
                    <option value="open" <?= $pe['data']['status'] === 'open' ? 'selected' : '' ?>>Offen (nicht in Berechnung)</option>
                </select></div>
        </div>
        <?php if ($periodMax > 1): ?>
        <div class="mb-3"><label class="form-label" for="p-period">Abrechnungszeitraum<?= info('Normalerweise wird die Zahlung automatisch nach ihrem Datum zugeordnet. Wähle einen Zeitraum, wenn die Zahlung zu einer früheren Abrechnung gehört, z. B. Raten für einen Rückstand.') ?></label>
            <select id="p-period" name="period" class="form-select"><option value="">automatisch (nach Datum)</option>
                <?php for ($k = $periodMax; $k >= 1; $k--): ?><option value="<?= $k ?>" <?= $k === $preSel ? 'selected' : '' ?>><?= $k ?>. · <?= e(Calc::periodInfo($c, $k)['label']) ?></option><?php endfor; ?>
            </select></div>
        <?php endif; ?>
        <div class="mb-3"><label class="form-label">Notiz</label><input name="note" class="form-control" value="<?= e($pe['data']['note']) ?>" maxlength="200"></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $edit ? 'Aktualisieren' : 'Zahlung speichern' ?></button>
            <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '?period=' . (int) $sel)) ?>#zahlungen">Abbrechen</a><?php endif; ?>
        </div>
    </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($st): ?>
        <div class="tab-pane fade <?= $tabSettle ? 'show active' : '' ?>" id="pane-settle" role="tabpanel" tabindex="0">
            <div class="card-body"><?php require __DIR__ . '/_settlement.php'; ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<details class="mt-4">
    <summary class="text-danger">Vertrag löschen</summary>
    <form method="post" action="<?= e(url('contracts/' . $c['id'] . '/delete')) ?>" class="mt-2" data-confirm="Vertrag samt allen Zahlungen löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Vertrag löschen</button>
    </form>
</details>
