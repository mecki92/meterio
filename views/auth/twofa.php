<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1>Zwei-Faktor-Code</h1><p>meterio by MeckByte</p></header>
    <?php if ($method === 'totp'): ?>
        <p>Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
    <?php else: ?>
        <p>Wir haben einen 6-stelligen Code an <b><?= e($email) ?></b> gesendet (10 Minuten gültig).</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('login/2fa')) ?>">
        <?= csrf_field() ?>
        <label>Code
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="123456" required autofocus>
        </label>
        <button type="submit">Bestätigen</button>
    </form>
    <p class="small muted">Authenticator nicht verfügbar? Gib stattdessen einen deiner Backup-Codes ein (Format <code>ABCDE-FGHJK</code>).</p>
    <?php if ($method === 'email'): ?>
        <form method="post" action="<?= e(url('login/2fa/resend')) ?>"><?= csrf_field() ?><button class="secondary outline" type="submit">Neuen Code senden</button></form>
    <?php endif; ?>
    <p class="center"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
</article>
