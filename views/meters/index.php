<div class="pagehead"><h2>Zähler</h2><a role="button" href="<?= e(url('meters/new')) ?>">+ Neuer Zähler</a></div>
<?php if (!$meters): ?>
    <article><p>Noch kein Zähler angelegt. Lege deinen ersten Zähler an und definiere seine Register (z. B. 1.8.0 Bezug, 1.8.1 HT, 1.8.2 NT, 2.8.0 Einspeisung).</p></article>
<?php endif; ?>
<div class="cards">
<?php foreach ($meters as $m): ?>
    <article class="<?= $m['archived'] ? 'archived' : '' ?>">
        <header><a href="<?= e(url('meters/' . $m['id'])) ?>"><b><?= e($m['name']) ?></b></a>
            <?php if ($m['archived']): ?><span class="badge">archiviert</span><?php endif; ?></header>
        <p class="small muted"><?= $m['kind'] === 'analog' ? 'Analog' : 'Digital' ?><?= $m['location'] !== '' ? ' · ' . e($m['location']) : '' ?></p>
        <ul class="small">
            <?php foreach ($m['registers'] as $r): ?><li><?= e(Repo::registerTitle($r)) ?> <span class="badge"><?= e($r['tariff']) ?></span></li><?php endforeach; ?>
        </ul>
        <p class="small">Letzte Ablesung: <b><?= e(fmt_date($m['last_read'], 'd.m.Y H:i')) ?></b></p>
        <footer class="actions">
            <a role="button" href="<?= e(url('meters/' . $m['id'] . '/readings/new')) ?>">Ablesen</a>
            <a role="button" class="secondary outline" href="<?= e(url('meters/' . $m['id'])) ?>">Details</a>
        </footer>
    </article>
<?php endforeach; ?>
</div>
