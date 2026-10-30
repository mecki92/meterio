<div class="pagehead">
    <h2><?= e($meter['name']) ?> <?php if ($meter['archived']): ?><span class="badge">archiviert</span><?php endif; ?></h2>
    <div class="actions">
        <a role="button" href="<?= e(url('meters/' . $meter['id'] . '/readings/new')) ?>">+ Ablesung</a>
        <a role="button" class="secondary outline" href="<?= e(url('meters/' . $meter['id'] . '/edit')) ?>">Bearbeiten</a>
    </div>
</div>
<p class="muted small"><?= $meter['kind'] === 'analog' ? 'Analoger Zähler' : 'Digitaler Zähler' ?><?= $meter['location'] !== '' ? ' · ' . e($meter['location']) : '' ?><?= $meter['note'] !== '' ? ' · ' . e($meter['note']) : '' ?></p>

<article>
    <header>Ablesungen (<?= count($readings) ?>)</header>
    <?php if (!$readings): ?>
        <p>Noch keine Ablesung erfasst.</p>
    <?php else: ?>
    <div class="tablewrap">
    <table class="striped">
        <thead><tr><th>Datum</th><?php foreach ($meter['registers'] as $r): ?><th class="num"><?= e($r['label']) ?><br><small><?= e($r['obis']) ?></small></th><?php endforeach; ?><th>Fotos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($readings as $rd): ?>
            <tr>
                <td><?= e(date('d.m.Y H:i', $rd['ts'])) ?><?= $rd['note'] !== '' ? '<br><small class="muted">' . e($rd['note']) . '</small>' : '' ?></td>
                <?php foreach ($meter['registers'] as $r): ?><td class="num"><?= isset($rd['values'][$r['id']]) ? e(nf($rd['values'][$r['id']], 1)) : '–' ?></td><?php endforeach; ?>
                <td>
                    <?php foreach ($rd['photos'] as $i => $p): ?>
                        <a href="<?= e(url('photos/' . $p['id'])) ?>" target="_blank" rel="noopener" title="Foto <?= $i + 1 ?>">📷</a>
                    <?php endforeach; ?>
                </td>
                <td class="num"><a href="<?= e(url('readings/' . $rd['id'] . '/edit')) ?>">Bearbeiten</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</article>

<details class="danger">
    <summary>Zähler löschen</summary>
    <p>Löscht den Zähler mit allen Registern, Ablesungen, Fotos und zugehörigen Verträgen/Zahlungen unwiderruflich.</p>
    <form method="post" action="<?= e(url('meters/' . $meter['id'] . '/delete')) ?>" data-confirm="Zähler „<?= e($meter['name']) ?>“ wirklich endgültig löschen?">
        <?= csrf_field() ?><button type="submit" class="contrast">Endgültig löschen</button>
    </form>
</details>
