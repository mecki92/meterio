<?php
/** @var array $meter @var ?array $reading @var ?array $prev @var array $form */
$isNew = !$reading;
$action = $isNew ? 'meters/' . $meter['id'] . '/readings' : 'readings/' . $reading['id'];
$maxFiles = (int) Config::get('uploads.max_files', 8);
$maxMb = (int) Config::get('uploads.max_size_mb', 12);
?>
<div class="page-head">
    <div><h1><?= $isNew ? 'Ablesung erfassen' : 'Ablesung bearbeiten' ?></h1>
        <p class="sub"><i class="bi bi-speedometer2"></i> <?= e($meter['name']) ?><?= $prev ? ' · letzte Ablesung ' . e(date('d.m.Y', $prev['ts'])) : '' ?></p></div>
</div>

<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-123"></i> Zählerstand</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6"><label class="form-label" for="read_at">Datum der Ablesung</label>
                            <input type="date" id="read_at" name="read_at" class="form-control" value="<?= e($form['read_at']) ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
                        <div class="col-sm-6"><label class="form-label" for="reason">Anlass</label>
                            <select id="reason" name="reason" class="form-select">
                                <?php foreach (reading_reasons() as $k => [$l]): ?><option value="<?= e($k) ?>" <?= ($form['reason'] ?? 'regular') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                            </select></div>
                    </div>
                    <?php foreach ($meter['registers'] as $r):
                        $val = $form['raw'][$r['id']] ?? (isset($form['values'][$r['id']]) ? nf((float) $form['values'][$r['id']], 0) : '');
                        $pv = $prev['values'][$r['id']] ?? null; ?>
                        <div class="mb-3"><label class="form-label"><?= e(Repo::registerTitle($r)) ?> <span class="text-body-secondary fw-normal">· <?= e(tariff_labels()[$r['tariff']]) ?></span></label>
                            <div class="input-group"><input type="text" inputmode="numeric" name="val[<?= (int) $r['id'] ?>]" class="form-control" value="<?= e($val) ?>" placeholder="z. B. 12.345" data-kwh autocomplete="off"><span class="input-group-text">kWh</span></div>
                            <?php if ($pv !== null): ?><div class="form-text">Letzter Stand (<?= e(date('d.m.Y', $prev['ts'])) ?>): <b><?= e(nf($pv, 0)) ?></b> kWh</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-3"><label class="form-label" for="note">Notiz <span class="text-body-secondary fw-normal">(optional)</span></label>
                        <input id="note" name="note" class="form-control" value="<?= e($form['note']) ?>" maxlength="1000" placeholder="z. B. neuer Zähler Nr. 12345, Startwert 0"></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="force" id="force" value="1" <?= !empty($form['force']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="force">Trotzdem speichern, auch wenn ein Wert unplausibel erscheint</label>
                        <div class="form-text">Bei Anlass „Zählerwechsel“ ist das nicht nötig.</div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-camera"></i> Fotos als Beleg</div>
                <div class="card-body">
                    <?php if (!$isNew && $reading['photos']): ?>
                        <div class="thumbs">
                        <?php foreach ($reading['photos'] as $i => $p): ?>
                            <figure>
                                <a href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('photos/' . $p['id'])) ?>" alt="Foto <?= $i + 1 ?>" loading="lazy"></a>
                                <button type="submit" class="btn btn-outline-danger btn-sm mt-1 w-100" formaction="<?= e(url('photos/' . $p['id'] . '/delete')) ?>" formnovalidate data-confirm="Foto löschen?"><i class="bi bi-trash"></i> Löschen</button>
                            </figure>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div id="photopick" data-max="<?= $maxFiles ?>" data-maxmb="<?= $maxMb ?>">
                        <div class="d-grid gap-2 d-sm-flex">
                            <button type="button" class="btn btn-primary flex-fill" id="btn-camera"><i class="bi bi-camera-fill"></i> Foto aufnehmen</button>
                            <button type="button" class="btn btn-outline-secondary flex-fill" id="btn-gallery"><i class="bi bi-images"></i> Aus Galerie / Datei</button>
                        </div>
                        <input type="file" id="in-camera" accept="image/*" capture="environment" hidden>
                        <input type="file" id="in-files" name="photos[]" accept="image/*" multiple hidden>
                        <div class="thumbs mt-3" id="newthumbs"></div>
                        <div class="form-text" id="photohint">Bis zu <?= $maxFiles ?> Fotos je Speichervorgang, <?= $maxMb ?> MB je Foto. Sie werden automatisch verkleinert und verschlüsselt abgelegt.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
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
