<?php
/** @var array $meter @var ?array $reading @var ?array $prev @var array $form */
$isNew = !$reading;
$action = $isNew ? 'meters/' . $meter['id'] . '/readings' : 'readings/' . $reading['id'];
?>
<h2 class="h3 mb-3"><?= $isNew ? 'Ablesung erfassen' : 'Ablesung bearbeiten' ?> – <?= e($meter['name']) ?></h2>
<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" style="max-width:760px">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body">
        <div class="mb-3"><label class="form-label">Datum &amp; Uhrzeit der Ablesung</label>
            <input type="datetime-local" name="read_at" class="form-control" value="<?= e($form['read_at']) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required></div>
        <div class="mb-3"><label class="form-label">Anlass der Ablesung</label>
            <select name="reason" class="form-select">
                <?php foreach (reading_reasons() as $k => [$l]): ?><option value="<?= e($k) ?>" <?= ($form['reason'] ?? 'regular') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Bei „Zählerwechsel“ wird die Plausibilitätsprüfung der Zählerstände übersprungen.</div></div>
        <?php foreach ($meter['registers'] as $r):
            $val = $form['raw'][$r['id']] ?? (isset($form['values'][$r['id']]) ? nf((float) $form['values'][$r['id']], 0) : '');
            $pv = $prev['values'][$r['id']] ?? null; ?>
            <div class="mb-3"><label class="form-label"><?= e(Repo::registerTitle($r)) ?> <small class="text-body-secondary">(<?= e(tariff_labels()[$r['tariff']]) ?>)</small></label>
                <div class="input-group"><input type="text" inputmode="numeric" name="val[<?= (int) $r['id'] ?>]" class="form-control form-control-lg" value="<?= e($val) ?>" placeholder="z. B. 12.345" data-kwh autocomplete="off"><span class="input-group-text">kWh</span></div>
                <?php if ($pv !== null): ?><div class="form-text">Letzter Stand (<?= e(date('d.m.Y', $prev['ts'])) ?>): <b><?= e(nf($pv, 0)) ?></b> kWh</div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="mb-3"><label class="form-label">Notiz (optional)</label>
            <input name="note" class="form-control" value="<?= e($form['note']) ?>" maxlength="1000" placeholder="z. B. Neuer Zähler Nr. 12345, Startwert 0"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="force" id="force" value="1" <?= !empty($form['force']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="force">Trotzdem speichern, auch wenn ein Wert unplausibel erscheint (Zählerwechsel/Überlauf)</label></div>
    </div></div>

    <div class="card mb-3">
        <div class="card-header">Fotos als Beleg</div>
        <div class="card-body">
            <?php if (!$isNew && $reading['photos']): ?>
                <div class="thumbs">
                <?php foreach ($reading['photos'] as $i => $p): ?>
                    <figure>
                        <a href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('photos/' . $p['id'])) ?>" alt="Foto <?= $i + 1 ?>" loading="lazy"></a>
                        <button type="submit" class="btn btn-outline-danger btn-sm mt-1 w-100" formaction="<?= e(url('photos/' . $p['id'] . '/delete')) ?>" formnovalidate data-confirm="Foto löschen?">Löschen</button>
                    </figure>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="form-label">Fotos hinzufügen (mehrere möglich; werden automatisch verkleinert &amp; komprimiert)</label>
            <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
            <div class="form-text">Max. <?= (int) Config::get('uploads.max_files', 8) ?> Fotos je Speichervorgang, <?= (int) Config::get('uploads.max_size_mb', 12) ?> MB je Foto. Fotos werden verschlüsselt abgelegt.</div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'])) ?>">Abbrechen</a>
    </div>
</form>

<?php if (!$isNew): ?>
<details class="mt-4">
    <summary class="text-danger">Ablesung löschen</summary>
    <form method="post" action="<?= e(url('readings/' . $reading['id'] . '/delete')) ?>" class="mt-2" data-confirm="Ablesung inkl. Fotos wirklich löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Ablesung löschen</button>
    </form>
</details>
<?php endif; ?>
