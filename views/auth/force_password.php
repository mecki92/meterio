<div class="card mt-4">
    <?php $heading = 'Eigenes Passwort wählen'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <p>Du hast dich mit einem temporären Passwort angemeldet. Bitte lege jetzt ein eigenes fest.</p>
        <form method="post" action="<?= e(url('password/force')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Neues Passwort</label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="10" required autofocus></div>
            <div class="mb-3"><label class="form-label">Wiederholen</label><input type="password" name="password2" class="form-control" autocomplete="new-password" minlength="10" required></div>
            <button type="submit" class="btn btn-primary w-100">Weiter</button>
        </form>
    </div>
</div>
