<?php
/** @var array $c @var array $meters */
$d = $c['data'];
$isNew = !$c['id'];
$n = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');
?>
<h2><?= $isNew ? 'Neuer Stromvertrag' : 'Vertrag bearbeiten' ?></h2>
<form method="post" action="<?= e(url($isNew ? 'contracts' : 'contracts/' . $c['id'])) ?>">
    <?= csrf_field() ?>
    <article>
        <header>Vertrag &amp; Laufzeit</header>
        <div class="grid">
            <label>Anbieter <input name="provider" value="<?= e($d['provider']) ?>" maxlength="120"></label>
            <label>Vertragsnummer <input name="contract_no" value="<?= e($d['contract_no']) ?>" maxlength="60"></label>
        </div>
        <label>Zähler
            <select name="meter_id" required>
                <?php foreach ($meters as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $c['meter_id'] === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <div class="grid">
            <label>Vertragsbeginn <input type="date" name="start_date" value="<?= e($c['start_date']) ?>" required></label>
            <label>Vertragsende (leer = unbefristet) <input type="date" name="end_date" value="<?= e($c['end_date'] ?? '') ?>"></label>
        </div>
    </article>

    <article>
        <header>Preise (brutto, inkl. MwSt.)</header>
        <div class="grid">
            <label>Grundgebühr (€) <input name="base_fee" inputmode="decimal" value="<?= e($n($d['base_fee'])) ?>" placeholder="z. B. 12,50"></label>
            <label>pro
                <select name="base_period">
                    <option value="month" <?= $d['base_period'] === 'month' ? 'selected' : '' ?>>Monat</option>
                    <option value="year" <?= $d['base_period'] === 'year' ? 'selected' : '' ?>>Jahr</option>
                </select>
            </label>
        </div>
        <div class="grid">
            <label>Arbeitspreis Eintarif (ct/kWh) <input name="price_et" inputmode="decimal" value="<?= e($n($d['price_et'])) ?>" placeholder="z. B. 32,90"></label>
            <label>Arbeitspreis HT (ct/kWh) <input name="price_ht" inputmode="decimal" value="<?= e($n($d['price_ht'])) ?>"></label>
            <label>Arbeitspreis NT (ct/kWh) <input name="price_nt" inputmode="decimal" value="<?= e($n($d['price_nt'])) ?>"></label>
        </div>
        <label>Einspeisevergütung (ct/kWh, optional) <input name="feedin" inputmode="decimal" value="<?= e($n($d['feedin'])) ?>"></label>
        <small>Nur Register mit passender Abrechnungs-Zuordnung (Eintarif / HT / NT / Einspeisung) werden bewertet.</small>
    </article>

    <article>
        <header>Verbrauch &amp; Abschläge</header>
        <div class="grid">
            <label>Geschätzter Jahresverbrauch laut Vertrag (kWh) <input name="annual_kwh" inputmode="decimal" value="<?= e($n($d['annual_kwh'])) ?>"></label>
            <label>Monatlicher Abschlag (€) <input name="monthly_advance" inputmode="decimal" value="<?= e($n($d['monthly_advance'])) ?>"></label>
        </div>
        <label>Notiz <textarea name="note" rows="2" maxlength="1000"><?= e($d['note']) ?></textarea></label>
    </article>

    <div class="actions">
        <button type="submit">Speichern</button>
        <a role="button" class="secondary outline" href="<?= e(url($isNew ? 'contracts' : 'contracts/' . $c['id'])) ?>">Abbrechen</a>
    </div>
</form>
