<div class="pagehead"><h2>Stromverträge</h2><a role="button" href="<?= e(url('contracts/new')) ?>">+ Neuer Vertrag</a></div>
<?php if (!$rows): ?><article><p>Noch kein Vertrag hinterlegt. Mit Vertragsdaten und Abschlägen berechnet meterio Realkosten, Prognose und Nachzahlung.</p></article><?php endif; ?>
<?php foreach ($rows as ['c' => $c, 'meter' => $m, 'sum' => $sum]): ?>
<article>
    <header>
        <a href="<?= e(url('contracts/' . $c['id'])) ?>"><b><?= e($c['data']['provider'] ?: 'Vertrag #' . $c['id']) ?></b></a>
        <span class="muted small"> · <?= e($m['name'] ?? '?') ?> · <?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?></span>
    </header>
    <?php if ($sum) { $sum = $sum; require __DIR__ . '/_summary.php'; } ?>
    <footer><a href="<?= e(url('contracts/' . $c['id'])) ?>">Details &amp; Zahlungen</a></footer>
</article>
<?php endforeach; ?>
