<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1>meterio</h1><p>by MeckByte</p></header>
    <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
        <?= csrf_field() ?>
        <label>E-Mail
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </label>
        <label>Passwort
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Anmelden</button>
    </form>
    <p class="center"><a href="<?= e(url('forgot')) ?>">Passwort vergessen?</a></p>
    <p class="muted center small">Nach dem Passwort folgt ein Zwei-Faktor-Code.</p>
</article>
