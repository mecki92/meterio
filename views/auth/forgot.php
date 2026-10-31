<div class="card mt-4">
    <?php $heading = 'Passwort vergessen'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <p>Gib deine E-Mail-Adresse ein. Du erhältst einen Link zum Zurücksetzen (1 Stunde gültig).</p>
        <form method="post" action="<?= e(url('forgot')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" required autofocus autocomplete="username"></div>
            <button type="submit" class="btn btn-primary w-100">Link anfordern</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="<?= e(url('login')) ?>">Zurück zur Anmeldung</a></p>
    </div>
</div>
