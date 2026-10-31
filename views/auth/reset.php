<div class="card mt-4">
    <?php $heading = $invite ? 'Passwort festlegen' : 'Neues Passwort'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <form method="post" action="<?= e(url(($invite ? 'invite/' : 'reset/') . $token)) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Neues Passwort</label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="10" required autofocus></div>
            <div class="mb-1"><label class="form-label">Wiederholen</label><input type="password" name="password2" class="form-control" autocomplete="new-password" minlength="10" required></div>
            <div class="form-text mb-3">Mindestens 10 Zeichen, mit Buchstaben und Ziffern.</div>
            <button type="submit" class="btn btn-primary w-100">Speichern</button>
        </form>
    </div>
</div>
