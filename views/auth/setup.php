<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1>Ersteinrichtung</h1><p>meterio by MeckByte</p></header>
    <p>Lege das erste Administrator-Konto an. Diese Seite ist danach gesperrt.</p>
    <form method="post" action="<?= e(url('setup')) ?>">
        <?= csrf_field() ?>
        <label>Name <input name="name" value="<?= e($old['name'] ?? '') ?>" required autofocus></label>
        <label>E-Mail <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="username"></label>
        <label>Passwort <input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
        <label>Wiederholen <input type="password" name="password2" minlength="10" autocomplete="new-password" required></label>
        <small>Mindestens 10 Zeichen, mit Buchstaben und Ziffern.</small>
        <button type="submit">Admin anlegen</button>
    </form>
</article>
