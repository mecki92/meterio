<div class="card mt-4">
    <?php $heading = 'Zwei-Faktor-Code'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <?php if ($method === 'totp'): ?>
            <p>Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
        <?php else: ?>
            <p>Wir haben einen 6-stelligen Code an <b><?= e($email) ?></b> gesendet.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('login/2fa')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Code</label>
                <input type="text" name="code" class="form-control form-control-lg text-center" inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="123456" required autofocus></div>
            <button type="submit" class="btn btn-primary w-100">Bestätigen</button>
        </form>
        <div class="hint-box mt-3">
            <i class="bi bi-life-preserver"></i>
            <div><b>Kein Zugriff auf deinen Code?</b><br>Gib stattdessen einen deiner Backup-Codes ein, z. B. <code>ABCDE-FGHJK</code>.</div>
        </div>
        <?php if ($method === 'email'): ?>
            <form class="mt-3" method="post" action="<?= e(url('login/2fa/resend')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Neuen Code senden</button></form>
        <?php endif; ?>
        <p class="text-center mt-3 mb-0"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
    </div>
</div>
