<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h2 class="h3 m-0"><?= e($meter['name']) ?> <?php if ($meter['archived']): ?><span class="badge text-bg-secondary fs-6">archiviert</span><?php endif; ?></h2>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('meters/' . $meter['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesung</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('meters/' . $meter['id'] . '/edit')) ?>">Bearbeiten</a>
    </div>
</div>
<p class="text-body-secondary small"><?= $meter['kind'] === 'analog' ? 'Analoger Zähler' : 'Digitaler Zähler' ?><?= $meter['location'] !== '' ? ' · ' . e($meter['location']) : '' ?><?= $meter['note'] !== '' ? ' · ' . e($meter['note']) : '' ?></p>

<div class="card mb-4">
    <div class="card-header">Ablesungen (<?= count($readings) ?>)</div>
    <?php if (!$readings): ?>
        <div class="card-body">Noch keine Ablesung erfasst.</div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0">
        <thead><tr><th>Datum</th><?php foreach ($meter['registers'] as $r): ?><th class="num"><?= e($r['label']) ?><?= $r['obis'] !== '' ? '<br><small class="fw-normal text-body-secondary">' . e($r['obis']) . '</small>' : '' ?></th><?php endforeach; ?><th>Fotos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($readings as $rd): ?>
            <tr>
                <td><?= e(date('d.m.Y H:i', $rd['ts'])) ?><?php if ($rd['reason'] !== 'regular'): [$rl, $rc] = reading_reasons()[$rd['reason']] ?? ['Sonstiges', 'secondary']; ?> <span class="badge text-bg-<?= e($rc) ?>"><?= e($rl) ?></span><?php endif; ?><?= $rd['note'] !== '' ? '<br><small class="text-body-secondary">' . e($rd['note']) . '</small>' : '' ?></td>
                <?php foreach ($meter['registers'] as $r): ?><td class="num"><?= isset($rd['values'][$r['id']]) ? e(nf($rd['values'][$r['id']], 0)) : '–' ?></td><?php endforeach; ?>
                <td><?php foreach ($rd['photos'] as $i => $p): ?><a href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener" title="Foto <?= $i + 1 ?>"><i class="bi bi-camera"></i></a> <?php endforeach; ?></td>
                <td class="num"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('readings/' . $rd['id'] . '/edit')) ?>">Bearbeiten</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<details class="mt-4">
    <summary class="text-danger">Zähler löschen</summary>
    <p class="mt-2">Löscht den Zähler mit allen Registern, Ablesungen, Fotos und zugehörigen Verträgen/Zahlungen unwiderruflich.</p>
    <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/delete')) ?>" data-confirm="Zähler „<?= e($meter['name']) ?>“ wirklich endgültig löschen?">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger">Endgültig löschen</button>
    </form>
</details>
