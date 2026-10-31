<?php /** @var array $releases */
$types = ['new' => ['Neu', 'success'], 'imp' => ['Verbessert', 'info'], 'fix' => ['Behoben', 'warning']]; ?>
<div class="doc-page">
<div class="page-head">
    <div>
        <h1>Versionen</h1>
        <p class="sub">Was sich in meterio geändert hat · aktuelle Version <?= e(APP_VERSION) ?></p>
    </div>
</div>
<?php foreach ($releases as $i => $rel): ?>
    <div class="card rel-card">
        <div class="card-header split">
            <span><i class="bi bi-tag"></i> Version <?= e($rel['version']) ?><?= $i === 0 ? ' <span class="chip chip-success">aktuell</span>' : '' ?></span>
            <span class="small text-body-secondary fw-normal"><?= e(fmt_date($rel['date'])) ?></span>
        </div>
        <div class="card-body">
            <p class="fw-semibold mb-2"><?= e($rel['title']) ?></p>
            <ul class="rel-list">
                <?php foreach ($rel['items'] as [$t, $text]): ?>
                    <li><span class="chip chip-<?= e($types[$t][1]) ?>"><?= e($types[$t][0]) ?></span><span><?= e($text) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endforeach; ?>
</div>
