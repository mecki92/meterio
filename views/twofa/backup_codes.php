<h2>Deine Backup-Codes</h2>
<div class="flash flash-warn">Diese Codes werden <b>nur jetzt einmal</b> angezeigt. Jeder Code funktioniert genau einmal – als Ersatz, wenn du keinen Zugriff auf Authenticator-App oder E-Mail hast. Bewahre sie sicher auf (Passwortmanager oder Ausdruck).</div>
<article>
    <ul class="codes" id="codes">
        <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
    </ul>
    <div class="actions">
        <button type="button" data-copy="#codes" class="secondary">Kopieren</button>
        <button type="button" data-print class="secondary outline">Drucken</button>
        <a role="button" href="<?= e(url('dashboard')) ?>">Ich habe die Codes gesichert</a>
    </div>
</article>
