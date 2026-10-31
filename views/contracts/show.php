<?php
/** @var array $c @var ?array $meter @var array $payments @var ?array $sum @var ?array $edit @var array $settlements */
$d = $c['data'];
$pe = $edit ?? ['id' => 0, 'paid_on' => date('Y-m-d'), 'data' => ['amount' => $d['monthly_advance'], 'status' => 'paid', 'kind' => 'advance', 'note' => '']];
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
$tabSettle = $hasSettle && !$edit;   // Abrechnung zuerst bei abgeschlossenen Verträgen
?>
<div class="card my-3">
    <div class="card-header">Konditionen</div>
    <div class="card-body">
    <dl class="cond-grid mb-0">
        <div><dt>Preise</dt><dd><?= $d['price_basis'] === 'net' ? 'netto' : 'brutto' ?>, USt <?= e(nf((float) $d['vat_rate'], 0)) ?> %</dd></div>
        <div><dt>Grundgebühr</dt><dd><?= e(eur($d['base_fee'] !== null ? (float) $d['base_fee'] : null)) ?> / <?= $d['base_period'] === 'year' ? 'Jahr' : 'Monat' ?></dd></div>
        <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT', 'feedin' => 'Einspeisevergütung'] as $k => $l): if ($d[$k] !== null): ?>
            <div><dt><?= e($l) ?></dt><dd><?= e(price((float) $d[$k])) ?> ct/kWh</dd></div>
        <?php endif; endforeach; ?>
        <?php if ($d['annual_kwh'] !== null): ?><div><dt>Jahresverbrauch (Vertrag)</dt><dd><?= e(nf((float) $d['annual_kwh'], 0)) ?> kWh</dd></div><?php endif; ?>
        <?php if ($d['monthly_advance'] !== null): ?><div><dt>Monatlicher Abschlag</dt><dd><?= e(eur((float) $d['monthly_advance'])) ?></dd></div><?php endif; ?>
        <?php if ($d['min_term_months']): ?><div><dt>Mindestlaufzeit</dt><dd><?= (int) $d['min_term_months'] ?> Monate</dd></div><?php endif; ?>
        <?php foreach ($d['credits'] as $cr): ?>
            <div><dt>Gutschrift<?= $cr['label'] !== '' ? ' · ' . e($cr['label']) : '' ?></dt><dd><?= e(eur((float) $cr['amount'])) ?> <span class="text-body-secondary fw-normal">nach <?= (int) $cr['months'] ?> Monaten (ab <?= e((new DateTimeImmutable($c['start_date']))->modify('+' . (int) $cr['months'] . ' months')->format('d.m.Y')) ?>)</span></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if ($d['note'] !== ''): ?><p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-sticky"></i> <?= e($d['note']) ?></p><?php endif; ?>
    </div>
</div>

<div class="card my-3" id="contract-tabs">
    <div class="card-header pb-0 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs mb-0" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link <?= $tabSettle ? '' : 'active' ?>" id="tab-pay" data-bs-toggle="tab" data-bs-target="#pane-pay" type="button" role="tab"><i class="bi bi-cash-coin"></i> Zahlungen <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($payments) ?></span></button></li>
            <?php if ($hasSettle): ?>
            <li class="nav-item" role="presentation"><button class="nav-link <?= $tabSettle ? 'active' : '' ?>" id="tab-settle" data-bs-toggle="tab" data-bs-target="#pane-settle" type="button" role="tab"><i class="bi bi-receipt"></i> Abrechnung</button></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade <?= $tabSettle ? '' : 'show active' ?>" id="pane-pay" role="tabpanel" tabindex="0">
            <div class="card-body">
                <div class="pay-stats mb-3">
                    <div><span>Bezahlt</span><b><?= e(eur($paidSum)) ?></b></div>
                    <div><span>Offen</span><b><?= e(eur($openSum)) ?></b></div>
                    <div><span>Zahlungen</span><b><?= count($payments) ?></b></div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-8">
                    <?php if (!$payments): ?>
                        <p class="text-body-secondary mb-0">Noch keine Zahlungen erfasst.</p>
                    <?php else: ?>
                        <div class="table-responsive pay-table"><table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Datum</th><th class="d-none d-md-table-cell">Art</th><th>Status</th><th class="num">Betrag</th><th class="d-none d-md-table-cell">Notiz</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr class="<?= $edit && $edit['id'] === $p['id'] ? 'table-active' : '' ?>">
                                    <td class="text-nowrap"><?= e(fmt_date($p['paid_on'])) ?></td>
                                    <td class="d-none d-md-table-cell"><?= $p['data']['kind'] === 'special' ? 'Sonderzahlung' : 'Abschlag' ?></td>
                                    <td><?= $p['data']['status'] === 'paid' ? '<span class="chip chip-ok">bezahlt</span>' : '<span class="chip chip-muted">offen</span>' ?></td>
                                    <td class="num fw-semibold"><?= e(eur((float) $p['data']['amount'])) ?></td>
                                    <td class="small text-body-secondary d-none d-md-table-cell"><?= e($p['data']['note']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '?edit=' . $p['id'])) ?>" title="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('payments/' . $p['id'] . '/delete')) ?>" class="d-inline" data-confirm="Zahlung löschen?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button></form>
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
        <?= csrf_field() ?>
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
        <div class="mb-3"><label class="form-label">Notiz</label><input name="note" class="form-control" value="<?= e($pe['data']['note']) ?>" maxlength="200"></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $edit ? 'Aktualisieren' : 'Zahlung speichern' ?></button>
            <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'])) ?>">Abbrechen</a><?php endif; ?>
        </div>
    </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($hasSettle): ?>
        <div class="tab-pane fade <?= $tabSettle ? 'show active' : '' ?>" id="pane-settle" role="tabpanel" tabindex="0">
            <div class="card-body">
                <?php foreach ($settlements as $st) { require __DIR__ . '/_settlement.php'; } ?>
            </div>
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
