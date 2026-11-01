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

<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" id="readingform"
      data-check="<?= e(url('meters/' . $meter['id'] . '/readings/check')) ?>" data-ignore="<?= $isNew ? 0 : (int) $reading['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="force" id="force" value="<?= !empty($form['force']) ? '1' : '' ?>">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-speedometer2"></i> Zählerstand</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6"><label class="form-label" for="read_at">Datum der Ablesung</label>
                            <div class="input-group">
                                <input type="text" id="read_at" name="read_at" class="form-control" value="<?= e(($iso = parse_date_flex((string) $form['read_at'])) ? fmt_date($iso) : $form['read_at']) ?>" placeholder="TT.MM.JJJJ" inputmode="numeric" autocomplete="off" required>
                                <button type="button" class="btn btn-outline-secondary" id="read_at_pick" aria-label="Kalender öffnen" title="Kalender"><i class="bi bi-calendar3"></i></button>
                                <input type="date" id="read_at_cal" tabindex="-1" aria-hidden="true" class="position-absolute opacity-0 pe-none" style="width:1px;height:1px" max="<?= e(date('Y-m-d')) ?>" value="<?= e($iso ?? '') ?>">
                            </div></div>
                        <div class="col-sm-6"><label class="form-label" for="reason">Anlass</label>
                            <select id="reason" name="reason" class="form-select">
                                <?php foreach (reading_reasons_selectable($form['reason'] ?? null) as $k => [$l]): ?><option value="<?= e($k) ?>" <?= ($form['reason'] ?? 'a4') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                            </select></div>
                    </div>
                    <?php foreach ($meter['registers'] as $r):
                        $val = $form['raw'][$r['id']] ?? (isset($form['values'][$r['id']]) ? nf((float) $form['values'][$r['id']], 0) : '');
                        $pv = $prev['values'][$r['id']] ?? null; ?>
                        <div class="mb-3"><label class="form-label"><?= e(Repo::registerTitle($r)) ?><?php if (($ts = tariff_suffix($r)) !== ''): ?> <span class="text-body-secondary fw-normal">· <?= e($ts) ?></span><?php endif; ?></label>
                            <div class="input-group"><input type="text" inputmode="numeric" name="val[<?= (int) $r['id'] ?>]" class="form-control" value="<?= e($val) ?>" placeholder="z. B. 12.345" data-kwh autocomplete="off"><span class="input-group-text">kWh</span></div>
                            <?php if ($pv !== null): ?><div class="form-text">Letzter Stand (<?= e(date('d.m.Y', $prev['ts'])) ?>): <b><?= e(nf($pv, 0)) ?></b> kWh</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-3"><label class="form-label" for="note">Notiz <span class="text-body-secondary fw-normal">(optional)</span></label>
                        <input id="note" name="note" class="form-control" value="<?= e($form['note']) ?>" maxlength="1000" placeholder="z. B. neuer Zähler Nr. 12345, Startwert 0"></div>
                    <?php if ($isNew): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="notify" id="notify" value="1" <?= !empty($form['notify']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify">Per E-Mail zusenden<?= info(($meter['access'] ?? 'owner') === 'owner' ? 'Du erhältst den Zählerstand samt Fotos per E-Mail, z. B. um ihn beim Netzbetreiber einzutragen.' : 'Du erhältst eine Info-Mail mit dem Zählerstand. Der Eigentümer des Zählers bekommt immer die Mail mit den Fotos.') ?></label>
                    </div>
                    <?php endif; ?>
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
                        <div id="camlayer" class="camlayer" hidden>
                            <video id="camvideo" playsinline muted autoplay></video>
                            <div class="cambar">
                                <button type="button" class="btn btn-light" id="cam-cancel"><i class="bi bi-x-lg"></i> Abbrechen</button>
                                <button type="button" class="btn btn-primary btn-lg" id="cam-shot"><i class="bi bi-camera-fill"></i> Aufnehmen</button>
                            </div>
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

<div class="modal fade" id="plausModal" tabindex="-1" aria-labelledby="plausTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="plausTitle"><i class="bi bi-exclamation-triangle text-warning"></i> Unplausibler Wert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <ul class="mb-3" id="plausList"></ul>
                <p class="mb-0">Soll die Ablesung trotzdem gespeichert werden?<br><small class="text-body-secondary">Bei einem Zählerwechsel wähle stattdessen den Anlass „Zählerwechsel“.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-pencil"></i> Wert prüfen</button>
                <button type="button" class="btn btn-warning" id="plausSave"><i class="bi bi-check-lg"></i> Trotzdem speichern</button>
            </div>
        </div>
    </div>
</div>
