<?php
/** @var array $meter */
$isNew = !$meter['id'];
$analog = $meter['kind'] === 'analog';
$model = $meter['tariff_model'] ?? 'single';
$regs = $meter['registers'];
if ($analog) { $regs = [['id' => 0, 'obis' => '', 'label' => '', 'tariff' => 'ET']]; } // Platzhalter für das Digital-Panel
?>
<div class="page-head">
    <div>
        <h1><?= $isNew ? 'Neuer Zähler' : 'Zähler bearbeiten' ?></h1>
        <p class="sub"><?= $isNew ? 'Stammdaten und Zählwerke festlegen' : e($meter['name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<form method="post" action="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>" id="meterform">
    <?= csrf_field() ?>
<div class="row g-3 align-items-start">
<div class="col-lg-8">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-info-circle"></i> Stammdaten</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="m-name">Bezeichnung / Zählernummer</label>
                    <input id="m-name" name="name" class="form-control" value="<?= e($meter['name']) ?>" placeholder="z. B. Hauptzähler Keller" required maxlength="120"></div>
                <div class="col-md-6"><label class="form-label" for="m-loc">Standort</label>
                    <input id="m-loc" name="location" class="form-control" value="<?= e($meter['location']) ?>" placeholder="z. B. Keller, Wohnung EG" maxlength="160"></div>
                <div class="col-12"><label class="form-label" for="m-note">Notiz</label>
                    <textarea id="m-note" name="note" class="form-control" rows="2" maxlength="1000"><?= e($meter['note']) ?></textarea></div>
            </div>
        </div>
    </div>

    <!-- Analog: nur Eintarif oder HT/NT, keine OBIS-Kennzahlen -->
    <div class="card mb-3" id="analogbox" <?= $analog ? '' : 'hidden' ?>>
        <div class="card-header"><i class="bi bi-lightning-charge"></i> Tarif des analogen Zählers</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="radio" class="btn-check" name="tariff_model" id="tm1" value="single" <?= $model === 'single' ? 'checked' : '' ?> <?= $analog ? '' : 'disabled' ?>>
                    <label class="choice" for="tm1"><span class="icon-tile"><i class="bi bi-1-circle"></i></span><span><b>Eintarif</b><small>Ein Zählwerk</small></span></label>
                </div>
                <div class="col-md-6">
                    <input type="radio" class="btn-check" name="tariff_model" id="tm2" value="multi" <?= $model === 'multi' ? 'checked' : '' ?> <?= $analog ? '' : 'disabled' ?>>
                    <label class="choice" for="tm2"><span class="icon-tile"><i class="bi bi-2-circle"></i></span><span><b>Zweitarif HT/NT</b><small>Zwei Zählwerke: Hochtarif und Niedertarif</small></span></label>
                </div>
            </div>
        </div>
    </div>

    <!-- Digital: OBIS-Register, jede Kennzahl nur einmal -->
    <div class="card mb-3" id="digitalbox" <?= $analog ? 'hidden' : '' ?>>
        <div class="card-header split"><span><i class="bi bi-list-ol"></i> Register (OBIS-Kennzahlen)<?= info('Du bestimmst selbst, was jedes Register bedeutet. Die Zuordnung „Abrechnung“ steuert, mit welchem Preis der Vertrag das Register bewertet. Jede OBIS-Kennzahl darf nur einmal vorkommen.') ?></span>
            <span class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="single">Eintarif (1.8.0)</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="multi">HT/NT (1.8.1 + 1.8.2)</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="export">+ Einspeisung (2.8.0)</button>
            </span></div>
        <div class="card-body">
            <div class="regrow reghead" aria-hidden="true"><span>OBIS</span><span>Bezeichnung</span><span>Abrechnung</span><span></span></div>
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
            <?php if (!$isNew): ?><p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Wird ein Register entfernt, werden auch alle erfassten Werte dieses Registers gelöscht.</p><?php endif; ?>
        </div>
    </div>

</div>
<div class="col-lg-4"><div class="side-sticky">
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-speedometer2"></i> Art des Zählers</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <input type="radio" class="btn-check" name="kind" id="kind-digital" value="digital" <?= !$analog ? 'checked' : '' ?>>
                    <label class="choice" for="kind-digital"><span class="icon-tile"><i class="bi bi-cpu"></i></span><span><b>Digital / modern</b><small>Mit OBIS-Registern, z. B. 1.8.0 Bezug, 2.8.0 Einspeisung</small></span></label>
                </div>
                <div class="col-12">
                    <input type="radio" class="btn-check" name="kind" id="kind-analog" value="analog" <?= $analog ? 'checked' : '' ?>>
                    <label class="choice" for="kind-analog"><span class="icon-tile"><i class="bi bi-speedometer"></i></span><span><b>Analog (Ferraris)</b><small>Ohne OBIS – Eintarif oder Zweitarif HT/NT</small></span></label>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isNew): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="archived" id="arch" value="1" <?= $meter['archived'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="arch"><b>Zähler archivieren</b><?= info('Wird auf dem Dashboard ausgeblendet; Daten bleiben erhalten.') ?></label>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> Speichern</button>
        <a class="btn btn-outline-secondary" href="<?= e(url($isNew ? 'meters' : 'meters/' . $meter['id'])) ?>">Abbrechen</a>
    </div>
</div></div>
</div>
</form>
