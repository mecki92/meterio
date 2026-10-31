<?php
/** @var array $c @var array $meters */
$d = $c['data'];
$isNew = !$c['id'];
$n = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');
?>
<h2 class="h3 mb-3"><?= $isNew ? 'Neuer Stromvertrag' : 'Vertrag bearbeiten' ?></h2>
<form method="post" action="<?= e(url($isNew ? 'contracts' : 'contracts/' . $c['id'])) ?>" style="max-width:860px">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-header">Vertrag &amp; Laufzeit</div><div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">Anbieter</label><input name="provider" class="form-control" value="<?= e($d['provider']) ?>" maxlength="120"></div>
            <div class="col-md-6"><label class="form-label">Vertragsnummer</label><input name="contract_no" class="form-control" value="<?= e($d['contract_no']) ?>" maxlength="60"></div>
        </div>
        <div class="mb-3"><label class="form-label">Zähler</label>
            <select name="meter_id" class="form-select" id="contract_meter" required>
                <?php foreach ($meters as $m): ?><option value="<?= (int) $m['id'] ?>" data-tariffs="<?= e(implode(',', array_unique(array_column($m['registers'], 'tariff')))) ?>" <?= $c['meter_id'] === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Vertragsbeginn</label><input type="date" name="start_date" class="form-control" value="<?= e($c['start_date']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Vertragsende (leer = unbefristet)</label><input type="date" name="end_date" class="form-control" value="<?= e($c['end_date'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Mindestvertragslaufzeit</label>
                <div class="input-group"><input name="min_term_months" class="form-control" inputmode="numeric" value="<?= e($d['min_term_months'] !== null ? (string) $d['min_term_months'] : '') ?>" placeholder="z. B. 12"><span class="input-group-text">Monate</span></div>
                <div class="form-text">Leer = keine Mindestlaufzeit. Abgerechnet wird jeweils pro Vertragsjahr (Jahresabrechnung).</div></div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-header">Preise (brutto, inkl. MwSt.)</div><div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-8 col-md-6"><label class="form-label">Grundgebühr</label>
                <div class="input-group"><input name="base_fee" class="form-control" inputmode="decimal" value="<?= e($n($d['base_fee'])) ?>" placeholder="z. B. 12,50"><span class="input-group-text">€</span></div></div>
            <div class="col-4 col-md-6"><label class="form-label">pro</label>
                <select name="base_period" class="form-select">
                    <option value="month" <?= $d['base_period'] === 'month' ? 'selected' : '' ?>>Monat</option>
                    <option value="year" <?= $d['base_period'] === 'year' ? 'selected' : '' ?>>Jahr</option>
                </select></div>
        </div>
        <div class="row g-3 mb-3">
            <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT'] as $k => $l): ?>
                <div class="col-md-4" data-price="<?= e($k) ?>"><label class="form-label"><?= e($l) ?></label>
                    <div class="input-group"><input name="<?= $k ?>" class="form-control" inputmode="decimal" value="<?= e($n($d[$k])) ?>" placeholder="z. B. 32,90"><span class="input-group-text">ct/kWh</span></div></div>
            <?php endforeach; ?>
        </div>
        <div class="mb-2" data-price="feedin"><label class="form-label">Einspeisevergütung (optional)</label>
            <div class="input-group" style="max-width:320px"><input name="feedin" class="form-control" inputmode="decimal" value="<?= e($n($d['feedin'])) ?>"><span class="input-group-text">ct/kWh</span></div></div>
        <div class="form-text" id="pricehint">Angezeigt werden nur die Preise, die zum gewählten Zähler passen.</div>
    </div></div>

    <div class="card mb-3"><div class="card-header">Einmalige Gutschriften (z. B. Neukundenbonus)</div><div class="card-body">
        <p class="small text-body-secondary">Wird die Gutschrift erst nach einer bestimmten Zeit gewährt (z. B. nach 12 Monaten), trage die Monate ab Vertragsbeginn ein. Sie mindert die Kosten der Jahresabrechnung, in der sie fällig wird; die Prognose berücksichtigt sie bereits.</p>
        <div id="credits">
        <?php
        $rows = $d['credits'] ?: [];
        $rows[] = ['months' => '', 'amount' => '', 'label' => ''];
        foreach ($rows as $i => $cr): ?>
            <div class="row g-2 mb-2 creditrow">
                <div class="col-5 col-md-3"><div class="input-group"><span class="input-group-text">nach</span><input name="credit[<?= $i ?>][months]" class="form-control" inputmode="numeric" placeholder="12" aria-label="Monate" value="<?= e(isset($cr['months']) && $cr['months'] !== '' ? (string) (int) $cr['months'] : '') ?>"><span class="input-group-text">Mon.</span></div></div>
                <div class="col-7 col-md-3"><div class="input-group"><input name="credit[<?= $i ?>][amount]" class="form-control" inputmode="decimal" placeholder="Betrag" aria-label="Betrag" value="<?= e($cr['amount'] !== '' ? $n($cr['amount']) : '') ?>"><span class="input-group-text">€</span></div></div>
                <div class="col-12 col-md-6"><input name="credit[<?= $i ?>][label]" class="form-control" maxlength="80" placeholder="Bezeichnung (z. B. Neukundenbonus)" aria-label="Bezeichnung" value="<?= e($cr['label']) ?>"></div>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="addcredit"><i class="bi bi-plus-lg"></i> Weitere Gutschrift</button>
    </div></div>

    <div class="card mb-3"><div class="card-header">Verbrauch &amp; Abschläge</div><div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">Geschätzter Jahresverbrauch laut Vertrag</label>
                <div class="input-group"><input name="annual_kwh" class="form-control" inputmode="numeric" data-kwh placeholder="z. B. 2.500" value="<?= e($d['annual_kwh'] !== null ? nf((float) $d['annual_kwh'], 0) : '') ?>"><span class="input-group-text">kWh</span></div></div>
            <div class="col-md-6"><label class="form-label">Monatlicher Abschlag</label>
                <div class="input-group"><input name="monthly_advance" class="form-control" inputmode="decimal" value="<?= e($n($d['monthly_advance'])) ?>"><span class="input-group-text">€</span></div></div>
        </div>
        <label class="form-label">Notiz</label><textarea name="note" class="form-control" rows="2" maxlength="1000"><?= e($d['note']) ?></textarea>
    </div></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a class="btn btn-outline-secondary" href="<?= e(url($isNew ? 'contracts' : 'contracts/' . $c['id'])) ?>">Abbrechen</a>
    </div>
</form>
