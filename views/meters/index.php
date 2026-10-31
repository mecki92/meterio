<div class="page-head">
    <div><h1>Zähler</h1><p class="sub"><?= count($meters) ?> Zähler</p></div>
    <a class="btn btn-primary" href="<?= e(url('meters/new')) ?>"><i class="bi bi-plus-lg"></i> Neuer Zähler</a>
</div>
<?php if (!$meters): ?>
    <div class="alert alert-info">Noch kein Zähler angelegt. Lege deinen ersten Zähler an: analog (Eintarif oder HT/NT) oder digital mit OBIS-Registern (z. B. 1.8.0, 1.8.1 HT, 1.8.2 NT, 2.8.0 Einspeisung).</div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($meters as $m): ?>
    <div class="col-md-6 col-xl-4"><div class="card meter-card h-100 <?= $m['archived'] ? 'opacity-50' : '' ?>">
        <div class="card-body">
            <div class="d-flex gap-3 align-items-start">
                <div class="icon-tile"><i class="bi <?= $m['kind'] === 'analog' ? 'bi-speedometer' : 'bi-cpu' ?>"></i></div>
                <div class="flex-grow-1" style="min-width:0">
                    <a href="<?= e(url('meters/' . $m['id'])) ?>" class="stretched-link text-truncate d-block"><?= e($m['name']) ?></a>
                    <div class="small text-body-secondary"><?= $m['kind'] === 'analog' ? 'Analoger Zähler' : 'Digitaler Zähler' ?><?= $m['location'] !== '' ? ' · ' . e($m['location']) : '' ?></div>
                </div>
                <?php if ($m['archived']): ?><span class="badge text-bg-secondary">archiviert</span><?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-3">
                <?php foreach ($m['registers'] as $r): ?><span class="chip"><?= e(Repo::registerTitle($r)) ?> <b><?= e($r['tariff']) ?></b></span><?php endforeach; ?>
            </div>
            <div class="small text-body-secondary mt-3"><i class="bi bi-clock-history"></i> Letzte Ablesung: <span class="text-body"><?= e($m['last_read'] ? fmt_date($m['last_read'], 'd.m.Y H:i') : '–') ?></span></div>
        </div>
        <div class="card-footer d-flex gap-2 border-0 pt-0" style="position:relative;z-index:2">
            <a class="btn btn-primary btn-sm" href="<?= e(url('meters/' . $m['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesen</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('meters/' . $m['id'])) ?>">Details</a>
        </div>
    </div></div>
<?php endforeach; ?>
</div>
