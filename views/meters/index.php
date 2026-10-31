<div class="page-head">
    <div><h1>Zähler</h1><p class="sub"><?= count($meters) ?> Zähler</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('shares/redeem')) ?>"><i class="bi bi-key"></i> Freigabecode einlösen</a>
        <a class="btn btn-primary" href="<?= e(url('meters/new')) ?>"><i class="bi bi-plus-lg"></i> Neuer Zähler</a>
    </div>
</div>
<?php if (!$meters && !$shared): ?>
    <div class="alert alert-info">Noch kein Zähler angelegt. Lege deinen ersten Zähler an: analog (Eintarif oder HT/NT) oder digital mit OBIS-Registern (z. B. 1.8.0, 1.8.1 HT, 1.8.2 NT, 2.8.0 Einspeisung).</div>
<?php endif; ?>
<?php foreach ($meters as $m): $stats = $m['stats']; $meter = $m; ?>
<div class="card mb-3 <?= $m['archived'] ? 'opacity-75' : '' ?>">
    <div class="card-header split">
        <span>
            <a class="text-decoration-none" href="<?= e(url('meters/' . $m['id'])) ?>"><i class="bi <?= $m['kind'] === 'analog' ? 'bi-speedometer' : 'bi-cpu' ?>"></i> <?= e($m['name']) ?></a>
            <?php if ($m['archived']): ?><span class="chip chip-muted ms-2">archiviert</span><?php endif; ?>
        </span>
        <span class="small fw-normal text-body-secondary"><?= $m['kind'] === 'analog' ? 'Analoger Zähler' : 'Digitaler Zähler' ?><?= $m['location'] !== '' ? ' · ' . e($m['location']) : '' ?></span>
    </div>
    <div class="card-body"><?php require __DIR__ . '/_summary.php'; ?></div>
    <div class="card-footer d-flex flex-wrap gap-3 align-items-center">
        <a class="btn btn-primary btn-sm" href="<?= e(url('meters/' . $m['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesung erfassen</a>
        <a class="text-decoration-none" href="<?= e(url('meters/' . $m['id'])) ?>">Details &amp; Ablesungen <i class="bi bi-arrow-right"></i></a>
    </div>
</div>
<?php endforeach; ?>
<?php if ($shared): ?>
<h2 class="h5 mt-4 mb-3"><i class="bi bi-people"></i> Mit mir geteilt</h2>
<?php foreach ($shared as $m): $stats = $m['stats']; $meter = $m; $canW = $m['access'] === 'write'; ?>
<div class="card mb-3">
    <div class="card-header split">
        <span><a class="text-decoration-none" href="<?= e(url('meters/' . $m['id'])) ?>"><i class="bi <?= $m['kind'] === 'analog' ? 'bi-speedometer' : 'bi-cpu' ?>"></i> <?= e($m['name']) ?></a>
            <span class="chip ms-2"><?= $canW ? 'Ablesen &amp; erfassen' : 'Nur ansehen' ?></span></span>
        <span class="small fw-normal text-body-secondary">von <?= e($m['owner_name']) ?><?= $m['location'] !== '' ? ' · ' . e($m['location']) : '' ?></span>
    </div>
    <div class="card-body"><?php require __DIR__ . '/_summary.php'; ?></div>
    <div class="card-footer d-flex flex-wrap gap-3 align-items-center">
        <?php if ($canW): ?><a class="btn btn-primary btn-sm" href="<?= e(url('meters/' . $m['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesung erfassen</a><?php endif; ?>
        <a class="text-decoration-none" href="<?= e(url('meters/' . $m['id'])) ?>">Details &amp; Ablesungen <i class="bi bi-arrow-right"></i></a>
    </div>
</div>
<?php endforeach; endif; ?>
