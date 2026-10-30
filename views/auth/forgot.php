<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1>Passwort vergessen</h1><p>meterio by MeckByte</p></header>
    <p>Gib deine E-Mail-Adresse ein. Du erhältst einen Link zum Zurücksetzen (1 Stunde gültig).</p>
    <form method="post" action="<?= e(url('forgot')) ?>">
        <?= csrf_field() ?>
        <label>E-Mail <input type="email" name="email" required autofocus autocomplete="username"></label>
        <button type="submit">Link anfordern</button>
    </form>
    <p class="center"><a href="<?= e(url('login')) ?>">Zurück zur Anmeldung</a></p>
</article>
