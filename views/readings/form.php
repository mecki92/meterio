<?php
/** @var array $meter @var ?array $reading @var ?array $prev @var array $form */
$isNew = !$reading;
$action = $isNew ? 'meters/' . $meter['id'] . '/readings' : 'readings/' . $reading['id'];
?>
<h2><?= $isNew ? 'Ablesung erfassen' : 'Ablesung bearbeiten' ?> – <?= e($meter['name']) ?></h2>
<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <article>
        <label>Datum &amp; Uhrzeit der Ablesung
            <input type="datetime-local" name="read_at" value="<?= e($form['read_at']) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required>
        </label>

        <?php foreach ($meter['registers'] as $r):
            $val = $form['raw'][$r['id']] ?? (isset($form['values'][$r['id']]) ? (string) $form['values'][$r['id']] : '');
            $pv = $prev['values'][$r['id']] ?? null; ?>
            <label><?= e(Repo::registerTitle($r)) ?> <small class="muted">(<?= e(tariff_labels()[$r['tariff']]) ?>)</small>
                <input type="text" inputmode="decimal" name="val[<?= (int) $r['id'] ?>]" value="<?= e($val) ?>" placeholder="z. B. 12345,6" autocomplete="off">
                <?php if ($pv !== null): ?><small>Letzter Stand (<?= e(date('d.m.Y', $prev['ts'])) ?>): <b><?= e(nf($pv, 1)) ?></b> kWh</small><?php endif; ?>
            </label>
        <?php endforeach; ?>

        <label>Notiz (optional) <input name="note" value="<?= e($form['note']) ?>" maxlength="1000" placeholder="z. B. Zählerwechsel, Ablesung durch Netzbetreiber"></label>
        <label><input type="checkbox" name="force" value="1" <?= !empty($form['force']) ? 'checked' : '' ?>> Trotzdem speichern, auch wenn ein Wert unplausibel erscheint (Zählerwechsel/Überlauf)</label>
    </article>

    <article>
        <header>Fotos als Beleg</header>
        <?php if (!$isNew && $reading['photos']): ?>
            <div class="thumbs">
            <?php foreach ($reading['photos'] as $i => $p): ?>
                <figure>
                    <a href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('photos/' . $p['id'])) ?>" alt="Foto <?= $i + 1 ?>" loading="lazy"></a>
                    <figcaption>
                        <button type="submit" class="outline contrast small" formaction="<?= e(url('photos/' . $p['id'] . '/delete')) ?>" formnovalidate data-confirm="Foto löschen?">Löschen</button>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <label>Fotos hinzufügen (mehrere möglich; werden automatisch verkleinert &amp; komprimiert)
            <input type="file" name="photos[]" accept="image/*" multiple capture="environment">
        </label>
        <small>Max. <?= (int) Config::get('uploads.max_files', 8) ?> Fotos je Speichervorgang, <?= (int) Config::get('uploads.max_size_mb', 12) ?> MB je Foto. Fotos werden verschlüsselt abgelegt.</small>
    </article>

    <div class="actions">
        <button type="submit">Speichern</button>
        <a role="button" class="secondary outline" href="<?= e(url('meters/' . $meter['id'])) ?>">Abbrechen</a>
    </div>
</form>

<?php if (!$isNew): ?>
<details class="danger">
    <summary>Ablesung löschen</summary>
    <form method="post" action="<?= e(url('readings/' . $reading['id'] . '/delete')) ?>" data-confirm="Ablesung inkl. Fotos wirklich löschen?">
        <?= csrf_field() ?><button type="submit" class="contrast">Ablesung löschen</button>
    </form>
</details>
<?php endif; ?>
