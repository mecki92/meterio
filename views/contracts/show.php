<?php
/** @var array $c @var ?array $meter @var array $payments @var ?array $sum @var ?array $edit */
$d = $c['data'];
$pe = $edit ?? ['id' => 0, 'paid_on' => date('Y-m-d'), 'data' => ['amount' => $d['monthly_advance'], 'status' => 'paid', 'kind' => 'advance', 'note' => '']];
?>
<div class="pagehead">
    <h2><?= e($d['provider'] ?: 'Vertrag #' . $c['id']) ?></h2>
    <div class="actions"><a role="button" class="secondary outline" href="<?= e(url('contracts/' . $c['id'] . '/edit')) ?>">Bearbeiten</a></div>
</div>
<p class="muted small">
    Zähler: <?= $meter ? '<a href="' . e(url('meters/' . $meter['id'])) . '">' . e($meter['name']) . '</a>' : '?' ?>
    · <?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?>
    <?= $d['contract_no'] !== '' ? ' · Nr. ' . e($d['contract_no']) : '' ?>
</p>

<?php if ($sum) { require __DIR__ . '/_summary.php'; } ?>

<article>
    <header>Konditionen</header>
    <ul class="small">
        <li>Grundgebühr: <b><?= e(eur($d['base_fee'] !== null ? (float) $d['base_fee'] : null)) ?></b> / <?= $d['base_period'] === 'year' ? 'Jahr' : 'Monat' ?></li>
        <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT', 'feedin' => 'Einspeisevergütung'] as $k => $l): if ($d[$k] !== null): ?>
            <li><?= e($l) ?>: <b><?= e(nf((float) $d[$k], 2)) ?> ct/kWh</b></li>
        <?php endif; endforeach; ?>
        <?php if ($d['annual_kwh'] !== null): ?><li>Geschätzter Jahresverbrauch: <b><?= e(nf((float) $d['annual_kwh'], 0)) ?> kWh</b></li><?php endif; ?>
        <?php if ($d['monthly_advance'] !== null): ?><li>Monatlicher Abschlag: <b><?= e(eur((float) $d['monthly_advance'])) ?></b></li><?php endif; ?>
    </ul>
    <?php if ($d['note'] !== ''): ?><p class="small muted"><?= e($d['note']) ?></p><?php endif; ?>
</article>

<article>
    <header><?= $edit ? 'Zahlung bearbeiten' : 'Zahlung erfassen' ?></header>
    <form method="post" action="<?= e(url($edit ? 'payments/' . $edit['id'] : 'contracts/' . $c['id'] . '/payments')) ?>">
        <?= csrf_field() ?>
        <div class="grid">
            <label>Datum <input type="date" name="paid_on" value="<?= e($pe['paid_on']) ?>" required></label>
            <label>Betrag (€) <input name="amount" inputmode="decimal" value="<?= e($pe['data']['amount'] !== null ? number_format((float) $pe['data']['amount'], 2, ',', '') : '') ?>" required></label>
            <label>Art
                <select name="kind">
                    <option value="advance" <?= $pe['data']['kind'] === 'advance' ? 'selected' : '' ?>>Monatsabschlag</option>
                    <option value="special" <?= $pe['data']['kind'] === 'special' ? 'selected' : '' ?>>Sonderzahlung / Nachzahlung / Erstattung</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="paid" <?= $pe['data']['status'] === 'paid' ? 'selected' : '' ?>>Bezahlt</option>
                    <option value="open" <?= $pe['data']['status'] === 'open' ? 'selected' : '' ?>>Offen (nicht in Berechnung)</option>
                </select>
            </label>
        </div>
        <label>Notiz <input name="note" value="<?= e($pe['data']['note']) ?>" maxlength="200"></label>
        <div class="actions">
            <button type="submit"><?= $edit ? 'Aktualisieren' : 'Zahlung speichern' ?></button>
            <?php if ($edit): ?><a role="button" class="secondary outline" href="<?= e(url('contracts/' . $c['id'])) ?>">Abbrechen</a><?php endif; ?>
        </div>
    </form>
</article>

<article>
    <header>Zahlungen (<?= count($payments) ?>)</header>
    <?php if (!$payments): ?><p>Noch keine Zahlungen erfasst.</p><?php else: ?>
    <div class="tablewrap"><table class="striped">
        <thead><tr><th>Datum</th><th>Art</th><th>Status</th><th class="num">Betrag</th><th>Notiz</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(fmt_date($p['paid_on'])) ?></td>
                <td><?= $p['data']['kind'] === 'special' ? 'Sonderzahlung' : 'Abschlag' ?></td>
                <td><?= $p['data']['status'] === 'paid' ? 'Bezahlt' : '<span class="badge">offen</span>' ?></td>
                <td class="num"><?= e(eur((float) $p['data']['amount'])) ?></td>
                <td class="small muted"><?= e($p['data']['note']) ?></td>
                <td class="num">
                    <a href="<?= e(url('contracts/' . $c['id'] . '?edit=' . $p['id'])) ?>">Bearbeiten</a>
                    <form method="post" action="<?= e(url('payments/' . $p['id'] . '/delete')) ?>" class="inline" data-confirm="Zahlung löschen?"><?= csrf_field() ?><button class="linklike" type="submit">Löschen</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</article>

<details class="danger">
    <summary>Vertrag löschen</summary>
    <form method="post" action="<?= e(url('contracts/' . $c['id'] . '/delete')) ?>" data-confirm="Vertrag samt allen Zahlungen löschen?">
        <?= csrf_field() ?><button type="submit" class="contrast">Vertrag löschen</button>
    </form>
</details>
