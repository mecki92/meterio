<?php
/** @var array $c @var ?array $meter @var array $payments @var ?array $sum @var ?array $edit */
$d = $c['data'];
$pe = $edit ?? ['id' => 0, 'paid_on' => date('Y-m-d'), 'data' => ['amount' => $d['monthly_advance'], 'status' => 'paid', 'kind' => 'advance', 'note' => '']];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h2 class="h3 m-0"><?= e($d['provider'] ?: 'Vertrag #' . $c['id']) ?></h2>
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

<div class="row g-3 my-1">
<div class="col-lg-5"><div class="card h-100">
    <div class="card-header">Konditionen</div>
    <div class="card-body">
    <ul class="small ps-3 mb-2">
        <li>Grundgebühr: <b><?= e(eur($d['base_fee'] !== null ? (float) $d['base_fee'] : null)) ?></b> / <?= $d['base_period'] === 'year' ? 'Jahr' : 'Monat' ?></li>
        <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT', 'feedin' => 'Einspeisevergütung'] as $k => $l): if ($d[$k] !== null): ?>
            <li><?= e($l) ?>: <b><?= e(nf((float) $d[$k], 2)) ?> ct/kWh</b></li>
        <?php endif; endforeach; ?>
        <?php if ($d['annual_kwh'] !== null): ?><li>Geschätzter Jahresverbrauch: <b><?= e(nf((float) $d['annual_kwh'], 0)) ?> kWh</b></li><?php endif; ?>
        <?php if ($d['monthly_advance'] !== null): ?><li>Monatlicher Abschlag: <b><?= e(eur((float) $d['monthly_advance'])) ?></b></li><?php endif; ?>
        <?php if ($d['min_term_months']): ?><li>Mindestvertragslaufzeit: <b><?= (int) $d['min_term_months'] ?> Monate</b></li><?php endif; ?>
    </ul>
    <?php if ($d['credits']): ?>
        <div class="small fw-semibold">Einmalige Gutschriften</div>
        <ul class="small ps-3 mb-2">
        <?php foreach ($d['credits'] as $cr): ?><li><?= (int) $cr['year'] ?>. Vertragsjahr: <b><?= e(eur((float) $cr['amount'])) ?></b><?= $cr['label'] !== '' ? ' – ' . e($cr['label']) : '' ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($d['note'] !== ''): ?><p class="small text-body-secondary mb-0"><?= e($d['note']) ?></p><?php endif; ?>
    </div>
</div></div>

<div class="col-lg-7"><div class="card h-100">
    <div class="card-header"><?= $edit ? 'Zahlung bearbeiten' : 'Zahlung erfassen' ?></div>
    <div class="card-body">
    <form method="post" action="<?= e(url($edit ? 'payments/' . $edit['id'] : 'contracts/' . $c['id'] . '/payments')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3 mb-3">
            <div class="col-sm-6"><label class="form-label">Datum</label><input type="date" name="paid_on" class="form-control" value="<?= e($pe['paid_on']) ?>" required></div>
            <div class="col-sm-6"><label class="form-label">Betrag (€)</label><input name="amount" class="form-control" inputmode="decimal" value="<?= e($pe['data']['amount'] !== null ? number_format((float) $pe['data']['amount'], 2, ',', '') : '') ?>" required></div>
            <div class="col-sm-6"><label class="form-label">Art</label>
                <select name="kind" class="form-select">
                    <option value="advance" <?= $pe['data']['kind'] === 'advance' ? 'selected' : '' ?>>Monatsabschlag</option>
                    <option value="special" <?= $pe['data']['kind'] === 'special' ? 'selected' : '' ?>>Sonderzahlung / Nachzahlung / Erstattung</option>
                </select></div>
            <div class="col-sm-6"><label class="form-label">Status</label>
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
</div></div>
</div>

<div class="card my-3">
    <div class="card-header">Zahlungen (<?= count($payments) ?>)</div>
    <?php if (!$payments): ?><div class="card-body">Noch keine Zahlungen erfasst.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-striped align-middle mb-0">
        <thead><tr><th>Datum</th><th>Art</th><th>Status</th><th class="num">Betrag</th><th>Notiz</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(fmt_date($p['paid_on'])) ?></td>
                <td><?= $p['data']['kind'] === 'special' ? 'Sonderzahlung' : 'Abschlag' ?></td>
                <td><?= $p['data']['status'] === 'paid' ? '<span class="badge text-bg-success">bezahlt</span>' : '<span class="badge text-bg-secondary">offen</span>' ?></td>
                <td class="num"><?= e(eur((float) $p['data']['amount'])) ?></td>
                <td class="small text-body-secondary"><?= e($p['data']['note']) ?></td>
                <td class="num text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contracts/' . $c['id'] . '?edit=' . $p['id'])) ?>">Bearbeiten</a>
                    <form method="post" action="<?= e(url('payments/' . $p['id'] . '/delete')) ?>" class="d-inline" data-confirm="Zahlung löschen?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<details class="mt-4">
    <summary class="text-danger">Vertrag löschen</summary>
    <form method="post" action="<?= e(url('contracts/' . $c['id'] . '/delete')) ?>" class="mt-2" data-confirm="Vertrag samt allen Zahlungen löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Vertrag löschen</button>
    </form>
</details>
