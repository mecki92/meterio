<div class="page-head">
    <div><h1>Stromverträge</h1><p class="sub"><?= count($rows) ?> Vertrag/Verträge</p></div>
    <a class="btn btn-primary" href="<?= e(url('contracts/new')) ?>"><i class="bi bi-plus-lg"></i> Neuer Vertrag</a>
</div>
<?php if (!$rows): ?><div class="alert alert-info">Noch kein Vertrag hinterlegt. Mit Vertragsdaten und Abschlägen berechnet meterio Realkosten, Prognose und Nachzahlung.</div><?php endif; ?>
<?php foreach ($rows as ['c' => $c, 'meter' => $m, 'sum' => $sum]): ?>
<div class="card mb-3">
    <div class="card-header split">
        <a class="text-decoration-none" href="<?= e(url('contracts/' . $c['id'])) ?>"><i class="bi bi-file-earmark-text"></i> <?= e($c['data']['provider'] ?: 'Vertrag #' . $c['id']) ?></a><?php if (strtotime($c['start_date']) > time()): ?> <span class="chip chip-info ms-2"><i class="bi bi-calendar-event"></i> beginnt am <?= e(fmt_date($c['start_date'])) ?></span><?php endif; ?>
        <span class="small fw-normal text-body-secondary"><i class="bi bi-speedometer2"></i> <?= e($m['name'] ?? '?') ?> · <?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?></span>
    </div>
    <div class="card-body"><?php if ($sum) { require __DIR__ . '/_summary.php'; } else { echo '<p class="mb-0 text-body-secondary">Kein Zähler zugeordnet.</p>'; } ?></div>
    <div class="card-footer"><a class="text-decoration-none" href="<?= e(url('contracts/' . $c['id'])) ?>">Details &amp; Zahlungen <i class="bi bi-arrow-right"></i></a></div>
</div>
<?php endforeach; ?>
