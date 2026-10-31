<div class="card mt-4">
    <?php $heading = 'Zwei-Faktor-Code'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <?php if ($method === 'totp'): ?>
            <p>Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
        <?php else: ?>
            <p>Wir haben einen 6-stelligen Code an <b><?= e($email) ?></b> gesendet (10 Minuten gültig).</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('login/2fa')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Code</label>
                <input type="text" name="code" class="form-control form-control-lg text-center" inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="123456" required autofocus></div>
            <button type="submit" class="btn btn-primary w-100">Bestätigen</button>
        </form>
        <p class="small text-body-secondary mt-3">Authenticator nicht verfügbar? Gib stattdessen einen deiner Backup-Codes ein (Format <code>ABCDE-FGHJK</code>).</p>
        <?php if ($method === 'email'): ?>
            <form method="post" action="<?= e(url('login/2fa/resend')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Neuen Code senden</button></form>
        <?php endif; ?>
        <p class="text-center mt-3 mb-0"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
    </div>
</div>
