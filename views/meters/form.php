<?php
/** @var array $meter */
$isNew = !$meter['id'];
$analog = $meter['kind'] === 'analog';
$model = $meter['tariff_model'] ?? 'single';
$regs = $meter['registers'];
if ($analog) { $regs = [['id' => 0, 'obis' => '', 'label' => '', 'tariff' => 'ET']]; } // Platzhalter für das Digital-Panel
?>
<h2 class="h3 mb-3"><?= $isNew ? 'Neuer Zähler' : 'Zähler bearbeiten' ?></h2>
<form method="post" action="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>" id="meterform" style="max-width:860px">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-header">Stammdaten</div>
        <div class="card-body">
            <div class="mb-3"><label class="form-label">Bezeichnung</label>
                <input name="name" class="form-control" value="<?= e($meter['name']) ?>" placeholder="z. B. Hauptzähler Keller" required maxlength="120"></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Standort</label>
                    <input name="location" class="form-control" value="<?= e($meter['location']) ?>" placeholder="z. B. Keller, Wohnung EG" maxlength="160"></div>
                <div class="col-md-6"><label class="form-label">Art des Zählers</label>
                    <select name="kind" id="kind" class="form-select">
                        <option value="digital" <?= !$analog ? 'selected' : '' ?>>Digital / modern (mit OBIS-Registern)</option>
                        <option value="analog" <?= $analog ? 'selected' : '' ?>>Analog (Ferraris)</option>
                    </select></div>
            </div>
            <div class="mb-3"><label class="form-label">Notiz</label><textarea name="note" class="form-control" rows="2" maxlength="1000"><?= e($meter['note']) ?></textarea></div>
            <?php if (!$isNew): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="archived" id="arch" value="1" <?= $meter['archived'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="arch">Zähler archivieren (aus Dashboard ausblenden)</label></div><?php endif; ?>
        </div>
    </div>

    <!-- Analog: nur Eintarif oder HT/NT, keine OBIS-Kennzahlen -->
    <div class="card mb-3" id="analogbox" <?= $analog ? '' : 'hidden' ?>>
        <div class="card-header">Tarif des analogen Zählers</div>
        <div class="card-body">
            <div class="form-check mb-2"><input class="form-check-input" type="radio" name="tariff_model" id="tm1" value="single" <?= $model === 'single' ? 'checked' : '' ?> <?= $analog ? '' : 'disabled' ?>>
                <label class="form-check-label" for="tm1"><b>Eintarif</b> – ein Zählwerk</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="tariff_model" id="tm2" value="multi" <?= $model === 'multi' ? 'checked' : '' ?> <?= $analog ? '' : 'disabled' ?>>
                <label class="form-check-label" for="tm2"><b>Zweitarif HT/NT</b> – zwei Zählwerke (Hochtarif und Niedertarif)</label></div>
            <p class="small text-body-secondary mt-3 mb-0">Bei analogen Zählern werden keine OBIS-Kennzahlen benötigt.</p>
        </div>
    </div>

    <!-- Digital: OBIS-Register, jede Kennzahl nur einmal -->
    <div class="card mb-3" id="digitalbox" <?= $analog ? 'hidden' : '' ?>>
        <div class="card-header">Register (OBIS-Kennzahlen)</div>
        <div class="card-body">
            <p class="small text-body-secondary">Du bestimmst selbst, was jedes Register bedeutet. Die Zuordnung „Abrechnung“ steuert, mit welchem Preis der Vertrag das Register bewertet. <b>Jede OBIS-Kennzahl darf nur einmal vorkommen.</b></p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="single">Eintarif (1.8.0)</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="multi">HT/NT (1.8.1 + 1.8.2)</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="export">+ Einspeisung (2.8.0)</button>
            </div>
            <div id="regs" data-tariffs="<?= e(json_encode(tariff_labels(), JSON_UNESCAPED_UNICODE)) ?>" <?= $analog ? 'data-disabled="1"' : '' ?>>
                <?php foreach ($regs as $i => $r): ?>
                    <div class="regrow">
                        <input type="hidden" name="reg[<?= $i ?>][id]" value="<?= (int) $r['id'] ?>">
                        <input name="reg[<?= $i ?>][obis]" class="form-control" value="<?= e($r['obis']) ?>" placeholder="OBIS z. B. 1.8.1" aria-label="OBIS-Kennzahl" <?= $analog ? 'disabled' : '' ?>>
                        <input name="reg[<?= $i ?>][label]" class="form-control" value="<?= e($r['label']) ?>" placeholder="Bezeichnung z. B. Hochtarif" aria-label="Bezeichnung" <?= $analog ? 'disabled' : '' ?>>
                        <select name="reg[<?= $i ?>][tariff]" class="form-select" aria-label="Abrechnung" <?= $analog ? 'disabled' : '' ?>>
                            <?php foreach (tariff_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $r['tariff'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-danger" data-remove-row title="Register entfernen"><i class="bi bi-x-lg"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-danger small mb-2" id="obiserr" hidden></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addreg"><i class="bi bi-plus-lg"></i> Register hinzufügen</button>
            <?php if (!$isNew): ?><p class="small text-body-secondary mt-3 mb-0">Achtung: Wird ein Register entfernt, werden auch alle erfassten Werte dieses Registers gelöscht.</p><?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a class="btn btn-outline-secondary" href="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>">Abbrechen</a>
    </div>
</form>
