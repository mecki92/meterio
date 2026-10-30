<?php /** @var array $meter */ $isNew = !$meter['id']; ?>
<h2><?= $isNew ? 'Neuer Zähler' : 'Zähler bearbeiten' ?></h2>
<form method="post" action="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>">
    <?= csrf_field() ?>
    <article>
        <header>Stammdaten</header>
        <label>Bezeichnung <input name="name" value="<?= e($meter['name']) ?>" placeholder="z. B. Hauptzähler Keller" required maxlength="120"></label>
        <div class="grid">
            <label>Standort <input name="location" value="<?= e($meter['location']) ?>" placeholder="z. B. Keller, Wohnung EG" maxlength="160"></label>
            <label>Art
                <select name="kind">
                    <option value="digital" <?= $meter['kind'] === 'digital' ? 'selected' : '' ?>>Digital / modern (mME, eHZ)</option>
                    <option value="analog" <?= $meter['kind'] === 'analog' ? 'selected' : '' ?>>Analog (Ferraris)</option>
                </select>
            </label>
        </div>
        <label>Notiz <textarea name="note" rows="2" maxlength="1000"><?= e($meter['note']) ?></textarea></label>
        <?php if (!$isNew): ?><label><input type="checkbox" name="archived" value="1" <?= $meter['archived'] ? 'checked' : '' ?>> Zähler archivieren (aus Dashboard ausblenden)</label><?php endif; ?>
    </article>

    <article>
        <header>Register (OBIS-Kennzahlen)</header>
        <p class="small muted">Du bestimmst selbst, was jedes Register bedeutet. Die Zuordnung „Abrechnung“ steuert, mit welchem Preis der Vertrag das Register bewertet.</p>
        <div class="presets">
            <button type="button" class="secondary outline small" data-preset="single">Eintarif (1.8.0)</button>
            <button type="button" class="secondary outline small" data-preset="multi">HT/NT (1.8.1 + 1.8.2)</button>
            <button type="button" class="secondary outline small" data-preset="export">+ Einspeisung (2.8.0)</button>
        </div>
        <div id="regs" data-tariffs="<?= e(json_encode(tariff_labels(), JSON_UNESCAPED_UNICODE)) ?>">
            <?php foreach ($meter['registers'] as $i => $r): ?>
                <div class="regrow">
                    <input type="hidden" name="reg[<?= $i ?>][id]" value="<?= (int) $r['id'] ?>">
                    <input name="reg[<?= $i ?>][obis]" value="<?= e($r['obis']) ?>" placeholder="OBIS z. B. 1.8.1" aria-label="OBIS-Kennzahl">
                    <input name="reg[<?= $i ?>][label]" value="<?= e($r['label']) ?>" placeholder="Bezeichnung z. B. Hochtarif" aria-label="Bezeichnung">
                    <select name="reg[<?= $i ?>][tariff]" aria-label="Abrechnung">
                        <?php foreach (tariff_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $r['tariff'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="secondary outline small" data-remove-row title="Register entfernen">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="secondary outline" id="addreg">+ Register hinzufügen</button>
        <?php if (!$isNew): ?><p class="small muted">Achtung: Wird ein Register entfernt, werden auch alle erfassten Werte dieses Registers gelöscht.</p><?php endif; ?>
    </article>

    <div class="actions">
        <button type="submit">Speichern</button>
        <a role="button" class="secondary outline" href="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>">Abbrechen</a>
    </div>
</form>
