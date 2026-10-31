<?php
/** @var array $c @var array $meters */
$d = $c['data'];
$isNew = !$c['id'];
// Euro-Beträge: immer mind. 2 Nachkommastellen (12,50 / 63,00), bei Bedarf mehr
$eu = static function ($v) {
    if ($v === null || $v === '') { return ''; }
    $t = rtrim(number_format((float) $v, 6, ',', ''), '0');
    $dec = strlen(substr($t, strpos($t, ',') + 1));
    return $dec < 2 ? number_format((float) $v, 2, ',', '') : $t;
};
$n = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 6, ',', ''), '0'), ',');
$back = url($isNew ? 'contracts' : 'contracts/' . $c['id']);
?>
<div class="page-head">
    <div>
        <h1><?= $isNew ? 'Neuer Stromvertrag' : 'Vertrag bearbeiten' ?></h1>
        <p class="sub"><?= $isNew ? 'Laufzeit, Preise und Abschläge festlegen' : e(($d['provider'] !== '' ? $d['provider'] : 'Vertrag') . ($d['contract_no'] !== '' ? ' · Nr. ' . $d['contract_no'] : '')) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e($back) ?>"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<form method="post" action="<?= e(url($isNew ? 'contracts' : 'contracts/' . $c['id'])) ?>">
    <?= csrf_field() ?>
<div class="row g-3 align-items-start">
<div class="col-lg-8">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-file-earmark-text"></i> Vertrag &amp; Laufzeit</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="c-prov">Anbieter / Tarif</label><input id="c-prov" name="provider" class="form-control" value="<?= e($d['provider']) ?>" maxlength="120" placeholder="z. B. E.ON ÖkoStrom Extra 12"></div>
                <div class="col-md-6"><label class="form-label" for="c-no">Vertragsnummer</label><input id="c-no" name="contract_no" class="form-control" value="<?= e($d['contract_no']) ?>" maxlength="60"></div>
                <div class="col-12"><label class="form-label" for="contract_meter">Zähler</label>
                    <select name="meter_id" class="form-select" id="contract_meter" required>
                        <?php foreach ($meters as $m): ?><option value="<?= (int) $m['id'] ?>" data-tariffs="<?= e(implode(',', array_unique(array_column($m['registers'], 'tariff')))) ?>" <?= $c['meter_id'] === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label" for="c-start">Vertragsbeginn</label><input id="c-start" type="date" name="start_date" class="form-control" value="<?= e($c['start_date']) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="c-end">Vertragsende<?= info('Leer = unbefristet') ?></label><input id="c-end" type="date" name="end_date" class="form-control" value="<?= e($c['end_date'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="c-min">Mindestlaufzeit<?= info('Leer = keine Mindestlaufzeit, der Vertrag ist jederzeit bzw. monatlich kündbar.') ?></label>
                    <div class="input-group"><input id="c-min" name="min_term_months" class="form-control" inputmode="numeric" value="<?= e($d['min_term_months'] !== null ? (string) $d['min_term_months'] : '') ?>" placeholder="12"><span class="input-group-text">Monate</span></div></div>
                <div class="col-md-4"><label class="form-label" for="c-bill">Abrechnungszeitraum<?= info('Üblich sind 12 Monate (Jahresabrechnung). Bei monatlich kündbaren Verträgen kann unterjährig abgerechnet werden, z. B. alle 3 Monate oder monatlich. Mit dem Vertragsende wird immer die Schlussrechnung erstellt.') ?></label>
                    <select id="c-bill" name="billing_months" class="form-select">
                        <?php foreach ([12 => '12 Monate (jährlich)', 6 => '6 Monate', 3 => '3 Monate (quartalsweise)', 1 => '1 Monat (monatlich)'] as $bm => $bl): ?>
                            <option value="<?= $bm ?>" <?= (int) $d['billing_months'] === $bm ? 'selected' : '' ?>><?= e($bl) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label" for="c-fb">Erste Abrechnung am<?= info('Optional: Abrechnungsstichtag laut Netzbetreiber bzw. Anbieter (letzter Tag des ersten Zeitraums). Der erste Zeitraum geht dann von Vertragsbeginn bis zu diesem Tag, danach wird jeweils im gewählten Abrechnungszeitraum abgerechnet, bis der Vertrag endet. Leer = Abrechnung ab Vertragsbeginn.') ?></label>
                    <input id="c-fb" type="date" name="first_billing" class="form-control" value="<?= e($d['first_billing'] ?? '') ?>"></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-currency-euro"></i> Preise<?= info('Angezeigt werden nur die Preise, die zum gewählten Zähler passen.', 'pricehint') ?></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-7 col-md-6"><label class="form-label" for="c-base">Grundgebühr</label>
                    <div class="input-group"><input id="c-base" name="base_fee" class="form-control" inputmode="decimal" value="<?= e($eu($d['base_fee'])) ?>" placeholder="z. B. 12,50"><span class="input-group-text">€ pro</span>
                        <select name="base_period" class="form-select" style="max-width:7rem" aria-label="Zeitraum">
                            <option value="month" <?= $d['base_period'] === 'month' ? 'selected' : '' ?>>Monat</option>
                            <option value="year" <?= $d['base_period'] === 'year' ? 'selected' : '' ?>>Jahr</option>
                        </select></div></div>
                <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT'] as $k => $l): ?>
                    <div class="col-sm-5 col-md-6" data-price="<?= e($k) ?>"><label class="form-label"><?= e($l) ?></label>
                        <div class="input-group"><input name="<?= $k ?>" class="form-control" inputmode="decimal" value="<?= e($eu($d[$k])) ?>" placeholder="z. B. 32,90"><span class="input-group-text">ct/kWh</span></div></div>
                <?php endforeach; ?>
                <div class="col-sm-5 col-md-6" data-price="feedin"><label class="form-label">Einspeisevergütung <span class="text-body-secondary fw-normal">(optional)</span></label>
                    <div class="input-group"><input name="feedin" class="form-control" inputmode="decimal" value="<?= e($eu($d['feedin'])) ?>"><span class="input-group-text">ct/kWh</span></div></div>
            </div>
        </div>
    </div>

    <?php
    $pcRow = static function ($i, array $pc) use ($eu): string {
        ob_start(); ?>
        <div class="pcrow">
            <div class="pc-head">
                <div class="pc-from"><label class="form-label">Gültig ab</label><input type="date" name="pc[<?= $i ?>][from]" class="form-control" value="<?= e($pc['from'] ?? '') ?>"></div>
                <div class="pc-label"><label class="form-label">Bezeichnung <span class="text-body-secondary fw-normal">(optional)</span></label><input name="pc[<?= $i ?>][label]" class="form-control" maxlength="80" placeholder="z. B. Preiserhöhung, Preisanpassung" value="<?= e($pc['label'] ?? '') ?>"></div>
                <button type="button" class="btn btn-outline-danger pc-remove" data-pc-remove title="Preisänderung entfernen"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="row g-2">
                <div class="col-sm-6 col-xl-4"><label class="form-label small">Grundgebühr</label>
                    <div class="input-group"><input name="pc[<?= $i ?>][base_fee]" class="form-control" inputmode="decimal" placeholder="bisher" value="<?= e($eu($pc['base_fee'] ?? null)) ?>"><span class="input-group-text">€ pro</span>
                        <select name="pc[<?= $i ?>][base_period]" class="form-select" style="max-width:6.5rem" aria-label="Zeitraum"><option value="month" <?= ($pc['base_period'] ?? 'month') === 'month' ? 'selected' : '' ?>>Monat</option><option value="year" <?= ($pc['base_period'] ?? '') === 'year' ? 'selected' : '' ?>>Jahr</option></select></div></div>
                <?php foreach (['price_et' => 'Arbeitspreis Eintarif', 'price_ht' => 'Arbeitspreis HT', 'price_nt' => 'Arbeitspreis NT', 'feedin' => 'Einspeisevergütung'] as $k => $l): ?>
                    <div class="col-sm-6 col-xl-4" data-price="<?= e($k) ?>"><label class="form-label small"><?= e($l) ?></label>
                        <div class="input-group"><input name="pc[<?= $i ?>][<?= $k ?>]" class="form-control" inputmode="decimal" placeholder="bisher" value="<?= e($eu($pc[$k] ?? null)) ?>"><span class="input-group-text">ct/kWh</span></div></div>
                <?php endforeach; ?>
                <div class="col-sm-6 col-xl-4"><label class="form-label small">Monatlicher Abschlag</label>
                    <div class="input-group"><input name="pc[<?= $i ?>][monthly_advance]" class="form-control" inputmode="decimal" placeholder="bisher" value="<?= e($eu($pc['monthly_advance'] ?? null)) ?>"><span class="input-group-text">€</span></div></div>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    };
    ?>
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-graph-up-arrow"></i> Preisänderungen im Vertragsverlauf<?= info('Ändert der Anbieter während der Laufzeit Preise, Grundgebühr oder Abschlag, lege hier die neuen Werte mit dem Tag an, ab dem sie gelten. Leere Felder gelten „wie bisher“. Die Abrechnung teilt den Zeitraum dann automatisch am Stichtag.') ?></div>
        <div class="card-body">
            <div id="pcrows">
                <?php foreach ($d['price_changes'] as $i => $pc): echo $pcRow($i, $pc); endforeach; ?>
            </div>
            <template id="pc-tpl"><?= $pcRow('__I__', []) ?></template>
            <p class="small text-body-secondary mb-2" id="pc-empty" <?= $d['price_changes'] ? 'hidden' : '' ?>>Keine Preisänderungen eingetragen – es gelten die oben genannten Preise für die gesamte Laufzeit.</p>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addpc"><i class="bi bi-plus-lg"></i> Preisänderung hinzufügen</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-gift"></i> Bonuszahlungen<?= info('Z. B. Neukundenbonus. Wird der Bonus erst nach einer bestimmten Zeit gewährt, trage die Monate ab Vertragsbeginn ein. Er erscheint als Bonuszahlung in den Zahlungen, gebucht zum Ende des Vertragsjahres, in dem er fällig wird. Endet der Vertrag vorher, entfällt er.') ?></div>
        <div class="card-body">
        <div id="credits">
        <?php
        $rows = $d['credits'] ?: [];
        if (!$rows) { $rows[] = ['months' => '', 'amount' => '', 'label' => '']; }   // weitere Zeilen per „+“
        foreach ($rows as $i => $cr): ?>
            <div class="row g-2 mb-2 creditrow">
                <div class="col-5 col-md-3"><div class="input-group"><span class="input-group-text">nach</span><input name="credit[<?= $i ?>][months]" class="form-control" inputmode="numeric" placeholder="12" aria-label="Monate" value="<?= e(isset($cr['months']) && $cr['months'] !== '' ? (string) (int) $cr['months'] : '') ?>"><span class="input-group-text">Mon.</span></div></div>
                <div class="col-7 col-md-3"><div class="input-group"><input name="credit[<?= $i ?>][amount]" class="form-control" inputmode="decimal" placeholder="Betrag" aria-label="Betrag" value="<?= e($cr['amount'] !== '' ? $eu($cr['amount']) : '') ?>"><span class="input-group-text">€</span></div></div>
                <div class="col-12 col-md-6"><input name="credit[<?= $i ?>][label]" class="form-control" maxlength="80" placeholder="Bezeichnung (z. B. Neukundenbonus)" aria-label="Bezeichnung" value="<?= e($cr['label']) ?>"></div>
            </div>
        <?php endforeach; ?>
        </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addcredit"><i class="bi bi-plus-lg"></i> Weiteren Bonus hinzufügen</button>
        </div>
    </div>

</div>
<div class="col-lg-4"><div class="side-sticky">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-percent"></i> Preisbasis &amp; Steuer</div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <input type="radio" class="btn-check" name="price_basis" id="pb-gross" value="gross" <?= $d['price_basis'] !== 'net' ? 'checked' : '' ?>>
                    <label class="choice" for="pb-gross"><span><b>Brutto</b><small>inkl. USt</small></span></label>
                </div>
                <div class="col-6">
                    <input type="radio" class="btn-check" name="price_basis" id="pb-net" value="net" <?= $d['price_basis'] === 'net' ? 'checked' : '' ?>>
                    <label class="choice" for="pb-net"><span><b>Netto</b><small>zzgl. USt</small></span></label>
                </div>
            </div>
            <label class="form-label" for="c-vat">Umsatzsteuer<?= info('Der Satz gilt fest für diesen Vertrag und ändert sich nicht rückwirkend.') ?></label>
            <select name="vat_rate" id="c-vat" class="form-select">
                <?php foreach (['19' => '19 %', '7' => '7 %', '0' => '0 %'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= (string) (int) $d['vat_rate'] === (string) $v ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-lightning-charge"></i> Verbrauch &amp; Abschläge</div>
        <div class="card-body">
            <div class="mb-3"><label class="form-label" for="c-kwh">Jahresverbrauch laut Vertrag</label>
                <div class="input-group"><input id="c-kwh" name="annual_kwh" class="form-control" inputmode="numeric" data-kwh placeholder="z. B. 2.500" value="<?= e($d['annual_kwh'] !== null ? nf((float) $d['annual_kwh'], 0) : '') ?>"><span class="input-group-text">kWh</span></div></div>
            <div><label class="form-label" for="c-adv">Monatlicher Abschlag</label>
                <div class="input-group"><input id="c-adv" name="monthly_advance" class="form-control" inputmode="decimal" value="<?= e($eu($d['monthly_advance'])) ?>"><span class="input-group-text">€</span></div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-sticky"></i> Notiz</div>
        <div class="card-body"><textarea name="note" class="form-control" rows="3" maxlength="1000" aria-label="Notiz"><?= e($d['note']) ?></textarea></div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> Speichern</button>
        <a class="btn btn-outline-secondary" href="<?= e($back) ?>">Abbrechen</a>
    </div>
</div></div>
</div>
</form>
