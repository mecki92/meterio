<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1>Eigenes Passwort wählen</h1><p>meterio by MeckByte</p></header>
    <p>Du hast dich mit einem temporären Passwort angemeldet. Bitte lege jetzt ein eigenes fest.</p>
    <form method="post" action="<?= e(url('password/force')) ?>">
        <?= csrf_field() ?>
        <label>Neues Passwort <input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus></label>
        <label>Wiederholen <input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
        <button type="submit">Weiter</button>
    </form>
</article>
