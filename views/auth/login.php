<div class="card mt-4">
    <?php $heading = 'meterio'; require __DIR__ . '/_head.php'; ?>
    <div class="card-body">
        <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">E-Mail</label>
                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" autocomplete="username" required autofocus></div>
            <div class="mb-3"><label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-control" autocomplete="current-password" required></div>
            <button type="submit" class="btn btn-primary w-100">Anmelden</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="<?= e(url('forgot')) ?>">Passwort vergessen?</a></p>
    </div>
</div>
