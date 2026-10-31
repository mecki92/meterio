<h2 class="h3 mb-3">Deine Backup-Codes</h2>
<div class="alert alert-warning">Diese Codes werden <b>nur jetzt einmal</b> angezeigt. Jeder Code funktioniert genau einmal – als Ersatz, wenn du keinen Zugriff auf Authenticator-App oder E-Mail hast. Bewahre sie sicher auf (Passwortmanager oder Ausdruck).</div>
<div class="card"><div class="card-body">
    <ul class="codes" id="codes">
        <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
    </ul>
    <div class="d-flex flex-wrap gap-2 no-print">
        <button type="button" data-copy="#codes" class="btn btn-outline-secondary"><i class="bi bi-clipboard"></i> Kopieren</button>
        <button type="button" data-print class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Drucken</button>
        <a class="btn btn-primary" href="<?= e(url('dashboard')) ?>">Ich habe die Codes gesichert</a>
    </div>
</div></div>
