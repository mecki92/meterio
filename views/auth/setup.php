<div class="card mt-4">
    <?php $heading = 'Ersteinrichtung'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <p>Lege das erste Administrator-Konto an. Diese Seite ist danach gesperrt.</p>
        <form method="post" action="<?= e(url('setup')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required autofocus></div>
            <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required autocomplete="username"></div>
            <div class="mb-3"><label class="form-label">Passwort<?= info('Mindestens 10 Zeichen, mit Buchstaben und Ziffern.') ?></label><input type="password" name="password" class="form-control" minlength="10" autocomplete="new-password" required></div>
            <div class="mb-3"><label class="form-label">Wiederholen</label><input type="password" name="password2" class="form-control" minlength="10" autocomplete="new-password" required></div>
            <button type="submit" class="btn btn-primary w-100">Admin anlegen</button>
        </form>
    </div>
</div>
